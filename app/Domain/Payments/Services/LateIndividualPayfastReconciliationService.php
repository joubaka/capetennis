<?php

namespace App\Domain\Payments\Services;

use App\Domain\Entries\Services\{EntryEligibilityService, EntryService};
use App\Models\{AuditEvent, CategoryEvent, CategoryEventRegistration, Event, EventNomination, Player, Registration, RegistrationOrder, RegistrationOrderItems, Transaction, User, WalletTransaction};
use App\Services\DivisionCapacityService;
use App\Support\Audit\AuditIntegrity;
use App\Support\FinanceMutationScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/** Explicit accounting recovery of an existing verified late ITN receipt; never an automatic callback override. */
final class LateIndividualPayfastReconciliationService
{
    public const AUDIT = 'Verified late individual PayFast receipt reconciled to original registration';

    public function run(int $orderId, User $actor, string $providerId, string $reason, ?string $confirmation = null): array
    {
        abort_unless($actor->fresh()?->hasRole('super-user'), 403);
        $this->require(preg_match('/^[A-Za-z0-9_-]{3,120}$/', $providerId) === 1 && trim($reason) !== '' && strlen($reason) <= 500,
            'An exact provider reference and reviewed reason of at most 500 characters are required.');

        return DB::transaction(function () use ($orderId, $actor, $providerId, $reason, $confirmation): array {
            $candidate = RegistrationOrderItems::where('order_id', $orderId)->sole();
            $eventId = CategoryEvent::whereKey($candidate->category_event_id)->value('event_id');
            $event = Event::with('eventTypeModel')->lockForUpdate()->findOrFail($eventId);
            $category = CategoryEvent::with('event')->lockForUpdate()->findOrFail($candidate->category_event_id);
            $player = Player::lockForUpdate()->findOrFail($candidate->player_id);
            $nominations = EventNomination::where('event_id', $event->id)->where('category_event_id', $category->id)
                ->where('player_id', $player->id)->lockForUpdate()->get();
            $order = RegistrationOrder::lockForUpdate()->findOrFail($orderId);
            $items = $order->items()->lockForUpdate()->get();
            $this->require($items->count() === 1 && $event->isIndividual() && ! $event->isMasters() && ! $event->isInterprovincialTrials(),
                'Only an ordinary single-player individual checkout is supported.');
            $item = $items->sole();
            $registration = Registration::lockForUpdate()->findOrFail($item->registration_id);
            $entries = CategoryEventRegistration::withTrashed()->where('registration_id', $registration->id)->lockForUpdate()->get();
            $this->require($entries->count() === 1, 'The original registration must contain exactly one entry.');
            $entry = $entries->sole();
            $players = DB::table('player_registrations')->where('registration_id', $registration->id)->lockForUpdate()->pluck('player_id');
            $this->require((int) $item->id === (int) $candidate->id && (int) $category->event_id === (int) $event->id
                && (int) $item->category_event_id === (int) $category->id && (int) $item->player_id === (int) $player->id
                && (int) $item->user_id === (int) $order->user_id && (int) $entry->user_id === (int) $order->user_id
                && (int) $entry->category_event_id === (int) $category->id && $order->user
                && $players->count() === 1 && (int) $players->sole() === (int) $player->id,
                'Original payer, player, registration, category and event relationships must match.');

            $activities = Activity::where('subject_type', $order->getMorphClass())->where('subject_id', $orderId)->lockForUpdate()->get();
            $handoffs = $activities->where('description', 'Expired unresolved PayFast handoff retained for reconciliation');
            $expiries = $activities->where('description', 'Unpaid registration checkout expired; reservation released');
            $lateReceipts = $activities->where('description', 'Expired PayFast charge requires reconciliation');
            $reconciliations = $activities->where('description', self::AUDIT);
            $this->require($handoffs->count() === 1 && $expiries->count() === 1 && $lateReceipts->count() === 1 && $reconciliations->count() <= 1,
                'Unique canonical handoff, automatic expiry and verified late receipt evidence is required.');
            $handoff = $handoffs->sole(); $expiry = $expiries->sole(); $late = $lateReceipts->sole();
            $snapshot = $handoff->properties;
            foreach (['payer_id' => $order->user_id, 'event_id' => $event->id, 'category_event_id' => $category->id, 'player_id' => $player->id] as $key => $value) {
                $this->require((string) $snapshot[$key] === (string) $value, 'Immutable handoff identity differs from the original checkout.');
            }
            $minutes = (int) $expiry->properties['timeout_minutes'];
            $handedAt = CarbonImmutable::parse($snapshot['handed_off_at']);
            $this->require(in_array($minutes, [5, 30], true) && (int) $expiry->properties['payer_id'] === (int) $order->user_id
                && $expiry->created_at->gte($handedAt->addMinutes($minutes)) && $late->created_at->gte($expiry->created_at)
                && (string) $late->properties['pf_payment_id'] === $providerId
                && (int) $late->properties['payer_id'] === (int) $order->user_id
                && (bool) $late->properties['reconciliation_required'], 'Historical expiry or verified late receipt provenance is inconsistent.');
            $receipts = Transaction::where(fn ($query) => $query->where('pf_payment_id', $providerId)
                ->orWhere(fn ($query) => $query->where('custom_int5', $orderId)->where(fn ($blank) => $blank->whereNull('custom_str5')->orWhere('custom_str5', ''))))
                ->lockForUpdate()->get();
            $this->require($receipts->count() === 1, 'Exactly one original real PayFast receipt is required.');
            $receipt = $receipts->sole();
            $this->require((string) $receipt->pf_payment_id === $providerId && ! $receipt->is_test && blank($receipt->custom_str5)
                && (int) $receipt->custom_int1 === (int) $category->id && (int) $receipt->category_event_id === (int) $category->id
                && (int) $receipt->custom_int2 === (int) $player->id && (int) $receipt->player_id === (int) $player->id
                && (int) $receipt->custom_int3 === (int) $event->id && (int) $receipt->event_id === (int) $event->id
                && (int) $receipt->custom_int4 === (int) $order->user_id && (int) $receipt->custom_int5 === $orderId
                && $this->cents($receipt->amount_gross) > 0
                && $this->cents($receipt->amount_gross) === $this->cents($snapshot['expected_provider_amount'])
                && $this->cents($receipt->amount_gross) === $this->cents($late->properties['amount'])
                && $this->cents($receipt->amount_gross) === $this->cents($item->item_price)
                && $this->cents($receipt->amount_gross) === $this->cents($order->total_fee)
                && $this->cents($receipt->amount_fee) >= 0
                && $this->cents($receipt->amount_net) >= 0
                && $this->cents($receipt->amount_gross) - $this->cents($receipt->amount_fee) === $this->cents($receipt->amount_net),
                'Receipt identity, exact gross or stored configured fee/net evidence is inconsistent.');
            $this->require((float) $snapshot['reserved_wallet_amount'] === 0.0 && (float) $order->wallet_reserved === 0.0
                && ! $order->wallet_debited && ! $order->wallet_transaction_id
                && ! WalletTransaction::where('source_type', 'event_registration_wallet_payment')->where('source_id', $orderId)->lockForUpdate()->exists(),
                'Wallet-funded attempts require separate review.');
            $this->assertNoConflicts($order, $item, $entry, $event);
            $otherIds = RegistrationOrderItems::where('player_id', $item->player_id)->where('order_id', '!=', $orderId)
                ->whereHas('category_event', fn ($q) => $q->where('event_id', $event->id))->lockForUpdate()->pluck('order_id')->unique()->sort()->values()->all();
            $preserved = $this->preservedUnsentAttempts($otherIds, $order, $item, $expiry);
            $immutableReceipt = $this->receiptDigest($receipt);
            $result = ['order_id' => $orderId, 'registration_id' => (int) $registration->id, 'entry_id' => (int) $entry->id,
                'player_id' => (int) $player->id, 'category_event_id' => (int) $category->id, 'event_id' => (int) $event->id,
                'payer_id' => (int) $order->user_id, 'provider_id' => $providerId, 'receipt_id' => (int) $receipt->id,
                'evidence_source' => 'canonical_verified_late_itn_receipt', 'original_expiry_minutes' => $minutes,
                'receipt_recorded_at' => $receipt->created_at->toIso8601String(), 'gross' => $receipt->amount_gross,
                'stored_configured_fee_estimate' => $receipt->amount_fee, 'stored_configured_net_estimate' => $receipt->amount_net,
                'preserved_never_handed_off_order_ids' => $preserved['ids'], 'other_attempts_unchanged' => true,
                'receipt_amounts_and_date_unchanged' => true, 'reviewed_current_platform_fee' => $event->cape_tennis_fee,
                'platform_fee_source' => 'current_event_configuration_at_review', 'applied' => false, 'already_reconciled' => false,
                'new_receipt' => false, 'new_charge' => false, 'wallet_debit' => false, 'mail' => false];

            if ($reconciliations->isNotEmpty()) {
                $audit = $reconciliations->sole();
                $this->require((string) $audit->properties['pf_payment_id'] === $providerId
                    && (int) $audit->properties['receipt_id'] === (int) $receipt->id
                    && (int) $audit->properties['registration_id'] === (int) $registration->id
                    && $audit->properties['immutable_receipt_digest'] === $immutableReceipt
                    && $this->cents($receipt->cape_tennis_fee) === $this->cents($audit->properties['reviewed_platform_fee'])
                    && $order->status === 'completed' && $order->pay_status && $order->payfast_paid
                    && (string) $order->payfast_pf_payment_id === $providerId && ! $entry->trashed()
                    && $entry->status === 'active' && (int) $entry->payment_status_id === 1
                    && (string) $entry->pf_transaction_id === $providerId && $entry->payment_method === 'payfast',
                    'Previously reconciled original records changed and require separate review.');
                return array_replace($result, ['already_reconciled' => true]);
            }
            $this->require($order->status === 'cancelled' && ! $order->pay_status && ! $order->payfast_paid
                && ! $order->payfast_pf_payment_id && ! $order->payfast_handed_off_at && (float) $order->payfast_amount_due === 0.0
                && $entry->trashed() && $entry->status === 'active' && ! $entry->payment_status_id
                && ! $entry->pf_transaction_id && ! $entry->wallet_transaction_id && $this->cents($receipt->cape_tennis_fee) === 0
                && $entry->deleted_at->format('Y-m-d H:i:s') === $expiry->created_at->format('Y-m-d H:i:s')
                && $entry->updated_at->format('Y-m-d H:i:s') === $expiry->created_at->format('Y-m-d H:i:s')
                && $order->updated_at->format('Y-m-d H:i:s') === $expiry->created_at->format('Y-m-d H:i:s'),
                'Only unchanged unpaid original records removed by automatic expiry may be restored.');
            $history = AuditEvent::where(fn ($q) => $q->where(fn ($q) => $q->where('subject_type', $order->getMorphClass())->where('subject_id', $orderId))
                ->orWhere(fn ($q) => $q->where('subject_type', $entry->getMorphClass())->where('subject_id', $entry->id)))->lockForUpdate()->get();
            $this->require($history->where('subject_type', $order->getMorphClass())->where('subject_id', $orderId)->isNotEmpty()
                && $history->where('subject_type', $entry->getMorphClass())->where('subject_id', $entry->id)->isNotEmpty()
                && $history->every(fn ($audit) => AuditIntegrity::verify($audit->getRawOriginal())),
                'Integrity-verifiable original order and entry history is required.');
            foreach ($history as $audit) {
                foreach ([$audit->before ?? [], $audit->after ?? []] as $snapshot) $this->assertUnfundedSnapshot($snapshot);
            }
            $this->require($event->published && $event->registrationIsOpen(), 'The published event registration window must remain open.');
            $this->require($this->cents($event->cape_tennis_fee) >= 0 && $this->cents($event->cape_tennis_fee) <= $this->cents($receipt->amount_gross),
                'Current platform fee requires separate review.');
            app(EntryEligibilityService::class)->assertCanRegister($category, (int) $player->id);
            app(DivisionCapacityService::class)->assertAvailable($category);
            $state = [$order->getRawOriginal(), $item->getRawOriginal(), $registration->getRawOriginal(), $entry->getRawOriginal(),
                $event->getRawOriginal(), $category->getRawOriginal(), $player->getRawOriginal(), $players->all(), $receipt->getRawOriginal(),
                $handoff->getRawOriginal(), $expiry->getRawOriginal(), $late->getRawOriginal(), $history->map(fn ($a) => $a->getRawOriginal())->all(),
                $preserved['state'], $nominations->map(fn ($n) => $n->getRawOriginal())->all(), $providerId, $reason];
            $fingerprint = hash_hmac('sha256', json_encode($state, JSON_THROW_ON_ERROR), (string) config('app.key'));
            if ($confirmation === null) {
                $expires = now()->addMinutes(10)->timestamp;
                return array_replace($result, ['confirmation' => $expires.'.'.hash_hmac('sha256', $actor->id.':'.$expires.':'.$fingerprint, (string) config('app.key'))]);
            }
            [$expires, $mac] = array_pad(explode('.', $confirmation, 2), 2, '');
            $this->require(ctype_digit($expires) && (int) $expires >= now()->timestamp && (int) $expires <= now()->addMinutes(10)->timestamp
                && hash_equals(hash_hmac('sha256', $actor->id.':'.$expires.':'.$fingerprint, (string) config('app.key')), $mac),
                'The preview expired or changed. Review a fresh preview before applying.');
            FinanceMutationScope::run('individual_payfast_reconciliation', function () use ($entry, $actor, $order, $receipt, $providerId, $reason, $immutableReceipt, $handoff, $expiry, $late, $event, $registration, $preserved): void {
                app(EntryService::class)->restoreReconciledLateIndividualEntry($entry, $actor);
                $paid = app(RegistrationPaymentService::class)->completeReconciledExpiredPayment($order, $actor, (float) $receipt->amount_gross, $providerId);
                app(PaymentTransactionService::class)->classifyReconciledIndividualReceipt($receipt, $paid->load('items.category_event.event'));
                activity('payment')->performedOn($paid)->causedBy($actor)->withProperties([
                    'evidence_source' => 'canonical_verified_late_itn_receipt', 'reason' => $reason,
                    'pf_payment_id' => $providerId, 'receipt_id' => $receipt->id, 'immutable_receipt_digest' => $immutableReceipt,
                    'registration_id' => $registration->id, 'entry_id' => $entry->id,
                    'handoff_audit_id' => $handoff->id, 'expiry_audit_id' => $expiry->id, 'late_receipt_audit_id' => $late->id,
                    'reviewed_platform_fee' => $event->cape_tennis_fee, 'platform_fee_source' => 'current_event_configuration_at_review',
                    'stored_configured_fee_estimate' => $receipt->amount_fee, 'stored_configured_net_estimate' => $receipt->amount_net,
                    'preserved_never_handed_off_order_ids' => $preserved['ids'], 'other_attempts_unchanged' => true,
                    'new_receipt' => false, 'new_charge' => false, 'wallet_debit' => false, 'mail' => false,
                ])->log(self::AUDIT);
            });
            return array_replace($result, ['applied' => true]);
        }, 3);
    }

    private function assertNoConflicts(RegistrationOrder $order, RegistrationOrderItems $item, CategoryEventRegistration $entry, Event $event): void
    {
        $this->require(! $entry->withdrawn_at && ! $entry->withdrawn_by && ! $entry->refunded_at && ! $entry->refund_waived_at
            && ! $entry->refund_waived_by && ! $entry->refund_method && ! $entry->refund_waiver_reason
            && ! $entry->refund_gross && ! $entry->refund_fee && ! $entry->refund_net
            && ($entry->refund_status === null || in_array($entry->refund_status, CategoryEventRegistration::NO_REFUND_STATUSES, true))
            && $entry->admin_payment_status !== 'paid', 'Withdrawal, refund or private collection history requires separate review.');
        foreach (['registration_manual_receipts', 'trial_payment_proofs', 'registration_payment_recoveries'] as $table) {
            $column = $table === 'registration_payment_recoveries' ? 'registration_order_id' : 'order_id';
            $this->require(! DB::table($table)->where($column, $order->id)->lockForUpdate()->exists(), 'Manual, EFT or recovery history requires separate review.');
        }
        $this->require(! CategoryEventRegistration::where('id', '!=', $entry->id)->whereHas('categoryEvent', fn ($q) => $q->where('event_id', $event->id))
                ->whereHas('registration.players', fn ($q) => $q->where('players.id', $item->player_id))->lockForUpdate()->exists(),
            'Another checkout or active entry exists for this player in the event.');
        $this->require(! DB::table('withdrawals')->where('registration_id', $item->registration_id)->lockForUpdate()->exists()
            && ! DB::table('category_adjustments')->where('entry_id', $entry->id)->lockForUpdate()->exists()
            && ! DB::table('draw_registrations')->where('registration_id', $item->registration_id)->lockForUpdate()->exists()
            && ! DB::table('draws')->where('category_event_id', $item->category_event_id)->lockForUpdate()->exists()
            && ! DB::table('fixtures')->where(fn ($q) => $q->where('registration1_id', $item->registration_id)->orWhere('registration2_id', $item->registration_id))->lockForUpdate()->exists(),
            'Withdrawal, adjustment or competition history requires separate review.');
    }

    /** Keep disjoint, demonstrably unpaid attempts intact; overlapping or handed-off history remains blocked. */
    private function preservedUnsentAttempts(array $orderIds, RegistrationOrder $original, RegistrationOrderItems $originalItem, Activity $originalExpiry): array
    {
        $state = [];
        foreach ($orderIds as $orderId) {
            $other = RegistrationOrder::lockForUpdate()->findOrFail($orderId);
            $items = $other->items()->lockForUpdate()->get();
            $this->require($items->count() === 1, 'A competing multi-item checkout requires separate review.');
            $item = $items->sole();
            $this->require((int) $other->user_id === (int) $original->user_id && (int) $item->user_id === (int) $original->user_id
                && (int) $item->player_id === (int) $originalItem->player_id && (int) $item->category_event_id === (int) $originalItem->category_event_id
                && (int) $item->registration_id !== (int) $originalItem->registration_id
                && $other->status === 'cancelled' && ! $other->pay_status && ! $other->payfast_paid
                && ! $other->payfast_handed_off_at && ! $other->payfast_pf_payment_id && (float) $other->payfast_amount_due === 0.0
                && (float) $other->wallet_reserved === 0.0 && ! $other->wallet_debited && ! $other->wallet_transaction_id
                && $this->cents($other->total_fee) === $this->cents($item->item_price), 'Another attempt is funded, active, handed off or has a different original tuple.');
            $activities = Activity::where('subject_type', $other->getMorphClass())->where('subject_id', $other->id)->lockForUpdate()->get();
            $expiry = $activities->count() === 1 ? $activities->sole() : null;
            $minutes = (int) ($expiry?->properties['timeout_minutes'] ?? 0);
            $this->require($expiry && $expiry->description === 'Unpaid registration checkout expired; reservation released'
                && in_array($minutes, [5, 30], true) && (int) $expiry->properties['payer_id'] === (int) $original->user_id
                && $expiry->created_at->gte($other->created_at->copy()->addMinutes($minutes))
                && (($other->id < $original->id && $expiry->created_at->lt($original->created_at))
                    || ($other->id > $original->id && $other->created_at->gt($originalExpiry->created_at)))
                && $other->updated_at->format('Y-m-d H:i:s') === $expiry->created_at->format('Y-m-d H:i:s'),
                'Another attempt lacks unique unchanged automatic expiry outside the original checkout interval.');
            $registration = Registration::lockForUpdate()->findOrFail($item->registration_id);
            $players = DB::table('player_registrations')->where('registration_id', $registration->id)->lockForUpdate()->pluck('player_id');
            $entries = CategoryEventRegistration::withTrashed()->where('registration_id', $registration->id)->lockForUpdate()->get();
            $entry = $entries->count() === 1 ? $entries->sole() : null;
            $this->require($entry && (int) $entry->user_id === (int) $original->user_id && (int) $entry->category_event_id === (int) $originalItem->category_event_id
                && $players->count() === 1 && (int) $players->sole() === (int) $originalItem->player_id
                && $entry->trashed() && $entry->status === 'active' && ! $entry->payment_status_id && ! $entry->pf_transaction_id && ! $entry->wallet_transaction_id
                && $entry->deleted_at->format('Y-m-d H:i:s') === $expiry->created_at->format('Y-m-d H:i:s')
                && $entry->updated_at->format('Y-m-d H:i:s') === $expiry->created_at->format('Y-m-d H:i:s')
                && ! $entry->withdrawn_at && ! $entry->withdrawn_by && ! $entry->refunded_at && ! $entry->refund_waived_at
                && ! $entry->refund_waived_by && ! $entry->refund_method && ! $entry->refund_waiver_reason
                && ! $entry->refund_gross && ! $entry->refund_fee && ! $entry->refund_net && $entry->admin_payment_status !== 'paid'
                && ($entry->refund_status === null || in_array($entry->refund_status, CategoryEventRegistration::NO_REFUND_STATUSES, true)),
                'Another attempt has changed entry, payment, withdrawal or refund history.');
            $history = AuditEvent::where(fn ($q) => $q->where(fn ($q) => $q->where('subject_type', $other->getMorphClass())->where('subject_id', $other->id))
                ->orWhere(fn ($q) => $q->where('subject_type', $entry->getMorphClass())->where('subject_id', $entry->id)))->lockForUpdate()->get();
            $this->require($history->where('subject_type', $other->getMorphClass())->where('subject_id', $other->id)->isNotEmpty()
                && $history->where('subject_type', $entry->getMorphClass())->where('subject_id', $entry->id)->isNotEmpty(),
                'Another attempt lacks complete integrity-verifiable order and entry history.');
            foreach ($history as $audit) {
                $this->require(AuditIntegrity::verify($audit->getRawOriginal()), 'Another attempt has tampered historical evidence.');
                foreach ([$audit->before ?? [], $audit->after ?? []] as $snapshot) {
                    $this->assertUnfundedSnapshot($snapshot);
                    $this->require(empty($snapshot['payfast_handed_off_at'])
                        && ! in_array($snapshot['status'] ?? null, ['completed', 'reconciliation_required'], true),
                        'Another attempt has historical handoff, settlement or reversal evidence.');
                }
            }
            foreach (['registration_manual_receipts', 'trial_payment_proofs', 'registration_payment_recoveries'] as $table) {
                $column = $table === 'registration_payment_recoveries' ? 'registration_order_id' : 'order_id';
                $this->require(! DB::table($table)->where($column, $other->id)->lockForUpdate()->exists(), 'Another attempt has manual, proof or recovery evidence.');
            }
            $this->require(! Transaction::where('custom_int5', $other->id)->where(fn ($q) => $q->whereNull('custom_str5')->orWhere('custom_str5', ''))->lockForUpdate()->exists()
                && ! WalletTransaction::where('source_type', 'event_registration_wallet_payment')->where('source_id', $other->id)->lockForUpdate()->exists()
                && ! DB::table('withdrawals')->where('registration_id', $registration->id)->lockForUpdate()->exists()
                && ! DB::table('category_adjustments')->where('entry_id', $entry->id)->lockForUpdate()->exists()
                && ! DB::table('draw_registrations')->where('registration_id', $registration->id)->lockForUpdate()->exists()
                && ! DB::table('fixtures')->where(fn ($q) => $q->where('registration1_id', $registration->id)->orWhere('registration2_id', $registration->id))->lockForUpdate()->exists(),
                'Another attempt has financial or competition history.');
            $state[] = [$other->getRawOriginal(), $item->getRawOriginal(), $registration->getRawOriginal(), $entry->getRawOriginal(),
                $players->all(), $expiry->getRawOriginal(), $history->map(fn ($a) => $a->getRawOriginal())->all()];
        }
        return ['ids' => array_map('intval', $orderIds), 'state' => $state];
    }

    private function receiptDigest(Transaction $receipt): string
    {
        $raw = $receipt->getRawOriginal();
        unset($raw['cape_tennis_fee'], $raw['updated_at']);
        ksort($raw);
        return hash('sha256', json_encode($raw, JSON_THROW_ON_ERROR));
    }

    private function cents(mixed $value): int
    {
        $this->require(is_numeric($value) && is_finite((float) $value)
            && preg_match('/^-?\d+(?:\.\d{1,2})?$/D', (string) $value) === 1, 'Exact numeric financial evidence with at most two decimal places is required.');
        return (int) round((float) $value * 100);
    }

    private function assertUnfundedSnapshot(array $snapshot): void
    {
        $this->require(empty($snapshot['payfast_pf_payment_id']) && empty($snapshot['pay_status']) && empty($snapshot['payfast_paid'])
            && empty($snapshot['wallet_debited']) && empty($snapshot['wallet_transaction_id']) && (float) ($snapshot['wallet_reserved'] ?? 0) === 0.0
            && empty($snapshot['payment_status_id']) && empty($snapshot['pf_transaction_id']) && empty($snapshot['withdrawn_at'])
            && empty($snapshot['withdrawn_by']) && empty($snapshot['refunded_at']) && empty($snapshot['refund_waived_at'])
            && empty($snapshot['refund_waived_by']) && empty($snapshot['refund_method']) && empty($snapshot['refund_waiver_reason'])
            && $this->zeroOrBlank($snapshot['refund_gross'] ?? null) && $this->zeroOrBlank($snapshot['refund_fee'] ?? null)
            && $this->zeroOrBlank($snapshot['refund_net'] ?? null)
            && (($snapshot['refund_status'] ?? null) === null || in_array($snapshot['refund_status'], CategoryEventRegistration::NO_REFUND_STATUSES, true))
            && ($snapshot['admin_payment_status'] ?? null) !== 'paid', 'Historical settlement, withdrawal, refund or private payment evidence requires separate review.');
    }

    private function zeroOrBlank(mixed $value): bool
    {
        return $value === null || $value === '' || $this->cents($value) === 0;
    }

    private function require(bool $condition, string $message): void
    {
        if (! $condition) throw ValidationException::withMessages(['reconciliation' => $message]);
    }
}
