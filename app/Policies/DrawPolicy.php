<?php

namespace App\Policies;

use App\Models\Draw;
use App\Models\User;

class DrawPolicy
{
    /**
     * View the draw admin hub.
     */
    public function view(User $user, Draw $draw): bool
    {
        return $user->hasAnyRole(['super-user', 'admin', 'convenor']);
    }

    /**
     * Update draw settings / notes / playoff config.
     */
    public function update(User $user, Draw $draw): bool
    {
        if ($draw->locked) {
            return false;
        }

        return $user->hasAnyRole(['super-user', 'admin', 'convenor']);
    }

    /**
     * Save a score to a fixture.
     */
    public function saveScore(User $user, Draw $draw): bool
    {
        if ($draw->locked || $draw->published) {
            return false;
        }

        return $user->hasAnyRole(['super-user', 'admin', 'convenor']);
    }

    /**
     * Delete a score from a fixture.
     */
    public function deleteScore(User $user, Draw $draw): bool
    {
        if ($draw->locked || $draw->published) {
            return false;
        }

        return $user->hasAnyRole(['super-user', 'admin', 'convenor']);
    }

    /**
     * Regenerate round-robin fixtures (destructive).
     */
    public function generateFixtures(User $user, Draw $draw): bool
    {
        if ($draw->locked || $draw->published) {
            return false;
        }

        return $user->hasAnyRole(['super-user', 'admin']);
    }

    /**
     * Generate / regenerate playoff brackets (destructive).
     */
    public function generateBrackets(User $user, Draw $draw): bool
    {
        if ($draw->locked || $draw->published) {
            return false;
        }

        return $user->hasAnyRole(['super-user', 'admin', 'convenor']);
    }

    /**
     * Modify group assignments (drag/drop, save-groups).
     */
    public function modifyGroups(User $user, Draw $draw): bool
    {
        if ($draw->locked) {
            return false;
        }

        return $user->hasAnyRole(['super-user', 'admin', 'convenor']);
    }

    /**
     * Modify the order-of-play schedule.
     */
    public function modifySchedule(User $user, Draw $draw): bool
    {
        if ($draw->locked) {
            return false;
        }

        return $user->hasAnyRole(['super-user', 'admin', 'convenor']);
    }

    /**
     * Publish or unpublish a draw.
     */
    public function publish(User $user, Draw $draw): bool
    {
        return $user->hasAnyRole(['super-user', 'admin']);
    }

    /**
     * Toggle the locked state of a draw.
     */
    public function lockToggle(User $user, Draw $draw): bool
    {
        return $user->hasAnyRole(['super-user', 'admin']);
    }

    /**
     * Edit draw notes/rules (allowed even when locked — non-destructive).
     */
    public function editNotes(User $user, Draw $draw): bool
    {
        return $user->hasAnyRole(['super-user', 'admin', 'convenor']);
    }
}
