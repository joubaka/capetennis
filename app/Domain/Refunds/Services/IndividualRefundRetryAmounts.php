<?php

namespace App\Domain\Refunds\Services;

use App\Models\CategoryEventRegistration;
use Illuminate\Validation\ValidationException;

/** Resolve an approved pending refund without applying a new fee on retry. */
final class IndividualRefundRetryAmounts
{
    /** @return array{payfast: float, wallet: float} */
    public function resolve(CategoryEventRegistration $entry, array $payment): array
    {
        $fail = static fn () => throw ValidationException::withMessages([
            'refund' => 'The approved refund amounts or original funding need reconciliation before retrying.',
        ]);
        $cents = static function (mixed $value) use ($fail): int {
            if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0) $fail();
            $rounded = (int) round((float) $value * 100);
            if (abs((float) $value * 100 - $rounded) > 0.00001) $fail();
            return $rounded;
        };
        $gross = $cents($entry->refund_gross);
        $fee = $cents($entry->refund_fee);
        $net = $cents($entry->refund_net);
        $provider = $cents($payment['gross'] ?? null);
        $wallet = $cents($payment['wallet_paid'] ?? null);
        if ($entry->status !== 'withdrawn' || ! $entry->withdrawn_at || ! $entry->is_paid
            || $entry->refund_status !== 'pending' || $entry->refund_method !== 'bank'
            || $entry->refunded_at || $gross <= 0 || $net <= 0 || $gross !== $fee + $net
            || $gross !== $provider + $wallet || $net - min($wallet, $net) > $provider) $fail();

        // Wallet funding carries no new retry fee; the approved total remains authoritative.
        $walletNet = min($wallet, $net);
        if ($net - $walletNet <= 0 || ($walletNet > 0 && ! $entry->user?->wallet)) $fail();
        return ['payfast' => ($net - $walletNet) / 100, 'wallet' => $walletNet / 100];
    }
}
