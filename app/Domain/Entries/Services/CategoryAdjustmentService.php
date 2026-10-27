<?php

declare(strict_types=1);

namespace App\Domain\Entries\Services;

use App\Domain\Payments\Services\{LedgerService, PaymentOrchestrator, PaymentTransactionService};
use App\Mail\CategoryMovedMail;
use App\Models\{CategoryAdjustment, CategoryEvent, CategoryEventRegistration, Event, RegistrationOrderItems, SiteSetting, User};
use App\Services\Payfast;
use App\Support\FinanceMutationScope;
use Illuminate\Support\Facades\{Crypt, DB, Mail};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Active-entry price adjustments, separate from withdrawal refunds. */
class CategoryAdjustmentService
{
    public function __construct(
        private EntryService $entries,
        private EntryEligibilityService $eligibility,
        private CategoryAdjustmentInvitationService $invitations,
        private PaymentOrchestrator $payments,
        private LedgerService $ledger,
    ) {}

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['category' => $message]);
    }

    public function quote(CategoryEventRegistration $entry, CategoryEvent $target, User $actor, bool $admin = false): array
    {
        return DB::transaction(function () use ($entry, $target, $actor, $admin) {
            [$entry, $target] = $this->lockAndValidate($entry, $target, $actor, $admin);
            $payment = $this->funding($entry);
            $fee = $target->entry_fee ?? $entry->categoryEvent->event->entryFee;
            if (! is_numeric($fee) || ! is_finite((float) $fee) || (float) $fee < 0) {
                $this->reject('The destination category has no valid entry fee. Please contact the organiser.');
            }
            $snapshot = [
                'entry_id' => $entry->id, 'source_id' => $entry->category_event_id,
                'target_id' => $target->id, 'payer_id' => $entry->user_id,
                'actor_id' => $actor->id, 'admin' => $admin,
                'funded_cents' => $this->cents($payment['total_paid']),
                'target_cents' => $this->cents($fee),
                'version' => CategoryAdjustment::where('entry_id', $entry->id)->whereNotNull('applied_at')->max('id'),
            ];
            $difference = $snapshot['target_cents'] - $snapshot['funded_cents'];
            if ($difference > 0 && $difference < 500) {
                $this->reject('PayFast requires a minimum payment of R5.00. Please contact the organiser for a smaller category difference. Your entry has not changed.');
            }
            return $snapshot + [
                'difference' => $difference / 100,
                'funded' => $snapshot['funded_cents'] / 100,
                'target_fee' => $snapshot['target_cents'] / 100,
                'wallet_credit' => max(0, -$difference) / 100,
                'quote_token' => Crypt::encryptString(json_encode($snapshot + ['expires' => now()->addMinutes(5)->timestamp], JSON_THROW_ON_ERROR)),
                'request_token' => (string) Str::uuid(),
            ];
        }, 3);
    }

    public function start(CategoryEventRegistration $entry, CategoryEvent $target, User $actor, string $quoteToken, string $requestToken, bool $admin = false): CategoryAdjustment
    {
        try {
            $approved = json_decode(Crypt::decryptString($quoteToken), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $this->reject('Review the current category price before confirming.');
        }
        return DB::transaction(function () use ($entry, $target, $actor, $approved, $requestToken, $admin) {
            [$entry, $target] = $this->lockAndValidate($entry, $target, $actor, $admin, false);
            $existing = CategoryAdjustment::where('request_token', $requestToken)->lockForUpdate()->first();
            if ($existing) {
                abort_unless((int) $existing->entry_id === (int) $entry->id && (int) $existing->actor_id === (int) $actor->id
                    && (int) $existing->target_category_id === (int) $target->id, 409);
                return $existing;
            }
            $quote = $this->quote($entry, $target, $actor, $admin);
            if ($admin && $quote['difference'] > 0) {
                $this->reject('The registering account must pay the difference through PayFast.');
            }
            $expires = $approved['expires'] ?? 0;
            unset($approved['expires']);
            if ($expires < now()->timestamp || array_intersect_key($quote, $approved) !== $approved) {
                $this->reject('The entry or category price changed. Review the current amounts again.');
            }
            $pending = CategoryAdjustment::where('entry_id', $entry->id)->where('status', 'pending')->lockForUpdate()->get();
            foreach ($pending as $attempt) {
                if ($attempt->expires_at->isFuture()) {
                    $this->reject('A category payment is already pending. Resume or cancel it before making another change.');
                }
                $this->cancel($attempt, $actor, true, $admin);
            }
            $payment = $this->funding($entry);
            $origin = CategoryAdjustment::where('entry_id', $entry->id)->oldest('id')->value('original_order_item_id')
                ?? RegistrationOrderItems::where('registration_id', $entry->registration_id)->where('category_event_id', $entry->category_event_id)->value('id');
            $close = $entry->categoryEvent->event->registrationClosesAt();
            $expiry = now()->addMinutes(5);
            if ($close && $close->lt($expiry)) { $expiry = $close; }
            $adjustment = CategoryAdjustment::create([
                'entry_id' => $entry->id, 'event_id' => $entry->categoryEvent->event_id,
                'player_id' => $payment['player_id'],
                'user_id' => $entry->user_id, 'actor_id' => $actor->id,
                'source_category_id' => $entry->category_event_id, 'target_category_id' => $target->id,
                'original_order_item_id' => $origin, 'request_token' => $requestToken,
                'payment_before' => $payment, 'target_fee' => $quote['target_fee'], 'difference' => $quote['difference'],
                'wallet_credit' => $quote['wallet_credit'], 'expires_at' => $expiry,
            ]);
            if ($quote['difference'] > 0) {
                return $this->payments->initiatePayment($adjustment, 0, $quote['difference']);
            }
            return $this->apply($adjustment, $entry, $target, $actor);
        }, 3);
    }

    private function lockAndValidate(CategoryEventRegistration $entry, CategoryEvent $target, User $actor, bool $admin, bool $validateMove = true): array
    {
        $eventId = $entry->categoryEvent->event_id;
        $event = Event::with('eventTypeModel')->lockForUpdate()->findOrFail($eventId);
        CategoryEvent::whereIn('id', [$entry->category_event_id, $target->id])->orderBy('id')->lockForUpdate()->get();
        $entry = CategoryEventRegistration::with('categoryEvent.event.eventTypeModel', 'registration.players')->lockForUpdate()->findOrFail($entry->id);
        $target = CategoryEvent::with('event.eventTypeModel', 'category')->findOrFail($target->id);
        if ($admin) {
            abort_unless($actor->hasRole('super-user'), 403);
        } else {
            abort_unless((int) $entry->user_id === (int) $actor->id, 403);
        }
        abort_unless((int) $target->event_id === (int) $event->id && (int) $entry->categoryEvent->event_id === (int) $event->id, 422);
        if (! $event->isIndividual() || ! $event->published || ! $event->registrationIsOpen()
            || SiteSetting::get('registration_open', '1') !== '1') {
            $this->reject('Category changes are available for individual events only while entries are open.');
        }
        if (! $entry->is_paid || $entry->withdrawn_at || $entry->status !== 'active'
            || in_array($entry->refund_status, ['pending', 'completed', 'waived'], true)) {
            $this->reject('Only an active paid entry without a refund may change category.');
        }
        if ($entry->registration->players->isEmpty() || ! $entry->user_id) {
            $this->reject('This entry needs organiser reconciliation before changing category.');
        }
        if ($validateMove) {
            if ($entry->categoryEvent->isLocked() || $target->isLocked()
                || $entry->categoryEvent->draws()->exists() || $target->draws()->exists()) {
                $this->reject('Categories may only be changed before either draw is created or locked.');
            }
            foreach ($entry->registration->players as $player) {
                try { $this->eligibility->assertCanRegister($target, $player->id); }
                catch (\RuntimeException $exception) { $this->reject($exception->getMessage()); }
            }
            $this->invitations->assertEligible($entry, $target, $actor);
            if ((int) $entry->category_event_id === (int) $target->id) { $this->reject('The entry is already in this category.'); }
        }
        return [$entry, $target];
    }

    /** Exact funding for one item; never equal-split differently priced order items. */
    public function funding(CategoryEventRegistration $entry): array
    {
        $last = CategoryAdjustment::where('entry_id', $entry->id)->whereNotNull('applied_at')->latest('id')->first();
        if ($last) {
            $players = $entry->registration->players()->orderBy('players.id')->pluck('players.id')->map(fn ($id) => (int) $id)->all();
            if ((int) $last->user_id !== (int) $entry->user_id
                || (int) $last->target_category_id !== (int) $entry->category_event_id
                || (int) $last->event_id !== (int) $entry->categoryEvent->event_id
                || $players !== ($last->payment_after['player_ids'] ?? null)) {
                $this->reject('The entry ownership or players changed. The original category payments need organiser reconciliation.');
            }
            return $last->payment_after;
        }
        $item = RegistrationOrderItems::with('order.items', 'order.walletTransaction.wallet')
            ->where('registration_id', $entry->registration_id)->where('category_event_id', $entry->category_event_id)->first();
        if (! $item) {
            if ($entry->isAdminEntry()) {
                return ['gross' => 0.0, 'wallet_paid' => 0.0, 'total_paid' => 0.0, 'fee' => 0.0, 'net' => 0.0, 'payment_method' => 'admin',
                    'player_id' => $entry->registration->players()->orderBy('players.id')->firstOrFail()->id,
                    'player_ids' => $entry->registration->players()->orderBy('players.id')->pluck('players.id')->all()];
            }
            $this->reject('The original payment must be reconciled before changing category.');
        }
        $order = $item->order;
        if ($order) {
            $order = \App\Models\RegistrationOrder::with('items', 'walletTransaction.wallet')->lockForUpdate()->findOrFail($order->id);
            $itemIds = $order->items->pluck('id');
            if (! CategoryAdjustment::whereNotNull('applied_at')->whereIn('original_order_item_id', $itemIds)->exists()) {
                $siblings = CategoryEventRegistration::whereIn('registration_id', $order->items->pluck('registration_id'))
                    ->where('id', '!=', $entry->id)->lockForUpdate()->get();
                if ($siblings->contains(fn ($sibling) => $sibling->withdrawn_at
                    || in_array($sibling->refund_status, ['pending', 'completed', 'waived'], true))) {
                    $this->reject('This shared order has an earlier withdrawal or refund. Reconcile its item allocations before changing category.');
                }
            }
        }
        if (! $order || ! $order->pay_status || (int) $order->user_id !== (int) $entry->user_id) {
            $this->reject('The original payer and paid order must match this entry.');
        }
        $items = $order->items->sortBy('id');
        if ((int) $item->user_id !== (int) $entry->user_id || ! $entry->registration->players->contains('id', $item->player_id)
            || $items->contains(fn ($line) => ! is_numeric($line->item_price) || ! is_finite((float) $line->item_price) || (float) $line->item_price < 0)) {
            $this->reject('The original item identity or amount needs reconciliation.');
        }
        $total = $this->cents($items->sum('item_price'));
        $wallet = $this->cents($order->wallet_reserved);
        $due = $this->cents($order->payfast_amount_due);
        if ($wallet > 0 && (! $order->wallet_debited || ! $order->walletTransaction
            || $order->walletTransaction->type !== 'debit' || $this->cents($order->walletTransaction->amount) !== $wallet
            || (int) $order->walletTransaction->wallet?->payable_id !== (int) $entry->user_id
            || $order->walletTransaction->wallet?->payable_type !== User::class)) {
            $this->reject('The original wallet debit needs reconciliation.');
        }
        if (in_array($entry->payment_method, ['eft', 'manual'], true)) {
            $manual = \App\Models\RegistrationManualReceipt::where('order_id', $order->id)->first();
            if (! $manual || $this->cents($manual->amount) !== $total) { $this->reject('The manual payment needs reconciliation.'); }
            $due = $total; $wallet = 0;
        } elseif (($due > 0 && (! $order->payfast_paid || ! $order->payfast_pf_payment_id
            || ! \App\Models\Transaction::where('pf_payment_id', $order->payfast_pf_payment_id)
                ->where('custom_int5', $order->id)->where('custom_int4', $entry->user_id)
                ->where(fn ($q) => $q->whereNull('custom_str5')->orWhere('custom_str5', ''))
                ->where('amount_gross', $order->payfast_amount_due)->exists()))
            || $wallet + $due !== $total || $this->cents($order->total_fee) !== $total) {
            $this->reject('The original payment amount needs reconciliation.');
        }
        // Cumulative allocation preserves every cent across the order.
        $running = 0; $allocatedWallet = 0;
        foreach ($items as $line) {
            $running += $this->cents($line->item_price);
            $cumulative = $total > 0 ? (int) round($wallet * $running / $total) : 0;
            if ((int) $line->id === (int) $item->id) { $itemWallet = $cumulative - $allocatedWallet; }
            $allocatedWallet = $cumulative;
        }
        $itemTotal = $this->cents($item->item_price);
        $itemWallet ??= 0;
        return ['gross' => ($itemTotal - $itemWallet) / 100, 'wallet_paid' => $itemWallet / 100,
            'total_paid' => $itemTotal / 100, 'fee' => 0.0, 'net' => ($itemTotal - $itemWallet) / 100,
            'payment_method' => $entry->payment_method, 'paid_at' => $order->updated_at?->toISOString(),
            'payer_name' => $order->user?->name, 'payer_email' => $order->user?->email,
            'player_id' => $item->player_id, 'player_ids' => $entry->registration->players()->orderBy('players.id')->pluck('players.id')->all(),
            'pf_payment_id' => null, 'transaction_id' => null];
    }

    public function handoff(CategoryAdjustment $adjustment, User $actor): CategoryAdjustment
    {
        return DB::transaction(function () use ($adjustment, $actor) {
            abort_unless((int) $adjustment->user_id === (int) $actor->id, 403);
            [$entry, $target] = $this->lockAndValidate($adjustment->entry, $adjustment->targetCategory, $actor, false);
            $adjustment = CategoryAdjustment::lockForUpdate()->findOrFail($adjustment->id);
            if ($adjustment->status !== 'pending' || $adjustment->expires_at->isPast()) {
                $this->reject('This category checkout is no longer available. Return to the event to start again.');
            }
            if ($adjustment->payfast_handed_off_at) {
                $this->reject('This payment has already been sent to PayFast. Wait for confirmation or cancel this attempt before trying again.');
            }
            if ((int) $entry->category_event_id !== (int) $adjustment->source_category_id) { $this->reject('The entry has changed.'); }
            if (! $adjustment->payfast_handed_off_at) {
                $close = $entry->categoryEvent->event->registrationClosesAt();
                $expiry = now()->addMinutes(5);
                if ($close && $close->lt($expiry)) { $expiry = $close; }
                $adjustment->update(['payfast_handed_off_at' => now(), 'expires_at' => $expiry]);
            }
            return $adjustment;
        }, 3);
    }

    public function form(CategoryAdjustment $adjustment): string
    {
        $payfast = new Payfast;
        $payfast->setMode(config('services.payfast.sandbox') ? 0 : 1);
        $payfast->setEvent($adjustment->event);
        $payfast->setPayer($adjustment->user);
        $payfast->custom_int1 = $adjustment->target_category_id;
        $payfast->custom_int2 = $adjustment->player_id;
        $payfast->custom_int4 = $adjustment->user_id;
        $payfast->custom_int5 = $adjustment->id;
        $payfast->custom_str5 = 'CategoryAdjustment';
        $payfast->setAmount((float) $adjustment->difference);
        $payfast->setItem('Category change: '.$adjustment->event->name);
        $payfast->setReturnUrl(route('registrations.categoryAdjustment.show', $adjustment));
        $payfast->setCancelUrl(route('registrations.categoryAdjustment.show', $adjustment));
        return $payfast->getForm();
    }

    public function cancel(CategoryAdjustment $adjustment, User $actor, bool $expired = false, bool $admin = false): CategoryAdjustment
    {
        abort_unless((int) $adjustment->user_id === (int) $actor->id || ($admin && $actor->hasRole('super-user')), 403);
        return DB::transaction(function () use ($adjustment, $expired) {
            $locked = CategoryAdjustment::lockForUpdate()->findOrFail($adjustment->id);
            if ($locked->status !== 'pending' || $locked->payfast_paid) { return $locked; }
            if ($expired && $locked->expires_at->isFuture()) { return $locked; }
            $cancelled = $this->payments->cancelPayment($locked);
            $cancelled->update(['status' => $expired ? 'expired' : 'cancelled']);
            return $cancelled;
        }, 3);
    }

    /** Called only after the shared PayFast signature/merchant verifier succeeds. */
    public function receive(array $data): void
    {
        if (($data['payment_status'] ?? null) !== 'COMPLETE') { return; }
        DB::transaction(function () use ($data) {
            $reference = CategoryAdjustment::findOrFail((int) ($data['custom_int5'] ?? 0));
            Event::whereKey($reference->event_id)->lockForUpdate()->first();
            CategoryEvent::whereIn('id', [$reference->source_category_id, $reference->target_category_id])->orderBy('id')->lockForUpdate()->get();
            CategoryEventRegistration::withTrashed()->whereKey($reference->entry_id)->lockForUpdate()->first();
            $adjustment = CategoryAdjustment::lockForUpdate()->findOrFail((int) ($data['custom_int5'] ?? 0));
            foreach (['custom_str5' => 'CategoryAdjustment', 'custom_int1' => $adjustment->target_category_id,
                'custom_int2' => $adjustment->player_id, 'custom_int3' => $adjustment->event_id,
                'custom_int4' => $adjustment->user_id] as $key => $expected) {
                if ((string) ($data[$key] ?? '') !== (string) $expected) { $this->reject('Payment identity mismatch.'); }
            }
            $providerId = (string) ($data['pf_payment_id'] ?? '');
            if ($providerId === '' || strlen($providerId) > 120 || ! preg_match('/^\d+(?:\.\d{1,2})?$/', (string) ($data['amount_gross'] ?? ''))
                || $this->cents($data['amount_gross']) !== $this->cents($adjustment->difference) || ! $adjustment->payfast_handed_off_at) {
                $this->reject('Payment amount or handoff mismatch.');
            }
            if ($adjustment->payfast_paid) {
                if ($adjustment->payfast_pf_payment_id !== $providerId) {
                    app(PaymentTransactionService::class)->record(array_merge($data, ['cape_tennis_fee' => 0]), $adjustment);
                    activity('category-adjustment')->performedOn($adjustment)
                        ->withProperties(['additional_pf_payment_id' => $providerId])
                        ->log('Additional category payment requires reconciliation; entry unchanged');
                }
                return;
            }
            $reason = null;
            if ($adjustment->status !== 'pending' || $adjustment->expires_at->isPast()) { $reason = 'Payment received after checkout cancellation or expiry.'; }
            try {
                if (! $adjustment->entry?->categoryEvent?->event || ! $adjustment->user || ! $adjustment->targetCategory?->event) {
                    $this->reject('The entry is no longer available.');
                }
                [$entry, $target] = $this->lockAndValidate($adjustment->entry, $adjustment->targetCategory, $adjustment->user, false);
                if ((int) $entry->category_event_id !== (int) $adjustment->source_category_id
                    || $this->cents($this->funding($entry)['total_paid']) !== $this->cents($adjustment->payment_before['total_paid'])
                    || $entry->registration->players()->orderBy('players.id')->pluck('players.id')->all() !== $adjustment->payment_before['player_ids']) {
                    $reason ??= 'Entry changed while payment was pending.';
                }
            } catch (\RuntimeException|ValidationException|\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $reason ??= 'Entry eligibility or registration deadline changed before settlement.';
            }
            app(PaymentTransactionService::class)->record(array_merge($data, ['cape_tennis_fee' => 0]), $adjustment);
            if ($reason) {
                FinanceMutationScope::run('payment_state_write', fn () => $adjustment->update([
                    'status' => 'reconciliation_required', 'pay_status' => true, 'payfast_paid' => true,
                    'payfast_pf_payment_id' => $providerId, 'reconciliation_reason' => $reason,
                ]));
                activity('category-adjustment')->performedOn($adjustment)->withProperties(['reason' => $reason])->log('Category payment requires reconciliation');
                return;
            }
            $paid = $this->payments->finalizePayment($adjustment, ['payment_method' => 'payfast', 'pf_payment_id' => $providerId,
                'payfast_amount_received' => $data['amount_gross']]);
            $this->apply($paid, $entry, $target, $adjustment->actor);
        }, 3);
    }

    private function apply(CategoryAdjustment $adjustment, CategoryEventRegistration $entry, CategoryEvent $target, User $actor): CategoryAdjustment
    {
        $before = $adjustment->payment_before;
        $gross = $this->cents($before['gross']);
        $wallet = $this->cents($before['wallet_paid']);
        $difference = $this->cents($adjustment->difference);
        // PayFast-only payers may not have a wallet yet. Provision it under the
        // payer lock so later credits and eligible withdrawal refunds work too.
        $payer = User::lockForUpdate()->findOrFail($adjustment->user_id);
        $payerWallet = $payer->wallet ?? $payer->wallet()->create([]);
        if ($difference > 0) { $gross += $difference; }
        if ($difference < 0) {
            $credit = -$difference;
            $fromPayfast = min($gross, $credit);
            $gross -= $fromPayfast; $wallet -= $credit - $fromPayfast;
            $transaction = $this->ledger->appendWalletCredit($payerWallet, $credit / 100, 'category_adjustment', $adjustment->id,
                ['entry_id' => $entry->id, 'event_id' => $adjustment->event_id, 'reference' => $adjustment->event->name]);
            $adjustment->wallet_transaction_id = $transaction->id;
        }
        $this->entries->transferEntry($entry, $target, $actor, true);
        $this->invitations->apply($entry->fresh(), (int) $adjustment->source_category_id, $target, $actor);
        $after = array_merge($before, ['gross' => $gross / 100, 'wallet_paid' => $wallet / 100,
            'total_paid' => ($gross + $wallet) / 100, 'net' => $gross / 100, 'fee' => 0.0,
            'pf_payment_id' => null, 'transaction_id' => null, 'category_adjusted' => true]);
        $adjustment->fill(['payment_after' => $after, 'status' => 'completed', 'applied_at' => now()])->save();
        activity('category-adjustment')->performedOn($entry)->causedBy($actor)->withProperties([
            'adjustment_id' => $adjustment->id, 'payer_id' => $adjustment->user_id,
            'from_category' => $adjustment->source_category_id, 'to_category' => $target->id,
            'difference' => $adjustment->difference, 'wallet_credit' => $adjustment->wallet_credit,
        ])->log('Category and funding adjusted');
        DB::afterCommit(function () use ($adjustment, $entry, $target, $actor) {
            try {
            if (SiteSetting::get('player_email_on_move', '1') !== '1') { return; }
            if (filter_var($adjustment->user?->email, FILTER_VALIDATE_EMAIL)) {
                Mail::to($adjustment->user->email)->queue(new \App\Mail\CategoryAdjustmentReceiptMail($adjustment));
            }
            $recipients = $entry->registration->players->flatMap(fn ($player) => app(\App\Services\TeamSelection\TeamSelectionContactService::class)->emails($player))->unique();
            foreach ($recipients as $email) {
                Mail::to($email)->queue(new CategoryMovedMail([
                    'player_name' => $entry->display_name, 'event_name' => $adjustment->event->name,
                    'event_context' => $adjustment->event, 'old_category' => $adjustment->sourceCategory->category?->name,
                    'new_category' => $target->category?->name, 'changed_by' => $actor->name,
                ]));
            }
            } catch (\Throwable $exception) {
                \Illuminate\Support\Facades\Log::warning('Category adjustment notification could not be queued', ['adjustment_id' => $adjustment->id]);
            }
        });
        return $adjustment->fresh();
    }

    private function cents(mixed $value): int { return (int) round((float) $value * 100); }
}
