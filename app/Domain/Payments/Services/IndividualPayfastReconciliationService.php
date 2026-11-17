<?php

namespace App\Domain\Payments\Services;

use App\Domain\Entries\Services\{EntryEligibilityService, EntryService};
use App\Models\{AuditEvent, CategoryEvent, CategoryEventRegistration, Event, EventNomination, InterprovincialTrialInvitation, Registration, RegistrationOrder, RegistrationOrderItems, Transaction, User, WalletTransaction};
use App\Services\DivisionCapacityService;
use App\Services\InterprovincialTrials\InvitationService;
use App\Support\Audit\AuditIntegrity;
use App\Support\FinanceMutationScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/** Explicit reviewed dashboard attestation, never an ITN handler or automatic expiry override. */
final class IndividualPayfastReconciliationService
{
    public const AUDIT = 'On-time individual PayFast settlement reconciled from authenticated dashboard';
    public const RECONFIRMED_SOURCE = 'same_day_authenticated_dashboard_operator_reconfirmed';

    public function run(int $orderId, User $actor, array $evidence, string $reason, ?string $confirmation = null): array
    {
        abort_unless($actor->fresh()?->hasRole('super-user'), 403);
        $evidence = $this->evidence($evidence, $actor, $reason);
        $this->require(trim($reason) !== '' && strlen($reason) <= 500, 'A reviewed reason of at most 500 characters is required.');
        return DB::transaction(function () use ($orderId, $actor, $evidence, $reason, $confirmation): array {
            $candidate = RegistrationOrderItems::where('order_id', $orderId)->sole();
            $eventId = CategoryEvent::whereKey($candidate->category_event_id)->value('event_id');
            // Match public trial admission: event, category, nomination, invitation, order.
            $event = Event::with('eventTypeModel')->lockForUpdate()->findOrFail($eventId);
            $category = CategoryEvent::with('event')->lockForUpdate()->findOrFail($candidate->category_event_id);
            $this->require((int) $category->event_id === (int) $event->id, 'The checkout category changed events.');
            $nomination = EventNomination::where('event_id', $event->id)->where('category_event_id', $category->id)
                ->where('player_id', $candidate->player_id)->lockForUpdate()->sole();
            $invitation = InterprovincialTrialInvitation::where('nomination_id', $nomination->id)
                ->where('status', '!=', 'prepared')->orderByDesc('id')->lockForUpdate()->firstOrFail();
            $batch = $invitation->batch()->lockForUpdate()->firstOrFail();
            $order = RegistrationOrder::lockForUpdate()->findOrFail($orderId);
            $items = $order->items()->lockForUpdate()->get();
            $this->require($items->count() === 1, 'Only an original single-player checkout is supported.');
            $item = $items->sole();
            $registration = Registration::lockForUpdate()->findOrFail($item->registration_id);
            $entry = CategoryEventRegistration::withTrashed()->where('registration_id', $registration->id)
                ->where('category_event_id', $category->id)->lockForUpdate()->sole();
            $this->require((int) $item->id === (int) $candidate->id && (int) $item->category_event_id === (int) $category->id
                && (int) $item->user_id === (int) $order->user_id && (int) $entry->user_id === (int) $order->user_id
                && (int) $invitation->event_id === (int) $event->id && (int) $invitation->category_event_id === (int) $category->id
                && (int) $invitation->player_id === (int) $item->player_id && (int) $batch->event_id === (int) $event->id
                && $order->user && $nomination->player, 'The original payer, player, nomination and event must still match.');
            foreach (['order_id' => $orderId, 'payer_id' => $order->user_id, 'event_id' => $event->id,
                'category_event_id' => $category->id, 'player_id' => $item->player_id] as $key => $value) {
                $this->require((string) $evidence[$key] === (string) $value, 'Provider evidence does not match the exact original checkout.');
            }
            $players = DB::table('player_registrations')->where('registration_id', $registration->id)->lockForUpdate()->pluck('player_id');
            $this->require($players->count() === 1 && (int) $players->sole() === (int) $item->player_id, 'Original registration identity changed.');
            $this->require(! DB::table('draw_registrations')->where('registration_id', $registration->id)->lockForUpdate()->exists()
                && ! DB::table('fixtures')->where(fn ($query) => $query->where('registration1_id', $registration->id)->orWhere('registration2_id', $registration->id))->lockForUpdate()->exists(),
                'Competition history requires separate review.');
            $audits = Activity::where('subject_type', $order->getMorphClass())->where('subject_id', $orderId)->lockForUpdate()->get();
            $handoffs = $audits->where('description', 'Expired unresolved PayFast handoff retained for reconciliation');
            $expiries = $audits->where('description', 'Unpaid registration checkout expired; reservation released');
            $reconciled = $audits->where('description', self::AUDIT);
            $this->require($handoffs->count() === 1 && $expiries->count() === 1 && $reconciled->count() <= 1, 'Unique canonical handoff and automatic-expiry evidence is required.');
            $handoff = $handoffs->sole();
            $expiry = $expiries->sole();
            $snapshot = $handoff->properties;
            foreach (['payer_id' => $order->user_id, 'event_id' => $event->id, 'category_event_id' => $category->id, 'player_id' => $item->player_id] as $key => $value) {
                $this->require((string) $snapshot[$key] === (string) $value, 'Immutable handoff identity changed.');
            }
            $paidAt = CarbonImmutable::parse($evidence['paid_at'])->setTimezone(config('app.timezone'));
            $handedAt = CarbonImmutable::parse($snapshot['handed_off_at']);
            $this->require($paidAt->gte($handedAt) && $paidAt->lt($handedAt->addMinutes(CheckoutExpiryPolicy::MINUTES))
                && $paidAt->lt($expiry->created_at), 'Only settlement completed inside the original five-minute handoff is supported.');
            $this->require($this->cents($snapshot['expected_provider_amount']) === $this->cents($evidence['gross'])
                && $this->cents($item->item_price) === $this->cents($evidence['gross'])
                && $this->cents($order->total_fee) === $this->cents($evidence['gross'])
                && (float) $snapshot['reserved_wallet_amount'] === 0.0 && (float) $order->wallet_reserved === 0.0
                && ! $order->wallet_debited && ! $order->wallet_transaction_id, 'Only unchanged, full PayFast payments without any wallet funding are supported.');
            $this->require(! WalletTransaction::where('source_type', 'event_registration_wallet_payment')->where('source_id', $orderId)->lockForUpdate()->exists(), 'Wallet history requires separate review.');
            foreach (['registration_manual_receipts', 'trial_payment_proofs', 'registration_payment_recoveries'] as $table) {
                $column = $table === 'registration_payment_recoveries' ? 'registration_order_id' : 'order_id';
                $this->require(! DB::table($table)->where($column, $orderId)->lockForUpdate()->exists(), 'Manual, EFT or recovery history requires separate review.');
            }
            $this->require(! $entry->withdrawn_at && ! $entry->withdrawn_by && ! $entry->refunded_at && ! $entry->refund_waived_at
                && ! $entry->refund_waived_by && ! $entry->refund_method && ! $entry->refund_waiver_reason
                && ! $entry->refund_gross && ! $entry->refund_fee && ! $entry->refund_net
                && ($entry->refund_status === null || in_array($entry->refund_status, CategoryEventRegistration::NO_REFUND_STATUSES, true))
                && $entry->admin_payment_status !== 'paid', 'Withdrawal, refund or private collection history requires separate review.');
            $others = RegistrationOrderItems::where('player_id', $item->player_id)->where('order_id', '!=', $orderId)
                ->whereHas('category_event', fn ($query) => $query->where('event_id', $event->id))->lockForUpdate()->get();
            $this->require($others->isEmpty(), 'Another checkout exists for this player in the event.');
            $this->require(! CategoryEventRegistration::where('id', '!=', $entry->id)
                ->whereHas('categoryEvent', fn ($query) => $query->where('event_id', $event->id))
                ->whereHas('registration.players', fn ($query) => $query->where('players.id', $item->player_id))->lockForUpdate()->exists(), 'Another event entry exists for this player.');
            $receipts = Transaction::where(fn ($query) => $query->where('pf_payment_id', $evidence['provider_id'])
                ->orWhere(fn ($query) => $query->where('custom_int5', $orderId)
                    ->where(fn ($blank) => $blank->whereNull('custom_str5')->orWhere('custom_str5', ''))))->lockForUpdate()->get();
            $result = ['order_id' => $orderId, 'entry_id' => $entry->id, 'registration_id' => $registration->id,
                'invitation_id' => $invitation->id, 'provider_id' => $evidence['provider_id'], 'evidence_source' => $evidence['source'],
                'gross' => $evidence['gross'], 'fee' => $evidence['fee'], 'net' => $evidence['net'], 'paid_at' => $evidence['paid_at'],
                'reviewed_current_platform_fee' => $event->cape_tennis_fee,
                'platform_fee_source' => 'current_event_configuration_at_review',
                'applied' => false, 'already_reconciled' => false, 'new_charge' => false, 'wallet_debit' => false, 'mail' => false];
            $result = array_merge($result, $this->reviewContext($evidence));
            $digest = hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR));
            if ($reconciled->isNotEmpty()) {
                $receipt = $receipts->count() === 1 ? $receipts->sole() : null;
                $this->require($reconciled->sole()->properties['evidence_digest'] === $digest && $receipt
                    && (string) $receipt->pf_payment_id === $evidence['provider_id'] && ! $receipt->is_test
                    && $this->cents($receipt->amount_gross) === $this->cents($evidence['gross'])
                    && $this->cents($receipt->amount_fee) === $this->cents($evidence['fee']) && $this->cents($receipt->amount_net) === $this->cents($evidence['net'])
                    && (int) $receipt->custom_int4 === (int) $order->user_id && (int) $receipt->custom_int5 === $orderId
                    && (int) $receipt->event_id === (int) $event->id && (int) $receipt->custom_int3 === (int) $event->id
                    && (int) $receipt->category_event_id === (int) $category->id && (int) $receipt->custom_int1 === (int) $category->id
                    && (int) $receipt->player_id === (int) $item->player_id && (int) $receipt->custom_int2 === (int) $item->player_id
                    && (int) $receipt->registration_id === (int) $registration->id
                    && $order->status === 'completed' && $order->pay_status && $order->payfast_paid
                    && (string) $order->payfast_pf_payment_id === $evidence['provider_id'] && ! $entry->trashed()
                    && $entry->status === 'active' && (int) $entry->payment_status_id === 1
                    && (string) $entry->pf_transaction_id === $evidence['provider_id'] && $entry->payment_method === 'payfast'
                    && $invitation->status === InterprovincialTrialInvitation::PAID_CONFIRMED
                    && (int) $invitation->order_id === $orderId && (int) $invitation->registration_id === (int) $registration->id
                    && $invitation->paid_at?->equalTo($paidAt),
                    'Previously reconciled state differs and needs separate review.');
                return array_replace($result, ['already_reconciled' => true]);
            }
            $this->require($receipts->isEmpty() && $order->status === 'cancelled' && ! $order->pay_status && ! $order->payfast_paid
                && ! $order->payfast_pf_payment_id && ! $order->payfast_handed_off_at && (float) $order->payfast_amount_due === 0.0
                && $entry->trashed() && $entry->status === 'pending_checkout'
                && ! $entry->payment_status_id && ! $entry->pf_transaction_id && ! $entry->wallet_transaction_id
                && $entry->deleted_at->format('Y-m-d H:i:s') === $expiry->created_at->format('Y-m-d H:i:s')
                && $entry->updated_at->format('Y-m-d H:i:s') === $expiry->created_at->format('Y-m-d H:i:s')
                && $order->updated_at->format('Y-m-d H:i:s') === $expiry->created_at->format('Y-m-d H:i:s'), 'Only an unchanged automatic-expiry cancellation can be recovered.');
            $resets = AuditEvent::where('subject_type', $invitation->getMorphClass())->where('subject_id', $invitation->id)
                ->where('action', 'interprovincial-trial-invitation.updated')->where('occurred_at', '>=', $handedAt)
                ->where('occurred_at', '<=', $expiry->created_at->copy()->addSecond())->lockForUpdate()->get()
                ->filter(fn ($audit) => (int) ($audit->before['order_id'] ?? 0) === $orderId
                    && (int) ($audit->before['registration_id'] ?? 0) === (int) $registration->id
                    && ($audit->before['status'] ?? null) === InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT
                    && ($audit->after['status'] ?? null) === 'sent' && array_key_exists('order_id', $audit->after) && $audit->after['order_id'] === null);
            $this->require($resets->count() === 1 && AuditIntegrity::verify($resets->sole()->getRawOriginal()), 'A unique integrity-verified reset audit must link the original invitation and checkout.');
            $reset = $resets->sole();
            $this->require(! empty($reset->before['accepted_at']) && $invitation->status === 'sent'
                && ! $invitation->order_id && ! $invitation->registration_id && ! $invitation->accepted_at && ! $invitation->paid_at && ! $invitation->withdrawn_at,
                'The original invitation is no longer in its unchanged expiry-reset state.');
            $this->require($event->isInterprovincialTrials() && $event->published && (int) $event->signUp === 1
                && $event->hasOpenRegistrationLifecycle() && (! $event->registrationClosesAt() || ! now()->gt($event->registrationClosesAt()->endOfDay()))
                && $category->nominations_published, 'The published trial nomination and registration window must remain open.');
            app(EntryEligibilityService::class)->assertCanRegister($category, (int) $item->player_id);
            app(DivisionCapacityService::class)->assertAvailable($category);
            $state = [$order->getRawOriginal(), $item->getRawOriginal(), $registration->getRawOriginal(), $entry->getRawOriginal(),
                $category->getRawOriginal(), $event->getRawOriginal(), $nomination->getRawOriginal(), $invitation->getRawOriginal(),
                $batch->getRawOriginal(), $handoff->getRawOriginal(), $expiry->getRawOriginal(), $reset->getRawOriginal(), $evidence, $reason];
            $fingerprint = hash_hmac('sha256', json_encode($state, JSON_THROW_ON_ERROR), (string) config('app.key'));
            if ($confirmation === null) {
                $expires = now()->addMinutes(10)->timestamp;
                $material = $actor->id.':'.$expires.':'.$fingerprint;
                return array_replace($result, ['confirmation' => $expires.'.'.hash_hmac('sha256', $material, (string) config('app.key'))]);
            }
            [$expires, $mac] = array_pad(explode('.', $confirmation, 2), 2, '');
            $this->require(ctype_digit($expires) && (int) $expires >= now()->timestamp && (int) $expires <= now()->addMinutes(10)->timestamp
                && hash_equals(hash_hmac('sha256', $actor->id.':'.$expires.':'.$fingerprint, (string) config('app.key')), $mac), 'The preview expired or changed. Review a fresh preview before applying.');
            FinanceMutationScope::run('individual_payfast_reconciliation', function () use ($entry, $actor, $invitation, $order, $registration, $reset, $evidence, $paidAt, $reason, $digest, $handoff, $expiry, $event): void {
                app(EntryService::class)->restoreReconciledExpiredEntry($entry, $actor);
                app(InvitationService::class)->restoreReconciledExpiredOrder($invitation, $order, $registration->id, $reset->before['accepted_at'], $actor);
                $paid = app(RegistrationPaymentService::class)->completeReconciledExpiredPayment($order, $actor, (float) $evidence['gross'], $evidence['provider_id']);
                app(InvitationService::class)->confirmPaidOrder($paid, $paidAt);
                app(PaymentTransactionService::class)->recordAttestedRegistrationReceipt($paid->load('items.category_event.event'),
                    array_merge($evidence, ['cape_tennis_fee_snapshot' => $event->cape_tennis_fee]), $paidAt);
                activity('payment')->performedOn($paid)->causedBy($actor)->withProperties(array_merge([
                    'evidence_source' => $evidence['source'], 'evidence_digest' => $digest, 'provider_evidence' => $evidence,
                    'reason' => $reason, 'handoff_audit_id' => $handoff->id, 'expiry_audit_id' => $expiry->id,
                    'reset_audit_id' => $reset->id, 'entry_id' => $entry->id, 'invitation_id' => $invitation->id,
                    'reviewed_platform_fee' => $event->cape_tennis_fee, 'platform_fee_source' => 'current_event_configuration_at_review',
                    'pf_payment_id' => $evidence['provider_id'], 'new_charge' => false, 'wallet_debit' => false, 'mail' => false,
                ], $this->reviewContext($evidence)))->log(self::AUDIT);
            });
            return array_replace($result, ['applied' => true]);
        }, 3);
    }

    private function evidence(array $evidence, User $actor, string $reason): array
    {
        $reconfirmed = ($evidence['source'] ?? null) === self::RECONFIRMED_SOURCE;
        $data = Validator::make($evidence, [
            'source' => ['required', 'in:authenticated_payfast_dashboard,'.self::RECONFIRMED_SOURCE], 'attested' => ['required', 'accepted'],
            'verified_by' => ['required', 'integer', 'in:'.$actor->id],
            'verified_at' => $reconfirmed ? ['missing'] : ['required', 'date_format:Y-m-d\TH:i:sP'],
            'observed_on' => $reconfirmed ? ['required', 'date_format:Y-m-d'] : ['missing'],
            'operator_reviewed_at' => $reconfirmed ? ['required', 'date_format:Y-m-d\TH:i:sP'] : ['missing'],
            'unchanged_payment_confirmed' => $reconfirmed ? ['required', 'accepted'] : ['missing'],
            'owner_confirmation_reference' => $reconfirmed ? ['required', 'string', 'min:10', 'max:200'] : ['missing'],
            'provider_status' => ['required', 'in:successful'], 'currency' => ['required', 'in:ZAR'],
            'provider_id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{3,120}$/'],
            'order_id' => ['required', 'integer', 'min:1'], 'payer_id' => ['required', 'integer', 'min:1'],
            'player_id' => ['required', 'integer', 'min:1'], 'category_event_id' => ['required', 'integer', 'min:1'], 'event_id' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date_format:Y-m-d\TH:i:sP'],
            'gross' => ['required', 'string', 'regex:/^\d{1,8}\.\d{2}$/'], 'fee' => ['required', 'string', 'regex:/^\d{1,8}\.\d{2}$/'],
            'net' => ['required', 'string', 'regex:/^\d{1,8}\.\d{2}$/'],
        ])->validate();
        $this->require($this->cents($data['gross']) > 0 && $this->cents($data['gross']) - $this->cents($data['fee']) === $this->cents($data['net']), 'Provider fee and net must reconcile exactly to gross.');
        if ($reconfirmed) {
            $paidOn = CarbonImmutable::parse($data['paid_at'])->setTimezone(config('app.timezone'))->toDateString();
            $reviewed = CarbonImmutable::parse($data['operator_reviewed_at']);
            $this->require($data['observed_on'] === now()->toDateString() && $data['observed_on'] === $paidOn,
                'The authenticated dashboard observation, original payment and operator review must be on the same application date.');
            $this->require($reviewed->lte(now()) && $reviewed->gte(now()->subMinutes(10))
                && $reviewed->setTimezone(config('app.timezone'))->toDateString() === $data['observed_on']
                && $reviewed->gte(CarbonImmutable::parse($data['paid_at'])),
                'The operator must reconfirm the unchanged payment within ten minutes of this review.');
            $this->require(str_contains($reason, $data['owner_confirmation_reference']),
                'The reviewed reason must reference the explicit owner confirmation.');
        } else {
            $verified = CarbonImmutable::parse($data['verified_at']);
            $this->require($verified->lte(now()->addSeconds(5)) && $verified->gte(now()->subMinutes(30)), 'Fresh authenticated dashboard verification is required.');
        }
        ksort($data);
        return $data;
    }

    private function reviewContext(array $evidence): array
    {
        if ($evidence['source'] === self::RECONFIRMED_SOURCE) {
            return ['provider_freshly_rechecked' => false,
                'evidence_notice' => 'PayFast was NOT freshly rechecked. This uses a same-day authenticated dashboard observation and current operator reconfirmation.',
                'observed_on' => $evidence['observed_on'], 'operator_reviewed_at' => $evidence['operator_reviewed_at'],
                'unchanged_payment_confirmed' => true, 'owner_confirmation_reference' => $evidence['owner_confirmation_reference']];
        }
        return ['provider_freshly_rechecked' => true, 'verified_at' => $evidence['verified_at']];
    }

    private function cents(mixed $value): int { return (int) round((float) $value * 100); }
    private function require(bool $condition, string $message): void
    {
        if (! $condition) throw ValidationException::withMessages(['reconciliation' => $message]);
    }
}
