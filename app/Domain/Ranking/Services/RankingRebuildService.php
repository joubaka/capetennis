<?php

namespace App\Domain\Ranking\Services;

use App\Domain\Ranking\DTO\RankingResult;
use App\Domain\Ranking\DTO\RankingRow;
use App\Domain\Ranking\Enums\RankingStatus;
use App\Models\RankingList;
use App\Models\Series;
use App\Models\SeriesRanking;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RankingRebuildService
 *
 * Orchestrates a full ranking rebuild for a Series:
 *  1. Clear previous calculated rows (never touches Published ones by default)
 *  2. Run RankingCalculationService for every RankingList in the Series
 *  3. Persist results to series_rankings with status = 'calculated'
 *  4. Return a structured report for the admin UI / audit log
 *
 * Use $dryRun = true to run the full pipeline without any DB writes.
 */
final class RankingRebuildService
{
    public function __construct(
        private readonly RankingCalculationService $calculator,
        private readonly RankingAuditService       $auditor,
    ) {}

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Rebuild all ranking lists for a Series.
     *
     * @param  Series $series
     * @param  array  $options
     *   - dryRun:             bool        Do not write to DB (default false)
     *   - bestN:              int|null    Override per-list best-N
     *   - excludePlayerIds:   int[]       Player IDs to exclude globally
     *   - walkoversExcluded:  bool        Exclude walkover legs
     * @return array{
     *   run_id: string,
     *   series_id: int,
     *   dry_run: bool,
     *   lists: array,
     *   total_rows: int,
     *   warnings: string[],
     *   topology: array{created_lists: int, linked_category_events: int},
     *   persisted: bool,
     * }
     */
    public function rebuild(Series $series, array $options = []): array
    {
        $dryRun = (bool) ($options['dryRun'] ?? false);
        $runId  = 'rkb-' . $series->id . '-' . now()->format('YmdHis') . '-' . substr(md5((string) microtime(true)), 0, 6);

        Log::info('[RankingRebuild] START', [
            'run_id'    => $runId,
            'series_id' => $series->id,
            'dry_run'   => $dryRun,
        ]);

        $listReports = [];
        $globalWarnings = [];
        $totalRows = 0;
        $topology = [
            'created_lists' => 0,
            'linked_category_events' => 0,
        ];
        $persisted = false;

        DB::transaction(function () use ($series, $options, $dryRun, $runId, &$listReports, &$globalWarnings, &$totalRows, &$topology, &$persisted) {
            DB::table('series')->where('id', $series->id)->lockForUpdate()->first();

            $lists = $series->ranking_lists()->with(['category', 'series'])->get();

            if ($lists->isEmpty()) {
                if ($dryRun) {
                    $globalWarnings[] = 'No ranking lists found. Run a normal rebuild once to create them from the saved event results.';
                    return;
                }

                $topology = $this->bootstrapListsFromResults($series);
                $lists = $series->ranking_lists()->with(['category', 'series'])->get();

                if ($lists->isEmpty()) {
                    $globalWarnings[] = 'No ranking lists could be created because this series has no saved category results.';
                    return;
                }
            }

            if (!$dryRun) {
                $linked = $this->linkEmptyListsFromResults($series, $lists);
                $topology['linked_category_events'] += $linked;

                if ($linked > 0) {
                    $lists = $series->ranking_lists()->with(['category', 'series'])->get();
                }
            }

            /** @var array<int, RankingResult> $calculatedResults */
            $calculatedResults = [];
            $emptyListIds = [];

            foreach ($lists as $list) {
                $this->validateListOwnership($series, $list);

                $listOptions = array_merge($options, [
                    // Per-list best-N takes priority over global override
                    'bestN' => $options['bestN'] ?? $list->best_num_of_scores ?? $series->best_num_of_scores ?? 9999,
                ]);

                // Run calculation
                $result = $this->calculator->calculate($list, $listOptions);
                $calculatedResults[$list->id] = $result;

                $rowCount    = $result->rows->count();
                $totalRows  += $rowCount;
                if ($rowCount === 0) {
                    $emptyListIds[] = $list->id;
                }

                $listReports[] = [
                    'ranking_list_id' => $list->id,
                    'list_name'       => $result->listName,
                    'rows_written'    => $rowCount,
                    'warnings'        => $result->warnings,
                    'audit'           => $result->audit,
                    'top10'           => $result->rows->take(10)->map(fn(RankingRow $r) => [
                        'rank'        => $r->rankPosition,
                        'player_id'   => $r->playerId,
                        'total_pts'   => $r->totalPoints,
                        'auto_award'  => $r->autoAward,
                        'legs'        => count($r->countingLegs),
                        'dropped'     => count($r->droppedLegs),
                    ])->values()->all(),
                ];

                Log::info('[RankingRebuild] list complete', [
                    'run_id'          => $runId,
                    'ranking_list_id' => $list->id,
                    'rows'            => $rowCount,
                    'warnings'        => count($result->warnings),
                ]);
            }

            if ($dryRun) {
                return;
            }

            if ($totalRows === 0) {
                $globalWarnings[] = 'No replacement ranking rows were calculated. Existing ranking rows were preserved.';
                return;
            }

            if ($emptyListIds !== []) {
                $globalWarnings[] = 'Rebuild was not persisted because ranking list(s) produced no rows: '
                    . implode(', ', $emptyListIds) . '. Existing ranking rows were preserved.';
                return;
            }

            // All lists passed preflight. Only now may the current calculated
            // snapshot be replaced.
            SeriesRanking::where('series_id', $series->id)
                ->whereNull('ranking_list_id')
                ->where('status', '!=', RankingStatus::Published->value)
                ->delete();

            foreach ($lists as $list) {
                $this->persist($series, $list->id, $calculatedResults[$list->id], $runId);
            }

            $persisted = true;
        });

        $report = [
            'run_id'     => $runId,
            'series_id'  => $series->id,
            'dry_run'    => $dryRun,
            'lists'      => $listReports,
            'total_rows' => $totalRows,
            'warnings'   => $globalWarnings,
            'topology'   => $topology,
            'persisted'  => $persisted,
        ];

        // Record audit log entry
        if (!$dryRun) {
            $this->auditor->recordRebuild($series, $report, $runId);
        }

        Log::info('[RankingRebuild] DONE', [
            'run_id'     => $runId,
            'total_rows' => $totalRows,
        ]);

        return $report;
    }

    /**
     * Create canonical lists for older series that already have final results
     * but pre-date ranking-list setup.
     *
     * This only runs when the series has no lists at all, so an intentionally
     * curated existing list is never expanded or changed by a rebuild.
     *
     * @return array{created_lists: int, linked_category_events: int}
     */
    private function bootstrapListsFromResults(Series $series): array
    {
        $categoryEvents = DB::table('category_events as ce')
            ->join('events as e', 'e.id', '=', 'ce.event_id')
            ->join('categories as c', 'c.id', '=', 'ce.category_id')
            ->join('category_results as cr', function ($join) {
                $join->on('cr.event_id', '=', 'ce.event_id')
                    ->on('cr.category_id', '=', 'ce.category_id');
            })
            ->where('e.series_id', $series->id)
            ->select('ce.id', 'ce.category_id', 'c.name as category_name', 'e.start_date')
            ->groupBy('ce.id', 'ce.category_id', 'c.name', 'e.start_date')
            ->orderBy('e.start_date')
            ->orderBy('ce.id')
            ->get()
            ->groupBy(fn($categoryEvent) => $this->normalizeCategoryName($categoryEvent->category_name));

        $createdLists = 0;
        $linkedCategoryEvents = 0;
        $now = now();

        foreach ($categoryEvents as $events) {
            $categoryId = (int) $events->first()->category_id;
            $list = RankingList::firstOrCreate([
                'series_id' => $series->id,
                'category_id' => $categoryId,
            ]);

            if ($list->wasRecentlyCreated) {
                $createdLists++;
            }

            foreach ($events->values() as $index => $categoryEvent) {
                $linkedCategoryEvents += DB::table('ranking_list_category_events')->insertOrIgnore([
                    'ranking_list_id' => $list->id,
                    'category_event_id' => $categoryEvent->id,
                    'sort_order' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        return [
            'created_lists' => $createdLists,
            'linked_category_events' => $linkedCategoryEvents,
        ];
    }

    /**
     * Repair only completely empty historical lists. Curated lists that already
     * contain at least one event are never expanded automatically.
     */
    private function linkEmptyListsFromResults(Series $series, Collection $lists): int
    {
        $emptyLists = $lists->filter(fn(RankingList $list) => !$list->rank_cats()->exists());
        if ($emptyLists->isEmpty()) {
            return 0;
        }

        $eventsByName = DB::table('category_events as ce')
            ->join('events as e', 'e.id', '=', 'ce.event_id')
            ->join('categories as c', 'c.id', '=', 'ce.category_id')
            ->join('category_results as cr', function ($join) {
                $join->on('cr.event_id', '=', 'ce.event_id')
                    ->on('cr.category_id', '=', 'ce.category_id');
            })
            ->where('e.series_id', $series->id)
            ->select('ce.id', 'c.name as category_name', 'e.start_date')
            ->groupBy('ce.id', 'c.name', 'e.start_date')
            ->orderBy('e.start_date')
            ->orderBy('ce.id')
            ->get()
            ->groupBy(fn($categoryEvent) => $this->normalizeCategoryName($categoryEvent->category_name));

        $linked = 0;
        $now = now();

        foreach ($emptyLists as $list) {
            $categoryName = $this->normalizeCategoryName((string) $list->category?->name);
            foreach ($eventsByName->get($categoryName, collect())->values() as $index => $categoryEvent) {
                $linked += DB::table('ranking_list_category_events')->insertOrIgnore([
                    'ranking_list_id' => $list->id,
                    'category_event_id' => $categoryEvent->id,
                    'sort_order' => $index + 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        return $linked;
    }

    // ------------------------------------------------------------------
    // Persistence
    // ------------------------------------------------------------------

    private function persist(Series $series, int $rankingListId, RankingResult $result, string $runId): void
    {
        $categoryId = $this->resolveCategoryId($rankingListId);

        // Delete previously calculated (non-published) rows for this list
        SeriesRanking::where('series_id', $series->id)
            ->where('ranking_list_id', $rankingListId)
            ->where('status', '!=', RankingStatus::Published->value)
            ->delete();

        $now = now();

        foreach ($result->rows as $row) {
            SeriesRanking::create([
                'series_id'       => $series->id,
                'ranking_list_id' => $rankingListId,
                'category_id'     => $categoryId,
                'player_id'       => $row->playerId,
                'rank_position'   => $row->rankPosition,
                'total_points'    => $row->totalPoints,
                'status'          => RankingStatus::Calculated->value,
                'run_id'          => $runId,
                'meta_json'       => $this->buildMeta($row),
            ]);
        }
    }

    private function resolveCategoryId(int $rankingListId): ?int
    {
        return DB::table('ranking_lists')
            ->where('id', $rankingListId)
            ->value('category_id');
    }

    private function buildMeta(RankingRow $row): array
    {
        return [
            'auto_award'    => $row->autoAward,
            'wins'          => $row->wins,
            'best_single'   => $row->bestSingle,
            'positions_sum' => $row->positionsSum,
            'counting_legs' => array_map(fn($l) => [
                'category_event_id' => $l->categoryEventId,
                'position'          => $l->position,
                'points'            => $l->points,
                'synthetic'         => $l->synthetic,
                'note'              => $l->note,
                'event_date'        => $l->eventDate,
            ], $row->countingLegs),
            'dropped_legs'  => array_map(fn($l) => [
                'category_event_id' => $l->categoryEventId,
                'position'          => $l->position,
                'points'            => $l->points,
                'event_date'        => $l->eventDate,
            ], $row->droppedLegs),
            'tiebreak_notes' => $row->tiebreakNotes,
        ];
    }

    private function validateListOwnership(Series $series, RankingList $list): void
    {
        $linkedEvents = DB::table('ranking_list_category_events as rlce')
            ->join('category_events as ce', 'ce.id', '=', 'rlce.category_event_id')
            ->join('events as e', 'e.id', '=', 'ce.event_id')
            ->join('categories as c', 'c.id', '=', 'ce.category_id')
            ->where('rlce.ranking_list_id', $list->id)
            ->select('rlce.category_event_id', 'e.series_id', 'c.name as category_name')
            ->get();

        $expectedName = $this->normalizeCategoryName((string) $list->category?->name);
        $invalid = $linkedEvents->filter(fn($event) =>
            (int) $event->series_id !== (int) $series->id
            || $this->normalizeCategoryName((string) $event->category_name) !== $expectedName
        )->pluck('category_event_id');

        if ($invalid->isNotEmpty()) {
            throw new \RuntimeException(
                "Ranking list {$list->id} contains category events outside its series/category: " . $invalid->implode(', ')
            );
        }
    }

    private function normalizeCategoryName(string $name): string
    {
        return mb_strtolower(preg_replace('/\s+/', ' ', trim($name)) ?? '');
    }
}
