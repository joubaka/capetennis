<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class RegionalClothingSettlement extends Model
{
    public const DATE_ONLY_EVIDENCE = 'Actual date only; time not recorded. ';
    public const DATE_TIME_EVIDENCE = 'Actual date and time recorded. ';

    protected $guarded = ['id'];

    protected $casts = ['amount' => 'decimal:2', 'funding_shortfall' => 'decimal:2', 'occurred_at' => 'immutable_datetime', 'offset_agreement_confirmed' => 'boolean'];

    public function hasDateOnlyEvidence(): bool
    {
        return str_starts_with($this->evidence, self::DATE_ONLY_EVIDENCE);
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw ValidationException::withMessages(['settlement' => 'Clothing settlement records are immutable.']));
        static::deleting(fn () => throw ValidationException::withMessages(['settlement' => 'Clothing settlement records cannot be deleted.']));
    }
}
