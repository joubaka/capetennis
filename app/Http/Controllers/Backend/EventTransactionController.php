<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Transaction;
use App\Models\CategoryEventRegistration;
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
      'order.items.player',
      'order.items.category_event.category',
    ])
      ->where('event_id', $event->id)
      ->where('transaction_type', 'Registration')
      ->where('amount_gross', '>', 0)
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
      $grossTx = round((float) $tx->amount_gross, 2);

      // Ledger convention: costs are negative
      $pfFeeTx = -1 * round(abs((float) $tx->amount_fee), 2);

      // ✅ Cape Tennis fee is PER PLAYER (per entry), so multiply by entry count
      $capeFeeTx = -1 * round($feePerEntry * $entryCount, 2);

      // ✅ Net to event for this transaction
      $netTx = round($grossTx + $pfFeeTx + $capeFeeTx, 2);

      return (object) [
        'type' => 'payment',
        'created_at' => $tx->created_at,

        // display
        'player' => optional($tx->user)->name,   // payer
        'method' => 'PayFast',

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
    $refundRegs = CategoryEventRegistration::with([
      'players',
      'categoryEvent.category',
      'payfastTransaction',
    ])
      ->whereHas('categoryEvent', fn($q) => $q->where('event_id', $event->id))
      ->where('status', 'withdrawn')
      ->where('refund_status', 'completed')
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
        'created_at' => $reg->refunded_at ?? $reg->updated_at,

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
    // STEP 6: MERGE LEDGER
    // =========================
    $ledger = collect()
      ->merge($paymentRows)
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
    $netTournamentIncome = $ledger->sum('net');

    $totalGross = $paymentRows->sum('gross');
    $totalPayfastFees = $paymentRows->sum('fee');

    $totalEntries = $isTeamEvent
      ? $paymentRows->count()
      : $paymentRows->flatMap(fn($t) => optional($t->order)->items ?? collect())->count();

    $totalCapeTennisFees = $totalEntries * $feePerEntry;

    if ($STEP === 7) {
      dd([
        'step' => 7,
        'totals' => [
          'totalEntries' => $totalEntries,
          'totalGross' => $totalGross,
          'totalPayfastFees' => $totalPayfastFees,
          'totalCapeTennisFees_platform' => $totalCapeTennisFees,
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

    // =========================
    // STEP 8: RETURN VIEW
    // =========================
    return view('backend.event.transactions', [
      'event' => $event,
      'transactions' => $ledger,

      'feePerEntry' => $feePerEntry,
      'isTeamEvent' => $isTeamEvent,

      'totalEntries' => $totalEntries,
      'totalGross' => $totalGross,
      'totalPayfastFees' => $totalPayfastFees,
      'totalCapeTennisFees' => $totalCapeTennisFees,
      'netTournamentIncome' => $netTournamentIncome,
    ]);
  }
}
