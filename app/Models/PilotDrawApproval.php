<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PilotDrawApproval
 *
 * Records which draws have been approved for the canonical RR pilot.
 * Only draws with an active approval record may have engine_mode = 'canonical'.
 *
 * Statuses: approved | revoked
 */
class PilotDrawApproval extends Model
{
    protected $table = 'pilot_draw_approvals';

    protected $fillable = [
        'draw_id',
        'event_id',
        'approved_by_email',
        'approved_by',
        'status',
        'notes',
        'player_count',
        'is_rr',
        'has_consolation',
        'has_feed_in',
        'is_national',
        'engine_mode_before',
        'approved_at',
        'revoked_at',
    ];

    protected $casts = [
        'is_rr'           => 'boolean',
        'has_consolation' => 'boolean',
        'has_feed_in'     => 'boolean',
        'is_national'     => 'boolean',
        'approved_at'     => 'datetime',
        'revoked_at'      => 'datetime',
    ];

    public const STATUS_APPROVED = 'approved';
    public const STATUS_REVOKED  = 'revoked';

    public function draw()
    {
        return $this->belongsTo(Draw::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeApproved($q)
    {
        return $q->where('status', self::STATUS_APPROVED);
    }

    public function scopeForDraw($q, int $drawId)
    {
        return $q->where('draw_id', $drawId);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    public static function isApproved(int $drawId): bool
    {
        return static::where('draw_id', $drawId)
            ->where('status', self::STATUS_APPROVED)
            ->exists();
    }
}
