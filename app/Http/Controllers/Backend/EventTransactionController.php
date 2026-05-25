<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventPayout;
use App\Models\SiteSetting;
use App\Models\Transaction;
use App\Models\CategoryEventRegistration;
use App\Models\WalletTransaction;
use App\Models\RegistrationOrder;
use Illuminate\Support\Facades\Log;

class EventTransactionController extends Controller
{
  /**
   * STEP-BY-STEP DEBUG VERSION (dd checkpoints)
   *
   * Usage:
   * 1) Temporarily swap your route to point to this method (or rename index->indexDebug).
   * 2) Toggle $STEP to move through each dd().
   */
  public function index(Event $event)
  {
    $user = auth()->user();
    if (!$user || !$user->hasAnyRole(['super-user', 'admin', 'convenor'])) {
      abort(403, 'Unauthorized.');
    }

    // ---------------------------------------------------------
    // CHANGE THIS TO STEP THROUGH
    // ---------------------------------------------------------
    $STEP = 8;     // <- increment 1..8
    $DEBUG = true; // <- log extra details too

    // =========================
    // STEP 1: CONFIG
    // =========================
    $feePerEntry = (float) $event->cape_tennis_fee;
    $isTeamEvent = $event->isTeam();

    if ($STEP === 1) {
      dump([
        'step' => 1,
        'event_id' => $event->id,
        'feePerEntry' => $feePerEntry,
        'isTeamEvent' => $isTeamEvent,
      ]);
    }

    // =========================
    // STEP 2: LOAD PAYMENTS (TX)
    // =========================
    $transactions = Transaction::with([
      'user',
      'player',
      'order.items.player',
      'order.items.category_event.category',
    ])
      ->where('event_id', $event->id)
      ->where('transaction_type', 'Registration')
      ->where('amount_gross', '>=', 0)
      ->where('is_test', false)
      ->orderByDesc('created_at')
      ->get();

    if ($STEP === 2) {
      dd([
        'step' => 2,
        'tx_count' => $transactions->count(),
        'sample' => $transactions->take(5)->map(function ($t) use ($feePerEntry) {

          $entries = $t->order?->items?->count() ?? 1;
          $capeFeeTotal = $entries * $feePerEntry;

          return [
            'id' => $t->id,
            'pf_payment_id' => $t->pf_payment_id,

            // PayFast truth
            'gross' => (float) $t->amount_gross,
            'fee' => (float) $t->amount_fee,
            'net' => (float) $t->amount_net,

            // Cape Tennis (derived)
            'entries' => $entries,
            'cape_fee_per_entry' => $feePerEntry,
            'cape_fee_total' => $capeFeeTotal,

            'created_at' => (string) $t->created_at,
            'user' => optional($t->user)->name,
          ];
        })->values(),
      ]);
    }


    // =========================
    // STEP 3: MAP PAYMENTS → ROWS
    // =========================
    // =========================
// MAP PAYMENTS → LEDGER ROWS (PER ENTRY)
// =========================
    // =========================
// MAP PAYMENTS → LEDGER ROWS (PER ENTRY)
// =========================
    // =========================
// MAP PAYMENTS → LEDGER ROWS (PER TRANSACTION + CHILD ITEMS)
// =========================
    $paymentRows = $transactions->map(function ($tx) use ($feePerEntry, $isTeamEvent) {

      $items = collect(optional($tx->order)->items ?? []);

      // How many entries does this transaction represent?
      // For individual events this is usually items count.
      // For team events: if your order items still represent each entry, keep this.
      // If team events are 1 item but multiple players, you'll need a different count source.
      $entryCount = max(1, $items->count());

      // PayFast totals (transaction-level)
      $payfastGross = round((float) $tx->amount_gross, 2);

      // Wallet credit applied to this order (if any)
      $walletUsed = round((float) optional($tx->order)->wallet_reserved, 2);

      // Full gross = what PayFast charged + what wallet covered
      $grossTx = $payfastGross + $walletUsed;

      // PayFast fee recalculated using current custom rates (applies to PayFast portion only, not wallet)
      $pfFeeTx = $tx->pf_payment_id === null ? 0 : (-1 * SiteSetting::calculatePayfastFee($payfastGross));

      // ✅ Cape Tennis fee is PER PLAYER (per entry), so multiply by entry count
      $capeFeeTx = -1 * round($feePerEntry * $entryCount, 2);

      // ✅ Net to event for this transaction
      $netTx = round($grossTx + $pfFeeTx + $capeFeeTx, 2);

      // Method label
      if ($tx->pf_payment_id === null && $walletUsed == 0) {
        $method = 'Admin Entry';
      } elseif ($walletUsed > 0) {
        $method = 'PayFast + Wallet';
      } else {
        $method = 'PayFast';
      }

      // For admin entries use the player name; for PayFast use the account holder
      $playerName = $tx->pf_payment_id === null
        ? trim(optional($tx->player)->name . ' ' . optional($tx->player)->surname)
        : optional($tx->user)->name;

      return (object) [
        'type' => 'payment',
        'created_at' => $tx->created_at,

        // display
        'player' => $playerName ?: optional($tx->user)->name,
        'method' => $method,

        // ledger (ONE ROW PER TRANSACTION)
        'gross' => $grossTx,
        'fee' => $pfFeeTx,      // PayFast expense
        'capeFee' => $capeFeeTx,    // Cape Tennis expense (per entry × count)
        'net' => $netTx,

        // trace
        'pf_payment_id' => $tx->pf_payment_id,
        'tx_id' => $tx->id,
        'paid_at' => $tx->created_at,

        // for child drill-down
        'order' => $tx->order,
        'entryCount' => $entryCount,
        'payfastGross' => $payfastGross,
        'walletUsed' => $walletUsed,
      ];
    });



    if ($STEP === 3) {
      dd([
        'step' => 3,
        'payment_rows_count' => $paymentRows->count(),

        'payment_rows_sample' => $paymentRows->take(5)->map(fn($r) => [
          'type' => $r->type,
          'gross' => $r->gross,
          'fee' => $r->fee,
          'net' => $r->net,
          'pf_payment_id' => $r->pf_payment_id,
        ])->values(),

        'payment_totals_EVENT_ONLY' => [
          'gross' => $paymentRows->sum('gross'),
          'fee' => $paymentRows->sum('fee'),
          'net' => $paymentRows->sum('net'),
        ],
      ]);
    }


    // =========================
    // STEP 4: LOAD REFUND REGS
    // =========================
    // Include ALL paid withdrawn registrations — not just those with a
    // linked transactions_pf row. Admin-marked-paid registrations have
    // payment_status_id=1 and pf_transaction_id set but no transactions_pf
    // row (the legacy/admin path never inserts one). paymentInfo() resolves
    // amounts from the order for those cases (_legacy fallback).
    $refundRegs = CategoryEventRegistration::with([
      'players',
      'categoryEvent.category',
      'payfastTransaction',
    ])
      ->whereHas('categoryEvent', fn($q) => $q->where('event_id', $event->id))
      ->where('status', 'withdrawn')
      ->where('payment_status_id', 1)
      ->whereIn('refund_status', ['completed', 'pending'])
      ->where(function ($q) {
          // Either has a real PayFast transaction (not a test), OR was
          // admin-marked-paid (pf_transaction_id set but no tx row).
          $q->whereHas('payfastTransaction', fn($q2) => $q2->where('is_test', false))
            ->orWhere(function ($q3) {
                $q3->whereNotNull('pf_transaction_id')
                   ->whereDoesntHave('payfastTransaction');
            });
      })
      ->get();

    if ($STEP === 4) {
      dd([
        'step' => 4,
        'refund_regs_count' => $refundRegs->count(),
        'sample' => $refundRegs->take(5)->map(function ($r) {

          $tx = $r->payfastTransaction;
          $items = $tx?->order?->items?->count() ?? 1;

          $perGross = $tx ? round($tx->amount_gross / $items, 2) : 0;
          $perFee = $tx ? round(abs($tx->amount_fee) / $items, 2) : 0;
          $perNet = $perGross - $perFee;

          return [
            // registration
            'registration_id' => $r->id,
            'player' => $r->display_name,
            'category' => optional($r->categoryEvent->category)->name,

            // transaction (raw)
            'tx_gross_total' => (float) ($tx->amount_gross ?? 0),
            'tx_fee_total' => (float) ($tx->amount_fee ?? 0),
            'tx_net_total' => (float) ($tx->amount_net ?? 0),
            'tx_items' => $items,

            // ✅ per player (what refund should use)
            'per_player_gross' => $perGross,
            'per_player_fee' => $perFee,
            'per_player_net' => $perNet,

            // authoritative source
            'paymentInfo()' => $r->paymentInfo(),
          ];
        })->values(),
      ]);
    }


    // =========================
    // STEP 5: COMPUTE REFUND ROWS (TRACE EVERY CALC)
    // =========================
// =========================
// STEP 5: COMPUTE REFUND ROWS (TRACE EVERY CALC)
// =========================
    $refundRows = $refundRegs->map(function ($reg) use ($feePerEntry, $DEBUG) {

      $payment = $reg->paymentInfo();

      $grossPaid = (float) ($payment['gross'] ?? 0);     // per player
      $payfastFee = abs((float) ($payment['fee'] ?? 0));  // per player

      // -----------------------------------
      // REFUND ACCOUNTING (FINAL MODEL)
      // -----------------------------------

      $grossDisplay = $grossPaid;          // refunded to player
      $feeDisplay = -1 * $payfastFee;    // PF fee recovered
      $capeDisplay = -1 * $feePerEntry;   // Cape fee recovered

      // Net impact = reverse original net
      $netImpact = -1 * ($grossPaid - $payfastFee - $feePerEntry);

      if ($DEBUG) {
        Log::info('REFUND FINAL MODEL', [
          'reg_id' => $reg->id,
          'gross' => $grossDisplay,
          'pf_recovered' => $feeDisplay,
          'cape_recovered' => $capeDisplay,
          'net' => $netImpact,
        ]);
      }

      return (object) [
        'type' => 'refund',
        'refund_status' => $reg->refund_status,
        'created_at' => $reg->refunded_at ?? $reg->withdrawn_at ?? $reg->updated_at,

        'player' => $reg->display_name,
        'category' => optional($reg->categoryEvent->category)->name,
        'method' => ucfirst($reg->refund_method),

        'pf_payment_id' => $payment['pf_payment_id'] ?? null,
        'tx_id' => $payment['transaction_id'] ?? null,
        'paid_at' => $payment['paid_at'] ?? null,

        'gross' => -$grossPaid,
        'fee' => +$payfastFee,
        'capeFee' => +$feePerEntry,
        'net' => (-$grossPaid + $payfastFee + $feePerEntry),
      ];

    });



    if ($STEP === 5) {
      dd([
        'step' => 5,
        'refund_rows_count' => $refundRows->count(),
        'refund_rows_sample' => collect($refundRows)->take(5)->map(fn($r) => [
          'player' => $r->player,
          'gross' => $r->gross,
          'fee' => $r->fee,
          'capeFee' => $r->capeFee,
          'net' => $r->net,
          'pf_payment_id' => $r->pf_payment_id,
          'tx_id' => $r->tx_id,
        ])->values(),
        'refund_totals' => [
          'gross' => $refundRows->sum('gross'),
          'fee' => $refundRows->sum('fee'),
          'capeFee' => $refundRows->sum('capeFee'),
          'net' => $refundRows->sum('net'),
        ],
      ]);
    }

    // =========================
    // STEP 5b: WALLET-ONLY PAYMENT ROWS
    // Orders paid entirely by wallet (no PayFast transaction)
    // =========================
    $walletOnlyOrderIds = RegistrationOrder::whereHas('items', function ($q) use ($event) {
        $q->whereHas('category_event', fn($q2) => $q2->where('event_id', $event->id));
      })
      ->where('wallet_reserved', '>', 0)
      ->where(function ($q) {
        $q->whereNull('payfast_amount_due')
          ->orWhere('payfast_amount_due', 0);
      })
      ->pluck('id');

    $walletOnlyRows = WalletTransaction::with(['wallet.payable'])
      ->whereIn('source_id', $walletOnlyOrderIds)
      ->where('source_type', 'event_registration_wallet_payment')
      ->where('type', 'debit')
      ->get()
      ->map(function ($wt) use ($feePerEntry) {
        $order = RegistrationOrder::with('items')->find($wt->source_id);
        $entryCount = max(1, $order?->items?->count() ?? 1);
        $gross = round((float) $wt->amount, 2);
        $capeFeeTx = -1 * round($feePerEntry * $entryCount, 2);
        $netTx = round($gross + $capeFeeTx, 2);
        $user = $wt->wallet?->payable;

        return (object) [
          'type'       => 'payment',
          'created_at' => $wt->created_at,
          'player'     => $user?->name ?? '—',
          'method'     => 'Wallet',
          'gross'      => $gross,
          'fee'        => 0,
          'capeFee'    => $capeFeeTx,
          'net'        => $netTx,
          'pf_payment_id' => null,
          'tx_id'      => null,
          'paid_at'    => $wt->created_at,
          'order'      => $order,
          'entryCount' => $entryCount,
          'payfastGross' => 0,
          'walletUsed' => $gross,
        ];
      });

    // =========================
    // STEP 6: MERGE LEDGER
    // =========================
    $ledger = collect()
      ->merge($paymentRows)
      ->merge($walletOnlyRows)
      ->merge($refundRows)
      ->sortByDesc('created_at')
      ->values();
   
    if ($STEP === 6) {
    
      dd([
        'step' => 6,
        'ledger_count' => $ledger->count(),
        'ledger_sample' => $ledger->take(10)->map(fn($r) => [
        
          'type' => $r->type,
          'created_at' => (string) $r->created_at,
          'player' => $r->player ?? null,
          'gross' => $r->gross,
          'fee' => $r->fee,
          'capeFee' => $r->capeFee,
          'net' => $r->net,
          'pf_payment_id' => $r->pf_payment_id ?? null,
        ])->values(),
      ]);
    }

    // =========================
    // STEP 7: TOTALS
    // =========================

    // Load payouts for this event
    $payouts = EventPayout::where('event_id', $event->id)->orderByDesc('created_at')->get();

    $payoutRows = $payouts->map(function ($p) {
      return (object) [
        'type'       => 'payout',
        'created_at' => $p->created_at,
        'player'     => $p->recipient,
        'method'     => $p->method,
        'gross'      => -abs($p->amount),
        'fee'        => 0,
        'capeFee'    => 0,
        'net'        => -abs($p->amount),
      ];
    });

    // Merge payouts into ledger
    $ledger = $ledger->merge($payoutRows)->sortByDesc('created_at')->values();

    // ✅ Gross = payments only (not reduced by refunds or payouts), including wallet-only
    $allPaymentRows = collect()->merge($paymentRows)->merge($walletOnlyRows);
    $totalGross = $allPaymentRows->sum('gross');

    // Fees are net of refund recoveries
    $totalPayfastFees = $ledger->whereIn('type', ['payment', 'refund'])->sum('fee');
    $totalCapeTennisFees = $ledger->whereIn('type', ['payment', 'refund'])->sum('capeFee');

    // Total payouts (absolute value for display)
    $totalPayouts = $payouts->sum('amount');

    // Net = gross + fees (negative) + refund impact + payouts (negative)
    $netTournamentIncome = $ledger->sum('net');

    // Entry count for display (payments only - refunds don't add entries), including wallet-only
    $totalEntries = $isTeamEvent
      ? $allPaymentRows->count()
      : $allPaymentRows->flatMap(fn($t) => optional($t->order)->items ?? collect())->count();

    // Refund count for display
    $refundCount          = $refundRows->count();
    $completedRefundCount = $refundRows->where('refund_status', 'completed')->count();
    $pendingRefundCount   = $refundRows->where('refund_status', 'pending')->count();

    // Withdrawal totals (gross amount refunded back to players)
    $totalWithdrawals          = abs($refundRows->sum('gross'));
    $completedWithdrawalsTotal = abs($refundRows->where('refund_status', 'completed')->sum('gross'));
    $pendingWithdrawalsTotal   = abs($refundRows->where('refund_status', 'pending')->sum('gross'));

    if ($STEP === 7) {
      dd([
        'step' => 7,
        'totals' => [
          'totalEntries' => $totalEntries,
          'refundCount' => $refundCount,
          'totalGross' => $totalGross,
          'totalPayfastFees' => $totalPayfastFees,
          'totalCapeTennisFees' => $totalCapeTennisFees,
          'netTournamentIncome_event' => $netTournamentIncome,
        ],
        'sanity' => [
          'payments_net_sum' => $paymentRows->sum('net'),
          'refunds_net_sum' => $refundRows->sum('net'),
          'ledger_net_sum' => $ledger->sum('net'),
          'check_payments_plus_refunds' => ($paymentRows->sum('net') + $refundRows->sum('net')),
        ],
      ]);
    }

    // Admin entry breakdown (privately collected)
    $adminPaymentRows    = $paymentRows->filter(fn($r) => $r->method === 'Admin Entry');
    $adminEntriesCount   = $adminPaymentRows->count();
    $adminEntriesCapeFee = abs($adminPaymentRows->sum('capeFee'));
    $adminGrossPrivate   = $adminEntriesCount * (float) $event->entryFee; // cash collected privately

    // PayFast breakdown
    $payfastPaymentRows  = $paymentRows->filter(fn($r) => $r->method !== 'Admin Entry');
    $payfastEntriesCount = $totalEntries - $adminEntriesCount;
    $payfastGrossTotal   = $payfastPaymentRows->sum('gross');

    // =========================
    // STEP 8: RETURN VIEW
    // =========================
    return view('backend.event.transactions', [
      'event' => $event,
      'transactions' => $ledger,

      'feePerEntry' => $feePerEntry,
      'isTeamEvent' => $isTeamEvent,

      'totalEntries' => $totalEntries,
      'refundCount'              => $refundCount,
      'completedRefundCount'     => $completedRefundCount,
      'pendingRefundCount'       => $pendingRefundCount,
      'totalWithdrawals'         => $totalWithdrawals,
      'completedWithdrawalsTotal'=> $completedWithdrawalsTotal,
      'pendingWithdrawalsTotal'  => $pendingWithdrawalsTotal,
      'totalGross' => $totalGross,
      'totalPayfastFees' => $totalPayfastFees,
      'totalCapeTennisFees' => $totalCapeTennisFees,
      'totalPayouts' => $totalPayouts,
      'netTournamentIncome' => $netTournamentIncome,
      'adminEntriesCount'   => $adminEntriesCount,
      'adminEntriesCapeFee' => $adminEntriesCapeFee,
      'adminGrossPrivate'   => $adminGrossPrivate,
      'payfastEntriesCount' => $payfastEntriesCount,
      'payfastGrossTotal'   => $payfastGrossTotal,
    ]);
  }

  // =========================
  // SHARED: build the ledger (payments + refunds, no test transactions)
  // Used by both the web view and the PDF export.
  // =========================
  public static function buildLedger(Event $event): array
  {
    $feePerEntry = (float) $event->cape_tennis_fee;
    $isTeamEvent = $event->isTeam();

    // ---- PAYMENTS ----
    $transactions = Transaction::with([
      'user',
      'order.items.player',
      'order.items.category_event.category',
    ])
      ->where('event_id', $event->id)
      ->where('transaction_type', 'Registration')
      ->where('amount_gross', '>=', 0)
      ->where('is_test', false)
      ->orderByDesc('created_at')
      ->get();

    $paymentRows = $transactions->map(function ($tx) use ($feePerEntry) {
      $items      = collect(optional($tx->order)->items ?? []);
      $entryCount = max(1, $items->count());
      $payfastGross = round((float) $tx->amount_gross, 2);
      $walletUsed   = round((float) optional($tx->order)->wallet_reserved, 2);
      $grossTx      = $payfastGross + $walletUsed;
      $pfFeeTx      = $tx->pf_payment_id === null ? 0 : (-1 * SiteSetting::calculatePayfastFee($payfastGross));
      $capeFeeTx    = -1 * round($feePerEntry * $entryCount, 2);
      $netTx        = round($grossTx + $pfFeeTx + $capeFeeTx, 2);
      if ($tx->pf_payment_id === null && $walletUsed == 0) {
        $method = 'Admin Entry';
      } elseif ($walletUsed > 0) {
        $method = 'PayFast + Wallet';
      } else {
        $method = 'PayFast';
      }

      return (object) [
        'type'          => 'payment',
        'created_at'    => $tx->created_at,
        'player'        => optional($tx->user)->name,
        'method'        => $method,
        'gross'         => $grossTx,
        'fee'           => $pfFeeTx,
        'capeFee'       => $capeFeeTx,
        'net'           => $netTx,
        'pf_payment_id' => $tx->pf_payment_id,
        'tx_id'         => $tx->id,
        'paid_at'       => $tx->created_at,
        'order'         => $tx->order,
        'entryCount'    => $entryCount,
        'payfastGross'  => $payfastGross,
        'walletUsed'    => $walletUsed,
      ];
    });

    // ---- REFUNDS ----
    $refundRegs = CategoryEventRegistration::with([
      'players',
      'categoryEvent.category',
      'payfastTransaction',
    ])
      ->whereHas('categoryEvent', fn($q) => $q->where('event_id', $event->id))
      ->where('status', 'withdrawn')
      ->whereIn('refund_status', ['completed', 'pending'])
      ->whereNotNull('pf_transaction_id')
      ->whereHas('payfastTransaction', fn($q) => $q->where('is_test', false))
      ->get();

    $refundRows = $refundRegs->map(function ($reg) use ($feePerEntry) {
      $payment    = $reg->paymentInfo();
      $grossPaid  = (float) ($payment['gross'] ?? 0);
      $payfastFee = abs((float) ($payment['fee'] ?? 0));

      return (object) [
        'type'          => 'refund',
        'refund_status' => $reg->refund_status,
        'created_at'    => $reg->refunded_at ?? $reg->withdrawn_at ?? $reg->updated_at,
        'player'        => $reg->display_name,
        'category'      => optional($reg->categoryEvent->category)->name,
        'method'        => ucfirst($reg->refund_method),
        'pf_payment_id' => $payment['pf_payment_id'] ?? null,
        'tx_id'         => $payment['transaction_id'] ?? null,
        'paid_at'       => $payment['paid_at'] ?? null,
        'gross'         => -$grossPaid,
        'fee'           => +$payfastFee,
        'capeFee'       => +$feePerEntry,
        'net'           => (-$grossPaid + $payfastFee + $feePerEntry),
      ];
    });

    // ---- PAYOUTS ----
    $payouts = EventPayout::where('event_id', $event->id)->orderByDesc('created_at')->get();

    $payoutRows = $payouts->map(function ($p) {
      return (object) [
        'type'       => 'payout',
        'created_at' => $p->created_at,
        'player'     => $p->recipient,
        'method'     => $p->method,
        'gross'      => -abs($p->amount),
        'fee'        => 0,
        'capeFee'    => 0,
        'net'        => -abs($p->amount),
      ];
    });

    // ---- MERGE & TOTALS ----
    $ledger = collect()->merge($paymentRows)->merge($refundRows)->merge($payoutRows)->sortByDesc('created_at')->values();

    // ✅ Gross = payments only
    $totalGross          = $paymentRows->sum('gross');
    $totalPayfastFees    = $ledger->whereIn('type', ['payment', 'refund'])->sum('fee');
    $totalCapeTennisFees = $ledger->whereIn('type', ['payment', 'refund'])->sum('capeFee');
    $totalPayouts        = $payouts->sum('amount');
    $netTournamentIncome = $ledger->sum('net');

    $totalEntries = $isTeamEvent
      ? $paymentRows->count()
      : $paymentRows->flatMap(fn($t) => optional($t->order)->items ?? collect())->count();

    $refundCount = $refundRows->count();

    return compact(
      'ledger',
      'feePerEntry',
      'isTeamEvent',
      'totalEntries',
      'refundCount',
      'totalGross',
      'totalPayfastFees',
      'totalCapeTennisFees',
      'totalPayouts',
      'netTournamentIncome'
    );
  }
}
