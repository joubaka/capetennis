<?php

namespace App\Domain\Engine;

use App\Domain\Draws\Services\RoundRobinGenerationService;
use App\Domain\Draws\Services\PlayoffGenerationService;
use App\Domain\Draws\Services\StandingsService;
use App\Domain\Draws\Services\ByeAdvancementService;
use App\Domain\Draws\Services\BracketRenderService;
use App\Domain\Fixtures\Services\FixtureProgressionService;
use App\Models\Draw;
use App\Models\EngineComparisonLog;
use App\Models\EngineMismatch;
use App\Models\EngineRun;
use App\Models\Fixture;
use App\Services\FeatureFlags;
use App\Services\PerformanceTracker;
use App\Services\PlatformAuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * EngineRouter
 *
 * Central dispatch layer for all draw/fixture engine operations.
 *
 * In LEGACY mode  → delegates to provided legacy callables only.
 * In HYBRID mode  → runs canonical, compares with legacy, logs mismatches,
 *                   falls back to legacy on canonical exception.
 * In CANONICAL    → runs canonical only, no legacy calls.
 *
 * Controllers and services inject this class instead of calling legacy
 * services or canonical services directly.
 */
final class EngineRouter
{
    // ------------------------------------------------------------------
    // MODE CONSTANTS
    // ------------------------------------------------------------------
    public const MODE_LEGACY    = 'legacy';
    public const MODE_HYBRID    = 'hybrid';
    public const MODE_CANONICAL = 'canonical';

    private string $mode;
    private bool   $autoFallback;
    private ?string $logChannel;
    private string  $logLevel;

    private RoundRobinGenerationService $rr;
    private PlayoffGenerationService    $playoff;
    private StandingsService            $standings;
    private ByeAdvancementService       $byes;
    private BracketRenderService        $render;
    private FixtureProgressionService   $progression;

    // Counters accessible by the debug panel
    private static int $mismatchCount  = 0;
    private static int $fallbackCount  = 0;
    private static array $mismatchLog  = [];

    public function __construct(
        RoundRobinGenerationService $rr,
        PlayoffGenerationService    $playoff,
        StandingsService            $standings,
        ByeAdvancementService       $byes,
        BracketRenderService        $render,
        FixtureProgressionService   $progression
    ) {
        $this->rr          = $rr;
        $this->playoff     = $playoff;
        $this->standings   = $standings;
        $this->byes        = $byes;
        $this->render      = $render;
        $this->progression = $progression;

        $this->mode         = config('capetennis_engine.mode', self::MODE_HYBRID);
        $this->autoFallback = (bool) config('capetennis_engine.auto_fallback', true);
        $this->logChannel   = config('capetennis_engine.comparison_log_channel');
        $this->logLevel     = config('capetennis_engine.comparison_log_level', 'warning');
    }

    // ------------------------------------------------------------------
    // MISMATCH THRESHOLD AUTO-ROLLBACK
    // ------------------------------------------------------------------

    /**
     * Check if the recent mismatch rate exceeds the configured threshold.
     * If exceeded, auto-downgrade this router instance to hybrid and audit-log it.
     * Threshold configured via DRAW_ENGINE_MISMATCH_THRESHOLD (default 5.0%).
     */
    private function checkMismatchThreshold(int $drawId): void
    {
        $threshold = (float) config('capetennis_engine.mismatch_rollback_threshold', 25);
        if ($threshold <= 0) {
            return;
        }

        try {
            $since  = now()->subHours(2);
            $total  = EngineRun::where('created_at', '>=', $since)->count();
            $misses = EngineRun::where('created_at', '>=', $since)->where('mismatch_detected', true)->count();

            if ($total < 10) {
                return; // not enough data to trigger threshold
            }

            $pct = ($misses / $total) * 100;

            if ($pct > $threshold) {
                Log::critical('[EngineRouter] Mismatch rate {pct}% exceeds threshold {threshold}% — auto-downgrading to hybrid', [
                    'pct' => round($pct, 1), 'threshold' => $threshold, 'draw_id' => $drawId,
                ]);

                PlatformAuditLogger::log(
                    PlatformAuditLogger::ENGINE_FALLBACK,
                    null,
                    ['mode' => $this->mode, 'mismatch_pct' => round($pct, 1)],
                    ['mode' => self::MODE_HYBRID],
                    ['reason' => 'auto_rollback_threshold', 'threshold' => $threshold, 'draw_id' => $drawId],
                );

                $this->mode = self::MODE_HYBRID;
            }
        } catch (\Throwable $e) {
            Log::error('[EngineRouter] Could not check mismatch threshold', ['error' => $e->getMessage()]);
        }
    }

    // ------------------------------------------------------------------
    // PUBLIC: MODE INSPECTION
    // ------------------------------------------------------------------

    public function mode(): string  { return $this->mode; }
    public function isLegacy(): bool    { return $this->mode === self::MODE_LEGACY; }
    public function isHybrid(): bool    { return $this->mode === self::MODE_HYBRID; }
    public function isCanonical(): bool { return $this->mode === self::MODE_CANONICAL; }

    public static function mismatchCount(): int    { return self::$mismatchCount; }
    public static function fallbackCount(): int    { return self::$fallbackCount; }
    public static function mismatchLog(): array    { return self::$mismatchLog; }
    public static function resetCounters(): void   { self::$mismatchCount = 0; self::$fallbackCount = 0; self::$mismatchLog = []; }

    /**
     * Return a copy of this router with the mode overridden by the draw's
     * effective engine mode (draw → event → global config).
     *
     * If canonical is requested but blocked (unresolved P0/P1 mismatches),
     * falls back to hybrid and logs a warning.
     */
    public function forDraw(Draw $draw): static
    {
        $clone = clone $this;

        // Three-layer resolution: per-draw DB override → FeatureFlags (event/admin/env) → config
        $effective = $draw->effectiveEngineModeWithFlags();

        if ($effective === self::MODE_CANONICAL && ! $draw->canonicalAllowed()) {
            $channel = $clone->logChannel ? Log::channel($clone->logChannel) : Log::getFacadeRoot();
            $channel->warning('[EngineRouter] Canonical mode blocked for draw #{draw_id} — unresolved HIGH/MEDIUM mismatches. Falling back to hybrid.', [
                'draw_id' => $draw->id,
            ]);
            PlatformAuditLogger::log(
                PlatformAuditLogger::ENGINE_FALLBACK,
                $draw,
                ['mode' => 'canonical'],
                ['mode' => 'hybrid'],
                ['reason' => 'unresolved_mismatches'],
            );
            $effective = self::MODE_HYBRID;
        }

        $clone->mode = $effective;
        return $clone;
    }

    /**
     * Safety check: can canonical mode be enabled for a draw right now?
     * Returns array with allowed (bool) and reason (string).
     */
    public static function canonicalSafetyCheck(Draw $draw): array
    {
        $unresolvedHigh = \App\Models\EngineMismatch::forDraw($draw->id)
            ->unresolved()
            ->where('severity', 'high')
            ->count();

        $unresolvedMedium = \App\Models\EngineMismatch::forDraw($draw->id)
            ->unresolved()
            ->where('severity', 'medium')
            ->count();

        if ($unresolvedHigh > 0) {
            return [
                'allowed' => false,
                'reason'  => "Draw #{$draw->id} has {$unresolvedHigh} unresolved HIGH severity mismatch(es). Resolve before enabling canonical mode.",
            ];
        }

        if ($unresolvedMedium > 0) {
            return [
                'allowed' => false,
                'reason'  => "Draw #{$draw->id} has {$unresolvedMedium} unresolved MEDIUM severity mismatch(es). Resolve or mark resolved before enabling canonical mode.",
            ];
        }

        return ['allowed' => true, 'reason' => 'No blocking mismatches.'];
    }

    // ------------------------------------------------------------------
    // PUBLIC: RR GENERATION
    // ------------------------------------------------------------------

    /**
     * Route round-robin generation.
     *
     * @param  Draw      $draw
     * @param  callable  $legacyFn  fn(Draw): void  — legacy generator callable
     */
    public function generateRoundRobin(Draw $draw, callable $legacyFn): void
    {
        if ($this->isLegacy()) {
            PerformanceTracker::track(PerformanceTracker::DRAW_GENERATION, $draw->id, function () use ($draw, $legacyFn) {
                $legacyFn($draw);
            });
            PlatformAuditLogger::log(PlatformAuditLogger::DRAW_GENERATED, $draw, null, null,
                ['type' => 'rr', 'engine_mode' => 'legacy']);
            return;
        }

        $this->checkMismatchThreshold($draw->id);

        PerformanceTracker::track(PerformanceTracker::DRAW_GENERATION, $draw->id, function () use ($draw, $legacyFn) {
            $this->runCanonicalWithFallback(
                operation: 'rr_generation',
                drawId:    $draw->id,
                canonical: function () use ($draw) {
                    $this->rr->generate($draw);
                },
                legacy: function () use ($draw, $legacyFn) {
                    $legacyFn($draw);
                },
                compare: function () use ($draw) {
                    return $this->compareRRGeneration($draw);
                }
            );
        });

        PlatformAuditLogger::log(PlatformAuditLogger::DRAW_GENERATED, $draw, null, null,
            ['type' => 'rr', 'engine_mode' => $this->mode]);
    }

    // ------------------------------------------------------------------
    // PUBLIC: STANDINGS
    // ------------------------------------------------------------------

    /**
     * Route standings calculation.
     *
     * @param  Draw      $draw
     * @param  callable  $legacyFn  fn(Draw): array — legacy standings callable
     * @return array
     */
    public function standings(Draw $draw, callable $legacyFn): array
    {
        if ($this->isLegacy()) {
            return PerformanceTracker::track(
                PerformanceTracker::STANDINGS_CALCULATION, $draw->id, fn () => $legacyFn($draw)
            );
        }

        $this->checkMismatchThreshold($draw->id);

        $startMs        = (int) round(microtime(true) * 1000);
        $mismatchBefore = self::$mismatchCount;

        try {
            $timer     = PerformanceTracker::start(PerformanceTracker::STANDINGS_CALCULATION);
            $canonical = $this->standings->forDraw($draw);
            PerformanceTracker::end($timer, $draw->id);

            if ($this->isHybrid()) {
                try {
                    $legacy = $legacyFn($draw);
                    $this->compareStandings($draw->id, $legacy, $canonical);
                } catch (\Throwable $e) {
                    $this->logMismatch('standings', $draw->id, 'legacy_standings_threw', $e->getMessage(), null);
                }
            }

            $this->persistEngineRun($draw->id, 'standings', true, null, false,
                self::$mismatchCount - $mismatchBefore,
                (int) round(microtime(true) * 1000) - $startMs, null);

            return $canonical;

        } catch (\Throwable $e) {
            $this->handleCanonicalError('standings', $draw->id, $e);

            PlatformAuditLogger::log(
                PlatformAuditLogger::ENGINE_FALLBACK,
                $draw,
                ['mode' => $this->mode, 'operation' => 'standings'],
                ['fallback' => 'legacy'],
                ['reason' => $e->getMessage()],
            );

            $this->persistEngineRun($draw->id, 'standings', false, null, $this->autoFallback,
                self::$mismatchCount - $mismatchBefore,
                (int) round(microtime(true) * 1000) - $startMs, $e->getMessage());

            if ($this->autoFallback) {
                return PerformanceTracker::track(
                    PerformanceTracker::STANDINGS_CALCULATION, $draw->id, fn () => $legacyFn($draw)
                );
            }
            throw $e;
        }
    }

    // ------------------------------------------------------------------
    // PUBLIC: FIXTURE PROGRESSION
    // ------------------------------------------------------------------

    /**
     * Route winner/loser advancement.
     *
     * @param  Fixture   $fixture
     * @param  int       $winner
     * @param  int       $loser
     * @param  callable  $legacyFn  fn(Fixture, int, int): void
     */
    public function advanceFixture(Fixture $fixture, int $winner, int $loser, callable $legacyFn): void
    {
        if ($this->isLegacy()) {
            PerformanceTracker::track(PerformanceTracker::SCORE_SAVE, $fixture->id, function () use ($fixture, $winner, $loser, $legacyFn) {
                $legacyFn($fixture, $winner, $loser);
            });
            PlatformAuditLogger::log(PlatformAuditLogger::PROGRESSION_ADVANCED, $fixture,
                null, ['winner' => $winner, 'loser' => $loser],
                ['engine_mode' => 'legacy', 'draw_id' => $fixture->draw_id]);
            return;
        }

        $this->checkMismatchThreshold($fixture->draw_id);

        PerformanceTracker::track(PerformanceTracker::SCORE_SAVE, $fixture->id, function () use ($fixture, $winner, $loser, $legacyFn) {
            $this->runCanonicalWithFallback(
                operation: 'progression',
                drawId:    $fixture->draw_id,
                canonical: function () use ($fixture, $winner, $loser) {
                    $this->progression->advance($fixture, $winner, $loser);
                },
                legacy: function () use ($fixture, $winner, $loser, $legacyFn) {
                    $legacyFn($fixture, $winner, $loser);
                },
                compare: function () use ($fixture, $winner, $loser) {
                    return $this->compareProgression($fixture, $winner, $loser);
                }
            );
        });

        PlatformAuditLogger::log(PlatformAuditLogger::PROGRESSION_ADVANCED, $fixture,
            null, ['winner' => $winner, 'loser' => $loser],
            ['engine_mode' => $this->mode, 'draw_id' => $fixture->draw_id]);
    }

    /**
     * Route score rollback.
     *
     * @param  Fixture   $fixture
     * @param  callable  $legacyFn  fn(Fixture): void
     */
    public function rollbackFixture(Fixture $fixture, callable $legacyFn): void
    {
        if ($this->isLegacy()) {
            $legacyFn($fixture);
            PlatformAuditLogger::log(PlatformAuditLogger::PROGRESSION_RESET, $fixture,
                null, null, ['engine_mode' => 'legacy', 'draw_id' => $fixture->draw_id]);
            return;
        }

        $this->runCanonicalWithFallback(
            operation: 'rollback',
            drawId:    $fixture->draw_id,
            canonical: function () use ($fixture) {
                $this->progression->rollback($fixture);
            },
            legacy: function () use ($fixture, $legacyFn) {
                $legacyFn($fixture);
            },
            compare: null
        );

        PlatformAuditLogger::log(PlatformAuditLogger::PROGRESSION_RESET, $fixture,
            null, null, ['engine_mode' => $this->mode, 'draw_id' => $fixture->draw_id]);
    }

    // ------------------------------------------------------------------
    // PUBLIC: BYE ADVANCEMENT
    // ------------------------------------------------------------------

    /**
     * Route BYE advancement.
     *
     * @param  Draw      $draw
     * @param  callable  $legacyFn  fn(Draw): void
     */
    public function advanceByes(Draw $draw, callable $legacyFn): void
    {
        if ($this->isLegacy()) {
            $legacyFn($draw);
            return;
        }

        $this->runCanonicalWithFallback(
            operation: 'bye_advancement',
            drawId:    $draw->id,
            canonical: function () use ($draw) {
                $this->byes->advance($draw);
            },
            legacy: function () use ($draw, $legacyFn) {
                $legacyFn($draw);
            },
            compare: null
        );
    }

    // ------------------------------------------------------------------
    // PUBLIC: BRACKET RENDERING DATA
    // ------------------------------------------------------------------

    /**
     * Route bracket data build (read-only).
     *
     * @param  Draw      $draw
     * @param  callable  $legacyFn  fn(Draw): array
     * @return array
     */
    public function bracketData(Draw $draw, callable $legacyFn): array
    {
        if ($this->isLegacy()) {
            return $legacyFn($draw);
        }

        $startMs        = (int) round(microtime(true) * 1000);
        $mismatchBefore = self::$mismatchCount;

        try {
            $canonical = $this->render->buildBracketData($draw);

            if ($this->isHybrid()) {
                try {
                    $legacy = $legacyFn($draw);
                    $this->compareBracketData($draw->id, $legacy, $canonical);
                } catch (\Throwable $e) {
                    $this->logMismatch('bracket_render', $draw->id, 'legacy_render_threw', $e->getMessage(), null);
                }
            }

            $this->persistEngineRun($draw->id, 'bracket_render', true, null, false,
                self::$mismatchCount - $mismatchBefore,
                (int) round(microtime(true) * 1000) - $startMs, null);

            return $canonical;

        } catch (\Throwable $e) {
            $this->handleCanonicalError('bracket_render', $draw->id, $e);

            $this->persistEngineRun($draw->id, 'bracket_render', false, null, $this->autoFallback,
                self::$mismatchCount - $mismatchBefore,
                (int) round(microtime(true) * 1000) - $startMs, $e->getMessage());

            if ($this->autoFallback) {
                return $legacyFn($draw);
            }
            throw $e;
        }
    }

    // ------------------------------------------------------------------
    // PRIVATE: CORE RUNNER
    // ------------------------------------------------------------------

    /**
     * Run canonical, optionally compare with legacy in hybrid mode, fall back on error.
     */
    private function runCanonicalWithFallback(
        string    $operation,
        int       $drawId,
        callable  $canonical,
        callable  $legacy,
        ?callable $compare
    ): void {
        $startMs         = (int) round(microtime(true) * 1000);
        $canonSuccess    = false;
        $legacySuccess   = null;
        $fallbackUsed    = false;
        $mismatchBefore  = self::$mismatchCount;
        $exceptionMsg    = null;

        try {
            $canonical();
            $canonSuccess = true;

            if ($this->isHybrid() && $compare !== null) {
                try {
                    $compare();
                } catch (\Throwable $e) {
                    $this->logMismatch($operation, $drawId, 'comparison_threw', $e->getMessage(), null);
                }
            }

        } catch (\Throwable $e) {
            $exceptionMsg = $e->getMessage();
            $this->handleCanonicalError($operation, $drawId, $e);

            if ($this->autoFallback) {
                PlatformAuditLogger::log(
                    PlatformAuditLogger::ENGINE_FALLBACK,
                    null,
                    ['mode' => $this->mode, 'operation' => $operation],
                    ['fallback' => 'legacy'],
                    ['reason' => $exceptionMsg, 'draw_id' => $drawId],
                );

                try {
                    $legacy();
                    $legacySuccess = true;
                } catch (\Throwable $le) {
                    $legacySuccess = false;
                    $this->persistEngineRun($drawId, $operation, false, false, true,
                        self::$mismatchCount - $mismatchBefore,
                        (int) round(microtime(true) * 1000) - $startMs,
                        $exceptionMsg);
                    throw $le;
                }
                $fallbackUsed = true;
                $this->persistEngineRun($drawId, $operation, false, $legacySuccess, true,
                    self::$mismatchCount - $mismatchBefore,
                    (int) round(microtime(true) * 1000) - $startMs,
                    $exceptionMsg);
                return;
            }
            $this->persistEngineRun($drawId, $operation, false, null, false,
                self::$mismatchCount - $mismatchBefore,
                (int) round(microtime(true) * 1000) - $startMs,
                $exceptionMsg);
            throw $e;
        }

        $this->persistEngineRun($drawId, $operation, $canonSuccess, $legacySuccess, $fallbackUsed,
            self::$mismatchCount - $mismatchBefore,
            (int) round(microtime(true) * 1000) - $startMs,
            $exceptionMsg);
    }

    // ------------------------------------------------------------------
    // PRIVATE: COMPARISON HELPERS
    // ------------------------------------------------------------------

    private function compareRRGeneration(Draw $draw): void
    {
        $draw->refresh()->load(['drawFixtures', 'groups.groupRegistrations']);

        $canonFixtures = $draw->drawFixtures
            ->where('stage', 'RR')
            ->sortBy(['draw_group_id', 'match_nr'])
            ->map(fn($f) => [
                'group_id' => $f->draw_group_id,
                'r1'       => $f->registration1_id,
                'r2'       => $f->registration2_id,
            ])
            ->values()
            ->toArray();

        // Record fixture fingerprint for later comparison
        $this->logMismatch('rr_generation', $draw->id, 'canonical_snapshot', null, [
            'fixture_count' => count($canonFixtures),
            'fixtures'      => $canonFixtures,
        ]);
    }

    private function compareStandings(int $drawId, array $legacy, array $canonical): void
    {
        foreach ($canonical as $groupId => $canonRows) {
            $legacyRows = $legacy[$groupId] ?? null;

            if ($legacyRows === null) {
                $this->logMismatch('standings', $drawId, 'group_missing_in_legacy', "Group {$groupId} absent from legacy standings", null);
                continue;
            }

            $canonOrder  = array_column($canonRows,  'reg_id');
            $legacyOrder = is_array($legacyRows)
                ? array_column($legacyRows, 'reg_id')
                : [];

            if ($canonOrder !== $legacyOrder) {
                $this->logMismatch('standings', $drawId, 'standings_order_mismatch', [
                    'group_id'     => $groupId,
                    'legacy_order' => $legacyOrder,
                ], [
                    'canonical_order' => $canonOrder,
                ]);
            }
        }
    }

    private function compareProgression(Fixture $fixture, int $winner, int $loser): void
    {
        // After canonical advance has already run, verify parent slot consistency
        if ($fixture->parent_fixture_id) {
            $parent = Fixture::find($fixture->parent_fixture_id);
            if ($parent && $parent->registration1_id !== $winner && $parent->registration2_id !== $winner) {
                $this->logMismatch('progression', $fixture->draw_id, 'winner_not_placed_in_parent', [
                    'fixture_id' => $fixture->id,
                    'winner'     => $winner,
                ], [
                    'parent_r1' => $parent->registration1_id,
                    'parent_r2' => $parent->registration2_id,
                ]);
            }
        }
    }

    private function compareBracketData(int $drawId, array $legacy, array $canonical): void
    {
        $legacyCount    = count($legacy['fixtures']   ?? $legacy);
        $canonicalCount = count($canonical['fixtures'] ?? $canonical);

        if ($legacyCount !== $canonicalCount) {
            $this->logMismatch('bracket_render', $drawId, 'fixture_count_mismatch', [
                'legacy_count' => $legacyCount,
            ], [
                'canonical_count' => $canonicalCount,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // PRIVATE: LOGGING
    // ------------------------------------------------------------------

    private function handleCanonicalError(string $operation, int $drawId, \Throwable $e): void
    {
        self::$fallbackCount++;

        $channel = $this->logChannel ? Log::channel($this->logChannel) : Log::getFacadeRoot();

        $channel->critical('[EngineRouter] Canonical engine threw — falling back to legacy', [
            'operation' => $operation,
            'draw_id'   => $drawId,
            'error'     => $e->getMessage(),
            'trace'     => $e->getTraceAsString(),
        ]);

        // Persist fallback event
        $this->persistLog($operation, $drawId, 'canonical_threw', null, [
            'error' => $e->getMessage(),
        ], true);
    }

    private function logMismatch(string $operation, int $drawId, string $type, mixed $legacy, mixed $canonical): void
    {
        // Snapshots are informational only — not counted as mismatches
        if (str_ends_with($type, '_snapshot')) {
            return;
        }

        self::$mismatchCount++;

        $entry = [
            'operation' => $operation,
            'draw_id'   => $drawId,
            'type'      => $type,
            'legacy'    => $legacy,
            'canonical' => $canonical,
            'ts'        => now()->toIso8601String(),
        ];

        self::$mismatchLog[] = $entry;

        $channel = $this->logChannel ? Log::channel($this->logChannel) : Log::getFacadeRoot();
        $channel->{$this->logLevel}('[EngineRouter] Mismatch detected', $entry);

        // Persist mismatch
        $this->persistLog($operation, $drawId, $type, $legacy, $canonical, false);
    }

    private function persistLog(string $operation, int $drawId, string $type, mixed $legacy, mixed $canonical, bool $wasFallback): void
    {
        $legacyArr    = $legacy    !== null ? (is_array($legacy)    ? $legacy    : ['value' => $legacy])    : null;
        $canonicalArr = $canonical !== null ? (is_array($canonical) ? $canonical : ['value' => $canonical]) : null;

        try {
            EngineComparisonLog::create([
                'operation'        => $operation,
                'draw_id'          => $drawId,
                'mismatch_type'    => $type,
                'engine_mode'      => $this->mode,
                'legacy_result'    => $legacyArr,
                'canonical_result' => $canonicalArr,
                'was_fallback'     => $wasFallback,
            ]);
        } catch (\Throwable $e) {
            Log::error('[EngineRouter] Failed to persist comparison log', ['error' => $e->getMessage()]);
        }

        if (! $wasFallback && ! str_ends_with($type, '_snapshot')) {
            try {
                EngineMismatch::create([
                    'draw_id'          => $drawId,
                    'operation_type'   => $operation,
                    'mismatch_type'    => $type,
                    'legacy_output'    => $legacyArr,
                    'canonical_output' => $canonicalArr,
                    'severity'         => EngineMismatch::resolveSeverity($operation, $type),
                    'resolved'         => false,
                    'created_at'       => now(),
                ]);
            } catch (\Throwable $e) {
                Log::error('[EngineRouter] Failed to persist engine mismatch', ['error' => $e->getMessage()]);
            }
        }
    }

    private function persistEngineRun(
        int    $drawId,
        string $operation,
        bool   $canonicalSuccess,
        ?bool  $legacySuccess,
        bool   $fallbackUsed,
        int    $mismatchCount,
        int    $durationMs,
        ?string $exception
    ): void {
        try {
            EngineRun::create([
                'draw_id'           => $drawId,
                'engine_mode'       => $this->mode,
                'operation_type'    => $operation,
                'legacy_success'    => $legacySuccess,
                'canonical_success' => $canonicalSuccess,
                'mismatch_detected' => $mismatchCount > 0,
                'fallback_used'     => $fallbackUsed,
                'mismatch_count'    => $mismatchCount,
                'duration_ms'       => $durationMs,
                'exception'         => $exception,
                'created_at'        => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('[EngineRouter] Failed to persist engine run', ['error' => $e->getMessage()]);
        }
    }
}
