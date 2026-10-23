<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Later documentary recovery, distinct from an original participant snapshot. */
final class HistoricalIdentityRecovery extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['documents' => 'array'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Recovered evidence is immutable.'));
        static::deleting(fn () => throw new \LogicException('Recovered evidence is append-only.'));
    }
}
