<?php

declare(strict_types=1);

namespace App\Services\Clothing;

use App\Models\ClothingItemType;
use App\Models\ClothingOrder;
use App\Models\ClothingOrderItem;
use App\Models\ClothingSize;
use App\Models\Event;
use App\Models\TeamRegion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClothingOrderSizeService
{
    public function change(Event $event, TeamRegion $region, ClothingOrder $order, ClothingOrderItem $item, int $sizeId, int $expectedSizeId, string $expectedSizeName, User $actor): ClothingOrderItem
    {
        return DB::transaction(function () use ($event, $region, $order, $item, $sizeId, $expectedSizeId, $expectedSizeName, $actor) {
            $lockedOrder = ClothingOrder::lockForUpdate()->findOrFail($order->id);
            app(ClothingRefundService::class)->authorizeOrder($event, $lockedOrder, $actor, false);
            abort_unless((int) $lockedOrder->team->region_id === (int) $region->id, 404);
            $locked = ClothingOrderItem::lockForUpdate()->findOrFail($item->id);
            abort_unless((int) $locked->clothing_order_id === (int) $lockedOrder->id, 404);
            $fail = fn (string $message) => throw ValidationException::withMessages(['size_id' => $message]);
            if ($lockedOrder->pay_status !== 1 || $lockedOrder->status !== 'completed') {
                $fail('Only a paid, completed clothing order can have its size corrected.');
            }
            if ($locked->refundItems()->lockForUpdate()->get()->isNotEmpty()) {
                $fail('This item has refund history. Its original size must be kept for the refund record.');
            }
            if ((int) $locked->clothing_item_size !== $expectedSizeId || (string) $locked->size_name !== $expectedSizeName) {
                $fail('This size has changed. Refresh the orders page before trying again.');
            }
            $type = ClothingItemType::lockForUpdate()->findOrFail($locked->clothing_order_item_id);
            abort_unless((int) $type->region_id === (int) $region->id, 404);
            $size = ClothingSize::where('item_type', $type->id)->lockForUpdate()->find($sizeId);
            if (! $size) {
                $fail('Choose an available size for this clothing item.');
            }
            if ((int) $locked->clothing_item_size === (int) $size->id && (string) $locked->size_name === $size->size) {
                return $locked;
            }
            $before = $locked->only(['clothing_item_size', 'size_name']);
            $locked->update(['clothing_item_size' => $size->id, 'size_name' => $size->size]);
            activity('clothing-order')->performedOn($locked)->causedBy($actor)->withProperties([
                'event_id' => $event->id, 'region_id' => $region->id, 'order_id' => $lockedOrder->id,
                'before' => $before, 'after' => $locked->only(['clothing_item_size', 'size_name']),
            ])->log('administrator changed clothing order item size');

            return $locked;
        });
    }
}
