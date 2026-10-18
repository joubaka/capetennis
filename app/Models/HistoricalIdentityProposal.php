<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Append-only review evidence; no approval/application state or source mutation. */
final class HistoricalIdentityProposal extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Create a new proposal revision instead.'));
        static::deleting(fn () => throw new \LogicException('Historical review evidence is append-only.'));
    }
}
