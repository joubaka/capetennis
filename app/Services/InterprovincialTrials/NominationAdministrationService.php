<?php

namespace App\Services\InterprovincialTrials;

use App\Domain\Entries\Services\EntryService;
use App\Models\{CategoryEvent, Event, EventNomination, InterprovincialTrialInvitation, InterprovincialTrialInvitationBatch, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class NominationAdministrationService
{
    public function act(Event $event, CategoryEvent $category, EventNomination $nomination, User $actor, string $action): void
    {
        abort_unless(in_array($action, ['decline', 'paid_privately'], true), 422);
        app(TrialProgrammeService::class)->authorize($event, $actor);
        abort_unless($actor->hasRole('super-user') || ($actor->hasRole('admin') && $actor->is_event_admin($event->id)), 403);
        DB::transaction(function () use ($event, $category, $nomination, $actor, $action): void {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $category = CategoryEvent::whereKey($category->id)->lockForUpdate()->firstOrFail();
            $nomination = EventNomination::whereKey($nomination->id)->lockForUpdate()->firstOrFail();
            abort_unless($event->isInterprovincialTrials() && (int) $category->event_id === (int) $event->id
                && (int) $nomination->event_id === (int) $event->id
                && (int) $nomination->category_event_id === (int) $category->id, 404);
            $invitations = InterprovincialTrialInvitation::where('nomination_id', $nomination->id)->orderBy('id')->lockForUpdate()->get();
            abort_if($invitations->contains(fn ($item) => (int) $item->event_id !== (int) $event->id
                || (int) $item->category_event_id !== (int) $category->id
                || (int) ($item->player_id ?? 0) !== (int) ($nomination->player_id ?? 0)), 404);
            $invitation = $invitations->last(fn ($item) => $item->status !== 'prepared') ?? $invitations->last();
            if ($action === 'decline' && $invitation?->status === 'admin_declined') return;
            if ($action === 'paid_privately' && $invitation?->status === 'paid_confirmed' && !$invitation->order_id) return;
            if ($invitations->contains(fn ($item) => $item->order_id || $item->registration_id || in_array($item->status, ['queued', 'sending', 'paid_confirmed', 'withdrawn'], true))
                || ($nomination->player_id && $category->categoryEventRegistrations()->whereHas('registration.players', fn ($query) => $query->where('players.id', $nomination->player_id))->exists())) {
                throw ValidationException::withMessages(['nomination' => 'This nominee has registration or payment history. Use the existing checkout or Entries to cancel or withdraw first.']);
            }
            if ($action === 'paid_privately' && (!$nomination->player_id || $invitation?->status === 'admin_declined')) {
                throw ValidationException::withMessages(['nomination' => 'A current player profile and an active nomination are required.']);
            }
            if (!$invitation) {
                $batch = InterprovincialTrialInvitationBatch::create(['event_id' => $event->id, 'status' => 'draft', 'snapshot_hash' => hash('sha256', 'admin:'.$nomination->id), 'created_by_user_id' => $actor->id]);
                $invitation = InterprovincialTrialInvitation::create(['batch_id' => $batch->id, 'event_id' => $event->id, 'category_event_id' => $category->id, 'nomination_id' => $nomination->id, 'player_id' => $nomination->player_id, 'status' => 'prepared']);
            }
            if ($action === 'paid_privately') {
                try {
                    $entry = app(EntryService::class)->addPlayerAsAdmin($category, (int) $nomination->player_id, $actor, 'paid_privately');
                } catch (\RuntimeException $exception) {
                    throw ValidationException::withMessages(['nomination' => $exception->getMessage()]);
                }
                $invitation->update(['status' => 'paid_confirmed', 'registration_id' => $entry->registration_id, 'paid_at' => now()]);
            } else {
                $invitation->update(['status' => 'admin_declined']);
            }
            activity('interprovincial-trials')->performedOn($invitation)->causedBy($actor)
                ->withProperties(['nomination_id' => $nomination->id, 'action' => $action, 'reconciled_payment' => false])
                ->log($action === 'decline' ? 'Nomination declined by event administrator' : 'Nominee entered with private collection recorded');
        });
    }
}
