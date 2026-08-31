<?php

namespace App\Console\Commands;

use App\Domain\Payments\Services\PaymentTransactionService;
use App\Models\RegistrationOrder;
use App\Models\Transaction;
use Illuminate\Console\Command;

class RepairMissingPayfastTransactions extends Command
{
    protected $signature = 'finance:repair-missing-payfast {--event= : Limit to one event ID} {--since-days=21 : Only include orders from this many days ago (0 disables the date limit)} {--apply : Write the missing transaction rows}';
    protected $description = 'Preview or repair paid registration orders that have a PayFast ID but no transactions_pf row';

    public function handle(): int
    {
        $sinceDays = max(0, (int) $this->option('since-days'));
        $orders = RegistrationOrder::query()
            ->with(['items.category_event.event', 'items.player'])
            ->where(function ($q) { $q->where('pay_status', true)->orWhere('payfast_paid', true); })
            ->whereNotNull('payfast_pf_payment_id')
            ->when($sinceDays > 0, fn ($q) => $q->where('created_at', '>=', now()->subDays($sinceDays)))
            ->when($this->option('event'), fn ($q, $event) => $q->whereHas('items.category_event', fn ($q) => $q->where('event_id', (int) $event)))
            ->get();

        $missing = $orders->filter(fn ($order) => !Transaction::where('pf_payment_id', $order->payfast_pf_payment_id)->exists());
        $this->info("Found {$missing->count()} paid orders with missing PayFast transaction rows.");

        foreach ($missing as $order) {
            $item = $order->items->first();
            $event = $item?->category_event?->event;
            $this->line("order={$order->id} pf={$order->payfast_pf_payment_id} event=" . ($event?->id ?? '?') . " amount=" . ($order->payfast_amount_due ?? 0));

            if (!$this->option('apply') || !$item || !$event) {
                continue;
            }

            app(PaymentTransactionService::class)->record([
                'pf_payment_id' => $order->payfast_pf_payment_id,
                'amount_gross' => $order->payfast_amount_due,
                'custom_int1' => $item->category_event_id,
                'custom_int2' => $item->player_id,
                'custom_int3' => $event->id,
                'custom_int4' => $order->user_id,
                'custom_int5' => $order->id,
                'item_name' => $event->name,
                'custom_str3' => $event->name,
            ], null);
        }

        return self::SUCCESS;
    }
}
