<?php

declare(strict_types=1);

namespace App\Services\Clothing;

use App\Models\ClothingOrder;
use App\Models\ClothingRefund;
use App\Models\User;
use App\Services\Payfast;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Reconciles an old attempt against fresh provider evidence; never sends money. */
final class ClothingPayfastRefundRecoveryService
{
    private const AUDIT = 'Clothing PayFast uncertain attempt reconciled for retry';

    public function preview(ClothingRefund $refund, User $actor): array
    {
        $this->authorize($actor);
        $snapshot = DB::transaction(fn () => $this->snapshot($refund->id));
        $evidence = $this->providerEvidence($snapshot);
        $token = Crypt::encryptString(json_encode([
            'refund_id' => $refund->id, 'actor_id' => $actor->id, 'snapshot' => $this->hash($snapshot),
            'evidence' => $this->hash($evidence), 'issued_at' => now()->timestamp, 'nonce' => (string) Str::uuid(),
        ], JSON_THROW_ON_ERROR));

        return $this->result($snapshot, $evidence) + ['confirmation_token' => $token, 'applied' => false];
    }

    public function apply(ClothingRefund $refund, User $actor, string $confirmation): array
    {
        $this->authorize($actor);
        try {
            $token = json_decode(Crypt::decryptString($confirmation), true, 16, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $this->invalid('Use the confirmation token from a fresh recovery preview.');
        }
        if (($token['refund_id'] ?? null) !== $refund->id || ($token['actor_id'] ?? null) !== $actor->id
            || ! is_int($token['issued_at'] ?? null) || $token['issued_at'] > now()->timestamp
            || $token['issued_at'] < now()->subMinutes(10)->timestamp || ! is_string($token['nonce'] ?? null)) {
            $this->invalid('The recovery preview expired or belongs to another refund or actor.');
        }
        if ($this->alreadyApplied($refund->id, $token['nonce'])) {
            return ['refund_id' => $refund->id, 'applied' => false, 'already_applied' => true];
        }
        $snapshot = DB::transaction(fn () => $this->snapshot($refund->id));
        if (! hash_equals($token['snapshot'], $this->hash($snapshot))) {
            $this->invalid('The saved order or refund changed after preview. Preview again.');
        }
        $evidence = $this->providerEvidence($snapshot);
        if (! hash_equals($token['evidence'], $this->hash($evidence))) {
            $this->invalid('PayFast refund evidence changed after preview. No recovery was applied.');
        }

        return DB::transaction(function () use ($refund, $actor, $snapshot, $evidence, $token) {
            // Same lock order as dispatch; recheck the complete state after HTTP.
            ClothingOrder::whereKey($snapshot['order']['id'])->lockForUpdate()->firstOrFail();
            if ($this->alreadyApplied($refund->id, $token['nonce'])) {
                return ['refund_id' => $refund->id, 'applied' => false, 'already_applied' => true];
            }
            $current = $this->snapshot($refund->id);
            if (! hash_equals($token['snapshot'], $this->hash($current))) {
                $this->invalid('The saved order or refund changed during provider verification.');
            }
            $changed = DB::table('clothing_provider_refund_attempts')->where('id', $current['attempt']['id'])
                ->where('status', 'uncertain')->update(['status' => 'reconciled_ready', 'updated_at' => now()]);
            if ($changed !== 1) {
                $this->invalid('The provider attempt is no longer eligible for recovery.');
            }
            activity('refund')->performedOn(ClothingRefund::findOrFail($refund->id))->causedBy($actor)->withProperties([
                'recovery_token' => $token['nonce'], 'original_snapshot' => $current,
                'provider_evidence' => $evidence, 'verified_at' => now()->toIso8601String(),
                'new_attempt_status' => 'reconciled_ready', 'refund_dispatched' => false, 'wallet_credited' => false,
            ])->log(self::AUDIT);

            return $this->result($current, $evidence) + ['applied' => true, 'already_applied' => false];
        });
    }

    /** Every later retry must still match the reconciled provider ledger. */
    public function assertRetryEvidence(ClothingRefund $refund, bool $required = false): ?int
    {
        $audit = DB::table('activity_log')->where('subject_type', $refund->getMorphClass())->where('subject_id', $refund->id)
            ->where('description', self::AUDIT)->orderByDesc('id')->first();
        if (! $audit) {
            $attempt = DB::table('clothing_provider_refund_attempts')->where('clothing_refund_id', $refund->id)->first();
            if ($required || $attempt?->status === 'reconciled_ready') {
                $this->invalid('The reconciled retry has no audit evidence.');
            }
            return null;
        }
        if (app(Payfast::class)->mode !== 'live') {
            $this->invalid('Reconciled retries require live provider verification.');
        }
        $properties = json_decode($audit->properties, true, 32, JSON_THROW_ON_ERROR);
        $original = $properties['original_snapshot'];
        $attempt = DB::table('clothing_provider_refund_attempts')->where('clothing_refund_id', $refund->id)->first();
        foreach (['id', 'clothing_refund_id', 'clothing_order_id', 'pf_payment_id', 'amount', 'requested_by', 'created_at'] as $field) {
            if (! $attempt || $attempt->$field !== $original['attempt'][$field]) {
                $this->invalid('The original provider attempt changed after reconciliation.');
            }
        }
        $order = ClothingOrder::findOrFail($refund->clothing_order_id);
        foreach ($original['order'] as $field => $value) {
            if ($field !== 'updated_at' && $this->hash([$order->toArray()[$field] ?? null]) !== $this->hash([$value])) {
                $this->invalid('The original payment changed after reconciliation.');
            }
        }
        foreach (['id', 'clothing_order_id', 'event_id', 'payer_id', 'refund_method', 'refund_gross', 'refund_fee', 'refund_net'] as $field) {
            if ($refund->getAttribute($field) !== $original['refund'][$field]) {
                $this->invalid('The saved refund changed after reconciliation.');
            }
        }
        if ($this->hash($this->providerEvidence($original)) !== $this->hash($properties['provider_evidence'])) {
            $this->invalid('PayFast history changed after reconciliation. No refund was submitted.');
        }
        return ClothingRefundService::cents($original['order']['payfast_amount_due']);
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->hasRole('super-user'), 403);
        if (DB::transactionLevel() !== 0 || app(Payfast::class)->mode !== 'live') {
            $this->invalid('Recovery requires a live provider check outside an existing database transaction.');
        }
    }

    private function snapshot(int $refundId): array
    {
        $initial = ClothingRefund::findOrFail($refundId);
        $order = ClothingOrder::whereKey($initial->clothing_order_id)->lockForUpdate()->firstOrFail();
        $refund = ClothingRefund::whereKey($refundId)->lockForUpdate()->firstOrFail();
        $attempt = DB::table('clothing_provider_refund_attempts')->where('clothing_refund_id', $refundId)->lockForUpdate()->first();
        $original = ClothingRefundService::cents($order->payfast_amount_due);
        if (! $attempt || $attempt->status !== 'uncertain' || $attempt->confirmed_at !== null
            || CarbonImmutable::parse($attempt->updated_at)->gt(now()->subMinutes(30))
            || (int) $attempt->clothing_order_id !== (int) $order->id || (int) $refund->clothing_order_id !== (int) $order->id
            || (int) $refund->event_id !== (int) $order->event_id || (int) $refund->payer_id !== (int) $order->user_id
            || $refund->refund_method !== 'payfast' || $refund->refund_status !== 'pending'
            || $refund->refunded_at || $refund->completed_by || $refund->external_reference
            || $order->pay_status !== 1 || $order->status !== 'completed' || ! $order->payfast_paid
            || ! filled($order->payfast_pf_payment_id) || $attempt->pf_payment_id !== $order->payfast_pf_payment_id
            || ($order->pf_id && (string) $order->pf_id !== $order->payfast_pf_payment_id)
            || $order->wallet_debited || ClothingRefundService::cents($order->wallet_reserved ?? 0) !== 0
            || $original <= 0 || $original !== ClothingRefundService::cents($order->total)
            || $original !== ClothingRefundService::cents($order->amount_paid)
            || ClothingRefundService::cents($attempt->amount) !== ClothingRefundService::cents($refund->refund_net)
            || ClothingRefundService::cents($refund->refund_net) <= 0 || ClothingRefundService::cents($refund->refund_net) > $original
            || ClothingRefundService::cents($refund->refund_gross) - ClothingRefundService::cents($refund->refund_fee) !== ClothingRefundService::cents($refund->refund_net)) {
            $this->invalid('Only an aged, exact, pending PayFast-only refund with an unconfirmed attempt can be reconciled.');
        }
        $paymentId = $attempt->pf_payment_id;
        if (ClothingOrder::where('payfast_pf_payment_id', $paymentId)->where('id', '!=', $order->id)->exists()
            || DB::table('clothing_provider_refund_attempts')->where('pf_payment_id', $paymentId)->where('id', '!=', $attempt->id)->exists()
            || $order->refunds()->whereKeyNot($refundId)->whereIn('refund_status', ['pending', 'completed'])->exists()) {
            $this->invalid('Another order, refund or provider attempt claims this payment. Reconcile those claims first.');
        }
        foreach (['team_provider_refund_attempts', 'trial_provider_refund_attempts'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->where('pf_payment_id', $paymentId)->exists()) {
                $this->invalid('Another provider refund workflow claims this payment.');
            }
        }
        foreach (['registration_orders', 'team_payment_orders'] as $table) {
            if (DB::table($table)->where('payfast_pf_payment_id', $paymentId)->exists()) {
                $this->invalid('Another payment workflow claims this PayFast payment.');
            }
        }
        $walletIds = User::findOrFail($order->user_id)->wallet()->pluck('id');
        // Any payer credit requires manual review; do not guess its provenance.
        if (DB::table('wallet_transactions')->whereIn('wallet_id', $walletIds)->where('type', 'credit')->exists()
            || DB::table('wallet_transactions')->where('source_type', 'clothing_refund')->where('source_id', $refundId)->exists()) {
            $this->invalid('A wallet credit or linked wallet transaction exists; reconcile it before recovery.');
        }
        return [
            'order' => $order->only(['id', 'event_id', 'user_id', 'pay_status', 'status', 'payfast_paid', 'payfast_pf_payment_id',
                'payfast_amount_due', 'total', 'amount_paid', 'wallet_reserved', 'wallet_debited', 'updated_at']),
            'refund' => $refund->only(['id', 'clothing_order_id', 'event_id', 'payer_id', 'requested_by', 'refund_method', 'refund_status',
                'refund_gross', 'refund_fee', 'refund_net', 'created_at', 'updated_at']),
            'attempt' => (array) $attempt,
        ];
    }

    private function providerEvidence(array $snapshot): array
    {
        $gateway = app(Payfast::class);
        $paymentId = $snapshot['attempt']['pf_payment_id'];
        $history = $gateway->refundHistory($paymentId);
        $body = $history['data'] ?? [];
        $ledger = $body['data']['response'] ?? null;
        $original = ClothingRefundService::cents($snapshot['order']['payfast_amount_due']);
        if (($history['success'] ?? false) !== true || ($history['http_status'] ?? null) !== 200
            || ($body['code'] ?? null) !== 200 || ($body['status'] ?? null) !== 'success' || ! is_array($ledger)
            || $this->providerCents($ledger['available_balance'] ?? null) !== $original
            || ! is_array($ledger['transactions'] ?? null) || count($ledger['transactions']) !== 1) {
            $this->invalid('PayFast history must confirm the complete original balance and no refund transactions.');
        }
        $transaction = array_values($ledger['transactions'])[0];
        if (! is_array($transaction) || ($transaction['type'] ?? null) !== 'Funds Received'
            || $this->providerCents($transaction['amount'] ?? null) !== $original || ! is_string($transaction['date'] ?? null)) {
            $this->invalid('PayFast history contains a refund, unknown transaction or mismatched original amount.');
        }
        $query = $gateway->refundQuery($paymentId);
        $queryBody = $query['data'] ?? [];
        $availability = $queryBody['data']['response'] ?? $queryBody;
        if (($query['success'] ?? false) !== true || ! is_array($availability)
            || (isset($queryBody['data']) && (($queryBody['code'] ?? null) !== 200 || ($queryBody['status'] ?? null) !== 'success'))
            || ($availability['status'] ?? null) !== 'REFUNDABLE'
            || $this->providerCents($availability['amount_original'] ?? null) !== $original
            || $this->providerCents($availability['amount_available_for_refund'] ?? null) !== $original) {
            $this->invalid('PayFast must independently confirm the unchanged original refundable amount.');
        }
        $method = $availability[ClothingRefundService::cents($snapshot['refund']['refund_net']) === $original ? 'refund_full' : 'refund_partial']['method'] ?? null;
        if (! in_array($method, ['PAYMENT_SOURCE', 'BANK_PAYOUT'], true)) {
            $this->invalid('PayFast does not offer a supported route for this exact saved refund.');
        }
        return ['pf_payment_id' => $paymentId, 'original_cents' => $original, 'available_cents' => $original,
            'transactions' => [['type' => $transaction['type'], 'amount' => $original, 'date' => $transaction['date']]], 'method' => $method];
    }

    private function providerCents(mixed $value): ?int
    {
        return (is_int($value) || is_string($value)) && preg_match('/^[0-9]{1,10}$/', (string) $value) ? (int) $value : null;
    }

    private function alreadyApplied(int $refundId, string $nonce): bool
    {
        return DB::table('activity_log')->where('subject_type', (new ClothingRefund)->getMorphClass())->where('subject_id', $refundId)
            ->where('description', self::AUDIT)->where('properties->recovery_token', $nonce)->exists();
    }

    private function result(array $snapshot, array $evidence): array
    {
        return ['order_id' => $snapshot['order']['id'], 'refund_id' => $snapshot['refund']['id'],
            'attempt_id' => $snapshot['attempt']['id'], 'refund_net' => $snapshot['refund']['refund_net'],
            'provider_evidence' => $evidence, 'refund_dispatched' => false, 'wallet_credited' => false];
    }

    private function hash(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR));
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['refund' => $message]);
    }
}
