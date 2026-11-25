<?php

namespace App\Services\InterprovincialTrials;

use App\Models\{CategoryEvent, CategoryEventRegistration, Event, EventNomination, InterprovincialTrialInvitation, InterprovincialTrialInvitationBatch, Player, RegistrationOrderItems, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Re-add an unpaid nomination without rewriting its earlier response or deletion evidence. */
class NominationReactivationService
{
    public function revision(Event $event, CategoryEvent $category, int $id): string
    {
        return hash('sha256', json_encode($this->state($event, $category, $id), JSON_THROW_ON_ERROR));
    }

    public function reactivate(Event $event, CategoryEvent $category, int $id, User $actor, string $revision, string $reason): void
    {
        abort_unless($actor->hasRole('super-user') || ($actor->hasRole('admin') && $actor->is_event_admin($event->id)), 403);
        abort_unless($event->isInterprovincialTrials(), 404);
        DB::transaction(function () use ($event, $category, $id, $actor, $revision, $reason): void {
            $event = Event::with('eventTypeModel')->lockForUpdate()->findOrFail($event->id);
            $category = CategoryEvent::lockForUpdate()->findOrFail($category->id);
            abort_unless((int) $category->event_id === (int) $event->id && $event->isInterprovincialTrials(), 404);
            EventNomination::whereKey($id)->lockForUpdate()->first();
            $invitations = InterprovincialTrialInvitation::where('nomination_id', $id)->orderBy('id')->lockForUpdate()->get();
            $state = $this->state($event, $category, $id);
            if ($state['audit_id']) {
                $this->ensure(\App\Support\Audit\AuditIntegrity::verify((array) DB::table('audit_events')->find($state['audit_id'])), 'The deletion audit integrity needs review before this nomination can be re-added.');
            }
            $this->ensure(hash_equals($this->revision($event, $category, $id), $revision), 'The nomination or response changed. Reload the list and review it again.');
            $this->ensure(! $category->isLocked(), 'Unlock the category before re-adding a nomination.');
            $snapshot = $state['nomination'];
            $playerId = $snapshot['player_id'] ?? null;
            $this->ensure((is_int($playerId) || (is_string($playerId) && ctype_digit($playerId))) && (int) $playerId > 0
                && Player::whereKey($playerId)->exists(), 'A current player profile is required to re-add this nomination.');
            $playerId = (int) $playerId;
            $this->ensure($invitations->every(fn ($row) => (int) $row->event_id === (int) $event->id
                && (int) $row->category_event_id === (int) $category->id && (int) $row->player_id === $playerId), 'Invitation identity needs review.');
            $this->ensure($invitations->every(fn ($row) => ! $row->order_id && ! $row->registration_id && ! $row->accepted_at && ! $row->paid_at && ! $row->withdrawn_at
                && in_array($row->status, ['prepared', 'sent', 'failed', 'declined', 'admin_declined', 'open_registration', 'cancelled'], true)), 'Use Entries to resolve registration, payment or withdrawal history before re-adding this player.');
            $current = $invitations->last(fn ($row) => $row->status !== 'prepared') ?? $invitations->last();
            $nomination = EventNomination::find($id);
            $this->ensure(! $nomination || $nomination->archived_at || in_array($current?->status, ['declined', 'admin_declined'], true), 'This nomination is already active.');
            $categoryIds = CategoryEvent::where('event_id', $event->id)->pluck('id');
            $this->ensure(! CategoryEventRegistration::withTrashed()->whereIn('category_event_id', $categoryIds)
                ->whereHas('registration.players', fn ($query) => $query->where('players.id', $playerId))->exists()
                && ! RegistrationOrderItems::whereIn('category_event_id', $categoryIds)->where('player_id', $playerId)->exists(),
                'This player has registration or checkout history. Manage it in Entries.');
            $this->ensure(! EventNomination::where('category_event_id', $category->id)->where('player_id', $playerId)->where('id', '!=', $id)->exists(), 'This player already has another nomination in this category.');
            if (! $nomination) {
                $nomination = new EventNomination();
                // Never replay arbitrary audit attributes, archived state or financial references.
                $nomination->forceFill(['id' => $id, 'event_id' => $event->id, 'category_event_id' => $category->id, 'player_id' => $playerId]);
            }
            $nomination->forceFill(['archived_at' => null, 'archived_by' => null, 'archive_reason' => null])->save();
            $batch = InterprovincialTrialInvitationBatch::create(['event_id' => $event->id, 'status' => 'draft',
                'snapshot_hash' => hash('sha256', 'reactivate:'.$id.':'.$revision), 'created_by_user_id' => $actor->id]);
            InterprovincialTrialInvitation::create(['batch_id' => $batch->id, 'event_id' => $event->id, 'category_event_id' => $category->id,
                'nomination_id' => $id, 'player_id' => $playerId, 'status' => 'open_registration']);
            activity('interprovincial-trials')->performedOn($nomination)->causedBy($actor)->withProperties([
                'event_id' => $event->id, 'category_event_id' => $category->id, 'reason' => trim($reason),
                'source_audit_id' => $state['audit_id'], 'before' => $snapshot, 'retained_invitation_ids' => $invitations->pluck('id')->all(),
            ])->log('Nomination reactivated by event administrator');
        }, 3);
    }

    private function state(Event $event, CategoryEvent $category, int $id): array
    {
        abort_unless((int) $category->event_id === (int) $event->id, 404);
        $nomination = EventNomination::find($id);
        $audit = null;
        if ($nomination) {
            abort_unless((int) $nomination->event_id === (int) $event->id && (int) $nomination->category_event_id === (int) $category->id, 404);
            $snapshot = $nomination->getAttributes();
        } else {
            $removed = app(NominationHistory::class)->removedQuery($event)->where('a.subject_id', (string) $id)->where('c.id', $category->id)->first();
            abort_unless($removed, 404);
            $audit = DB::table('audit_events')->find($removed->audit_id);
            $snapshot = json_decode($audit->before, true, flags: JSON_THROW_ON_ERROR);
        }
        return ['nomination' => $snapshot, 'audit_id' => $audit?->id, 'category' => CategoryEvent::findOrFail($category->id)->only(['id', 'event_id', 'nominations_published', 'locked_at']),
            'invitations' => InterprovincialTrialInvitation::where('nomination_id', $id)->orderBy('id')->get()->map->getAttributes()->all()];
    }

    private function ensure(bool $condition, string $message): void
    {
        if (! $condition) throw ValidationException::withMessages(['nomination' => $message]);
    }
}
