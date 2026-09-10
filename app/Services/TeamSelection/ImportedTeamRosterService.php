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
}
