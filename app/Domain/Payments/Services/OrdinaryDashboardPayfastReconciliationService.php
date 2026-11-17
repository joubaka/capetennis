<?php

namespace App\Domain\Payments\Services;

use App\Domain\Entries\Services\{EntryEligibilityService, EntryService};
use App\Models\{AuditEvent, CategoryEvent, CategoryEventRegistration, Event, EventNomination, Player, Registration, RegistrationOrder, RegistrationOrderItems, Transaction, User, WalletTransaction};
use App\Services\DivisionCapacityService;
use App\Support\Audit\AuditIntegrity;
use App\Support\FinanceMutationScope;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, Validator};
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/** Reviewed on-time dashboard evidence for an expired ordinary original basket, never a fabricated ITN. */
final class OrdinaryDashboardPayfastReconciliationService
{
    public const AUDIT = 'On-time ordinary individual PayFast settlement reconciled from authenticated dashboard';

    public function run(int $orderId, User $actor, array $evidence, string $reason, ?string $confirmation = null): array
    {
        abort_unless($actor->fresh()?->hasRole('super-user'), 403);
        $evidence = $this->evidence($evidence, $actor);
        $this->require(trim($reason) !== '' && strlen($reason) <= 500, 'A reviewed reason of at most 500 characters is required.');
        return DB::transaction(function () use ($orderId, $actor, $evidence, $reason, $confirmation): array {
            $candidates = RegistrationOrderItems::where('order_id', $orderId)->orderBy('id')->get();
            $this->require($candidates->isNotEmpty() && $candidates->count() <= 20, 'Only an original basket of one to twenty paid individual items is supported.');
            $event = Event::with('eventTypeModel')->lockForUpdate()->findOrFail(CategoryEvent::whereKey($candidates->first()->category_event_id)->value('event_id'));
            $this->require($event->isIndividual() && ! $event->isMasters() && ! $event->isInterprovincialTrials(), 'Only an ordinary individual event is supported.');
            $categories = CategoryEvent::whereIn('id', $candidates->pluck('category_event_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $players = Player::whereIn('id', $candidates->pluck('player_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $order = RegistrationOrder::lockForUpdate()->findOrFail($orderId);
            $items = $order->items()->orderBy('id')->lockForUpdate()->get();
            $this->require($items->pluck('id')->all() === $candidates->pluck('id')->all() && $items->pluck('player_id')->unique()->count() === $items->count()
                && $items->pluck('registration_id')->unique()->count() === $items->count() && $players->count() === $items->count()
                && $categories->every(fn ($category) => (int) $category->event_id === (int) $event->id)
                && (int) $evidence['order_id'] === $orderId && (int) $evidence['payer_id'] === (int) $order->user_id
                && (int) $evidence['event_id'] === (int) $event->id && $order->user,
                'The original basket, payer, event, distinct players and registrations must match.');
            $activities = Activity::where('subject_type', $order->getMorphClass())->where('subject_id', $orderId)->lockForUpdate()->get();
            $handoffs = $activities->where('description', 'Expired unresolved PayFast handoff retained for reconciliation');
            $expiries = $activities->where('description', 'Unpaid registration checkout expired; reservation released');
            $reconciliations = $activities->where('description', self::AUDIT);
            $this->require($handoffs->count() === 1 && $expiries->count() === 1 && $reconciliations->count() <= 1
                && $activities->where('description', 'Expired PayFast charge requires reconciliation')->isEmpty(),
                'Unique original handoff and automatic expiry without an existing late receipt are required.');
            $handoff = $handoffs->sole(); $expiry = $expiries->sole(); $snapshot = $handoff->properties;
            $minutes = (int) $expiry->properties['timeout_minutes'];
            $paidAt = CarbonImmutable::parse($evidence['paid_at'])->setTimezone(config('app.timezone'));
            $handedAt = CarbonImmutable::parse($snapshot['handed_off_at']);
            $this->require(in_array($minutes, [5, 30], true) && (int) $expiry->properties['payer_id'] === (int) $order->user_id
                && $expiry->created_at->gte($handedAt->addMinutes($minutes)) && $order->created_at->lte($handedAt)
                && $paidAt->gte($handedAt) && $paidAt->lt($handedAt->addMinutes($minutes)) && $paidAt->lt($expiry->created_at),
                'Payment must be on time inside the original recorded checkout window.');
            foreach (['payer_id' => $order->user_id, 'event_id' => $event->id, 'category_event_id' => $evidence['category_event_id'], 'player_id' => $evidence['player_id']] as $field => $value) {
                $this->require((string) $snapshot[$field] === (string) $value, 'Provider metadata does not match the immutable original handoff anchor.');
            }
            $anchor = $items->filter(fn ($item) => (int) $item->category_event_id === (int) $evidence['category_event_id']
                && (int) $item->player_id === (int) $evidence['player_id']);
            $this->require($anchor->count() === 1, 'Provider category and player must identify the same original basket item.');
            $total = $items->sum(fn ($item) => $this->cents($item->item_price));
            $this->require($items->every(fn ($item) => $this->cents($item->item_price) > 0)
                && $total === $this->cents($order->total_fee) && $total === $this->cents($snapshot['expected_provider_amount'])
                && $total === $this->cents($evidence['gross']) && $this->cents($snapshot['reserved_wallet_amount']) === 0
                && $this->cents($order->wallet_reserved) === 0 && ! $order->wallet_debited && ! $order->wallet_transaction_id
                && ! WalletTransaction::where('source_type', 'event_registration_wallet_payment')->where('source_id', $orderId)->lockForUpdate()->exists(),
                'Exact original paid item prices and full PayFast funding without wallet history are required.');
            foreach (['registration_manual_receipts', 'trial_payment_proofs', 'registration_payment_recoveries'] as $table) {
                $column = $table === 'registration_payment_recoveries' ? 'registration_order_id' : 'order_id';
                $this->require(! DB::table($table)->where($column, $orderId)->lockForUpdate()->exists(), 'Manual, EFT or recovery history requires separate review.');
            }
            $receipts = Transaction::where(fn ($q) => $q->where('pf_payment_id', $evidence['provider_id'])
                ->orWhere(fn ($q) => $q->where('custom_int5', $orderId)->where(fn ($b) => $b->whereNull('custom_str5')->orWhere('custom_str5', ''))))->lockForUpdate()->get();
            $complete = $reconciliations->isNotEmpty();
            $registrations = Registration::whereIn('id', $items->pluck('registration_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $entries = CategoryEventRegistration::withTrashed()->whereIn('registration_id', $items->pluck('registration_id'))->orderBy('id')->lockForUpdate()->get();
            $this->require($registrations->count() === $items->count() && $entries->count() === $items->count(), 'Every original item must retain exactly one distinct entry and registration.');
            $history = collect(); $membership = []; $basket = [];
            foreach ($items as $item) {
                $entryRows = $entries->where('registration_id', $item->registration_id);
                $this->require($entryRows->count() === 1, 'The original item registration contains conflicting entries.');
                $entry = $entryRows->sole();
                $links = DB::table('player_registrations')->where('registration_id', $item->registration_id)->lockForUpdate()->pluck('player_id');
                $membership[$item->registration_id] = $links->all();
                $this->require((int) $item->user_id === (int) $order->user_id && (int) $entry->user_id === (int) $order->user_id
                    && (int) $entry->category_event_id === (int) $item->category_event_id && $links->count() === 1
                    && (int) $links->sole() === (int) $item->player_id && $item->created_at->lte($handedAt) && $item->updated_at->lte($handedAt),
                    'The original item, entry owner and one-player registration link changed.');
                $this->assertNoReversal($entry);
                $this->require(! CategoryEventRegistration::whereNotIn('id', $entries->pluck('id'))->whereHas('categoryEvent', fn ($q) => $q->where('event_id', $event->id))
                    ->whereHas('registration.players', fn ($q) => $q->where('players.id', $item->player_id))->lockForUpdate()->exists(),
                    'Another entry exists for an original basket player.');
                $this->require(! DB::table('withdrawals')->where('registration_id', $item->registration_id)->lockForUpdate()->exists()
                    && ! DB::table('category_adjustments')->where('entry_id', $entry->id)->lockForUpdate()->exists()
                    && ! DB::table('draw_registrations')->where('registration_id', $item->registration_id)->lockForUpdate()->exists()
                    && ! DB::table('fixtures')->where(fn ($q) => $q->where('registration1_id', $item->registration_id)->orWhere('registration2_id', $item->registration_id))->lockForUpdate()->exists(),
                    'Original withdrawal, adjustment or competition history requires separate review.');
                if (! $complete) {
                    $this->require($entry->trashed() && $entry->status === 'active' && ! $entry->payment_status_id
                        && ! $entry->pf_transaction_id && ! $entry->wallet_transaction_id
                        && $entry->deleted_at->format('Y-m-d H:i:s') === $expiry->created_at->format('Y-m-d H:i:s')
                        && $entry->updated_at->format('Y-m-d H:i:s') === $expiry->created_at->format('Y-m-d H:i:s'),
                        'Every original entry must remain unchanged and unpaid since automatic expiry.');
                    foreach ([$item, $registrations[$item->registration_id], $entry] as $subject) {
                        $audits = $this->history($subject);
                        $history = $history->merge($audits);
                        if ($subject instanceof RegistrationOrderItems) {
                            $created = $audits->filter(fn ($a) => str_ends_with($a->action, '.created') && $a->occurred_at->lte($handedAt)
                                && (int) ($a->after['order_id'] ?? 0) === $orderId
                                && (int) ($a->after['player_id'] ?? 0) === (int) $item->player_id
                                && (int) ($a->after['category_event_id'] ?? 0) === (int) $item->category_event_id
                                && (int) ($a->after['registration_id'] ?? 0) === (int) $item->registration_id
                                && $this->cents($a->after['item_price'] ?? -1) === $this->cents($item->item_price));
                            $this->require($created->count() === 1, 'Every original basket item needs an integrity-verified matching creation audit before handoff.');
                        }
                    }
                }
                $basket[] = ['item_id' => (int) $item->id, 'player_id' => (int) $item->player_id, 'category_event_id' => (int) $item->category_event_id,
                    'registration_id' => (int) $item->registration_id, 'entry_id' => (int) $entry->id, 'price' => number_format($this->cents($item->item_price) / 100, 2, '.', '')];
            }
            $otherIds = RegistrationOrderItems::where('order_id', '!=', $orderId)->whereIn('player_id', $items->pluck('player_id'))
                ->whereHas('category_event', fn ($q) => $q->where('event_id', $event->id))->orderBy('order_id')->lockForUpdate()->pluck('order_id')->unique()->values()->all();
            $preserved = $this->preservedPriorAttempts($otherIds, $order, $items, $event, $evidence, $paidAt);
            $digest = hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR));
            $platformFee = $this->cents($event->cape_tennis_fee) * $items->count();
            $this->require($platformFee >= 0 && $platformFee <= $total, 'The reviewed current platform fee requires separate review.');
            $result = ['order_id' => $orderId, 'payer_id' => (int) $order->user_id, 'event_id' => (int) $event->id, 'provider_id' => $evidence['provider_id'],
                'evidence_source' => $evidence['source'], 'provider_freshly_rechecked' => true, 'verified_at' => $evidence['verified_at'],
                'basket' => $basket, 'item_count' => $items->count(), 'gross' => $evidence['gross'], 'fee' => $evidence['fee'], 'net' => $evidence['net'],
                'paid_at' => $evidence['paid_at'], 'original_expiry_minutes' => $minutes,
                'preserved_prior_attempts' => $preserved['summary'], 'prior_attempts_unchanged' => true,
                'reviewed_current_platform_fee' => number_format($platformFee / 100, 2, '.', ''), 'platform_fee_source' => 'current_event_configuration_at_review_per_original_item',
                'applied' => false, 'already_reconciled' => false, 'new_charge' => false, 'wallet_debit' => false, 'mail' => false];
            if ($complete) {
                $audit = $reconciliations->sole(); $receipt = $receipts->count() === 1 ? $receipts->sole() : null;
                $this->require($receipt && $audit->properties['evidence_digest'] === $digest && $audit->properties['basket'] == $basket
                    && $order->status === 'completed' && $order->pay_status && $order->payfast_paid && (string) $order->payfast_pf_payment_id === $evidence['provider_id']
                    && (string) $receipt->pf_payment_id === $evidence['provider_id'] && ! $receipt->is_test && blank($receipt->custom_str5)
                    && (int) $receipt->custom_int5 === $orderId && (int) $receipt->custom_int4 === (int) $order->user_id
                    && (int) $receipt->custom_int1 === (int) $evidence['category_event_id'] && (int) $receipt->category_event_id === (int) $evidence['category_event_id']
                    && (int) $receipt->custom_int2 === (int) $evidence['player_id'] && (int) $receipt->player_id === (int) $evidence['player_id']
                    && (int) $receipt->custom_int3 === (int) $event->id && (int) $receipt->event_id === (int) $event->id
                    && $this->cents($receipt->amount_gross) === $total && $this->cents($receipt->amount_fee) === $this->cents($evidence['fee'])
                    && $this->cents($receipt->amount_net) === $this->cents($evidence['net']) && $receipt->created_at->equalTo($paidAt)
                    && $this->cents($receipt->cape_tennis_fee) === $this->cents($audit->properties['reviewed_platform_fee'])
                    && $entries->every(fn ($entry) => ! $entry->trashed() && $entry->status === 'active' && (int) $entry->payment_status_id === 1
                        && (string) $entry->pf_transaction_id === $evidence['provider_id'] && $entry->payment_method === 'payfast'),
                    'Previously reconciled original basket or receipt changed and requires separate review.');
                return array_replace($result, ['already_reconciled' => true]);
            }
            $this->require($receipts->isEmpty() && $order->status === 'cancelled' && ! $order->pay_status && ! $order->payfast_paid
                && ! $order->payfast_pf_payment_id && ! $order->payfast_handed_off_at && $this->cents($order->payfast_amount_due) === 0
                && $order->updated_at->format('Y-m-d H:i:s') === $expiry->created_at->format('Y-m-d H:i:s'),
                'Only an original unchanged automatic-expiry cancellation without any receipt can be recovered.');
            $history = $history->merge($this->history($order));
            $this->require($event->published && $event->registrationIsOpen(), 'The original event must be published and registration must remain open.');
            $this->require(! DB::table('draws')->whereIn('category_event_id', $categories->keys())->lockForUpdate()->exists(), 'A basket category already has a draw and requires separate review.');
            $nominations = EventNomination::where('event_id', $event->id)->whereIn('player_id', $items->pluck('player_id'))->lockForUpdate()->get();
            foreach ($categories as $category) {
                $category->setRelation('event', $event);
                $count = $items->where('category_event_id', $category->id)->count();
                $this->require($category->entry_limit === null || app(DivisionCapacityService::class)->occupied($category, true) + $count <= (int) $category->entry_limit,
                    'The original category does not have enough places for every original basket player.');
            }
            foreach ($items as $item) app(EntryEligibilityService::class)->assertCanRegister($categories[$item->category_event_id], (int) $item->player_id);
            $state = [$order->getRawOriginal(), $event->getRawOriginal(), $items->map(fn ($m) => $m->getRawOriginal())->all(),
                $categories->map(fn ($m) => $m->getRawOriginal())->all(), $players->map(fn ($m) => $m->getRawOriginal())->all(),
                $registrations->map(fn ($m) => $m->getRawOriginal())->all(), $entries->map(fn ($m) => $m->getRawOriginal())->all(), $membership,
                $handoff->getRawOriginal(), $expiry->getRawOriginal(), $history->map(fn ($a) => $a->getRawOriginal())->all(),
                $nominations->map(fn ($m) => $m->getRawOriginal())->all(), $preserved['state'], $evidence, $reason];
            $fingerprint = hash_hmac('sha256', json_encode($state, JSON_THROW_ON_ERROR), (string) config('app.key'));
            if ($confirmation === null) {
                $expires = now()->addMinutes(10)->timestamp;
                return array_replace($result, ['confirmation' => $expires.'.'.hash_hmac('sha256', $actor->id.':'.$expires.':'.$fingerprint, (string) config('app.key'))]);
            }
            [$expires, $mac] = array_pad(explode('.', $confirmation, 2), 2, '');
            $this->require(ctype_digit($expires) && (int) $expires >= now()->timestamp && (int) $expires <= now()->addMinutes(10)->timestamp
                && hash_equals(hash_hmac('sha256', $actor->id.':'.$expires.':'.$fingerprint, (string) config('app.key')), $mac), 'The preview expired or changed. Review a fresh preview before applying.');
            FinanceMutationScope::run('individual_payfast_reconciliation', function () use ($entries, $actor, $order, $evidence, $paidAt, $platformFee, $reason, $digest, $basket, $handoff, $expiry, $preserved): void {
                foreach ($entries as $entry) app(EntryService::class)->restoreReconciledLateIndividualEntry($entry, $actor);
                $paid = app(RegistrationPaymentService::class)->completeReconciledExpiredPayment($order, $actor, (float) $evidence['gross'], $evidence['provider_id']);
                $receipt = app(PaymentTransactionService::class)->recordAttestedOrdinaryRegistrationReceipt($paid->load('items.category_event.event'),
                    array_merge($evidence, ['cape_tennis_fee_snapshot' => $platformFee / 100]), $paidAt);
                activity('payment')->performedOn($paid)->causedBy($actor)->withProperties([
                    'evidence_source' => $evidence['source'], 'evidence_digest' => $digest, 'provider_evidence' => $evidence,
                    'basket' => $basket, 'receipt_id' => $receipt->id, 'pf_payment_id' => $evidence['provider_id'], 'reason' => $reason,
                    'handoff_audit_id' => $handoff->id, 'expiry_audit_id' => $expiry->id, 'reviewed_platform_fee' => $platformFee / 100,
                    'platform_fee_source' => 'current_event_configuration_at_review_per_original_item', 'new_charge' => false, 'wallet_debit' => false, 'mail' => false,
                    'preserved_prior_attempts' => $preserved['summary'], 'prior_attempts_unchanged' => true,
                ])->log(self::AUDIT);
            });
            return array_replace($result, ['applied' => true]);
        }, 3);
    }

    private function evidence(array $evidence, User $actor): array
    {
        $data = Validator::make($evidence, [
            'source' => ['required', 'in:authenticated_payfast_dashboard'], 'attested' => ['required', 'accepted'],
            'verified_by' => ['required', 'integer', 'in:'.$actor->id], 'verified_at' => ['required', 'date_format:Y-m-d\TH:i:sP'],
            'provider_status' => ['required', 'in:successful'], 'currency' => ['required', 'in:ZAR'],
            'provider_id' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{3,120}$/'],
            'order_id' => ['required', 'integer', 'min:1'], 'payer_id' => ['required', 'integer', 'min:1'], 'event_id' => ['required', 'integer', 'min:1'],
            'category_event_id' => ['required', 'integer', 'min:1'], 'player_id' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date_format:Y-m-d\TH:i:sP'],
            'gross' => ['required', 'string', 'regex:/^\d{1,8}\.\d{2}$/'], 'fee' => ['required', 'string', 'regex:/^\d{1,8}\.\d{2}$/'],
            'net' => ['required', 'string', 'regex:/^\d{1,8}\.\d{2}$/'],
            'observed_on' => ['missing'], 'operator_reviewed_at' => ['missing'], 'unchanged_payment_confirmed' => ['missing'], 'owner_confirmation_reference' => ['missing'],
            'prior_attempt_observations' => ['sometimes', 'array', 'max:20'],
            'prior_attempt_observations.*.source' => ['required', 'in:authenticated_payfast_dashboard_search'],
            'prior_attempt_observations.*.attested' => ['required', 'accepted'],
            'prior_attempt_observations.*.verified_by' => ['required', 'integer', 'in:'.$actor->id],
            'prior_attempt_observations.*.verified_at' => ['required', 'date_format:Y-m-d\TH:i:sP'],
            'prior_attempt_observations.*.order_id' => ['required', 'integer', 'min:1', 'distinct'],
            'prior_attempt_observations.*.payer_id' => ['required', 'integer', 'min:1'],
            'prior_attempt_observations.*.event_id' => ['required', 'integer', 'min:1'],
            'prior_attempt_observations.*.category_event_id' => ['required', 'integer', 'min:1'],
            'prior_attempt_observations.*.player_id' => ['required', 'integer', 'min:1'],
            'prior_attempt_observations.*.amount' => ['required', 'string', 'regex:/^\d{1,8}\.\d{2}$/'],
            'prior_attempt_observations.*.searched_from' => ['required', 'date_format:Y-m-d\TH:i:sP'],
            'prior_attempt_observations.*.searched_until' => ['required', 'date_format:Y-m-d\TH:i:sP'],
            'prior_attempt_observations.*.outcome' => ['required', 'in:no_matching_settlement_observed'],
        ])->validate();
        $verified = CarbonImmutable::parse($data['verified_at']);
        $this->require($verified->lte(now()->addSeconds(5)) && $verified->gte(now()->subMinutes(30))
            && CarbonImmutable::parse($data['paid_at'])->lte($verified), 'Fresh authenticated dashboard verification is required.');
        $this->require($this->cents($data['gross']) > 0 && $this->cents($data['gross']) - $this->cents($data['fee']) === $this->cents($data['net']),
            'Provider gross, fee and net must reconcile exactly.');
        ksort($data); return $data;
    }

    /** Preserve only exact prior expired attempts; a handed-off attempt requires its own fresh observed-negative provider evidence. */
    private function preservedPriorAttempts(array $ids, RegistrationOrder $target, \Illuminate\Support\Collection $targetItems, Event $event, array $evidence, CarbonImmutable $targetPaidAt): array
    {
        $summary = $state = $used = [];
        $observations = collect($evidence['prior_attempt_observations'] ?? [])->keyBy('order_id');
        foreach ($ids as $id) {
            $prior = RegistrationOrder::lockForUpdate()->findOrFail($id);
            $priorItems = $prior->items()->lockForUpdate()->get();
            $this->require($priorItems->count() === 1, 'A competing prior basket requires separate review.');
            $item = $priorItems->sole();
            $matches = $targetItems->filter(fn ($i) => (int) $i->player_id === (int) $item->player_id && (int) $i->category_event_id === (int) $item->category_event_id);
            $this->require($matches->count() === 1 && $prior->id < $target->id && (int) $prior->user_id === (int) $target->user_id
                && (int) $item->user_id === (int) $target->user_id && (int) $item->registration_id !== (int) $matches->sole()->registration_id
                && $prior->status === 'cancelled' && ! $prior->pay_status && ! $prior->payfast_paid && ! $prior->payfast_pf_payment_id
                && ! $prior->payfast_handed_off_at && $this->cents($prior->wallet_reserved) === 0 && ! $prior->wallet_debited
                && ! $prior->wallet_transaction_id && $this->cents($prior->payfast_amount_due) === 0
                && $this->cents($prior->total_fee) === $this->cents($item->item_price) && $this->cents($prior->total_fee) > 0,
                'A competing checkout is not an unchanged prior unpaid attempt for the exact original payer and player/category.');
            $activities = Activity::where('subject_type', $prior->getMorphClass())->where('subject_id', $prior->id)->lockForUpdate()->get();
            $expiries = $activities->where('description', 'Unpaid registration checkout expired; reservation released');
            $retained = $activities->where('description', 'Expired unresolved PayFast handoff retained for reconciliation');
            $this->require($expiries->count() === 1 && $retained->count() <= 1, 'Unique prior automatic expiry provenance is required.');
            $expiry = $expiries->sole(); $minutes = (int) $expiry->properties['timeout_minutes'];
            $this->require(in_array($minutes, [5, 30], true) && (int) $expiry->properties['payer_id'] === (int) $target->user_id
                && $expiry->created_at->lt($target->created_at) && $prior->updated_at->format('Y-m-d H:i:s') === $expiry->created_at->format('Y-m-d H:i:s'),
                'A prior attempt overlaps the target checkout or changed since automatic expiry.');
            $registration = Registration::lockForUpdate()->findOrFail($item->registration_id);
            $entries = CategoryEventRegistration::withTrashed()->where('registration_id', $registration->id)->lockForUpdate()->get();
            $links = DB::table('player_registrations')->where('registration_id', $registration->id)->lockForUpdate()->pluck('player_id');
            $this->require($entries->count() === 1 && $links->count() === 1 && (int) $links->sole() === (int) $item->player_id, 'Prior original registration links changed.');
            $entry = $entries->sole(); $this->assertNoReversal($entry);
            $this->require((int) $entry->user_id === (int) $target->user_id && (int) $entry->category_event_id === (int) $item->category_event_id
                && $entry->trashed() && $entry->status === 'active' && ! $entry->payment_status_id && ! $entry->pf_transaction_id && ! $entry->wallet_transaction_id
                && $entry->deleted_at->format('Y-m-d H:i:s') === $expiry->created_at->format('Y-m-d H:i:s')
                && $entry->updated_at->format('Y-m-d H:i:s') === $expiry->created_at->format('Y-m-d H:i:s'), 'Prior entry is no longer an unchanged unpaid expiry deletion.');
            $history = collect();
            foreach ([$prior, $item, $registration, $entry] as $subject) $history = $history->merge($this->history($subject));
            foreach (['registration_manual_receipts', 'trial_payment_proofs', 'registration_payment_recoveries'] as $table) {
                $column = $table === 'registration_payment_recoveries' ? 'registration_order_id' : 'order_id';
                $this->require(! DB::table($table)->where($column, $id)->lockForUpdate()->exists(), 'Prior manual, proof or recovery history requires separate review.');
            }
            $this->require(! Transaction::where('custom_int5', $id)->where(fn ($q) => $q->whereNull('custom_str5')->orWhere('custom_str5', ''))->lockForUpdate()->exists()
                && ! WalletTransaction::where('source_type', 'event_registration_wallet_payment')->where('source_id', $id)->lockForUpdate()->exists()
                && ! DB::table('withdrawals')->where('registration_id', $registration->id)->lockForUpdate()->exists()
                && ! DB::table('category_adjustments')->where('entry_id', $entry->id)->lockForUpdate()->exists()
                && ! DB::table('draw_registrations')->where('registration_id', $registration->id)->lockForUpdate()->exists()
                && ! DB::table('fixtures')->where(fn ($q) => $q->where('registration1_id', $registration->id)->orWhere('registration2_id', $registration->id))->lockForUpdate()->exists(),
                'A prior attempt contains financial or competition evidence.');
            $observation = null;
            if ($retained->isNotEmpty()) {
                $handoff = $retained->sole(); $snapshot = $handoff->properties; $handedAt = CarbonImmutable::parse($snapshot['handed_off_at']);
                $this->require($activities->count() === 3 && $activities->where('description', 'registration checkout handed off to PayFast')->count() === 1
                    && $expiry->created_at->gte($handedAt->addMinutes($minutes)) && $this->cents($snapshot['reserved_wallet_amount']) === 0
                    && $this->cents($snapshot['expected_provider_amount']) === $this->cents($prior->total_fee), 'A prior handed-off attempt has ambiguous lifecycle evidence.');
                foreach (['payer_id' => $target->user_id, 'event_id' => $event->id, 'category_event_id' => $item->category_event_id, 'player_id' => $item->player_id] as $field => $value) {
                    $this->require((string) $snapshot[$field] === (string) $value, 'Prior retained handoff identity differs.');
                }
                $observation = $observations->get($id);
                $this->require($observation !== null, 'A prior handed-off attempt requires its own fresh authenticated observed-negative provider search.');
                foreach (['order_id' => $id, 'payer_id' => $target->user_id, 'event_id' => $event->id, 'category_event_id' => $item->category_event_id, 'player_id' => $item->player_id] as $field => $value) {
                    $this->require((string) $observation[$field] === (string) $value, 'Observed-negative evidence does not match the exact prior checkout tuple.');
                }
                $verified = CarbonImmutable::parse($observation['verified_at']); $through = CarbonImmutable::parse($observation['searched_until']);
                $this->require($verified->lte(now()->addSeconds(5)) && $verified->gte(now()->subMinutes(30))
                    && $through->lte($verified->addSeconds(5)) && $through->gte(now()->subMinutes(30)) && $through->gte($targetPaidAt)
                    && CarbonImmutable::parse($observation['searched_from'])->lte($handedAt)
                    && $this->cents($observation['amount']) === $this->cents($prior->total_fee),
                    'Observed-negative provider evidence must freshly cover the complete prior handoff interval through the target payment.');
                $used[] = (int) $id;
            } else {
                $this->require($activities->count() === 1 && $expiry->created_at->gte($prior->created_at->copy()->addMinutes($minutes)),
                    'An unsent prior attempt must contain only its canonical automatic expiry.');
                foreach ($history as $audit) foreach ([$audit->before ?? [], $audit->after ?? []] as $snapshot) {
                    $this->require(empty($snapshot['payfast_handed_off_at']), 'An apparently unsent prior attempt contains historical handoff evidence.');
                }
            }
            $summary[] = ['order_id' => (int) $id, 'registration_id' => (int) $registration->id, 'entry_id' => (int) $entry->id,
                'had_payfast_handoff' => $retained->isNotEmpty(), 'negative_observation' => $observation,
                'future_settlement_still_possible' => $retained->isNotEmpty(), 'preserved_cancelled' => true];
            $state[] = [$prior->getRawOriginal(), $item->getRawOriginal(), $registration->getRawOriginal(), $entry->getRawOriginal(), $links->all(),
                $activities->map(fn ($a) => $a->getRawOriginal())->all(), $history->map(fn ($a) => $a->getRawOriginal())->all(), $observation];
        }
        $this->require(count($used) === $observations->count(), 'Observed-negative evidence includes an unknown or inapplicable prior attempt.');
        return compact('summary', 'state');
    }

    private function history(\Illuminate\Database\Eloquent\Model $subject): \Illuminate\Support\Collection
    {
        $audits = AuditEvent::where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->id)->orderBy('id')->lockForUpdate()->get();
        $this->require($audits->isNotEmpty() && $audits->every(fn ($a) => AuditIntegrity::verify($a->getRawOriginal())), 'Complete integrity-verifiable original basket history is required.');
        foreach ($audits as $audit) foreach ([$audit->before ?? [], $audit->after ?? []] as $snapshot) {
            $this->require(empty($snapshot['payfast_paid']) && empty($snapshot['pay_status']) && empty($snapshot['wallet_debited'])
                && empty($snapshot['wallet_transaction_id']) && $this->zeroOrBlank($snapshot['wallet_reserved'] ?? null)
                && empty($snapshot['payment_status_id']) && empty($snapshot['pf_transaction_id']) && empty($snapshot['payfast_pf_payment_id'])
                && empty($snapshot['withdrawn_at']) && empty($snapshot['withdrawn_by']) && empty($snapshot['refunded_at'])
                && empty($snapshot['refund_waived_at']) && empty($snapshot['refund_waived_by']) && empty($snapshot['refund_method'])
                && empty($snapshot['refund_waiver_reason']) && $this->zeroOrBlank($snapshot['refund_gross'] ?? null)
                && $this->zeroOrBlank($snapshot['refund_fee'] ?? null) && $this->zeroOrBlank($snapshot['refund_net'] ?? null)
                && (($snapshot['refund_status'] ?? null) === null || in_array($snapshot['refund_status'], CategoryEventRegistration::NO_REFUND_STATUSES, true))
                && ($snapshot['admin_payment_status'] ?? null) !== 'paid', 'Original financial or reversal history requires separate review.');
        }
        return $audits;
    }

    private function assertNoReversal(CategoryEventRegistration $entry): void
    {
        $this->require(! $entry->withdrawn_at && ! $entry->withdrawn_by && ! $entry->refunded_at && ! $entry->refund_waived_at
            && ! $entry->refund_waived_by && ! $entry->refund_method && ! $entry->refund_waiver_reason
            && ! $entry->refund_gross && ! $entry->refund_fee && ! $entry->refund_net && $entry->admin_payment_status !== 'paid'
            && ($entry->refund_status === null || in_array($entry->refund_status, CategoryEventRegistration::NO_REFUND_STATUSES, true)),
            'Original withdrawal, refund or private collection history requires separate review.');
    }

    private function zeroOrBlank(mixed $value): bool { return $value === null || $value === '' || $this->cents($value) === 0; }
    private function cents(mixed $value): int
    {
        $this->require(is_numeric($value) && is_finite((float) $value) && preg_match('/^-?\d+(?:\.\d{1,2})?$/D', (string) $value) === 1,
            'Exact numeric financial evidence with at most two decimal places is required.');
        return (int) round((float) $value * 100);
    }
    private function require(bool $condition, string $message): void { if (! $condition) throw ValidationException::withMessages(['reconciliation' => $message]); }
}
