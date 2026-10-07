<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchResultNotification extends Model
{
    protected $guarded = [];
    protected $casts = ['snapshot' => 'array', 'claimed_at' => 'datetime', 'sent_at' => 'datetime', 'invalidated_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(function (self $notification): void {
            foreach (['event_id', 'fixture_type', 'fixture_id', 'revision', 'snapshot_hash', 'snapshot', 'recipient'] as $field) {
                if ($notification->isDirty($field)) throw new \LogicException('Result notification snapshots are immutable.');
            }
        });
    }
}
