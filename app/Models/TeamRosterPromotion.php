<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class TeamRosterPromotion extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Roster promotion history is immutable.'));
        static::deleting(fn () => throw new \LogicException('Roster promotion history is retained.'));
    }
}
