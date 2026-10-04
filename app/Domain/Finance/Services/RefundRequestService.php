<?php

namespace App\Domain\Finance\Services;

use App\Exceptions\RefundAlreadyProcessedException;
use App\Models\CategoryEventRegistration;
use App\Models\TeamPaymentOrder;
use App\Models\User;
use App\Support\FinanceMutationScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundRequestService
{
    public function requestTrialTeamRefund(\App\Models\TrialParticipation $participation, User $actor, string $method, array $bankDetails = []): TeamPaymentOrder
    {
        abort_unless(in_array($method, ['bank', 'payfast'], true), 422);
        return DB::transaction(function () use ($participation, $actor, $method, $bankDetails) {
            app(\App\Services\InterprovincialTrials\TrialParticipationService::class)->lockForOrder((int) $participation->order_id, true);
            $order = TeamPaymentOrder::with('event')->lockForUpdate()->findOrFail($participation->order_id);
            $participation = \App\Models\TrialParticipation::lockForUpdate()->findOrFail($participation->id);
            $manager = app(\App\Services\InterprovincialTrials\TrialProgrammeService::class)->canManage($order->event, $actor);
            abort_unless($order->event->isInterprovincialTrials() && (int) $order->event_id === (int) $participation->event_id && (int) $order->player_id === (int) $participation->player_id
                && (int) $participation->order_id === (int) $order->id && (int) $participation->payer_id === (int) $order->user_id && ($manager || (int) $order->user_id === (int) $actor->id), 403);
            if (in_array($order->refund_status, ['pending', 'completed'], true)) return $order;
            $deadline = \App\Models\TrialProgramme::where('event_id', $order->event_id)->first()?->withdrawal_deadline ?? $order->event->withdrawalCloseAt();
            if (! $order->withdrawn_at || $order->withdrawn_at->gt($deadline) || ! $order->pay_status || $order->hasRefund() || $order->wallet_debited) {
                throw ValidationException::withMessages(['refund' => 'Withdraw a paid participation before its deadline to request a refund.']);
            }
            $receipt = \App\Models\TrialParticipationReceipt::where('order_id', $order->id)->first();
            $gross = round((float) ($receipt?->amount ?? ($order->payfast_paid && $order->payfast_pf_payment_id ? $order->total_amount : 0)), 2);
            if ($gross <= 0 || $gross !== round((float) $order->total_amount, 2) || ($method === 'payfast' && (! $order->payfast_paid || ! $order->payfast_pf_payment_id))) {
                throw ValidationException::withMessages(['refund' => 'No matching original payment is available for this refund method.']);
            }
            $fee = \App\Models\SiteSetting::calculateWithdrawalFee($gross);
            $attributes = ['refund_method' => $method, 'refund_status' => 'pending', 'refund_gross' => $gross, 'refund_fee' => $fee, 'refund_net' => round($gross - $fee, 2)];
            foreach (['refund_account_name', 'refund_bank_name', 'refund_account_number', 'refund_branch_code', 'refund_account_type'] as $field) {
                if (array_key_exists($field, $bankDetails)) $attributes[$field] = $bankDetails[$field];
            }
            FinanceMutationScope::run('refund_state_write', fn () => $order->fill($attributes)->save());
            activity('refund')->performedOn($order)->causedBy($actor)->withProperties(['gross' => $gross, 'fee' => $fee, 'net' => $attributes['refund_net'], 'method' => $method])->log('Regional participation refund requested');
            return $order->fresh();
        });
    }
    public function requestTrialManualRefund(CategoryEventRegistration $registration, User $actor): CategoryEventRegistration
    {
        $event = $registration->categoryEvent->event;
        app(\App\Services\InterprovincialTrials\TrialProgrammeService::class)->authorize($event, $actor);
        return FinanceMutationScope::run('refund_state_write', function () use ($registration, $actor) {
            return DB::transaction(function () use ($registration, $actor) {
                $locked = CategoryEventRegistration::with('categoryEvent.event')->lockForUpdate()->findOrFail($registration->id);
                app(\App\Services\InterprovincialTrials\TrialProgrammeService::class)->authorize($locked->categoryEvent->event, $actor);
                if (in_array($locked->refund_status, ['pending', 'completed'], true)) { return $locked; }
                $this->assertRegistrationRefundEligible($locked, $actor, true);
                $paid = $locked->paymentInfo();
                $gross = round((float) ($paid['total_paid'] ?? 0), 2);
                if ($gross <= 0 || $gross > round($locked->maxRefundableAmount(), 2)) {
                    throw ValidationException::withMessages(['refund' => 'No exact reconciled paid amount is available.']);
                }
                $wallet = round((float) ($paid['wallet_paid'] ?? 0), 2);
                $fee = \App\Models\SiteSetting::calculateWithdrawalFee(round($gross - $wallet, 2));
                $locked->fill(['refund_method' => 'bank', 'refund_status' => 'pending', 'refund_gross' => $gross, 'refund_fee' => $fee, 'refund_net' => round($gross - $fee, 2)])->save();
                activity('refund')->performedOn($locked)->causedBy($actor)->withProperties(['gross' => $gross, 'fee' => $fee, 'net' => round($gross - $fee, 2)])->log('Scoped Trials manual refund requested');
                return $locked;
            });
        });
    }
    public function requestRegistrationRefund(CategoryEventRegistration $registration, array $attributes, ?User $actor = null): CategoryEventRegistration
    {
        return FinanceMutationScope::run('refund_state_write', function () use ($registration, $attributes, $actor) {
            return DB::transaction(function () use ($registration, $attributes, $actor) {
                $locked = CategoryEventRegistration::query()
                    ->with('categoryEvent.event')
                    ->lockForUpdate()
                    ->findOrFail($registration->id);

                if (($attributes['refund_status'] ?? null) === 'pending'
                    && in_array($locked->refund_status, ['pending', 'completed'], true)) {
                    throw new RefundAlreadyProcessedException('Refund already requested or completed.');
                }

                if (($attributes['refund_status'] ?? null) === 'pending') {
                    $this->assertRegistrationRefundEligible($locked, $actor);
                }

                $locked->fill($attributes);
                $locked->save();

                return $locked;
            });
        });
    }

    public function requestTeamRefund(TeamPaymentOrder $order, array $attributes, ?User $actor = null): TeamPaymentOrder
    {
        return FinanceMutationScope::run('refund_state_write', function () use ($order, $attributes, $actor) {
            return DB::transaction(function () use ($order, $attributes, $actor) {
                $locked = TeamPaymentOrder::query()
                    ->with('event')
                    ->lockForUpdate()
                    ->findOrFail($order->id);
                if ($locked->effective_player_id !== $order->effective_player_id) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['payment' => 'Payment coverage changed. Refresh before requesting a refund.']);
                }

                if (($attributes['refund_status'] ?? null) === 'pending'
                    && in_array($locked->refund_status, ['pending', 'completed'], true)) {
                    throw new RefundAlreadyProcessedException('Refund already requested or completed.');
                }

                if (($attributes['refund_status'] ?? null) === 'pending') {
                    $this->assertTeamRefundEligible($locked, $actor);
                }

                $locked->fill($attributes);
                $locked->save();

                return $locked;
            });
        });
    }

    public function requestRetainedTeamCashRefund(TeamPaymentOrder $order, \App\Models\TeamSelectionInvitation $invitation, User $actor, string $reference, string $reason): TeamPaymentOrder
    {
        abort_unless($actor->hasRole('super-user'), 403);
        return FinanceMutationScope::run('refund_state_write', function () use ($order, $invitation, $actor, $reference, $reason) {
            return DB::transaction(function () use ($order, $invitation, $actor, $reference, $reason) {
                $locked = TeamPaymentOrder::with('event')->lockForUpdate()->findOrFail($order->id);
                $selected = \App\Models\TeamSelectionInvitation::lockForUpdate()->findOrFail($invitation->id);
                if ($locked->withdrawn_at || $locked->hasRefund() || ! $locked->event || now()->gt($locked->event->withdrawalCloseAt())
                    || $selected->status !== \App\Models\TeamSelectionInvitation::PAID_CONFIRMED || ! $selected->roster_rank
                    || (int) $selected->order_id !== (int) $locked->id || (int) $selected->event_id !== (int) $locked->event_id
                    || (int) $selected->team_id !== (int) $locked->team_id || (int) $selected->player_id !== $locked->effective_player_id
                    || trim($reference) === '' || trim($reason) === '') {
                    throw ValidationException::withMessages(['cash_refund' => 'Only an eligible current selected paid player can be retained after a recorded cash refund.']);
                }
                app(\App\Domain\Payments\Services\TeamPaymentService::class)->assertVerifiedTeamPayfastSettlement($locked);
                $amounts = app(\App\Domain\Refunds\Services\TeamRefundCalculator::class)->calculate($locked);
                if ($amounts['net'] <= 0) throw ValidationException::withMessages(['cash_refund' => 'No policy refund amount is available.']);
                $locked->fill(['refund_method' => 'cash', 'refund_status' => 'pending', 'refund_gross' => $amounts['gross'], 'refund_fee' => $amounts['fee'], 'refund_net' => $amounts['net']])->save();
                activity('refund')->performedOn($locked)->causedBy($actor)->withProperties([
                    'invitation_id' => $selected->id, 'roster_rank' => $selected->roster_rank, 'payer_id' => $locked->user_id,
                    'beneficiary_player_id' => $locked->effective_player_id, 'operation_at' => now()->toIso8601String(),
                    'reference' => trim($reference), 'reason' => trim($reason), 'disposition' => 'keep',
                ])->log('super user requested cash refund while retaining selected player unpaid');
                return $locked;
            });
        });
    }

    private function assertRegistrationRefundEligible(CategoryEventRegistration $registration, ?User $actor, bool $scopedTrialOperator = false): void
    {
        $event = $registration->categoryEvent?->event;
        $isSuperUser = $actor && method_exists($actor, 'hasRole') && $actor->hasRole('super-user');

        if (! $actor || ((int) $registration->user_id !== (int) $actor->id && ! $isSuperUser && ! $scopedTrialOperator)) {
            throw ValidationException::withMessages(['refund' => 'You may only request a refund for your own payment.']);
        }
        if ($registration->status !== 'withdrawn') {
            throw ValidationException::withMessages(['refund' => 'Registration must be withdrawn before requesting a refund.']);
        }
        if (! $registration->is_paid) {
            throw ValidationException::withMessages(['refund' => 'Only a paid registration can be refunded.']);
        }
        if ($registration->isAdminEntry()) {
            throw ValidationException::withMessages([
                'refund' => 'Admin-created entries have no reconciled payment to refund.',
            ]);
        }
        if (! $event || ! $registration->withdrawn_at || $registration->withdrawn_at->gt($event->withdrawalCloseAt())) {
            throw ValidationException::withMessages(['refund' => 'The withdrawal deadline passed before this entry was withdrawn.']);
        }
    }

    private function assertTeamRefundEligible(TeamPaymentOrder $order, ?User $actor): void
    {
        $isSuperUser = $actor && method_exists($actor, 'hasRole') && $actor->hasRole('super-user');

        if (! $actor || ((int) $order->user_id !== (int) $actor->id && ! $isSuperUser)) {
            throw ValidationException::withMessages(['refund' => 'Only the payer may request this refund.']);
        }
        if (! $order->event || ! $order->withdrawn_at) {
            throw ValidationException::withMessages(['refund' => 'Player must be withdrawn before requesting a refund.']);
        }
        if ($order->withdrawn_at->gt($order->event->withdrawalCloseAt())) {
            throw ValidationException::withMessages(['refund' => 'The withdrawal deadline passed before this player was withdrawn.']);
        }
        if ((int) $order->pay_status !== 1 && ! $order->payfast_paid && ! $order->wallet_debited) {
            throw ValidationException::withMessages(['refund' => 'No paid amount was found for this order.']);
        }
    }
}
