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
    'collection_status',
    'paid_privately_at',
    'paid_privately_by',
    'payfast_pf_payment_id',
    'payfast_raw_data',
    'payfast_handed_off_at',
    // Refund fields
    'refund_method',
    'refund_status',
    'refund_gross',
    'refund_fee',
    'refund_net',
    'withdrawn_at',
    'withdrawn_by',
    'refunded_at',
    'refund_waived_at',
    'refund_waived_by',
    'refund_waiver_reason',
    'refund_account_name',
    'refund_bank_name',
    'refund_account_number',
    'refund_branch_code',
    'refund_account_type',
  ];

  protected $casts = [
    'beneficiary_player_id' => 'integer',
    'wallet_reserved' => 'float',
    'payfast_amount_due' => 'float',
    'total_amount' => 'float',
    'wallet_debited' => 'boolean',
    'payfast_paid' => 'boolean',
    'pay_status' => 'boolean',
    'paid_privately_at' => 'datetime',
    'paid_privately_by' => 'integer',
    'payfast_raw_data' => 'array',
    'payfast_handed_off_at' => 'datetime',
    // Refund casts
    'refund_gross' => 'float',
    'refund_fee' => 'float',
    'refund_net' => 'float',
    'withdrawn_at' => 'datetime',
    'withdrawn_by' => 'integer',
    'refunded_at' => 'datetime',
    'refund_waived_at' => 'datetime',
    'refund_waived_by' => 'integer',
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

  public function beneficiary()
  {
    return $this->belongsTo(Player::class, 'beneficiary_player_id');
  }

  public function effectivePlayer()
  {
    return $this->belongsTo(Player::class, 'effective_player_id');
  }

  public function getEffectivePlayerIdAttribute(): int
  {
    return (int) ($this->beneficiary_player_id ?? $this->player_id);
  }

  public function scopeForBeneficiary($query, int $playerId)
  {
    return $query->whereRaw('COALESCE(beneficiary_player_id, player_id) = ?', [$playerId]);
  }

  public function scopeForPlayerHistory($query, array $playerIds)
  {
    return $query->where(fn ($q) => $q->whereIn('player_id', $playerIds)->orWhereIn('beneficiary_player_id', $playerIds));
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

  public function isRefundWaived(): bool
  {
    return $this->refund_status === 'waived';
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
    if ($this->isRefundCompleted() || $this->isRefundWaived()) {
      return 0.0;
    }

    $paid = (float) $this->total_amount;
    return max(0, round($paid, 2));
  }
}

