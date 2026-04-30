<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @deprecated This model targets the legacy withdrawal log table which pre-dates
 *             the CategoryEventRegistration refund system.  All new withdrawal /
 *             refund logic should use CategoryEventRegistration directly.
 *             The underlying table has been renamed from `withdrawels` (misspelled)
 *             to `withdrawals` via migration 2026_04_30_210001_rename_withdrawels_to_withdrawals.
 */
class Withdrawals extends Model
{
    use HasFactory;

    protected $table = 'withdrawals';

    public function registration()
    {
        return $this->belongsTo(Registration::class, 'registration_id', 'id');
    }
}
