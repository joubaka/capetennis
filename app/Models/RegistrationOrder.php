<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RegistrationOrder extends Model
{
  use HasFactory;

  protected $fillable = [
    'user_id',                // ✅ ADD THIS
    'wallet_reserved',
    'wallet_debited',
    'payfast_paid',
    'payfast_pf_payment_id',
    'payfast_amount_due',
    'pay_status',             // ✅ ADD THIS (important)
  ];

  protected $casts = [
    'wallet_reserved' => 'float',
    'payfast_amount_due' => 'float',
    'wallet_debited' => 'boolean',
    'payfast_paid' => 'boolean',
    'pay_status' => 'boolean',   // ✅ ADD
  ];

  /*
  |--------------------------------------------------------------------------
  | Relationships
  |--------------------------------------------------------------------------
  */

  public function items()
  {
    return $this->hasMany(
      RegistrationOrderItems::class,
      'order_id',
      'id'
    );
  }

  public function user()  // ✅ REQUIRED FOR ITN
  {
    return $this->belongsTo(User::class);
  }

  public function isFullyPaid(): bool
  {
    return (bool) $this->pay_status;
  }

  public function markPayfastPaid(string $pfId): void
  {
    $this->update([
      'payfast_paid' => true,
      'payfast_pf_payment_id' => $pfId,
    ]);
  }

}
