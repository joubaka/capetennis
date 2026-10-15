<?php

declare(strict_types=1);

namespace App\Services\Clothing;

use App\Models\ClothingOrder;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClothingRefundReviewService
{
    /** Pure calculation shared by review and the locked financial mutation. */
    public function quote(ClothingOrder $order, array $input): array
    {
        $fail = fn ($message) => throw ValidationException::withMessages(['refund' => $message]);
        $cents = ClothingRefundService::cents(...);
        $paid = $cents($order->amount_paid ?? '0');
        if ($order->pay_status !== 1 || $order->status !== 'completed' || ! $order->user_id || ! $order->paid_at || $paid <= 0 || $paid !== $cents($order->total)) {
            $fail('A reconciled completed payment is required.');
        }
        $attempts = DB::table('clothing_provider_refund_attempts')->where('clothing_order_id', $order->id)->orderBy('id')->get();
        if ($input['method'] === 'payfast') {
            if (! $order->payfast_paid || ! $order->payfast_pf_payment_id) $fail('This order has no verified original PayFast payment.');
            if ($attempts->whereIn('status', ['dispatching', 'uncertain'])->isNotEmpty()) $fail('An earlier PayFast refund needs reconciliation before another can be submitted.');
        }
        $lines = $order->items()->with('refundItems.refund')->when(DB::transactionLevel() > 0, fn ($query) => $query->lockForUpdate())->orderBy('id')->get()->keyBy('id');
        if ($lines->sum(fn ($line) => $cents($line->line_total)) !== $paid) $fail('The clothing item amounts do not match the paid order. Reconcile this order before refunding.');
        $mode = $input['mode'] ?? 'quantity';
        $selected = $mode === 'quantity' ? array_filter($input['items'] ?? [], fn ($qty) => (int) $qty > 0) : [($input['item_id'] ?? 0) => 0];
        if ($selected === []) $fail('Select at least one clothing item to refund.');
        ksort($selected);
        $allocations = [];
        $total = 0;
        foreach ($selected as $id => $quantity) {
            $line = $lines->get($id);
            if (! $line || $line->qty <= 0) $fail('Choose an item belonging to this order.');
            $linePaid = $cents($line->line_total);
            $used = $line->refundItems->filter(fn ($entry) => in_array($entry->refund?->refund_status, ['pending', 'completed'], true));
            $remaining = $linePaid - $used->sum(fn ($entry) => $cents($entry->amount));
            if ($mode === 'quantity') {
                if ($quantity < 1 || $quantity > $line->remaining_quantity) $fail('The selected quantity has already been refunded or reserved.');
                $before = $line->refunded_quantity;
                $amount = min(intdiv($linePaid * ($before + $quantity), $line->qty) - intdiv($linePaid * $before, $line->qty), $remaining);
            } else {
                $amount = $mode === 'full' ? $remaining : $cents($input['amount'] ?? '0');
                $quantity = $mode === 'full' ? $line->remaining_quantity : 0;
            }
            if ($amount <= 0 || $amount > $remaining) $fail('The refund exceeds this item’s remaining paid amount.');
            $allocations[$id] = ['quantity' => (int) $quantity, 'amount' => sprintf('%.2f', $amount / 100)];
            $total += $amount;
        }
        $refunds = $order->refunds()->with('items')->orderBy('id')->get();
        $active = $refunds->whereIn('refund_status', ['pending', 'completed']);
        $remainingOrder = $paid - $active->sum(fn ($refund) => $cents($refund->refund_gross));
        if ($total <= 0 || $total > $remainingOrder) $fail('The refund exceeds the order’s remaining payment.');
        $external = $active->where('refund_method', 'payfast')->sum(fn ($refund) => $cents($refund->refund_net));
        if ($input['method'] === 'payfast' && $total > $cents($order->payfast_amount_due) - $external) $fail('The refund exceeds the original PayFast portion.');
        $rows = $lines->map(function ($line) use ($allocations, $cents) {
            $used = $line->refundItems->filter(fn ($entry) => in_array($entry->refund?->refund_status, ['pending', 'completed'], true));
            return ['id' => $line->id, 'name' => $line->item_name, 'size' => $line->size_name, 'purchased' => $line->qty,
                'paid_cents' => $cents($line->line_total), 'used_quantity' => $line->refunded_quantity,
                'used_cents' => $used->sum(fn ($entry) => $cents($entry->amount)), 'remaining_quantity' => $line->remaining_quantity,
                'selected_quantity' => $allocations[$line->id]['quantity'] ?? 0,
                'selected_cents' => isset($allocations[$line->id]) ? $cents($allocations[$line->id]['amount']) : 0];
        })->values()->all();
        $snapshot = hash('sha256', json_encode([$order->getAttributes(), $lines->map(fn ($line) => $line->getAttributes())->all(),
            $refunds->map(fn ($refund) => [$refund->getAttributes(), $refund->items->map(fn ($line) => $line->getAttributes())->all()])->all(),
            $attempts->all(), app(ClothingRefundService::class)->deadline($order->event, $order)?->toIso8601String(), $allocations, $total], JSON_THROW_ON_ERROR));
        return ['allocations' => $allocations, 'total_cents' => $total, 'paid_cents' => $paid, 'remaining_cents' => $remainingOrder,
            'kind' => $total === $paid ? 'Full original order refund' : ($total === $remainingOrder ? 'Full remaining order refund' : 'Partial order refund'),
            'rows' => $rows, 'snapshot' => $snapshot];
    }

    public function token(Event $event, ClothingOrder $order, User $actor, array $input, array $quote): string
    {
        return Crypt::encryptString(json_encode(['actor' => $actor->id, 'event' => $event->id, 'order' => $order->id,
            'expires' => now()->addMinutes(20)->timestamp, 'input' => $input, 'snapshot' => $quote['snapshot']], JSON_THROW_ON_ERROR));
    }

    public function resolve(string $token, Event $event, ClothingOrder $order, User $actor): array
    {
        try { $review = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR); }
        catch (\Throwable) { throw ValidationException::withMessages(['refund' => 'This refund review is invalid. Select the items and review again.']); }
        abort_unless(($review['actor'] ?? null) === $actor->id && ($review['event'] ?? null) === $event->id && ($review['order'] ?? null) === $order->id, 403);
        if (($review['expires'] ?? 0) < now()->timestamp) throw ValidationException::withMessages(['refund' => 'This refund review expired. Review the current items again.']);
        return $review['input'] + ['_review_snapshot' => $review['snapshot']];
    }
}
