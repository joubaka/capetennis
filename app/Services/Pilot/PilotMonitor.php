<?php

namespace App\Services\Pilot;

use App\Models\Draw;
use App\Models\EngineRun;
use App\Models\EngineMismatch;
use App\Models\PlatformAuditLog;
use App\Models\PilotDrawApproval;
use App\Models\PilotFeedback;
use Illuminate\Support\Carbon;

/**
 * PilotMonitor
 *
 * Live metric computation for the limited public RR pilot.
 *
 * Alert thresholds (configurable via capetennis_engine config):
 *   mismatch_alert_pct   default 5.0%
 *   fallback_alert_pct   default 2.0%
 *   rollback_spike_count default 3  (in window)
 *   duplicate_alert_count default 2 (in window)
 */
class PilotMonitor
{
    public const DEFAULT_MISMATCH_ALERT_PCT    = 5.0;
    public const DEFAULT_FALLBACK_ALERT_PCT    = 2.0;
    public const DEFAULT_ROLLBACK_SPIKE        = 3;
    public const DEFAULT_DUPLICATE_ALERT_COUNT = 2;
    public const WINDOW_HOURS                  = 24;

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Compute live metrics for all approved pilot draws.
     * Returns per-draw metrics plus aggregated totals.
     *
     * @return array{draws: array, totals: array, alerts: array}
     */
    public static function snapshot(?\DateTimeInterface $since = null): array
    {
        $since ??= now()->subHours(self::WINDOW_HOURS);

        $approvedDrawIds = PilotDrawApproval::approved()->pluck('draw_id');
        if ($approvedDrawIds->isEmpty()) {
            return ['draws' => [], 'totals' => self::emptyTotals(), 'alerts' => []];
        }

        $draws   = [];
        $totals  = self::emptyTotals();
        $alerts  = [];

        foreach ($approvedDrawIds as $drawId) {
            $metrics = self::metricsForDraw($drawId, $since);
            $draws[$drawId] = $metrics;

            $totals['total_runs']       += $metrics['total_runs'];
            $totals['mismatch_count']   += $metrics['mismatch_count'];
            $totals['fallback_count']   += $metrics['fallback_count'];
            $totals['rollback_count']   += $metrics['rollback_count'];
            $totals['score_delete_count'] += $metrics['score_delete_count'];
            $totals['duplicate_count']  += $metrics['duplicate_count'];
            $totals['total_duration_ms'] += $metrics['total_duration_ms'];
            $totals['run_count_for_avg'] += $metrics['total_runs'];

            // Per-draw alert checks
            $drawAlerts = self::alertsForMetrics($drawId, $metrics);
            if (! empty($drawAlerts)) {
                $alerts[$drawId] = $drawAlerts;
            }
        }

        // Global mismatch / fallback pct
        if ($totals['total_runs'] > 0) {
            $totals['mismatch_pct'] = round(($totals['mismatch_count'] / $totals['total_runs']) * 100, 2);
            $totals['fallback_pct'] = round(($totals['fallback_count'] / $totals['total_runs']) * 100, 2);
            $totals['avg_duration_ms'] = (int) round($totals['total_duration_ms'] / $totals['total_runs']);
        }

        return compact('draws', 'totals', 'alerts');
    }

    /**
     * Per-draw metrics.
     */
    public static function metricsForDraw(int $drawId, ?\DateTimeInterface $since = null): array
    {
        $since ??= now()->subHours(self::WINDOW_HOURS);

        $runs = EngineRun::forDraw($drawId)
            ->where('created_at', '>=', $since)
            ->get();

        $totalRuns     = $runs->count();
        $mismatchCount = $runs->where('mismatch_detected', true)->count();
        $fallbackCount = $runs->where('fallback_used', true)->count();
        $totalMs       = $runs->sum('duration_ms');
        $avgMs         = $totalRuns > 0 ? (int) round($totalMs / $totalRuns) : 0;

        $rollbackCount = PlatformAuditLog::where('subject_type', 'draw')
            ->where('subject_id', $drawId)
            ->where('action', 'progression.reset')
            ->where('created_at', '>=', $since)
            ->count();

        $scoreDeleteCount = PlatformAuditLog::where('subject_type', 'draw')
            ->where('subject_id', $drawId)
            ->where('action', 'score.deleted')
            ->where('created_at', '>=', $since)
            ->count();

        $duplicateCount = PlatformAuditLog::where('subject_type', 'draw')
            ->where('subject_id', $drawId)
            ->whereIn('action', ['duplicate_progression', 'duplicate_result'])
            ->where('created_at', '>=', $since)
            ->count();

        $openFeedback = PilotFeedback::forDraw($drawId)->open()->count();

        $mismatchPct = $totalRuns > 0 ? round(($mismatchCount / $totalRuns) * 100, 2) : 0.0;
        $fallbackPct = $totalRuns > 0 ? round(($fallbackCount / $totalRuns) * 100, 2) : 0.0;

        return [
            'draw_id'           => $drawId,
            'total_runs'        => $totalRuns,
            'mismatch_count'    => $mismatchCount,
            'mismatch_pct'      => $mismatchPct,
            'fallback_count'    => $fallbackCount,
            'fallback_pct'      => $fallbackPct,
            'rollback_count'    => $rollbackCount,
            'score_delete_count'=> $scoreDeleteCount,
            'duplicate_count'   => $duplicateCount,
            'total_duration_ms' => $totalMs,
            'avg_duration_ms'   => $avgMs,
            'open_feedback'     => $openFeedback,
        ];
    }

    /**
     * Determine alerts for a draw's metrics.
     *
     * @return string[]
     */
    public static function alertsForMetrics(int $drawId, array $metrics): array
    {
        $alerts = [];

        $mismatchThreshold = (float) config('capetennis_engine.mismatch_alert_pct',
            self::DEFAULT_MISMATCH_ALERT_PCT);
        $fallbackThreshold = (float) config('capetennis_engine.fallback_alert_pct',
            self::DEFAULT_FALLBACK_ALERT_PCT);
        $rollbackSpike     = (int)   config('capetennis_engine.rollback_spike_count',
            self::DEFAULT_ROLLBACK_SPIKE);
        $duplicateAlert    = (int)   config('capetennis_engine.duplicate_alert_count',
            self::DEFAULT_DUPLICATE_ALERT_COUNT);

        if ($metrics['total_runs'] >= 5 && $metrics['mismatch_pct'] > $mismatchThreshold) {
            $alerts[] = "MISMATCH ALERT: draw #{$drawId} — {$metrics['mismatch_pct']}% (threshold {$mismatchThreshold}%)";
        }

        if ($metrics['total_runs'] >= 5 && $metrics['fallback_pct'] > $fallbackThreshold) {
            $alerts[] = "FALLBACK ALERT: draw #{$drawId} — {$metrics['fallback_pct']}% (threshold {$fallbackThreshold}%)";
        }

        if ($metrics['rollback_count'] >= $rollbackSpike) {
            $alerts[] = "ROLLBACK SPIKE: draw #{$drawId} — {$metrics['rollback_count']} rollbacks in window";
        }

        if ($metrics['duplicate_count'] >= $duplicateAlert) {
            $alerts[] = "DUPLICATE PROGRESSION: draw #{$drawId} — {$metrics['duplicate_count']} duplicate events in window";
        }

        return $alerts;
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    private static function emptyTotals(): array
    {
        return [
            'total_runs'         => 0,
            'mismatch_count'     => 0,
            'mismatch_pct'       => 0.0,
            'fallback_count'     => 0,
            'fallback_pct'       => 0.0,
            'rollback_count'     => 0,
            'score_delete_count' => 0,
            'duplicate_count'    => 0,
            'total_duration_ms'  => 0,
            'run_count_for_avg'  => 0,
            'avg_duration_ms'    => 0,
        ];
    }
}
