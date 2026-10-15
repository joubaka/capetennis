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
                if ($existing->status !== 'confirmed' || $existing->pf_payment_id !== $order->payfast_pf_payment_id
                    || ClothingRefundService::cents($existing->amount) !== ClothingRefundService::cents($locked->refund_net)) {
                    $this->uncertain();
                }
                return $existing;
            }
            if ($locked->refund_status === 'completed') {
                $this->uncertain();
            }
            if (DB::table('clothing_provider_refund_attempts')->where('clothing_order_id', $order->id)
                ->whereIn('status', ['dispatching', 'uncertain'])->exists()) {
                $this->uncertain();
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
            try {
                $result = app(Payfast::class)->refundUsingAvailableMethod($attempt->pf_payment_id, $attempt->amount, 'Clothing item refund', $bankDetails);
                $payload = $result['data'] ?? [];
                // PayFast documents response=true inside its successful API envelope.
                $confirmed = ($result['success'] ?? false) === true
                    && ($payload['status'] ?? null) === 'success'
                    && (int) ($payload['code'] ?? 0) === 200
                    && ($payload['data']['response'] ?? null) === true;
            } catch (\Throwable $exception) {
                $confirmed = false;
            }
            DB::table('clothing_provider_refund_attempts')->where('id', $attempt->id)->where('status', 'dispatching')->update([
                'status' => $confirmed ? 'confirmed' : 'uncertain', 'confirmed_at' => $confirmed ? now() : null, 'updated_at' => now(),
            ]);
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
            $completed = app(RefundExecutionService::class)->executeBankRefund($locked, ['refund_method' => 'payfast']);
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
