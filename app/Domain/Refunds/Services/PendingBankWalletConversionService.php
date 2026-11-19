<?php

declare(strict_types=1);

namespace App\Domain\Refunds\Services;

use App\Models\{CategoryEventRegistration, Event, User, WalletTransaction};
use Illuminate\Support\Facades\{Crypt, DB};
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/** Operator-reviewed closure of an existing bank claim, never a second refund. */
final class PendingBankWalletConversionService
{
    public const AUDIT = 'Pending bank refund converted to original-payer wallet';
    private const SOURCE = 'admin_full_refund';

    public function preview(Event $event, CategoryEventRegistration $entry, User $actor, string $providerId, string $reason, bool $verifiedNotRefunded): array
    {
        $this->authorize($actor);
        $this->ensure($verifiedNotRefunded && strlen(trim($reason)) >= 10 && strlen($reason) <= 1000,
            'Confirm your independent PayFast and bank checks and give a specific reason. A failed API query is not verification.');
        return DB::transaction(function () use ($event, $entry, $actor, $providerId, $reason) {
            $context = $this->context($event, $entry->id);
            $this->ensure(trim($providerId) === (string) $context['funding']['order']->payfast_pf_payment_id, 'Enter the exact original PayFast payment reference.');
            $review = $this->review($context, $actor, trim($reason));
            $payload = $review + ['fingerprint' => $this->fingerprint($context, $actor, trim($reason)),
                'funding_fingerprint' => $this->fundingFingerprint($context), 'verified_at' => now()->toIso8601String(),
                'expires' => now()->addMinutes(15)->timestamp, 'independently_verified_no_provider_or_bank_refund' => true];
            return $review + ['token' => Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR))];
        }, 3);
    }

    public function execute(Event $event, CategoryEventRegistration $entry, User $actor, string $token): array
    {
        $this->authorize($actor);
        try { $approved = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR); }
        catch (\Throwable) { throw ValidationException::withMessages(['wallet_conversion' => 'Review this refund before confirming.']); }
        $this->ensure(($approved['actor_id'] ?? 0) === $actor->id && ($approved['event_id'] ?? 0) === $event->id
            && ($approved['entry_id'] ?? 0) === $entry->id && ($approved['expires'] ?? 0) >= now()->timestamp
            && ($approved['independently_verified_no_provider_or_bank_refund'] ?? false) === true, 'The refund review changed or expired. Review again.');
        return DB::transaction(function () use ($event, $entry, $actor, $approved) {
            $locked = CategoryEventRegistration::lockForUpdate()->findOrFail($entry->id);
            abort_unless((int) $locked->categoryEvent?->event_id === (int) $event->id, 404);
            $prior = Activity::where('subject_type', $locked->getMorphClass())->where('subject_id', $locked->id)
                ->where('description', self::AUDIT)->where('properties->fingerprint', $approved['fingerprint'])->first();
            if ($prior) {
                $funding = app(IndividualProviderRefundGuard::class)->funding($locked);
                $payer = User::lockForUpdate()->findOrFail($funding['order']->user_id);
                $wallet = $payer->wallet()->lockForUpdate()->firstOrFail();
                $context = compact('funding', 'payer', 'wallet') + ['entry' => $locked];
                $credit = WalletTransaction::where('wallet_id', $wallet->id)->where('source_type', self::SOURCE)->where('source_id', $locked->id)->sole();
                $this->ensure($locked->status === 'withdrawn' && $locked->withdrawn_at && $locked->refund_status === 'completed'
                    && $locked->refund_method === 'wallet' && (int) $approved['payer_id'] === (int) $payer->id
                    && $locked->refunded_at && $this->cents($locked->refund_gross) === $approved['amount_cents']
                    && $this->cents($locked->refund_net) === $approved['amount_cents'] && $this->cents($locked->refund_fee) === 0
                    && ($approved['wallet_id'] === null || (int) $approved['wallet_id'] === (int) $wallet->id)
                    && $this->cents($credit->amount) === $approved['amount_cents'] && $credit->type === 'credit'
                    && ($credit->meta['conversion_fingerprint'] ?? '') === $approved['fingerprint']
                    && $approved['funding_fingerprint'] === $this->fundingFingerprint($context), 'The completed refund no longer matches this review.');
                return ['already_completed' => true, 'amount' => $approved['amount_cents'] / 100, 'payer_name' => $payer->name];
            }
            $context = $this->context($event, $locked->id);
            $this->ensure(hash_equals($approved['fingerprint'], $this->fingerprint($context, $actor, $approved['reason'])),
                'The pending refund, payment or original payer changed after review. Review again.');
            $payer = $context['payer'];
            $wallet = $context['wallet'] ?? $payer->wallet()->create([]);
            $this->ensure($payer->wallet()->whereKey($wallet->id)->exists(), 'Only the original payer wallet may receive this refund.');
            $before = $locked->getAttributes();
            $amount = $approved['amount_cents'] / 100;
            $completed = \App\Support\FinanceMutationScope::run('pending_bank_wallet_conversion', fn () => app(RefundExecutionService::class)->executeWalletRefund($context['entry'], $wallet, $amount, self::SOURCE, $locked->id,
                ['event_id' => $event->id, 'order_id' => $context['funding']['order']->id, 'payer_id' => $payer->id,
                    'conversion_fingerprint' => $approved['fingerprint'], 'pf_payment_id' => $approved['provider_id'],
                    'reason' => $approved['reason'], 'operator_verified_no_provider_or_bank_refund' => true],
                ['refund_method' => 'wallet', 'refund_gross' => $amount, 'refund_fee' => 0, 'refund_net' => $amount]));
            activity('refund')->performedOn($completed)->causedBy($actor)->withProperties([
                'event_id' => $event->id, 'order_id' => $context['funding']['order']->id, 'payer_id' => $payer->id,
                'wallet_id' => $wallet->id, 'amount' => $amount, 'pf_payment_id' => $approved['provider_id'],
                'fingerprint' => $approved['fingerprint'], 'reason' => $approved['reason'], 'verified_at' => $approved['verified_at'],
                'independently_verified_no_provider_or_bank_refund' => true,
                'before' => array_intersect_key($before, array_flip(['refund_status', 'refund_method', 'refund_gross', 'refund_fee', 'refund_net', 'refunded_at'])),
                'after' => $completed->only(['refund_status', 'refund_method', 'refund_gross', 'refund_fee', 'refund_net', 'refunded_at']),
            ])->log(self::AUDIT);
            return ['already_completed' => false, 'amount' => $amount, 'payer_name' => $payer->name];
        }, 3);
    }

    private function context(Event $event, int $entryId): array
    {
        $entry = CategoryEventRegistration::lockForUpdate()->findOrFail($entryId);
        abort_unless((int) $entry->categoryEvent?->event_id === (int) $event->id, 404);
        $funding = app(IndividualProviderRefundGuard::class)->funding($entry);
        app(IndividualProviderRefundGuard::class)->assertNoAttempt($entry, $funding['order']);
        $this->ensure($entry->status === 'withdrawn' && $entry->withdrawn_at && $entry->refund_status === 'pending'
            && $entry->refund_method === 'bank' && ! $entry->refunded_at && ! $entry->refund_waived_at && ! $entry->refund_waived_by && ! $entry->refund_waiver_reason
            && $this->cents($entry->refund_fee) === 0 && $this->cents($entry->refund_gross) === $this->cents($funding['item']->item_price)
            && $this->cents($entry->refund_net) === $this->cents($entry->refund_gross), 'Only an existing full pending bank refund with no deduction may be converted.');
        $this->ensure(! WalletTransaction::where('source_id', $entry->id)->whereIn('source_type', [self::SOURCE, 'admin_refund',
            'event_registration_refund', 'event_registration_bank_wallet_refund', 'individual_refund'])->exists(), 'A wallet refund ledger entry already exists. Reconcile it first.');
        $payer = User::lockForUpdate()->findOrFail($funding['order']->user_id);
        $wallets = $payer->wallet()->lockForUpdate()->get();
        $this->ensure($wallets->count() <= 1, 'The original payer wallet requires reconciliation.');
        $wallet = $wallets->first();
        return compact('entry', 'funding', 'payer', 'wallet');
    }

    private function review(array $context, User $actor, string $reason): array
    {
        return ['actor_id' => $actor->id, 'event_id' => (int) $context['entry']->categoryEvent->event_id, 'entry_id' => $context['entry']->id,
            'payer_id' => $context['payer']->id, 'payer_name' => $context['payer']->name, 'payer_email' => $context['payer']->email,
            'wallet_id' => $context['wallet']?->id, 'order_id' => $context['funding']['order']->id,
            'provider_id' => $context['funding']['order']->payfast_pf_payment_id,
            'amount_cents' => $this->cents($context['entry']->refund_net), 'reason' => $reason];
    }

    private function fingerprint(array $context, User $actor, string $reason): string
    {
        return hash('sha256', json_encode([$this->review($context, $actor, $reason), $context['entry']->getAttributes(),
            $this->fundingFingerprint($context)], JSON_THROW_ON_ERROR));
    }

    private function fundingFingerprint(array $context): string
    {
        return hash('sha256', json_encode(array_map(fn ($model) => $model->getAttributes(), $context['funding']), JSON_THROW_ON_ERROR));
    }

    private function authorize(User $actor): void { abort_unless($actor->fresh()->hasRole('super-user'), 403); }
    private function cents(mixed $value): int { return (int) round((float) $value * 100); }
    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) throw ValidationException::withMessages(['wallet_conversion' => $message]);
    }
}
