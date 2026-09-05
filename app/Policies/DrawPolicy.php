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
