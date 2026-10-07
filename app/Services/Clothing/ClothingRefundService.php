<?php

declare(strict_types=1);

namespace App\Services\Clothing;

use App\Domain\Refunds\Services\RefundExecutionService;
use App\Models\ClothingOrder;
use App\Models\ClothingRefund;
use App\Models\Event;
use App\Models\User;
use App\Support\FinanceMutationScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClothingRefundService
{
    public function authorizeOrder(Event $event, ClothingOrder $order, User $actor): void
    {
        abort_unless($actor->hasRole('super-user'), 403);
        abort_unless((int) $order->event_id === (int) $event->id
            && (int) $order->team?->category?->event_id === (int) $event->id
            && $event->regions()->whereKey($order->team?->region_id)->exists(), 404);
    }

    public function completeExternal(Event $event, ClothingOrder $order, ClothingRefund $refund, User $actor, string $reference): ClothingRefund
    {
        $this->authorizeOrder($event, $order, $actor);
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/\-]{2,119}$/', $reference)) {
            throw ValidationException::withMessages(['reference' => 'Record a payment reference without personal banking details.']);
        }

        return FinanceMutationScope::run('refund_state_write', function () use ($event, $order, $refund, $actor, $reference) {
            return DB::transaction(function () use ($event, $order, $refund, $actor, $reference) {
                $lockedOrder = ClothingOrder::lockForUpdate()->findOrFail($order->id);
                $this->authorizeOrder($event, $lockedOrder, $actor);
                $locked = ClothingRefund::lockForUpdate()->findOrFail($refund->id);
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
