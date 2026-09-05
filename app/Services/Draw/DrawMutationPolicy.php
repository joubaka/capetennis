<?php

namespace App\Services\Draw;

use App\Models\Draw;

/**
 * DrawMutationPolicy — single source of truth for draw lock/state rules.
 *
 * All backend controllers and policies should call these helpers instead of
 * checking $draw->locked / $draw->published directly, so that logic stays
 * centralised and consistent.
 *
 * Frontend mirrors this via window.RR_DRAW_LOCKED / window.RR_DRAW_PUBLISHED
 * injected in the Blade view.
 *
 * Canonical lock rules:
 *
 *   DRAFT (locked=0, published=0)
 *     - All mutations allowed for authorised users.
 *
 *   LOCKED (locked=1, published=0)
 *     - Group assignments BLOCKED.
 *     - Fixture regeneration BLOCKED.
 *     - Score save/delete BLOCKED.
 *     - Schedule mutations BLOCKED.
 *     - Structural settings (boxes, format) BLOCKED.
 *     - Notes editing ALLOWED (non-destructive).
 *     - Lock toggle ALLOWED (for admins).
 *     - Publication ALLOWED.
 *
 *   PUBLISHED, BEFORE FIRST RESULT (locked=0, published=1)
 *     - Score save BLOCKED (public-facing draw must not silently change).
 *     - Score delete BLOCKED.
 *     - Fixture regeneration BLOCKED.
 *     - Group assignments BLOCKED.
 *     - Match format and rules ALLOWED until the first result is recorded.
 *     - Other structural settings BLOCKED.
 *     - Unpublish ALLOWED (for admins).
 *
 *   COMPLETED (locked=1, published=1) — both flags set
 *     - All mutations BLOCKED except lock toggle for super-user.
 */
class DrawMutationPolicy
{
    public function __construct(private readonly Draw $draw) {}

    /** Can group assignments be saved or rearranged? */
    public function canEditAssignments(): bool
    {
        return ! $this->draw->locked && ! $this->draw->published;
    }

    /** Can round-robin fixtures be regenerated (destructive)? */
    public function canGenerateFixtures(): bool
    {
        return ! $this->draw->locked && ! $this->draw->published;
    }

    /** Can bracket playoff be generated/regenerated? */
    public function canGenerateBrackets(): bool
    {
        return ! $this->draw->locked && ! $this->draw->published;
    }

    /** Can a score be saved on a fixture? */
    public function canEditScores(): bool
    {
        return ! $this->draw->locked && ! $this->draw->published;
    }

    /** Can a score be deleted from a fixture? */
    public function canDeleteScores(): bool
    {
        return ! $this->draw->locked && ! $this->draw->published;
    }

    /** Can the order-of-play / schedule be modified? */
    public function canModifySchedule(): bool
    {
        return ! $this->draw->locked;
    }

    /** Can match format and competition-rule settings be changed? */
    public function canEditSettings(): bool
    {
        return ! $this->draw->locked && ! $this->draw->event?->hasRecordedResults();
    }

    /** Can notes/rules be edited? Frozen once play has produced a result. */
    public function canEditNotes(): bool
    {
        return ! $this->draw->locked && ! $this->draw->event?->hasRecordedResults();
    }

    /** Can the draw be published or unpublished? */
    public function canPublish(): bool
    {
        return true; // Role-level check is handled by the policy/controller.
    }

    /** Can the lock state be toggled? */
    public function canToggleLock(): bool
    {
        return true; // Role-level check is handled by the policy/controller.
    }

    /**
     * Return a JSON-serialisable summary of all allowed actions.
     * Used by the blade to seed the frontend lock state object.
     */
    public function toArray(): array
    {
        return [
            'locked'              => (bool) $this->draw->locked,
            'published'           => (bool) $this->draw->published,
            'canEditAssignments'  => $this->canEditAssignments(),
            'canGenerateFixtures' => $this->canGenerateFixtures(),
            'canGenerateBrackets' => $this->canGenerateBrackets(),
            'canEditScores'       => $this->canEditScores(),
            'canDeleteScores'     => $this->canDeleteScores(),
            'canModifySchedule'   => $this->canModifySchedule(),
            'canEditSettings'     => $this->canEditSettings(),
            'canEditNotes'        => $this->canEditNotes(),
        ];
    }

    /** Static factory for fluent use: DrawMutationPolicy::for($draw)->canEditScores() */
    public static function for(Draw $draw): self
    {
        return new self($draw);
    }
}
