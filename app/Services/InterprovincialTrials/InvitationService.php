<?php

namespace App\Services\InterprovincialTrials;

use App\Domain\Entries\Services\EntryEligibilityService;
use App\Domain\Payments\Services\RegistrationPaymentService;
use App\Jobs\SendInterprovincialTrialInvitationEmailJob;
use App\Models\BulkEmailLog;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\EventNomination;
use App\Models\InterprovincialTrialInvitation;
use App\Models\InterprovincialTrialInvitationBatch;
use App\Models\Registration;
use App\Models\CategoryEventRegistration;
use App\Models\RegistrationOrder;
use App\Models\RegistrationOrderItems;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InvitationService
{
    public function acceptPublishedNomination(
        Event $event,
        CategoryEvent $categoryEvent,
        EventNomination $nomination,
        User $user
    ): RegistrationOrder {
        return DB::transaction(function () use ($event, $categoryEvent, $nomination, $user): RegistrationOrder {
            $lockedEvent = Event::query()->with('eventTypeModel')->lockForUpdate()->findOrFail($event->id);
            $lockedCategory = CategoryEvent::query()->lockForUpdate()->findOrFail($categoryEvent->id);
            $lockedNomination = EventNomination::query()->lockForUpdate()->findOrFail($nomination->id);

            abort_unless($lockedEvent->isInterprovincialTrials()
                && (int) $lockedCategory->event_id === (int) $lockedEvent->id
                && (bool) $lockedCategory->nominations_published
                && (int) $lockedNomination->event_id === (int) $lockedEvent->id
                && (int) $lockedNomination->category_event_id === (int) $lockedCategory->id,
                404
            );

            if (! $lockedEvent->published || ! $lockedEvent->hasOpenRegistrationLifecycle()
                || (int) $lockedEvent->signUp !== 1) {
                throw ValidationException::withMessages(['invitation' => 'Registration for this trial is closed.']);
            }
            $closeAt = $lockedEvent->registrationClosesAt()?->endOfDay();
            if ($closeAt && now()->gt($closeAt)) {
                throw ValidationException::withMessages(['invitation' => 'The registration deadline has passed.']);
            }

            $existingOrderAttempt = InterprovincialTrialInvitation::query()
                ->where('event_id', $lockedEvent->id)
                ->where('category_event_id', $lockedCategory->id)
                ->where('nomination_id', $lockedNomination->id)
                ->where('player_id', $lockedNomination->player_id)
                ->whereIn('status', [
                    InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT,
                    InterprovincialTrialInvitation::PAID_CONFIRMED,
                ])
                ->latest('id')
                ->first();

            $invitation = $existingOrderAttempt ?: InterprovincialTrialInvitation::query()
                ->where('event_id', $lockedEvent->id)
                ->where('category_event_id', $lockedCategory->id)
                ->where('nomination_id', $lockedNomination->id)
                ->where('player_id', $lockedNomination->player_id)
                ->whereIn('status', ['queued', 'sent', 'open_registration'])
                ->latest('id')
                ->first();

            if (! $invitation) {
                $batch = InterprovincialTrialInvitationBatch::create([
                    'event_id' => $lockedEvent->id,
                    'status' => InterprovincialTrialInvitationBatch::DRAFT,
                    'snapshot_hash' => hash('sha256', implode(':', [
                        'open-registration', $lockedEvent->id, $lockedCategory->id, $lockedNomination->id,
                    ])),
                    'created_by_user_id' => $user->id,
                ]);

                $invitation = InterprovincialTrialInvitation::create([
                    'batch_id' => $batch->id,
                    'event_id' => $lockedEvent->id,
                    'category_event_id' => $lockedCategory->id,
                    'nomination_id' => $lockedNomination->id,
                    'player_id' => $lockedNomination->player_id,
                    'status' => 'open_registration',
                ]);
            }

            return $this->accept($invitation, $user);
        });
    }

    public function previewAttempt(Event $event, string $mode, ?int $invitationId = null): array
    {
        $selection = $this->attemptSelection($event, $mode, $invitationId, false);

        return $selection + [
            'request_token' => (string) Str::uuid(),
            'recipient_hash' => $this->attemptRecipientHash($selection['recipients']),
            'subject' => 'Invitation to '.$event->name,
            'body' => "You have been invited to register for {$event->name}.\n\nUse the secure registration link in this email to continue.",
        ];
    }

    public function queueAttempt(Event $event, User $actor, array $request): array
    {
        return DB::transaction(function () use ($event, $actor, $request): array {
            Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $existingAttempts = DB::table('interprovincial_trial_mail_dispatches as dispatches')
                ->join('interprovincial_trial_invitations as invitations', 'invitations.id', '=', 'dispatches.invitation_id')
                ->where('invitations.event_id', $event->id)
                ->where('dispatches.request_token', $request['request_token'])
                ->count();
            if ($existingAttempts > 0) {
                return ['queued_count' => 0, 'skipped_count' => 0, 'already_queued' => true];
            }
            $selection = $this->attemptSelection($event, $request['mode'], $request['invitation_id'] ?? null, true);
            if (! hash_equals($request['recipient_hash'], $this->attemptRecipientHash($selection['recipients']))) {
                throw ValidationException::withMessages(['recipients' => 'The recipients changed. Preview the exact list again.']);
            }
            if (! $selection['recipients']) {
                throw ValidationException::withMessages(['recipients' => 'There are no eligible recipients for this send.']);
            }

            $batch = InterprovincialTrialInvitationBatch::query()->where('event_id', $event->id)->lockForUpdate()->latest('id')->first();
            $batch ??= InterprovincialTrialInvitationBatch::create([
                'event_id' => $event->id,
                'status' => InterprovincialTrialInvitationBatch::DRAFT,
                'snapshot_hash' => hash('sha256', 'event:'.$event->id),
                'created_by_user_id' => $actor->id,
            ]);
            $queued = 0;
            foreach ($selection['recipients'] as $recipient) {
                $invitation = $recipient['invitation_id']
                    ? InterprovincialTrialInvitation::query()->lockForUpdate()->findOrFail($recipient['invitation_id'])
                    : InterprovincialTrialInvitation::create([
                        'batch_id' => $batch->id,
                        'event_id' => $event->id,
                        'category_event_id' => $recipient['category_event_id'],
                        'nomination_id' => $recipient['nomination_id'],
                        'player_id' => $recipient['player_id'],
                        'recipient_email' => $recipient['email'],
                        'recipient_name' => $recipient['name'],
                        'status' => 'prepared',
                    ]);
                abort_unless((int) $invitation->event_id === (int) $event->id, 404);
                $kind = $request['mode'] === 'new' ? 'initial' : 'follow_up';
                $claimed = DB::table('interprovincial_trial_mail_dispatches')->insertOrIgnore([
                    'invitation_id' => $invitation->id,
                    'request_token' => $request['request_token'],
                    'kind' => $kind,
                    'requested_by_user_id' => $actor->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                if (! $claimed) continue;
                $invitation->update(['recipient_email' => $recipient['email'], 'recipient_name' => $recipient['name']]);
                $messageHash = $this->messageHash($request['subject'], $request['body']);
                $log = BulkEmailLog::create([
                    'mail_type' => 'interprovincial_trial_invitation',
                    'related_type' => InterprovincialTrialInvitation::class,
                    'related_id' => $invitation->id,
                    'recipient_email' => $recipient['email'],
                    'recipient_name' => $recipient['name'],
                    'status' => 'queued',
                    'payload' => [
                        'event_id' => $event->id,
                        'invitation_id' => $invitation->id,
                        'mode' => $request['mode'],
                        'kind' => $kind,
                        'request_token' => $request['request_token'],
                        'requested_by_user_id' => $actor->id,
                        'recipient_hash' => $request['recipient_hash'],
                        'message_hash' => $messageHash,
                        'subject' => $request['subject'],
                        'body' => $request['body'],
                        'recipient_name' => $recipient['name'],
                        'event_name' => $event->name,
                        'category_name' => $recipient['category'],
                    ],
                    'queued_at' => now(),
                ]);
                DB::table('interprovincial_trial_mail_dispatches')
                    ->where('invitation_id', $invitation->id)
                    ->where('request_token', $request['request_token'])
                    ->update(['bulk_email_log_id' => $log->id, 'updated_at' => now()]);
                if ($kind === 'initial') {
                    $invitation->update(['status' => 'queued', 'queued_at' => $invitation->queued_at ?? now()]);
                }
                DB::afterCommit(fn () => SendInterprovincialTrialInvitationEmailJob::dispatch($log->id, $event->id));
                $queued++;
            }

            return [
                'queued_count' => $queued,
                'skipped_count' => count($selection['blockers']),
                'already_queued' => $queued === 0,
            ];
        });
    }

    public function accept(InterprovincialTrialInvitation $invitation, User $user): RegistrationOrder
    {
        return DB::transaction(function () use ($invitation, $user): RegistrationOrder {
            $locked = InterprovincialTrialInvitation::query()
                ->with(['event.eventTypeModel', 'categoryEvent', 'nomination', 'player'])
                ->lockForUpdate()->findOrFail($invitation->id);

            $tupleIsCurrent = $locked->event?->isInterprovincialTrials()
                && (int) $locked->categoryEvent?->event_id === (int) $locked->event_id
                && (int) $locked->nomination?->event_id === (int) $locked->event_id
                && (int) $locked->nomination?->category_event_id === (int) $locked->category_event_id
                && (int) $locked->nomination?->player_id === (int) $locked->player_id
                && ! InterprovincialTrialInvitation::query()->where('event_id', $locked->event_id)
                    ->where('nomination_id', $locked->nomination_id)->where('id', '>', $locked->id)
                    ->where('status', '!=', 'prepared')->exists();
            abort_unless($tupleIsCurrent, 404);

            if ($locked->status === InterprovincialTrialInvitation::PAID_CONFIRMED && $locked->order_id) {
                $existing = RegistrationOrder::findOrFail($locked->order_id);
                if ((int) $existing->user_id !== (int) $user->id) throw ValidationException::withMessages(['invitation' => 'This invitation is already being processed.']);
                return $existing;
            }
            if ($locked->status === InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT && $locked->order_id) {
                $existing = RegistrationOrder::query()->lockForUpdate()->findOrFail($locked->order_id);
                if ($existing->payfast_handed_off_at) {
                    if ((int) $existing->user_id !== (int) $user->id) {
                        throw ValidationException::withMessages([
                            'invitation' => 'Payment is already being processed for this player. Please wait for it to resolve.',
                        ]);
                    }

                    return $existing;
                }

                app(RegistrationPaymentService::class)->cancelPayment($existing);
                $this->resetCancelledPaymentAttempt($locked, $existing);
                $locked->refresh();
            }

            $event = $locked->event;
            $category = $locked->categoryEvent;
            if (! $event?->isInterprovincialTrials()
                || ! $event->published || ! $event->hasOpenRegistrationLifecycle()
                || (int) $event->signUp !== 1) {
                throw ValidationException::withMessages(['invitation' => 'Registration for this trial is closed.']);
            }
            if (! $category || (int) $category->event_id !== (int) $event->id
                || ! in_array($locked->status, ['queued', 'sent', 'open_registration'], true)) {
                throw ValidationException::withMessages(['invitation' => 'This invitation is no longer available.']);
            }
            $closeAt = $event->registrationClosesAt()?->endOfDay();
            if ($closeAt && now()->gt($closeAt)) {
                throw ValidationException::withMessages(['invitation' => 'The registration deadline has passed.']);
            }

            try {
                app(EntryEligibilityService::class)->assertCanRegister($category, (int) $locked->player_id);
            } catch (\RuntimeException $exception) {
                throw ValidationException::withMessages(['invitation' => $exception->getMessage()]);
            }

            $registration = Registration::create([]);
            $registration->players()->sync([(int) $locked->player_id]);
            $registration->categoryEvents()->syncWithoutDetaching([
                $category->id => ['payment_status_id' => 0, 'user_id' => $user->id],
            ]);
            $rawFee = $category->entry_fee !== null ? $category->entry_fee : $event->entryFee;
            $fee = round((float) ($rawFee ?? 0), 2);
            if (! is_finite($fee) || $fee < 0 || (float) $rawFee !== $fee) {
                throw ValidationException::withMessages(['invitation' => 'The configured entry fee is invalid.']);
            }
            $order = RegistrationOrder::create([
                'user_id' => $user->id, 'payfast_amount_due' => $fee, 'total_fee' => $fee,
                'wallet_reserved' => 0, 'wallet_debited' => false, 'payfast_paid' => false,
                'pay_status' => false, 'payment_method' => $fee > 0 ? 'payfast' : 'free', 'status' => 'pending',
            ]);
            $item = new RegistrationOrderItems();
            $item->order_id = $order->id;
            $item->category_event_id = $category->id;
            $item->registration_id = $registration->id;
            $item->player_id = $locked->player_id;
            $item->user_id = $user->id;
            $item->item_price = $fee;
            $item->save();

            $locked->update([
                'registration_id' => $registration->id, 'order_id' => $order->id,
                'status' => InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT, 'accepted_at' => now(),
            ]);

            if ($fee === 0.0) {
                $order = app(RegistrationPaymentService::class)->markFreeOrderPaid($order);
                $this->confirmPaidOrder($order);
            }

            return $order->fresh('items');
        });
    }

    public function decline(InterprovincialTrialInvitation $invitation, User $actor): InterprovincialTrialInvitation
    {
        return DB::transaction(function () use ($invitation, $actor): InterprovincialTrialInvitation {
            $locked = InterprovincialTrialInvitation::query()
                ->with(['event.eventTypeModel', 'categoryEvent', 'nomination', 'player.users'])
                ->lockForUpdate()->findOrFail($invitation->id);
            $owned = (int) $locked->player?->userId === (int) $actor->id
                || $locked->player?->users->contains(fn (User $linked): bool => (int) $linked->id === (int) $actor->id);
            abort_unless($owned, 403);
            abort_unless($locked->event?->isInterprovincialTrials()
                && (int) $locked->categoryEvent?->event_id === (int) $locked->event_id
                && (int) $locked->nomination?->event_id === (int) $locked->event_id
                && (int) $locked->nomination?->category_event_id === (int) $locked->category_event_id
                && (int) $locked->nomination?->player_id === (int) $locked->player_id, 404);

            if ($locked->status === InterprovincialTrialInvitation::DECLINED) return $locked;
            if (! in_array($locked->status, ['queued', 'sent'], true)) {
                throw ValidationException::withMessages(['invitation' => 'This invitation can no longer be declined.']);
            }

            $locked->update(['status' => InterprovincialTrialInvitation::DECLINED]);
            activity('interprovincial_trial_invitation')
                ->performedOn($locked)
                ->causedBy($actor)
                ->withProperties([
                    'event_id' => $locked->event_id,
                    'category_event_id' => $locked->category_event_id,
                    'nomination_id' => $locked->nomination_id,
                    'player_id' => $locked->player_id,
                ])
                ->log('Trial invitation declined');

            return $locked->fresh();
        });
    }

    public function confirmPaidOrder(RegistrationOrder $order): void
    {
        DB::transaction(function () use ($order): void {
            $lockedOrder = RegistrationOrder::query()->lockForUpdate()->findOrFail($order->id);
            $invitation = InterprovincialTrialInvitation::query()
                ->lockForUpdate()
                ->where('order_id', $lockedOrder->id)
                ->first();

            if (! $invitation) {
                return;
            }

            $items = RegistrationOrderItems::query()
                ->where('order_id', $lockedOrder->id)
                ->lockForUpdate()
                ->get();
            $item = $items->count() === 1 ? $items->first() : null;
            $entry = CategoryEventRegistration::query()
                ->where('registration_id', $invitation->registration_id)
                ->where('category_event_id', $invitation->category_event_id)
                ->lockForUpdate()
                ->first();
            $category = $invitation->categoryEvent()->lockForUpdate()->first();
            $event = $invitation->event()->lockForUpdate()->first();
            $player = $invitation->player()->lockForUpdate()->first();
            $nominationMatches = DB::table('event_nominations')
                ->where('id', $invitation->nomination_id)
                ->where('event_id', $invitation->event_id)
                ->where('category_event_id', $invitation->category_event_id)
                ->where('player_id', $invitation->player_id)
                ->exists();
            $registrationHasPlayer = DB::table('player_registrations')
                ->where('registration_id', $invitation->registration_id)
                ->where('player_id', $invitation->player_id)
                ->exists();

            $tupleMatches = $event?->isInterprovincialTrials()
                && $category
                && (int) $category->event_id === (int) $invitation->event_id
                && $nominationMatches
                && $registrationHasPlayer
                && $player
                && $entry
                && $item
                && (int) $entry->user_id === (int) $lockedOrder->user_id
                && (int) $item->order_id === (int) $lockedOrder->id
                && (int) $item->registration_id === (int) $invitation->registration_id
                && (int) $item->category_event_id === (int) $invitation->category_event_id
                && (int) $item->player_id === (int) $invitation->player_id
                && (int) $item->user_id === (int) $lockedOrder->user_id
                && $this->moneyInCents($item->item_price) === $this->moneyInCents($lockedOrder->total_fee);

            if (! $tupleMatches) {
                throw ValidationException::withMessages([
                    'payment' => 'The paid order does not match the complete trial invitation registration.',
                ]);
            }

            if ((int) $lockedOrder->pay_status !== 1 || (int) $entry->payment_status_id !== 1) {
                return;
            }

            if ($invitation->status === InterprovincialTrialInvitation::PAID_CONFIRMED) {
                return;
            }

            if ($invitation->status !== InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT) {
                throw ValidationException::withMessages(['payment' => 'The trial invitation is not awaiting payment.']);
            }
            $invitation->update(['status' => InterprovincialTrialInvitation::PAID_CONFIRMED, 'paid_at' => now()]);
        });
    }

    public function resetCancelledPayment(RegistrationOrder $order, User $user): void
    {
        DB::transaction(function () use ($order, $user): void {
            $lockedOrder = RegistrationOrder::query()->lockForUpdate()->findOrFail($order->id);
            abort_unless((int) $lockedOrder->user_id === (int) $user->id, 403);
            if ($lockedOrder->status !== 'cancelled'
                || (int) $lockedOrder->pay_status === 1
                || (bool) $lockedOrder->payfast_paid
                || round((float) $lockedOrder->wallet_reserved, 2) !== 0.0) {
                throw ValidationException::withMessages([
                    'payment' => 'Only a cancelled, unpaid checkout with no wallet reservation can be reset.',
                ]);
            }
            $invitation = InterprovincialTrialInvitation::query()->with(['categoryEvent', 'nomination'])->lockForUpdate()
                ->where('order_id', $lockedOrder->id)->first();
            if (! $invitation || $invitation->status !== InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT) return;
            $this->resetCancelledPaymentAttempt($invitation, $lockedOrder);
        });
    }

    private function resetCancelledPaymentAttempt(
        InterprovincialTrialInvitation $invitation,
        RegistrationOrder $lockedOrder
    ): void {
        if ($lockedOrder->payfast_handed_off_at) {
            throw ValidationException::withMessages([
                'payment' => 'A checkout already sent to PayFast cannot be reset while payment is resolving.',
            ]);
        }
        $itemMatches = RegistrationOrderItems::query()->where('order_id', $lockedOrder->id)
            ->where('registration_id', $invitation->registration_id)
            ->where('category_event_id', $invitation->category_event_id)
            ->where('player_id', $invitation->player_id)
            ->where('user_id', $lockedOrder->user_id)->exists();
        abort_unless((int) $invitation->categoryEvent?->event_id === (int) $invitation->event_id
            && (int) $invitation->nomination?->event_id === (int) $invitation->event_id
            && (int) $invitation->nomination?->category_event_id === (int) $invitation->category_event_id
            && (int) $invitation->nomination?->player_id === (int) $invitation->player_id
            && $itemMatches, 404);
        CategoryEventRegistration::query()
            ->where('registration_id', $invitation->registration_id)
            ->where('category_event_id', $invitation->category_event_id)
            ->where(fn ($query) => $query->whereNull('payment_status_id')->orWhere('payment_status_id', 0))
            ->whereNull('pf_transaction_id')
            ->get()->each->delete();
        $invitation->update(['registration_id' => null, 'order_id' => null, 'accepted_at' => null, 'status' => 'sent']);
    }

    public function handlePaidWithdrawal(int $registrationId, ?User $actor = null): void
    {
        DB::transaction(function () use ($registrationId, $actor): void {
            $invitation = InterprovincialTrialInvitation::query()->lockForUpdate()
                ->where('registration_id', $registrationId)
                ->where('status', InterprovincialTrialInvitation::PAID_CONFIRMED)->first();
            if (! $invitation) return;
            $entry = CategoryEventRegistration::query()
                ->where('registration_id', $registrationId)
                ->where('category_event_id', $invitation->category_event_id)
                ->lockForUpdate()
                ->first();
            if (! $entry || $entry->status !== 'withdrawn') {
                throw new \RuntimeException('A paid trial invitation can only be withdrawn after its entry is withdrawn.');
            }
            $invitation->update(['status' => InterprovincialTrialInvitation::WITHDRAWN,
                'withdrawn_at' => $entry->withdrawn_at ?: now()]);

            activity('interprovincial_trial_invitation')
                ->performedOn($invitation)
                ->causedBy($actor)
                ->withProperties([
                    'event_id' => $invitation->event_id,
                    'category_event_id' => $invitation->category_event_id,
                    'registration_id' => $registrationId,
                ])
                ->log('Paid trial invitation synchronized to withdrawn entry');
        });
    }

    public function restorePaidWithdrawal(CategoryEventRegistration $entry, User $actor): void
    {
        DB::transaction(function () use ($entry, $actor): void {
            $lockedEntry = CategoryEventRegistration::query()
                ->with('categoryEvent')
                ->lockForUpdate()
                ->findOrFail($entry->id);
            $invitation = InterprovincialTrialInvitation::query()
                ->where('registration_id', $lockedEntry->registration_id)
                ->where('category_event_id', $lockedEntry->category_event_id)
                ->lockForUpdate()
                ->first();

            if (! $invitation) {
                return;
            }

            if ($invitation->status === InterprovincialTrialInvitation::PAID_CONFIRMED) {
                return;
            }

            if ($invitation->status !== InterprovincialTrialInvitation::WITHDRAWN
                || $lockedEntry->status !== 'active'
                || (int) $lockedEntry->payment_status_id !== 1
                || (int) $lockedEntry->categoryEvent?->event_id !== (int) $invitation->event_id
                || ! $invitation->paid_at
                || ! $invitation->order_id) {
                throw ValidationException::withMessages([
                    'registration' => 'Only a matching paid trial invitation can be restored.',
                ]);
            }

            $invitation->update([
                'status' => InterprovincialTrialInvitation::PAID_CONFIRMED,
                'withdrawn_at' => null,
            ]);

            activity('interprovincial_trial_invitation')
                ->performedOn($invitation)
                ->causedBy($actor)
                ->withProperties([
                    'event_id' => $invitation->event_id,
                    'category_event_id' => $invitation->category_event_id,
                    'registration_id' => $lockedEntry->registration_id,
                    'order_id' => $invitation->order_id,
                ])
                ->log('Paid trial invitation restored after withdrawal cancellation');
        });
    }

    public function prepare(Event $event, User $actor): InterprovincialTrialInvitationBatch
    {
        throw new \LogicException('Legacy invitation preparation is disabled; use the previewed send workflow.');

        return DB::transaction(function () use ($event, $actor): InterprovincialTrialInvitationBatch {
            $batch = InterprovincialTrialInvitationBatch::query()->lockForUpdate()
                ->where('event_id', $event->id)->where('status', InterprovincialTrialInvitationBatch::DRAFT)->latest('id')->first();
            $nominations = $event->nominations()->lockForUpdate()->with(['player.user', 'player.users', 'categoryEvent.category'])->orderBy('id')->get();
            $rows = $this->snapshotRows($event, $nominations);
            $hash = $this->snapshotHash($rows);
            $batch ??= InterprovincialTrialInvitationBatch::create([
                'event_id' => $event->id, 'status' => InterprovincialTrialInvitationBatch::DRAFT,
                'created_by_user_id' => $actor->id, 'snapshot_hash' => $hash,
            ]);
            if (! hash_equals($batch->snapshot_hash, $hash)) {
                $batch->update(['snapshot_hash' => $hash, 'snapshot_version' => $batch->snapshot_version + 1,
                    'reviewed_by_user_id' => null, 'reviewed_at' => null]);
            }
            $nominationIds = $nominations->pluck('id');
            $batch->invitations()->whereNotIn('nomination_id', $nominationIds)->delete();

            foreach ($rows as $row) {
                $existing = InterprovincialTrialInvitation::query()->where('batch_id', $batch->id)
                    ->where('nomination_id', $row['nomination_id'])->first();
                $latestStatus = $existing?->status ?? InterprovincialTrialInvitation::query()
                    ->where('event_id', $event->id)->where('nomination_id', $row['nomination_id'])
                    ->latest('id')->value('status');
                $attributes = ['event_id' => $event->id, 'category_event_id' => $row['category_event_id'],
                    'player_id' => $row['player_id'], 'recipient_email' => $row['recipient_email'],
                    'recipient_name' => $row['recipient_name']];
                if (! in_array($latestStatus, [
                    InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT,
                    InterprovincialTrialInvitation::PAID_CONFIRMED,
                    InterprovincialTrialInvitation::WITHDRAWN,
                    InterprovincialTrialInvitation::DECLINED,
                ], true)) {
                    $attributes['status'] = 'prepared';
                } elseif (! $existing) {
                    $attributes['status'] = $latestStatus;
                }
                InterprovincialTrialInvitation::updateOrCreate(
                    ['batch_id' => $batch->id, 'nomination_id' => $row['nomination_id']],
                    $attributes
                );
            }

            return $batch->fresh(['event', 'invitations.player', 'invitations.categoryEvent.category']);
        });
    }

    public function saveMessage(InterprovincialTrialInvitationBatch $batch, string $subject, string $body): InterprovincialTrialInvitationBatch
    {
        throw new \LogicException('Legacy invitation message staging is disabled; use the previewed send workflow.');

        return DB::transaction(function () use ($batch, $subject, $body): InterprovincialTrialInvitationBatch {
            $locked = InterprovincialTrialInvitationBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($locked->status === InterprovincialTrialInvitationBatch::QUEUED) {
                throw ValidationException::withMessages(['message' => 'A queued invitation message cannot be changed.']);
            }
            $hash = $this->messageHash($subject, $body);
            if (! hash_equals((string) $locked->message_hash, $hash)) {
                $locked->update([
                    'email_subject' => $subject,
                    'email_body' => $body,
                    'message_hash' => $hash,
                    'reviewed_message_hash' => null,
                    'content_version' => $locked->content_version + 1,
                    'prepared_at' => now(),
                    'status' => InterprovincialTrialInvitationBatch::DRAFT,
                    'reviewed_by_user_id' => null,
                    'reviewed_at' => null,
                ]);
            }

            return $locked->fresh(['invitations.player', 'invitations.categoryEvent.category']);
        });
    }

    public function queueCurrentNominations(Event $event, User $actor, string $subject, string $body): array
    {
        throw new \LogicException('Legacy direct invitation sending is disabled; use the previewed send workflow.');

        return DB::transaction(function () use ($event, $actor, $subject, $body): array {
            Event::query()->lockForUpdate()->findOrFail($event->id);
            $queued = InterprovincialTrialInvitationBatch::query()
                ->where('event_id', $event->id)
                ->where('status', InterprovincialTrialInvitationBatch::QUEUED)
                ->latest('id')
                ->first();
            if ($queued) {
                return ['batch' => $queued, 'queued_count' => 0, 'already_queued' => true];
            }

            $nominations = $event->nominations()->lockForUpdate()
                ->with(['player.user', 'player.users', 'categoryEvent.category'])
                ->orderBy('id')->get();
            if ($nominations->isEmpty()) {
                throw ValidationException::withMessages(['batch' => 'Nominate at least one player before sending invitations.']);
            }
            $rows = $this->snapshotRows($event, $nominations);
            if (collect($rows)->contains(fn (array $row): bool => blank($row['recipient_email']))) {
                throw ValidationException::withMessages(['batch' => 'Every nominated player needs a directly assigned or linked account email.']);
            }

            $hash = $this->snapshotHash($rows);
            $messageHash = $this->messageHash($subject, $body);
            $batch = InterprovincialTrialInvitationBatch::query()->lockForUpdate()
                ->where('event_id', $event->id)
                ->whereIn('status', [InterprovincialTrialInvitationBatch::DRAFT, InterprovincialTrialInvitationBatch::REVIEWED])
                ->latest('id')->first();
            $batch ??= InterprovincialTrialInvitationBatch::create([
                'event_id' => $event->id,
                'status' => InterprovincialTrialInvitationBatch::DRAFT,
                'created_by_user_id' => $actor->id,
                'snapshot_hash' => $hash,
            ]);
            $snapshotChanged = ! hash_equals((string) $batch->snapshot_hash, $hash);
            $messageChanged = ! hash_equals((string) $batch->message_hash, $messageHash);
            $batch->update([
                'snapshot_hash' => $hash,
                'snapshot_version' => $batch->snapshot_version + ($snapshotChanged ? 1 : 0),
                'email_subject' => $subject,
                'email_body' => $body,
                'message_hash' => $messageHash,
                'reviewed_message_hash' => $messageHash,
                'content_version' => $batch->content_version + ($messageChanged ? 1 : 0),
                'prepared_at' => now(),
                'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(),
            ]);

            $nominationIds = $nominations->pluck('id');
            $batch->invitations()->whereNotIn('nomination_id', $nominationIds)->delete();
            foreach ($rows as $row) {
                $invitation = InterprovincialTrialInvitation::query()
                    ->where('batch_id', $batch->id)->where('nomination_id', $row['nomination_id'])->first();
                $latestStatus = $invitation?->status ?? InterprovincialTrialInvitation::query()
                    ->where('event_id', $event->id)->where('nomination_id', $row['nomination_id'])
                    ->latest('id')->value('status');
                $attributes = [
                    'event_id' => $event->id,
                    'category_event_id' => $row['category_event_id'],
                    'player_id' => $row['player_id'],
                    'recipient_email' => $row['recipient_email'],
                    'recipient_name' => $row['recipient_name'],
                ];
                if (! in_array($latestStatus, [
                    InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT,
                    InterprovincialTrialInvitation::PAID_CONFIRMED,
                    InterprovincialTrialInvitation::WITHDRAWN,
                    InterprovincialTrialInvitation::DECLINED,
                ], true)) $attributes['status'] = 'prepared';
                elseif (! $invitation) $attributes['status'] = $latestStatus;
                InterprovincialTrialInvitation::updateOrCreate(
                    ['batch_id' => $batch->id, 'nomination_id' => $row['nomination_id']],
                    $attributes
                );
            }

            $batch->update(['status' => InterprovincialTrialInvitationBatch::REVIEWED]);
            $queuedCount = $this->claimAndQueueInvitations($batch->fresh('event'));
            $batch->update(['status' => InterprovincialTrialInvitationBatch::QUEUED, 'queued_at' => now()]);

            return ['batch' => $batch->fresh(), 'queued_count' => $queuedCount, 'already_queued' => false];
        });
    }

    public function review(InterprovincialTrialInvitationBatch $batch, User $actor, string $expectedHash, string $expectedMessageHash): InterprovincialTrialInvitationBatch
    {
        throw new \LogicException('Legacy invitation review is disabled; use the previewed send workflow.');

        return DB::transaction(function () use ($batch, $actor, $expectedHash, $expectedMessageHash): InterprovincialTrialInvitationBatch {
            $locked = InterprovincialTrialInvitationBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if (! hash_equals($locked->snapshot_hash, $expectedHash)) {
                throw ValidationException::withMessages(['batch' => 'The invitation recipients changed. Prepare and review the list again.']);
            }
            if ($locked->status === InterprovincialTrialInvitationBatch::REVIEWED) return $locked;
            if ($locked->status !== InterprovincialTrialInvitationBatch::DRAFT) throw ValidationException::withMessages(['batch' => 'Only a draft invitation batch can be reviewed.']);
            if (! $locked->message_hash || ! filled($locked->email_subject) || ! filled($locked->email_body)
                || ! hash_equals($locked->message_hash, $expectedMessageHash)
                || ! hash_equals($locked->message_hash, $this->messageHash($locked->email_subject, $locked->email_body))) {
                throw ValidationException::withMessages(['message' => 'Save and review the current invitation subject and message.']);
            }
            $currentRows = $this->snapshotRows($locked->event, $locked->event->nominations()->lockForUpdate()->with(['player.user', 'player.users', 'categoryEvent.category'])->orderBy('id')->get());
            if (! hash_equals($locked->snapshot_hash, $this->snapshotHash($currentRows))) {
                throw ValidationException::withMessages(['batch' => 'Nominations or recipients changed. Prepare and review the list again.']);
            }
            if (! $locked->invitations()->exists()) throw ValidationException::withMessages(['batch' => 'Nominate at least one player before reviewing invitations.']);
            if ($locked->invitations()->whereNull('recipient_email')->exists()) throw ValidationException::withMessages(['batch' => 'Every nominated player needs a directly assigned or linked account email.']);
            $locked->update(['status' => InterprovincialTrialInvitationBatch::REVIEWED, 'reviewed_by_user_id' => $actor->id,
                'reviewed_at' => now(), 'reviewed_message_hash' => $locked->message_hash]);
            return $locked->fresh();
        });
    }

    public function queue(InterprovincialTrialInvitationBatch $batch): InterprovincialTrialInvitationBatch
    {
        throw new \LogicException('Legacy invitation queueing is disabled; use the previewed send workflow.');

        return DB::transaction(function () use ($batch): InterprovincialTrialInvitationBatch {
            $locked = InterprovincialTrialInvitationBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($locked->status === InterprovincialTrialInvitationBatch::QUEUED) return $locked;
            if ($locked->status !== InterprovincialTrialInvitationBatch::REVIEWED) {
                throw ValidationException::withMessages(['batch' => 'Review the exact recipient list before sending invitations.']);
            }
            if (! $locked->message_hash || ! $locked->reviewed_message_hash
                || ! hash_equals($locked->message_hash, $locked->reviewed_message_hash)
                || ! hash_equals($locked->message_hash, $this->messageHash((string) $locked->email_subject, (string) $locked->email_body))) {
                throw ValidationException::withMessages(['message' => 'The invitation message changed. Review it again before queueing.']);
            }
            $currentRows = $this->snapshotRows(
                $locked->event,
                $locked->event->nominations()->lockForUpdate()
                    ->with(['player.user', 'player.users', 'categoryEvent.category'])
                    ->orderBy('id')->get()
            );
            if (! hash_equals($locked->snapshot_hash, $this->snapshotHash($currentRows))) {
                throw ValidationException::withMessages(['batch' => 'Nominations or recipients changed. Prepare and review the list again.']);
            }
            if ($locked->invitations()->whereNull('recipient_email')->exists()) {
                throw ValidationException::withMessages(['batch' => 'Every nominated player needs a directly assigned or linked account email.']);
            }
            $this->claimAndQueueInvitations($locked);
            $locked->update(['status' => InterprovincialTrialInvitationBatch::QUEUED, 'queued_at' => now()]);
            return $locked->fresh();
        });
    }

    public function retryFailed(InterprovincialTrialInvitationBatch $batch, InterprovincialTrialInvitation $invitation): bool
    {
        return DB::transaction(function () use ($batch, $invitation): bool {
            $lockedBatch = InterprovincialTrialInvitationBatch::query()->lockForUpdate()->findOrFail($batch->id);
            $locked = InterprovincialTrialInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            abort_unless((int) $locked->batch_id === (int) $lockedBatch->id
                && (int) $locked->event_id === (int) $lockedBatch->event_id, 404);

            if (in_array($locked->status, ['queued', 'sending', 'sent'], true)) return false;
            if ($locked->status !== 'failed') {
                throw ValidationException::withMessages(['invitation' => 'Only a failed invitation email can be retried.']);
            }

            $dispatch = DB::table('interprovincial_trial_mail_dispatches')->where('invitation_id', $locked->id)
                ->where('kind', 'initial')->lockForUpdate()->first();
            $log = $dispatch?->bulk_email_log_id ? BulkEmailLog::query()->lockForUpdate()->find($dispatch->bulk_email_log_id) : null;
            if (! $log || $log->mail_type !== 'interprovincial_trial_invitation'
                || $log->related_type !== InterprovincialTrialInvitation::class
                || (int) $log->related_id !== (int) $locked->id
                || $log->status !== 'failed' || $log->sent_at) {
                throw ValidationException::withMessages(['invitation' => 'The failed invitation does not have a matching failed email log.']);
            }

            $locked->update(['status' => 'queued', 'queued_at' => now()]);
            $log->update(['status' => 'queued', 'queued_at' => now(), 'failed_at' => null, 'skipped_at' => null, 'error_message' => null]);
            DB::afterCommit(fn () => SendInterprovincialTrialInvitationEmailJob::dispatch($log->id, $lockedBatch->event_id));

            return true;
        });
    }

    public function retryFailedFollowUp(Event $event, InterprovincialTrialInvitation $invitation): bool
    {
        return DB::transaction(function () use ($event, $invitation): bool {
            $locked = InterprovincialTrialInvitation::query()->lockForUpdate()->findOrFail($invitation->id);
            abort_unless((int) $locked->event_id === (int) $event->id, 404);

            if (! in_array($locked->status, ['sent', InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT], true)) {
                throw ValidationException::withMessages(['invitation' => 'This player is no longer eligible for a follow-up.']);
            }

            $dispatch = DB::table('interprovincial_trial_mail_dispatches as dispatches')
                ->join('bulk_email_logs as logs', 'logs.id', '=', 'dispatches.bulk_email_log_id')
                ->where('dispatches.invitation_id', $locked->id)
                ->where('dispatches.kind', 'follow_up')
                ->where('logs.status', 'failed')
                ->whereNull('logs.sent_at')
                ->orderByDesc('dispatches.id')
                ->lockForUpdate()
                ->select('dispatches.*')
                ->first();
            if (! $dispatch) return false;

            $log = BulkEmailLog::query()->lockForUpdate()->find($dispatch->bulk_email_log_id);
            if (! $log || $log->mail_type !== 'interprovincial_trial_invitation'
                || $log->related_type !== InterprovincialTrialInvitation::class
                || (int) $log->related_id !== (int) $locked->id) {
                throw ValidationException::withMessages(['invitation' => 'The failed follow-up does not have a matching email log.']);
            }

            $log->update([
                'status' => 'queued',
                'queued_at' => now(),
                'failed_at' => null,
                'skipped_at' => null,
                'error_message' => null,
            ]);
            DB::afterCommit(fn () => SendInterprovincialTrialInvitationEmailJob::dispatch($log->id, $event->id));

            return true;
        });
    }

    private function claimAndQueueInvitations(InterprovincialTrialInvitationBatch $batch): int
    {
        $claimedCount = 0;
        foreach ($batch->invitations()->whereNotNull('recipient_email')
            ->where('status', 'prepared')
            ->with('categoryEvent.category')->get() as $invitation) {
            $requestToken = $this->deterministicInitialToken((int) $invitation->id);
            $claimed = DB::table('interprovincial_trial_mail_dispatches')->insertOrIgnore([
                'invitation_id' => $invitation->id,
                'request_token' => $requestToken,
                'kind' => 'initial',
                'requested_by_user_id' => $batch->created_by_user_id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            if ($claimed) {
                $log = BulkEmailLog::create([
                    'mail_type' => 'interprovincial_trial_invitation',
                    'related_type' => InterprovincialTrialInvitation::class,
                    'related_id' => $invitation->id,
                    'recipient_email' => $invitation->recipient_email,
                    'recipient_name' => $invitation->recipient_name,
                    'status' => 'queued',
                    'payload' => [
                        'event_id' => $batch->event_id, 'invitation_id' => $invitation->id,
                        'mode' => 'initial', 'kind' => 'initial', 'request_token' => $requestToken,
                        'requested_by_user_id' => $batch->created_by_user_id,
                        'snapshot_hash' => $batch->snapshot_hash, 'message_hash' => $batch->message_hash,
                        'content_version' => $batch->content_version, 'subject' => $batch->email_subject,
                        'body' => $batch->email_body, 'recipient_name' => $invitation->recipient_name,
                        'event_name' => $batch->event->name,
                        'category_name' => $invitation->categoryEvent?->category?->name,
                    ],
                    'queued_at' => now(),
                ]);
                DB::table('interprovincial_trial_mail_dispatches')->where('invitation_id', $invitation->id)
                    ->where('request_token', $requestToken)
                    ->update(['bulk_email_log_id' => $log->id, 'updated_at' => now()]);
                DB::afterCommit(fn () => SendInterprovincialTrialInvitationEmailJob::dispatch($log->id, $batch->event_id));
                $claimedCount++;
            }
            if ($claimed) {
                $invitation->update(['status' => 'queued', 'queued_at' => $invitation->queued_at ?? now()]);
            }
        }

        return $claimedCount;
    }

    private function attemptSelection(Event $event, string $mode, ?int $invitationId, bool $lock): array
    {
        if (! in_array($mode, ['new', 'not_registered', 'individual'], true)) {
            throw ValidationException::withMessages(['mode' => 'Choose a supported invitation send mode.']);
        }
        $nominationQuery = $event->nominations()->with(['player.user', 'player.users', 'categoryEvent.category'])->orderBy('id');
        if ($lock) $nominationQuery->lockForUpdate();
        $nominations = $nominationQuery->get();
        $snapshotRows = collect($this->snapshotRows($event, $nominations))->keyBy('nomination_id');
        $invitationQuery = InterprovincialTrialInvitation::query()->where('event_id', $event->id)->orderByDesc('id');
        if ($lock) $invitationQuery->lockForUpdate();
        $invitations = $invitationQuery->get()->unique('nomination_id')->keyBy('nomination_id');
        $successfulInitialIds = DB::table('interprovincial_trial_mail_dispatches as dispatches')
            ->join('bulk_email_logs as logs', 'logs.id', '=', 'dispatches.bulk_email_log_id')
            ->where('dispatches.kind', 'initial')->where('logs.status', 'sent')->whereNotNull('logs.sent_at')
            ->pluck('dispatches.invitation_id')->map(fn ($id): int => (int) $id)->all();
        $dispatchedIds = DB::table('interprovincial_trial_mail_dispatches')->pluck('invitation_id')
            ->map(fn ($id): int => (int) $id)->all();
        $recipients = [];
        $blockers = [];
        foreach ($nominations as $nomination) {
            $row = $snapshotRows->get((int) $nomination->id);
            $invitation = $invitations->get((int) $nomination->id);
            if ($mode === 'individual' && (int) $invitation?->id !== (int) $invitationId) continue;
            $eligible = match ($mode) {
                'new' => ! $invitation || (! in_array((int) $invitation->id, $dispatchedIds, true) && $invitation->status === 'prepared'),
                'not_registered', 'individual' => $invitation
                    && in_array($invitation->status, ['sent', InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT], true)
                    && in_array((int) $invitation->id, $successfulInitialIds, true),
            };
            if (! $eligible) continue;
            if (blank($row['recipient_email'])) {
                $blockers[] = ['nomination_id' => $nomination->id, 'name' => $row['recipient_name'], 'reason' => 'Missing linked account email'];
                continue;
            }
            $recipients[] = [
                'invitation_id' => $invitation?->id,
                'nomination_id' => (int) $nomination->id,
                'category_event_id' => (int) $nomination->category_event_id,
                'player_id' => (int) $nomination->player_id,
                'name' => $row['recipient_name'],
                'email' => $row['recipient_email'],
                'category' => $nomination->categoryEvent?->category?->name,
                'status' => $invitation?->status ?? 'nominated',
            ];
        }

        if ($mode === 'individual' && count($recipients) + count($blockers) !== 1) {
            throw ValidationException::withMessages(['invitation' => 'This invitation is no longer eligible to resend.']);
        }

        return ['mode' => $mode, 'recipients' => $recipients, 'blockers' => $blockers];
    }

    private function attemptRecipientHash(array $recipients): string
    {
        return hash('sha256', json_encode(collect($recipients)->map(fn (array $row): array => [
            'invitation_id' => $row['invitation_id'],
            'nomination_id' => $row['nomination_id'],
            'player_id' => $row['player_id'],
            'category_event_id' => $row['category_event_id'],
            'email' => $row['email'],
            'status' => $row['status'],
        ])->values()->all(), JSON_THROW_ON_ERROR));
    }

    private function deterministicInitialToken(int $invitationId): string
    {
        $hex = md5('interpro-initial-invitation-'.$invitationId);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-5'.substr($hex, 13, 3).'-a'.substr($hex, 17, 3).'-'.substr($hex, 20, 12);
    }

    private function snapshotRows(Event $event, $nominations): array
    {
        return $nominations->map(function ($nomination) use ($event): array {
            abort_unless((int) $nomination->categoryEvent?->event_id === (int) $event->id, 422);
            $player = $nomination->player;
            $direct = $player?->user && filled($player->user->email) ? $player->user : null;
            $recipient = $direct ?: $player?->users?->filter(fn ($user) => filled($user->email))->sortBy('id')->first();
            return ['nomination_id' => (int) $nomination->id, 'category_event_id' => (int) $nomination->category_event_id,
                'player_id' => (int) $nomination->player_id, 'recipient_email' => $recipient?->email,
                'recipient_user_id' => $recipient?->id, 'recipient_name' => trim(($player?->name ?? '').' '.($player?->surname ?? ''))];
        })->sortBy('nomination_id')->values()->all();
    }

    private function snapshotHash(array $rows): string
    {
        return hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR));
    }

    private function messageHash(string $subject, string $body): string
    {
        return hash('sha256', json_encode(['subject' => $subject, 'body' => $body], JSON_THROW_ON_ERROR));
    }

    private function moneyInCents(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }
}
