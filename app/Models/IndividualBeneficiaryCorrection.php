<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Immutable evidence of an explicitly reviewed correction, never a new receipt. */
class IndividualBeneficiaryCorrection extends Model
{
    protected $guarded = [];
    protected $casts = ['payment_snapshot' => 'array', 'invitation_snapshot' => 'array'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Beneficiary correction evidence is immutable.'));
        static::deleting(fn () => throw new \LogicException('Beneficiary correction evidence is immutable.'));
    }
}
