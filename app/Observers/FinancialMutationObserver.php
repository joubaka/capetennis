<?php

namespace App\Observers;

use App\Support\FinanceMutationScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class FinancialMutationObserver
{
    /**
     * @var array<class-string<Model>, array<int, string>>
     */
    protected array $financialAttributes = [
        \App\Models\RegistrationOrder::class => [
            'wallet_reserved',
            'wallet_debited',
            'payfast_paid',
            'payfast_pf_payment_id',
            'payfast_amount_due',
            'pay_status',
        ],
        \App\Models\TeamPaymentOrder::class => [
            'wallet_reserved',
            'wallet_debited',
            'payfast_paid',
            'payfast_pf_payment_id',
            'payfast_amount_due',
            'pay_status',
            'refund_method',
            'refund_status',
            'refund_gross',
            'refund_fee',
            'refund_net',
            'refunded_at',
        ],
        \App\Models\CategoryEventRegistration::class => [
            'payment_status_id',
            'pf_transaction_id',
            'refund_method',
            'refund_status',
            'refund_gross',
            'refund_fee',
            'refund_net',
            'refunded_at',
        ],
        \App\Models\ClothingOrder::class => [
            'pay_status',
            'pf_id',
            'paid_at',
            'amount_paid',
        ],
        \App\Models\Order::class => [
            'pay_status',
            'pf_payment_id',
        ],
        \App\Models\TeamPlayer::class => [
            'pay_status',
            'player_id',
        ],
    ];

    public function saving(Model $model): void
    {
        $attributes = $this->financialAttributes[$model::class] ?? [];
        if ($attributes === []) {
            return;
        }

        $dirty = array_values(array_intersect(array_keys($model->getDirty()), $attributes));
        if ($dirty === []) {
            return;
        }

        if (FinanceMutationScope::allows(
            'payment_state_write',
            'refund_state_write',
            'registration_payment_state_write',
            'team_payment_state_write',
            'simple_payment_state_write'
        )) {
            return;
        }

        Log::warning('FINANCE LOCKDOWN: direct financial state mutation detected', [
            'model' => $model::class,
            'id' => $model->getKey(),
            'dirty' => $dirty,
            'scopes' => FinanceMutationScope::current(),
        ]);
    }
}
