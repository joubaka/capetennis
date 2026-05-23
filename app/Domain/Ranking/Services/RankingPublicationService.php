<?php

namespace App\Domain\Ranking\Services;

use App\Domain\Ranking\Enums\RankingStatus;
use App\Models\Series;
use App\Models\SeriesRanking;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RankingPublicationService
 *
 * Manages the lifecycle of a ranking from Calculated → Reviewed → Published.
 * Publishing is guarded: only Reviewed rows can be published, and an existing
 * Published snapshot is atomically archived before the new one goes live.
 *
 * No ranking row may be mutated once it reaches Published status — callers must
 * use RankingRebuildService to generate a new Calculated set first.
 */
final class RankingPublicationService
{
    public function __construct(
        private readonly RankingAuditService $auditor,
    ) {}

    // ------------------------------------------------------------------
    // Review
    // ------------------------------------------------------------------

    /**
     * Mark all Calculated rows for a series as Reviewed.
     * Must be called by an admin before publication is allowed.
     */
    public function markReviewed(Series $series, int $userId): void
    {
        $updated = SeriesRanking::where('series_id', $series->id)
            ->where('status', RankingStatus::Calculated->value)
            ->update([
                'status'      => RankingStatus::Reviewed->value,
                'reviewed_by' => $userId,
                'reviewed_at' => now(),
            ]);

        Log::info('[RankingPublication] Marked reviewed', [
            'series_id' => $series->id,
            'rows'      => $updated,
            'user_id'   => $userId,
        ]);

        $this->auditor->recordStatusChange($series, RankingStatus::Calculated, RankingStatus::Reviewed, $userId);
    }

    // ------------------------------------------------------------------
    // Publish
    // ------------------------------------------------------------------

    /**
     * Publish the current Reviewed set for a series.
     *
     * Steps:
     *  1. Guard: confirm Reviewed rows exist.
     *  2. Archive any currently Published rows.
     *  3. Promote Reviewed rows to Published.
     *  4. Record audit entry.
     *
     * @throws \RuntimeException if no Reviewed rows exist.
     */
    public function publish(Series $series, int $userId): void
    {
        DB::transaction(function () use ($series, $userId) {

            $reviewedCount = SeriesRanking::where('series_id', $series->id)
                ->where('status', RankingStatus::Reviewed->value)
                ->count();

            if ($reviewedCount === 0) {
                throw new \RuntimeException(
                    "No reviewed ranking rows found for series {$series->id}. Run and review a rebuild first."
                );
            }

            // Archive old Published rows
            $archived = SeriesRanking::where('series_id', $series->id)
                ->where('status', RankingStatus::Published->value)
                ->update(['status' => RankingStatus::Archived->value]);

            // Promote Reviewed → Published
            $published = SeriesRanking::where('series_id', $series->id)
                ->where('status', RankingStatus::Reviewed->value)
                ->update([
                    'status'       => RankingStatus::Published->value,
                    'published_by' => $userId,
                    'published_at' => now(),
                ]);

            Log::info('[RankingPublication] Published', [
                'series_id' => $series->id,
                'published' => $published,
                'archived'  => $archived,
                'user_id'   => $userId,
            ]);

            $this->auditor->recordStatusChange($series, RankingStatus::Reviewed, RankingStatus::Published, $userId);
        });
    }

    // ------------------------------------------------------------------
    // Unpublish / Rollback
    // ------------------------------------------------------------------

    /**
     * Roll back the current publication by restoring the most recent Archived snapshot.
     * The current Published rows are deleted (they will be regenerated on next rebuild).
     */
    public function rollback(Series $series, int $userId): void
    {
        DB::transaction(function () use ($series, $userId) {

            $publishedCount = SeriesRanking::where('series_id', $series->id)
                ->where('status', RankingStatus::Published->value)
                ->count();

            if ($publishedCount === 0) {
                throw new \RuntimeException("No published ranking found for series {$series->id}.");
            }

            // Remove the current publication
            SeriesRanking::where('series_id', $series->id)
                ->where('status', RankingStatus::Published->value)
                ->delete();

            // Restore the most recent archived run
            $latestRunId = SeriesRanking::where('series_id', $series->id)
                ->where('status', RankingStatus::Archived->value)
                ->orderByDesc('updated_at')
                ->value('run_id');

            if ($latestRunId) {
                SeriesRanking::where('series_id', $series->id)
                    ->where('status', RankingStatus::Archived->value)
                    ->where('run_id', $latestRunId)
                    ->update(['status' => RankingStatus::Published->value]);
            }

            Log::info('[RankingPublication] Rolled back', [
                'series_id'       => $series->id,
                'restored_run_id' => $latestRunId,
                'user_id'         => $userId,
            ]);

            $this->auditor->recordStatusChange($series, RankingStatus::Published, RankingStatus::Archived, $userId, [
                'action'          => 'rollback',
                'restored_run_id' => $latestRunId,
            ]);
        });
    }

    // ------------------------------------------------------------------
    // Guards
    // ------------------------------------------------------------------

    /**
     * Throw if a Published ranking exists and the caller has not acknowledged it.
     * Use before destructive operations that would overwrite a live ranking.
     */
    public function requireNotPublished(Series $series): void
    {
        $exists = SeriesRanking::where('series_id', $series->id)
            ->where('status', RankingStatus::Published->value)
            ->exists();

        if ($exists) {
            throw new \RuntimeException(
                "Series {$series->id} has a published ranking. Archive it or roll back before rebuilding."
            );
        }
    }
}
