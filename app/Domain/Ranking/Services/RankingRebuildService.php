<?php

namespace App\Domain\Ranking\Services;

use App\Domain\Ranking\DTO\RankingResult;
use App\Domain\Ranking\DTO\RankingRow;
use App\Domain\Ranking\Enums\RankingStatus;
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

        DB::transaction(function () use ($series, $options, $dryRun, $runId, &$listReports, &$globalWarnings, &$totalRows) {

            $lists = $series->ranking_lists()->with(['category', 'series'])->get();

            if ($lists->isEmpty()) {
                $globalWarnings[] = 'No ranking lists found for this series.';
                return;
            }

            foreach ($lists as $list) {
                $listOptions = array_merge($options, [
                    // Per-list best-N takes priority over global override
                    'bestN' => $options['bestN'] ?? $list->best_num_of_scores ?? $series->best_num_of_scores ?? 9999,
                ]);

                // Run calculation
                $result = $this->calculator->calculate($list, $listOptions);

                // Persist unless dry-run
                if (!$dryRun) {
                    $this->persist($series, $list->id, $result, $runId);
                }

                $rowCount    = $result->rows->count();
                $totalRows  += $rowCount;

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
        });

        $report = [
            'run_id'     => $runId,
            'series_id'  => $series->id,
            'dry_run'    => $dryRun,
            'lists'      => $listReports,
            'total_rows' => $totalRows,
            'warnings'   => $globalWarnings,
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

        // Also delete legacy rows (ranking_list_id = null) for the same series + category
        if ($categoryId) {
            SeriesRanking::where('series_id', $series->id)
                ->whereNull('ranking_list_id')
                ->where('category_id', $categoryId)
                ->where('status', '!=', RankingStatus::Published->value)
                ->delete();
        }

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
}
