<?php

namespace App\Domain\Payments\Services;

use App\Models\{RegistrationOrder, TeamPaymentOrder, Transaction};
use App\Support\FinanceMutationScope;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

class ExpiredPayfastHandoffService
{
    private const AUDIT = 'Expired unresolved PayFast handoff retained for reconciliation';

    public function registrationReceiptRequiresReconciliation(Transaction $receipt): bool
    {
        if (filled($receipt->custom_str5) || blank($receipt->pf_payment_id)) return false;
        $order = RegistrationOrder::whereKey($receipt->custom_int5)->where('user_id', $receipt->custom_int4)
            ->where('status', 'cancelled')->where('pay_status', false)
            ->whereHas('items.category_event', fn ($query) => $query->where('event_id', $receipt->event_id))->first();
        return $order && Activity::where('subject_type', $order->getMorphClass())->where('subject_id', $order->id)
            ->where('description', self::AUDIT)->where('properties->event_id', (int) $receipt->event_id)
            ->where('properties->payer_id', (int) $receipt->custom_int4)->exists()
            && Activity::where('subject_type', $order->getMorphClass())->where('subject_id', $order->id)
                ->where('description', 'Expired PayFast charge requires reconciliation')
                ->where('properties->pf_payment_id', $receipt->pf_payment_id)->exists();
    }

    /** Called only after the canonical expiry service has validated and locked the lifecycle. */
    public function retain(RegistrationOrder|TeamPaymentOrder $order): void
    {
        if (!$order->payfast_handed_off_at) return;
        $item = $order instanceof RegistrationOrder ? $order->items->first() : null;
        activity('payment')->performedOn($order)->withProperties([
            'payer_id' => (int) $order->user_id,
            'event_id' => (int) ($item?->category_event?->event_id ?? $order->event_id),
            'player_id' => (int) ($item?->player_id ?? $order->player_id),
            'category_event_id' => $item?->category_event_id,
            'team_id' => $order instanceof TeamPaymentOrder ? $order->team_id : null,
            'expected_provider_amount' => (string) $order->payfast_amount_due,
            'reserved_wallet_amount' => (string) $order->wallet_reserved,
            'handed_off_at' => $order->payfast_handed_off_at->toIso8601String(),
        ])->log(self::AUDIT);
        FinanceMutationScope::run('payment_state_write', fn () => $order->forceFill(['payfast_handed_off_at' => null])->save());
    }

    /** Signature verification belongs to the ITN endpoint, before calling this adapter. */
    public function recordReceipt(RegistrationOrder|TeamPaymentOrder $order, array $data): bool
    {
        return DB::transaction(function () use ($order, $data): bool {
            $locked = $order->newQuery()->lockForUpdate()->findOrFail($order->id);
            $audit = Activity::where('subject_type', $locked->getMorphClass())->where('subject_id', $locked->id)
                ->where('description', self::AUDIT)->first();
            if (!$audit) return false;
            $snapshot = $audit->properties;
            $team = $locked instanceof TeamPaymentOrder;
            $providerId = trim((string) ($data['pf_payment_id'] ?? ''));
            if (($team ? !$locked->withdrawn_at : $locked->status !== 'cancelled')
                || ($data['payment_status'] ?? '') !== 'COMPLETE' || $providerId === ''
                || (string) ($data['custom_int5'] ?? '') !== (string) $locked->id
                || (string) ($data['custom_int4'] ?? '') !== (string) $snapshot['payer_id']
                || ($data['custom_str5'] ?? '') !== ($team ? 'TeamOrder' : '')
                || !is_numeric($data['amount_gross'] ?? null)
                || round((float) $data['amount_gross'], 2) !== round((float) $snapshot['expected_provider_amount'], 2)) {
                throw new \RuntimeException('Expired PayFast receipt does not match its original handoff.');
            }
            foreach (['custom_int1' => 'category_event_id', 'custom_int2' => 'player_id', 'custom_int3' => 'event_id'] as $field => $identity) {
                if ($team && $field === 'custom_int1') $identity = 'team_id';
                if ((($team && $field !== 'custom_int1') || isset($data[$field])) && $snapshot[$identity] !== null
                    && (string) ($data[$field] ?? '') !== (string) $snapshot[$identity]) {
                    throw new \RuntimeException('Expired PayFast receipt metadata does not match its original handoff.');
                }
            }
            $existing = Transaction::where('pf_payment_id', $providerId)->lockForUpdate()->first();
            if ($existing && ((int) $existing->custom_int5 !== (int) $locked->id
                || (string) $existing->custom_str5 !== ($team ? 'TeamOrder' : '')
                || (int) $existing->custom_int4 !== (int) $snapshot['payer_id']
                || round((float) $existing->amount_gross, 2) !== round((float) $snapshot['expected_provider_amount'], 2))) {
                throw new \RuntimeException('PayFast receipt already belongs to another payment.');
            }
            if (!$existing) {
                app(PaymentTransactionService::class)->record(array_replace($data, [
                    'cape_tennis_fee' => 0, 'custom_int1' => $snapshot['category_event_id'],
                    'custom_int2' => $snapshot['player_id'], 'custom_int3' => $snapshot['event_id'],
                ]), $locked);
                activity('payment')->performedOn($locked)->withProperties([
                    'pf_payment_id' => $providerId, 'payer_id' => $snapshot['payer_id'],
                    'amount' => $snapshot['expected_provider_amount'], 'reconciliation_required' => true,
                ])->log('Expired PayFast charge requires reconciliation');
                if ($team) FinanceMutationScope::run('payment_state_write', fn () => $locked->forceFill(['collection_status' => 'reconciliation_required'])->save());
            }
            return true;
        });
    }
}
