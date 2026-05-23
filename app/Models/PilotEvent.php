<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PilotEvent
 *
 * Tracks metadata for internally-controlled pilot scenarios.
 * One row per pilot scenario, linked to the Event it exercises.
 */
class PilotEvent extends Model
{
    protected $fillable = [
        'event_id',
        'scenario',
        'engine_mode',
        'player_count',
        'draw_count',
        'mismatch_count',
        'fallback_count',
        'rollback_count',
        'score_delete_count',
        'canonical_exception_count',
        'notes',
        'status',
    ];

    protected $casts = [
        'notes' => 'array',
    ];

    // ------------------------------------------------------------------
    // Scenario constants
    // ------------------------------------------------------------------
    public const SCENARIO_RR          = 'rr';
    public const SCENARIO_PLAYOFF     = 'playoff';
    public const SCENARIO_CONSOLATION = 'consolation';
    public const SCENARIO_PAYMENT     = 'payment';

    // Status constants
    public const STATUS_ACTIVE   = 'active';
    public const STATUS_COMPLETE = 'complete';
    public const STATUS_FAILED   = 'failed';

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    // ------------------------------------------------------------------
    // Increment helpers (safe for concurrent use)
    // ------------------------------------------------------------------

    public function incrementMismatch(): void
    {
        $this->increment('mismatch_count');
    }

    public function incrementFallback(): void
    {
        $this->increment('fallback_count');
    }

    public function incrementRollback(): void
    {
        $this->increment('rollback_count');
    }

    public function incrementScoreDelete(): void
    {
        $this->increment('score_delete_count');
    }

    public function incrementCanonicalException(): void
    {
        $this->increment('canonical_exception_count');
    }

    public function complete(array $notes = []): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETE,
            'notes'  => array_merge($this->notes ?? [], $notes),
        ]);
    }

    public function fail(string $reason): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'notes'  => array_merge($this->notes ?? [], ['failure_reason' => $reason]),
        ]);
    }
}
