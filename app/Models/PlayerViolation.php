<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;

class PlayerViolation extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'player_id',
        'violation_type_id',
        'violation_date',
        'penalty_type',
        'points_assigned',
        'notes',
        'recorded_by',
        'event_id',
    ];

    protected $casts = [
        'violation_date' => 'date',
        'points_assigned' => 'integer',
    ];

    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function violationType()
    {
        return $this->belongsTo(ViolationType::class);
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Scope to only non-expired violations (within the rolling expiry window).
     */
    public function scopeActive(Builder $query): Builder
    {
        $days = DisciplineSetting::expiryDays();
        return $query->where('violation_date', '>=', Carbon::now()->subDays($days)->toDateString());
    }

    /**
     * Whether this violation has expired (outside the rolling window).
     */
    public function getIsExpiredAttribute(): bool
    {
        $days = DisciplineSetting::expiryDays();
        return $this->violation_date->lt(Carbon::now()->subDays($days));
    }

    /**
     * Date this violation will expire.
     */
    public function getExpiresAtAttribute(): Carbon
    {
        $days = DisciplineSetting::expiryDays();
        return $this->violation_date->copy()->addDays($days);
    }
}
