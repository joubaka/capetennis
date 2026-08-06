<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CategoryEventRegistration extends Model
{

  public const REFUND_PENDING = 'pending';
  public const REFUND_COMPLETED = 'completed';

  use HasFactory;
  use SoftDeletes;

  protected static function booted(): void
  {
    static::updating(function (self $reg) {
      // Lock withdrawn_at once set — only super-users may override
      if (
        $reg->getOriginal('withdrawn_at') !== null
        && $reg->isDirty('withdrawn_at')
      ) {
        $user = auth()->user();
        $isSuperUser = $user
          && method_exists($user, 'hasRole')
          && $user->hasRole('super-user');

        if (!$isSuperUser) {
          $reg->withdrawn_at = $reg->getOriginal('withdrawn_at');
        }
      }
    });
  }

  protected $fillable = [
    'category_event_id',
    'registration_id',
    'user_id',

    // Payment
    'pf_transaction_id',
    'payment_status_id',
    'payment_method',
    'wallet_transaction_id',

    // Withdrawal
    'status',
    'withdrawn_at',
    'withdrawn_by',
    'withdrawal_reason',

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
    'user_id'              => 'integer',
    'withdrawn_by'         => 'integer',
    'refund_account_number' => 'encrypted',
    'withdrawn_at'         => 'datetime',
    'refunded_at'          => 'datetime',
    'refund_gross'         => 'float',
    'refund_fee'           => 'float',
    'refund_net'           => 'float',
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

  public function walletTransaction()
  {
    return $this->belongsTo(\App\Models\WalletTransaction::class, 'wallet_transaction_id');
  }

  public function withdrawnByUser()
  {
    return $this->belongsTo(User::class, 'withdrawn_by');
  }

  // --------------------------------------------------
  // QUERY SCOPES
  // --------------------------------------------------

  /**
   * Active (non-withdrawn) entries only.
   * Use this for draws, rankings, entry counts, and finance summaries.
   */
  public function scopeActive($query)
  {
    return $query->whereNotIn('status', [
      'withdrawn',
      'withdrawn_pending_refund',
      'withdrawn_refunded',
    ]);
  }

  /**
   * Withdrawn entries only (all sub-states).
   */
  public function scopeWithdrawn($query)
  {
    return $query->whereIn('status', [
      'withdrawn',
      'withdrawn_pending_refund',
      'withdrawn_refunded',
    ]);
  }

  /**
   * Paid and active — use for draws, rankings, and finance entry counts.
   */
  public function scopeActiveAndPaid($query)
  {
    return $query->active()->where('payment_status_id', 1);
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
   * Resolves the PayFast transaction for payfast/hybrid payments.
   * Only valid when pf_transaction_id is a real PayFast payment ID
   * (i.e. payment_method is 'payfast' or 'hybrid').
   * Returns null for wallet-only payments.
   */
  public function payfastTransaction()
  {
    // Wallet-only payments have no PayFast record — avoid a useless join.
    if ($this->payment_method === 'wallet' || empty($this->pf_transaction_id)) {
      return $this->belongsTo(Transaction::class, 'pf_transaction_id', 'pf_payment_id')
        ->whereRaw('1 = 0'); // always-empty relation
    }

    return $this->belongsTo(
      Transaction::class,
      'pf_transaction_id',
      'pf_payment_id'
    );
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
   * Returns normalised payment info for this registration.
   *
   * Resolves correctly for all three payment paths:
   *   - 'wallet'  — no PayFast record; amounts come from the order + wallet_transaction
   *   - 'payfast' — standard PayFast ITN; amounts come from transactions_pf row
   *   - 'hybrid'  — both; PayFast gross + wallet contribution split per item
   *
   * Returns [] if the registration is not paid or has no resolvable payment record.
   */
  public function paymentInfo(): array
  {
    // Must be paid (payment_status_id = 1)
    if (!$this->is_paid) {
      return [];
    }

    $method = $this->payment_method; // wallet | payfast | hybrid | null (legacy)

    // ------------------------------------------------------------------
    // Resolve the order via the order item that holds this CER's
    // registration_id. We load lazily so callers that already eager-loaded
    // don't pay twice.
    // ------------------------------------------------------------------
    $orderItem = RegistrationOrderItems::where('registration_id', $this->registration_id)
      ->where('category_event_id', $this->category_event_id)
      ->first();

    $order = $orderItem?->order;

    $totalItems = max(1, $order?->items?->count() ?? 1);

    // ------------------------------------------------------------------
    // WALLET-ONLY
    // ------------------------------------------------------------------
    if ($method === 'wallet') {
      $walletTx       = $this->walletTransaction;
      $walletReserved = (float) ($order?->wallet_reserved ?? $walletTx?->amount ?? 0);
      $walletPerReg   = round($walletReserved / $totalItems, 2);

      return [
        'payment_method'  => 'wallet',
        'pf_payment_id'   => null,
        'transaction_id'  => null,
        // Per-registration amounts
        'gross'           => $walletPerReg,
        'fee'             => 0.00,
        'net'             => $walletPerReg,
        'wallet_paid'     => $walletPerReg,
        'total_paid'      => $walletPerReg,
        // Meta
        'paid_at'         => $walletTx?->created_at ?? $this->updated_at,
        'payer_email'     => null,
        'payer_name'      => null,
        'item_name'       => optional($this->categoryEvent?->event)->name,
        'items_in_order'  => $totalItems,
      ];
    }

    // ------------------------------------------------------------------
    // PAYFAST-ONLY or HYBRID
    // ------------------------------------------------------------------
    $tx = $this->payfastTransaction;

    // Graceful fallback: if there is no linked transactions_pf row yet
    // (e.g. admin-marked-paid rows or legacy rows where pf_transaction_id
    // was stored before the transactions_pf insert, or pf_payment_id column
    // truncation meant the insert failed). Derive amounts from the order so
    // that the refund flow always has a valid gross/fee/net to work with.
    if (!$tx) {
      if (($method === null || $method === 'payfast') && !empty($this->pf_transaction_id)) {
        // Resolve amounts from the order or CER item price as fallback.
        // This covers admin-marked-paid and legacy rows with no transactions_pf record.
        $orderItemPrice = RegistrationOrderItems::where('registration_id', $this->registration_id)
          ->where('category_event_id', $this->category_event_id)
          ->value('item_price');

        $grossTotal  = (float) ($orderItemPrice ?? $order?->payfast_amount_due ?? 0);
        $grossPerReg = $totalItems > 1 ? round($grossTotal / $totalItems, 2) : round($grossTotal, 2);
        $feeTotal    = SiteSetting::calculatePayfastFee($grossPerReg);
        $feePerReg   = round($feeTotal, 2);
        $netPerReg   = round($grossPerReg - $feePerReg, 2);

        return [
          'payment_method' => 'payfast',
          'pf_payment_id'  => $this->pf_transaction_id,
          'transaction_id' => null,
          'gross'          => $grossPerReg,
          'fee'            => $feePerReg,
          'net'            => $netPerReg,
          'wallet_paid'    => 0.00,
          'total_paid'     => $grossPerReg,
          'paid_at'        => $this->updated_at,
          'payer_email'    => null,
          'payer_name'     => null,
          'item_name'      => optional($this->categoryEvent?->event)->name,
          'items_in_order' => $totalItems,
          '_legacy'        => true,
        ];
      }

      return [];
    }

    // PayFast gross split per registration in the same order
    $grossPerReg = round((float) $tx->amount_gross / $totalItems, 2);
    $totalFee    = SiteSetting::calculatePayfastFee((float) $tx->amount_gross);
    $feePerReg   = round($totalFee / $totalItems, 2);
    $netPerReg   = round($grossPerReg - $feePerReg, 2);

    // Wallet portion (hybrid only)
    $walletReserved = (float) ($order?->wallet_reserved ?? 0);
    $walletPerReg   = round($walletReserved / $totalItems, 2);

    return [
      'payment_method'  => $method ?? 'payfast',
      'pf_payment_id'   => $tx->pf_payment_id,
      'transaction_id'  => $tx->id,
      // Per-registration amounts (PayFast portion)
      'gross'           => $grossPerReg,
      'fee'             => $feePerReg,
      'net'             => $netPerReg,
      // Wallet contribution
      'wallet_paid'     => $walletPerReg,
      // Combined
      'total_paid'      => round($grossPerReg + $walletPerReg, 2),
      // Meta
      'paid_at'         => $tx->created_at,
      'payer_email'     => $tx->email_address,
      'payer_name'      => trim($tx->name_first . ' ' . $tx->name_last),
      'item_name'       => $tx->item_name,
      'items_in_order'  => $totalItems,
    ];
  }

  // --------------------------------------------------
  // REFUND HELPERS
  // --------------------------------------------------

  public function isRefunded(): bool
  {
    return $this->refund_status === 'completed';
  }

  // --------------------------------------------------
  // WITHDRAWAL RULES
  // --------------------------------------------------

  // --------------------------------------------------
  // WITHDRAWAL — CANONICAL TRANSITION
  // --------------------------------------------------

  /**
   * Single authoritative method for marking a registration as withdrawn.
   *
   * All controllers must call this instead of inlining update() calls.
   * Payment columns (payment_status_id, pf_transaction_id, payment_method,
   * wallet_transaction_id) are intentionally preserved for audit/refund purposes.
   *
   * @param  \App\Models\User  $by             The user performing the withdrawal.
   * @param  string            $initiatedBy    'self' | 'admin'
   * @param  string|null       $reason         Optional admin note / reason.
   */
  public function markWithdrawn(
    User $by,
    string $initiatedBy = 'self',
    ?string $reason = null
  ): void {
    // HOTFIX 5: Idempotency check + state mutation inside a lockForUpdate transaction
    // so concurrent requests cannot both pass the withdrawn-state check before either commits.
    $alreadyWithdrawn = false;

    \Illuminate\Support\Facades\DB::transaction(function () use ($by, $reason, &$alreadyWithdrawn) {
      $locked = static::lockForUpdate()->findOrFail($this->id);

      if (in_array($locked->status, ['withdrawn', 'withdrawn_pending_refund', 'withdrawn_refunded'])) {
        $alreadyWithdrawn = true;
        return;
      }

      $locked->update([
        'status'            => 'withdrawn',
        'withdrawn_at'      => now(),
        'withdrawn_by'      => $by->id,
        'withdrawal_reason' => $reason,
        // Reset refund tracking to a clean slate
        'refund_status'     => 'not_refunded',
        'refund_method'     => null,
        'refund_gross'      => 0,
        'refund_fee'        => 0,
        'refund_net'        => 0,
        'refunded_at'       => null,
      ]);

      // Refresh $this so callers see the new state immediately
      $this->setRawAttributes($locked->fresh()->getAttributes());
    });

    if ($alreadyWithdrawn) {
      return;
    }

    try {
      activity('withdrawal')
        ->performedOn($this)
        ->causedBy($by)
        ->withProperties([
          'registration_id' => $this->id,
          'initiated_by'    => $initiatedBy,
          'reason'          => $reason,
          'event'           => optional($this->categoryEvent?->event)->name,
          'category'        => optional($this->categoryEvent?->category)->name,
          'player'          => ($p = $this->players->first())
            ? trim($p->name . ' ' . $p->surname)
            : null,
        ])
        ->log(ucfirst($initiatedBy) . ' withdrawal recorded');
    } catch (\Throwable $e) {
      \Illuminate\Support\Facades\Log::warning('markWithdrawn: activity log failed', [
        'cer_id' => $this->id,
        'error'  => $e->getMessage(),
      ]);
    }
  }

  public function canWithdraw(User $user): array
  {
    $isAdmin = method_exists($user, 'hasAnyRole')
      && $user->hasAnyRole(['super-user', 'admin']);

    // Ownership (admins bypass)
    if ($this->user_id !== $user->id && !$isAdmin) {
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
        'message' => 'This registration is already withdrawn.',
      ];
    }

    $event = $this->categoryEvent->event;

    // Draw has been finalised (category locked) — non-admins cannot withdraw
    // once the draw is published, as their slot may already be scheduled.
    if (!$isAdmin && $this->categoryEvent->isLocked()) {
      return [
        'ok' => false,
        'reason' => 'draw_locked',
        'refund_allowed' => false,
        'message' => 'Withdrawals are not allowed after the draw has been finalised. Please contact the event administrator.',
      ];
    }

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

  /**
   * Paid state is authoritative from payment_status_id = 1.
   * Never inferred from pf_transaction_id strings.
   */
  public function getIsPaidAttribute(): bool
  {
    return (int) $this->payment_status_id === 1;
  }

  // --------------------------------------------------
// REFUND STATUS HELPERS
// --------------------------------------------------

  public function isRefundPending(): bool
  {
    return $this->refund_status === 'pending';
  }

  public function isRefundCompleted(): bool
  {
    return $this->refund_status === 'completed';
  }

  public function hasRefund(): bool
  {
    return !in_array($this->refund_status, [null, '', 'not_refunded']);
  }

  public function isBankRefund(): bool
  {
    return $this->refund_method === 'bank';
  }

  public function isWalletRefund(): bool
  {
    return $this->refund_method === 'wallet';
  }

  /**
   * Maximum amount that may be refunded for this registration.
   * Derived from paymentInfo() gross + wallet_paid.
   * Never returns more than was actually paid.
   */
  public function maxRefundableAmount(): float
  {
    if ($this->isRefundCompleted()) {
      return 0.0;
    }

    $payment = $this->paymentInfo();
    if (empty($payment)) {
      return max(0, round((float) ($this->refund_gross ?? 0), 2));
    }

    $gross = round((float) ($payment['gross'] ?? 0) + (float) ($payment['wallet_paid'] ?? 0), 2);
    return max(0, $gross);
  }

  public function canRequestRefund(): bool
  {
    return $this->status === 'withdrawn'
      && $this->is_paid
      && in_array($this->refund_status, [null, '', 'not_refunded']);
  }

  /**
   * Send withdrawal notification emails to the player, all event admins, and all super-users.
   *
   * @param  string  $initiatedBy  'self' when the player withdrew, 'admin' when an admin withdrew
   */
  public function sendWithdrawalEmails(string $initiatedBy = 'self'): void
  {
    $this->loadMissing(['players', 'categoryEvent.event.admins', 'user']);

    // --- Player email (gated by player_email_on_withdrawal setting) ---
    $player       = $this->players->first();
    $playerEmail  = $player?->email ?? $this->user?->email ?? null;

    if ($playerEmail && SiteSetting::get('player_email_on_withdrawal', '1') === '1') {
      \Illuminate\Support\Facades\Mail::to($playerEmail)
        ->queue(new \App\Mail\WithdrawalPlayerMail($this, $initiatedBy));
    }

    // --- Admin email: refund details go to super-users only (not event admins) ---
    $superUserEmails = \App\Models\User::role('super-user')
      ->pluck('email')
      ->filter()
      ->map('strtolower')
      ->unique()
      ->values();

    foreach ($superUserEmails as $email) {
      \Illuminate\Support\Facades\Mail::to($email)
        ->queue(new \App\Mail\WithdrawalAdminMail($this, $initiatedBy));
    }
  }

}
