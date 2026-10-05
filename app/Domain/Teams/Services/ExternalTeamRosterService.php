<?php

namespace App\Domain\Teams\Services;

use App\Models\Event;
use App\Models\NoProfileTeamPlayer;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Services\PlayerEligibilityService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExternalTeamRosterService
{
    public function __construct(private PlayerEligibilityService $playerEligibility) {}

    public function teamBelongsToEvent(Team $team, Event $event): bool
    {
        if ($team->category_event_id) {
            return $team->category()->where('event_id', $event->id)->exists();
        }

        return $team->region_id && DB::table('event_regions')
            ->where('event_id', $event->id)
            ->where('region_id', $team->region_id)
            ->exists();
    }

    public function assertTeamBelongsToEvent(Team $team, Event $event): void
    {
        abort_unless($this->teamBelongsToEvent($team, $event), 404);
    }

    public function validateImport(Team $team, array $rows): array
    {
        $errors = [];
        $expected = (int) $team->num_team_members;

        if ($rows === []) $errors[] = 'The spreadsheet does not contain any roster players.';
        if ($expected > 0 && count($rows) !== $expected) {
            $errors[] = "This team has {$expected} roster slots, but the spreadsheet contains ".count($rows).'.';
        }

        if ($expected > 0) {
            $ranks = array_column($rows, 'rank');
            $missing = array_values(array_diff(range(1, $expected), $ranks));
            $outside = array_values(array_filter($ranks, fn (int $rank): bool => $rank > $expected));
            if ($missing !== []) $errors[] = 'Missing roster ranks: '.implode(', ', $missing).'.';
            if ($outside !== []) $errors[] = 'Ranks outside the configured roster size: '.implode(', ', $outside).'.';
        }

        $existing = $team->team_players_no_profile()->get()->keyBy('rank');
        $duplicateTeamPlayerRanks = TeamPlayer::query()
            ->where('team_id', $team->id)
            ->select('rank')
            ->groupBy('rank')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('rank')
            ->all();
        if ($duplicateTeamPlayerRanks !== []) {
            $errors[] = 'Duplicate existing team slots must be resolved at ranks: '.implode(', ', $duplicateTeamPlayerRanks).'.';
        }

        foreach ($rows as $row) {
            $slot = $existing->get($row['rank']);
            if (! $slot || ! $slot->player_profile) continue;

            if ($this->identityName((string) $slot->name, (string) $slot->surname)
                !== $this->identityName($row['name'], $row['surname'])) {
                $errors[] = "Rank {$row['rank']} is already linked to a profile. Use roster management before changing that imported name.";
            }
        }

        return array_values(array_unique($errors));
    }

    public function import(Team $team, array $rows, User $actor): void
    {
        DB::transaction(function () use ($team, $rows, $actor): void {
            app(\App\Services\TeamDrawAdaptationService::class)->lockEvent($team->category->event_id);
            Team::whereKey($team->id)->lockForUpdate()->firstOrFail();

            foreach ($rows as $row) {
                $slot = NoProfileTeamPlayer::firstOrNew(['team_id' => $team->id, 'rank' => $row['rank']]);
                $slot->fill([
                    'name' => $row['name'],
                    'surname' => $row['surname'],
                    'date_of_birth' => $row['date_of_birth'],
                    'email' => $row['email'],
                    'cell_nr' => $row['cell_nr'],
                ]);
                $slot->pay_status ??= 0;
                $slot->save();

                TeamPlayer::firstOrCreate(
                    ['team_id' => $team->id, 'rank' => $row['rank']],
                    ['player_id' => 0, 'pay_status' => 0]
                );
            }

            $team->forceFill([
                'noProfile' => true,
                'num_team_members' => (int) $team->num_team_members > 0 ? $team->num_team_members : count($rows),
            ])->save();

            activity('team-roster')->performedOn($team)->causedBy($actor)
                ->withProperties(['team_id' => $team->id, 'row_count' => count($rows), 'ranks' => array_column($rows, 'rank')])
                ->log('External team roster imported');
            app(\App\Services\TeamDrawAdaptationService::class)->adaptEvent($team->category->event_id);
        });
    }

    public function assertClaimAvailable(Event $event, Team $team, NoProfileTeamPlayer $slot): void
    {
        $this->assertTeamBelongsToEvent($team, $event);
        abort_unless((int) $slot->team_id === (int) $team->id, 404);

        if (! $team->published) {
            throw ValidationException::withMessages(['team' => 'This team has not been published yet.']);
        }
        if (! $this->registrationIsOpen($event)) {
            throw ValidationException::withMessages(['event' => 'Registration for this event is closed.']);
        }
        if ($slot->player_profile) {
            throw ValidationException::withMessages(['player' => 'This roster position has already been linked to a profile.']);
        }
    }

    public function claim(
        User $user,
        Event $event,
        Team $team,
        NoProfileTeamPlayer $slot,
        Player $player,
    ): void
    {
        $this->assertClaimCandidate($event, $team, $slot, $player);

        $this->playerEligibility->assertEligible($player, $event);

        DB::transaction(function () use ($user, $event, $team, $slot, $player): void {
            app(\App\Services\TeamDrawAdaptationService::class)->lockEvent($event->id);
            $lockedSlot = NoProfileTeamPlayer::whereKey($slot->id)->lockForUpdate()->firstOrFail();
            if ((int) $lockedSlot->team_id !== (int) $team->id || $lockedSlot->player_profile) {
                throw ValidationException::withMessages(['player' => 'This roster position is no longer available.']);
            }

            $teamPlayers = TeamPlayer::where('team_id', $team->id)
                ->where('rank', $lockedSlot->rank)
                ->lockForUpdate()
                ->get();
            if ($teamPlayers->count() > 1) {
                throw ValidationException::withMessages([
                    'player' => 'This roster rank has duplicate team slots. Ask the tournament administrator to repair it before linking a profile.',
                ]);
            }
            $teamPlayer = $teamPlayers->first();
            $teamPlayer ??= new TeamPlayer([
                'team_id' => $team->id,
                'rank' => $lockedSlot->rank,
                'player_id' => 0,
                'pay_status' => 0,
            ]);

            if ((int) $teamPlayer->player_id > 0 && (int) $teamPlayer->player_id !== (int) $player->id) {
                throw ValidationException::withMessages(['player' => 'This roster position has already been assigned to another player.']);
            }

            $teamPlayer->player_id = $player->id;
            $teamPlayer->pay_status ??= 0;
            $teamPlayer->save();

            $lockedSlot->forceFill([
                'player_profile' => $player->id,
                'claimed_by_user_id' => $user->id,
                'claimed_at' => now(),
            ])->save();

            $user->players()->syncWithoutDetaching([$player->id]);

            activity('team-roster')->performedOn($team)->causedBy($user)
                ->withProperties(['event_id' => $event->id, 'slot_id' => $lockedSlot->id, 'rank' => $lockedSlot->rank, 'player_id' => $player->id])
                ->log('External roster position claimed');
            app(\App\Services\TeamDrawAdaptationService::class)->adaptEvent($event);
        });
    }

    public function assertClaimCandidate(Event $event, Team $team, NoProfileTeamPlayer $slot, Player $player): void
    {
        $this->assertClaimAvailable($event, $team, $slot);
    }

    public function assertCanRegister(User $user, Event $event, Team $team, Player $player): TeamPlayer
    {
        return $this->assertSelectedPlayerCanRegister($event, $team, $player);
    }

    public function assertSelectedPlayerCanRegister(Event $event, Team $team, Player $player): TeamPlayer
    {
        $this->assertTeamBelongsToEvent($team, $event);

        if (! $team->published) throw ValidationException::withMessages(['team' => 'This team has not been published yet.']);
        if (! $this->registrationIsOpen($event)) throw ValidationException::withMessages(['event' => 'Registration for this event is closed.']);

        return DB::transaction(function () use ($event, $team, $player): TeamPlayer {
            app(\App\Services\TeamDrawAdaptationService::class)->lockEvent($event->id);
            if (! $team->noProfile) {
                $teamPlayer = TeamPlayer::query()
                    ->where('team_id', $team->id)
                    ->where('player_id', $player->id)
                    ->lockForUpdate()
                    ->first();
                if (! $teamPlayer) throw ValidationException::withMessages(['player' => 'This player is not on the selected team roster.']);
                if ((int) $teamPlayer->pay_status === 1) throw ValidationException::withMessages(['player' => 'This player is already registered.']);

                $this->playerEligibility->assertEligible($player, $event);

                return $teamPlayer;
            }

            $canonicalSlots = NoProfileTeamPlayer::query()
                ->where('team_id', $team->id)
                ->where('player_profile', $player->id)
                ->lockForUpdate()
                ->get();
            if ($canonicalSlots->count() !== 1) {
                throw ValidationException::withMessages(['player' => 'This player is not on the selected team roster.']);
            }

            $canonicalSlot = $canonicalSlots->first();
            if ((int) $canonicalSlot->pay_status === 1) {
                throw ValidationException::withMessages(['player' => 'This player is already registered.']);
            }

            $teamPlayers = TeamPlayer::query()
                ->where('team_id', $team->id)
                ->where('rank', $canonicalSlot->rank)
                ->lockForUpdate()
                ->get();
            if ($teamPlayers->count() > 1) {
                throw ValidationException::withMessages([
                    'player' => 'This roster rank has duplicate team slots. Ask the tournament administrator to repair it before registering.',
                ]);
            }

            $teamPlayer = $teamPlayers->first();
            if ($teamPlayer && (int) $teamPlayer->player_id > 0 && (int) $teamPlayer->player_id !== (int) $player->id) {
                throw ValidationException::withMessages([
                    'player' => 'This roster position is assigned to another player. Ask the tournament administrator to repair it before registering.',
                ]);
            }

            if (! $teamPlayer) {
                $teamPlayer = new TeamPlayer([
                    'team_id' => $team->id,
                    'rank' => $canonicalSlot->rank,
                    'player_id' => $player->id,
                    'pay_status' => 0,
                ]);
            } elseif ((int) $teamPlayer->player_id === 0) {
                $teamPlayer->player_id = $player->id;
            }

            if ((int) $teamPlayer->pay_status === 1) {
                throw ValidationException::withMessages(['player' => 'This player is already registered.']);
            }

            $this->playerEligibility->assertEligible($player, $event);
            if ($teamPlayer->isDirty() || ! $teamPlayer->exists) {
                $teamPlayer->save();
                app(\App\Services\TeamDrawAdaptationService::class)->adaptEvent($event);
            }

            return $teamPlayer;
        });
    }

    public function registrationIsOpen(Event $event): bool
    {
        return $event->published
            && (bool) $event->signUp
            && ! in_array($event->status, ['closed', 'draft'], true);
    }

    private function identityName(string $name, string $surname): string
    {
        $fullName = trim((string) preg_replace('/\s+/u', ' ', $name.' '.$surname));

        return mb_strtolower(Str::ascii($fullName));
    }
}
