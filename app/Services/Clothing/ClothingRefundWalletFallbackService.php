<?php

declare(strict_types=1);

namespace App\Services\Clothing;

use App\Domain\Refunds\Services\RefundExecutionService;
use App\Models\{ClothingOrder, ClothingRefund, Event, User};
use App\Support\FinanceMutationScope;
use Illuminate\Support\Facades\{Crypt, DB};
use Illuminate\Validation\ValidationException;

/** Completes a proven unpaid provider refund to the original payer wallet. */
final class ClothingRefundWalletFallbackService
{
    private const AUDIT = 'Saved clothing PayFast refund completed to original payer wallet';

    public function review(Event $event, ClothingOrder $order, ClothingRefund $refund, User $actor, ?string $lateNote): string
    {
        $verified = $this->verifiedState($event, $order, $refund, $actor);
        return DB::transaction(function () use ($event, $order, $refund, $actor, $lateNote, $verified) {
            [$lockedOrder, $locked, $attempt] = $this->lock($event, $order, $refund, $actor);
            $this->eligible($lockedOrder, $locked, $attempt);
            $this->assertVerified($verified, $lockedOrder, $locked, $attempt);
            $deadline = app(ClothingRefundService::class)->deadline($event, $lockedOrder);
            if ($deadline && now()->gte($deadline) && ! $locked->late_refund_note && trim((string) $lateNote) === '') {
                $this->invalid('Explain the late refund and clothing payout adjustment before reviewing.');
            }
            return Crypt::encryptString(json_encode([
                'actor_id' => $actor->id, 'event_id' => $event->id, 'order_id' => $order->id, 'refund_id' => $refund->id,
                'issued_at' => now()->timestamp, 'snapshot' => $this->hash($lockedOrder, $locked, $attempt),
                'late_note' => $lateNote,
            ], JSON_THROW_ON_ERROR));
        });
    }

    public function confirm(Event $event, ClothingOrder $order, ClothingRefund $refund, User $actor, string $reviewToken): ClothingRefund
    {
        try { $token = json_decode(Crypt::decryptString($reviewToken), true, 16, JSON_THROW_ON_ERROR); }
        catch (\Throwable) { $this->invalid('Review this saved refund again before confirming.'); }
        if (($token['actor_id'] ?? null) !== $actor->id || ($token['event_id'] ?? null) !== $event->id
            || ($token['order_id'] ?? null) !== $order->id || ($token['refund_id'] ?? null) !== $refund->id
            || ! is_int($token['issued_at'] ?? null) || $token['issued_at'] > now()->timestamp
            || $token['issued_at'] < now()->subMinutes(10)->timestamp) {
            $this->invalid('This review expired or belongs to another refund or user.');
        }
        $verified = $this->verifiedState($event, $order, $refund, $actor, true);
        return DB::transaction(function () use ($event, $order, $refund, $actor, $reviewToken, $token, $verified) {
            [$lockedOrder, $locked, $attempt] = $this->lock($event, $order, $refund, $actor);
            $tokenHash = hash('sha256', $reviewToken);
            if ($locked->refund_status === 'completed' && $locked->refund_method === 'wallet'
                && DB::table('activity_log')->where('subject_type', $locked->getMorphClass())->where('subject_id', $locked->id)
                    ->where('description', self::AUDIT)->get(['properties'])->contains(fn ($audit) =>
                        (json_decode($audit->properties, true)['review_hash'] ?? null) === $tokenHash)) {
                return $locked;
            }
            $this->eligible($lockedOrder, $locked, $attempt);
            $this->assertVerified($verified, $lockedOrder, $locked, $attempt);
            if (! hash_equals($token['snapshot'], $this->hash($lockedOrder, $locked, $attempt))) {
                $this->invalid('The saved refund changed after review. Review it again.');
            }
            FinanceMutationScope::run('refund_state_write', fn () => app(ClothingRefundService::class)
                ->recordLateNote($locked, $lockedOrder, $actor, $token['late_note'] ?? null));
            $payer = User::lockForUpdate()->findOrFail($locked->payer_id);
            $wallet = $payer->wallet()->lockForUpdate()->first() ?? $payer->wallet()->create([]);
            if ($attempt->status === 'reconciled_ready' || DB::table('activity_log')->where('subject_type', $locked->getMorphClass())
                ->where('subject_id', $locked->id)->where('description', 'Clothing PayFast uncertain attempt reconciled for retry')->exists()) {
                app(ClothingPayfastRefundRecoveryService::class)->assertWalletCreditsUnrelated($locked);
            }
            $completed = app(RefundExecutionService::class)->executeWalletRefund($locked, $wallet,
                ClothingRefundService::cents($locked->refund_net) / 100, 'clothing_refund', $locked->id,
                ['event_id' => $event->id, 'order_id' => $order->id],
                ['refund_method' => 'wallet', 'completed_by' => $actor->id, 'completed_by_name' => $actor->name]);
            activity('refund')->performedOn($completed)->causedBy($actor)->withProperties([
                'review_hash' => $tokenHash, 'original_method' => 'payfast', 'provider_attempt' => (array) $attempt,
                'payer_id' => $payer->id, 'amount' => $locked->refund_net, 'provider_dispatched' => false,
            ])->log(self::AUDIT);
            return $completed;
        });
    }

    /** Release all locks before signed provider GETs, then compare under locks. */
    private function verifiedState(Event $event, ClothingOrder $order, ClothingRefund $refund, User $actor, bool $allowCompleted = false): string
    {
        if (DB::transactionLevel() !== 0) {
            $this->invalid('Provider verification must run outside a database transaction.');
        }
        [$snapshot, $pending, $requiresProof] = DB::transaction(function () use ($event, $order, $refund, $actor, $allowCompleted) {
            [$lockedOrder, $locked, $attempt] = $this->lock($event, $order, $refund, $actor);
            if ($allowCompleted && $locked->refund_status === 'completed' && $locked->refund_method === 'wallet') {
                return ['', false, false];
            }
            $this->eligible($lockedOrder, $locked, $attempt);
            return [$this->hash($lockedOrder, $locked, $attempt), true, $attempt->status === 'reconciled_ready'];
        });
        if ($pending) {
            app(ClothingPayfastRefundRecoveryService::class)->assertRetryEvidence($refund->fresh(), $requiresProof);
        }
        return $snapshot;
    }

    private function assertVerified(string $snapshot, ClothingOrder $order, ClothingRefund $refund, object $attempt): void
    {
        if (! hash_equals($snapshot, $this->hash($order, $refund, $attempt))) {
            $this->invalid('The order or saved refund changed during PayFast verification. Review again.');
        }
    }

    private function lock(Event $event, ClothingOrder $order, ClothingRefund $refund, User $actor): array
    {
        $lockedOrder = ClothingOrder::lockForUpdate()->findOrFail($order->id);
        app(ClothingRefundService::class)->authorizeOrder($event, $lockedOrder, $actor);
        $locked = ClothingRefund::lockForUpdate()->findOrFail($refund->id);
        abort_unless((int) $locked->clothing_order_id === (int) $lockedOrder->id
            && (int) $locked->event_id === (int) $event->id && (int) $locked->payer_id === (int) $lockedOrder->user_id, 404);
        $attempt = DB::table('clothing_provider_refund_attempts')->where('clothing_refund_id', $locked->id)->lockForUpdate()->first();
        return [$lockedOrder, $locked, $attempt];
    }

    private function eligible(ClothingOrder $order, ClothingRefund $refund, ?object $attempt): void
    {
        if (! $attempt || ! in_array($attempt->status, ['not_dispatched', 'reconciled_ready'], true) || $attempt->confirmed_at !== null
            || (int) $attempt->clothing_order_id !== (int) $order->id || $attempt->pf_payment_id !== $order->payfast_pf_payment_id
            || ClothingRefundService::cents($attempt->amount) !== ClothingRefundService::cents($refund->refund_net)
            || $refund->refund_status !== 'pending' || $refund->refund_method !== 'payfast'
            || $refund->refunded_at || $refund->completed_by || $refund->external_reference
            || $order->pay_status !== 1 || $order->status !== 'completed' || ! $order->payfast_paid
            || ! filled($order->payfast_pf_payment_id)
            || ClothingRefundService::cents($refund->refund_net) <= 0
            || ClothingRefundService::cents($refund->refund_gross) - ClothingRefundService::cents($refund->refund_fee) !== ClothingRefundService::cents($refund->refund_net)
            || DB::table('clothing_provider_refund_attempts')->where('clothing_order_id', $order->id)
                ->where('id', '!=', $attempt->id)->whereIn('status', ['dispatching', 'uncertain'])->exists()
            || DB::table('wallet_transactions')->where('source_type', 'clothing_refund')->where('source_id', $refund->id)->exists()) {
            $this->invalid('Only a pending saved refund proven not submitted to PayFast can be refunded to the wallet.');
        }
        $paid = ClothingRefundService::cents($order->amount_paid ?? 0);
        $orderLines = $order->items()->with('refundItems.refund')->lockForUpdate()->orderBy('id')->get();
        $active = $order->refunds()->whereIn('refund_status', ['pending', 'completed'])->orderBy('id')->get();
        if (! $order->paid_at || $paid <= 0 || $paid !== ClothingRefundService::cents($order->total)
            || $orderLines->sum(fn ($line) => ClothingRefundService::cents($line->line_total)) !== $paid
            || $active->sum(fn ($entry) => ClothingRefundService::cents($entry->refund_gross)) > $paid
            || $active->where('refund_method', 'payfast')->sum(fn ($entry) => ClothingRefundService::cents($entry->refund_net)) > ClothingRefundService::cents($order->payfast_amount_due)) {
            $this->invalid('The saved refund exceeds the verified clothing payment. Reconcile it first.');
        }
        foreach ($orderLines as $line) {
            $allocated = $line->refundItems->filter(fn ($entry) => in_array($entry->refund?->refund_status, ['pending', 'completed'], true));
            if ($line->qty <= 0 || $allocated->contains(fn ($entry) => $entry->quantity < 0)
                || $allocated->sum('quantity') > $line->qty
                || $allocated->sum(fn ($entry) => ClothingRefundService::cents($entry->amount)) > ClothingRefundService::cents($line->line_total)) {
                $this->invalid('Saved refunds exceed a purchased clothing item. Reconcile the allocation first.');
            }
        }
        $lines = $refund->items()->with('item')->get();
        if ($lines->isEmpty() || $lines->contains(fn ($line) => ! $line->item || (int) $line->item->clothing_order_id !== (int) $order->id)
            || $lines->sum(fn ($line) => ClothingRefundService::cents($line->amount)) !== ClothingRefundService::cents($refund->refund_gross)) {
            $this->invalid('The saved clothing item allocation requires reconciliation.');
        }
    }

    private function hash(ClothingOrder $order, ClothingRefund $refund, object $attempt): string
    {
        return hash('sha256', json_encode([$order->getAttributes(), $refund->getAttributes(), (array) $attempt,
            $refund->items()->orderBy('id')->get()->toArray(),
            $order->items()->orderBy('id')->get()->toArray(),
            $order->refunds()->with('items')->orderBy('id')->get()->toArray(),
            app(ClothingRefundService::class)->deadline($order->event, $order)?->toIso8601String(),
            DB::table('wallet_transactions')->whereIn('wallet_id', User::findOrFail($refund->payer_id)->wallet()->pluck('id'))
                ->where('type', 'credit')->orderBy('id')->get()->all()], JSON_THROW_ON_ERROR));
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['refund' => $message]);
    }
}
