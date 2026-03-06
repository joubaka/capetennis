<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Transaction;
use App\Models\RegistrationOrder;
use App\Models\CategoryEvent;
use App\Models\Player;

class TransactionFixSeeder extends Seeder
{
    public function run()
    {
        // Find all paid orders missing transactions
        $missingOrders = RegistrationOrder::where('pay_status', 1)
            ->whereNotNull('payfast_pf_payment_id')
            ->whereNotIn('payfast_pf_payment_id', Transaction::whereNotNull('pf_payment_id')->pluck('pf_payment_id'))
            ->with(['items.category_event.event', 'items.player', 'user'])
            ->get();

        $created = 0;
        $skipped = 0;

        foreach ($missingOrders as $order) {
            // Skip if no items
            if ($order->items->isEmpty()) {
                $skipped++;
                continue;
            }

            // Get the first item for event info (multi-item orders share the same event typically)
            $firstItem = $order->items->first();
            $categoryEvent = $firstItem->category_event;
            $player = $firstItem->player;
            $event = $categoryEvent?->event;

            if (!$event) {
                echo "Order {$order->id}: Missing event data, skipping\n";
                $skipped++;
                continue;
            }

            $t = new Transaction();
            $t->transaction_type = 'Registration';
            $t->amount_gross = $order->payfast_amount_due;
            $t->amount_net = $order->payfast_amount_due;
            $t->amount_fee = 0;
            $t->event_id = $event->id;
            $t->item_name = $event->name;
            $t->pf_payment_id = $order->payfast_pf_payment_id;
            $t->custom_int5 = $order->id;
            
            if ($player) {
                $t->player_id = $player->id;
                $t->custom_int2 = $player->id;
                $t->custom_str2 = $player->name . ' ' . $player->surname;
            }
            
            $t->custom_int3 = $event->id;
            $t->custom_int4 = $order->user_id;
            $t->created_at = $order->updated_at; // Use order completion time
            $t->save();

            $created++;
        }

        echo "\n=== TRANSACTION FIX COMPLETE ===\n";
        echo "Created: {$created}\n";
        echo "Skipped: {$skipped}\n";
    }
}
