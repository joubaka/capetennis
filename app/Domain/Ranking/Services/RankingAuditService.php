<?php

namespace App\Domain\Ranking\Services;

use App\Domain\Ranking\Enums\RankingStatus;
use App\Models\Series;
use App\Models\SeriesRanking;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * RankingAuditService
 *
 * Provides human-readable audit reports and records audit log entries.
 * All methods are read-only except recordRebuild / recordStatusChange.
 */
final class RankingAuditService
{
    // ------------------------------------------------------------------
    // Audit log writes
    // ------------------------------------------------------------------

    public function recordRebuild(Series $series, array $report, string $runId): void
    {
        DB::table('ranking_audit_logs')->insert([
            'series_id'   => $series->id,
            'run_id'      => $runId,
            'action'      => 'rebuild',
            'payload'     => json_encode($report),
            'user_id'     => auth()->id(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function recordStatusChange(
        Series $series,
        RankingStatus $from,
        RankingStatus $to,
        int $userId,
        array $extra = []
    ): void {
        DB::table('ranking_audit_logs')->insert([
            'series_id'  => $series->id,
            'run_id'     => null,
            'action'     => "status:{$from->value}→{$to->value}",
            'payload'    => json_encode(array_merge(['from' => $from->value, 'to' => $to->value], $extra)),
            'user_id'    => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ------------------------------------------------------------------
    // Admin report
    // ------------------------------------------------------------------

    /**
     * Build a comprehensive audit report for the ranking admin view.
     *
     * Returns:
     *  - series meta
     *  - per-list breakdown: inputs, points per event, dropped events,
     *    tiebreak reasons, top players
     *  - parity comparison (canonical vs legacy) if legacy rows exist
     *  - publication history
     */
    public function buildReport(Series $series): array
    {
        $activeRunId = SeriesRanking::where('series_id', $series->id)
            ->whereIn('status', [
                RankingStatus::Calculated->value,
                RankingStatus::Reviewed->value,
                RankingStatus::Published->value,
            ])
            ->whereNotNull('run_id')
            ->orderByDesc('updated_at')
            ->value('run_id');

        $lists      = $series->ranking_lists()->with(['category'])->get();
        $pointsMap  = DB::table('points')
            ->where('series_id', $series->id)
            ->pluck('score', 'position')
            ->all();

        $listReports = $lists->map(function ($list) use ($series, $pointsMap, $activeRunId) {
            $ceIds = DB::table('ranking_list_category_events')
                ->where('ranking_list_id', $list->id)
                ->pluck('category_event_id');

            $canonicalRows = SeriesRanking::where('series_id', $series->id)
                ->where('ranking_list_id', $list->id)
                ->whereIn('status', [
                    RankingStatus::Calculated->value,
                    RankingStatus::Reviewed->value,
                    RankingStatus::Published->value,
                ])
                ->when($activeRunId, fn($query) => $query->where('run_id', $activeRunId))
                ->orderBy('rank_position')
                ->get();

            $legacyRows = SeriesRanking::where('series_id', $series->id)
                ->whereNull('ranking_list_id')
                ->where('category_id', $list->category_id)
                ->orderBy('rank_position')
                ->get();

            return [
                'list_id'         => $list->id,
                'list_name'       => $list->category?->name ?? "List #{$list->id}",
                'category_events' => $ceIds->values()->all(),
                'best_n'          => $list->best_num_of_scores ?? $series->best_num_of_scores,
                'points_map'      => $pointsMap,
                'canonical_count' => $canonicalRows->count(),
                'legacy_count'    => $legacyRows->count(),
                'parity'          => $this->compareParity($canonicalRows, $legacyRows),
                'top_rows'        => $canonicalRows->take(20)->map(fn($r) => [
                    'rank'        => $r->rank_position,
                    'player_id'   => $r->player_id,
                    'total_pts'   => $r->total_points,
                    'auto_award'  => $r->meta_json['auto_award'] ?? false,
                    'status'      => $r->status,
                    'run_id'      => $r->run_id,
                    'legs'        => $r->meta_json['counting_legs'] ?? [],
                    'dropped'     => $r->meta_json['dropped_legs'] ?? [],
                    'tiebreaks'   => $r->meta_json['tiebreak_notes'] ?? [],
                ])->values()->all(),
                'dropped_events'  => $this->identifyDroppedEvents($canonicalRows, $ceIds),
                'tiebreak_reasons'=> $this->collectTiebreakReasons($canonicalRows),
                'missing_points'  => $this->identifyMissingPoints($ceIds, $pointsMap),
            ];
        });

        $publicationHistory = DB::table('ranking_audit_logs')
            ->where('series_id', $series->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn($row) => [
                'action'     => $row->action,
                'run_id'     => $row->run_id,
                'user_id'    => $row->user_id,
                'created_at' => $row->created_at,
            ])->all();

        return [
            'series'              => [
                'id'        => $series->id,
                'name'      => $series->name,
                'rank_type' => optional($series->rankType)->type,
                'best_n'    => $series->best_num_of_scores,
                'auto_award_rule' => $series->auto_award_rule,
            ],
            'active_run_id'       => $activeRunId,
            'lists'               => $listReports->values()->all(),
            'publication_history' => $publicationHistory,
        ];
    }

    // ------------------------------------------------------------------
    // Comparison mode (canonical vs legacy)
    // ------------------------------------------------------------------

    /**
     * Compare canonical rows against legacy rows.
     * Returns: match_count, mismatch_count, mismatches[]
     */
    public function compareParity(Collection $canonical, Collection $legacy): array
    {
        if ($canonical->isEmpty() || $legacy->isEmpty()) {
            return [
                'status'        => 'no_comparison',
                'message'       => $canonical->isEmpty() ? 'No canonical rows.' : 'No legacy rows.',
                'match_count'   => 0,
                'mismatch_count'=> 0,
                'mismatches'    => [],
            ];
        }

        $canonicalByPlayer = $canonical->keyBy('player_id');
        $legacyByPlayer    = $legacy->keyBy('player_id');

        $allPlayerIds = $canonicalByPlayer->keys()->merge($legacyByPlayer->keys())->unique();

        $mismatches = [];
        $matchCount = 0;

        foreach ($allPlayerIds as $playerId) {
            $c = $canonicalByPlayer->get($playerId);
            $l = $legacyByPlayer->get($playerId);

            if (!$c) {
                $mismatches[] = ['player_id' => $playerId, 'reason' => 'missing_from_canonical', 'legacy_pts' => $l->total_points, 'canonical_pts' => null];
                continue;
            }
            if (!$l) {
                $mismatches[] = ['player_id' => $playerId, 'reason' => 'missing_from_legacy', 'legacy_pts' => null, 'canonical_pts' => $c->total_points];
                continue;
            }

            if ($c->total_points !== $l->total_points || $c->rank_position !== $l->rank_position) {
                $mismatches[] = [
                    'player_id'     => $playerId,
                    'reason'        => 'points_or_rank_mismatch',
                    'legacy_pts'    => $l->total_points,
                    'canonical_pts' => $c->total_points,
                    'legacy_rank'   => $l->rank_position,
                    'canonical_rank'=> $c->rank_position,
                ];
                continue;
            }

            $matchCount++;
        }

        $total = $allPlayerIds->count();

        return [
            'status'         => empty($mismatches) ? 'parity' : 'mismatch',
            'match_count'    => $matchCount,
            'mismatch_count' => count($mismatches),
            'total_players'  => $total,
            'mismatches'     => $mismatches,
        ];
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    private function identifyDroppedEvents(Collection $rows, Collection $ceIds): array
    {
        $eventsWithData = collect();
        foreach ($rows as $row) {
            $legs = array_merge(
                $row->meta_json['counting_legs'] ?? [],
                $row->meta_json['dropped_legs'] ?? []
            );
            foreach ($legs as $leg) {
                $eventsWithData->push($leg['category_event_id']);
            }
        }
        return $ceIds->diff($eventsWithData->unique())->values()->all();
    }

    private function collectTiebreakReasons(Collection $rows): array
    {
        $reasons = [];
        foreach ($rows as $row) {
            $notes = $row->meta_json['tiebreak_notes'] ?? [];
            foreach ($notes as $note) {
                $reasons[] = ['player_id' => $row->player_id, 'note' => $note];
            }
        }
        return $reasons;
    }

    private function identifyMissingPoints(Collection $ceIds, array $pointsMap): array
    {
        // Positions present in results for these CEs that have no points entry
        $positions = DB::table('positions')
            ->whereIn('category_event_id', $ceIds)
            ->distinct()
            ->pluck('position');

        return $positions->filter(fn($pos) => !isset($pointsMap[$pos]))->values()->all();
    }
}
