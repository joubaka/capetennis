<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamPaymentOrder extends Model
{
  protected $fillable = [
    'user_id',
    'team_id',
    'player_id',
    'event_id',
    'total_amount',
    'wallet_reserved',
    'payfast_amount_due',
    'wallet_debited',
    'payfast_paid',
    'pay_status',
    'payfast_pf_payment_id',
    'payfast_raw_data',
    // Refund fields
    'refund_method',
    'refund_status',
    'refund_gross',
    'refund_fee',
    'refund_net',
    'refunded_at',
    'refund_account_name',
    'refund_bank_name',
    'refund_account_number',
    'refund_branch_code',
    'refund_account_type',
  ];

  protected $casts = [
    'wallet_reserved' => 'float',
    'payfast_amount_due' => 'float',
    'total_amount' => 'float',
    'wallet_debited' => 'boolean',
    'payfast_paid' => 'boolean',
    'pay_status' => 'boolean',
    'payfast_raw_data' => 'array',
    // Refund casts
    'refund_gross' => 'float',
    'refund_fee' => 'float',
    'refund_net' => 'float',
    'refunded_at' => 'datetime',
    'refund_account_number' => 'encrypted',
  ];

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  public function team()
  {
    return $this->belongsTo(Team::class);
  }

  public function player()
  {
    return $this->belongsTo(Player::class);
  }

  public function event()
  {
    return $this->belongsTo(Event::class);
  }

  // ------------------------------------------------------------------
  // Refund status helpers (mirror CategoryEventRegistration interface)
  // ------------------------------------------------------------------

  public function isRefundCompleted(): bool
  {
    return $this->refund_status === 'completed';
  }

  public function isRefundPending(): bool
  {
    return $this->refund_status === 'pending';
  }

  public function hasRefund(): bool
  {
    return !in_array($this->refund_status, [null, '', 'not_refunded']);
  }

  /**
   * Maximum amount that may be refunded for this order.
   * Returns the lesser of what was actually paid and the stored gross.
   * Always >= 0.
   */
  public function maxRefundableAmount(): float
  {
    if ($this->isRefundCompleted()) {
      return 0.0;
    }

    $paid = (float) $this->total_amount;
    return max(0, round($paid, 2));
  }
}

