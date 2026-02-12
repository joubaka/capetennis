<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CategoryEventRegistration extends Model
{

  public const REFUND_PENDING = 'pending';
  public const REFUND_COMPLETED = 'completed';

  use HasFactory;

  protected $fillable = [
    'category_event_id',
    'registration_id',
    'user_id',

    // Payment
    'pf_transaction_id',
    'payment_status_id',

    // Withdrawal
    'status',
    'withdrawn_at',

    // Refund core
    'refund_method',
    'refund_status',
    'refund_gross',
    'refund_fee',
    'refund_net',
    'refunded_at',

    // Bank refund details
    'refund_account_name',
    'refund_bank_name',
    'refund_account_number',
    'refund_branch_code',
    'refund_account_type',
  ];


  protected $appends = ['display_name', 'is_paid'];
  protected $casts = [
    'user_id' => 'integer',
    'refund_account_number' => 'encrypted',
    'withdrawn_at' => 'datetime',
    'refunded_at' => 'datetime',
    'refund_gross' => 'float',
    'refund_fee' => 'float',
    'refund_net' => 'float',
  ];



  // --------------------------------------------------
  // RELATIONSHIPS
  // --------------------------------------------------

  public function registration()
  {
    return $this->belongsTo(Registration::class);
  }

  public function categoryEvent()
  {
    return $this->belongsTo(CategoryEvent::class);
  }

  public function user()
  {
    return $this->belongsTo(User::class);
  }

  /**
   * Players via registration
   */
  public function players()
  {
    return $this->belongsToMany(
      Player::class,
      'player_registrations',
      'registration_id',
      'player_id',
      'registration_id',
      'id'
    );
  }

  /**
   * PayFast transaction (single source of truth)
   * Linked via pf_transaction_id → transactions.pf_payment_id
   */
  public function payfastTransaction()
  {
    return $this->belongsTo(
      Transaction::class,
      'pf_transaction_id', // local key on this model
      'pf_payment_id'      // column on transactions table
    )->where('transaction_type', 'Registration');
  }

  // --------------------------------------------------
  // ACCESSORS
  // --------------------------------------------------

  public function getDisplayNameAttribute()
  {
    $count = $this->players->count();

    if ($count === 1) {
      return $this->players->first()->full_name;
    }

    if ($count === 2) {
      return $this->players[0]->full_name . ' / ' . $this->players[1]->full_name;
    }

    return 'TBD';
  }

  // --------------------------------------------------
  // PAYMENT STATE (SINGLE SOURCE OF TRUTH)
  // --------------------------------------------------

  /**
   * Returns PayFast payment info for this registration.
   * Always derived from the linked Transaction model.
   */
  public function paymentInfo(): array
  {
    $tx = $this->payfastTransaction;

    if (!$tx || !$tx->order) {
      return [];
    }

    // 🔹 How many registrations were paid in this transaction
    $totalItems = max(
      1,
      $tx->order->items->count()
    );

    // 🔹 Per-registration allocation
    $grossPerReg = (float) $tx->amount_gross / $totalItems;
    $feePerReg = (float) $tx->amount_fee / $totalItems;
    $netPerReg = $grossPerReg - $feePerReg;

    return [
      'transaction_id' => $tx->id,
      'pf_payment_id' => $tx->pf_payment_id,

      // ✅ PER REGISTRATION
      'gross' => round($grossPerReg, 2),
      'fee' => round($feePerReg, 2),
      'net' => round($netPerReg, 2),

      // meta
      'paid_at' => $tx->created_at,
      'payer_email' => $tx->email_address,
      'payer_name' => trim($tx->name_first . ' ' . $tx->name_last),
      'item_name' => $tx->item_name,

      // debug / trace
      'items_in_tx' => $totalItems,
    ];
  }

  // --------------------------------------------------
  // REFUND HELPERS
  // --------------------------------------------------

  public function isRefunded(): bool
  {
   
    return $this->status === 'completed';
  }

  // --------------------------------------------------
  // WITHDRAWAL RULES
  // --------------------------------------------------

  public function canWithdraw(User $user): array
  {
    // Ownership
    if ($this->user_id !== $user->id) {
     //   if ((int) $this->user_id !== (int) $user->id) {
      return [
        'ok' => false,
        'reason' => 'not_owner',
        'refund_allowed' => false,
        'message' => 'You do not own this registration.',
      ];
    }

    // Already withdrawn
    if (
      in_array($this->status, [
        'withdrawn',
        'withdrawn_pending_refund',
        'withdrawn_refunded',
      ])
    ) {
      return [
        'ok' => false,
        'reason' => 'already_withdrawn',
        'refund_allowed' => false,
        'message' => 'This registration has already been withdrawn.',
      ];
    }

    $event = $this->categoryEvent->event;

    // 🔴 Deadline passed → withdraw OK, refund NOT OK
    if (now()->gt($event->withdrawalCloseAt())) {
      return [
        'ok' => true,
        'reason' => 'late_withdraw',
        'refund_allowed' => false,
        'message' => 'Withdrawn after deadline (no refund).',
      ];
    }

    // Normal withdraw + refund
    return [
      'ok' => true,
      'reason' => 'allowed',
      'refund_allowed' => true,
      'message' => 'Withdrawal allowed.',
    ];
  }
  // --------------------------------------------------
// ACCESSORS
// --------------------------------------------------

  public function getIsPaidAttribute(): bool
  {
    return !empty($this->pf_transaction_id)
      || $this->payfastTransaction !== null;
  }

  // --------------------------------------------------
// REFUND STATUS HELPERS
// --------------------------------------------------

  public function isRefundPending(): bool
  {
    return $this->status === 'pending';
  }

  public function isRefundCompleted(): bool
  {
   
    return $this->status === 'completed';
  }

  public function hasRefund(): bool
  {
    return !empty($this->status);
  }

  public function isBankRefund(): bool
  {
    return $this->refund_method === 'bank';
  }

  public function isWalletRefund(): bool
  {
    return $this->refund_method === 'wallet';
  }

  public function canRequestRefund(): bool
  {
    return $this->status === 'withdrawn'
      && $this->is_paid
      && empty($this->refund_status);
  }

}
