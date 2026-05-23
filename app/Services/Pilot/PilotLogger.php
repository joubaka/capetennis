<?php

namespace App\Services\Pilot;

use App\Models\PilotEvent;
use App\Services\PlatformAuditLogger;
use Illuminate\Support\Facades\Log;

/**
 * PilotLogger
 *
 * Thin wrapper around PlatformAuditLogger that also updates the
 * PilotEvent counters for the active pilot scenario.
 *
 * Usage:
 *   PilotLogger::forPilot($pilotEvent)->mismatch($draw, 'rr_generation');
 *   PilotLogger::forPilot($pilotEvent)->fallback($draw, 'standings');
 *   PilotLogger::forPilot($pilotEvent)->rollback($draw, $fixture);
 *   PilotLogger::forPilot($pilotEvent)->scoreDelete($draw, $fixture);
 *   PilotLogger::forPilot($pilotEvent)->canonicalException($draw, $e);
 */
class PilotLogger
{
    public function __construct(private readonly PilotEvent $pilot) {}

    public static function forPilot(PilotEvent $pilot): self
    {
        return new self($pilot);
    }

    // ------------------------------------------------------------------

    public function mismatch(\App\Models\Draw $draw, string $operation, array $meta = []): void
    {
        $this->pilot->incrementMismatch();
        PlatformAuditLogger::log(
            PlatformAuditLogger::ENGINE_FALLBACK,
            $draw,
            meta: array_merge(['pilot_event_id' => $this->pilot->id, 'operation' => $operation, 'type' => 'mismatch'], $meta),
        );
        Log::channel('daily')->info('[PILOT] mismatch', [
            'pilot_id' => $this->pilot->id, 'draw_id' => $draw->id, 'op' => $operation,
        ]);
    }

    public function fallback(\App\Models\Draw $draw, string $operation, array $meta = []): void
    {
        $this->pilot->incrementFallback();
        PlatformAuditLogger::log(
            PlatformAuditLogger::ENGINE_FALLBACK,
            $draw,
            meta: array_merge(['pilot_event_id' => $this->pilot->id, 'operation' => $operation, 'type' => 'fallback'], $meta),
        );
        Log::channel('daily')->warning('[PILOT] fallback', [
            'pilot_id' => $this->pilot->id, 'draw_id' => $draw->id, 'op' => $operation,
        ]);
    }

    public function rollback(\App\Models\Draw $draw, \App\Models\Fixture $fixture, array $meta = []): void
    {
        $this->pilot->incrementRollback();
        PlatformAuditLogger::log(
            PlatformAuditLogger::PROGRESSION_RESET,
            $draw,
            meta: array_merge(['pilot_event_id' => $this->pilot->id, 'fixture_id' => $fixture->id, 'type' => 'rollback'], $meta),
        );
        Log::channel('daily')->info('[PILOT] rollback', [
            'pilot_id' => $this->pilot->id, 'draw_id' => $draw->id, 'fixture_id' => $fixture->id,
        ]);
    }

    public function scoreDelete(\App\Models\Draw $draw, \App\Models\Fixture $fixture, array $meta = []): void
    {
        $this->pilot->incrementScoreDelete();
        PlatformAuditLogger::log(
            PlatformAuditLogger::SCORE_DELETED,
            $draw,
            meta: array_merge(['pilot_event_id' => $this->pilot->id, 'fixture_id' => $fixture->id, 'type' => 'score_delete'], $meta),
        );
        Log::channel('daily')->info('[PILOT] score_delete', [
            'pilot_id' => $this->pilot->id, 'draw_id' => $draw->id, 'fixture_id' => $fixture->id,
        ]);
    }

    public function canonicalException(\App\Models\Draw $draw, \Throwable $e, string $operation = 'unknown', array $meta = []): void
    {
        $this->pilot->incrementCanonicalException();
        PlatformAuditLogger::log(
            PlatformAuditLogger::ENGINE_FALLBACK,
            $draw,
            meta: array_merge([
                'pilot_event_id' => $this->pilot->id,
                'operation'      => $operation,
                'type'           => 'canonical_exception',
                'exception'      => $e->getMessage(),
            ], $meta),
        );
        Log::channel('daily')->error('[PILOT] canonical_exception', [
            'pilot_id' => $this->pilot->id, 'draw_id' => $draw->id, 'op' => $operation, 'err' => $e->getMessage(),
        ]);
    }

    public function complete(array $notes = []): void
    {
        $this->pilot->complete($notes);
        Log::channel('daily')->info('[PILOT] scenario_complete', [
            'pilot_id'    => $this->pilot->id,
            'scenario'    => $this->pilot->scenario,
            'mismatches'  => $this->pilot->mismatch_count,
            'fallbacks'   => $this->pilot->fallback_count,
            'rollbacks'   => $this->pilot->rollback_count,
            'score_dels'  => $this->pilot->score_delete_count,
            'canon_excs'  => $this->pilot->canonical_exception_count,
        ] + $notes);
    }
}
