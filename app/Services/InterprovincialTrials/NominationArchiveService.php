<?php

declare(strict_types=1);

namespace App\Services\InterprovincialTrials;

use App\Models\{CategoryEvent, CategoryEventRegistration, Event, EventNomination, InterprovincialTrialInvitation, RegistrationOrderItems, RegistrationOrder, TrialPaymentProof, User};
use Illuminate\Support\Facades\{Crypt, DB};
use Illuminate\Validation\ValidationException;

/** Withdrawn participation and its financial evidence are retained independently of public nomination visibility. */
final class NominationArchiveService
{
    public function run(User $actor, int $eventId, int $categoryId, int $nominationId, string $action, string $reason, ?string $confirmation = null): array
    {
        abort_unless($actor->fresh()->hasRole('super-user'), 403);
        abort_unless(in_array($action, ['archive', 'restore'], true), 422);
        $this->ensure(strlen(trim($reason)) >= 5 && strlen($reason) <= 1000, 'Give a specific nomination archive reason.');
        return DB::transaction(function () use ($actor, $eventId, $categoryId, $nominationId, $action, $reason, $confirmation) {
            $event = Event::with('eventTypeModel')->lockForUpdate()->findOrFail($eventId);
            $category = CategoryEvent::lockForUpdate()->findOrFail($categoryId);
            $nomination = EventNomination::lockForUpdate()->findOrFail($nominationId);
            abort_unless($event->isInterprovincialTrials() && (int) $category->event_id === $eventId
                && (int) $nomination->event_id === $eventId && (int) $nomination->category_event_id === $categoryId
                && $nomination->player_id, 404);
            $invitations = InterprovincialTrialInvitation::where('nomination_id', $nominationId)->orderBy('id')->lockForUpdate()->get();
            $this->ensure($invitations->every(fn ($row) => (int) $row->event_id === $eventId && (int) $row->category_event_id === $categoryId
                && (int) $row->player_id === (int) $nomination->player_id), 'Invitation identity no longer matches the nomination.');
            $categoryIds = CategoryEvent::where('event_id', $eventId)->pluck('id');
            $entries = CategoryEventRegistration::withTrashed()->whereIn('category_event_id', $categoryIds)
                ->whereHas('registration.players', fn ($query) => $query->where('players.id', $nomination->player_id))
                ->orderBy('id')->lockForUpdate()->get();
            $this->ensure($entries->isNotEmpty() && $entries->every(fn ($row) => $row->withdrawn_at
                && in_array($row->status, ['withdrawn', 'withdrawn_pending_refund', 'withdrawn_refunded'], true)),
                'Withdraw all event participation before archiving this nomination.');
            $items = RegistrationOrderItems::whereIn('category_event_id', $categoryIds)->where('player_id', $nomination->player_id)
                ->orderBy('id')->lockForUpdate()->get();
            $orders = RegistrationOrder::whereIn('id', $items->pluck('order_id'))->orderBy('id')->lockForUpdate()->get();
            $this->ensure($invitations->every(function ($invitation) use ($items, $orders, $entries) {
                if (! $invitation->order_id && ! $invitation->registration_id) return true;
                $order = $orders->firstWhere('id', $invitation->order_id);
                return $order && $entries->contains('registration_id', $invitation->registration_id)
                    && $items->contains(fn ($item) => (int) $item->order_id === (int) $order->id
                        && (int) $item->registration_id === (int) $invitation->registration_id
                        && (int) $item->category_event_id === (int) $invitation->category_event_id
                        && (int) $item->player_id === (int) $invitation->player_id && (int) $item->user_id === (int) $order->user_id);
            }), 'An invitation refers to another or inconsistent checkout.');
            foreach ($orders as $order) {
                $this->ensure(! TrialPaymentProof::where('order_id', $order->id)->whereIn('status', ['pending', 'verified'])->exists(),
                    'An EFT proof requires separate reconciliation before archiving.');
                $paid = $order->pay_status && $order->status === 'completed';
                $closed = $order->status === 'cancelled' && ! $order->pay_status && ! $order->payfast_paid && ! $order->wallet_debited
                    && ! $order->payfast_handed_off_at && ! $order->payfast_pf_payment_id && ! $order->wallet_transaction_id && (float) $order->wallet_reserved === 0.0;
                $this->ensure($paid || $closed, 'An active or unresolved event checkout prevents nomination archiving.');
            }
            $this->ensure(! $invitations->contains(fn ($row) => in_array($row->status, ['queued', 'sending', 'accepted_pending_payment', 'paid_confirmed'], true)),
                'Resolve current invitations and paid participation before archiving.');
            $current = $nomination->archived_at !== null;
            $desired = $action === 'archive';
            $review = ['actor_id' => $actor->id, 'event_id' => $eventId, 'category_event_id' => $categoryId, 'nomination_id' => $nominationId,
                'player_id' => $nomination->player_id, 'player_name' => $nomination->display_name, 'action' => $action, 'reason' => trim($reason),
                'nomination' => $nomination->getAttributes(), 'entries' => $entries->map->getAttributes()->all(),
                'invitations' => $invitations->map->getAttributes()->all(), 'orders' => $orders->map->getAttributes()->all(),
                'items' => $items->map->getAttributes()->all()];
            $fingerprint = hash('sha256', json_encode($review, JSON_THROW_ON_ERROR));
            $safeReview = array_diff_key($review, array_flip(['nomination', 'entries', 'invitations', 'orders', 'items']))
                + ['withdrawn_entry_ids' => $entries->pluck('id')->all(), 'retained_order_ids' => $orders->pluck('id')->all(),
                    'retained_invitation_ids' => $invitations->pluck('id')->all(), 'currently_archived' => $current];
            if ($confirmation === null) {
                return ['applied' => false, 'already_in_state' => $current === $desired, 'preview' => $safeReview,
                    'confirmation' => Crypt::encryptString(json_encode(['fingerprint' => $fingerprint, 'actor_id' => $actor->id,
                        'event_id' => $eventId, 'nomination_id' => $nominationId, 'action' => $action, 'reason' => trim($reason),
                        'expires' => now()->addMinutes(15)->timestamp], JSON_THROW_ON_ERROR))];
            }
            try { $approved = json_decode(Crypt::decryptString($confirmation), true, flags: JSON_THROW_ON_ERROR); }
            catch (\Throwable) { throw ValidationException::withMessages(['nomination' => 'Invalid archive confirmation.']); }
            $this->ensure(($approved['actor_id'] ?? 0) === $actor->id && ($approved['event_id'] ?? 0) === $eventId
                && ($approved['nomination_id'] ?? 0) === $nominationId && ($approved['action'] ?? '') === $action
                && ($approved['reason'] ?? '') === trim($reason) && ($approved['expires'] ?? 0) >= now()->timestamp, 'Archive confirmation changed or expired.');
            if ($current === $desired) {
                $this->ensure(\Spatie\Activitylog\Models\Activity::where('subject_type', $nomination->getMorphClass())->where('subject_id', $nominationId)
                    ->where('description', 'Interpro nomination visibility archived or restored')->where('properties->fingerprint', $approved['fingerprint'])->exists(),
                    'This confirmation was not used for the current archive state.');
                return ['applied' => true, 'already_in_state' => true, 'nomination_id' => $nominationId];
            }
            $this->ensure(hash_equals($fingerprint, $approved['fingerprint'] ?? ''), 'Nomination or participation changed. Review again.');
            $before = $nomination->only(['archived_at', 'archived_by', 'archive_reason']);
            $nomination->forceFill(['archived_at' => $desired ? now() : null, 'archived_by' => $desired ? $actor->id : null,
                'archive_reason' => $desired ? trim($reason) : null])->save();
            activity('interprovincial-trials')->performedOn($nomination)->causedBy($actor)->withProperties([
                'event_id' => $eventId, 'category_event_id' => $categoryId, 'action' => $action, 'reason' => trim($reason),
                'fingerprint' => $fingerprint, 'before' => $before, 'after' => $nomination->only(['archived_at', 'archived_by', 'archive_reason']),
            ])->log('Interpro nomination visibility archived or restored');
            return ['applied' => true, 'already_in_state' => false, 'nomination_id' => $nominationId, 'archived' => $desired];
        }, 3);
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) throw ValidationException::withMessages(['nomination' => $message]);
    }
}
