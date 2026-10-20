<?php

namespace App\Domain\Payments\Services;

use App\Models\{NoProfileTeamPlayer, Team, TeamPaymentOrder, TeamPlayer, TeamSelectionInvitation, Transaction, User, WalletTransaction};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/** Explicit operator reconciliation only. An ITN must never call this service. */
final class TeamLatePaymentReconciliationService
{
    public const AUDIT = 'Expired team PayFast receipt reconciled by super user';

    public function reconcile(int $orderId, string $providerId, User $actor, string $reason, bool $apply = false): array
    {
        abort_unless($actor->hasRole('super-user'), 403);
        $fail = fn (string $message) => throw ValidationException::withMessages(['reconciliation' => $message]);
        if (trim($reason) === '' || trim($providerId) === '') $fail('An exact provider reference and reconciliation reason are required.');

        return DB::transaction(function () use ($orderId, $providerId, $actor, $reason, $apply, $fail): array {
            $candidate = TeamPaymentOrder::findOrFail($orderId);
            $team = Team::withoutGlobalScopes()->lockForUpdate()->findOrFail($candidate->team_id);
            $order = TeamPaymentOrder::lockForUpdate()->findOrFail($orderId);
            if ((int) $order->team_id !== (int) $team->id || ! $team->category()->where('event_id', $order->event_id)->exists()
                || $order->beneficiary_player_id !== null || ! $order->user || ! $order->player
                || \App\Models\TrialParticipation::where('order_id', $orderId)->exists()) $fail('The original event, team, player and payer must still match.');
            $audits = Activity::where('subject_type', $order->getMorphClass())->where('subject_id', $orderId)->lockForUpdate()->get();
            $handoffs = $audits->where('description', 'Expired unresolved PayFast handoff retained for reconciliation');
            $received = $audits->where('description', 'Expired PayFast charge requires reconciliation')->filter(fn ($audit) => (string) $audit->properties['pf_payment_id'] === $providerId);
            $expiry = $audits->where('description', 'Unpaid team checkout expired; reservation released');
            $recovered = $audits->where('description', self::AUDIT)->filter(fn ($audit) => (string) $audit->properties['pf_payment_id'] === $providerId);
            if ($handoffs->count() !== 1 || $received->count() !== 1 || $expiry->count() !== 1 || $recovered->count() > 1) $fail('Unique canonical expiry and verified receipt audits are required.');
            $snapshot = $handoffs->first()->properties;
            foreach (['payer_id' => 'user_id', 'team_id' => 'team_id', 'event_id' => 'event_id', 'player_id' => 'player_id'] as $key => $field) {
                if ((int) $snapshot[$key] !== (int) $order->$field) $fail('The immutable handoff no longer matches this checkout.');
            }
            $amount = round((float) $snapshot['expected_provider_amount'], 2);
            if ($amount <= 0 || $amount !== round((float) $order->total_amount, 2)
                || (float) $snapshot['reserved_wallet_amount'] !== 0.0 || (float) $order->wallet_reserved !== 0.0 || $order->wallet_debited
                || WalletTransaction::where('source_type', 'team_registration_wallet_payment')->where('source_id', $orderId)->lockForUpdate()->exists()
                || \App\Models\TeamProviderRefundAttempt::where('order_id', $orderId)->lockForUpdate()->exists()
                || $order->hasRefund() || $order->refunded_at || $order->refund_waived_at || $order->refund_waived_by || filled($order->refund_waiver_reason)
                || filled($order->refund_method) || (float) $order->refund_gross !== 0.0 || (float) $order->refund_fee !== 0.0 || (float) $order->refund_net !== 0.0
                || $order->paid_privately_at || $order->paid_privately_by) $fail('Only exact full PayFast settlement without wallet, transfer or refund history can be recovered.');
            $receipts = Transaction::where('custom_str5', 'TeamOrder')->where('custom_int5', $orderId)->lockForUpdate()->get();
            $receipt = $receipts->count() === 1 ? $receipts->first() : null;
            if (! $receipt || (string) $receipt->pf_payment_id !== $providerId || $receipt->is_test
                || Transaction::where('pf_payment_id', $providerId)->count() !== 1
                || ! in_array($receipt->getAttributes()['payment_status'] ?? null, [null, 'COMPLETE'], true)
                || round((float) $receipt->amount_gross, 2) !== $amount || (int) $receipt->event_id !== (int) $order->event_id
                || (int) $receipt->player_id !== (int) $order->player_id || (int) $receipt->custom_int2 !== (int) $order->player_id
                || (int) $receipt->custom_int3 !== (int) $order->event_id || (int) $receipt->custom_int4 !== (int) $order->user_id
                || (int) $received->first()->properties['payer_id'] !== (int) $order->user_id
                || round((float) $received->first()->properties['amount'], 2) !== $amount) $fail('One matching real verified provider receipt is required.');
            $slots = TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->where('player_id', $order->player_id)->lockForUpdate()->get();
            $slot = $slots->count() === 1 ? $slots->first() : null;
            $imported = NoProfileTeamPlayer::where('team_id', $team->id)->where('player_profile', $order->player_id)->lockForUpdate()->get();
            if (! $slot || (int) $slot->rank < 1 || TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->where('rank', $slot->rank)->count() !== 1
                || ($team->noProfile && ($imported->count() !== 1 || (int) $imported->first()->rank !== (int) $slot->rank
                    || NoProfileTeamPlayer::where('team_id', $team->id)->where('rank', $slot->rank)->count() !== 1))
                || (! $team->noProfile && $imported->isNotEmpty())) $fail('The player must still occupy one consistent original roster position.');
            if (TeamPlayer::withoutGlobalScopes()->where('player_id', $order->player_id)->where('team_id', '!=', $team->id)
                ->whereHas('team.category', fn ($query) => $query->where('event_id', $order->event_id))->lockForUpdate()->exists()
                || NoProfileTeamPlayer::where('player_profile', $order->player_id)->where('team_id', '!=', $team->id)
                    ->whereHas('team.category', fn ($query) => $query->where('event_id', $order->event_id))->lockForUpdate()->exists()) $fail('The player now occupies another team in this event.');
            $orders = TeamPaymentOrder::where('event_id', $order->event_id)->forPlayerHistory([(int) $order->player_id])->where('id', '!=', $orderId)->orderBy('id')->lockForUpdate()->get();
            $payments = app(TeamPaymentService::class);
            foreach ($orders as $other) {
                if ($other->beneficiary_player_id !== null || (int) $other->team_id !== (int) $team->id) $fail('Another event checkout or coverage transfer requires separate review.');
                if (\App\Models\TeamProviderRefundAttempt::where('order_id', $other->id)->lockForUpdate()->exists()) $fail('Another checkout has provider refund history requiring separate review.');
                $payments->assertUnpaidRosterCheckoutMayBeReset($other);
            }
            $invitations = TeamSelectionInvitation::where('event_id', $order->event_id)->where('player_id', $order->player_id)->lockForUpdate()->get();
            if ($invitations->count() > 1) $fail('Ambiguous active selection requires separate review.');
            foreach ($invitations as $invitation) {
                $import = $invitation->selectionImport()->lockForUpdate()->first();
                if ((int) $invitation->team_id !== (int) $team->id || (int) $invitation->roster_rank !== (int) $slot->rank
                    || (int) $invitation->region_id !== (int) $team->region_id || ! $import || $import->status !== 'sent'
                    || (int) $import->event_id !== (int) $order->event_id || (int) $import->region_id !== (int) $team->region_id
                    || \App\Models\TeamSelectionImport::where('event_id', $order->event_id)->where('region_id', $team->region_id)->where('id', '>', $import->id)->exists()
                    || ! in_array($invitation->status, [TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, $recovered->isNotEmpty() ? TeamSelectionInvitation::PAID_CONFIRMED : ''], true)
                    || ! in_array($orderId, data_get($invitation->snapshot_json, 'expired_checkout_order_ids', []), true)
                    || ($invitation->order_id && ! in_array((int) $invitation->order_id, [$orderId, ...$orders->pluck('id')->all()], true))) $fail('The original selected campaign and roster position must remain current.');
            }
            $result = ['order_id' => $orderId, 'pf_payment_id' => $providerId, 'payer_id' => (int) $order->user_id,
                'event_id' => (int) $order->event_id, 'team_id' => (int) $team->id, 'player_id' => (int) $order->player_id,
                'amount' => $amount, 'successor_order_ids' => $orders->whereNull('withdrawn_at')->pluck('id')->all(), 'applied' => false, 'already_reconciled' => false];
            if ($recovered->isNotEmpty()) {
                if (! $order->pay_status || ! $order->payfast_paid || $order->withdrawn_at || $order->collection_status !== 'paid'
                    || (string) $order->payfast_pf_payment_id !== $providerId || ! $slot->pay_status
                    || ($team->noProfile && ! $imported->first()->pay_status)) $fail('Previously reconciled records changed and require separate review.');
                return array_replace($result, ['already_reconciled' => true]);
            }
            if (! $order->withdrawn_at || $order->withdrawn_by !== null || ! $order->withdrawn_at->equalTo($expiry->first()->created_at)
                || $order->pay_status || $order->payfast_paid || filled($order->payfast_pf_payment_id) || filled($order->payfast_raw_data)
                || $order->payfast_handed_off_at || (float) $order->payfast_amount_due !== 0.0 || $order->collection_status !== 'reconciliation_required'
                || $slot->pay_status || ($team->noProfile && $imported->first()->pay_status)) $fail('Only an unchanged expiry withdrawal can be reopened.');
            if (! $apply) return $result;
            foreach ($orders as $other) {
                if (! $other->withdrawn_at) {
                    $closed = $payments->closeUnpaidLifecycle($other, $actor);
                    app(\App\Services\TeamSelection\TeamSelectionInvitationService::class)->resetExpiredCheckout($closed);
                }
            }
            \App\Support\FinanceMutationScope::run('late_team_reconciliation', fn () => $payments->completeReconciledExpiredPayment($order, $receipt, $actor, $reason));
            return array_replace($result, ['applied' => true]);
        }, 3);
    }
}
