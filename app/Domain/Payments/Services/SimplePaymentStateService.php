<?php

namespace App\Domain\Payments\Services;

use App\Models\ClothingOrder;
use App\Models\Order;
use App\Support\FinanceMutationScope;
use Illuminate\Support\Facades\DB;

class SimplePaymentStateService
{
    public function markClothingOrderPaid(ClothingOrder $order, array $attributes = []): ClothingOrder
    {
        return FinanceMutationScope::run('simple_payment_state_write', function () use ($order, $attributes) {
            return DB::transaction(function () use ($order, $attributes) {
                $locked = ClothingOrder::query()->lockForUpdate()->findOrFail($order->id);
                $locked->fill(array_merge([
                    'pay_status' => 1,
                ], $attributes));
                $locked->save();

                return $locked;
            });
        });
    }

    public function markOrderPaid(Order $order, array $attributes = []): Order
    {
        return FinanceMutationScope::run('simple_payment_state_write', function () use ($order, $attributes) {
            return DB::transaction(function () use ($order, $attributes) {
                $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
                $locked->fill(array_merge([
                    'pay_status' => 1,
                ], $attributes));
                $locked->save();

                return $locked;
            });
        });
    }
}
