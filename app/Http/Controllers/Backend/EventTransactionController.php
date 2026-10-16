<?php

namespace App\Http\Controllers\Backend;

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Domain\Finance\Services\EventFinanceReceiptReport;
use App\Http\Controllers\Controller;
use App\Models\CategoryEventRegistration;
use App\Models\Event;

class EventTransactionController extends Controller
{
  public function index(Event $event)
  {
    $this->authorize('event-finance.view', $event);

    /** @var FinancialLedgerService $ledgerService */
    $ledgerService = app(FinancialLedgerService::class);

    $built       = $ledgerService->buildForEvent($event);
    $paymentRows = $built['paymentRows'];
    $refundRows  = $built['refundRows'];
    $payoutRows  = $built['payoutRows'];
    $totals      = $built['totals'];

    $receiptSections = app(EventFinanceReceiptReport::class)->build($event, $paymentRows->merge($refundRows));
    $registrationRows = $receiptSections['registrations']['groups']->flatMap(fn ($group) => $group['rows']);
    $registrationAccountingRows = $registrationRows->reject(fn ($row) => $row->type === 'withdrawal' || ($row->type === 'refund' && ($row->refund_status ?? '') !== 'completed'));
    $isTeamEvent = $event->isTeam();

    // Merge all rows into a single chronological ledger for the table
    $ledger = collect()
      ->merge($paymentRows)
      ->merge($refundRows)
      ->merge($payoutRows)
      ->sortByDesc('created_at')
      ->values();

    // Entry count
    $entryRows = $paymentRows->whereIn('type', ['payment', 'admin_entry_fee']);
    $totalEntries = $isTeamEvent
      ? $entryRows->count()
      : $entryRows->sum(fn($r) => $r->entryCount ?? 1);

    // Refund / withdrawal breakdown
    // type='refund'     → completed or pending — these affect accounting
    // type='withdrawal' → not_refunded         — operational visibility only
    $accountingRefundRows = $registrationRows->where('type', 'refund');
    $noRefundRows         = $refundRows->where('type', 'withdrawal');

    $refundCount          = $accountingRefundRows->count();  // completed + pending refunds only
    $completedRefundCount = $accountingRefundRows->where('refund_status', CategoryEventRegistration::REFUND_COMPLETED)->count();
    $pendingRefundCount   = $accountingRefundRows->where('refund_status', CategoryEventRegistration::REFUND_PENDING)->count();
    $noRefundCount        = $noRefundRows->count();

    // Withdrawal card amounts — only money-moving rows
    $totalWithdrawals          = round($accountingRefundRows->sum('refund_gross'), 2);
    $completedWithdrawalsTotal = round($accountingRefundRows->where('refund_status', CategoryEventRegistration::REFUND_COMPLETED)->sum('refund_gross'), 2);
    $pendingWithdrawalsTotal   = round($accountingRefundRows->where('refund_status', CategoryEventRegistration::REFUND_PENDING)->sum('refund_gross'), 2);
    $noRefundRetainedTotal     = round($noRefundRows->sum('original_gross'), 2); // what was paid and kept

    // Admin entries are fee-liability rows, not received payments.
    $adminFeeRows        = $paymentRows->where('type', 'admin_entry_fee');
    $adminEntriesCount   = $adminFeeRows->count();
    $adminEntriesCapeFee = abs($adminFeeRows->sum('capeFee'));

    // Received-payment breakdown
    $payfastPaymentRows  = $paymentRows->where('type', 'payment');
    $payfastEntriesCount = $isTeamEvent
      ? $payfastPaymentRows->count()
      : $payfastPaymentRows->sum(fn($r) => $r->entryCount ?? 1);
    $payfastGrossTotal   = $payfastPaymentRows->sum('gross');

    return view('backend.event.transactions', [
      'event'        => $event,
      'receiptSections' => $receiptSections,
      'transactions' => $ledger,
      'feePerEntry'  => (float) $event->cape_tennis_fee,
      'isTeamEvent'  => $isTeamEvent,

      'totalEntries'             => $totalEntries,
      'refundCount'              => $refundCount,
      'completedRefundCount'     => $completedRefundCount,
      'pendingRefundCount'       => $pendingRefundCount,
      'noRefundCount'            => $noRefundCount,
      'noRefundRetainedTotal'    => $noRefundRetainedTotal,
      'totalWithdrawals'         => $totalWithdrawals,
      'completedWithdrawalsTotal'=> $completedWithdrawalsTotal,
      'pendingWithdrawalsTotal'  => $pendingWithdrawalsTotal,

      'totalGross'          => $totals['registration_received'],
      'totalPayfastFees'    => round($registrationAccountingRows->sum('fee'), 2),
      'totalCapeTennisFees' => round($registrationAccountingRows->sum('capeFee'), 2),
      'totalPayouts'        => $totals['total_paid_out'],
      'netTournamentIncome' => $totals['registration_balance'],
      'registrationReceived'=> $totals['registration_received'],
      'clothingReceived'    => $totals['clothing_received'],
      'clothingNet'         => $totals['clothing_net'],

      'adminEntriesCount'   => $adminEntriesCount,
      'adminEntriesCapeFee' => $adminEntriesCapeFee,
      'payfastEntriesCount' => $payfastEntriesCount,
      'payfastGrossTotal'   => $payfastGrossTotal,
    ]);
  }

  }

