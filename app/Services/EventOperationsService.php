<?php

namespace App\Services;

use App\Domain\Draws\Services\DrawReadinessService;
use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use Illuminate\Support\Collection;

/** Read-only event command-centre aggregation. */
class EventOperationsService
{
    public function __construct(
        private readonly DrawReadinessService $drawReadiness,
        private readonly FinancialLedgerService $ledger,
        private readonly EventLifecycleService $lifecycle,
    ) {
    }

    public function for(Event $event): array
    {
        $registrationQuery = $event->registrations();
        $entryCount = (clone $registrationQuery)->activeAndPaid()->count();
        $paidCount = $entryCount;
        $pendingCheckoutCount = (clone $registrationQuery)
            ->active()
            ->where(function ($query): void {
                $query->where('payment_status_id', '!=', 1)->orWhereNull('payment_status_id');
            })->count();
        $withdrawalCount = (clone $registrationQuery)->withdrawn()->count();
        $pendingRefundCount = (clone $registrationQuery)
            ->where('refund_status', CategoryEventRegistration::REFUND_PENDING)->count();

        $draws = $event->draws()->with(['drawFixtures'])->limit(100)->get();
        $readiness = $draws->mapWithKeys(fn ($draw) => [$draw->id => $this->drawReadiness->for($draw)]);
        $warnings = collect();

        $canViewPendingCheckouts = auth()->user()?->hasRole('super-user') ?? false;
        if ($canViewPendingCheckouts) {
            $this->warning($warnings, 'critical', 'pending_checkouts', 'Pending checkouts', $pendingCheckoutCount, route('admin.events.entries.new', $event));
        }
        $this->warning($warnings, 'warning', 'withdrawals', 'Withdrawals', $withdrawalCount, route('admin.events.overview', $event));
        $this->warning($warnings, 'warning', 'pending_refunds', 'Pending refunds', $pendingRefundCount, route('admin.registration.refunds.bank.index'));
        $this->warning($warnings, 'warning', 'draw_readiness', 'Draws not ready to publish', $readiness->filter(fn (array $state) => ! $state['ready_to_publish'])->count(), route('event.tab.draws', $event->id));

        $ledger = $this->ledger->buildForEvent($event);
        $totals = $ledger['totals'];
        $paymentRows = $ledger['paymentRows'];
        $entryRows = $paymentRows->whereIn('type', ['payment', 'admin_entry_fee']);
        $finance = [
            'event' => $event,
            'gross_payments' => $totals['gross_payments'],
            'completed_refunds' => $totals['completed_refunds'],
            'pending_refunds' => $totals['pending_refunds'],
            'total_entries' => $event->isTeam() ? $entryRows->count() : $entryRows->sum(fn ($row) => $row->entryCount ?? 1),
            'total_paid_out' => $totals['total_paid_out'],
            'balance' => $totals['balance'],
            'has_transactions' => $paymentRows->isNotEmpty(),
        ];

        $counts = [
            'entries' => $entryCount,
            'paid' => $paidCount,
            'withdrawals' => $withdrawalCount,
            'pending_refunds' => $pendingRefundCount,
            'draws' => $draws->count(),
        ];
        if ($canViewPendingCheckouts) {
            $counts = [
                'entries' => $entryCount,
                'paid' => $paidCount,
                'pending_checkouts' => $pendingCheckoutCount,
                'withdrawals' => $withdrawalCount,
                'pending_refunds' => $pendingRefundCount,
                'draws' => $draws->count(),
            ];
        }

        return [
            'lifecycle' => $this->lifecycle->snapshot($event),
            'counts' => $counts,
            'draws' => $draws,
            'readiness' => $readiness,
            'finance' => $finance,
            'financeTotals' => $totals,
            'warnings' => $warnings->values(),
        ];
    }

    private function warning(Collection $warnings, string $severity, string $key, string $reason, int $count, string $action): void
    {
        if ($count === 0) {
            return;
        }

        $warnings->push(compact('severity', 'key', 'reason', 'count', 'action'));
    }
}
