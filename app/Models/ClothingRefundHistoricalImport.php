<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class ClothingRefundHistoricalImport extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['source_snapshot' => 'array', 'imported_at' => 'immutable_datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw ValidationException::withMessages(['import' => 'Historical refund import provenance is immutable.']));
        static::deleting(fn () => throw ValidationException::withMessages(['import' => 'Historical refund import provenance cannot be deleted.']));
    }
}
