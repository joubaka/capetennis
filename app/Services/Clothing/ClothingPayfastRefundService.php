<?php

declare(strict_types=1);

namespace App\Services\Clothing;

use App\Domain\Refunds\Services\RefundExecutionService;
use App\Models\ClothingOrder;
use App\Models\ClothingRefund;
use App\Models\User;
use App\Services\Payfast;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ClothingPayfastRefundService
{
    public function dispatch(ClothingRefund $refund, User $actor, array $bankDetails = [], ?string $lateNote = null): ClothingRefund
    {
        if (DB::transactionLevel() !== 0) {
            throw new \LogicException('Provider refunds require a committed request.');
        }
        $dispatch = false;
        $attempt = DB::transaction(function () use ($refund, $actor, &$dispatch, $lateNote) {
            $order = ClothingOrder::lockForUpdate()->findOrFail($refund->clothing_order_id);
            app(ClothingRefundService::class)->authorizeOrder($order->event, $order, $actor);
            $locked = ClothingRefund::lockForUpdate()->findOrFail($refund->id);
            $this->assertEligible($locked, $order);
            \App\Support\FinanceMutationScope::run('refund_state_write', fn () => app(ClothingRefundService::class)->recordLateNote($locked, $order, $actor, $lateNote));
            $existing = DB::table('clothing_provider_refund_attempts')->where('clothing_refund_id', $locked->id)->first();
            if ($existing) {
                if ((int) $existing->clothing_order_id !== (int) $order->id || $existing->pf_payment_id !== $order->payfast_pf_payment_id
                    || ClothingRefundService::cents($existing->amount) !== ClothingRefundService::cents($locked->refund_net)) {
                    $this->uncertain();
                }
                if ($existing->status === 'confirmed') {
                    return $existing;
                }
                // Only an explicitly recorded preflight failure is retryable.
                // Legacy uncertain attempts still require independent evidence.
                if (! in_array($existing->status, ['not_dispatched', 'reconciled_ready'], true)) {
                    $this->uncertain();
                }
            }
            if ($locked->refund_status === 'completed') {
                $this->uncertain();
            }
            if (DB::table('clothing_provider_refund_attempts')->where('clothing_order_id', $order->id)
                ->whereIn('status', ['dispatching', 'uncertain'])->exists()) {
                $this->uncertain();
            }
            if ($existing) {
                $claimed = DB::table('clothing_provider_refund_attempts')->where('id', $existing->id)->where('status', $existing->status)->update([
                    'status' => 'dispatching', 'updated_at' => now(),
                ]);
                if ($claimed !== 1) {
                    $this->uncertain();
                }
                activity('refund')->performedOn($locked)->causedBy($actor)->withProperties([
                    'attempt_id' => $existing->id, 'amount' => $locked->refund_net,
                ])->log('Clothing PayFast saved refund retry started');
                $dispatch = true;
                return $existing;
            }
            $id = DB::table('clothing_provider_refund_attempts')->insertGetId([
                'clothing_refund_id' => $locked->id, 'clothing_order_id' => $order->id,
                'pf_payment_id' => $order->payfast_pf_payment_id, 'amount' => $locked->refund_net,
                'requested_by' => $actor->id, 'status' => 'dispatching', 'created_at' => now(), 'updated_at' => now(),
            ]);
            $dispatch = true;
            return DB::table('clothing_provider_refund_attempts')->find($id);
        });

        if ($dispatch) {
            $notDispatched = false;
            $failureCode = 'provider_result_unconfirmed';
            try {
                $expectedAvailable = app(ClothingPayfastRefundRecoveryService::class)->assertRetryEvidence($refund->fresh(), $attempt->status === 'reconciled_ready');
                $result = $expectedAvailable === null
                    ? app(Payfast::class)->refundUsingAvailableMethod($attempt->pf_payment_id, $attempt->amount, 'Clothing item refund', $bankDetails)
                    : app(Payfast::class)->refundUsingAvailableMethod($attempt->pf_payment_id, $attempt->amount, 'Clothing item refund', $bankDetails, $expectedAvailable);
                $payload = $result['data'] ?? [];
                // PayFast documents response=true inside its successful API envelope.
                $confirmed = ($result['success'] ?? false) === true
                    && ($payload['status'] ?? null) === 'success'
                    && (int) ($payload['code'] ?? 0) === 200
                    && ($payload['data']['response'] ?? null) === true;
                $notDispatched = ($result['success'] ?? null) === false && ($result['dispatched'] ?? null) === false;
                if ($notDispatched) {
                    $failureCode = in_array($result['failure_code'] ?? null, [
                        'invalid_amount', 'query_failed', 'not_refundable', 'insufficient_refundable_amount', 'method_unavailable', 'bank_details_required', 'provider_evidence_changed',
                    ], true) ? $result['failure_code'] : 'preflight_failed';
                }
            } catch (\Throwable $exception) {
                $confirmed = false;
                $failureCode = 'provider_exception';
            }
            $outcome = $confirmed ? 'confirmed' : ($notDispatched ? 'not_dispatched' : 'uncertain');
            DB::transaction(function () use ($attempt, $refund, $actor, $outcome, $confirmed, $failureCode) {
                DB::table('clothing_provider_refund_attempts')->where('id', $attempt->id)->where('status', 'dispatching')->update([
                    'status' => $outcome, 'confirmed_at' => $confirmed ? now() : null, 'updated_at' => now(),
                ]);
                // Keep categorical evidence, never raw provider responses,
                // exception messages, credentials or personal banking details.
                activity('refund')->performedOn($refund)->causedBy($actor)->withProperties([
                    'attempt_id' => $attempt->id, 'amount' => $attempt->amount, 'outcome' => $outcome,
                    'failure_code' => $confirmed ? null : $failureCode,
                ])->log('Clothing PayFast refund attempt result');
            });
            if ($notDispatched) {
                $detail = $failureCode === 'bank_details_required'
                    ? 'PayFast requires valid original-payer bank details for this refund.'
                    : 'PayFast refund checks did not pass.';
                throw ValidationException::withMessages(['refund' => $detail.' No refund was submitted. The amount remains reserved; retry the saved refund after correcting the issue.']);
            }
            if (! $confirmed) {
                $this->uncertain();
            }
        }

        return DB::transaction(function () use ($refund, $actor, $attempt, $lateNote) {
            $order = ClothingOrder::lockForUpdate()->findOrFail($refund->clothing_order_id);
            app(ClothingRefundService::class)->authorizeOrder($order->event, $order, $actor);
            $locked = ClothingRefund::lockForUpdate()->findOrFail($refund->id);
            $this->assertEligible($locked, $order);
            $confirmed = DB::table('clothing_provider_refund_attempts')->where('id', $attempt->id)->first();
            abort_unless($confirmed?->status === 'confirmed'
                && (int) $confirmed->clothing_refund_id === (int) $locked->id
                && (int) $confirmed->clothing_order_id === (int) $order->id
                && $confirmed->pf_payment_id === $order->payfast_pf_payment_id
                && ClothingRefundService::cents($confirmed->amount) === ClothingRefundService::cents($locked->refund_net), 422);
            if ($locked->refund_status === 'completed') {
                return $locked;
            }
            app(ClothingRefundService::class)->recordLateNote($locked, $order, $actor, $lateNote);
            $completed = app(RefundExecutionService::class)->executeBankRefund($locked, [
                'refund_method' => 'payfast', 'completed_by' => $actor->id, 'completed_by_name' => $actor->name,
            ]);
            activity('refund')->performedOn($completed)->causedBy($actor)->withProperties([
                'attempt_id' => $attempt->id, 'payer_id' => $locked->payer_id, 'order_id' => $order->id,
                'amount' => $locked->refund_net, 'provider_dispatched' => true,
            ])->log('Clothing PayFast refund confirmed');
            return $completed;
        });
    }

    private function assertEligible(ClothingRefund $refund, ClothingOrder $order): void
    {
        abort_unless($refund->refund_method === 'payfast' && in_array($refund->refund_status, ['pending', 'completed'], true)
            && (int) $refund->payer_id === (int) $order->user_id && (int) $refund->event_id === (int) $order->event_id
            && $order->pay_status === 1 && $order->status === 'completed' && $order->payfast_paid && filled($order->payfast_pf_payment_id)
            && ClothingRefundService::cents($refund->refund_net) > 0
            && ClothingRefundService::cents($refund->refund_gross) - ClothingRefundService::cents($refund->refund_fee) === ClothingRefundService::cents($refund->refund_net), 422);
    }

    private function uncertain(): never
    {
        throw ValidationException::withMessages(['refund' => 'PayFast has not confirmed this refund. Review PayFast history before any further attempt. The amount remains reserved; no duplicate payment will be sent.']);
    }
}
