<?php

declare(strict_types=1);

namespace App\Services\Clothing;

use App\Domain\Refunds\Services\RefundExecutionService;
use App\Models\ClothingOrder;
use App\Models\ClothingRefund;
use App\Models\Event;
use App\Models\EventRegion;
use App\Services\TeamSelection\RegionManagerAccessService;
use Carbon\CarbonImmutable;
use App\Models\User;
use App\Support\FinanceMutationScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClothingRefundService
{
    public function deadline(Event $event, ClothingOrder $order): ?CarbonImmutable
    {
        return EventRegion::where('event_id', $event->id)->where('region_id', $order->team?->region_id)->first()?->clothing_deadline_at;
    }

    public function canRefund(Event $event, ClothingOrder $order, User $actor): bool
    {
        $scope = EventRegion::with('events')
            ->when(DB::transactionLevel() > 0, fn ($query) => $query->lockForUpdate())
            ->where('event_id', $event->id)
            ->where('region_id', $order->team?->region_id)
            ->first();
        return $scope && app(RegionManagerAccessService::class)->canManage($actor, $scope)
            && ($actor->hasRole('super-user') || ($scope->clothing_deadline_at && now()->lt($scope->clothing_deadline_at)));
    }

    public function authorizeOrder(Event $event, ClothingOrder $order, User $actor, bool $mutation = true): void
    {
        $scope = EventRegion::with('events')
            ->when(DB::transactionLevel() > 0, fn ($query) => $query->lockForUpdate())
            ->where('event_id', $event->id)
            ->where('region_id', $order->team?->region_id)
            ->first();
        abort_unless($actor->hasRole('super-user') || ($scope && app(RegionManagerAccessService::class)->canManage($actor, $scope)), 403);
        abort_unless((int) $order->event_id === (int) $event->id
            && (int) $order->team?->category?->event_id === (int) $event->id && $scope, 404);
        if ($mutation) {
            abort_unless($this->canRefund($event, $order, $actor), 403, 'Only a super-user may refund after the clothing deadline or while no deadline is set.');
        }
    }

    public function recordLateNote(ClothingRefund $refund, ClothingOrder $order, User $actor, ?string $note): void
    {
        $deadline = $this->deadline($order->event, $order);
        if ($refund->refund_status === 'completed' || ! $deadline || now()->lt($deadline) || $refund->late_refund_note) {
            return;
        }
        abort_unless($actor->hasRole('super-user'), 403);
        if (trim((string) $note) === '') {
            throw ValidationException::withMessages(['late_refund_note' => 'Explain the late refund and the clothing payout adjustment to reconcile.']);
        }
        $refund->fill(['clothing_deadline_at' => $deadline, 'late_refund_note' => trim($note), 'reconciliation_status' => 'open'])->save();
        activity('refund')->performedOn($refund)->causedBy($actor)->withProperties(['amount' => $refund->refund_net, 'deadline' => $deadline->toIso8601String()])->log('Late clothing refund requires payout reconciliation');
    }

    public function resolveReconciliation(Event $event, ClothingOrder $order, ClothingRefund $refund, User $actor, string $note): void
    {
        abort_unless($actor->hasRole('super-user'), 403);
        $this->authorizeOrder($event, $order, $actor);
        FinanceMutationScope::run('refund_state_write', function () use ($event, $order, $refund, $actor, $note) {
            DB::transaction(function () use ($event, $order, $refund, $actor, $note) {
                $locked = ClothingRefund::lockForUpdate()->findOrFail($refund->id);
                abort_unless((int) $locked->event_id === (int) $event->id && (int) $locked->clothing_order_id === (int) $order->id, 404);
                if ($locked->reconciliation_status === 'resolved') {
                    return;
                }
                if ($locked->reconciliation_status !== 'open' || $locked->refund_status !== 'completed' || trim($note) === '') {
                    throw ValidationException::withMessages(['reconciliation_note' => 'Complete the refund first, then record how its clothing payout adjustment was reconciled.']);
                }
                $locked->fill(['reconciliation_status' => 'resolved', 'reconciliation_note' => trim($note), 'reconciled_by' => $actor->id, 'reconciled_at' => now()])->save();
                activity('refund')->performedOn($locked)->causedBy($actor)->withProperties(['amount' => $locked->refund_net, 'note' => trim($note)])->log('Clothing payout reconciliation resolved');
            });
        });
    }

    public function completeExternal(Event $event, ClothingOrder $order, ClothingRefund $refund, User $actor, string $reference, ?string $lateNote = null): ClothingRefund
    {
        $this->authorizeOrder($event, $order, $actor);
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/\-]{2,119}$/', $reference)) {
            throw ValidationException::withMessages(['reference' => 'Record a payment reference without personal banking details.']);
        }

        return FinanceMutationScope::run('refund_state_write', function () use ($event, $order, $refund, $actor, $reference, $lateNote) {
            return DB::transaction(function () use ($event, $order, $refund, $actor, $reference, $lateNote) {
                $lockedOrder = ClothingOrder::lockForUpdate()->findOrFail($order->id);
                $this->authorizeOrder($event, $lockedOrder, $actor);
                $locked = ClothingRefund::lockForUpdate()->findOrFail($refund->id);
                if (DB::table('clothing_provider_refund_attempts')->where('clothing_refund_id', $locked->id)->exists()) {
                    throw ValidationException::withMessages(['refund' => 'This refund has a PayFast submission. Reconcile its provider result before recording any manual payment.']);
                }
                abort_unless((int) $locked->clothing_order_id === (int) $lockedOrder->id
                    && (int) $locked->event_id === (int) $event->id
                    && (int) $locked->payer_id === (int) $lockedOrder->user_id, 404);
                if ($locked->refund_status === 'completed') {
                    if (in_array($locked->refund_method, ['bank', 'payfast'], true) && $locked->external_reference === $reference) {
                        return $locked;
                    }
                    throw ValidationException::withMessages(['refund' => 'A different completed refund is already recorded.']);
                }
                if ($locked->refund_status !== 'pending' || ! in_array($locked->refund_method, ['bank', 'payfast'], true)
                    || (int) $lockedOrder->pay_status !== 1 || $lockedOrder->status !== 'completed'
                    || self::cents($locked->refund_net) <= 0
                    || self::cents($locked->refund_gross) - self::cents($locked->refund_fee) !== self::cents($locked->refund_net)) {
                    throw ValidationException::withMessages(['refund' => 'Only an eligible pending external refund can be recorded.']);
                }
                $this->recordLateNote($locked, $lockedOrder, $actor, $lateNote);
                $locked->fill(['external_reference' => $reference, 'completed_by' => $actor->id])->save();
                $completed = app(RefundExecutionService::class)->executeBankRefund($locked, ['refund_method' => $locked->refund_method]);
                activity('refund')->performedOn($completed)->causedBy($actor)->withProperties([
                    'payer_id' => $locked->payer_id, 'order_id' => $lockedOrder->id, 'event_id' => $event->id,
                    'reference' => $reference, 'amount' => $locked->refund_net,
                    'paid_to_original_payer' => true, 'provider_dispatched' => false,
                ])->log('Clothing external refund recorded');

                return $completed;
            });
        });
    }

    public static function cents(mixed $amount): int
    {
        $value = (string) $amount;
        if (! preg_match('/^([0-9]+)(?:\.([0-9]{1,2}))?$/', $value, $match)) {
            throw ValidationException::withMessages(['refund' => 'A stored clothing amount requires reconciliation.']);
        }

        return ((int) $match[1] * 100) + (int) str_pad($match[2] ?? '', 2, '0');
    }
}
