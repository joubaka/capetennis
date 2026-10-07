<?php

namespace App\Services\Performance;

use App\Models\CategoryEventRegistration;
use App\Models\TeamPaymentOrder;

/** Shared only for the current request; never persist a financial badge count. */
final class PendingBankRefundCount
{
    private ?int $count = null;

    public function count(): int
    {
        return $this->count ??= CategoryEventRegistration::where('status', 'withdrawn')
            ->where('refund_method', 'bank')
            ->where('refund_status', 'pending')
            ->count()
            + TeamPaymentOrder::where('refund_method', 'bank')
                ->where('refund_status', 'pending')
                ->count();
    }
}
