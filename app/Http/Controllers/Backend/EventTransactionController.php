<?php

namespace App\Http\Controllers\Backend;

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Http\Controllers\Controller;
use App\Models\CategoryEventRegistration;
use App\Models\Event;

class EventTransactionController extends Controller
{
  public function index(Event $event)
  {
    $user = auth()->user();
    if (!$user || !$user->hasAnyRole(['super-user', 'admin', 'convenor'])) {
      abort(403, 'Unauthorized.');
    }

    /** @var FinancialLedgerService $ledgerService */
    $ledgerService = app(FinancialLedgerService::class);

    $built       = $ledgerService->buildForEvent($event);
    $paymentRows = $built['paymentRows'];
    $refundRows  = $built['refundRows'];
    $payoutRows  = $built['payoutRows'];
    $totals      = $built['totals'];

    $isTeamEvent = $event->isTeam();

    // Merge all rows into a single chronological ledger for the table
    $ledger = collect()
      ->merge($paymentRows)
      ->merge($refundRows)
      ->merge($payoutRows)
      ->sortByDesc('created_at')
      ->values();

    // Entry count
    $totalEntries = $isTeamEvent
      ? $paymentRows->count()
      : $paymentRows->sum(fn($r) => $r->entryCount ?? 1);

    // Refund / withdrawal breakdown
    // type='refund'     → completed or pending — these affect accounting
    // type='withdrawal' → not_refunded         — operational visibility only
    $accountingRefundRows = $refundRows->where('type', 'refund');
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

    // Admin entry breakdown (privately collected)
    $adminPaymentRows    = $paymentRows->filter(fn($r) => $r->method === 'Admin Entry');
    $adminEntriesCount   = $adminPaymentRows->count();
    $adminEntriesCapeFee = abs($adminPaymentRows->sum('capeFee'));
    $adminGrossPrivate   = $adminEntriesCount * (float) $event->entryFee;

    // PayFast breakdown
    $payfastPaymentRows  = $paymentRows->filter(fn($r) => $r->method !== 'Admin Entry');
    $payfastEntriesCount = $totalEntries - $adminEntriesCount;
    $payfastGrossTotal   = $payfastPaymentRows->sum('gross');

    return view('backend.event.transactions', [
      'event'        => $event,
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

      'totalGross'          => $totals['gross_payments'],
      'totalPayfastFees'    => $totals['pf_fees'],
      'totalCapeTennisFees' => $totals['cape_fees'],
      'totalPayouts'        => $totals['total_paid_out'],
      'netTournamentIncome' => $totals['net_revenue'],

      'adminEntriesCount'   => $adminEntriesCount,
      'adminEntriesCapeFee' => $adminEntriesCapeFee,
      'adminGrossPrivate'   => $adminGrossPrivate,
      'payfastEntriesCount' => $payfastEntriesCount,
      'payfastGrossTotal'   => $payfastGrossTotal,
    ]);
  }

  }

