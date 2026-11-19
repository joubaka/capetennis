<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndividualProviderRefundAttempt extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['amount' => 'decimal:2', 'finished_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(function (self $attempt) {
            if ($attempt->getOriginal('status') !== 'dispatching' || ! in_array($attempt->status, ['confirmed', 'uncertain'], true)
                || array_diff(array_keys($attempt->getDirty()), ['status', 'finished_at', 'updated_at']) !== []) {
                throw new \LogicException('Provider refund attempt identity and terminal outcome are immutable.');
            }
        });
        static::deleting(fn () => throw new \LogicException('Provider refund attempts must be retained.'));
    }
}
