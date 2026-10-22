<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EventRegionFinancialRecipientLink extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['confirmed_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw ValidationException::withMessages(['recipient_link' => 'Confirmed financial recipient links are immutable.']));
        static::deleting(fn () => throw ValidationException::withMessages(['recipient_link' => 'Confirmed financial recipient links cannot be deleted.']));
    }
}
