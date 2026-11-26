<?php

namespace App\Domain\Entries\Services;

use App\Domain\Refunds\Services\AdminRegistrationWalletRefundService;
use App\Domain\Refunds\Services\RefundExecutionService;
use App\Models\{CategoryAdjustment, CategoryEvent, CategoryEventRegistration, Event, EventCommunicationBatch, IndividualProviderRefundAttempt, RegistrationOrder, User};
use App\Services\{BulkMailDispatcher, CommunicationSender};
use App\Services\TeamSelection\TeamSelectionContactService;
use Illuminate\Support\Facades\{Crypt, DB};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** One reviewed, atomic cancellation. Money and the database mail outbox commit together. */
class CategoryCancellationService
{
    public function authorize(Event $event, CategoryEvent $category, User $actor): void
    {
        abort_unless((int) $category->event_id === (int) $event->id, 404);
        abort_unless($actor->hasRole('super-user') || ($actor->hasRole('admin') && $actor->is_event_admin($event->id)), 403);
        abort_unless($event->isIndividual() && !$event->isMasters() && !$event->isInterprovincialTrials() && !$event->isCamp(), 422,
            'Category cancellation currently supports ordinary individual events only.');
    }

    public function preview(Event $event, CategoryEvent $category, User $actor, string $reason): array
    {
        $snapshot = DB::transaction(fn () => $this->snapshot($event, $category, $actor, $reason));
        return $snapshot + ['token' => Crypt::encryptString(json_encode([
            'snapshot' => $snapshot, 'expires' => now()->addMinutes(15)->timestamp,
        ], JSON_THROW_ON_ERROR))];
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['cancellation' => $message]);
    }

    private function snapshot(Event $event, CategoryEvent $category, User $actor, string $reason): array
    {
        $event = Event::lockForUpdate()->findOrFail($event->id);
        $category = CategoryEvent::lockForUpdate()->findOrFail($category->id);
        $this->authorize($event, $category, $actor);
        if ($category->isCancelled()) $this->reject('This category has already been cancelled.');
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 2000) $this->reject('Enter a cancellation reason of up to 2,000 characters.');
        $entries = $category->allCategoryEventRegistrations()->with('registration.players')->orderBy('id')->lockForUpdate()->get();
        if ($entries->count() > 500) $this->reject('This category is too large for this cancellation workflow. Contact finance support.');
        if (CategoryAdjustment::where(fn ($q) => $q->where('target_category_id', $category->id)->orWhereIn('entry_id', $entries->modelKeys()))
            ->where('status', 'pending')->exists()) $this->reject('Resolve pending category changes before cancellation.');
        $orders = RegistrationOrder::whereHas('items', fn ($q) => $q->where('category_event_id', $category->id))
            ->with('items')->orderBy('id')->lockForUpdate()->get();
        foreach ($orders as $order) {
            if (!$order->pay_status && (!in_array($order->status, ['cancelled', 'expired'], true)
                || $order->payfast_paid || $order->wallet_debited || filled($order->payfast_pf_payment_id))) {
                $this->reject('Resolve unpaid checkouts, payment handoffs and settlement evidence before cancelling. No changes were made.');
            }
            if ($order->pay_status && $order->items->where('category_event_id', $category->id)->contains(
                fn ($item) => !$entries->contains('registration_id', $item->registration_id))) {
                $this->reject('A paid checkout has no current entry. Reconcile it before cancelling.');
            }
        }
        if ($category->draws()->lockForUpdate()->exists()) {
            $this->reject('This category already has a draw. It requires reviewed draw cancellation before refunds; no changes were made.');
        }
        $rows = [];
        $contacts = [];
        $contactService = app(TeamSelectionContactService::class);
        foreach ($entries as $entry) {
            $completed = $entry->refund_status === 'completed';
            if ($completed && $entry->status !== 'withdrawn') $this->reject('An active entry already has a refund. Reconcile it first.');
            if ($completed) continue;
            if (!in_array((string) $entry->refund_status, CategoryEventRegistration::NO_REFUND_STATUSES, true)
                || filled($entry->refund_method) || $entry->refunded_at || $entry->refund_waived_at
                || (float) $entry->refund_fee !== 0.0 || (float) $entry->refund_net !== 0.0 || (float) $entry->refund_gross !== 0.0) {
                $this->reject('A pending, waived or inconsistent refund needs finance review before cancellation.');
            }
            if (!in_array($entry->status, ['active', 'withdrawn'], true)) $this->reject('Resolve pending or inconsistent entries before cancelling.');
            if (IndividualProviderRefundAttempt::where('entry_id', $entry->id)
                ->when(filled($entry->pf_transaction_id), fn ($q) => $q->orWhere('pf_payment_id', $entry->pf_transaction_id))->exists()) {
                $this->reject('A provider refund was attempted. Reconcile it before cancelling.');
            }
            $funding = app(AdminRegistrationWalletRefundService::class)->categoryCancellationSnapshot($event, $entry);
            $gross = $funding['gross'] ?? 0.0;
            $emails = $entry->registration?->players->flatMap(fn ($player) => $contactService->emails($player))->unique()->sort()->values()->all() ?? [];
            if (!$emails) $this->reject('A player has no valid notification contact. Add the contact and review again.');
            foreach ($emails as $email) $contacts[$email] = ['email' => $email, 'name' => null];
            $rows[] = ['entry_id' => $entry->id, 'player' => $entry->display_name, 'status' => $entry->status,
                'funding' => $funding, 'amount' => $gross, 'emails' => $emails];
        }
        ksort($contacts);
        $sender = CommunicationSender::eventDefaults($event, $actor);
        $subject = $category->category->name.' cancelled — '.$event->name;
        $message = "Dear Parents and Players,\n\nThe {$category->category->name} category at {$event->name} has been cancelled.\n\n{$reason}\n\nThe full entry fee has been credited to the Cape Tennis wallet of the original payer, with no handling fee. This credit can be used towards future Cape Tennis events. Free entries have no payment to refund.\n\nWe are sorry for the disappointment and thank you for your understanding.\n\nKind regards,\n{$sender['from_name']}";
        return ['event_id' => $event->id, 'category_id' => $category->id, 'category' => $category->category->name,
            'actor_id' => $actor->id, 'reason' => $reason, 'rows' => $rows, 'contacts' => array_values($contacts),
            'total' => round(collect($rows)->sum('amount'), 2), 'sender' => $sender, 'subject' => $subject, 'message' => $message];
    }

    public function cancel(Event $event, CategoryEvent $category, User $actor, string $token): array
    {
        $this->authorize($event, $category, $actor);
        try {
            $approved = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            $this->reject('Review a fresh cancellation preview before confirming.');
        }
        if (($approved['snapshot']['actor_id'] ?? null) !== $actor->id
            || ($approved['snapshot']['event_id'] ?? null) !== $event->id
            || ($approved['snapshot']['category_id'] ?? null) !== $category->id) {
            $this->reject('The cancellation preview does not match this actor, event and category.');
        }
        if (config('queue.connections.database.connection') && config('queue.connections.database.connection') !== config('database.default')) {
            $this->reject('The managed database mail queue must use the application database before cancellation.');
        }
        return DB::transaction(function () use ($event, $category, $actor, $approved) {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $category = CategoryEvent::lockForUpdate()->findOrFail($category->id);
            if ($category->isCancelled()) {
                if (($category->cancellation['snapshot'] ?? null) != $approved['snapshot']) $this->reject('This category was cancelled from a different review.');
                return $category->cancellation + ['duplicate' => true];
            }
            $snapshot = $this->snapshot($event, $category, $actor, $approved['snapshot']['reason']);
            if (($approved['expires'] ?? 0) < now()->timestamp || $snapshot != $approved['snapshot']) {
                $this->reject('Entries, payments or contacts changed, or this review expired. Review again before confirming.');
            }
            foreach ($snapshot['rows'] as $row) {
                $entry = CategoryEventRegistration::lockForUpdate()->findOrFail($row['entry_id']);
                if ($entry->status !== 'withdrawn') app(EntryService::class)->withdrawEntryAsAdmin($entry, $actor, contactsCoveredByWorkflow: true);
                if ($funding = $row['funding']) {
                    $payer = User::lockForUpdate()->findOrFail($funding['payer_id']);
                    $wallet = $payer->wallet ?? $payer->wallet()->create([]);
                    app(RefundExecutionService::class)->executeWalletRefund($entry, $wallet, $row['amount'], 'category_cancellation_refund', $entry->id,
                        ['category_event_id' => $category->id, 'event_id' => $event->id, 'payer_id' => $payer->id, 'actor_id' => $actor->id, 'reason' => $snapshot['reason']],
                        ['refund_method' => 'wallet', 'refund_gross' => $row['amount'], 'refund_fee' => 0, 'refund_net' => $row['amount']]);
                }
            }
            $recipients = array_map(fn ($contact) => $contact + ['kind' => 'players', 'player_keys' => [], 'subject' => $snapshot['subject'],
                'html' => nl2br(e($snapshot['message']))], $snapshot['contacts']);
            foreach ($snapshot['rows'] as $row) {
                if (!$row['funding']) continue;
                $funding = $row['funding'];
                $recipients[] = ['email' => strtolower(trim($funding['to'])), 'name' => $funding['payer_name'], 'kind' => 'payer',
                    'subject' => 'Wallet refund confirmation — '.$event->name,
                    'html' => '<p>'.e($row['player']).' — '.e($snapshot['category']).'</p><p>R'.number_format($row['amount'], 2).
                        ' has been credited to your Cape Tennis wallet. No handling fee was deducted. This credit can be used towards future events.</p><p>'.e($snapshot['reason']).'</p>'];
            }
            $batch = EventCommunicationBatch::create(['event_id' => $event->id, 'created_by' => $actor->id, 'token' => (string) Str::uuid(),
                'subject' => $snapshot['subject'], 'body' => $snapshot['message'], 'options' => ['source' => 'category_cancellation', 'category_id' => $category->id] + $snapshot['sender'],
                'recipients' => $recipients, 'issues' => [], 'fingerprint' => hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)), 'status' => 'approved', 'approved_at' => now()]);
            $queued = 0;
            foreach ($recipients as $index => $recipient) {
                $stats = app(BulkMailDispatcher::class)->dispatch('bulk_event_mail', $batch, [$recipient],
                    ['event_id' => $event->id, 'created_by' => $actor->id, 'event_communication_batch_id' => $batch->id,
                        'campaign_key' => 'category-cancellation:'.$category->id.':'.$index, 'recipient_kind' => $recipient['kind'],
                        'subject' => $recipient['subject'], 'body' => $recipient['html'], 'manual_retry_only' => true] + $snapshot['sender'],
                    atomicDatabaseQueue: true);
                if ($stats['queued'] !== 1) throw new \RuntimeException('The cancellation email outbox could not be saved. Nothing was cancelled.');
                $queued++;
            }
            $result = ['snapshot' => $snapshot, 'batch_id' => $batch->id, 'queued' => $queued, 'total' => $snapshot['total'], 'entries' => count($snapshot['rows'])];
            $category->update(['cancelled_at' => now(), 'locked_at' => now(), 'cancellation' => $result]);
            activity('category_cancellation')->performedOn($category)->causedBy($actor)->withProperties($result)->log('Category cancelled with full original-payer wallet refunds');
            return $result + ['duplicate' => false];
        }, 3);
    }
}
