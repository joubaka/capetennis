<?php

declare(strict_types=1);

namespace App\Domain\Entries\Services;

use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\CategoryAdjustment;
use App\Models\EventNomination;
use App\Models\InterprovincialTrialInvitation;
use App\Models\InterprovincialTrialInvitationBatch;
use App\Models\MastersInvitation;
use App\Models\MastersInvitationBatch;
use App\Models\RegistrationOrder;
use App\Models\RegistrationOrderItems;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Preserve invitation entitlement when an already funded entry changes category. */
final class CategoryAdjustmentInvitationService
{
    /** A replay may acknowledge original money, but must never recreate its old entry. */
    public function originalPaidItemMatchesCurrentInvitation(
        RegistrationOrder $order,
        RegistrationOrderItems $item,
        MastersInvitation|InterprovincialTrialInvitation $invitation,
    ): bool {
        if (! $order->pay_status || (int) $item->order_id !== (int) $order->id
            || (int) $item->user_id !== (int) $order->user_id
            || (int) $invitation->order_id !== (int) $order->id
            || (int) $invitation->registration_id !== (int) $item->registration_id
            || (int) $invitation->player_id !== (int) $item->player_id
            || ! in_array($invitation->status, ['paid_confirmed', 'withdrawn'], true)) {
            return false;
        }
        $query = CategoryAdjustment::where('original_order_item_id', $item->id)->whereNotNull('applied_at')
            ->where('user_id', $order->user_id)->where('player_id', $item->player_id);
        $first = (clone $query)->oldest('id')->first();
        $last = (clone $query)->latest('id')->first();
        if (! $first || ! $last || (int) $first->source_category_id !== (int) $item->category_event_id
            || (int) $first->entry_id !== (int) $last->entry_id
            || (int) $last->target_category_id !== (int) $invitation->category_event_id
            || (int) $first->event_id !== (int) $invitation->event_id || (int) $last->event_id !== (int) $invitation->event_id) {
            return false;
        }
        $entry = CategoryEventRegistration::find($last->entry_id);
        return $entry && (int) $entry->registration_id === (int) $item->registration_id
            && (int) $entry->category_event_id === (int) $last->target_category_id
            && (int) $entry->user_id === (int) $order->user_id && (int) $entry->payment_status_id === 1
            && (int) $entry->categoryEvent?->event_id === (int) $invitation->event_id
            && (int) $item->category_event?->event_id === (int) $invitation->event_id
            && $entry->registration->players()->count() === 1
            && $entry->registration->players()->where('players.id', $item->player_id)->exists();
    }

    public function assertEligible(CategoryEventRegistration $entry, CategoryEvent $target, User $actor): void
    {
        $this->context($entry, (int) $entry->category_event_id, $target, $actor);
    }

    /** Called within the adjustment transaction, after EntryService transfers the entry. */
    public function apply(CategoryEventRegistration $entry, int $sourceCategoryId, CategoryEvent $target, User $actor): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Invitation category adjustments require the entry transaction.');
        }

        $context = $this->context($entry, $sourceCategoryId, $target, $actor);
        if ($context === null) {
            return;
        }
        [$source, $destination, $nomination] = $context;
        $orderId = $source->order_id;

        if (! $destination) {
            // Published Trials nominees need no email invitation. Match their canonical
            // open-registration workflow without publishing or sending anything.
            $batch = InterprovincialTrialInvitationBatch::create([
                'event_id' => $target->event_id,
                'status' => InterprovincialTrialInvitationBatch::DRAFT,
                'snapshot_hash' => hash('sha256', 'category-adjustment:'.$entry->id.':'.$nomination->id),
                'created_by_user_id' => $actor->id,
            ]);
            $destination = InterprovincialTrialInvitation::create([
                'batch_id' => $batch->id,
                'event_id' => $target->event_id,
                'category_event_id' => $target->id,
                'nomination_id' => $nomination->id,
                'player_id' => $nomination->player_id,
                'status' => 'open_registration',
            ]);
        }

        activity('category-move')->performedOn($source)->causedBy($actor)
            ->withProperties([
                'entry_id' => $entry->id, 'registration_id' => $entry->registration_id,
                'original_order_id' => $orderId, 'destination_invitation_id' => $destination->id,
                'from_category_id' => $sourceCategoryId, 'to_category_id' => $target->id,
            ])->log('Paid invitation participation transferred to an eligible category');

        // Trials enforces unique order/registration links. Retain historical identity
        // in the adjustment audit; do not change original order items or nominations.
        $source->forceFill([
            'registration_id' => null, 'order_id' => null,
            'status' => $source instanceof MastersInvitation ? MastersInvitation::SUPERSEDED : InterprovincialTrialInvitation::WITHDRAWN,
        ])->save();
        $destination->forceFill([
            'registration_id' => $entry->registration_id, 'order_id' => $orderId,
            'status' => 'paid_confirmed', 'accepted_at' => now(),
            'paid_at' => $source->paid_at ?? now(), 'withdrawn_at' => null,
        ])->save();
    }

    /** @return array{0: MastersInvitation|InterprovincialTrialInvitation, 1: MastersInvitation|InterprovincialTrialInvitation|null, 2: EventNomination|null}|null */
    private function context(CategoryEventRegistration $entry, int $sourceCategoryId, CategoryEvent $target, User $actor): ?array
    {
        $event = $target->event;
        if (! $event->isMasters() && ! $event->isInterprovincialTrials()) {
            return null;
        }
        abort_unless((int) $entry->user_id === (int) $actor->id || $actor->hasRole('super-user'), 403);
        $sourceCategory = CategoryEvent::findOrFail($sourceCategoryId);
        abort_unless((int) $sourceCategory->event_id === (int) $event->id, 404);
        $playerIds = $entry->registration->players()->pluck('players.id');
        if ($playerIds->count() !== 1) {
            $this->reject('Invitation category changes require one exact registered player.');
        }
        $playerId = (int) $playerIds->sole();

        if ($event->isMasters()) {
            $batch = MastersInvitationBatch::where('event_id', $event->id)
                ->where('public_list_published', true)->latest('id')->lockForUpdate()->first();
            if (! $batch || $batch->status !== 'sent') {
                $this->reject('The current Masters invitation list is not available.');
            }
            $invitations = MastersInvitation::where('event_id', $event->id)->where('player_id', $playerId)
                ->whereIn('category_event_id', [$sourceCategoryId, $target->id])
                ->orderBy('id')->lockForUpdate()->get();
            $source = $invitations->first(fn ($invitation) => (int) $invitation->registration_id === (int) $entry->registration_id
                && (int) $invitation->category_event_id === $sourceCategoryId && $invitation->status === MastersInvitation::PAID_CONFIRMED);
            $destination = $invitations->first(fn ($invitation) => (int) $invitation->batch_id === (int) $batch->id
                && (int) $invitation->category_event_id === (int) $target->id && $invitation->status === MastersInvitation::INVITED);
            if (! $source || ! $source->order_id || ! $destination || $destination->order_id || $destination->registration_id
                || (int) $source->batch?->event_id !== (int) $event->id) {
                $this->reject('A current available Masters invitation for the destination category is required. Reserve positions cannot be promoted by a category change.');
            }
            return [$source, $destination, null];
        }

        $nomination = EventNomination::where('event_id', $event->id)->where('category_event_id', $target->id)
            ->where('player_id', $playerId)->lockForUpdate()->first();
        if (! $target->nominations_published || ! $nomination) {
            $this->reject('Only an exact currently published nominee may move into this Trials category.');
        }
        $invitations = InterprovincialTrialInvitation::where('event_id', $event->id)->where('player_id', $playerId)
            ->whereIn('category_event_id', [$sourceCategoryId, $target->id])->orderBy('id')->lockForUpdate()->get();
        $source = $invitations->first(fn ($invitation) => (int) $invitation->registration_id === (int) $entry->registration_id
            && (int) $invitation->category_event_id === $sourceCategoryId && $invitation->status === InterprovincialTrialInvitation::PAID_CONFIRMED);
        $conflictingAttempt = $invitations->contains(fn ($invitation) => (int) $invitation->nomination_id === (int) $nomination->id
            && (int) $invitation->category_event_id === (int) $target->id
            && ($invitation->order_id || $invitation->registration_id
                || in_array($invitation->status, ['accepted_pending_payment', 'paid_confirmed'], true)));
        $latest = $invitations->filter(fn ($invitation) => (int) $invitation->nomination_id === (int) $nomination->id
            && (int) $invitation->category_event_id === (int) $target->id && $invitation->status !== 'prepared')->last();
        if (! $source || ! $source->order_id || (int) $source->batch?->event_id !== (int) $event->id
            || ! EventNomination::whereKey($source->nomination_id)->where('event_id', $event->id)
                ->where('category_event_id', $sourceCategoryId)->where('player_id', $playerId)->exists()) {
            $this->reject('The original paid Trials invitation must be reconciled before changing category.');
        }
        if ($conflictingAttempt || $latest?->status === 'admin_declined') {
            $this->reject('The destination nomination is unavailable or already has a checkout.');
        }
        $destination = $latest && in_array($latest->status, ['queued', 'sent', 'open_registration'], true) ? $latest : null;
        if ($destination && (int) $destination->batch?->event_id !== (int) $event->id) {
            $this->reject('The destination invitation does not belong to this event.');
        }
        return [$source, $destination, $nomination];
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['category' => $message]);
    }
}
