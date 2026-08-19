<?php

namespace App\Policies;

use App\Models\Draw;
use App\Models\Event;
use App\Models\EventType;
use App\Models\TeamTie;
use App\Models\User;

/**
 * TeamDrawPolicy
 *
 * Governs access to team-event draw lifecycle operations.
 *
 * Role hierarchy (super-user bypassed via Gate::before in AuthServiceProvider):
 *   - admin: permitted when the user is an event admin for the relevant event.
 *   - convenor: denied (team draws are admin-managed).
 *   - all others: denied.
 *
 * Scope checks:
 *   - The target event must exist and be a team event (EventType::TEAM).
 *   - For draw-scoped abilities the draw must belong to a team event.
 *   - For tie-scoped abilities the tie must belong to a draw that belongs to a team event.
 */
class TeamDrawPolicy
{
    // ─────────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Determine whether the user is an admin for the given event.
     */
    private function isEventAdmin(User $user, int $eventId): bool
    {
        return $user->hasRole('admin') && $user->is_event_admin($eventId);
    }

    /**
     * Determine whether the event is a team event.
     */
    private function isTeamEvent(Event $event): bool
    {
        return (int) $event->eventTypeModel?->type === EventType::TEAM;
    }

    /**
     * Resolve the parent event from a draw.
     * Returns null if the draw has no event or the event cannot be loaded.
     */
    private function resolveEventFromDraw(Draw $draw): ?Event
    {
        return $draw->relationLoaded('event') ? $draw->event : $draw->event()->first();
    }

    /**
     * Resolve the parent event from a tie (via its draw).
     */
    private function resolveEventFromTie(TeamTie $tie): ?Event
    {
        $draw = $tie->relationLoaded('draw') ? $tie->draw : $tie->draw()->first();
        return $draw ? $this->resolveEventFromDraw($draw) : null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Abilities — Event scope
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * List formats available for a team event.
     */
    public function viewFormats(User $user, Event $event): bool
    {
        if (!$this->isTeamEvent($event)) {
            return false;
        }

        return $this->isEventAdmin($user, $event->id);
    }

    /**
     * Create (store) a new format for a team event.
     */
    public function createFormat(User $user, Event $event): bool
    {
        if (!$this->isTeamEvent($event)) {
            return false;
        }

        return $this->isEventAdmin($user, $event->id);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Abilities — Draw scope
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Attach a format to a draw, sync teams, or update draw-level settings.
     */
    public function updateTeamDraw(User $user, Draw $draw): bool
    {
        if ($draw->locked || $draw->published) {
            return false;
        }

        $event = $this->resolveEventFromDraw($draw);

        if (!$event || !$this->isTeamEvent($event)) {
            return false;
        }

        return $this->isEventAdmin($user, $event->id);
    }

    /**
     * Generate ties for a draw.
     */
    public function generateTies(User $user, Draw $draw): bool
    {
        if ($draw->locked || $draw->published) {
            return false;
        }

        $event = $this->resolveEventFromDraw($draw);

        if (!$event || !$this->isTeamEvent($event)) {
            return false;
        }

        return $this->isEventAdmin($user, $event->id);
    }

    /**
     * Generate rubbers for all ties in a draw.
     */
    public function generateRubbers(User $user, Draw $draw): bool
    {
        if ($draw->locked || $draw->published) {
            return false;
        }

        $event = $this->resolveEventFromDraw($draw);

        if (!$event || !$this->isTeamEvent($event)) {
            return false;
        }

        return $this->isEventAdmin($user, $event->id);
    }

    /**
     * Regenerate a draw (purge and rebuild ties/rubbers).
     */
    public function regenerate(User $user, Draw $draw): bool
    {
        if ($draw->locked || $draw->published) {
            return false;
        }

        $event = $this->resolveEventFromDraw($draw);

        if (!$event || !$this->isTeamEvent($event)) {
            return false;
        }

        return $this->isEventAdmin($user, $event->id);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Abilities — Tie scope
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Generate rubbers for a single tie.
     */
    public function generateRubbersForTie(User $user, TeamTie $tie): bool
    {
        $draw = $tie->relationLoaded('draw') ? $tie->draw : $tie->draw()->first();
        if (!$draw || $draw->locked || $draw->published) {
            return false;
        }

        $event = $this->resolveEventFromTie($tie);

        if (!$event || !$this->isTeamEvent($event)) {
            return false;
        }

        return $this->isEventAdmin($user, $event->id);
    }

    /**
     * Validate a tie (transition to 'validated' status).
     */
    public function validateTie(User $user, TeamTie $tie): bool
    {
        $event = $this->resolveEventFromTie($tie);

        if (!$event || !$this->isTeamEvent($event)) {
            return false;
        }

        return $this->isEventAdmin($user, $event->id);
    }

    /**
     * Publish a tie (transition to 'published' status).
     */
    public function publishTie(User $user, TeamTie $tie): bool
    {
        $event = $this->resolveEventFromTie($tie);

        if (!$event || !$this->isTeamEvent($event)) {
            return false;
        }

        return $this->isEventAdmin($user, $event->id);
    }
}
