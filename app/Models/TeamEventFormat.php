<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeamEventFormat extends Model
{
    use HasFactory;

    protected $table = 'team_event_formats';

    protected $fillable = [
        'event_id',
        'name',
        'min_roster_size',
        'max_roster_size',
        'allow_player_reuse',
        'is_default',
    ];

    protected $casts = [
        'allow_player_reuse' => 'boolean',
        'is_default'         => 'boolean',
        'min_roster_size'    => 'integer',
        'max_roster_size'    => 'integer',
    ];

    // ─── Relations ──────────────────────────────────────────────────────────

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function rubbers()
    {
        return $this->hasMany(TeamEventFormatRubber::class, 'format_id')->orderBy('sequence');
    }

    public function draws()
    {
        return $this->hasMany(Draw::class, 'team_event_format_id');
    }

    // ─── Scopes ─────────────────────────────────────────────────────────────

    public function scopeGlobal($query)
    {
        return $query->whereNull('event_id');
    }

    public function scopeForEvent($query, int $eventId)
    {
        return $query->where(function ($q) use ($eventId) {
            $q->where('event_id', $eventId)->orWhereNull('event_id');
        });
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }
}
