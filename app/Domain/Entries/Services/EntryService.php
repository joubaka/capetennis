<?php

declare(strict_types=1);

namespace App\Domain\Entries\Services;

use App\Domain\Entries\Events\EntryCreated;
use App\Domain\Entries\Events\EntryLocked;
use App\Domain\Entries\Events\EntryTransferred;
use App\Domain\Entries\Events\EntryUnlocked;
use App\Domain\Entries\Events\EntryWithdrawn;
use App\Domain\Entries\StateMachine\EntryStateMachine;
use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\PlayerRegistration;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Canonical entry service for Cape Tennis.
 *
 * All entry mutations should route through this class.
 * Existing controllers act as adapters and delegate here.
 *
 * Responsibilities:
 *  - create entry (admin + frontend paths)
 *  - validate eligibility (via EntryEligibilityService)
 *  - add / remove player from a category
 *  - withdraw entry
 *  - lock / unlock category
 *  - transfer (move) entry between categories
 *  - enforce deadlines and draw-lock rules
 *  - dispatch domain events (after commit)
 */
class EntryService
{
    public function __construct(
        private EntryEligibilityService $eligibility,
        private EntryStateMachine $stateMachine,
    ) {}

    // -----------------------------------------------------------------------
    // CREATE ENTRY (admin path — bypasses payment flow)
    // -----------------------------------------------------------------------

    /**
     * Add a player to a category as an admin entry (no payment required).
     *
     * @param  CategoryEvent  $categoryEvent
     * @param  int            $playerId
     * @param  User           $actingUser
     * @return CategoryEventRegistration
     *
     * @throws \RuntimeException  if category is locked or eligibility fails
     */
    public function addPlayerAsAdmin(
        CategoryEvent $categoryEvent,
        int $playerId,
        User $actingUser
    ): CategoryEventRegistration {
        $this->eligibility->assertCanAddAdmin($categoryEvent, $playerId);

        return DB::transaction(function () use ($categoryEvent, $playerId, $actingUser) {
            $registration = Registration::create([]);

            PlayerRegistration::create([
                'player_id'       => $playerId,
                'registration_id' => $registration->id,
            ]);

            /** @var CategoryEventRegistration $entry */
            $entry = $categoryEvent->categoryEventRegistrations()->create([
                'registration_id'   => $registration->id,
                'user_id'           => $actingUser->id,
                'status'            => 'active',
                'payment_status_id' => 1,
                'payfast_id'        => 'Admin',
            ]);

            DB::table('transactions_pf')->insert([
                'created_at'        => now(),
                'updated_at'        => now(),
                'transaction_type'  => 'Registration',
                'amount_gross'      => 0.00,
                'amount_net'        => 0.00,
                'amount_fee'        => 0.00,
                'cape_tennis_fee'   => 15.00,
                'event_id'          => $categoryEvent->event_id,
                'player_id'         => $playerId,
                'category_event_id' => $categoryEvent->id,
                'pf_payment_id'     => null,
                'is_test'           => 0,
                'item_name'         => 'Admin Entry',
                'custom_int3'       => $categoryEvent->event_id,
                'custom_int4'       => $actingUser->id,
                'custom_str1'       => optional($categoryEvent->category)->name,
                'custom_str3'       => optional($categoryEvent->event)->name,
            ]);

            Log::info('[EntryService] Admin entry created', [
                'category_event_id' => $categoryEvent->id,
                'player_id'         => $playerId,
                'entry_id'          => $entry->id,
                'actor'             => $actingUser->id,
            ]);

            DB::afterCommit(fn () => event(new EntryCreated($entry, $actingUser, 'admin')));

            return $entry;
        });
    }

    // -----------------------------------------------------------------------
    // REMOVE PLAYER (admin path)
    // -----------------------------------------------------------------------

    /**
     * Remove a player from a category (admin path).
     * Sets withdrawn_at; does NOT process a financial refund.
     *
     * @throws \RuntimeException  if category is locked
     */
    public function removePlayer(
        CategoryEvent $categoryEvent,
        Registration $registration,
        User $actingUser
    ): void {
        if ($categoryEvent->isLocked()) {
            throw new \RuntimeException('Category is locked — cannot remove player.');
        }

        DB::transaction(function () use ($categoryEvent, $registration, $actingUser) {
            // HOTFIX 4: Set status='withdrawn' so scopeActive() correctly excludes this player.
            // Previously only withdrawn_at was set, leaving the player visible in active queries.
            $categoryEvent->categoryEventRegistrations()
                ->where('registration_id', $registration->id)
                ->update([
                    'status'       => 'withdrawn',
                    'withdrawn_at' => now(),
                    'withdrawn_by' => $actingUser->id,
                ]);

            Log::info('[EntryService] Player removed from category', [
                'category_event_id' => $categoryEvent->id,
                'registration_id'   => $registration->id,
                'actor'             => $actingUser->id,
            ]);
        });
    }

    // -----------------------------------------------------------------------
    // WITHDRAW ENTRY (frontend — player-initiated)
    // -----------------------------------------------------------------------

    /**
     * Withdraw a player's own registration.
     * Validates the global withdrawal switch, ownership, deadline, and draw-lock.
     * Financial refund is handled separately by the Finance domain.
     *
     * @return array{ok: bool, refund_allowed: bool, message: string}
     *
     * @throws \RuntimeException  on hard validation failures
     */
    public function withdrawEntry(
        CategoryEventRegistration $entry,
        User $actingUser
    ): array {
        $check = $entry->canWithdraw($actingUser);

        if (! $check['ok']) {
            throw new \RuntimeException($check['message']);
        }

        if ($entry->status === 'withdrawn') {
            throw new \RuntimeException('This registration is already withdrawn.');
        }

        $player      = $entry->players->first();
        $eventName   = optional($entry->categoryEvent?->event)->name ?? 'Event';
        $categoryName = optional($entry->categoryEvent?->category)->name ?? '';

        DB::transaction(function () use ($entry, $actingUser, $check, $player, $eventName, $categoryName) {
            $entry->update([
                'status'         => EntryStateMachine::STATE_WITHDRAWN,
                'withdrawn_at'   => now(),
                'refund_status'  => 'not_refunded',
                'refund_method'  => null,
                'refund_gross'   => 0,
                'refund_fee'     => 0,
                'refund_net'     => 0,
                'refunded_at'    => null,
            ]);

            // Remove from any RR draw groups for this event
            if ($entry->registration_id) {
                $eventId = optional($entry->categoryEvent)->event_id;
                if ($eventId) {
                    $drawGroupIds = \DB::table('draw_groups')
                        ->join('draws', 'draws.id', '=', 'draw_groups.draw_id')
                        ->where('draws.event_id', $eventId)
                        ->pluck('draw_groups.id');

                    if ($drawGroupIds->isNotEmpty()) {
                        \DB::table('draw_group_registrations')
                            ->whereIn('draw_group_id', $drawGroupIds)
                            ->where('registration_id', $entry->registration_id)
                            ->delete();
                    }

                    // Null out unplayed fixture slots for this registration
                    $regId = $entry->registration_id;
                    $drawIds = \DB::table('draws')
                        ->where('event_id', $eventId)
                        ->pluck('id');

                    if ($drawIds->isNotEmpty()) {
                        \DB::table('fixtures')
                            ->whereIn('draw_id', $drawIds)
                            ->whereNull('winner_registration')
                            ->where('registration1_id', $regId)
                            ->update(['registration1_id' => null]);

                        \DB::table('fixtures')
                            ->whereIn('draw_id', $drawIds)
                            ->whereNull('winner_registration')
                            ->where('registration2_id', $regId)
                            ->update(['registration2_id' => null]);

                        // Remove schedules for those now-orphaned unplayed fixtures
                        $orphanedFixtureIds = \DB::table('fixtures')
                            ->whereIn('draw_id', $drawIds)
                            ->whereNull('winner_registration')
                            ->where(function ($q) use ($regId) {
                                $q->whereNull('registration1_id')
                                  ->orWhereNull('registration2_id');
                            })
                            ->pluck('id');

                        if ($orphanedFixtureIds->isNotEmpty()) {
                            \DB::table('order_of_plays')
                                ->whereIn('fixture_id', $orphanedFixtureIds)
                                ->delete();
                        }
                    }
                }
            }

            activity('withdrawal')
                ->performedOn($entry)
                ->causedBy($actingUser)
                ->withProperties([
                    'registration_id' => $entry->id,
                    'event'           => $eventName,
                    'category'        => $categoryName,
                    'player'          => $player ? trim($player->name . ' ' . $player->surname) : '',
                    'refund_allowed'  => $check['refund_allowed'] ?? false,
                    'initiated_by'    => 'player',
                ])
                ->log("Withdrew from {$eventName} ({$categoryName})");

            DB::afterCommit(fn () => event(new EntryWithdrawn($entry, $actingUser, 'player')));
        });

        return $check;
    }

    // -----------------------------------------------------------------------
    // WITHDRAW ENTRY (admin-initiated)
    // -----------------------------------------------------------------------

    /**
     * Admin-initiated withdrawal.
     * Bypasses deadline/draw-lock rules; requires admin role enforcement by caller.
     */
    public function withdrawEntryAsAdmin(
        CategoryEventRegistration $entry,
        User $actingUser
    ): void {
        if ($entry->status === 'withdrawn') {
            throw new \RuntimeException('This registration is already withdrawn.');
        }

        $player       = $entry->players->first();
        $eventName    = optional($entry->categoryEvent?->event)->name ?? 'Event';
        $categoryName = optional($entry->categoryEvent?->category)->name ?? '';

        DB::transaction(function () use ($entry, $actingUser, $player, $eventName, $categoryName) {
            $entry->update([
                'status'        => EntryStateMachine::STATE_WITHDRAWN,
                'withdrawn_at'  => now(),
                'refund_status' => 'not_refunded',
                'refund_method' => null,
                'refund_gross'  => 0,
                'refund_fee'    => 0,
                'refund_net'    => 0,
                'refunded_at'   => null,
            ]);

            // Remove from any RR draw groups for this event
            if ($entry->registration_id) {
                $eventId = optional($entry->categoryEvent)->event_id;
                if ($eventId) {
                    $drawGroupIds = \DB::table('draw_groups')
                        ->join('draws', 'draws.id', '=', 'draw_groups.draw_id')
                        ->where('draws.event_id', $eventId)
                        ->pluck('draw_groups.id');

                    if ($drawGroupIds->isNotEmpty()) {
                        \DB::table('draw_group_registrations')
                            ->whereIn('draw_group_id', $drawGroupIds)
                            ->where('registration_id', $entry->registration_id)
                            ->delete();
                    }

                    // Null out unplayed fixture slots for this registration
                    $regId = $entry->registration_id;
                    $drawIds = \DB::table('draws')
                        ->where('event_id', $eventId)
                        ->pluck('id');

                    if ($drawIds->isNotEmpty()) {
                        \DB::table('fixtures')
                            ->whereIn('draw_id', $drawIds)
                            ->whereNull('winner_registration')
                            ->where('registration1_id', $regId)
                            ->update(['registration1_id' => null]);

                        \DB::table('fixtures')
                            ->whereIn('draw_id', $drawIds)
                            ->whereNull('winner_registration')
                            ->where('registration2_id', $regId)
                            ->update(['registration2_id' => null]);

                        // Remove schedules for those now-orphaned unplayed fixtures
                        $orphanedFixtureIds = \DB::table('fixtures')
                            ->whereIn('draw_id', $drawIds)
                            ->whereNull('winner_registration')
                            ->where(function ($q) use ($regId) {
                                $q->whereNull('registration1_id')
                                  ->orWhereNull('registration2_id');
                            })
                            ->pluck('id');

                        if ($orphanedFixtureIds->isNotEmpty()) {
                            \DB::table('order_of_plays')
                                ->whereIn('fixture_id', $orphanedFixtureIds)
                                ->delete();
                        }
                    }
                }
            }

            activity('withdrawal')
                ->performedOn($entry)
                ->causedBy($actingUser)
                ->withProperties([
                    'registration_id' => $entry->id,
                    'event'           => $eventName,
                    'category'        => $categoryName,
                    'player'          => $player ? trim($player->name . ' ' . $player->surname) : '',
                    'initiated_by'    => 'admin',
                    'bypass_reason'   => 'admin override',
                ])
                ->log("Admin withdrew {$eventName} ({$categoryName})");

            DB::afterCommit(fn () => event(new EntryWithdrawn($entry, $actingUser, 'admin')));
        });
    }

    /**
     * Retire an abandoned, unpaid duplicate entry while merging player identities.
     *
     * This is intentionally narrower than a normal admin withdrawal: the entry
     * must have no payment, refund, result, draw, fixture or practice evidence.
     * Financial orders and registration records remain intact for audit.
     */
    public function retireUnpaidDuplicateForPlayerMerge(
        CategoryEventRegistration $entry,
        User $actingUser,
        int $canonicalRegistrationId,
        string $reason
    ): void {
        DB::transaction(function () use ($entry, $actingUser, $canonicalRegistrationId, $reason) {
            $entry = CategoryEventRegistration::query()->lockForUpdate()->findOrFail($entry->id);
            if ($entry->status === EntryStateMachine::STATE_WITHDRAWN) {
                return;
            }

            $orderIds = DB::table('registration_order_items')
                ->where('registration_id', $entry->registration_id)
                ->pluck('order_id')->filter()->unique();
            $orders = DB::table('registration_orders')->whereIn('id', $orderIds)->get();
            $hasFinancialEvidence = (int) $entry->payment_status_id === 1
                || filled($entry->pf_transaction_id)
                || filled($entry->wallet_transaction_id)
                || $orders->contains(fn ($order) => (bool) $order->pay_status
                    || (bool) $order->payfast_paid
                    || (bool) $order->wallet_debited
                    || (float) $order->wallet_reserved > 0
                    || filled($order->payfast_pf_payment_id)
                    || filled($order->wallet_transaction_id))
                || ($orderIds->isNotEmpty() && DB::table('transactions_pf')->whereIn('custom_int5', $orderIds)->exists());

            $hasCompetitionHistory = DB::table('category_results')
                ->where('registration_id', $entry->registration_id)->exists()
                || $this->registrationHasCompetitionHistory((int) $entry->registration_id);
            $hasRefundEvidence = ! in_array($entry->refund_status, [null, '', 'not_refunded'], true)
                || (float) ($entry->refund_gross ?? 0) !== 0.0
                || (float) ($entry->refund_fee ?? 0) !== 0.0
                || (float) ($entry->refund_net ?? 0) !== 0.0
                || filled($entry->refunded_at);

            if ($orderIds->count() !== 1 || $hasFinancialEvidence || $hasCompetitionHistory || $hasRefundEvidence) {
                throw ValidationException::withMessages([
                    'registration_overlap' => 'The duplicate registration gained payment, refund, result or competition history. Nothing was merged.',
                ]);
            }

            $entry->update([
                'status' => EntryStateMachine::STATE_WITHDRAWN,
                'withdrawn_at' => now(),
                'withdrawn_by' => $actingUser->id,
                'withdrawal_reason' => Str::limit($reason, 1000, ''),
            ]);

            activity('player-profile-merge')
                ->performedOn($entry)
                ->causedBy($actingUser)
                ->withProperties([
                    'duplicate_registration_id' => (int) $entry->registration_id,
                    'canonical_registration_id' => $canonicalRegistrationId,
                    'category_event_id' => (int) $entry->category_event_id,
                    'reason' => $reason,
                ])->log('Retired unpaid duplicate registration during player merge');

            Log::info('[EntryService] Unpaid duplicate registration retired for player merge', [
                'entry_id' => $entry->id,
                'duplicate_registration_id' => $entry->registration_id,
                'canonical_registration_id' => $canonicalRegistrationId,
                'actor' => $actingUser->id,
            ]);
        });
    }

    private function registrationHasCompetitionHistory(int $registrationId): bool
    {
        foreach ([
            'fixtures' => ['registration1_id', 'registration2_id', 'winner_registration'],
            'fixture_results' => ['winner_registration', 'loser_registration'],
            'practice_fixtures' => ['registration1_id', 'registration2_id'],
            'practice_results' => ['winner_registration', 'loser_registration'],
            'draw_group_registrations' => ['registration_id'],
        ] as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (Schema::hasColumn($table, $column)
                    && DB::table($table)->where($column, $registrationId)->exists()) {
                    return true;
                }
            }
        }

        return false;
    }

    // -----------------------------------------------------------------------
    // LOCK / UNLOCK CATEGORY
    // -----------------------------------------------------------------------

    public function lockCategory(CategoryEvent $categoryEvent, User $actingUser): void
    {
        $categoryEvent->update(['locked_at' => now()]);

        Log::info('[EntryService] Category locked', [
            'category_event_id' => $categoryEvent->id,
            'actor'             => $actingUser->id,
        ]);

        DB::afterCommit(fn () => event(new EntryLocked($categoryEvent, $actingUser)));
    }

    public function unlockCategory(CategoryEvent $categoryEvent, User $actingUser): void
    {
        $categoryEvent->update(['locked_at' => null]);

        Log::info('[EntryService] Category unlocked', [
            'category_event_id' => $categoryEvent->id,
            'actor'             => $actingUser->id,
        ]);

        DB::afterCommit(fn () => event(new EntryUnlocked($categoryEvent, $actingUser)));
    }

    // -----------------------------------------------------------------------
    // TRANSFER (MOVE) ENTRY
    // -----------------------------------------------------------------------

    /**
     * Transfer an entry from its current category to a new one.
     * Used by both frontend (player) and backend (admin) paths.
     *
     * @throws \RuntimeException  on any guard failure
     */
    public function transferEntry(
        CategoryEventRegistration $entry,
        CategoryEvent $targetCategory,
        User $actingUser,
        bool $adminOverride = false
    ): CategoryEventRegistration {
        $this->eligibility->assertCanTransfer($entry, $targetCategory, $actingUser, $adminOverride);

        return DB::transaction(function () use ($entry, $targetCategory, $actingUser) {
            $fromCategoryId = $entry->category_event_id;

            $entry->update(['category_event_id' => $targetCategory->id]);

            Log::info('[EntryService] Entry transferred', [
                'entry_id'           => $entry->id,
                'from_category'      => $fromCategoryId,
                'to_category'        => $targetCategory->id,
                'actor'              => $actingUser->id,
            ]);

            DB::afterCommit(fn () => event(
                new EntryTransferred($entry, $fromCategoryId, $targetCategory->id, $actingUser)
            ));

            return $entry->fresh();
        });
    }
}
