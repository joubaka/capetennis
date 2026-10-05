<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
/** Append-only audit snapshots; later attempts cannot rewrite earlier outcomes. */
class EventMailAttemptHistory extends Model
{
    protected $table = 'event_mail_attempt_history';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['snapshot' => 'array', 'recorded_at' => 'datetime'];
    protected static function booted(): void
    {
        static::updating(fn() => throw new \LogicException('Mail history is immutable.'));
        static::deleting(fn() => throw new \LogicException('Mail history is immutable.'));
    }
}
