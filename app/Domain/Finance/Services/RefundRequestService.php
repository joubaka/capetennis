<?php

namespace App\Domain\Finance\Services;

use App\Exceptions\RefundAlreadyProcessedException;
use App\Models\CategoryEventRegistration;
use App\Models\TeamPaymentOrder;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Support\FinanceMutationScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundRequestService
{
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

    private function assertRegistrationRefundEligible(CategoryEventRegistration $registration, ?User $actor): void
    {
        $event = $registration->categoryEvent?->event;
        $isSuperUser = $actor && method_exists($actor, 'hasRole') && $actor->hasRole('super-user');

        if (! $actor || ((int) $registration->user_id !== (int) $actor->id && ! $isSuperUser)) {
            throw ValidationException::withMessages(['refund' => 'You may only request a refund for your own payment.']);
        }
        if ($registration->status !== 'withdrawn') {
            throw ValidationException::withMessages(['refund' => 'Registration must be withdrawn before requesting a refund.']);
        }
        if (! $registration->is_paid) {
            throw ValidationException::withMessages(['refund' => 'Only a paid registration can be refunded.']);
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
        if (TeamPlayer::query()
            ->where('team_id', $order->team_id)
            ->where('player_id', $order->player_id)
            ->where('pay_status', 1)
            ->exists()) {
            throw ValidationException::withMessages(['refund' => 'Player must be withdrawn before requesting a refund.']);
        }
    }
}
