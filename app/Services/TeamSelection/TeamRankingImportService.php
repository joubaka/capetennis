<?php

declare(strict_types=1);

namespace App\Services\TeamSelection;

use App\Domain\Ranking\Services\RankingTeamEligibilityService;
use App\Models\CategoryEvent;
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
use Illuminate\Support\Facades\Schema;
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

    /** @return array{rows: Collection<int, array<string, mixed>>, published_ready: bool} */
    public function categorySetup(EventRegionRankingSource $source): array
    {
        $source->loadMissing(['event', 'series', 'region']);
        $lists = RankingList::query()->with('category')
            ->where('series_id', $source->series_id)
            ->whereNotNull('category_id')
            ->get()
            ->sortBy(fn (RankingList $list) => mb_strtolower((string) $list->category?->name))
            ->values();
        $categoryIds = $lists->pluck('category_id')->map(fn ($id) => (int) $id)->unique()->values();
        $eventCategories = CategoryEvent::query()
            ->where('event_id', $source->event_id)->whereIn('category_id', $categoryIds)
            ->get()->keyBy(fn (CategoryEvent $categoryEvent) => (int) $categoryEvent->category_id);
        $teams = $this->teamsForEvent($source->event, [(int) $source->region_id])->load('category');
        $publishedReady = $this->hasPublishedRanking($source->series_id);
        $publishedRunId = $publishedReady ? $this->publishedRunId($source->series_id) : null;
        $rankedCounts = $publishedRunId
            ? SeriesRanking::query()->where('series_id', $source->series_id)->where('run_id', $publishedRunId)
                ->where('status', 'published')->selectRaw('ranking_list_id, COUNT(*) as aggregate')
                ->groupBy('ranking_list_id')->pluck('aggregate', 'ranking_list_id')
            : collect();

        $rows = $lists->map(function (RankingList $list) use ($source, $eventCategories, $teams, $rankedCounts): array {
            $categoryEvent = $eventCategories->get((int) $list->category_id);
            $team = $categoryEvent
                ? $teams->first(fn (Team $candidate) => (int) $candidate->category_event_id === (int) $categoryEvent->id)
                : null;
            if (! $team) {
                $key = $this->categoryKey((string) $list->category?->name);
                $legacyMatches = $key
                    ? $teams->filter(fn (Team $candidate) => ! $candidate->category_event_id && $this->categoryKey((string) $candidate->name) === $key)
                    : collect();
                $team = $legacyMatches->count() === 1 ? $legacyMatches->first() : null;
            }
            $prefix = trim((string) ($source->region?->short_name ?: $source->region?->region_name));

            return [
                'ranking_list_id' => (int) $list->id,
                'category_id' => (int) $list->category_id,
                'category_name' => (string) ($list->category?->name ?: 'Category #'.$list->category_id),
                'ranked_count' => (int) ($rankedCounts[$list->id] ?? 0),
                'event_category' => $categoryEvent,
                'team' => $team,
                'ready' => (bool) ($categoryEvent && $team
                    && (int) $team->category_event_id === (int) $categoryEvent->id
                    && (int) $team->num_team_members > 0),
                'suggested_team_name' => trim($prefix.' '.(string) $list->category?->name),
            ];
        });

        return ['rows' => $rows, 'published_ready' => $publishedReady];
    }

    /**
     * @param array<int, array{selected?: mixed, ranking_list_id: mixed, team_name?: mixed, num_players?: mixed}> $categories
     * @return array{created: int, linked: int, unchanged: int}
     */
    public function createTeamsFromRankingCategories(EventRegionRankingSource $source, array $categories, User $actor): array
    {
        $selected = collect($categories)->filter(fn (array $row) => (bool) ($row['selected'] ?? false))->values();
        if ($selected->isEmpty()) {
            throw ValidationException::withMessages(['categories' => 'Select at least one ranking category.']);
        }

        return DB::transaction(function () use ($source, $selected, $actor): array {
            $lockedSource = EventRegionRankingSource::query()->lockForUpdate()->findOrFail($source->id);
            if ($lockedSource->imports()->whereIn('status', ['draft', 'sent'])->exists()) {
                throw ValidationException::withMessages(['categories' => 'Teams cannot be changed after a ranking import has started.']);
            }
            $rankingLists = RankingList::query()->with('category')
                ->where('series_id', $lockedSource->series_id)
                ->whereIn('id', $selected->pluck('ranking_list_id')->map(fn ($id) => (int) $id))
                ->lockForUpdate()->get()->keyBy('id');
            if ($rankingLists->count() !== $selected->count()) {
                throw ValidationException::withMessages(['categories' => 'One or more categories do not belong to the linked ranking series.']);
            }

            $event = Event::query()->lockForUpdate()->findOrFail($lockedSource->event_id);
            $created = 0;
            $linked = 0;
            $unchanged = 0;
            foreach ($selected as $row) {
                $rankingList = $rankingLists->get((int) $row['ranking_list_id']);
                $teamName = trim((string) ($row['team_name'] ?? ''));
                $numPlayers = (int) ($row['num_players'] ?? 0);
                if ($teamName === '' || $numPlayers < 1 || $numPlayers > 50) {
                    throw ValidationException::withMessages([
                        'categories' => "Enter a team name and player quantity for {$rankingList->category?->name}.",
                    ]);
                }

                $categoryEvent = CategoryEvent::query()->firstOrCreate(
                    ['event_id' => $event->id, 'category_id' => $rankingList->category_id],
                    ['entry_fee' => 0, 'ordering' => ((int) CategoryEvent::where('event_id', $event->id)->max('ordering')) + 1]
                );
                $team = Team::query()->withoutGlobalScopes()
                    ->where('region_id', $lockedSource->region_id)
                    ->where('category_event_id', $categoryEvent->id)
                    ->lockForUpdate()->first();

                if (! $team) {
                    $key = $this->categoryKey((string) $rankingList->category?->name);
                    $legacyMatches = Team::query()->withoutGlobalScopes()
                        ->where('region_id', $lockedSource->region_id)
                        ->whereNull('category_event_id')
                        ->where('year', (string) ($event->start_date?->format('Y') ?: date('Y')))
                        ->lockForUpdate()->get()
                        ->filter(fn (Team $candidate) => $key && $this->categoryKey((string) $candidate->name) === $key);
                    if ($legacyMatches->count() > 1) {
                        throw ValidationException::withMessages([
                            'categories' => "More than one existing team matches {$rankingList->category?->name}. Link the correct team category manually first.",
                        ]);
                    }
                    $team = $legacyMatches->first();
                }

                if ($team) {
                    $occupied = TeamPlayer::query()->withoutGlobalScopes()->where('team_id', $team->id)->where('player_id', '>', 0)->exists();
                    if ($occupied && (int) $team->num_team_members !== $numPlayers) {
                        throw ValidationException::withMessages([
                            'categories' => "{$team->name} already has players. Its team size was not changed.",
                        ]);
                    }
                    $wasLinked = ! $team->category_event_id;
                    $team->forceFill([
                        'category_event_id' => $categoryEvent->id,
                        'num_team_members' => $numPlayers,
                    ])->save();
                    $wasLinked ? $linked++ : $unchanged++;
                } else {
                    $team = new Team([
                        'name' => $teamName,
                        'num_team_members' => $numPlayers,
                        'year' => $event->start_date?->format('Y') ?: date('Y'),
                        'published' => false,
                        'region_id' => $lockedSource->region_id,
                        'category_event_id' => $categoryEvent->id,
                        'noProfile' => false,
                    ]);
                    if (Schema::hasColumn('teams', 'user_id')) $team->setAttribute('user_id', $actor->id);
                    if (Schema::hasColumn('teams', 'personal_team')) $team->setAttribute('personal_team', false);
                    $team->save();
                    $created++;
                }

                for ($rank = 1; $rank <= $numPlayers; $rank++) {
                    TeamPlayer::query()->withoutGlobalScopes()->firstOrCreate(
                        ['team_id' => $team->id, 'rank' => $rank],
                        ['player_id' => 0, 'pay_status' => 0]
                    );
                }
                TeamPlayer::query()->withoutGlobalScopes()->where('team_id', $team->id)
                    ->where('rank', '>', $numPlayers)->where('player_id', 0)->delete();
            }

            activity('team-selection')->performedOn($event)->causedBy($actor)
                ->withProperties([
                    'source_id' => $lockedSource->id,
                    'region_id' => $lockedSource->region_id,
                    'ranking_list_ids' => $selected->pluck('ranking_list_id')->map(fn ($id) => (int) $id)->all(),
                    'created_teams' => $created,
                    'linked_existing_teams' => $linked,
                ])->log('created regional event teams from ranking categories');

            return compact('created', 'linked', 'unchanged');
        });
    }

    public function hasPublishedRanking(int $seriesId): bool
    {
        $latest = SeriesRanking::query()->where('series_id', $seriesId)
            ->orderByDesc('created_at')->orderByDesc('id')->first(['status', 'run_id']);

        return $latest?->status === 'published' && filled($latest->run_id);
    }

    public function unlink(EventRegionRankingSource $source, User $actor): void
    {
        DB::transaction(function () use ($source, $actor): void {
            $lockedSource = EventRegionRankingSource::query()->lockForUpdate()->findOrFail($source->id);
            if ($lockedSource->imports()->exists()) {
                throw ValidationException::withMessages([
                    'series_id' => 'This series cannot be unlinked because a ranked-player import already exists. Restart the unsent draft first, or retain the link for the audit history.',
                ]);
            }

            $event = Event::findOrFail($lockedSource->event_id);
            activity('team-selection')->performedOn($event)->causedBy($actor)
                ->withProperties([
                    'source_id' => $lockedSource->id,
                    'event_region_id' => $lockedSource->event_region_id,
                    'region_id' => $lockedSource->region_id,
                    'series_id' => $lockedSource->series_id,
                ])->log('unlinked event region from ranking series');
            $lockedSource->delete();
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

        $teams = $this->teamsForEvent($source->event, [(int) $source->region_id])->load('category');
        if ($teams->isEmpty()) {
            throw ValidationException::withMessages(['teams' => 'Create and configure this region’s teams before importing ranked players.']);
        }

        $lists = $rows->pluck('rankingList')->filter()->unique('id')->values();
        $listByKey = $lists->groupBy(fn (RankingList $list) => $this->categoryKey((string) $list->category?->name));
        $listByCategory = $lists->groupBy(fn (RankingList $list) => (int) $list->category_id);
        $teamKeys = $teams->groupBy(fn (Team $team) => $team->category?->category_id
            ? 'category:'.(int) $team->category->category_id
            : 'name:'.($this->categoryKey((string) $team->name) ?: 'unmatched-'.$team->id));
        $warnings = [];
        $notices = [];
        $mappings = [];

        foreach ($teams as $team) {
            $categoryId = (int) ($team->category?->category_id ?? 0);
            $key = $categoryId > 0 ? null : $this->categoryKey((string) $team->name);
            $identity = $categoryId > 0 ? 'category:'.$categoryId : 'name:'.($key ?: 'unmatched-'.$team->id);
            if (($teamKeys->get($identity)?->count() ?? 0) > 1) {
                $warnings[] = "More than one event team is linked to the ranking category for {$team->name}.";
                continue;
            }
            if ($categoryId <= 0 && $key === null) {
                $warnings[] = "Team {$team->name} has no event category and its name does not identify an age group and gender.";
                continue;
            }
            $matches = $categoryId > 0
                ? $listByCategory->get($categoryId, collect())
                : $listByKey->get($key, collect());
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
                $emptyPlaces = $capacity - $eligible->count();
                $notices[] = "{$team->name} has {$eligible->count()} eligible ranked players for {$capacity} team places. {$emptyPlaces} team place(s) will remain empty and no reserves are available.";
            } elseif ($eligible->count() < $required) {
                $availableReserves = max(0, $eligible->count() - $capacity);
                $missingReserves = (int) $source->reserve_count - $availableReserves;
                $notices[] = "{$team->name} can fill all {$capacity} team places but has only {$availableReserves} reserve(s). {$missingReserves} reserve place(s) will remain empty.";
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

        return [
            'run_id' => $runId,
            'mappings' => $mappings,
            'warnings' => array_values(array_unique($warnings)),
            'notices' => array_values(array_unique($notices)),
        ];
    }

    public function import(EventRegionRankingSource $source, User $actor, bool $confirmIncompleteRosters = false): TeamSelectionImport
    {
        $preview = $this->preview($source);
        if ($preview['warnings'] !== []) {
            throw ValidationException::withMessages([
                'import' => 'Resolve the preview warnings before importing: '.implode(' ', $preview['warnings']),
            ]);
        }
        if ($preview['notices'] !== [] && ! $confirmIncompleteRosters) {
            throw ValidationException::withMessages([
                'confirm_incomplete_rosters' => 'Confirm that you accept the empty team or reserve places before importing.',
            ]);
        }

        return DB::transaction(function () use ($source, $actor, $preview, $confirmIncompleteRosters) {
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

                for ($rosterRank = 1; $rosterRank <= $mapping['capacity']; $rosterRank++) {
                    TeamPlayer::query()->withoutGlobalScopes()->firstOrCreate(
                        ['team_id' => $team->id, 'rank' => $rosterRank],
                        ['player_id' => 0, 'pay_status' => 0]
                    );
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
                    'incomplete_rosters_confirmed' => $confirmIncompleteRosters,
                    'incomplete_roster_notices' => $preview['notices'],
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
