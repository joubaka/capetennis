<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EventPayoutRequest extends Model
{
    protected $guarded = [];
    protected $casts = ['amount' => 'decimal:2', 'snapshot' => 'array', 'reviewed_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(function (self $request) {
            $allowed = ['status', 'reviewed_by', 'reviewed_at', 'review_note', 'updated_at'];
            if (array_diff(array_keys($request->getDirty()), $allowed)) throw new \LogicException('Original payout request details are immutable.');
        });
        static::deleting(fn () => throw new \LogicException('Payout requests cannot be deleted.'));
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
