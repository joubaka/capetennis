<?php

namespace App\Services;

use App\Models\{CategoryEvent, CategoryEventRegistration, Event, EventNomination, InterprovincialTrialInvitation, RegistrationOrderItems, User};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Read-only follow-up eligibility and history-preserving administrative actions. */
class NomineeFollowUpService
{
    public function canManage(Event $event, User $actor): bool
    {
        return $actor->hasRole('super-user') || ($actor->hasRole('admin') && $actor->is_event_admin($event->id));
    }

    public function query(Event $event): Builder
    {
        return EventNomination::query()
        ->where('event_id', $event->id)
        ->unarchived()
        ->whereHas('categoryEvent', fn ($query) => $query
          ->where('event_id', $event->id)->where('nominations_published', true))
        ->whereNotExists(function ($query) {
          $query->selectRaw('1')->from('interprovincial_trial_invitations as followup_responses')
            ->whereColumn('followup_responses.nomination_id', 'event_nominations.id')
            ->whereColumn('followup_responses.event_id', 'event_nominations.event_id')
            ->whereColumn('followup_responses.category_event_id', 'event_nominations.category_event_id')
            ->whereRaw('(followup_responses.player_id = event_nominations.player_id OR (followup_responses.player_id IS NULL AND event_nominations.player_id IS NULL))')
            ->whereRaw("followup_responses.id = (SELECT COALESCE(MAX(CASE WHEN current_response.status != 'prepared' THEN current_response.id END), MAX(current_response.id)) FROM interprovincial_trial_invitations current_response WHERE current_response.nomination_id = event_nominations.id AND current_response.event_id = event_nominations.event_id AND current_response.category_event_id = event_nominations.category_event_id AND (current_response.player_id = event_nominations.player_id OR (current_response.player_id IS NULL AND event_nominations.player_id IS NULL)))")
            ->whereIn('followup_responses.status', ['accepted_pending_payment', 'paid_confirmed', 'declined', 'admin_declined', 'withdrawn']);
        })
        ->whereNotExists(function ($query) {
          $query->selectRaw('1')->from('category_event_registrations as followup_entries')
            ->join('player_registrations as followup_players', 'followup_players.registration_id', '=', 'followup_entries.registration_id')
            ->whereColumn('followup_entries.category_event_id', 'event_nominations.category_event_id')
            ->whereColumn('followup_players.player_id', 'event_nominations.player_id')
            ->where(fn ($entries) => $entries->whereNull('followup_entries.deleted_at')
              ->orWhere('followup_entries.payment_status_id', 1)
              ->orWhereNotNull('followup_entries.withdrawn_at')
              ->orWhere('followup_entries.status', 'like', 'withdrawn%'));
        });
    }

    public function revision(EventNomination $nomination): string
    {
        return hash('sha256', json_encode($nomination->getAttributes(), JSON_THROW_ON_ERROR));
    }

    public function current(Event $event, User $actor, int $nominationId): EventNomination
    {
        abort_unless($this->canManage($event, $actor), 403);
        EventNomination::where('event_id', $event->id)->findOrFail($nominationId);
        $nomination = $this->query($event)->with('player')->find($nominationId);
        if (! $nomination) throw ValidationException::withMessages(['nomination' => 'The nomination or registration response changed. Reload the entries and review it again.']);
        return $nomination;
    }

    public function act(Event $event, CategoryEvent $source, EventNomination $nomination, User $actor, string $action, string $revision, ?int $targetId = null): array
    {
        abort_unless($this->canManage($event, $actor), 403);
        abort_unless($event->isInterprovincialTrials(), 422, 'Manage this nomination through its event nomination workflow.');
        abort_unless(in_array($action, ['remove', 'move'], true), 422);
        return DB::transaction(function () use ($event, $source, $nomination, $actor, $action, $revision, $targetId) {
            $event = Event::with('eventTypeModel')->lockForUpdate()->findOrFail($event->id);
            $categories = CategoryEvent::whereIn('id', array_filter([$source->id, $targetId]))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $source = $categories->get($source->id);
            $locked = EventNomination::lockForUpdate()->findOrFail($nomination->id);
            abort_unless($source && (int) $source->event_id === (int) $event->id
                && (int) $locked->event_id === (int) $event->id && (int) $locked->category_event_id === (int) $source->id, 404);
            if (! hash_equals($this->revision($locked), $revision)) {
                throw ValidationException::withMessages(['nomination' => 'The nomination changed. Reload the entries and review it again.']);
            }
            $this->current($event, $actor, $locked->id);
            if ($source->isLocked()) {
                throw ValidationException::withMessages(['nomination' => 'Unlock the category before changing this nomination.']);
            }
            $invitations = InterprovincialTrialInvitation::where('nomination_id', $locked->id)->orderBy('id')->lockForUpdate()->get();
            if ($invitations->contains(fn ($row) => (int) $row->event_id !== (int) $event->id
                || (int) $row->category_event_id !== (int) $source->id || (int) ($row->player_id ?? 0) !== (int) ($locked->player_id ?? 0))) abort(422, 'Invitation identity needs review.');
            if ($invitations->contains(fn ($row) => $row->order_id || $row->registration_id || $row->accepted_at || $row->paid_at || $row->withdrawn_at
                || ! in_array($row->status, ['prepared', 'sent', 'failed', 'open_registration', 'cancelled'], true))) {
                throw ValidationException::withMessages(['nomination' => 'This nominee has an invitation response or active delivery. Resolve it before changing the nomination.']);
            }
            $categoryIds = $event->categoryEvents()->pluck('id');
            $hasEntries = $locked->player_id && CategoryEventRegistration::withTrashed()->whereIn('category_event_id', $categoryIds)
                ->whereHas('registration.players', fn ($q) => $q->where('players.id', $locked->player_id))->lockForUpdate()->exists();
            $hasItems = $locked->player_id && RegistrationOrderItems::whereIn('category_event_id', $categoryIds)
                ->where('player_id', $locked->player_id)->lockForUpdate()->exists();
            if ($hasEntries || $hasItems) {
                throw ValidationException::withMessages(['nomination' => 'This player has registration or checkout history. Use the existing registration workflow instead.']);
            }
            if ($invitations->isNotEmpty() && ! $event->isInterprovincialTrials()) {
                throw ValidationException::withMessages(['nomination' => 'This nomination has invitation history. Review it through its nomination workflow.']);
            }
            $before = $locked->getAttributes();
            $target = null;
            if ($action === 'move') {
                $target = $categories->get($targetId);
                abort_unless($target && (int) $target->event_id === (int) $event->id && (int) $target->id !== (int) $source->id, 404);
                if (! $target->nominations_published || $target->isLocked()) {
                    throw ValidationException::withMessages(['category' => 'Choose a published, unlocked category.']);
                }
                $duplicate = EventNomination::where('category_event_id', $target->id)->where(function ($q) use ($locked) {
                    if ($locked->player_id) $q->where('player_id', $locked->player_id);
                    else $q->whereNull('player_id')->where('nominee_name', $locked->nominee_name)->where('nominee_surname', $locked->nominee_surname);
                })->lockForUpdate()->exists();
                if ($duplicate) throw ValidationException::withMessages(['category' => 'This player already has a nomination in the destination category.']);
            }
            if ($invitations->isNotEmpty()) {
                // Old invitation links retain their original identity and fail closed
                // against the archived source. The destination needs its own invitation.
                $locked->forceFill(['archived_at' => now(), 'archived_by' => $actor->id,
                    'archive_reason' => $action === 'move' ? 'Unanswered nominee moved to category '.$target->id : 'Unanswered nomination removed by event administrator'])->save();
                if ($target) {
                    $moved = EventNomination::create(['event_id' => $event->id, 'category_event_id' => $target->id,
                        'player_id' => $locked->player_id, 'nominee_name' => $locked->nominee_name,
                        'nominee_surname' => $locked->nominee_surname, 'nominee_email' => $locked->nominee_email,
                        'profileless_key' => $locked->player_id ? null : hash('sha256', implode('|', [$event->id, $target->id, mb_strtolower($locked->nominee_name), mb_strtolower($locked->nominee_surname)]))]);
                }
            } elseif ($target) {
                $locked->forceFill(['category_event_id' => $target->id, 'roster_position' => null,
                    'profileless_key' => $locked->player_id ? null : hash('sha256', implode('|', [$event->id, $target->id, mb_strtolower($locked->nominee_name), mb_strtolower($locked->nominee_surname)]))])->save();
                $moved = $locked;
            } else {
                $locked->delete();
            }
            activity('nominee-follow-up')->performedOn($locked)->causedBy($actor)->withProperties([
                'event_id' => $event->id, 'action' => $action, 'from_category_id' => $source->id,
                'to_category_id' => $target?->id, 'destination_nomination_id' => $moved->id ?? null,
                'before' => $before, 'retained_invitation_ids' => $invitations->pluck('id')->all(),
            ])->log('Unanswered nomination '.$action);
            return ['success' => true, 'message' => $target ? 'Nomination moved. Existing invitation history was retained; no email was sent.' : 'Nomination removed. Existing invitation history was retained.',
                'nomination_id' => $locked->id, 'destination_nomination_id' => $moved->id ?? null];
        }, 3);
    }
}
