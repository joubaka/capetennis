<?php

declare(strict_types=1);

namespace App\Services\Clothing;

use App\Domain\Payments\Services\PaymentOrchestrator;
use App\Models\ClothingItemType;
use App\Models\ClothingOrder;
use App\Models\ClothingOrderItem;
use App\Models\Event;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamRegion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClothingOrderService
{
    public function __construct(
        private PaymentOrchestrator $payments,
        private ClothingPriceService $prices,
    )
    {
    }

    /**
     * @param array<int|string, array{size: mixed, qty: mixed}> $lines
     */
    public function create(
        User $user,
        Event $event,
        TeamRegion $region,
        Team $team,
        Player $player,
        array $lines,
        string $requestToken,
    ): ClothingOrder {
        if (! $event->isTeam() || ! $event->regions()->whereKey($region->id)->exists()) {
            throw ValidationException::withMessages(['region_id' => 'The selected region does not belong to this event.']);
        }
        if ((int) $team->region_id !== (int) $region->id
            || (int) $team->category?->event_id !== (int) $event->id) {
            throw ValidationException::withMessages(['team_id' => 'The selected team does not belong to this event region.']);
        }
        if (! TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->where('player_id', $player->id)->exists()) {
            throw ValidationException::withMessages(['player_id' => 'The selected player is not in this team.']);
        }
        // Clothing is ordered from the published team roster. Any authenticated
        // user may place an order for a rostered player; the relationship checks
        // above prevent a submitted player, team or event from being substituted.
        if (! $region->usesOnlineClothingOrders()) {
            throw ValidationException::withMessages(['region_id' => 'Online clothing ordering is not offered for this region.']);
        }
        if (! (bool) $region->clothing_order) {
            throw ValidationException::withMessages(['region_id' => 'Clothing ordering is currently closed for this region.']);
        }

        $normalised = collect($lines)->map(function (array $line, $itemId) {
            return ['item_id' => (int) $itemId, 'size_id' => (int) $line['size'], 'qty' => (int) $line['qty']];
        })->filter(fn (array $line) => $line['item_id'] > 0 && $line['size_id'] > 0 && $line['qty'] > 0)->values();
        if ($normalised->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Select at least one clothing item, size and quantity.']);
        }
        if ($normalised->contains(fn (array $line) => $line['qty'] > 20)) {
            throw ValidationException::withMessages(['items' => 'A clothing line cannot exceed 20 units.']);
        }

        return DB::transaction(function () use ($user, $event, $region, $team, $player, $normalised, $requestToken) {
            $existing = ClothingOrder::query()->where('request_token', $requestToken)
                ->where('user_id', $user->id)->lockForUpdate()->first();
            if ($existing) {
                if ((int) $existing->event_id !== (int) $event->id
                    || (int) $existing->team_id !== (int) $team->id
                    || (int) $existing->player_id !== (int) $player->id) {
                    throw ValidationException::withMessages(['request_token' => 'This checkout reference has already been used. Refresh and try again.']);
                }
                return $existing;
            }

            $items = ClothingItemType::query()->with('sizes')->where('region_id', $region->id)
                ->whereIn('id', $normalised->pluck('item_id'))->lockForUpdate()->get()->keyBy('id');
            if ($items->count() !== $normalised->pluck('item_id')->unique()->count()) {
                throw ValidationException::withMessages(['items' => 'One or more clothing items are not available for this region.']);
            }

            $rows = [];
            $total = 0.0;
            foreach ($normalised as $line) {
                $item = $items->get($line['item_id']);
                $size = $item->sizes->firstWhere('id', $line['size_id']);
                if (! $size) {
                    throw ValidationException::withMessages(['items' => "Choose a valid size for {$item->item_type_name}."]);
                }
                $price = round((float) $item->price, 2);
                if ($price <= 0) {
                    throw ValidationException::withMessages(['items' => "{$item->item_type_name} does not have an approved selling price."]);
                }
                $lineTotal = round($price * $line['qty'], 2);
                $total += $lineTotal;
                $rows[] = compact('item', 'size', 'price', 'lineTotal', 'line');
            }

            // Calculate the PayFast amount once on the complete basket. The
            // stored catalogue values remain the approved clothing amounts.
            $pricing = $this->prices->totals($total);
            $order = ClothingOrder::create([
                'player_id' => $player->id,
                'team_id' => $team->id,
                'event_id' => $event->id,
                'user_id' => $user->id,
                'request_token' => $requestToken,
                'pay_status' => 0,
                'status' => 'pending',
                'subtotal' => $pricing['subtotal'],
                'payfast_fee' => $pricing['payfast_fee'],
                'total' => $pricing['total'],
            ]);
            foreach ($rows as $row) {
                ClothingOrderItem::create([
                    'clothing_order_id' => $order->id,
                    'clothing_order_item_id' => $row['item']->id,
                    'item_name' => $row['item']->item_type_name,
                    'clothing_item_size' => $row['size']->id,
                    'size_name' => $row['size']->size,
                    'qty' => $row['line']['qty'],
                    'price' => $row['price'],
                    'line_total' => $row['lineTotal'],
                ]);
            }

            return $this->payments->initiatePayment($order, 0, $pricing['total']);
        });
    }
}
