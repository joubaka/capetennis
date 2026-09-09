<?php

declare(strict_types=1);

namespace App\Services\TeamSelection;

use App\Domain\Ranking\Services\RankingTeamEligibilityService;
use App\Models\Event;
use App\Models\EventRegion;
use App\Models\EventRegionRankingSource;
use App\Models\RankingList;
use App\Models\Series;
use App\Models\SeriesRanking;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamSelectionImport;
use App\Models\TeamSelectionInvitation;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TeamRankingImportService
{
    public function __construct(private readonly RankingTeamEligibilityService $eligibility) {}

    public function link(Event $event, EventRegion $eventRegion, Series $series, int $reserveCount, User $actor): EventRegionRankingSource
    {
        $this->assertEventRegion($event, $eventRegion);
        $eventYear = (int) ($event->start_date?->format('Y') ?: date('Y'));
        if ((int) $series->year !== $eventYear) {
            throw ValidationException::withMessages([
                'series_id' => "Choose a {$eventYear} ranking series for this event.",
            ]);
        }

        return DB::transaction(function () use ($event, $eventRegion, $series, $reserveCount, $actor) {
            $existing = EventRegionRankingSource::query()->where('event_region_id', $eventRegion->id)->lockForUpdate()->first();
            if ($existing?->imports()->exists()) {
                throw ValidationException::withMessages([
                    'series_id' => 'This region already has a ranking import. Its source series and reserve settings are now locked.',
                ]);
            }

            $source = EventRegionRankingSource::updateOrCreate(
                ['event_region_id' => $eventRegion->id],
                [
                    'event_id' => $event->id,
                    'region_id' => $eventRegion->region_id,
                    'series_id' => $series->id,
                    'reserve_count' => max(0, min(20, $reserveCount)),
                    'linked_by' => $actor->id,
                ]
            );

            activity('team-selection')->performedOn($event)->causedBy($actor)
                ->withProperties([
                    'event_region_id' => $eventRegion->id,
                    'region_id' => $eventRegion->region_id,
                    'series_id' => $series->id,
                    'reserve_count' => $source->reserve_count,
                ])->log('linked event region to ranking series');

            return $source->fresh(['series', 'region']);
        });
    }

    /** @return array{run_id: string, mappings: array<int, array<string, mixed>>, warnings: array<int, string>} */
    public function preview(EventRegionRankingSource $source): array
    {
        $source->loadMissing(['event', 'series', 'region']);
        $runId = $this->publishedRunId($source->series_id);
        $rows = SeriesRanking::query()
            ->with(['player', 'rankingList.category'])
            ->where('series_id', $source->series_id)
            ->where('run_id', $runId)
            ->where('status', 'published')
            ->orderBy('ranking_list_id')
            ->orderBy('rank_position')
            ->get();

        $teams = $this->teamsForEvent($source->event, [(int) $source->region_id]);
        if ($teams->isEmpty()) {
            throw ValidationException::withMessages(['teams' => 'Create and configure this region’s teams before importing ranked players.']);
        }

        $lists = $rows->pluck('rankingList')->filter()->unique('id')->values();
        $listByKey = $lists->groupBy(fn (RankingList $list) => $this->categoryKey((string) $list->category?->name));
        $teamKeys = $teams->groupBy(fn (Team $team) => $this->categoryKey((string) $team->name));
        $warnings = [];
        $mappings = [];

        foreach ($teams as $team) {
            $key = $this->categoryKey((string) $team->name);
            if ($key === null) {
                $warnings[] = "Team {$team->name} does not identify an age group and gender.";
                continue;
            }
            if (($teamKeys->get($key)?->count() ?? 0) > 1) {
                $warnings[] = "More than one team matches {$key}; team names must identify a unique ranking category.";
                continue;
            }
            $matches = $listByKey->get($key, collect());
            if ($matches->count() !== 1) {
                $warnings[] = $matches->isEmpty()
                    ? "No published ranking list matches {$team->name}."
                    : "More than one published ranking list matches {$team->name}.";
                continue;
            }

            $capacity = max(0, (int) $team->num_team_members);
            if ($capacity < 1) {
                $warnings[] = "Set the player quantity for {$team->name} before importing.";
                continue;
            }

            $list = $matches->first();
            $ranked = $rows->where('ranking_list_id', $list->id)->values();
            $eligible = $this->eligibility->eligible($ranked, $source->series)->values();
            $required = $capacity + (int) $source->reserve_count;
            if ($eligible->count() < $capacity) {
                $warnings[] = "{$team->name} has only {$eligible->count()} eligible ranked players for {$capacity} places.";
            }

            $mappings[] = [
                'team' => $team,
                'ranking_list' => $list,
                'capacity' => $capacity,
                'reserve_count' => (int) $source->reserve_count,
                'players' => $eligible->take($required)->values(),
                'skipped_count' => $ranked->count() - $eligible->count(),
            ];
        }

        $duplicateCandidates = collect($mappings)
            ->flatMap(fn (array $mapping) => $mapping['players']->map(fn (SeriesRanking $row) => [
                'player_id' => (int) $row->player_id,
                'player_name' => $row->player?->full_name ?: 'Player #'.$row->player_id,
                'team_name' => $mapping['team']->name,
            ]))
            ->groupBy('player_id')
            ->filter(fn (Collection $occurrences) => $occurrences->pluck('team_name')->unique()->count() > 1);
        foreach ($duplicateCandidates as $occurrences) {
            $first = $occurrences->first();
            $warnings[] = $first['player_name'].' appears in more than one matched team list: '
                .$occurrences->pluck('team_name')->unique()->implode(', ').'. Resolve the ranking lists before importing.';
        }

        if ($mappings === []) {
            throw ValidationException::withMessages(['mapping' => 'No team could be matched safely to a published ranking list.']);
        }

        return ['run_id' => $runId, 'mappings' => $mappings, 'warnings' => array_values(array_unique($warnings))];
    }

    public function import(EventRegionRankingSource $source, User $actor): TeamSelectionImport
    {
        $preview = $this->preview($source);
        if ($preview['warnings'] !== []) {
            throw ValidationException::withMessages([
                'import' => 'Resolve the preview warnings before importing: '.implode(' ', $preview['warnings']),
            ]);
        }

        return DB::transaction(function () use ($source, $actor, $preview) {
            $lockedSource = EventRegionRankingSource::query()->lockForUpdate()->findOrFail($source->id);
            if ((int) $lockedSource->event_id !== (int) $source->event_id
                || (int) $lockedSource->region_id !== (int) $source->region_id
                || (int) $lockedSource->series_id !== (int) $source->series_id
                || $this->publishedRunId($lockedSource->series_id) !== $preview['run_id']) {
                throw ValidationException::withMessages(['import' => 'The ranking source changed while the preview was open. Review the import again.']);
            }
            if ($lockedSource->imports()->whereIn('status', ['draft', 'sent'])->exists()) {
                throw ValidationException::withMessages(['import' => 'This region already has an active ranking import.']);
            }

            $import = TeamSelectionImport::create([
                'source_id' => $lockedSource->id,
                'event_id' => $lockedSource->event_id,
                'region_id' => $lockedSource->region_id,
                'series_id' => $lockedSource->series_id,
                'ranking_run_id' => $preview['run_id'],
                'imported_by' => $actor->id,
                'status' => 'draft',
                'imported_at' => now(),
            ]);

            foreach ($preview['mappings'] as $mapping) {
                /** @var Team $team */
                $team = Team::query()->withoutGlobalScopes()->lockForUpdate()->findOrFail($mapping['team']->id);
                $occupied = TeamPlayer::query()->withoutGlobalScopes()->where('team_id', $team->id)
                    ->where('player_id', '>', 0)->lockForUpdate()->exists();
                $paid = TeamPlayer::query()->withoutGlobalScopes()->where('team_id', $team->id)
                    ->where('pay_status', 1)->lockForUpdate()->exists();
                if ($occupied || $paid) {
                    throw ValidationException::withMessages([
                        'team' => "{$team->name} already contains selected or paid players and was not changed.",
                    ]);
                }

                $rank = 0;
                foreach ($mapping['players'] as $row) {
                    $isSelected = $rank < $mapping['capacity'];
                    $rosterRank = $isSelected ? $rank + 1 : null;

                    if ($isSelected) {
                        $slot = TeamPlayer::query()->withoutGlobalScopes()
                            ->where('team_id', $team->id)->where('rank', $rosterRank)->lockForUpdate()->first();
                        $slot ??= new TeamPlayer(['team_id' => $team->id, 'rank' => $rosterRank]);
                        $slot->player_id = $row->player_id;
                        $slot->pay_status = 0;
                        $slot->save();
                    }

                    $assessment = $this->eligibility->assess($row, $lockedSource->series()->firstOrFail());
                    TeamSelectionInvitation::create([
                        'import_id' => $import->id,
                        'event_id' => $import->event_id,
                        'region_id' => $import->region_id,
                        'team_id' => $team->id,
                        'player_id' => $row->player_id,
                        'ranking_list_id' => $row->ranking_list_id,
                        'ranking_position' => $row->rank_position,
                        'queue_position' => $rank + 1,
                        'total_points' => $row->total_points,
                        'roster_rank' => $rosterRank,
                        'status' => $isSelected ? TeamSelectionInvitation::INVITED : TeamSelectionInvitation::RESERVE,
                        'snapshot_json' => [
                            'player_name' => $row->player?->full_name,
                            'ranking_position' => $row->rank_position,
                            'total_points' => $row->total_points,
                            'events_played' => $assessment['events_played'],
                            'minimum_events_for_team_selection' => $assessment['minimum_events'],
                            'ranking_run_id' => $preview['run_id'],
                        ],
                    ]);
                    $rank++;
                }
            }

            activity('team-selection')->performedOn($import)->causedBy($actor)
                ->withProperties([
                    'event_id' => $import->event_id,
                    'region_id' => $import->region_id,
                    'series_id' => $import->series_id,
                    'ranking_run_id' => $import->ranking_run_id,
                    'invitation_count' => $import->invitations()->count(),
                ])->log('imported ranked players into event region teams');

            return $import->fresh(['invitations.player', 'invitations.team']);
        });
    }

    public function restartDraft(TeamSelectionImport $selectionImport, User $actor): void
    {
        DB::transaction(function () use ($selectionImport, $actor): void {
            $locked = TeamSelectionImport::query()->lockForUpdate()->findOrFail($selectionImport->id);
            if ($locked->status !== 'draft') {
                throw ValidationException::withMessages(['import' => 'Only an unsent draft import can be restarted.']);
            }
            $invitations = $locked->invitations()->lockForUpdate()->get();
            if ($invitations->contains(fn (TeamSelectionInvitation $invitation) => $invitation->order_id
                || in_array($invitation->status, [TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, TeamSelectionInvitation::PAID_CONFIRMED], true))) {
                throw ValidationException::withMessages(['import' => 'This import cannot be restarted because registration or payment has begun.']);
            }

            foreach ($invitations->where('status', TeamSelectionInvitation::INVITED) as $invitation) {
                if (! $invitation->roster_rank) continue;
                TeamPlayer::query()->withoutGlobalScopes()
                    ->where('team_id', $invitation->team_id)
                    ->where('rank', $invitation->roster_rank)
                    ->where('player_id', $invitation->player_id)
                    ->where(fn ($query) => $query->whereNull('pay_status')->orWhere('pay_status', 0))
                    ->update(['player_id' => 0, 'pay_status' => 0, 'updated_at' => now()]);
            }

            activity('team-selection')->performedOn($locked)->causedBy($actor)
                ->withProperties(['event_id' => $locked->event_id, 'region_id' => $locked->region_id, 'removed_players' => $invitations->count()])
                ->log('restarted draft regional ranking import');
            $locked->invitations()->delete();
            $locked->delete();
        });
    }

    private function publishedRunId(int $seriesId): string
    {
        $latest = SeriesRanking::query()
            ->where('series_id', $seriesId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first(['status', 'run_id']);

        if (! $latest || $latest->status !== 'published' || ! $latest->run_id) {
            throw ValidationException::withMessages(['ranking' => 'The latest ranking run for this series must be reviewed and published before importing players.']);
        }

        return (string) $latest->run_id;
    }

    /** @param array<int, int> $regionIds */
    public function teamsForEvent(Event $event, array $regionIds): Collection
    {
        $eventYear = (int) ($event->start_date?->format('Y') ?: date('Y'));

        return Team::query()->withoutGlobalScopes()
            ->whereIn('region_id', $regionIds)
            ->where(function ($query) use ($event, $eventYear): void {
                $query->whereHas('category', fn ($category) => $category->where('event_id', $event->id))
                    ->orWhere(function ($legacy) use ($eventYear): void {
                        $legacy->whereNull('category_event_id')->where('year', (string) $eventYear);
                    });
            })
            ->orderBy('name')
            ->get();
    }

    private function assertEventRegion(Event $event, EventRegion $eventRegion): void
    {
        abort_unless((int) $eventRegion->event_id === (int) $event->id, 404);
    }

    private function categoryKey(string $name): ?string
    {
        $value = mb_strtolower($name);
        if (! preg_match('/(?:u|o)[\s\/-]*(\d{1,2})/u', $value, $age)) {
            return null;
        }
        $gender = preg_match('/boys?|seuns?/u', $value) ? 'boys'
            : (preg_match('/girls?|dogters?/u', $value) ? 'girls' : null);
        if (! $gender) {
            return null;
        }
        $division = preg_match('/(?:b\s*division|b\s*afdeling)/u', $value) ? '-b' : '';

        return 'u'.(int) $age[1].'-'.$gender.$division;
    }
}
