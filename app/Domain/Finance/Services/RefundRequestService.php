<?php

namespace App\Domain\Finance\Services;

use App\Exceptions\RefundAlreadyProcessedException;
use App\Models\CategoryEventRegistration;
use App\Models\TeamPaymentOrder;
use App\Support\FinanceMutationScope;
use Illuminate\Support\Facades\DB;

class RefundRequestService
{
    public function requestRegistrationRefund(CategoryEventRegistration $registration, array $attributes): CategoryEventRegistration
    {
        return FinanceMutationScope::run('refund_state_write', function () use ($registration, $attributes) {
            return DB::transaction(function () use ($registration, $attributes) {
                $locked = CategoryEventRegistration::query()
                    ->lockForUpdate()
                    ->findOrFail($registration->id);

                if (($attributes['refund_status'] ?? null) === 'pending'
                    && in_array($locked->refund_status, ['pending', 'completed'], true)) {
                    throw new RefundAlreadyProcessedException('Refund already requested or completed.');
                }

                $locked->fill($attributes);
                $locked->save();

                return $locked;
            });
        });
    }

    public function requestTeamRefund(TeamPaymentOrder $order, array $attributes): TeamPaymentOrder
    {
        return FinanceMutationScope::run('refund_state_write', function () use ($order, $attributes) {
            return DB::transaction(function () use ($order, $attributes) {
                $locked = TeamPaymentOrder::query()
                    ->lockForUpdate()
                    ->findOrFail($order->id);

                if (($attributes['refund_status'] ?? null) === 'pending'
                    && in_array($locked->refund_status, ['pending', 'completed'], true)) {
                    throw new RefundAlreadyProcessedException('Refund already requested or completed.');
                }

                $locked->fill($attributes);
                $locked->save();

                return $locked;
            });
        });
    }
}
