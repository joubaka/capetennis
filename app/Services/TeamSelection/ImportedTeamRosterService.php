<?php

declare(strict_types=1);

namespace App\Services\TeamSelection;

use App\Models\Event;
use App\Models\NoProfileTeamPlayer;
use App\Models\TeamPlayer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ImportedTeamRosterService
{
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
        });
    }

    public function reorder(Event $event, int $teamId, array $slotIds, User $actor): void
    {
        DB::transaction(function () use ($event, $teamId, $slotIds, $actor): void {
            $slots = NoProfileTeamPlayer::query()->where('team_id', $teamId)
                ->lockForUpdate()->orderBy('rank')->get();
            $currentIds = $slots->pluck('id')->map(fn ($id) => (int) $id)->all();
            $requestedIds = array_map('intval', $slotIds);
            if (count($requestedIds) !== count(array_unique($requestedIds))
                || collect($requestedIds)->sort()->values()->all() !== collect($currentIds)->sort()->values()->all()) {
                throw ValidationException::withMessages(['order' => 'The roster changed. Refresh the page and try the order again.']);
            }

            $temporaryBase = min((int) $slots->min('rank'), (int) (TeamPlayer::query()->withoutGlobalScopes()
                ->where('team_id', $teamId)->min('rank') ?? 1)) - count($slots) - 1;
            $teamSlots = TeamPlayer::query()->withoutGlobalScopes()->where('team_id', $teamId)
                ->lockForUpdate()->get()->keyBy('rank');
            if ($teamSlots->count() !== $slots->count()) {
                throw ValidationException::withMessages(['order' => 'The linked roster positions are incomplete. Ask the tournament administrator to repair them before reordering.']);
            }
            $originalRanks = $slots->mapWithKeys(fn (NoProfileTeamPlayer $slot) => [$slot->id => (int) $slot->rank]);
            foreach ($slots as $offset => $slot) {
                $slot->update(['rank' => $temporaryBase + $offset]);
            }
            foreach ($teamSlots->values() as $offset => $teamSlot) {
                $teamSlot->update(['rank' => $temporaryBase + $offset]);
            }

            foreach ($requestedIds as $index => $slotId) {
                NoProfileTeamPlayer::query()->whereKey($slotId)->update(['rank' => $index + 1]);
                $teamSlots->get($originalRanks->get($slotId))->update(['rank' => $index + 1]);
            }

            activity('team-roster')->performedOn($slots->first()->team)->causedBy($actor)
                ->withProperties(['event_id' => $event->id, 'team_id' => $teamId, 'slot_ids' => $requestedIds])
                ->log('regional manager reordered imported roster by drag and drop');
        });
    }
}
