<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class PlayerSuspension extends Model
{
    protected $fillable = [
        'player_id',
        'triggered_at',
        'suspension_number',
        'duration_months',
        'starts_at',
        'ends_at',
        'notes',
        'lifted_by',
        'lifted_at',
    ];

    protected $casts = [
        'triggered_at' => 'date',
        'starts_at'    => 'date',
        'ends_at'      => 'date',
        'lifted_at'    => 'datetime',
    ];

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function liftedBy()
    {
        return $this->belongsTo(User::class, 'lifted_by');
    }

    /**
     * Scope to currently active suspensions (not lifted, not expired).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('lifted_at')
            ->where('ends_at', '>', Carbon::today()->toDateString());
    }

    public function getIsActiveAttribute(): bool
    {
        return is_null($this->lifted_at)
            && $this->ends_at->gt(Carbon::today());
    }
}
