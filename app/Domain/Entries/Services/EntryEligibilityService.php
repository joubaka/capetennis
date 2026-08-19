<?php

declare(strict_types=1);

namespace App\Domain\Entries\Services;

use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\SiteSetting;
use App\Models\User;
use RuntimeException;
use App\Services\PlayerEligibilityService;

/**
 * Centralized eligibility and validation layer for entry operations.
 *
 * All guard methods either throw RuntimeException on failure
 * or return a result array for callers that prefer soft checks.
 */
class EntryEligibilityService
{
    public function __construct(private PlayerEligibilityService $disciplinaryEligibility) {}

    // -----------------------------------------------------------------------
    // ADMIN ADD-PLAYER GUARDS
    // -----------------------------------------------------------------------

    /**
     * Assert that an admin may add a player to a category.
     *
     * Checks:
     *  1. Category not locked
     *  2. No active, paid duplicate for this player in the category
     *
     * @throws RuntimeException
     */
    public function assertCanAddAdmin(CategoryEvent $categoryEvent, int $playerId): void
    {
        $this->disciplinaryEligibility->assertEligible($playerId, $categoryEvent->event_id);

        if ($categoryEvent->isLocked()) {
            throw new RuntimeException('Category is locked — cannot add player.');
        }

        $duplicate = $categoryEvent->categoryEventRegistrations()
            ->where('status', 'active')
            ->where('payment_status_id', 1)
            ->whereHas('registration.players', fn ($q) => $q->where('players.id', $playerId))
            ->exists();

        if ($duplicate) {
            throw new RuntimeException('Player is already registered in this category.');
        }
    }

    // -----------------------------------------------------------------------
    // FRONTEND REGISTRATION GUARDS
    // -----------------------------------------------------------------------

    /**
     * Assert that a player may register in a category via the normal
     * frontend checkout flow.
     *
     * Checks:
     *  1. Global registration switch
     *  2. Event registration deadline (if set)
     *  3. Category not locked
     *  4. No duplicate entry (active + paid)
     *
     * @throws RuntimeException
     */
    public function assertCanRegister(CategoryEvent $categoryEvent, int $playerId): void
    {
        $this->disciplinaryEligibility->assertEligible($playerId, $categoryEvent->event_id);

        if (SiteSetting::get('registration_open', '1') !== '1') {
            throw new RuntimeException(
                'Registrations are currently closed. Please check back later or contact support@capetennis.co.za.'
            );
        }

        if ($categoryEvent->isLocked()) {
            throw new RuntimeException('This category is no longer accepting entries.');
        }

        $duplicate = $categoryEvent->categoryEventRegistrations()
            ->where('payment_status_id', 1)
            ->whereHas('registration.players', fn ($q) => $q->where('players.id', $playerId))
            ->exists();

        if ($duplicate) {
            throw new RuntimeException('You are already registered in this category.');
        }
    }

    // -----------------------------------------------------------------------
    // WITHDRAWAL GUARDS
    // -----------------------------------------------------------------------

    /**
     * Assert that the global withdrawal switch is on.
     * Admins bypass this check.
     *
     * @throws RuntimeException
     */
    public function assertWithdrawalsOpen(bool $isAdmin = false): void
    {
        if ($isAdmin) {
            return;
        }

        if (SiteSetting::get('withdrawal_allowed', '1') !== '1') {
            throw new RuntimeException(
                'Withdrawals are currently disabled. Please contact support@capetennis.co.za for assistance.'
            );
        }
    }

    /**
     * Assert that a registration has not already been withdrawn.
     *
     * @throws RuntimeException
     */
    public function assertNotWithdrawn(CategoryEventRegistration $entry): void
    {
        if ($entry->status === 'withdrawn') {
            throw new RuntimeException('This registration is already withdrawn.');
        }
    }

    /**
     * Assert that a non-admin cannot withdraw from a locked draw.
     *
     * @throws RuntimeException
     */
    public function assertDrawNotLocked(CategoryEventRegistration $entry, bool $isAdmin): void
    {
        if (! $isAdmin && $entry->categoryEvent->isLocked()) {
            throw new RuntimeException(
                'Withdrawals are not allowed after the draw has been finalised. '
                . 'Please contact the event administrator.'
            );
        }
    }

    // -----------------------------------------------------------------------
    // TRANSFER GUARDS
    // -----------------------------------------------------------------------

    /**
     * Assert that an entry may be transferred to $targetCategory.
     *
     * Checks:
     *  1. Entry not withdrawn
     *  2. Target category not locked
     *  3. Target is in the same event
     *  4. Not moving to the same category
     *  5. No duplicate in target
     *  6. Withdrawal deadline not passed (for non-admins, since move = re-enter)
     *
     * @throws RuntimeException
     */
    public function assertCanTransfer(
        CategoryEventRegistration $entry,
        CategoryEvent $targetCategory,
        User $actingUser,
        bool $adminOverride = false
    ): void {
        $playerId = $entry->registration?->players()->value('players.id');
        if ($playerId) {
            $this->disciplinaryEligibility->assertEligible((int) $playerId, $targetCategory->event_id);
        }
        if ($entry->status === 'withdrawn') {
            throw new RuntimeException('Cannot move a withdrawn entry.');
        }

        if ($targetCategory->isLocked()) {
            throw new RuntimeException('Target category is locked.');
        }

        $currentCategory = $entry->categoryEvent;

        if ($currentCategory->event_id !== $targetCategory->event_id) {
            throw new RuntimeException('Cannot move to a category in a different event.');
        }

        if ((int) $entry->category_event_id === (int) $targetCategory->id) {
            throw new RuntimeException('Player is already in this category.');
        }

        $duplicate = CategoryEventRegistration::where('category_event_id', $targetCategory->id)
            ->where('registration_id', $entry->registration_id)
            ->exists();

        if ($duplicate) {
            throw new RuntimeException('Player is already registered in that category.');
        }

        if (! $adminOverride) {
            $event = $currentCategory->event;
            if ($event && now()->gt($event->withdrawalCloseAt())) {
                throw new RuntimeException('The deadline for category changes has passed.');
            }
        }
    }

    // -----------------------------------------------------------------------
    // DUPLICATE DETECTION (utility)
    // -----------------------------------------------------------------------

    /**
     * Return true if the player already has a paid, active entry in the category.
     */
    public function hasDuplicateEntry(CategoryEvent $categoryEvent, int $playerId): bool
    {
        return $categoryEvent->categoryEventRegistrations()
            ->where('payment_status_id', 1)
            ->whereHas('registration.players', fn ($q) => $q->where('players.id', $playerId))
            ->exists();
    }

    /**
     * Return true if the registration already exists in the category (any status).
     */
    public function registrationExistsInCategory(
        CategoryEvent $categoryEvent,
        int $registrationId
    ): bool {
        return $categoryEvent->categoryEventRegistrations()
            ->where('registration_id', $registrationId)
            ->exists();
    }
}
