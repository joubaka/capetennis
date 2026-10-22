<?php

namespace App\Domain\Refunds\Services;

use App\Domain\Payments\Services\LedgerService;
use App\Events\RefundCompleted;
use App\Models\User;
use App\Models\Wallet;
use App\Support\FinanceMutationScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundExecutionService
{
    public function requestAdminTeamWithdrawalRefund(\App\Models\TeamPaymentOrder $order, User $actor, string $destination, string $feeMode, string $fingerprint): Model
    {
        abort_unless($actor->hasRole('super-user'), 403);
        return DB::transaction(function () use ($order, $actor, $destination, $feeMode, $fingerprint) {
            $locked = \App\Models\TeamPaymentOrder::with('event')->lockForUpdate()->findOrFail($order->id);
            abort_unless(in_array($destination, ['wallet', 'payfast'], true) && in_array($feeMode, ['current', 'full'], true), 422);
            if (!$locked->withdrawn_at || $locked->withdrawn_at->gt($locked->event->withdrawalCloseAt()) || !$locked->pay_status
                || $locked->hasRefund() || $locked->refund_waived_at || $locked->refunded_at
                || \App\Models\TrialParticipation::where('order_id', $locked->id)->exists()
                || DB::table('trial_provider_refund_attempts')->where('order_id', $locked->id)->exists()
                || \App\Models\TeamProviderRefundAttempt::where('order_id', $locked->id)->exists()) {
                throw ValidationException::withMessages(['refund' => 'An eligible withdrawn order without an existing refund is required.']);
            }
            $amounts = app(TeamRefundCalculator::class)->calculate($locked, $feeMode === 'full');
            app(\App\Domain\Payments\Services\TeamPaymentService::class)->assertAdminWithdrawalSettlement($locked);
            abort_unless($amounts['net'] > 0, 422);
            $payer = User::lockForUpdate()->findOrFail($locked->user_id);
            $state = ['refund_method' => $destination, 'refund_gross' => $amounts['gross'], 'refund_fee' => $amounts['fee'], 'refund_net' => $amounts['net']];
            if ($destination === 'wallet') {
                $wallet = $payer->wallet ?? $payer->wallet()->create();
                return $this->executeWalletRefund($locked, $wallet, $amounts['net'], 'admin_team_withdrawal_refund', $locked->id,
                    ['actor_id' => $actor->id, 'event_id' => $locked->event_id, 'team_id' => $locked->team_id, 'fee_mode' => $feeMode], $state);
            }
            app(\App\Domain\Payments\Services\TeamPaymentService::class)->assertVerifiedTeamPayfastSettlement($locked);
            abort_unless($amounts['payfastNet'] > 0, 422, 'No PayFast-funded amount remains to return to its original source.');
            FinanceMutationScope::run('refund_state_write', fn () => $locked->forceFill($state + ['refund_status' => 'pending'])->save());
            \App\Models\TeamProviderRefundAttempt::create([
                'order_id' => $locked->id, 'requested_by' => $actor->id, 'payer_id' => $locked->user_id,
                'beneficiary_player_id' => $locked->effective_player_id, 'pf_payment_id' => $locked->payfast_pf_payment_id,
                'fee_mode' => $feeMode, 'review_fingerprint' => $fingerprint, 'gross' => $amounts['gross'], 'fee' => $amounts['fee'],
                'authorized_withdrawn_at' => $locked->withdrawn_at, 'authorized_deadline' => $locked->event->withdrawalCloseAt(),
                'net' => $amounts['net'], 'amount' => $amounts['payfastNet'], 'wallet_amount' => $amounts['walletNet'], 'status' => 'ready',
            ]);
            return $locked;
        });
    }

    public function executeAdminTeamPayfastRefund(\App\Models\TeamPaymentOrder $order, User $actor): Model
    {
        if (DB::transactionLevel() !== 0) throw new \LogicException('Provider refunds require a committed withdrawal outside an enclosing transaction.');
        $dispatch = false;
        $attempt = DB::transaction(function () use ($order, $actor, &$dispatch) {
            $locked = \App\Models\TeamPaymentOrder::with('event')->lockForUpdate()->findOrFail($order->id);
            abort_unless($actor->hasRole('super-user'), 403);
            $attempt = \App\Models\TeamProviderRefundAttempt::where('order_id', $locked->id)->lockForUpdate()->firstOrFail();
            $this->assertAdminTeamProviderClaim($locked, $attempt);
            if ($locked->refund_status === 'completed') return $attempt;
            if ($attempt->status === 'ready') {
                $attempt->update(['status' => 'dispatching']);
                $dispatch = true;
            } elseif ($attempt->status !== 'confirmed') {
                throw ValidationException::withMessages(['refund' => 'The PayFast outcome requires reconciliation. No second refund or wallet fallback will be sent.']);
            }
            return $attempt;
        });
        if ($dispatch) {
            $unavailable = false;
            try {
                $result = app(\App\Services\Payfast::class)->refundToOriginalSource($attempt->pf_payment_id, (float) $attempt->amount, 'Team withdrawal refund');
                $payload = $result['data'] ?? [];
                $unavailable = ($result['dispatched'] ?? null) === false;
                $confirmed = ($result['success'] ?? false) === true && ($payload['status'] ?? null) === 'success'
                    && (int) ($payload['code'] ?? 0) === 200 && ($payload['data']['response'] ?? null) === true;
            } catch (\Throwable $exception) {
                $confirmed = false;
            }
            \App\Models\TeamProviderRefundAttempt::whereKey($attempt->id)->where('status', 'dispatching')
                ->update(['status' => $confirmed ? 'confirmed' : ($unavailable ? 'unavailable' : 'uncertain'), 'confirmed_at' => $confirmed ? now() : null, 'updated_at' => now()]);
            if (!$confirmed) {
                throw ValidationException::withMessages(['refund' => 'PayFast did not confirm a refund to the original payment source. The withdrawal is complete; review the pending refund and PayFast history before further action.']);
            }
        }
        return DB::transaction(function () use ($order, $actor) {
            $locked = \App\Models\TeamPaymentOrder::with('event')->lockForUpdate()->findOrFail($order->id);
            $attempt = \App\Models\TeamProviderRefundAttempt::where('order_id', $locked->id)->lockForUpdate()->firstOrFail();
            $this->assertAdminTeamProviderClaim($locked, $attempt);
            abort_unless($attempt->status === 'confirmed', 422);
            if ($locked->refund_status === 'completed') return $locked;
            $payer = User::lockForUpdate()->findOrFail($attempt->payer_id);
            $wallet = (float) $attempt->wallet_amount > 0 ? ($payer->wallet ?? $payer->wallet()->create()) : null;
            $completed = FinanceMutationScope::run('admin_team_provider_refund_finalize', fn () => $this->executeSplitRefund($locked, $wallet, (float) $attempt->wallet_amount, 'admin_team_withdrawal_refund', $locked->id,
                ['actor_id' => $actor->id, 'provider_attempt_id' => $attempt->id], [
                    'refund_method' => 'payfast', 'refund_gross' => $attempt->gross, 'refund_fee' => $attempt->fee, 'refund_net' => $attempt->net,
                ]));
            activity('refund')->performedOn($completed)->causedBy($actor)->withProperties([
                'attempt_id' => $attempt->id, 'payer_id' => $attempt->payer_id, 'provider_amount' => $attempt->amount,
                'wallet_amount' => $attempt->wallet_amount, 'fee_mode' => $attempt->fee_mode,
            ])->log('Team withdrawal original source refund confirmed');
            return $completed;
        });
    }

    public function recoverAdminTeamPayfastRefund(\App\Models\TeamPaymentOrder $order, User $actor, ?array $evidence = null): Model
    {
        if (DB::transactionLevel() !== 0) throw new \LogicException('Refund recovery requires a committed request.');
        DB::transaction(function () use ($order, $actor, $evidence): void {
            abort_unless($actor->hasRole('super-user'), 403);
            $locked = \App\Models\TeamPaymentOrder::with('event')->lockForUpdate()->findOrFail($order->id);
            $attempt = \App\Models\TeamProviderRefundAttempt::where('order_id', $locked->id)->lockForUpdate()->firstOrFail();
            $this->assertAdminTeamProviderClaim($locked, $attempt);
            if ($attempt->status === 'confirmed') return;
            abort_unless($evidence !== null && in_array($attempt->status, ['dispatching', 'uncertain', 'unavailable'], true), 422, 'Exact independent PayFast confirmation is required.');
            $valid = validator($evidence, [
                'pf_payment_id' => ['required', 'string', 'max:120'], 'amount' => ['required', 'decimal:0,2', 'min:0.01'],
                'reference' => ['required', 'regex:/^[A-Za-z0-9][A-Za-z0-9._:\/\-]{2,119}$/'],
                'reason' => ['required', 'string', 'min:10', 'max:1000'], 'confirmed_at' => ['required', 'date', 'before_or_equal:now'],
                'externally_confirmed' => ['accepted'],
            ])->validate();
            abort_unless($valid['pf_payment_id'] === $attempt->pf_payment_id && round((float) $valid['amount'], 2) === (float) $attempt->amount, 422);
            $attempt->update(['status' => 'confirmed', 'provider_reference' => $valid['reference'], 'confirmed_at' => $valid['confirmed_at'], 'confirmed_by' => $actor->id]);
            activity('refund')->performedOn($attempt)->causedBy($actor)->withProperties($valid)
                ->log('Team withdrawal PayFast refund independently reconciled');
        });
        return $this->executeAdminTeamPayfastRefund($order, $actor);
    }

    private function assertAdminTeamProviderClaim(\App\Models\TeamPaymentOrder $order, \App\Models\TeamProviderRefundAttempt $attempt): void
    {
        $cents = fn ($value) => (int) round((float) $value * 100);
        app(\App\Domain\Payments\Services\TeamPaymentService::class)->assertVerifiedTeamPayfastSettlement($order);
        $receipt = \App\Models\Transaction::where('custom_str5', 'TeamOrder')->where('custom_int5', $order->id)->firstOrFail();
        if (!$order->withdrawn_at || $order->withdrawn_at->format('Y-m-d H:i:s') !== $attempt->authorized_withdrawn_at?->format('Y-m-d H:i:s')
            || $attempt->authorized_withdrawn_at->gt($attempt->authorized_deadline) || $receipt->is_test
            || (int) $attempt->order_id !== (int) $order->id || (int) $attempt->payer_id !== (int) $order->user_id
            || (int) $attempt->beneficiary_player_id !== $order->effective_player_id || $attempt->pf_payment_id !== $order->payfast_pf_payment_id
            || $order->refund_method !== 'payfast' || !in_array($order->refund_status, ['pending', 'completed'], true)
            || $order->refund_waived_at || $cents($attempt->gross) !== $cents($order->total_amount)
            || $cents($attempt->fee) < 0 || $cents($attempt->net) <= 0 || $cents($attempt->net) > $cents($attempt->gross)
            || $cents($attempt->gross) - $cents($attempt->fee) !== $cents($attempt->net)
            || $cents($attempt->amount) + $cents($attempt->wallet_amount) !== $cents($attempt->net)
            || $cents($attempt->amount) <= 0 || $cents($attempt->amount) > $cents($order->payfast_amount_due)
            || $cents($attempt->wallet_amount) < 0 || $cents($attempt->wallet_amount) > $cents($order->wallet_reserved)
            || $cents($order->refund_gross) !== $cents($attempt->gross) || $cents($order->refund_fee) !== $cents($attempt->fee)
            || $cents($order->refund_net) !== $cents($attempt->net)
            || \App\Models\TrialParticipation::where('order_id', $order->id)->exists()) {
            throw ValidationException::withMessages(['refund' => 'The pending provider refund no longer matches its original settlement. Reconciliation is required.']);
        }
    }

    public function claimLegacyAdminTeamRefund(\App\Models\TeamPaymentOrder $order, \App\Models\Event $event, User $actor, array $state): \App\Models\TeamPaymentOrder
    {
        abort_unless($actor->hasRole('super-user'), 403);
        return DB::transaction(function () use ($order, $event, $state) {
            $locked = \App\Models\TeamPaymentOrder::lockForUpdate()->findOrFail($order->id);
            abort_unless((int) $locked->event_id === (int) $event->id, 404);
            $this->assertNoTeamProviderClaim($locked);
            if ($locked->refund_status === 'completed' || round((float) $locked->total_amount, 2) !== (float) $state['refund_gross']) {
                throw ValidationException::withMessages(['refund' => 'The payment or refund changed. Refresh before continuing.']);
            }
            FinanceMutationScope::run('refund_state_write', fn () => $locked->forceFill($state + ['refund_status' => 'pending'])->save());
            return $locked;
        });
    }

    private function assertNoTeamProviderClaim(Model $entity): void
    {
        if ($entity instanceof \App\Models\TeamPaymentOrder
            && !FinanceMutationScope::allows('admin_team_provider_refund_finalize')
            && \App\Models\TeamProviderRefundAttempt::where('order_id', $entity->id)->exists()) {
            throw ValidationException::withMessages(['refund' => 'This order has an original-source PayFast refund claim. Use its pending refund review; another refund or wallet fallback is blocked.']);
        }
    }

    public function claimAdminRegistrationBankRefund(\App\Models\CategoryEventRegistration $entry, \App\Models\Event $event, User $actor, array $amounts): \App\Models\CategoryEventRegistration
    {
        abort_unless($actor->hasRole('super-user'), 403);
        return DB::transaction(function () use ($entry, $event, $actor, $amounts) {
            $locked = \App\Models\CategoryEventRegistration::lockForUpdate()->findOrFail($entry->id);
            abort_unless((int) $locked->categoryEvent?->event_id === (int) $event->id, 404);
            if (in_array($locked->refund_status, ['pending', 'completed'], true)) {
                throw ValidationException::withMessages(['refund' => 'This refund is already completed or pending reconciliation.']);
            }
            $payment = $locked->paymentInfo();
            $gross = round((float) ($payment['gross'] ?? 0) + (float) ($payment['wallet_paid'] ?? 0), 2);
            if ($gross <= 0 || $gross !== (float) $amounts['refund_gross']) {
                throw ValidationException::withMessages(['refund' => 'The paid amount changed. Refresh before refunding.']);
            }
            if ($locked->status !== 'withdrawn') {
                app(\App\Domain\Entries\Services\EntryService::class)->withdrawEntryAsAdmin($locked, $actor);
            }
            FinanceMutationScope::run('refund_state_write', fn () => $locked->update($amounts + ['refund_status' => 'pending']));
            return $locked;
        });
    }

    public function __construct(private LedgerService $ledgerService)
    {
    }

    public function executeWithdrawnTeamWalletRefund(
        \App\Models\TeamPaymentOrder $order,
        Wallet $wallet,
        float $amount,
        string $sourceType,
        int $sourceId,
        array $meta = [],
        array $statusOverrides = []
    ): Model {
        return DB::transaction(function () use ($order, $wallet, $amount, $sourceType, $sourceId, $meta, $statusOverrides) {
            $locked = \App\Models\TeamPaymentOrder::with('event')->lockForUpdate()->findOrFail($order->id);
            if ($locked->isRefundWaived()) {
                throw ValidationException::withMessages(['refund' => 'This refund was waived and cannot be processed.']);
            }
            if ($locked->refund_status === 'pending') {
                throw ValidationException::withMessages(['refund' => 'A refund is already pending. Review its existing method before processing.']);
            }
            if (! $locked->withdrawn_at || ! $locked->event
                || $locked->withdrawn_at->gt($locked->event->withdrawalCloseAt())) {
                throw ValidationException::withMessages(['refund' => 'A withdrawal within the refund deadline is required.']);
            }
            $amounts = app(TeamRefundCalculator::class)->calculate($locked);
            if (! $locked->pay_status || $locked->effective_player_id !== $order->effective_player_id
                || ! $locked->user->wallet()->whereKey($wallet->id)->exists()
                || $amounts['net'] <= 0 || round($amount, 2) !== $amounts['net']) {
                throw ValidationException::withMessages(['refund' => 'Payment coverage changed. Refresh before refunding the original payer.']);
            }
            return $this->executeWalletRefund($locked, $wallet, $amounts['net'], $sourceType, $sourceId, $meta, [
                'refund_method' => 'wallet',
                'refund_gross' => $amounts['gross'],
                'refund_fee' => $amounts['fee'],
                'refund_net' => $amounts['net'],
            ]);
        });
    }

    public function executeWalletRefund(
        Model $refundEntity,
        Wallet $wallet,
        float $amount,
        string $sourceType,
        int $sourceId,
        array $meta = [],
        array $statusOverrides = []
    ): Model {
        $entityClass = get_class($refundEntity);

        $transitioned = false;
        $completed = FinanceMutationScope::run('refund_state_write', function () use (
            $refundEntity,
            $entityClass,
            $wallet,
            $amount,
            $sourceType,
            $sourceId,
            $meta,
            $statusOverrides,
            &$transitioned
        ) {
            return DB::transaction(function () use (
                $refundEntity,
                $entityClass,
                $wallet,
                $amount,
                $sourceType,
                $sourceId,
                $meta,
                $statusOverrides,
                &$transitioned
            ) {
                /** @var Model $locked */
                $locked = $entityClass::query()->lockForUpdate()->findOrFail($refundEntity->getKey());

                $this->assertNoTeamProviderClaim($locked);

                if ($locked instanceof \App\Models\TeamPaymentOrder
                    && ($locked->effective_player_id !== $refundEntity->effective_player_id
                        || ($locked->beneficiary_player_id !== null
                            && (! $locked->withdrawn_at || ! $locked->user->wallet()->whereKey($wallet->id)->exists())))) {
                    throw ValidationException::withMessages(['refund' => 'Transferred payment refunds require the current withdrawn allocation and the original payer wallet.']);
                }

                if (($locked->refund_status ?? null) === 'completed') {
                    return $locked;
                }

                $transitioned = true;
                $this->ledgerService->appendWalletCredit($wallet, $amount, $sourceType, $sourceId, $meta);

                if ($this->supportsAttribute($locked, 'refund_method')) {
                    $locked->refund_method = $statusOverrides['refund_method'] ?? ($locked->refund_method ?? 'wallet');
                }
                if ($locked instanceof \App\Models\ClothingRefund && isset($statusOverrides['completed_by'])) {
                    $locked->completed_by = $statusOverrides['completed_by'];
                    $locked->completed_by_name = $statusOverrides['completed_by_name'] ?? null;
                }
                if ($this->supportsAttribute($locked, 'refund_status')) {
                    $locked->refund_status = 'completed';
                }
                if ($this->supportsAttribute($locked, 'refunded_at')) {
                    $locked->refunded_at = now();
                }

                if (array_key_exists('refund_gross', $statusOverrides)) {
                    $locked->refund_gross = $statusOverrides['refund_gross'];
                }
                if (array_key_exists('refund_fee', $statusOverrides)) {
                    $locked->refund_fee = $statusOverrides['refund_fee'];
                }
                if (array_key_exists('refund_net', $statusOverrides)) {
                    $locked->refund_net = $statusOverrides['refund_net'];
                }

                $locked->save();

                return $locked;
            });
        });

        if ($transitioned) {
            $payload = ['type' => 'wallet'] + $meta;
            $dispatchFn = function () use ($completed, $payload) {
                event(new RefundCompleted($completed, $payload));
            };
            app()->runningUnitTests()
                ? $dispatchFn()
                : DB::afterCommit($dispatchFn);
        }

        return $completed;
    }

    public function completeTrialManualRefund(\App\Models\CategoryEventRegistration $entry, User $actor, string $reference): Model
    {
        app(\App\Services\InterprovincialTrials\TrialProgrammeService::class)->authorize($entry->categoryEvent->event, $actor);
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/\-]{2,119}$/', $reference)) {
            throw ValidationException::withMessages(['reference' => 'Record the external refund reference without personal banking details.']);
        }
        return DB::transaction(function () use ($entry, $actor, $reference) {
            $locked = \App\Models\CategoryEventRegistration::with('categoryEvent.event')->lockForUpdate()->findOrFail($entry->id);
            app(\App\Services\InterprovincialTrials\TrialProgrammeService::class)->authorize($locked->categoryEvent->event, $actor);
            if ($locked->refund_status === 'completed') { return $locked; }
            if ($locked->status !== 'withdrawn' || !$locked->is_paid || $locked->refund_status !== 'pending' || $locked->refund_method !== 'bank'
                || (float) $locked->refund_net <= 0 || (float) $locked->refund_gross > round($locked->maxRefundableAmount(), 2)) {
                throw ValidationException::withMessages(['refund' => 'Only an eligible pending bank refund can be recorded as externally paid.']);
            }
            $completed = $this->executeBankRefund($locked);
            activity('refund')->performedOn($completed)->causedBy($actor)->withProperties(['reference' => $reference, 'amount' => $locked->refund_net])->log('Trials refund paid externally and recorded');
            return $completed;
        });
    }

    public function completeTrialTeamManualRefund(\App\Models\TrialParticipation $participation, User $actor, string $reference): Model
    {
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/\-]{2,119}$/', $reference)) {
            throw ValidationException::withMessages(['reference' => 'Record a safe external refund reference.']);
        }
        return DB::transaction(function () use ($participation, $actor, $reference) {
            app(\App\Services\InterprovincialTrials\TrialParticipationService::class)->lockForOrder((int) $participation->order_id, true);
            $order = \App\Models\TeamPaymentOrder::with('event')->lockForUpdate()->findOrFail($participation->order_id);
            app(\App\Services\InterprovincialTrials\TrialProgrammeService::class)->authorize($order->event, $actor);
            if ($order->refund_status === 'completed' && $order->refund_method === 'bank') return $order;
            $this->assertTrialTeamRefund($participation, $order, 'bank');
            $completed = $this->executeBankRefund($order);
            activity('refund')->performedOn($completed)->causedBy($actor)->withProperties(['external_reference' => $reference, 'gross' => $completed->refund_gross, 'fee' => $completed->refund_fee, 'net' => $completed->refund_net])->log('Regional participation manual refund reconciled');
            return $completed;
        });
    }

    public function executeTrialTeamPayfastRefund(\App\Models\TrialParticipation $participation, User $actor): Model
    {
        if (DB::transactionLevel() !== 0) {
            throw new \LogicException('Provider refunds require a committed request outside an enclosing transaction.');
        }
        $dispatch = false;
        $attempt = DB::transaction(function () use ($participation, $actor, &$dispatch) {
            app(\App\Services\InterprovincialTrials\TrialParticipationService::class)->lockForOrder((int) $participation->order_id, true);
            $order = \App\Models\TeamPaymentOrder::with('event')->lockForUpdate()->findOrFail($participation->order_id);
            $manager = app(\App\Services\InterprovincialTrials\TrialProgrammeService::class)->canManage($order->event, $actor);
            abort_unless($manager || (int) $order->user_id === (int) $actor->id, 403);
            if ($order->refund_status === 'completed' && $order->refund_method === 'payfast') return null;
            $this->assertTrialTeamRefund($participation, $order, 'payfast');
            abort_unless($order->payfast_paid && filled($order->payfast_pf_payment_id), 422);
            $attempt = \App\Models\TrialProviderRefundAttempt::where('order_id', $order->id)->lockForUpdate()->first();
            if ($attempt) {
                if ($attempt->status !== 'confirmed' || $attempt->pf_payment_id !== $order->payfast_pf_payment_id || (float) $attempt->amount !== round((float) $order->refund_net, 2)) {
                    throw ValidationException::withMessages(['refund' => 'A provider refund attempt requires reconciliation. Review PayFast history before another dispatch.']);
                }
                return $attempt;
            }
            $dispatch = true;
            return \App\Models\TrialProviderRefundAttempt::create(['order_id' => $order->id, 'pf_payment_id' => $order->payfast_pf_payment_id, 'amount' => $order->refund_net, 'requested_by' => $actor->id]);
        });
        if (!$attempt) { return $participation->order()->firstOrFail(); }
        if ($dispatch) {
            $order = $participation->order()->firstOrFail();
            try {
                $result = app(\App\Services\Payfast::class)->refundUsingAvailableMethod($attempt->pf_payment_id, (float) $attempt->amount, 'Regional team withdrawal refund', [
                    'account_holder' => $order->refund_account_name, 'bank_name' => $order->refund_bank_name,
                    'account_number' => $order->refund_account_number, 'branch_code' => $order->refund_branch_code, 'account_type' => $order->refund_account_type,
                ]);
            } catch (\Throwable $exception) {
                \App\Models\TrialProviderRefundAttempt::whereKey($attempt->id)->where('status', 'dispatching')->update(['status' => 'uncertain']);
                throw ValidationException::withMessages(['refund' => 'The provider outcome needs reconciliation. No second refund will be dispatched.']);
            }
            \App\Models\TrialProviderRefundAttempt::whereKey($attempt->id)->where('status', 'dispatching')->update(['status' => ($result['success'] ?? false) ? 'confirmed' : 'uncertain', 'confirmed_at' => ($result['success'] ?? false) ? now() : null]);
            $attempt->refresh();
            if ($attempt->status !== 'confirmed') {
                throw ValidationException::withMessages(['refund' => 'PayFast did not confirm the refund. Review provider history before further action.']);
            }
        }
        return DB::transaction(function () use ($participation, $actor, $attempt) {
            app(\App\Services\InterprovincialTrials\TrialParticipationService::class)->lockForOrder((int) $attempt->order_id, true);
            $order = \App\Models\TeamPaymentOrder::with('event')->lockForUpdate()->findOrFail($attempt->order_id);
            if ($order->refund_status === 'completed' && $order->refund_method === 'payfast') { return $order; }
            $this->assertTrialTeamRefund($participation, $order, 'payfast');
            $completed = $this->executeSplitRefund($order, null, 0, 'trial_participation_refund', $order->id, [], [
                'refund_method' => 'payfast', 'refund_gross' => $order->refund_gross, 'refund_fee' => $order->refund_fee, 'refund_net' => $order->refund_net,
            ]);
            activity('refund')->performedOn($completed)->causedBy($actor)->withProperties(['attempt_id' => $attempt->id, 'pf_payment_id' => $order->payfast_pf_payment_id, 'gross' => $order->refund_gross, 'fee' => $order->refund_fee, 'net' => $order->refund_net])->log('Regional participation PayFast refund confirmed');
            return $completed;
        });
    }

    public function recoverTrialTeamPayfastRefund(\App\Models\TrialParticipation $participation, User $actor, ?array $evidence = null): Model
    {
        if (DB::transactionLevel() !== 0) throw new \LogicException('Refund recovery requires a committed request.');
        DB::transaction(function () use ($participation, $actor, $evidence) {
            app(\App\Services\InterprovincialTrials\TrialParticipationService::class)->lockForOrder((int)$participation->order_id, true);
            $order=\App\Models\TeamPaymentOrder::with('event')->lockForUpdate()->findOrFail($participation->order_id);
            app(\App\Services\InterprovincialTrials\TrialProgrammeService::class)->authorize($order->event,$actor);
            $attempt=\App\Models\TrialProviderRefundAttempt::where('order_id',$order->id)->lockForUpdate()->firstOrFail();
            abort_unless($attempt->pf_payment_id===$order->payfast_pf_payment_id && round((float)$attempt->amount,2)===round((float)$order->refund_net,2),422);
            if ($order->refund_status==='completed') { abort_unless($attempt->status==='confirmed' && $order->refund_method==='payfast',422);return; }
            $this->assertTrialTeamRefund($participation,$order,'payfast');
            if ($evidence===null) { abort_unless($attempt->status==='confirmed',422,'Provider confirmation is required before local retry.');return; }
            abort_unless(in_array($attempt->status,['dispatching','uncertain'],true),422);
            $validated=validator($evidence,[
                'pf_payment_id'=>'required|string|max:120','amount'=>'required|decimal:0,2|min:0.01',
                'reference'=>['required','regex:/^[A-Za-z0-9][A-Za-z0-9._:\/\-]{2,119}$/'],
                'reason'=>'required|string|min:10|max:1000','confirmed_at'=>'required|date|before_or_equal:now','externally_confirmed'=>'required|accepted',
            ])->validate();
            abort_unless($validated['pf_payment_id']===$attempt->pf_payment_id && round((float)$validated['amount'],2)===round((float)$attempt->amount,2),422,'Provider evidence must match the original payment and exact refund net.');
            $attempt->update(['status'=>'confirmed','confirmed_at'=>$validated['confirmed_at']]);
            activity('refund')->performedOn($attempt)->causedBy($actor)->withProperties([
                'order_id'=>$order->id,'pf_payment_id'=>$attempt->pf_payment_id,'refund_net'=>$attempt->amount,
                'provider_reference'=>$validated['reference'],'reason'=>$validated['reason'],'confirmed_at'=>$validated['confirmed_at'],
            ])->log('Regional PayFast refund externally confirmed for local recovery');
        });
        // The persisted confirmed attempt makes the canonical executor skip provider dispatch.
        return $this->executeTrialTeamPayfastRefund($participation,$actor);
    }

    private function assertTrialTeamRefund(\App\Models\TrialParticipation $participation, \App\Models\TeamPaymentOrder $order, string $method): void
    {
        $participation = \App\Models\TrialParticipation::whereKey($participation->id)->firstOrFail();
        $receipt = \App\Models\TrialParticipationReceipt::where('order_id', $order->id)->first();
        $gross = round((float) ($receipt?->amount ?? ($order->payfast_paid && $order->payfast_pf_payment_id ? $order->total_amount : 0)), 2);
        $fee = \App\Models\SiteSetting::calculateWithdrawalFee($gross);
        if ((int) $participation->order_id !== (int) $order->id || (int) $participation->event_id !== (int) $order->event_id || (int) $participation->player_id !== (int) $order->player_id
            || ! $order->event->isInterprovincialTrials() || ! $order->withdrawn_at || ! $order->pay_status || $order->refund_status !== 'pending' || $order->refund_method !== $method
            || $gross <= 0 || $gross !== round((float) $order->total_amount, 2) || $gross !== round((float) $order->refund_gross, 2)
            || $fee !== round((float) $order->refund_fee, 2) || round($gross - $fee, 2) !== round((float) $order->refund_net, 2)) {
            throw ValidationException::withMessages(['refund' => 'Only an exact pending refund against the original paid, withdrawn participation may be completed.']);
        }
    }

    public function executeBankRefund(Model $refundEntity, array $statusOverrides = []): Model
    {
        $entityClass = get_class($refundEntity);

        $transitioned = false;
        $completed = FinanceMutationScope::run('refund_state_write', function () use ($refundEntity, $entityClass, $statusOverrides, &$transitioned) {
            return DB::transaction(function () use ($refundEntity, $entityClass, $statusOverrides, &$transitioned) {
                /** @var Model $locked */
                $locked = $entityClass::query()->lockForUpdate()->findOrFail($refundEntity->getKey());
                $this->assertNoTeamProviderClaim($locked);

                if (($locked->refund_status ?? null) === 'completed') {
                    return $locked;
                }

                $transitioned = true;
                if ($this->supportsAttribute($locked, 'refund_method')) {
                    $locked->refund_method = $statusOverrides['refund_method'] ?? ($locked->refund_method ?? 'bank');
                }
                if ($locked instanceof \App\Models\ClothingRefund && isset($statusOverrides['completed_by'])) {
                    $locked->completed_by = $statusOverrides['completed_by'];
                    $locked->completed_by_name = $statusOverrides['completed_by_name'] ?? null;
                }
                if ($this->supportsAttribute($locked, 'refund_status')) {
                    $locked->refund_status = 'completed';
                }
                if ($this->supportsAttribute($locked, 'refunded_at')) {
                    $locked->refunded_at = now();
                }

                if (array_key_exists('refund_gross', $statusOverrides)) {
                    $locked->refund_gross = $statusOverrides['refund_gross'];
                }
                if (array_key_exists('refund_fee', $statusOverrides)) {
                    $locked->refund_fee = $statusOverrides['refund_fee'];
                }
                if (array_key_exists('refund_net', $statusOverrides)) {
                    $locked->refund_net = $statusOverrides['refund_net'];
                }

                $locked->save();

                return $locked;
            });
        });

        if ($transitioned) {
            $dispatchFn = function () use ($completed) {
                event(new RefundCompleted($completed, ['type' => 'bank']));
            };
            app()->runningUnitTests()
                ? $dispatchFn()
                : DB::afterCommit($dispatchFn);
        }

        return $completed;
    }

    public function recordTeamCashRefund(\App\Models\TeamPaymentOrder $order, User $actor, string $reference, string $reason, string $disposition = 'remove', bool $deadlineOverride = false, string $overrideReason = ''): \App\Models\TeamPaymentOrder
    {
        abort_unless($actor->hasRole('super-user'), 403);
        return FinanceMutationScope::run('refund_state_write', function () use ($order, $actor, $reference, $reason, $disposition, $deadlineOverride, $overrideReason) {
            return DB::transaction(function () use ($order, $actor, $reference, $reason, $disposition, $deadlineOverride, $overrideReason) {
                $locked = \App\Models\TeamPaymentOrder::with('event')->lockForUpdate()->findOrFail($order->id);
                $fail = fn (string $message) => throw ValidationException::withMessages(['cash_refund' => $message]);
                if ($locked->effective_player_id !== $order->effective_player_id || trim($reference) === '' || trim($reason) === '') {
                    $fail('The payment beneficiary changed or the cash refund reference and reason are missing.');
                }
                if ($locked->refund_status === 'completed') {
                    $audit = \Spatie\Activitylog\Models\Activity::where('subject_type', get_class($locked))->where('subject_id', $locked->id)
                        ->where('description', 'super user recorded team cash refund to original payer')->latest('id')->first();
                    if ($locked->refund_method === 'cash' && $audit && (int) $audit->causer_id === (int) $actor->id
                        && data_get($audit->properties, 'reference') === trim($reference) && data_get($audit->properties, 'reason') === trim($reason)
                        && data_get($audit->properties, 'disposition') === $disposition
                        && (bool) data_get($audit->properties, 'deadline_override') === $deadlineOverride
                        && data_get($audit->properties, 'override_reason', '') === trim($overrideReason)
                        && (int) data_get($audit->properties, 'payer_id') === (int) $locked->user_id
                        && (int) data_get($audit->properties, 'beneficiary_player_id') === $locked->effective_player_id
                        && (float) data_get($audit->properties, 'gross') === (float) $locked->refund_gross
                        && (float) data_get($audit->properties, 'fee') === (float) $locked->refund_fee
                        && (float) data_get($audit->properties, 'net') === (float) $locked->refund_net) return $locked;
                    $fail('This refund was already completed through another correction.');
                }
                $amounts = app(TeamRefundCalculator::class)->calculate($locked);
                app(\App\Domain\Payments\Services\TeamPaymentService::class)->assertVerifiedTeamPayfastSettlement($locked);
                $cashRequest = \Spatie\Activitylog\Models\Activity::where('subject_type', get_class($locked))->where('subject_id', $locked->id)
                    ->where('description', 'super user requested recorded cash refund for selected player')->latest('id')->first();
                $requestEligible = $locked->event && $cashRequest && (int) $cashRequest->causer_id === (int) $actor->id
                    && data_get($cashRequest->properties, 'reference') === trim($reference) && data_get($cashRequest->properties, 'reason') === trim($reason)
                    && data_get($cashRequest->properties, 'disposition') === $disposition
                    && data_get($cashRequest->properties, 'refund_fingerprint') === \App\Domain\Payments\Services\TeamPaymentService::cashRefundFingerprint($locked, $amounts)
                    && (int) data_get($cashRequest->properties, 'payer_id') === (int) $locked->user_id
                    && (int) data_get($cashRequest->properties, 'beneficiary_player_id') === $locked->effective_player_id
                    && (bool) data_get($cashRequest->properties, 'deadline_override') === $deadlineOverride
                    && data_get($cashRequest->properties, 'override_reason', '') === trim($overrideReason)
                    && (\Carbon\CarbonImmutable::parse(data_get($cashRequest->properties, 'operation_at'))->lte($locked->event->withdrawalCloseAt())
                        || ($deadlineOverride && trim($overrideReason) !== '' && (int) data_get($cashRequest->properties, 'override_actor_id') === (int) $actor->id));
                if (! in_array($disposition, ['remove', 'keep'], true) || $locked->refund_status !== 'pending' || $locked->refund_method !== 'cash'
                    || ! $requestEligible || ($disposition === 'remove' && ! $locked->withdrawn_at)
                    || ($disposition === 'keep' && $locked->withdrawn_at)
                    || ! $locked->pay_status || ! $locked->payfast_paid || $locked->collection_status === 'paid_privately'
                    || ! $locked->user_id || $locked->refund_waived_at || $locked->refunded_at
                    || (float) $locked->refund_gross !== $amounts['gross'] || (float) $locked->refund_fee !== $amounts['fee']
                    || (float) $locked->refund_net !== $amounts['net'] || $amounts['net'] <= 0
                    || \App\Models\TrialParticipation::where('order_id', $locked->id)->exists()
                    || DB::table('trial_provider_refund_attempts')->where('order_id', $locked->id)->exists()) {
                    $fail('Only an exact eligible pending cash refund can be recorded.');
                }
                $locked->forceFill(['refund_status' => 'completed', 'refunded_at' => now()])->save();
                activity('refund')->performedOn($locked)->causedBy($actor)->withProperties([
                    'event_id' => $locked->event_id, 'team_id' => $locked->team_id, 'payer_id' => $locked->user_id,
                    'original_player_id' => $locked->player_id, 'beneficiary_player_id' => $locked->effective_player_id,
                    'gross' => $amounts['gross'], 'fee' => $amounts['fee'], 'net' => $amounts['net'],
                    'reference' => trim($reference), 'reason' => trim($reason), 'method' => 'cash', 'disposition' => $disposition,
                    'deadline' => data_get($cashRequest->properties, 'deadline'), 'operation_at' => data_get($cashRequest->properties, 'operation_at'),
                    'deadline_override' => $deadlineOverride, 'override_reason' => trim($overrideReason), 'override_actor_id' => $deadlineOverride ? $actor->id : null,
                    'cash_already_paid_to_original_payer' => true, 'provider_refund_dispatched' => false, 'wallet_credited' => false,
                ])->log('super user recorded team cash refund to original payer');
                return $locked;
            });
        });
    }

    /**
     * Record a refund that PayFast has already completed outside this app.
     *
     * This method never contacts PayFast or moves money. It only reconciles an
     * exact, independently verified reversal against the paid withdrawn entry.
     */
    public function recordExternalPayfastRefund(
        Model $refundEntity,
        string $pfPaymentId,
        float $grossAmount,
        string $refundedAt,
        ?User $actor = null,
        array $evidence = []
    ): Model {
        $entityClass = get_class($refundEntity);
        $expectedGross = round($grossAmount, 2);
        $effectiveAt = CarbonImmutable::parse($refundedAt);
        $transitioned = false;

        if ($expectedGross <= 0) {
            throw ValidationException::withMessages(['refund' => 'External refund amount must be positive.']);
        }

        $completed = FinanceMutationScope::run('refund_state_write', function () use (
            $entityClass,
            $refundEntity,
            $pfPaymentId,
            $expectedGross,
            $effectiveAt,
            &$transitioned
        ) {
            return DB::transaction(function () use (
                $entityClass,
                $refundEntity,
                $pfPaymentId,
                $expectedGross,
                $effectiveAt,
                &$transitioned
            ) {
                /** @var Model $locked */
                $locked = $entityClass::query()->lockForUpdate()->findOrFail($refundEntity->getKey());
                $this->assertNoTeamProviderClaim($locked);

                if (($locked->refund_status ?? null) === 'completed') {
                    $sameRefund = ($locked->refund_method ?? null) === 'payfast'
                        && round((float) ($locked->refund_gross ?? 0), 2) === $expectedGross
                        && (string) ($locked->pf_transaction_id ?? '') === $pfPaymentId;
                    if (! $sameRefund) {
                        throw ValidationException::withMessages(['refund' => 'A different completed refund is already recorded.']);
                    }

                    return $locked;
                }

                if (($locked->status ?? null) !== 'withdrawn'
                    || (int) ($locked->payment_status_id ?? 0) !== 1
                    || (string) ($locked->pf_transaction_id ?? '') !== $pfPaymentId) {
                    throw ValidationException::withMessages([
                        'refund' => 'External PayFast evidence does not match a paid withdrawn entry.',
                    ]);
                }

                $transitioned = true;
                $locked->refund_method = 'payfast';
                $locked->refund_status = 'completed';
                $locked->refund_gross = $expectedGross;
                $locked->refund_fee = 0;
                $locked->refund_net = $expectedGross;
                $locked->refunded_at = $effectiveAt;
                $locked->save();

                return $locked;
            });
        });

        if ($transitioned) {
            $payload = ['type' => 'external_payfast', 'pf_payment_id' => $pfPaymentId] + $evidence;
            $activity = activity('refund')->performedOn($completed);
            if ($actor) {
                $activity->causedBy($actor);
            }
            $activity->withProperties($payload + [
                    'refund_gross' => $expectedGross,
                    'refunded_at' => $effectiveAt->toDateTimeString(),
                ])
                ->log('Reconciled externally completed PayFast refund');
            $dispatchFn = fn () => event(new RefundCompleted($completed, $payload));
            app()->runningUnitTests() ? $dispatchFn() : DB::afterCommit($dispatchFn);
        }

        return $completed;
    }

    /**
     * Close a pending refund without moving money.
     *
     * A waiver is deliberately distinct from a completed refund: refunded_at
     * remains null and no RefundCompleted event or ledger entry is created.
     */
    public function waiveRefund(Model $refundEntity, int $waivedBy, string $reason): Model
    {
        $entityClass = get_class($refundEntity);

        return FinanceMutationScope::run('refund_state_write', function () use ($entityClass, $refundEntity, $waivedBy, $reason) {
            return DB::transaction(function () use ($entityClass, $refundEntity, $waivedBy, $reason) {
                /** @var Model $locked */
                $locked = $entityClass::query()->lockForUpdate()->findOrFail($refundEntity->getKey());
                $this->assertNoTeamProviderClaim($locked);

                if (($locked->refund_status ?? null) !== 'pending') {
                    throw ValidationException::withMessages([
                        'refund' => 'Only a pending refund can be waived.',
                    ]);
                }

                $locked->fill([
                    'refund_status' => 'waived',
                    'refund_waived_at' => now(),
                    'refund_waived_by' => $waivedBy,
                    'refund_waiver_reason' => trim($reason),
                    'refunded_at' => null,
                ]);
                $locked->save();

                return $locked;
            });
        });
    }

    public function executeSplitRefund(
        Model $refundEntity,
        ?Wallet $wallet,
        float $walletAmount,
        string $sourceType,
        int $sourceId,
        array $meta = [],
        array $statusOverrides = []
    ): Model {
        $entityClass = get_class($refundEntity);

        return FinanceMutationScope::run('refund_state_write', function () use ($entityClass, $refundEntity, $wallet, $walletAmount, $sourceType, $sourceId, $meta, $statusOverrides) {
            return DB::transaction(function () use ($entityClass, $refundEntity, $wallet, $walletAmount, $sourceType, $sourceId, $meta, $statusOverrides) {
                $locked = $entityClass::query()->lockForUpdate()->findOrFail($refundEntity->getKey());
                $this->assertNoTeamProviderClaim($locked);
                if (($locked->refund_status ?? null) === 'completed') {
                    return $locked;
                }

                if ($walletAmount > 0) {
                    if (! $wallet) {
                        throw new \RuntimeException('Wallet not found for split refund.');
                    }
                    $this->ledgerService->appendWalletCredit($wallet, $walletAmount, $sourceType, $sourceId, $meta);
                }

                $locked->fill($statusOverrides + [
                    'refund_method' => 'payfast',
                    'refund_status' => 'completed',
                    'refunded_at' => now(),
                ]);
                $locked->refund_status = 'completed';
                $locked->refunded_at = now();
                $locked->save();

                return $locked;
            });
        });
    }
    private function supportsAttribute(Model $model, string $attribute): bool
    {
        return array_key_exists($attribute, $model->getAttributes())
            || in_array($attribute, $model->getFillable(), true);
    }
}
