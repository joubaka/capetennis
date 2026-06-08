<?php

declare(strict_types=1);

namespace App\Domain\Doubles\Services;

use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Registration;
use App\Models\RegistrationPair;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * PairService
 *
 * Handles admin-only creation and deletion of doubles pairs.
 *
 * PHASE 2 SCOPE:
 *   - Admin creates pair from two existing players + a category event
 *   - Creates Registration + 2 × PlayerRegistration + CategoryEventRegistration + RegistrationPair
 *   - Validates: same-player, duplicate pair, player already paired in category
 *   - Remove pair: only when category is not locked and draw is not published
 *
 * OUT OF SCOPE:
 *   - Invitations, split payments, refunds, public registration
 */
class PairService
{
    // -----------------------------------------------------------------------
    // CREATE
    // -----------------------------------------------------------------------

    /**
     * Create an admin doubles pair.
     *
     * @throws RuntimeException on any validation failure
     */
    public function createPair(
        CategoryEvent $categoryEvent,
        int           $player1Id,
        int           $player2Id,
        User          $actingUser
    ): RegistrationPair {
        $this->assertCanCreate($categoryEvent, $player1Id, $player2Id);

        return DB::transaction(function () use ($categoryEvent, $player1Id, $player2Id, $actingUser) {
            // 1. Shared registration aggregate
            $registration = Registration::create([]);

            // 2. Attach both players
            PlayerRegistration::create([
                'registration_id' => $registration->id,
                'player_id'       => $player1Id,
            ]);
            PlayerRegistration::create([
                'registration_id' => $registration->id,
                'player_id'       => $player2Id,
            ]);

            // 3. Category event registration (admin-paid so draw can pick it up)
            $cer = CategoryEventRegistration::create([
                'category_event_id' => $categoryEvent->id,
                'registration_id'   => $registration->id,
                'user_id'           => $actingUser->id,
                'status'            => 'active',
                'payment_status_id' => 1,
                'payment_method'    => 'admin',
            ]);

            // 4. Pair record
            $pair = RegistrationPair::create([
                'registration_id'   => $registration->id,
                'category_event_id' => $categoryEvent->id,
                'player1_cer_id'    => $cer->id,
                'player2_cer_id'    => $cer->id,   // same CER — admin-created single entry
                'owner_user_id'     => $actingUser->id,
                'status'            => RegistrationPair::STATUS_ACTIVE,
                'payment_model'     => RegistrationPair::PAYMENT_FULL,
            ]);

            return $pair;
        });
    }

    // -----------------------------------------------------------------------
    // DELETE
    // -----------------------------------------------------------------------

    /**
     * Remove an admin doubles pair.
     * Withdraws the CER and marks the pair as dissolved.
     *
     * @throws RuntimeException if category is locked or draw is published
     */
    public function removePair(RegistrationPair $pair, User $actingUser): void
    {
        $categoryEvent = $pair->categoryEvent;

        if ($categoryEvent->isLocked()) {
            throw new RuntimeException('Cannot remove pair: category is locked.');
        }

        if ($this->drawIsPublished($categoryEvent)) {
            throw new RuntimeException('Cannot remove pair: draw is published.');
        }

        DB::transaction(function () use ($pair, $actingUser) {
            // Withdraw the CER
            $cer = CategoryEventRegistration::where('registration_id', $pair->registration_id)
                ->where('category_event_id', $pair->category_event_id)
                ->first();

            if ($cer) {
                $cer->update([
                    'status'       => 'withdrawn',
                    'withdrawn_at' => now(),
                    'withdrawn_by' => $actingUser->id,
                ]);
            }

            // Dissolve the pair record
            $pair->update(['status' => RegistrationPair::STATUS_DISSOLVED]);
        });
    }

    // -----------------------------------------------------------------------
    // VALIDATION
    // -----------------------------------------------------------------------

    /**
     * @throws RuntimeException
     */
    public function assertCanCreate(CategoryEvent $categoryEvent, int $player1Id, int $player2Id): void
    {
        // 1. Same player selected twice
        if ($player1Id === $player2Id) {
            throw new RuntimeException('A player cannot be paired with themselves.');
        }

        // 2. Category locked
        if ($categoryEvent->isLocked()) {
            throw new RuntimeException('Cannot add pair: category is locked.');
        }

        // 3. Duplicate pair — both players already share an active registration in this category
        $duplicate = RegistrationPair::where('category_event_id', $categoryEvent->id)
            ->where('status', RegistrationPair::STATUS_ACTIVE)
            ->whereHas('registration.players', fn ($q) => $q->where('players.id', $player1Id))
            ->whereHas('registration.players', fn ($q) => $q->where('players.id', $player2Id))
            ->exists();

        if ($duplicate) {
            throw new RuntimeException('This pair is already registered in the category.');
        }

        // 4. Either player already paired (active pair) in this category
        $this->assertPlayerNotAlreadyPaired($categoryEvent, $player1Id, 'Player 1');
        $this->assertPlayerNotAlreadyPaired($categoryEvent, $player2Id, 'Player 2');
    }

    // -----------------------------------------------------------------------
    // HELPERS
    // -----------------------------------------------------------------------

    private function assertPlayerNotAlreadyPaired(CategoryEvent $ce, int $playerId, string $label): void
    {
        $alreadyPaired = RegistrationPair::where('category_event_id', $ce->id)
            ->where('status', RegistrationPair::STATUS_ACTIVE)
            ->whereHas('registration.players', fn ($q) => $q->where('players.id', $playerId))
            ->exists();

        if ($alreadyPaired) {
            $player = Player::find($playerId);
            $name   = $player ? "{$player->name} {$player->surname}" : "Player #{$playerId}";
            throw new RuntimeException("{$label} ({$name}) is already paired in this category.");
        }
    }

    private function drawIsPublished(CategoryEvent $categoryEvent): bool
    {
        return $categoryEvent->draws()
            ->where('published', 1)
            ->exists();
    }

    // -----------------------------------------------------------------------
    // QUERY HELPERS
    // -----------------------------------------------------------------------

    /**
     * Active pairs for a category event, with relationships loaded.
     */
    public function activePairsFor(CategoryEvent $categoryEvent)
    {
        return RegistrationPair::where('category_event_id', $categoryEvent->id)
            ->where('status', RegistrationPair::STATUS_ACTIVE)
            ->with(['registration.players', 'registration.categoryEvents'])
            ->get();
    }

    /**
     * Players eligible to be added as a partner in this category.
     * Returns all players not already in an active pair here.
     */
    public function eligiblePlayers(CategoryEvent $categoryEvent)
    {
        $pairedPlayerIds = RegistrationPair::where('category_event_id', $categoryEvent->id)
            ->where('status', RegistrationPair::STATUS_ACTIVE)
            ->with('registration.players')
            ->get()
            ->flatMap(fn ($pair) => $pair->registration->players->pluck('id'))
            ->unique()
            ->values();

        return Player::whereNotIn('id', $pairedPlayerIds)
            ->orderBy('surname')
            ->orderBy('name')
            ->get(['id', 'name', 'surname']);
    }
}
