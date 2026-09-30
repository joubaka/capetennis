<?php

namespace App\Domain\Payments\Services;

use App\Models\Registration;
use App\Models\RegistrationOrder;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\FinanceMutationScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationPaymentService
{
    public function __construct(
        private PaymentOrchestrator $paymentOrchestrator
    ) {
    }

    public function reservePayment(RegistrationOrder $order, float $walletApplied, float $remainingAmount): RegistrationOrder
    {
        /** @var RegistrationOrder $reserved */
        $reserved = DB::transaction(function () use ($order, $walletApplied, $remainingAmount): RegistrationOrder {
            $locked = RegistrationOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->payfast_handed_off_at) {
                throw ValidationException::withMessages([
                    'payment' => 'This checkout has already been sent to PayFast and cannot be changed while payment is resolving.',
                ]);
            }

            return $this->paymentOrchestrator->initiatePayment($locked, $walletApplied, $remainingAmount);
        });

        return $reserved;
    }

    public function cancelPayment(RegistrationOrder $order): RegistrationOrder
    {
        /** @var RegistrationOrder $cancelled */
        $cancelled = DB::transaction(function () use ($order): RegistrationOrder {
            $locked = RegistrationOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->payfast_handed_off_at) {
                throw ValidationException::withMessages([
                    'payment' => 'This checkout has already been sent to PayFast and cannot be cancelled while payment is resolving.',
                ]);
            }

            return $this->paymentOrchestrator->cancelPayment($locked);
        });

        return $cancelled;
    }

    public function preparePayfastHandoff(
        RegistrationOrder $order,
        User $payer,
        float $walletReserved,
        float $payfastDue
    ): RegistrationOrder
    {
        if (! is_finite($walletReserved) || ! is_finite($payfastDue)) {
            throw ValidationException::withMessages(['payment' => 'The checkout amount is invalid.']);
        }

        return DB::transaction(function () use ($order, $payer, $walletReserved, $payfastDue): RegistrationOrder {
            $locked = RegistrationOrder::query()->lockForUpdate()->with('items')->findOrFail($order->id);

            if ((int) $locked->user_id !== (int) $payer->id) {
                throw ValidationException::withMessages(['payment' => 'Only the payer may submit this checkout to PayFast.']);
            }
            if ((int) $locked->pay_status === 1 || (bool) $locked->payfast_paid
                || (bool) $locked->wallet_debited || $locked->status === 'cancelled') {
                throw ValidationException::withMessages(['payment' => 'This checkout can no longer be sent to PayFast.']);
            }
            if ($locked->payfast_handed_off_at) {
                throw ValidationException::withMessages([
                    'payment' => 'This checkout has already been sent to PayFast and is awaiting a verified result.',
                ]);
            }

            $total = round((float) $locked->items->sum('item_price'), 2);
            $reserved = round($walletReserved, 2);
            $due = round($payfastDue, 2);
            $storedReserved = round((float) $locked->wallet_reserved, 2);
            $storedDue = round($total - $storedReserved, 2);
            if ($total <= 0 || $reserved < 0 || $due <= 0 || round($reserved + $due, 2) !== $total) {
                throw ValidationException::withMessages(['payment' => 'The checkout amount is not ready for PayFast.']);
            }
            if ($reserved !== $storedReserved || $due !== $storedDue) {
                throw ValidationException::withMessages([
                    'payment' => 'The checkout amount changed. Refresh the checkout before continuing to PayFast.',
                ]);
            }

            $prepared = $this->paymentOrchestrator->initiatePayment($locked, $reserved, $due);
            $prepared->forceFill(['payfast_handed_off_at' => now()])->save();
            activity('registration-payment')->performedOn($prepared)->causedBy($payer)
                ->withProperties([
                    'order_id' => $prepared->id,
                    'payer_id' => $payer->id,
                    'amount_due' => $due,
                    'handed_off_at' => $prepared->payfast_handed_off_at?->toIso8601String(),
                ])->log('registration checkout handed off to PayFast');

            return $prepared->fresh('items');
        });
    }

    public function assertPayfastHandoffMayBeReleased(RegistrationOrder $order): void
    {
        $order->loadMissing('items');
        $total = round((float) $order->items->sum('item_price'), 2);
        $reserved = round((float) $order->wallet_reserved, 2);
        $due = round((float) $order->payfast_amount_due, 2);
        if ($order->payfast_handed_off_at === null) {
            throw ValidationException::withMessages(['payment' => 'This registration order has no unresolved PayFast handoff.']);
        }
        if ($total <= 0 || $due <= 0 || $reserved < 0 || round($reserved + $due, 2) !== $total
            || $this->hasSettlementEvidence($order)) {
            throw ValidationException::withMessages([
                'payment' => 'This registration order contains settlement or lifecycle evidence and cannot be released.',
            ]);
        }
    }

    public function releaseUnresolvedPayfastHandoff(RegistrationOrder $order, User $operator, string $evidenceReference): RegistrationOrder
    {
        $this->assertSafeEvidenceReference($evidenceReference);

        return DB::transaction(function () use ($order, $operator, $evidenceReference): RegistrationOrder {
            $locked = RegistrationOrder::query()->lockForUpdate()->with('items')->findOrFail($order->id);
            $authorizedOperator = User::query()->find($operator->id);
            if (! $authorizedOperator?->hasRole('super-user')) {
                throw ValidationException::withMessages(['operator' => 'An existing super-user operator is required.']);
            }
            $this->assertPayfastHandoffMayBeReleased($locked);
            $walletReserved = round((float) $locked->wallet_reserved, 2);
            $payfastDue = round((float) $locked->payfast_amount_due, 2);

            $locked->forceFill(['payfast_handed_off_at' => null])->save();

            activity('registration-payment')->performedOn($locked)->causedBy($authorizedOperator)
                ->withProperties([
                    'order_id' => $locked->id,
                    'operator_id' => $authorizedOperator->id,
                    'evidence_reference' => $evidenceReference,
                    'wallet_reserved' => $walletReserved,
                    'payfast_amount_due' => $payfastDue,
                ])->log('supervised unresolved PayFast handoff released');

            return $locked->fresh('items');
        });
    }

    private function assertSafeEvidenceReference(string $evidenceReference): void
    {
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/-]{2,119}$/', $evidenceReference)) {
            throw ValidationException::withMessages([
                'payment' => 'A safe 3-120 character provider evidence/reference is required.',
            ]);
        }
    }

    private function hasSettlementEvidence(RegistrationOrder $order): bool
    {
        if ((int) $order->pay_status === 1 || (bool) $order->payfast_paid
            || (bool) $order->wallet_debited || filled($order->payfast_pf_payment_id)
            || filled($order->wallet_transaction_id)
            || $order->status === 'cancelled') {
            return true;
        }
        if (Transaction::query()->where('custom_int5', $order->id)
            ->where(function ($query): void {
                $query->whereNull('custom_str5')->orWhere('custom_str5', '');
            })->exists()) {
            return true;
        }
        if (WalletTransaction::query()->where('source_id', $order->id)
            ->where('source_type', 'event_registration_wallet_payment')->exists()) {
            return true;
        }

        foreach ($order->items as $item) {
            $hasEvidence = DB::table('category_event_registrations')
                ->where('registration_id', $item->registration_id)
                ->where('category_event_id', $item->category_event_id)
                ->where(function ($query): void {
                    $query->whereNotNull('withdrawn_at')
                        ->orWhereNotNull('refunded_at')
                        ->orWhereNotNull('pf_transaction_id')
                        ->orWhereNotNull('wallet_transaction_id')
                        ->orWhere('payment_status_id', 1)
                        ->orWhere('admin_payment_status', 'paid')
                        ->orWhere(function ($refundQuery): void {
                            $refundQuery->whereNotNull('refund_status')
                                ->whereNotIn('refund_status', ['', 'not_refunded']);
                        });
                })->exists();

            if ($hasEvidence) {
                return true;
            }
        }

        return false;
    }

    public function finalizePayment(RegistrationOrder $order, array $context = []): RegistrationOrder
    {
        /** @var RegistrationOrder $finalized */
        $finalized = $this->paymentOrchestrator->finalizePayment($order, $context);
        $this->markOrderRegistrationsPaid($finalized, $context['pf_payment_id'] ?? null, $context['user_id'] ?? $finalized->user_id);

        return $finalized;
    }

    public function finalizeWalletPayment(RegistrationOrder $order, array $context = []): RegistrationOrder
    {
        return DB::transaction(function () use ($order, $context) {
            $locked = RegistrationOrder::query()
                ->lockForUpdate()
                ->with('items')
                ->findOrFail($order->id);

            $total = round((float) $locked->items->sum('item_price'), 2);
            $reserved = round((float) $locked->wallet_reserved, 2);
            $payfastDue = round((float) $locked->payfast_amount_due, 2);

            if ($total <= 0 || $payfastDue !== 0.0 || $reserved !== $total) {
                throw new \RuntimeException('Wallet payment cannot complete while an unpaid balance remains.');
            }

            return $this->finalizePayment($locked, $context + [
                'payment_method' => 'wallet',
                'payfast_amount_due' => 0,
            ]);
        });
    }

    public function finalizePayfastPayment(RegistrationOrder $order, float $receivedAmount, array $context = []): RegistrationOrder
    {
        if (! is_finite($receivedAmount) || $receivedAmount <= 0) {
            throw new \RuntimeException('PayFast payment amount must be positive.');
        }

        return DB::transaction(function () use ($order, $receivedAmount, $context) {
            $locked = RegistrationOrder::query()
                ->lockForUpdate()
                ->with('items')
                ->findOrFail($order->id);

            if ((int) $locked->pay_status === 1 || (bool) $locked->payfast_paid) {
                return $locked;
            }

            $total = round((float) $locked->items->sum('item_price'), 2);
            $reserved = round((float) $locked->wallet_reserved, 2);
            $expected = round($total - $reserved, 2);
            $received = round($receivedAmount, 2);

            if ($total <= 0 || $reserved < 0 || $expected <= 0 || $received !== $expected) {
                throw new \RuntimeException("Payment amount mismatch. Expected {$expected}, received {$received}.");
            }

            // Older pending orders may not have stored the PayFast remainder.
            // Recover it only from the locked item-price snapshot and reservation.
            if (round((float) $locked->payfast_amount_due, 2) !== $expected) {
                $locked->payfast_amount_due = $expected;
                $locked->save();
            }

            return $this->finalizePayment($locked, array_merge($context, [
                'payfast_amount_due' => $expected,
                'payfast_amount_received' => $received,
            ]));
        });
    }

    public function markOrderRegistrationsPaid(RegistrationOrder $order, ?string $pfPaymentId, ?int $userId = null): void
    {
        FinanceMutationScope::run('registration_payment_state_write', function () use ($order, $pfPaymentId, $userId) {
            DB::transaction(function () use ($order, $pfPaymentId, $userId) {
                $lockedOrder = RegistrationOrder::query()
                    ->lockForUpdate()
                    ->with('items')
                    ->findOrFail($order->id);

                foreach ($lockedOrder->items as $item) {
                    $registration = Registration::find($item->registration_id);
                    if (!$registration) {
                        continue;
                    }

                    $registration->players()->syncWithoutDetaching([$item->player_id]);
                    $registration->categoryEvents()->syncWithoutDetaching([
                        $item->category_event_id => [
                            'payment_status_id' => 1,
                            'user_id' => $userId,
                            'pf_transaction_id' => $pfPaymentId,
                            'payment_method' => $lockedOrder->payment_method,
                            'wallet_transaction_id' => $lockedOrder->wallet_transaction_id,
                        ],
                    ]);
                }
            });
        });
    }

    public function markFreeOrderPaid(RegistrationOrder $order): RegistrationOrder
    {
        return FinanceMutationScope::run(
            ['payment_state_write', 'registration_payment_state_write'],
            function () use ($order) {
                return DB::transaction(function () use ($order) {
                    $lockedOrder = RegistrationOrder::query()
                        ->lockForUpdate()
                        ->with('items')
                        ->findOrFail($order->id);

                    foreach ($lockedOrder->items as $item) {
                        $registration = Registration::find($item->registration_id);
                        if ($registration) {
                            $registration->categoryEvents()->updateExistingPivot($item->category_event_id, [
                                'payment_status_id' => 1,
                            ]);
                        }
                    }

                    $lockedOrder->pay_status = 1;
                    $lockedOrder->payfast_paid = false;
                    $lockedOrder->wallet_debited = false;
                    $lockedOrder->wallet_reserved = 0;
                    $lockedOrder->payfast_amount_due = 0;
                    $lockedOrder->payment_method = 'free';
                    if (array_key_exists('status', $lockedOrder->getAttributes())) {
                        $lockedOrder->status = 'completed';
                    }
                    $lockedOrder->save();

                    return $lockedOrder;
                });
            }
        );
    }
}
