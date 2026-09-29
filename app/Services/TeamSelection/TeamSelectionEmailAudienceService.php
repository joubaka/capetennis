<?php

namespace App\Services\TeamSelection;

use App\Models\Event;
use App\Models\EventRegion;
use App\Models\EventRegionRankingSource;
use App\Models\SeriesRanking;
use App\Models\Team;
use App\Models\TeamSelectionImport;
use App\Models\TeamSelectionInvitation;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class TeamSelectionEmailAudienceService
{
    private const ACTIVE_IMPORT_STATUSES = ['draft', 'sent'];

    public function __construct(private TeamSelectionContactService $contacts) {}

    /**
     * @return Collection<int, array{email:string,name:string,category:string,status:string}>
     */
    public function resolve(Event $event, EventRegion $eventRegion, array $filters): Collection
    {
        $categoryIds = collect($filters['category_event_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($categoryIds->isEmpty()) {
            throw ValidationException::withMessages([
                'category_event_ids' => 'Select at least one age group.',
            ]);
        }

        $validCategoryIds = Team::query()
            ->withoutGlobalScopes()
            ->where('region_id', $eventRegion->region_id)
            ->whereIn('category_event_id', $categoryIds)
            ->whereHas('category', fn ($query) => $query->where('event_id', $event->id))
            ->pluck('category_event_id')
            ->unique()
            ->values();
        if ($validCategoryIds->count() !== $categoryIds->count()) {
            throw ValidationException::withMessages([
                'category_event_ids' => 'One or more selected age groups do not belong to this event.',
            ]);
        }

        $activeImportId = TeamSelectionImport::query()
            ->where('event_id', $event->id)
            ->where('region_id', $eventRegion->region_id)
            ->whereIn('status', self::ACTIVE_IMPORT_STATUSES)
            ->latest('id')
            ->value('id');

        if (! $activeImportId) {
            throw ValidationException::withMessages([
                'audience_status' => 'This region has no current team-selection roster to email.',
            ]);
        }

        $invitations = TeamSelectionInvitation::query()
            ->with(['player.user', 'player.users', 'team.category.category'])
            ->where('event_id', $event->id)
            ->where('region_id', $eventRegion->region_id)
            ->where('import_id', $activeImportId)
            ->whereHas('team', fn ($query) => $query->whereIn('category_event_id', $categoryIds))
            ->get();

        $gender = $filters['gender'] ?? 'any';
        if ($gender !== 'any') {
            $genderId = $gender === 'boys' ? 1 : 2;
            $invitations = $invitations->filter(fn (TeamSelectionInvitation $invitation) => (int) $invitation->player?->gender === $genderId);
        }

        $audience = $filters['audience_status'] ?? 'active';
        $invitations = $invitations->filter(fn (TeamSelectionInvitation $invitation) => $this->matchesStatus($invitation, $audience));

        return $invitations
            ->map(function (TeamSelectionInvitation $invitation): array {
                return [
                    'invitation_id' => (int) $invitation->id,
                    'team_id' => (int) $invitation->team_id,
                    'email' => (string) $this->contacts->primaryEmail($invitation->player),
                    'name' => $this->displayLabel((string) ($invitation->player?->full_name ?? 'Player')),
                    'category' => $this->displayLabel((string) ($invitation->team?->category?->category?->name ?? $invitation->team?->name ?? 'Age group')),
                    'status' => $invitation->status,
                ];
            })
            ->filter(fn (array $recipient) => filled($recipient['email']))
            ->map(fn (array $recipient) => [...$recipient, 'email' => mb_strtolower(trim($recipient['email']))])
            ->unique('email')
            ->sortBy([['category', 'asc'], ['name', 'asc']])
            ->values();
    }

    /**
     * @return Collection<int, array{email:string,name:string,category:string,status:string,region:string}>
     */
    public function resolveForEvent(Event $event, array $filters): Collection
    {
        if (($filters['audience_mode'] ?? 'roster') === 'ranking') {
            return $this->resolveRankingForEvent($event, $filters);
        }

        $regionIds = collect($filters['event_region_ids'] ?? [])
            ->map(fn ($id) => (int) $id)->filter()->unique()->sort()->values();

        if ($regionIds->isEmpty()) {
            throw ValidationException::withMessages(['event_region_ids' => 'Select at least one region.']);
        }

        $eventRegions = EventRegion::query()->with('region')
            ->where('event_id', $event->id)->whereIn('id', $regionIds)->get()->keyBy('id');
        if ($eventRegions->count() !== $regionIds->count()) {
            throw ValidationException::withMessages(['event_region_ids' => 'One or more selected regions do not belong to this event.']);
        }

        $teamIds = collect($filters['team_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->sort()->values();
        $invitationIds = collect($filters['invitation_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->sort()->values();
        if ($teamIds->isNotEmpty()) {
            $validTeamIds = Team::query()->withoutGlobalScopes()
                ->whereIn('id', $teamIds)
                ->whereIn('region_id', $eventRegions->pluck('region_id'))
                ->whereHas('category', fn ($query) => $query->where('event_id', $event->id))
                ->pluck('id')->sort()->values();
            if ($validTeamIds->all() !== $teamIds->all()) {
                throw ValidationException::withMessages(['team_ids' => 'One or more selected teams do not belong to the selected event regions.']);
            }
        }

        $categoryIds = collect($filters['category_event_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($teamIds->isNotEmpty()) {
            $categoryIds = Team::query()->withoutGlobalScopes()->whereIn('id', $teamIds)
                ->pluck('category_event_id')->filter()->unique()->values();
        }
        $validCategoryIds = Team::query()->withoutGlobalScopes()
            ->whereIn('region_id', $eventRegions->pluck('region_id'))
            ->whereIn('category_event_id', $categoryIds)
            ->whereHas('category', fn ($query) => $query->where('event_id', $event->id))
            ->pluck('category_event_id')->unique()->values();
        if ($categoryIds->isEmpty() || $validCategoryIds->count() !== $categoryIds->count()) {
            throw ValidationException::withMessages(['category_event_ids' => 'One or more selected age groups do not belong to the selected event regions.']);
        }

        $recipients = $regionIds->flatMap(function (int $eventRegionId) use ($event, $eventRegions, $filters, $categoryIds, $teamIds): Collection {
            $eventRegion = $eventRegions->get($eventRegionId);
            $regionalCategoryIds = Team::query()->withoutGlobalScopes()
                ->where('region_id', $eventRegion->region_id)
                ->whereIn('category_event_id', $categoryIds)
                ->pluck('category_event_id')->unique()->values();
            if ($regionalCategoryIds->isEmpty()) {
                return collect();
            }
            $hasActiveImport = TeamSelectionImport::query()->where('event_id', $event->id)
                ->where('region_id', $eventRegion->region_id)
                ->whereIn('status', self::ACTIVE_IMPORT_STATUSES)->exists();
            if (! $hasActiveImport) {
                return collect();
            }

            return $this->resolve($event, $eventRegion, [...$filters, 'category_event_ids' => $regionalCategoryIds->all()])
                ->when($teamIds->isNotEmpty(), fn (Collection $items) => $items->whereIn('team_id', $teamIds))
                ->map(fn (array $recipient): array => [
                    ...$recipient,
                    'region' => $this->displayLabel((string) ($eventRegion->region?->region_name ?? 'Region')),
                ]);
        });

        if ($invitationIds->isNotEmpty()) {
            $activeImportIds = $eventRegions->pluck('region_id')->map(fn ($regionId) => TeamSelectionImport::query()
                ->where('event_id', $event->id)->where('region_id', $regionId)
                ->whereIn('status', self::ACTIVE_IMPORT_STATUSES)->latest('id')->value('id'))->filter();
            $validInvitationIds = TeamSelectionInvitation::query()
                ->where('event_id', $event->id)->whereIn('region_id', $eventRegions->pluck('region_id'))
                ->whereIn('import_id', $activeImportIds)->whereIn('team_id', $teamIds)->whereIn('id', $invitationIds)
                ->pluck('id')->sort()->values();
            if ($validInvitationIds->all() !== $invitationIds->all()) {
                throw ValidationException::withMessages(['invitation_ids' => 'One or more selected players do not belong to the selected teams and regions.']);
            }
            $recipients = $recipients->whereIn('invitation_id', $invitationIds);
        }

        return $recipients->groupBy('email')->map(function (Collection $matches): array {
            $first = $matches->first();

            return [
                ...$first,
                'category' => $matches->pluck('category')->unique()->sort()->implode(', '),
                'region' => $matches->pluck('region')->unique()->sort()->implode(', '),
            ];
        })->sortBy([['region', 'asc'], ['category', 'asc'], ['name', 'asc']])->values();
    }

    public function eventPreviewHash(Event $event, array $filters, Collection $recipients, string $sendToken): string
    {
        return hash('sha256', json_encode([
            'event_id' => (int) $event->id,
            'audience_mode' => $filters['audience_mode'] ?? 'roster',
            'event_region_ids' => collect($filters['event_region_ids'])->map(fn ($id) => (int) $id)->unique()->sort()->values()->all(),
            'category_event_ids' => collect($filters['category_event_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->sort()->values()->all(),
            'team_ids' => collect($filters['team_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->sort()->values()->all(),
            'invitation_ids' => collect($filters['invitation_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->sort()->values()->all(),
            'series_ranking_ids' => collect($filters['series_ranking_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->sort()->values()->all(),
            'ranking_sources' => $recipients->pluck('source_identity')->filter()->unique()->sort()->values()->all(),
            'gender' => $filters['gender'],
            'audience_status' => $filters['audience_status'],
            'recipients' => $recipients->pluck('email')->map(fn ($email) => mb_strtolower(trim((string) $email)))->sort()->values()->all(),
            'send_token' => $sendToken,
        ], JSON_THROW_ON_ERROR));
    }

    /** @return Collection<int, array<string, mixed>> */
    public function eventSelectionOptions(Event $event, array $filters): Collection
    {
        $regionIds = collect($filters['event_region_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->sort()->values();
        if ($regionIds->isEmpty()) {
            throw ValidationException::withMessages(['event_region_ids' => 'Select at least one region.']);
        }
        $eventRegions = EventRegion::query()->with('region')->where('event_id', $event->id)
            ->whereIn('id', $regionIds)->get()->keyBy('id');
        if ($eventRegions->count() !== $regionIds->count()) {
            throw ValidationException::withMessages(['event_region_ids' => 'One or more selected regions do not belong to this event.']);
        }

        $activeImports = TeamSelectionImport::query()->where('event_id', $event->id)
            ->whereIn('region_id', $eventRegions->pluck('region_id'))
            ->whereIn('status', self::ACTIVE_IMPORT_STATUSES)
            ->orderByDesc('id')->get()->unique('region_id')->pluck('id');
        $activeTeamIds = TeamSelectionInvitation::query()->whereIn('import_id', $activeImports)
            ->pluck('team_id')->filter()->unique();

        $teams = Team::query()->withoutGlobalScopes()->with(['category.category'])
            ->whereIn('region_id', $eventRegions->pluck('region_id'))
            ->whereHas('category', fn ($query) => $query->where('event_id', $event->id))
            ->orderBy('name')->get();

        $sources = EventRegionRankingSource::query()->where('event_id', $event->id)
            ->whereIn('event_region_id', $eventRegions->keys())->get()->keyBy('event_region_id');
        $rankingRowsByTeam = $teams->mapWithKeys(function (Team $team) use ($eventRegions, $sources): array {
            $eventRegion = $eventRegions->firstWhere('region_id', $team->region_id);
            $source = $eventRegion ? $sources->get($eventRegion->id) : null;
            $runId = $source ? $this->publishedRunId((int) $source->series_id) : null;
            $rows = $source && $runId && $team->category?->category_id
                ? SeriesRanking::query()->with(['player.user', 'player.users'])->where('series_id', $source->series_id)
                    ->where('run_id', $runId)->where('status', 'published')
                    ->where('category_id', $team->category->category_id)
                    ->orderBy('rank_position')->orderBy('id')->get()
                : collect();

            return [$team->id => $rows];
        });

        $mode = $filters['audience_mode'] ?? 'roster';

        return $teams->filter(fn (Team $team) => $mode === 'ranking'
            ? $rankingRowsByTeam->get($team->id)?->isNotEmpty()
            : $activeTeamIds->contains($team->id))
            ->map(function (Team $team) use ($eventRegions, $activeImports, $rankingRowsByTeam): array {
                $region = $eventRegions->firstWhere('region_id', $team->region_id);
                $players = TeamSelectionInvitation::query()->with(['player.user', 'player.users'])
                    ->where('event_id', $team->category?->event_id)->where('team_id', $team->id)
                    ->whereIn('import_id', $activeImports)->orderBy('queue_position')->get()
                    ->map(fn (TeamSelectionInvitation $invitation): array => [
                        'invitation_id' => (int) $invitation->id,
                        'name' => $this->displayLabel((string) ($invitation->player?->full_name ?? 'Player')),
                        'status' => $invitation->status,
                        'has_email' => filled($this->contacts->primaryEmail($invitation->player)),
                    ])->values();
                $rankingPlayers = $rankingRowsByTeam->get($team->id, collect())->map(fn (SeriesRanking $row): array => [
                    'series_ranking_id' => (int) $row->id,
                    'name' => $this->displayLabel((string) ($row->player?->full_name ?? 'Player')),
                    'status' => 'Rank '.(int) $row->rank_position,
                    'has_email' => filled($this->contacts->primaryEmail($row->player)),
                ])->values();

                return [
                    'team_id' => (int) $team->id,
                    'name' => $this->displayLabel((string) $team->name),
                    'category' => $this->displayLabel((string) ($team->category?->category?->name ?? $team->name)),
                    'region' => $this->displayLabel((string) ($region?->region?->region_name ?? 'Region')),
                    'players' => $players,
                    'ranking_players' => $rankingPlayers,
                ];
            })->sortBy([['region', 'asc'], ['category', 'asc'], ['name', 'asc']])->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function resolveRankingForEvent(Event $event, array $filters): Collection
    {
        $regionIds = collect($filters['event_region_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->sort()->values();
        $teamIds = collect($filters['team_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->sort()->values();
        $rowIds = collect($filters['series_ranking_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->unique()->sort()->values();
        if ($regionIds->isEmpty() || $teamIds->isEmpty() || $rowIds->isEmpty()) {
            throw ValidationException::withMessages(['series_ranking_ids' => 'Select at least one ranked player.']);
        }
        $eventRegions = EventRegion::query()->with(['region', 'rankingSource'])->where('event_id', $event->id)
            ->whereIn('id', $regionIds)->get();
        if ($eventRegions->count() !== $regionIds->count() || $eventRegions->contains(fn (EventRegion $region) => ! $region->rankingSource)) {
            throw ValidationException::withMessages(['event_region_ids' => 'Every selected region must have a linked ranking source.']);
        }
        $teams = Team::query()->withoutGlobalScopes()->with(['category.category'])->whereIn('id', $teamIds)
            ->whereIn('region_id', $eventRegions->pluck('region_id'))
            ->whereHas('category', fn ($query) => $query->where('event_id', $event->id))->get();
        if ($teams->count() !== $teamIds->count()) {
            throw ValidationException::withMessages(['team_ids' => 'One or more selected teams do not belong to the selected event regions.']);
        }

        $allowed = collect();
        foreach ($eventRegions as $eventRegion) {
            $source = $eventRegion->rankingSource;
            $runId = $this->publishedRunId((int) $source->series_id);
            if (! $runId) {
                throw ValidationException::withMessages(['event_region_ids' => 'A selected region no longer has a current published ranking.']);
            }
            $categoryIds = $teams->where('region_id', $eventRegion->region_id)->pluck('category.category_id')->filter()->unique();
            $rows = SeriesRanking::query()->with(['player.user', 'player.users', 'category'])
                ->where('series_id', $source->series_id)->where('run_id', $runId)->where('status', 'published')
                ->whereIn('category_id', $categoryIds)->whereIn('id', $rowIds)->get();
            $allowed = $allowed->concat($rows->map(function (SeriesRanking $row) use ($eventRegion, $source, $runId): array {
                return [
                    'series_ranking_id' => (int) $row->id,
                    'email' => mb_strtolower(trim((string) $this->contacts->primaryEmail($row->player))),
                    'name' => $this->displayLabel((string) ($row->player?->full_name ?? 'Player')),
                    'category' => $this->displayLabel((string) ($row->category?->name ?? 'Age group')),
                    'region' => $this->displayLabel((string) ($eventRegion->region?->region_name ?? 'Region')),
                    'status' => 'Rank '.(int) $row->rank_position,
                    'source_identity' => implode(':', [(int) $source->id, (int) $source->series_id, $runId]),
                ];
            }));
        }
        if ($allowed->pluck('series_ranking_id')->unique()->sort()->values()->all() !== $rowIds->all()) {
            throw ValidationException::withMessages(['series_ranking_ids' => 'One or more selected ranked players are outside the selected regions, teams, or current published ranking.']);
        }

        return $allowed->filter(fn (array $recipient) => filled($recipient['email']))->groupBy('email')->map(function (Collection $matches): array {
            $first = $matches->first();
            return [...$first,
                'category' => $matches->pluck('category')->unique()->sort()->implode(', '),
                'region' => $matches->pluck('region')->unique()->sort()->implode(', '),
                'source_identity' => $matches->pluck('source_identity')->unique()->sort()->implode(','),
            ];
        })->sortBy([['region', 'asc'], ['category', 'asc'], ['name', 'asc']])->values();
    }

    private function publishedRunId(int $seriesId): ?string
    {
        // RankingPublicationService treats exactly one published run as the canonical lifecycle state.
        // Refuse ambiguous publication state rather than guessing from a newer reviewed/calculated row.
        $runIds = SeriesRanking::query()->where('series_id', $seriesId)->where('status', 'published')
            ->whereNotNull('run_id')->where('run_id', '!=', '')->distinct()->pluck('run_id');

        return $runIds->count() === 1 ? (string) $runIds->first() : null;
    }

    private function displayLabel(string $value): string
    {
        $decoded = rawurldecode($value);

        return trim(preg_replace('/\s+/u', ' ', $decoded) ?? $decoded);
    }

    private function matchesStatus(TeamSelectionInvitation $invitation, string $audience): bool
    {
        return match ($audience) {
            'active' => in_array($invitation->status, [
                TeamSelectionInvitation::INVITED,
                TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
                TeamSelectionInvitation::PAID_CONFIRMED,
            ], true),
            'entered' => $invitation->status === TeamSelectionInvitation::PAID_CONFIRMED,
            'not_entered' => in_array($invitation->status, [
                TeamSelectionInvitation::INVITED,
                TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
            ], true),
            'invited' => $invitation->invited_at !== null && in_array($invitation->status, [
                TeamSelectionInvitation::INVITED,
                TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
                TeamSelectionInvitation::PAID_CONFIRMED,
            ], true),
            'not_invited' => $invitation->status === TeamSelectionInvitation::RESERVE
                || ($invitation->status === TeamSelectionInvitation::INVITED && $invitation->invited_at === null),
            'accepted' => in_array($invitation->status, [
                TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
                TeamSelectionInvitation::PAID_CONFIRMED,
            ], true),
            'not_accepted' => $invitation->status === TeamSelectionInvitation::INVITED
                && $invitation->invited_at !== null
                && $invitation->accepted_at === null,
            'declined' => $invitation->status === TeamSelectionInvitation::DECLINED,
            'withdrawn' => $invitation->status === TeamSelectionInvitation::WITHDRAWN,
            default => false,
        };
    }
}
