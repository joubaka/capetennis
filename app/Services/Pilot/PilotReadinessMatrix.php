<?php

namespace App\Services\Pilot;

use App\Models\Draw;
use App\Models\EngineRun;
use App\Models\EngineMismatch;
use App\Models\PilotDrawApproval;
use App\Models\PilotFeedback;

/**
 * PilotReadinessMatrix
 *
 * Tracks readiness independently per domain.
 * Domains must NEVER be combined — each has its own threshold and criteria.
 *
 * Domains:
 *   rr_canonical     — canonical RR engine
 *   playoff_canonical — canonical playoff (not yet started)
 *   feed_in          — feed-in/consolation (not yet started)
 *   scheduling       — OOP/scheduling layer
 *   rendering        — bracket/standings rendering
 */
class PilotReadinessMatrix
{
    public const DOMAIN_RR_CANONICAL      = 'rr_canonical';
    public const DOMAIN_PLAYOFF_CANONICAL = 'playoff_canonical';
    public const DOMAIN_FEED_IN           = 'feed_in';
    public const DOMAIN_SCHEDULING        = 'scheduling';
    public const DOMAIN_RENDERING         = 'rendering';

    public const ALL_DOMAINS = [
        self::DOMAIN_RR_CANONICAL,
        self::DOMAIN_PLAYOFF_CANONICAL,
        self::DOMAIN_FEED_IN,
        self::DOMAIN_SCHEDULING,
        self::DOMAIN_RENDERING,
    ];

    // Minimum approved draws + runs before a domain can reach READY
    private const MIN_RR_DRAWS    = 1;
    private const MIN_RR_RUNS     = 3;
    private const MAX_RR_MISMATCH = 5.0; // %
    private const MAX_RR_FALLBACK = 2.0; // %

    // Levels: not_started | in_progress | ready | blocked
    public const LEVEL_NOT_STARTED  = 'not_started';
    public const LEVEL_IN_PROGRESS  = 'in_progress';
    public const LEVEL_READY        = 'ready';
    public const LEVEL_BLOCKED      = 'blocked';

    // ------------------------------------------------------------------

    /**
     * Compute full readiness matrix.
     *
     * @return array<string, array{level: string, notes: string[], metrics: array}>
     */
    public static function compute(): array
    {
        return [
            self::DOMAIN_RR_CANONICAL      => self::computeRR(),
            self::DOMAIN_PLAYOFF_CANONICAL => self::computeNotStarted('Playoff canonical is not part of the current pilot scope.'),
            self::DOMAIN_FEED_IN           => self::computeNotStarted('Feed-in canonical is not part of the current pilot scope.'),
            self::DOMAIN_SCHEDULING        => self::computeScheduling(),
            self::DOMAIN_RENDERING         => self::computeRendering(),
        ];
    }

    // ------------------------------------------------------------------
    // Per-domain computations
    // ------------------------------------------------------------------

    private static function computeRR(): array
    {
        $notes   = [];
        $blocked = [];

        $approvedCount = PilotDrawApproval::approved()->count();
        $totalRuns     = EngineRun::where('engine_mode', 'canonical')->count();
        $mismatchRuns  = EngineRun::where('engine_mode', 'canonical')
            ->where('mismatch_detected', true)->count();
        $fallbackRuns  = EngineRun::where('engine_mode', 'canonical')
            ->where('fallback_used', true)->count();

        $mismatchPct = $totalRuns > 0 ? round(($mismatchRuns / $totalRuns) * 100, 2) : 0.0;
        $fallbackPct = $totalRuns > 0 ? round(($fallbackRuns / $totalRuns) * 100, 2) : 0.0;

        $openFeedback = PilotFeedback::open()
            ->whereIn('category', [PilotFeedback::CAT_SCORING, PilotFeedback::CAT_STANDINGS])
            ->count();

        $unresolvedHigh = EngineMismatch::unresolved()
            ->where('severity', 'high')->count();

        if ($approvedCount < self::MIN_RR_DRAWS) {
            $notes[] = "No approved RR pilot draws yet ({$approvedCount}).";
        } else {
            $notes[] = "Approved pilot draws: {$approvedCount}";
        }

        if ($totalRuns < self::MIN_RR_RUNS) {
            $notes[] = "Insufficient canonical runs ({$totalRuns} < " . self::MIN_RR_RUNS . " required).";
        } else {
            $notes[] = "Canonical runs: {$totalRuns}  mismatches: {$mismatchPct}%  fallbacks: {$fallbackPct}%";
        }

        if ($unresolvedHigh > 0) {
            $blocked[] = "Unresolved HIGH severity mismatches: {$unresolvedHigh}";
        }

        if ($mismatchPct > self::MAX_RR_MISMATCH) {
            $blocked[] = "Mismatch rate {$mismatchPct}% exceeds threshold " . self::MAX_RR_MISMATCH . "%.";
        }

        if ($fallbackPct > self::MAX_RR_FALLBACK) {
            $blocked[] = "Fallback rate {$fallbackPct}% exceeds threshold " . self::MAX_RR_FALLBACK . "%.";
        }

        if ($openFeedback > 0) {
            $blocked[] = "Open scoring/standings feedback items: {$openFeedback}";
        }

        if (! empty($blocked)) {
            $level = self::LEVEL_BLOCKED;
        } elseif ($approvedCount >= self::MIN_RR_DRAWS && $totalRuns >= self::MIN_RR_RUNS) {
            $level = self::LEVEL_READY;
        } elseif ($approvedCount > 0) {
            $level = self::LEVEL_IN_PROGRESS;
        } else {
            $level = self::LEVEL_NOT_STARTED;
        }

        return [
            'level'   => $level,
            'notes'   => array_merge($notes, $blocked),
            'metrics' => compact(
                'approvedCount', 'totalRuns', 'mismatchPct',
                'fallbackPct', 'openFeedback', 'unresolvedHigh'
            ),
        ];
    }

    private static function computeScheduling(): array
    {
        $openScheduling = PilotFeedback::open()
            ->where('category', PilotFeedback::CAT_SCHEDULING)
            ->count();

        $notes = ["Open scheduling feedback: {$openScheduling}"];
        $level = $openScheduling > 0 ? self::LEVEL_IN_PROGRESS : self::LEVEL_IN_PROGRESS;

        return [
            'level'   => $level,
            'notes'   => $notes,
            'metrics' => ['open_feedback' => $openScheduling],
        ];
    }

    private static function computeRendering(): array
    {
        $openRendering = PilotFeedback::open()
            ->where('category', PilotFeedback::CAT_RENDERING)
            ->count();

        $notes = ["Open rendering feedback: {$openRendering}"];

        return [
            'level'   => self::LEVEL_IN_PROGRESS,
            'notes'   => $notes,
            'metrics' => ['open_feedback' => $openRendering],
        ];
    }

    private static function computeNotStarted(string $note): array
    {
        return [
            'level'   => self::LEVEL_NOT_STARTED,
            'notes'   => [$note],
            'metrics' => [],
        ];
    }
}
