<?php

namespace App\Services\InterprovincialTrials;

use App\Domain\Entries\Services\EntryEligibilityService;
use App\Domain\Payments\Services\RegistrationPaymentService;
use App\Jobs\SendInterprovincialTrialInvitationEmailJob;
use App\Models\BulkEmailLog;
use App\Models\Event;
use App\Models\InterprovincialTrialInvitation;
use App\Models\InterprovincialTrialInvitationBatch;
use App\Models\Registration;
use App\Models\CategoryEventRegistration;
use App\Models\RegistrationOrder;
use App\Models\RegistrationOrderItems;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InvitationService
{
    public function accept(InterprovincialTrialInvitation $invitation, User $user): RegistrationOrder
    {
        return DB::transaction(function () use ($invitation, $user): RegistrationOrder {
            $locked = InterprovincialTrialInvitation::query()
                ->with(['event.eventTypeModel', 'categoryEvent', 'player.users'])
                ->lockForUpdate()->findOrFail($invitation->id);

            $owned = (int) $locked->player?->userId === (int) $user->id
                || $locked->player?->users->contains(fn (User $linked): bool => (int) $linked->id === (int) $user->id);
            abort_unless($owned, 403);

            if ($locked->status === InterprovincialTrialInvitation::PAID_CONFIRMED && $locked->order_id) {
                return RegistrationOrder::findOrFail($locked->order_id);
            }
            if ($locked->status === InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT && $locked->order_id) {
                return RegistrationOrder::findOrFail($locked->order_id);
            }

            $event = $locked->event;
            $category = $locked->categoryEvent;
            if (! $event?->isInterprovincialTrials()
                || ! $event->published || ! $event->hasOpenRegistrationLifecycle()
                || (int) $event->signUp !== 1) {
                throw ValidationException::withMessages(['invitation' => 'Registration for this trial is closed.']);
            }
            if (! $category || (int) $category->event_id !== (int) $event->id
                || ! in_array($locked->status, ['queued', 'sent'], true)) {
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
            $player = $invitation->player()->lockForUpdate()->first();
            $ownerMatches = $player
                && ((int) $player->userId === (int) $lockedOrder->user_id
                    || DB::table('user_players')
                        ->where('player_id', $player->id)
                        ->where('user_id', $lockedOrder->user_id)
                        ->exists());
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

            $tupleMatches = $category
                && (int) $category->event_id === (int) $invitation->event_id
                && $nominationMatches
                && $registrationHasPlayer
                && $ownerMatches
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
            $invitation = InterprovincialTrialInvitation::query()->with('player.users')->lockForUpdate()
                ->where('order_id', $order->id)->first();
            if (! $invitation || $invitation->status !== InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT) return;
            $owned = (int) $invitation->player?->userId === (int) $user->id
                || $invitation->player?->users->contains(fn (User $linked): bool => (int) $linked->id === (int) $user->id);
            abort_unless($owned, 403);
            CategoryEventRegistration::query()
                ->where('registration_id', $invitation->registration_id)
                ->where('category_event_id', $invitation->category_event_id)
                ->where(fn ($query) => $query->whereNull('payment_status_id')->orWhere('payment_status_id', 0))
                ->whereNull('pf_transaction_id')
                ->get()->each->delete();
            $invitation->update(['registration_id' => null, 'order_id' => null, 'accepted_at' => null, 'status' => 'sent']);
        });
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
                $attributes = ['event_id' => $event->id, 'category_event_id' => $row['category_event_id'],
                    'player_id' => $row['player_id'], 'recipient_email' => $row['recipient_email'],
                    'recipient_name' => $row['recipient_name']];
                if (! $existing || ! in_array($existing->status, [
                    InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT,
                    InterprovincialTrialInvitation::PAID_CONFIRMED,
                    InterprovincialTrialInvitation::WITHDRAWN,
                ], true)) {
                    $attributes['status'] = 'prepared';
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

    public function review(InterprovincialTrialInvitationBatch $batch, User $actor, string $expectedHash, string $expectedMessageHash): InterprovincialTrialInvitationBatch
    {
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
            foreach ($locked->invitations()->whereNotNull('recipient_email')->get() as $invitation) {
                $claimed = DB::table('interprovincial_trial_mail_dispatches')->insertOrIgnore(['invitation_id' => $invitation->id, 'created_at' => now(), 'updated_at' => now()]);
                if ($claimed) {
                    $log = BulkEmailLog::create(['mail_type' => 'interprovincial_trial_invitation', 'related_type' => InterprovincialTrialInvitation::class,
                        'related_id' => $invitation->id, 'recipient_email' => $invitation->recipient_email,
                        'recipient_name' => $invitation->recipient_name, 'status' => 'queued',
                        'payload' => ['event_id' => $locked->event_id, 'invitation_id' => $invitation->id,
                            'snapshot_hash' => $locked->snapshot_hash, 'message_hash' => $locked->message_hash,
                            'content_version' => $locked->content_version, 'subject' => $locked->email_subject,
                            'body' => $locked->email_body, 'recipient_name' => $invitation->recipient_name,
                            'event_name' => $locked->event->name,
                            'category_name' => $invitation->categoryEvent?->category?->name], 'queued_at' => now()]);
                    DB::table('interprovincial_trial_mail_dispatches')->where('invitation_id', $invitation->id)->update(['bulk_email_log_id' => $log->id, 'updated_at' => now()]);
                    DB::afterCommit(fn () => SendInterprovincialTrialInvitationEmailJob::dispatch($log->id, $locked->event_id));
                }
                $invitation->update(['status' => 'queued', 'queued_at' => $invitation->queued_at ?? now()]);
            }
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

            $dispatch = DB::table('interprovincial_trial_mail_dispatches')->where('invitation_id', $locked->id)->lockForUpdate()->first();
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
