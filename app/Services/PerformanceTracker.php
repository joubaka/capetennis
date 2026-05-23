<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * PerformanceTracker
 *
 * Lightweight latency tracker that records durations and fires warning logs
 * when operations exceed defined thresholds.
 *
 * Usage (wrap any operation):
 *
 *   $result = PerformanceTracker::track('draw.generation', $drawId, function () {
 *       return $this->generator->generate($draw);
 *   });
 *
 * Or manually:
 *   $timer = PerformanceTracker::start('standings.calculation');
 *   // ... work ...
 *   PerformanceTracker::end($timer, $drawId);
 */
class PerformanceTracker
{
    // ------------------------------------------------------------------
    // Operation keys (canonical)
    // ------------------------------------------------------------------
    public const DRAW_GENERATION       = 'draw.generation';
    public const STANDINGS_CALCULATION = 'standings.calculation';
    public const SVG_RENDER            = 'svg.render';
    public const SCHEDULING            = 'scheduling';
    public const SCORE_SAVE            = 'score.save';
    public const PAYMENT_COMPLETION    = 'payment.completion';

    /**
     * Warning thresholds in milliseconds.
     * Exceeding these emits a LOG_LEVEL_WARNING.
     * Exceeding 3× these emits a LOG_LEVEL_ERROR.
     */
    private const THRESHOLDS_MS = [
        self::DRAW_GENERATION       => 3000,
        self::STANDINGS_CALCULATION => 1500,
        self::SVG_RENDER            => 2000,
        self::SCHEDULING            => 5000,
        self::SCORE_SAVE            => 500,
        self::PAYMENT_COMPLETION    => 4000,
    ];

    /** Per-request in-memory slow-operation log. */
    private static array $log = [];

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    /**
     * Wrap a callable and record its duration.
     */
    public static function track(string $operation, int|string|null $subjectId, callable $callback): mixed
    {
        $start  = hrtime(true);
        $result = $callback();
        $ms     = (int) round((hrtime(true) - $start) / 1e6);

        self::record($operation, $ms, $subjectId);

        return $result;
    }

    /**
     * Start a manual timer. Returns a context array.
     */
    public static function start(string $operation): array
    {
        return ['operation' => $operation, 'start' => hrtime(true)];
    }

    /**
     * End a manual timer and record the result.
     */
    public static function end(array $timer, int|string|null $subjectId = null): int
    {
        $ms = (int) round((hrtime(true) - $timer['start']) / 1e6);
        self::record($timer['operation'], $ms, $subjectId);
        return $ms;
    }

    /**
     * Get all slow operations from the current request.
     */
    public static function slowOps(int $thresholdMs = 0): array
    {
        return array_filter(
            self::$log,
            fn ($entry) => $entry['ms'] > ($thresholdMs ?: (self::THRESHOLDS_MS[$entry['operation']] ?? PHP_INT_MAX))
        );
    }

    /**
     * Reset per-request log (useful in tests).
     */
    public static function reset(): void
    {
        self::$log = [];
    }

    // ------------------------------------------------------------------
    // Internal
    // ------------------------------------------------------------------

    private static function record(string $operation, int $ms, int|string|null $subjectId): void
    {
        self::$log[] = [
            'operation'  => $operation,
            'subject_id' => $subjectId,
            'ms'         => $ms,
            'ts'         => now()->toIso8601String(),
        ];

        $threshold = self::THRESHOLDS_MS[$operation] ?? null;
        if ($threshold === null) {
            return;
        }

        $context = ['operation' => $operation, 'subject_id' => $subjectId, 'ms' => $ms, 'threshold_ms' => $threshold];

        if ($ms >= $threshold * 3) {
            Log::error("[PerformanceTracker] CRITICAL slow operation: {$operation}", $context);
        } elseif ($ms >= $threshold) {
            Log::warning("[PerformanceTracker] Slow operation: {$operation}", $context);
        }
    }
}
