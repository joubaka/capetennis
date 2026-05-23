<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PilotFeedback
 *
 * Stores convenor/user feedback raised during the limited public RR pilot.
 * Categories: scoring | standings | scheduling | rendering | other
 * Statuses:   open | investigating | resolved | wont_fix
 */
class PilotFeedback extends Model
{
    protected $table = 'pilot_feedback';

    protected $fillable = [
        'draw_id',
        'event_id',
        'engine_mode',
        'category',
        'description',
        'reproduction_steps',
        'reporter_email',
        'status',
        'attachments',
        'resolved_by',
        'resolved_at',
    ];

    protected $casts = [
        'reproduction_steps' => 'array',
        'attachments'        => 'array',
        'resolved_at'        => 'datetime',
    ];

    // Categories
    public const CAT_SCORING     = 'scoring';
    public const CAT_STANDINGS   = 'standings';
    public const CAT_SCHEDULING  = 'scheduling';
    public const CAT_RENDERING   = 'rendering';
    public const CAT_OTHER       = 'other';

    // Statuses
    public const STATUS_OPEN          = 'open';
    public const STATUS_INVESTIGATING = 'investigating';
    public const STATUS_RESOLVED      = 'resolved';
    public const STATUS_WONT_FIX      = 'wont_fix';

    public function draw()
    {
        return $this->belongsTo(Draw::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function resolver()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // ------------------------------------------------------------------
    // Scopes
    // ------------------------------------------------------------------

    public function scopeOpen($q)
    {
        return $q->where('status', self::STATUS_OPEN);
    }

    public function scopeForDraw($q, int $drawId)
    {
        return $q->where('draw_id', $drawId);
    }

    public function scopeForEvent($q, int $eventId)
    {
        return $q->where('event_id', $eventId);
    }
}
