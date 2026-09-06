<?php

namespace App\Policies;

use App\Models\Draw;
use App\Models\User;

class DrawPolicy
{
    private function canManage(User $user, Draw $draw, bool $allowConvenor = true): bool
    {
        // Event assignments are the authoritative scope. Requiring a second,
        // global role here made the venue-scoring gate and the write gate
        // disagree for legitimately assigned event administrators/convenors.
        if ($user->is_event_admin($draw->event_id)) {
            return true;
        }

        // A dedicated scorer is stored in the event assignment table so its
        // access can be time- and event-scoped. That assignment must not make
        // the account a draw manager.
        if ($user->is_event_score_keeper($draw->event_id)) {
            return false;
        }

        return $allowConvenor
            && $user->is_convenor($draw->event_id);
    }

    /**
     * View the draw admin hub.
     */
    public function view(User $user, Draw $draw): bool
    {
        return $this->canManage($user, $draw);
    }

    /**
     * Update draw settings / notes / playoff config.
     */
    public function update(User $user, Draw $draw): bool
    {
        if ($draw->locked || $draw->published) {
            return false;
        }

        return $this->canManage($user, $draw);
    }

    /**
     * Save a score to a fixture.
     */
    public function saveScore(User $user, Draw $draw): bool
    {
        if ($draw->locked) {
            return false;
        }

        return $this->canManage($user, $draw)
            || ($user->hasRole('score-keeper') && $user->is_convenor($draw->event_id));
    }

    /**
     * Delete a score from a fixture.
     */
    public function deleteScore(User $user, Draw $draw): bool
    {
        if ($draw->locked) {
            return false;
        }

        return $this->canManage($user, $draw)
            || ($user->hasRole('score-keeper') && $user->is_convenor($draw->event_id));
    }

    /**
     * Regenerate round-robin fixtures (destructive).
     */
    public function generateFixtures(User $user, Draw $draw): bool
    {
        if ($draw->locked || $draw->published) {
            return false;
        }

        return $this->canManage($user, $draw, false);
    }

    /**
     * Generate / regenerate playoff brackets (destructive).
     */
    public function generateBrackets(User $user, Draw $draw): bool
    {
        if ($draw->locked || $draw->published) {
            return false;
        }

        return $this->canManage($user, $draw);
    }

    /**
     * Progress a live round-robin draw into its configured playoffs.
     *
     * Unlike bracket regeneration, this is an idempotent tournament-day
     * operation and may run while the draw is published. It must never run
     * against a locked draw or be available to a score-keeper-only account.
     */
    public function progress(User $user, Draw $draw): bool
    {
        return ! $draw->locked && $this->canManage($user, $draw);
    }

    /**
     * Edit match format and tournament rules until the first result exists.
     * Publication is a visibility boundary, not the start-of-play boundary.
     */
    public function editCompetitionRules(User $user, Draw $draw): bool
    {
        if ($draw->locked || $draw->event?->hasRecordedResults()) {
            return false;
        }

        return $this->canManage($user, $draw);
    }

    /**
     * Prepare a published or unpublished result-free draw for regeneration
     * after a withdrawal. This destructive action remains admin-only.
     */
    public function prepareWithdrawalRedraw(User $user, Draw $draw): bool
    {
        return ! $draw->locked && $this->canManage($user, $draw, false);
    }

    /**
     * Modify group assignments (drag/drop, save-groups).
     */
    public function modifyGroups(User $user, Draw $draw): bool
    {
        if ($draw->locked || $draw->published) {
            return false;
        }

        return $this->canManage($user, $draw);
    }

    /**
     * Modify the order-of-play schedule.
     */
    public function modifySchedule(User $user, Draw $draw): bool
    {
        if ($draw->locked) {
            return false;
        }

        return $this->canManage($user, $draw);
    }

    /**
     * Publish or unpublish a draw.
     */
    public function publish(User $user, Draw $draw): bool
    {
        return $this->canManage($user, $draw, false);
    }

    /**
     * Toggle the locked state of a draw.
     */
    public function lockToggle(User $user, Draw $draw): bool
    {
        return $this->canManage($user, $draw, false);
    }

    /**
     * Permanently delete a draft draw and its generated graph.
     */
    public function delete(User $user, Draw $draw): bool
    {
        if ($draw->locked || $draw->published) {
            return false;
        }

        return $this->canManage($user, $draw, false);
    }

    /**
     * Edit draw notes/rules (allowed even when locked — non-destructive).
     */
    public function editNotes(User $user, Draw $draw): bool
    {
        return $this->canManage($user, $draw);
    }
}
