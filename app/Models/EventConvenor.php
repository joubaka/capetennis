<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class EventConvenor extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'user_id',
        'role',
        'starts_at',
        'expires_at',
    ];

    protected $casts = [
        'starts_at'  => 'datetime',
        'expires_at' => 'datetime',
    ];

    /* ------------------------------------------------------------------ */
    /*  RELATIONSHIPS                                                     */
    /* ------------------------------------------------------------------ */

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class, 'event_id', 'id');
    }

    // Legacy alias
    public function events()
    {
        return $this->event();
    }

    /**
     * Expenses paid by this convenor.
     */
    public function expenses()
    {
        return $this->hasMany(EventExpense::class, 'paid_by_convenor_id');
    }

    /* ------------------------------------------------------------------ */
    /*  SCOPES                                                            */
    /* ------------------------------------------------------------------ */

    /**
     * Only convenors whose window is currently active.
     */
    public function scopeActive($query)
    {
        $now = Carbon::now();

        return $query->where(function ($q) use ($now) {
            $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
        })->where(function ($q) use ($now) {
            $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
        });
    }

    /* ------------------------------------------------------------------ */
    /*  HELPERS                                                           */
    /* ------------------------------------------------------------------ */

    public function isHoof(): bool
    {
        return $this->role === 'hoof';
    }

    public function isHulp(): bool
    {
        return $this->role === 'hulp';
    }

    /**
     * Is this convenor assignment currently active?
     */
    public function isActive(): bool
    {
        $now = Carbon::now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($this->expires_at && $now->gt($this->expires_at)) {
            return false;
        }

        return true;
    }

    /**
     * Human-readable time remaining, e.g. "2d 4h 15m".
     * Returns null when no expiry is set or already expired.
     */
    public function timeRemaining(): ?string
    {
        if (!$this->expires_at) {
            return null;
        }

        $now = Carbon::now();

        if ($now->gte($this->expires_at)) {
            return 'Expired';
        }

        $diff = $now->diff($this->expires_at);

        $parts = [];
        if ($diff->days > 0) {
            $parts[] = $diff->days . 'd';
        }
        if ($diff->h > 0) {
            $parts[] = $diff->h . 'h';
        }
        if ($diff->i > 0) {
            $parts[] = $diff->i . 'm';
        }

        return implode(' ', $parts) ?: '< 1m';
    }
}
