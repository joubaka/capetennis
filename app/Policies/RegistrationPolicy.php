<?php

namespace App\Policies;

use App\Models\CategoryEventRegistration;
use App\Models\User;

class RegistrationPolicy
{
    /**
     * Determine if the user can view any registrations.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['super-user', 'admin', 'convenor']);
    }

    /**
     * Determine if the user can view a specific registration.
     */
    public function view(User $user, CategoryEventRegistration $registration): bool
    {
        return $user->id === $registration->user_id
            || $user->hasAnyRole(['super-user', 'admin', 'convenor']);
    }

    /**
     * Determine if the user can withdraw this registration.
     */
    public function withdraw(User $user, CategoryEventRegistration $registration): bool
    {
        if ($registration->status === 'withdrawn') {
            return false;
        }

        return $user->id === $registration->user_id
            || $user->hasAnyRole(['super-user', 'admin']);
    }

    /**
     * Determine if the user can request a refund for this registration.
     */
    public function refund(User $user, CategoryEventRegistration $registration): bool
    {
        if ($registration->status !== 'withdrawn') {
            return false;
        }

        if (!$registration->is_paid) {
            return false;
        }

        return $user->id === $registration->user_id
            || $user->hasAnyRole(['super-user', 'admin']);
    }

    /**
     * Determine if the user can update this registration (admin only).
     */
    public function update(User $user, CategoryEventRegistration $registration): bool
    {
        return $user->hasAnyRole(['super-user', 'admin', 'convenor']);
    }

    /**
     * Determine if the user can delete this registration.
     */
    public function delete(User $user, CategoryEventRegistration $registration): bool
    {
        return $user->hasAnyRole(['super-user', 'admin']);
    }
}
