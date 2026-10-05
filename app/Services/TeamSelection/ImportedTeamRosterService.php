<?php

declare(strict_types=1);

namespace App\Services\TeamSelection;

use App\Domain\Payments\Services\TeamPaymentService;
use App\Models\CategoryEventRegistration;
use App\Models\ClothingOrder;
use App\Models\Event;
use App\Models\NoProfileTeamPlayer;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamFixturePlayer;
use App\Models\TeamPaymentOrder;
use App\Models\TeamPlayer;
use App\Models\TeamSelectionInvitation;
use App\Models\User;
use App\Services\PlayerEligibilityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ImportedTeamRosterService
{
    public function unlink(Event $event, NoProfileTeamPlayer $slot, int $expectedPlayerId, int $expectedRank, User $actor): void
    {
        DB::transaction(function () use ($event, $slot, $expectedPlayerId, $expectedRank, $actor): void {
            app(\App\Services\TeamDrawAdaptationService::class)->lockEvent($event->id);
            $team = Team::query()->lockForUpdate()->findOrFail($slot->team_id);
            abort_unless($team->noProfile && $team->category()->where('event_id', $event->id)->exists()
                && app(RegionManagerAccessService::class)->isEventManager($actor, $event), 403);
            app(TeamSelectionInvitationService::class)->assertRosterEditable($team);
            $locked = NoProfileTeamPlayer::query()->lockForUpdate()->findOrFail($slot->id);
            $fail = fn (string $message) => throw ValidationException::withMessages(['player_id' => $message]);
            if ((int) $locked->team_id !== (int) $team->id || (int) $locked->player_profile !== $expectedPlayerId
                || (int) $locked->rank !== $expectedRank || $expectedPlayerId < 1) {
                $fail('The roster changed. Refresh the page before unlinking its profile.');
            }
            Player::query()->lockForUpdate()->findOrFail($expectedPlayerId);
            $mirrors = TeamPlayer::query()->withoutGlobalScopes()->where('team_id', $team->id)
                ->where('rank', $locked->rank)->lockForUpdate()->get();
            $mirror = $mirrors->first();
            if ($mirrors->count() > 1 || ($mirror && ! in_array((int) $mirror->player_id, [0, $expectedPlayerId], true))
                || NoProfileTeamPlayer::query()->where('team_id', $team->id)->where('rank', $locked->rank)->count() !== 1
                || TeamPlayer::query()->where('team_id', $team->id)->where('player_id', $expectedPlayerId)
                    ->where('rank', '!=', $locked->rank)->lockForUpdate()->get()->isNotEmpty()) {
                $fail('The linked roster position is inconsistent. Repair it before unlinking the profile.');
            }
            $orders = TeamPaymentOrder::query()->where('event_id', $event->id)->forBeneficiary($expectedPlayerId)->lockForUpdate()->get();
            foreach ($orders as $order) {
                if ((int) $order->team_id !== (int) $team->id) {
                    $fail('This player has checkout history on another team in this event.');
                }
                app(TeamPaymentService::class)->assertUnpaidRosterCheckoutMayBeReset($order);
            }
            if ((int) $locked->pay_status !== 0 || (int) $mirror?->pay_status !== 0
                || ClothingOrder::query()->where('event_id', $event->id)->where('player_id', $expectedPlayerId)->lockForUpdate()->get()->isNotEmpty()
                || TeamSelectionInvitation::query()->where('event_id', $event->id)->where('player_id', $expectedPlayerId)->lockForUpdate()->get()->isNotEmpty()
                || CategoryEventRegistration::withTrashed()->whereHas('categoryEvent', fn ($q) => $q->where('event_id', $event->id))
                    ->whereHas('registration.players', fn ($q) => $q->where('players.id', $expectedPlayerId))->lockForUpdate()->get()->isNotEmpty()
                || TeamFixturePlayer::query()->whereHas('fixture.draw', fn ($q) => $q->where('event_id', $event->id))
                    ->where(fn ($q) => $q->where('team1_id', $expectedPlayerId)->orWhere('team2_id', $expectedPlayerId)
                        ->orWhere('team1_no_profile_id', $locked->id)->orWhere('team2_no_profile_id', $locked->id))->lockForUpdate()->get()->isNotEmpty()) {
                $fail('This profile has payment, registration, invitation or fixture history. Use the existing withdrawal or selection workflow.');
            }
            $before = $locked->only(['player_profile', 'claimed_by_user_id', 'claimed_at']);
            foreach ($orders as $order) {
                app(TeamPaymentService::class)->closeUnpaidLifecycle($order, $actor);
            }
            if ($mirror) {
                app(TeamPaymentService::class)->updateTeamPlayerSlot($mirror, ['player_id' => 0]);
            }
            $locked->update(['player_profile' => null, 'claimed_by_user_id' => null, 'claimed_at' => null]);
            activity('team-roster')->performedOn($team)->causedBy($actor)->withProperties([
                'event_id' => $event->id, 'slot_id' => $locked->id, 'rank' => $locked->rank,
                'before' => $before, 'after_player_id' => null, 'mirror_id' => $mirror?->id,
                'closed_order_ids' => $orders->pluck('id')->all(),
            ])->log('administrator unlinked unpaid imported roster profile');
            app(\App\Services\TeamDrawAdaptationService::class)->adaptEvent($event);
        });
    }

    public function relink(
        Event $event,
        NoProfileTeamPlayer $slot,
        int $playerId,
        int $expectedPlayerId,
        int $expectedRank,
        User $actor,
    ): void {
        DB::transaction(function () use ($event, $slot, $playerId, $expectedPlayerId, $expectedRank, $actor): void {
            app(\App\Services\TeamDrawAdaptationService::class)->lockEvent($event->id);
            $team = Team::query()->lockForUpdate()->findOrFail($slot->team_id);
            abort_unless($team->noProfile && $team->category()->where('event_id', $event->id)->exists()
                && app(RegionManagerAccessService::class)->isEventManager($actor, $event), 403);
            app(TeamSelectionInvitationService::class)->assertRosterEditable($team);
            $locked = NoProfileTeamPlayer::query()->lockForUpdate()->findOrFail($slot->id);
            $fail = fn (string $message) => throw ValidationException::withMessages(['player_id' => $message]);
            if ((int) $locked->team_id !== (int) $team->id || (int) $locked->player_profile !== $expectedPlayerId || (int) $locked->rank !== $expectedRank) {
                $fail('The roster changed. Refresh the page before replacing its linked profile.');
            }
            if ($playerId === $expectedPlayerId) {
                $fail('Choose a different player profile.');
            }
            $player = Player::query()->lockForUpdate()->findOrFail($playerId);
            try {
                app(PlayerEligibilityService::class)->assertEligible($player, $event);
            } catch (\RuntimeException $exception) {
                $fail($exception->getMessage());
            }
            $teamSlots = TeamPlayer::query()->withoutGlobalScopes()->where('team_id', $team->id)
                ->where('rank', $locked->rank)->lockForUpdate()->get();
            $teamSlot = $teamSlots->first();
            $alignsExistingReplacement = $teamSlot && (int) $teamSlot->player_id === $playerId;
            if ($teamSlots->count() > 1 || ($teamSlot && (int) $teamSlot->player_id > 0
                && (int) $teamSlot->player_id !== $expectedPlayerId && ! $alignsExistingReplacement)
                || NoProfileTeamPlayer::query()->where('team_id', $team->id)->where('rank', $locked->rank)->count() !== 1) {
                $fail('The linked roster position is inconsistent. Repair it before replacing the profile.');
            }
            if ($expectedPlayerId > 0 && TeamPlayer::query()->where('team_id', $team->id)
                ->where('player_id', $expectedPlayerId)->where('rank', '!=', $locked->rank)->lockForUpdate()->get()->isNotEmpty()) {
                $fail('The current profile is assigned to another roster position. Repair its playing order before replacing the profile.');
            }
            // Current locking reads must observe another relink that committed while
            // this transaction waited for the replacement player's lock.
            if (TeamPlayer::query()->whereHas('team.category', fn ($q) => $q->where('event_id', $event->id))
                ->where('player_id', $playerId)
                ->when($alignsExistingReplacement, fn ($q) => $q->where('id', '!=', $teamSlot->id))
                ->lockForUpdate()->get()->isNotEmpty()
                || NoProfileTeamPlayer::query()->whereHas('team.category', fn ($q) => $q->where('event_id', $event->id))
                    ->where('player_profile', $playerId)->lockForUpdate()->get()->isNotEmpty()) {
                $fail('That player is already linked to a roster in this event.');
            }
            $playerIds = array_filter([$expectedPlayerId, $playerId]);
            $orders = TeamPaymentOrder::query()->where('event_id', $event->id)->forPlayerHistory($playerIds)->lockForUpdate()->get();
            $staleOrders = collect();
            foreach ($orders as $order) {
                if (! $alignsExistingReplacement || (int) $order->player_id !== $expectedPlayerId || (int) $order->team_id !== (int) $team->id) {
                    $fail('This player has payment history in this event. Profile relinking cannot transfer that history.');
                }
                if ($order->withdrawn_at !== null && $order->refund_status === 'completed' && $order->refunded_at !== null
                    && round((float) $order->refund_gross, 2) === round((float) $order->total_amount, 2)
                    && (float) $order->wallet_reserved === 0.0) {
                    continue;
                }
                app(TeamPaymentService::class)->assertUnpaidRosterCheckoutMayBeClosed($order);
                if ($order->withdrawn_at === null) {
                    $staleOrders->push($order);
                }
            }
            if ((int) $locked->pay_status !== 0 || (int) $teamSlot?->pay_status !== 0
                || ClothingOrder::query()->where('event_id', $event->id)->whereIn('player_id', $playerIds)->lockForUpdate()->get()->isNotEmpty()
                || TeamSelectionInvitation::query()->where('event_id', $event->id)->whereIn('player_id', $playerIds)->lockForUpdate()->get()->isNotEmpty()
                || CategoryEventRegistration::withTrashed()->whereHas('categoryEvent', fn ($q) => $q->where('event_id', $event->id))
                    ->whereHas('registration.players', fn ($q) => $q->whereIn('players.id', $playerIds))->lockForUpdate()->get()->isNotEmpty()
                || TeamFixturePlayer::query()->whereHas('fixture.draw', fn ($q) => $q->where('event_id', $event->id))
                    ->where(fn ($q) => $q->whereIn('team1_id', $playerIds)->orWhereIn('team2_id', $playerIds)
                        ->orWhere('team1_no_profile_id', $locked->id)->orWhere('team2_no_profile_id', $locked->id))->lockForUpdate()->get()->isNotEmpty()) {
                $fail('This player has registration, payment, invitation or fixture history in this event. Profile relinking cannot transfer that history; use the existing withdrawal or selection workflow for participant replacement.');
            }
            foreach ($staleOrders as $staleOrder) {
                app(TeamPaymentService::class)->closeUnpaidLifecycle($staleOrder, $actor);
            }
            $before = $locked->only(['player_profile', 'claimed_by_user_id', 'claimed_at']);
            $mirrorBefore = $teamSlot?->only(['id', 'player_id', 'rank', 'pay_status']);
            $repairKind = $alignsExistingReplacement ? 'aligned_existing_replacement'
                : (! $teamSlot ? 'missing_mirror' : ((int) $teamSlot->player_id === 0 ? 'empty_mirror' : 'existing_link'));
            if ($teamSlot && ! $alignsExistingReplacement) {
                app(TeamPaymentService::class)->updateTeamPlayerSlot($teamSlot, ['player_id' => $playerId]);
            } elseif (! $teamSlot) {
                $teamSlot = app(TeamPaymentService::class)->createUnpaidTeamPlayerSlot($team, $player, (int) $locked->rank);
            }
            $locked->update(['player_profile' => $playerId, 'claimed_by_user_id' => null, 'claimed_at' => null]);
            activity('team-roster')->performedOn($team)->causedBy($actor)->withProperties([
                'event_id' => $event->id, 'slot_id' => $locked->id, 'rank' => $locked->rank,
                'before' => $before, 'after_player_id' => $playerId,
                'mirror_before' => $mirrorBefore, 'mirror_after_id' => $teamSlot->id, 'mirror_repair' => $repairKind,
                'closed_stale_order_ids' => $staleOrders->pluck('id')->all(),
            ])->log('administrator replaced imported roster profile link');
            app(\App\Services\TeamDrawAdaptationService::class)->adaptEvent($event);
        });
    }

    public function rename(
        Event $event,
        NoProfileTeamPlayer $slot,
        string $name,
        string $surname,
        User $actor,
    ): NoProfileTeamPlayer {
        return DB::transaction(function () use ($event, $slot, $name, $surname, $actor): NoProfileTeamPlayer {
            $locked = NoProfileTeamPlayer::query()->lockForUpdate()->findOrFail($slot->id);
            $before = ['name' => $locked->name, 'surname' => $locked->surname];
            $after = ['name' => trim($name), 'surname' => trim($surname)];

            $locked->update($after);

            activity('team-roster')->performedOn($locked->team)->causedBy($actor)
                ->withProperties([
                    'event_id' => $event->id,
                    'slot_id' => $locked->id,
                    'rank' => $locked->rank,
                    'linked_player_id' => $locked->player_profile,
                    'before' => $before,
                    'after' => $after,
                ])->log('regional manager corrected imported roster name');

            return $locked->fresh('profile');
        });
    }

    public function move(
        Event $event,
        NoProfileTeamPlayer $slot,
        string $direction,
        User $actor,
    ): void {
        DB::transaction(function () use ($event, $slot, $direction, $actor): void {
            app(\App\Services\TeamDrawAdaptationService::class)->lockEvent($event->id);
            $locked = NoProfileTeamPlayer::query()->lockForUpdate()->findOrFail($slot->id);
            $target = NoProfileTeamPlayer::query()
                ->where('team_id', $locked->team_id)
                ->where('rank', $direction === 'up' ? '<' : '>', $locked->rank)
                ->orderBy('rank', $direction === 'up' ? 'desc' : 'asc')
                ->lockForUpdate()
                ->first();

            if (! $target) {
                throw ValidationException::withMessages([
                    'order' => 'That player is already at the end of the imported roster.',
                ]);
            }

            $currentRank = (int) $locked->rank;
            $targetRank = (int) $target->rank;
            $lowestImportedRank = (int) NoProfileTeamPlayer::query()
                ->where('team_id', $locked->team_id)->min('rank');
            $lowestTeamRank = (int) (TeamPlayer::query()->withoutGlobalScopes()
                ->where('team_id', $locked->team_id)->min('rank') ?? $lowestImportedRank);
            $temporaryRank = min($lowestImportedRank, $lowestTeamRank) - 1;

            $locked->update(['rank' => $temporaryRank]);
            $target->update(['rank' => $currentRank]);
            $locked->update(['rank' => $targetRank]);

            $teamSlots = TeamPlayer::query()->withoutGlobalScopes()->lockForUpdate()
                ->where('team_id', $locked->team_id)
                ->whereIn('rank', [$currentRank, $targetRank])
                ->get();
            if ($teamSlots->groupBy('rank')->contains(fn ($slots): bool => $slots->count() > 1)) {
                throw ValidationException::withMessages([
                    'order' => 'This roster has duplicate linked positions. Ask the tournament administrator to repair it before changing the order.',
                ]);
            }
            $currentTeamSlot = $teamSlots->firstWhere('rank', $currentRank);
            $targetTeamSlot = $teamSlots->firstWhere('rank', $targetRank);
            if ($currentTeamSlot && $targetTeamSlot) {
                $currentTeamSlot->update(['rank' => $temporaryRank]);
                $targetTeamSlot->update(['rank' => $currentRank]);
                $currentTeamSlot->update(['rank' => $targetRank]);
            } elseif ($currentTeamSlot) {
                $currentTeamSlot->update(['rank' => $targetRank]);
            } elseif ($targetTeamSlot) {
                $targetTeamSlot->update(['rank' => $currentRank]);
            }

            activity('team-roster')->performedOn($locked->team)->causedBy($actor)
                ->withProperties([
                    'event_id' => $event->id,
                    'slot_id' => $locked->id,
                    'swapped_slot_id' => $target->id,
                    'from_rank' => $currentRank,
                    'to_rank' => $targetRank,
                ])->log('regional manager reordered imported roster');
            app(\App\Services\TeamDrawAdaptationService::class)->adaptEvent($event);
        });
    }

    public function updateEmail(Event $event, NoProfileTeamPlayer $slot, string $email, User $actor): NoProfileTeamPlayer
    {
        return DB::transaction(function () use ($event, $slot, $email, $actor): NoProfileTeamPlayer {
            $locked = NoProfileTeamPlayer::query()->lockForUpdate()->findOrFail($slot->id);
            $before = $locked->email;
            $after = mb_strtolower(trim($email));
            $locked->update(['email' => $after]);

            activity('team-roster')->performedOn($locked->team)->causedBy($actor)
                ->withProperties([
                    'event_id' => $event->id,
                    'slot_id' => $locked->id,
                    'rank' => $locked->rank,
                    'linked_player_id' => $locked->player_profile,
                    'before_email' => $before,
                    'after_email' => $after,
                ])->log('regional manager updated imported roster email');

            return $locked->fresh('profile');
        });
    }

    public function reorder(Event $event, int $teamId, array $slotIds, User $actor, ?array $expectedIds = null): void
    {
        DB::transaction(function () use ($event, $teamId, $slotIds, $actor, $expectedIds): void {
            app(\App\Services\TeamDrawAdaptationService::class)->lockEvent($event->id);
            $slots = NoProfileTeamPlayer::query()->where('team_id', $teamId)
                ->lockForUpdate()->orderBy('rank')->get();
            $currentIds = $slots->pluck('id')->map(fn ($id) => (int) $id)->all();
            $requestedIds = array_map('intval', $slotIds);
            if (($expectedIds !== null && array_map('intval', $expectedIds) !== $currentIds)
                || count($requestedIds) !== count(array_unique($requestedIds))
                || collect($requestedIds)->sort()->values()->all() !== collect($currentIds)->sort()->values()->all()) {
                throw ValidationException::withMessages(['order' => 'The roster changed. Refresh the page and try the order again.']);
            }
            if ($requestedIds === $currentIds) return;

            $temporaryBase = min((int) $slots->min('rank'), (int) (TeamPlayer::query()->withoutGlobalScopes()
                ->where('team_id', $teamId)->min('rank') ?? 1)) - count($slots) - 1;
            $teamSlotRows = TeamPlayer::query()->withoutGlobalScopes()->where('team_id', $teamId)
                ->lockForUpdate()->get();
            $teamSlots = $teamSlotRows->keyBy('rank');
            if ($teamSlotRows->count() !== $slots->count() || $teamSlots->count() !== $slots->count()
                || $teamSlotRows->pluck('rank')->map(fn ($rank) => (int) $rank)->sort()->values()->all()
                    !== $slots->pluck('rank')->map(fn ($rank) => (int) $rank)->sort()->values()->all()
                || $slots->contains(fn ($slot) => $slot->player_profile && (int) $teamSlots->get($slot->rank)?->player_id !== (int) $slot->player_profile)) {
                throw ValidationException::withMessages(['order' => 'The linked roster positions are incomplete. Ask the tournament administrator to repair them before reordering.']);
            }
            $originalRanks = $slots->mapWithKeys(fn (NoProfileTeamPlayer $slot) => [$slot->id => (int) $slot->rank]);
            $destinationRanks = $originalRanks->values()->all();
            foreach ($slots as $offset => $slot) {
                $slot->update(['rank' => $temporaryBase + $offset]);
            }
            foreach ($teamSlots->values() as $offset => $teamSlot) {
                $teamSlot->update(['rank' => $temporaryBase + $offset]);
            }

            foreach ($requestedIds as $index => $slotId) {
                NoProfileTeamPlayer::query()->whereKey($slotId)->update(['rank' => $destinationRanks[$index]]);
                $teamSlots->get($originalRanks->get($slotId))->update(['rank' => $destinationRanks[$index]]);
            }

            activity('team-roster')->performedOn($slots->first()->team)->causedBy($actor)
                ->withProperties(['event_id' => $event->id, 'team_id' => $teamId,
                    'previous_slot_ids' => $currentIds, 'slot_ids' => $requestedIds, 'ranks' => $destinationRanks])
                ->log('regional manager reordered imported roster by drag and drop');
            app(\App\Services\TeamDrawAdaptationService::class)->adaptEvent($event);
        });
    }
}
