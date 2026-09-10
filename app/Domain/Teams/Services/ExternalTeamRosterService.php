<?php

namespace App\Domain\Teams\Services;

use App\Models\Event;
use App\Models\NoProfileTeamPlayer;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamSelectionInvitation;
use App\Models\User;
use App\Services\PlayerEligibilityService;
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
        ?string $verifiedDateOfBirth = null,
        array $verifiedContacts = [],
    ): void
    {
        $this->assertClaimAvailable($event, $team, $slot);
        $this->assertPlayerMatchesRosterSlot($slot, $player);

        if (! $this->userOwnsPlayer($user, $player) && ! $user->can('event.manage', $event)) {
            $this->assertExistingProfileVerification($player, $verifiedDateOfBirth, $verifiedContacts);
        }

        $this->playerEligibility->assertEligible($player, $event);

        DB::transaction(function () use ($user, $event, $team, $slot, $player): void {
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
        });
    }

    public function assertCanRegister(User $user, Event $event, Team $team, Player $player): TeamPlayer
    {
        $teamPlayer = $this->assertSelectedPlayerCanRegister($event, $team, $player);
        if (! $this->userOwnsPlayer($user, $player)
            && ! $user->can('event.manage', $event)
            && ! $this->hasAcceptedSelectionInvitation($user, $event, $team, $player)) {
            throw ValidationException::withMessages(['player' => 'You may only register a player linked to your account.']);
        }

        return $teamPlayer;
    }

    public function assertSelectedPlayerCanRegister(Event $event, Team $team, Player $player): TeamPlayer
    {
        $this->assertTeamBelongsToEvent($team, $event);

        if (! $team->published) throw ValidationException::withMessages(['team' => 'This team has not been published yet.']);
        if (! $this->registrationIsOpen($event)) throw ValidationException::withMessages(['event' => 'Registration for this event is closed.']);

        $teamPlayer = TeamPlayer::where('team_id', $team->id)->where('player_id', $player->id)->first();
        if (! $teamPlayer) throw ValidationException::withMessages(['player' => 'This player is not on the selected team roster.']);
        if ((int) $teamPlayer->pay_status === 1) throw ValidationException::withMessages(['player' => 'This player is already registered.']);

        $this->playerEligibility->assertEligible($player, $event);

        return $teamPlayer;
    }

    private function hasAcceptedSelectionInvitation(User $user, Event $event, Team $team, Player $player): bool
    {
        return TeamSelectionInvitation::query()
            ->where('event_id', $event->id)
            ->where('team_id', $team->id)
            ->where('player_id', $player->id)
            ->where('status', TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT)
            ->where(function ($query) use ($user) {
                $query->whereNull('order_id')
                    ->orWhereHas('order', fn ($order) => $order->where('user_id', $user->id));
            })
            ->exists();
    }

    public function registrationIsOpen(Event $event): bool
    {
        if (! $event->published || ! $event->signUp || in_array($event->status, ['closed', 'draft'], true)) return false;
        $closesAt = $event->registrationClosesAt();

        return ! $closesAt || now()->lte($closesAt->endOfDay());
    }

    public function userOwnsPlayer(User $user, Player $player): bool
    {
        return (int) $player->userId === (int) $user->id || $player->users()->whereKey($user->id)->exists();
    }

    private function assertPlayerMatchesRosterSlot(NoProfileTeamPlayer $slot, Player $player): void
    {
        if ($this->identityName((string) $slot->name, (string) $slot->surname)
            !== $this->identityName((string) $player->name, (string) $player->surname)) {
            throw ValidationException::withMessages([
                'player_id' => 'The selected profile name does not match this roster player.',
            ]);
        }

        $slotDateOfBirth = $slot->date_of_birth?->format('Y-m-d');
        $playerDateOfBirth = substr((string) $player->dateOfBirth, 0, 10);
        if ($slotDateOfBirth && $slotDateOfBirth !== $playerDateOfBirth) {
            throw ValidationException::withMessages([
                'player_id' => 'The selected profile date of birth does not match this roster player.',
            ]);
        }
    }

    private function assertExistingProfileVerification(
        Player $player,
        ?string $dateOfBirth,
        array $contacts,
    ): void {
        $dateOfBirthMatches = $dateOfBirth
            && substr((string) $player->dateOfBirth, 0, 10) === substr($dateOfBirth, 0, 10);
        $knownContacts = collect([$player->email, $player->cellNr])
            ->filter()
            ->map(fn ($contact): string => $this->normalizeContact((string) $contact));
        $contactMatches = collect($contacts)
            ->filter(fn ($contact): bool => trim((string) $contact) !== '')
            ->map(fn ($contact): string => $this->normalizeContact((string) $contact))
            ->contains(fn (string $contact): bool => $contact !== '' && $knownContacts->contains($contact));

        if (! $dateOfBirthMatches || ! $contactMatches) {
            throw ValidationException::withMessages([
                'player_id' => 'To link this existing profile, enter the player date of birth and the email address or mobile number already recorded on that profile.',
            ]);
        }
    }

    private function normalizeContact(string $contact): string
    {
        return mb_strtolower((string) preg_replace('/[^a-z0-9+@.]/i', '', trim($contact)));
    }

    private function identityName(string $name, string $surname): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name.' '.$surname)));
    }
}
