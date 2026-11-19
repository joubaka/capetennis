<?php

declare(strict_types=1);

namespace App\Domain\Refunds\Services;

use App\Models\{CategoryAdjustment, CategoryEventRegistration, IndividualBeneficiaryCorrection, IndividualProviderRefundAttempt, RegistrationOrder, RegistrationOrderItems, Transaction};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** One durable dispatch claim for an exact original single-item, zero-wallet individual receipt. */
final class IndividualProviderRefundGuard
{
    public function dispatch(string $providerId, float $amount, callable $transport): array
    {
        $this->ensure(! CategoryEventRegistration::where('pf_transaction_id', $providerId)->where('refund_status', 'completed')->where('refund_method', 'wallet')->exists(),
            'This payment was already refunded to a wallet. No provider refund may be dispatched.');
        $this->ensure(! IndividualProviderRefundAttempt::where('pf_payment_id', $providerId)->exists(), 'This provider refund attempt requires reconciliation before another dispatch.');
        $orders = RegistrationOrder::where('payfast_pf_payment_id', $providerId)->get();
        if ($orders->isEmpty()) return $transport(); // Team/clothing receipts retain their owning workflows.
        $this->ensure($orders->count() === 1, 'This PayFast reference belongs to ambiguous registration orders.');
        $candidate = $orders->sole();
        if (! $this->supportsOriginalOrder($candidate)) return $transport();
        $this->ensure(DB::transactionLevel() === 0, 'Provider dispatch must commit its claim before contacting PayFast.');
        $item = $candidate->items()->sole();
        $entries = CategoryEventRegistration::where('registration_id', $item->registration_id)->where('category_event_id', $item->category_event_id)->pluck('id');
        $this->ensure($entries->count() === 1, 'The original paid entry is unavailable or ambiguous for provider refund review.');
        $entryId = $entries->sole();
        $attempt = DB::transaction(function () use ($entryId, $amount, $providerId, $candidate) {
            $entry = CategoryEventRegistration::lockForUpdate()->findOrFail($entryId);
            $funding = $this->funding($entry);
            $this->ensure((string) $funding['order']->payfast_pf_payment_id === $providerId && (int) $funding['order']->id === (int) $candidate->id,
                'The original provider refund payment identity changed.');
            $this->ensure($entry->status === 'withdrawn' && $entry->withdrawn_at && $entry->refund_status === 'pending'
                && in_array($entry->refund_method, ['bank', 'payfast'], true) && ! $entry->refunded_at
                && ! $entry->refund_waived_at && ! $entry->refund_waived_by && ! $entry->refund_waiver_reason
                && $amount > 0 && $this->cents($amount) <= $this->cents($funding['order']->payfast_amount_due)
                && $this->cents($amount) === $this->cents($entry->refund_net)
                && $this->cents($entry->refund_net) + $this->cents($entry->refund_fee) === $this->cents($entry->refund_gross)
                && $this->cents($entry->refund_fee) >= 0 && $this->cents($entry->refund_gross) > 0
                && $this->cents($entry->refund_gross) <= $this->cents($funding['order']->payfast_amount_due),
                'Only the current pending withdrawn payment may be sent to PayFast.');
            $this->assertNoAttempt($entry, $funding['order']);
            return IndividualProviderRefundAttempt::create(['entry_id' => $entry->id, 'order_id' => $funding['order']->id,
                'receipt_id' => $funding['receipt']->id, 'pf_payment_id' => $providerId, 'actor_id' => auth()->id(),
                'amount' => $amount, 'status' => 'dispatching']);
        }, 3);
        // This claim is already committed. A crash or unknown result must never enable a second refund.
        try {
            $result = $transport();
            $attempt->update(['status' => ($result['success'] ?? false) === true ? 'confirmed' : 'uncertain', 'finished_at' => now()]);
            return $result;
        } catch (\Throwable $exception) {
            $attempt->update(['status' => 'uncertain', 'finished_at' => now()]);
            throw $exception;
        }
    }

    public function funding(CategoryEventRegistration $entry): array
    {
        $items = RegistrationOrderItems::where('registration_id', $entry->registration_id)->where('category_event_id', $entry->category_event_id)->lockForUpdate()->get();
        $this->ensure($items->count() === 1, 'One exact original paid registration item is required.');
        $item = $items->sole();
        $order = RegistrationOrder::lockForUpdate()->findOrFail($item->order_id);
        $receipts = Transaction::where('pf_payment_id', $order->payfast_pf_payment_id)->lockForUpdate()->get();
        $receipt = $receipts->count() === 1 ? $receipts->sole() : null;
        $this->ensure($order->items()->count() === 1 && $order->pay_status && $order->payfast_paid && $order->status === 'completed'
            && $order->payment_method === 'payfast' && ! $order->wallet_debited && ! $order->wallet_transaction_id && (float) $order->wallet_reserved === 0.0
            && $entry->payment_method === 'payfast' && (int) $entry->payment_status_id === 1 && ! $entry->wallet_transaction_id
            && ! $entry->admin_payment_status && (int) $item->user_id === (int) $order->user_id && (int) $entry->user_id === (int) $order->user_id
            && $this->cents($item->item_price) > 0 && $this->cents($item->item_price) === $this->cents($order->total_fee)
            && $this->cents($item->item_price) === $this->cents($order->payfast_amount_due)
            && (string) $entry->pf_transaction_id === (string) $order->payfast_pf_payment_id
            && $entry->registration->players()->count() === 1 && $entry->registration->players()->whereKey($item->player_id)->exists()
            && ! CategoryAdjustment::where('entry_id', $entry->id)->exists()
            && ! IndividualBeneficiaryCorrection::where('entry_id', $entry->id)->exists(), 'The original single-player PayFast-only payment must be verified without adjustments.');
        $this->ensure($receipt && ! $receipt->is_test && ! $receipt->archived_at && ! $receipt->custom_str5
            && ($receipt->payment_status === null || $receipt->payment_status === 'COMPLETE')
            && (int) $receipt->custom_int5 === (int) $order->id && (int) $receipt->custom_int4 === (int) $order->user_id
            && (int) $receipt->custom_int1 === (int) $item->category_event_id && (int) $receipt->custom_int2 === (int) $item->player_id
            && (int) $receipt->custom_int3 === (int) $entry->categoryEvent->event_id
            && $this->cents($receipt->amount_gross) === $this->cents($item->item_price), 'A real matching original PayFast receipt is required.');
        foreach (['category_event_id' => $item->category_event_id, 'player_id' => $item->player_id,
            'event_id' => $entry->categoryEvent->event_id, 'registration_id' => $entry->registration_id] as $field => $expected) {
            $this->ensure($receipt->{$field} === null || (int) $receipt->{$field} === (int) $expected, 'PayFast receipt identity is inconsistent.');
        }
        return compact('order', 'item', 'receipt');
    }

    /** Unsupported adjusted/corrected baskets keep their existing owning refund workflow. */
    public function supportsOriginalOrder(RegistrationOrder $order): bool
    {
        if ((float) $order->wallet_reserved > 0 || $order->wallet_debited || $order->items()->count() !== 1) return false;
        $item = $order->items()->sole();
        if (($item->registration?->players()->count() ?? 0) > 1) return false;
        return ! CategoryAdjustment::where('original_order_item_id', $item->id)->exists()
            && ! IndividualBeneficiaryCorrection::where('order_id', $order->id)->exists();
    }

    public function protectsPendingBankEntry(CategoryEventRegistration $entry): bool
    {
        $items = RegistrationOrderItems::where('registration_id', $entry->registration_id)->where('category_event_id', $entry->category_event_id)->get();
        return $items->count() === 1 && $items->sole()->order && $this->supportsOriginalOrder($items->sole()->order);
    }

    public function assertNoAttempt(CategoryEventRegistration $entry, RegistrationOrder $order): void
    {
        $this->ensure(! IndividualProviderRefundAttempt::where('entry_id', $entry->id)->orWhere('order_id', $order->id)
            ->orWhere('pf_payment_id', $order->payfast_pf_payment_id)->exists(), 'A provider refund was dispatched or remains unresolved. Reconcile that attempt before changing refund method.');
    }

    private function cents(mixed $value): int { return (int) round((float) $value * 100); }
    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) throw ValidationException::withMessages(['refund' => $message]);
    }
}
