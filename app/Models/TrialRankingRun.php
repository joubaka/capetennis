<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrialRankingRun extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['positions' => 'array'];
    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Trial ranking revisions are immutable.'));
        static::deleting(fn () => throw new \LogicException('Retain Trial ranking revision history.'));
    }
}
