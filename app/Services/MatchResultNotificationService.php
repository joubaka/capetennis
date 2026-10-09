<?php

namespace App\Services;

use App\Jobs\SendMatchResultNotification;
use App\Models\{Fixture, MatchResultNotification, Player, TeamFixture, TeamTie};
use Illuminate\Support\Facades\DB;

final class MatchResultNotificationService
{
    public function record(Fixture|TeamFixture $fixture): void
    {
        DB::transaction(function () use ($fixture): void {
            $fixture = $fixture->newQuery()->lockForUpdate()->findOrFail($fixture->id);
            // Serialize with Event Settings disabling/invalidation. Keep the
            // fresh locking read attached throughout snapshot creation, so an
            // earlier transaction read cannot resurrect the old enabled flag.
            $event = \App\Models\Event::whereKey($fixture->draw->event_id)->lockForUpdate()->firstOrFail();
            $snapshot = $this->snapshot($fixture, $event);
            if (!$snapshot) {
                if ($fixture->fixtureResults->isEmpty()) $this->invalidate($fixture);
                return;
            }
            $type = $fixture instanceof TeamFixture ? 'team' : 'individual';
            $previous = MatchResultNotification::where('fixture_type', $type)->where('fixture_id', $fixture->id)->latest('id')->first();
            $hash = hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR));
            if ($previous && !$previous->invalidated_at && hash_equals($previous->snapshot_hash, $hash)) return;
            $revision = ($previous?->revision ?? 0) + 1;
            foreach ($this->recipients($snapshot['player_ids'], $snapshot['imported_ids']) as $recipient) {
                $notification = MatchResultNotification::create([
                    'event_id' => $fixture->draw->event_id, 'fixture_type' => $type, 'fixture_id' => $fixture->id,
                    'revision' => $revision, 'snapshot_hash' => $hash, 'snapshot' => $snapshot, 'recipient' => $recipient,
                ]);
                // Use the durable queue even when the web application's default is sync.
                SendMatchResultNotification::dispatch($notification->id)->onConnection('database')
                    ->delay(now()->addMinutes(30))->afterCommit();
            }
        });
    }

    public function snapshot(Fixture|TeamFixture $fixture, ?\App\Models\Event $lockedEvent = null): ?array
    {
        $fixture->load('draw', 'fixtureResults');
        if ($lockedEvent) {
            if ((int) $fixture->draw?->event_id !== (int) $lockedEvent->id) return null;
            $fixture->draw->setRelation('event', $lockedEvent);
        } else {
            $fixture->draw?->load('event');
        }
        if (!$fixture->draw?->published || !$fixture->draw->event?->published || !$fixture->draw->event->result_notifications_enabled) return null;
        if ($fixture instanceof TeamFixture) {
            if (!TeamFixture::whereKey($fixture->id)->publishedTeamTies()->exists()) return null;
            if (!app(TeamRubberResultService::class)->outcome($fixture)['complete']) return null;
            $participants = $this->teamParticipants($fixture);
            if (!$participants) return null;
            [$players, $imported, $homeNames, $awayNames] = $participants;
            $sets = $fixture->fixtureResults->sortBy('set_nr')->unique('set_nr')->map(fn ($set) => [(int) $set->team1_score, (int) $set->team2_score])->values()->all();
        } else {
            $sets = $fixture->fixtureResults->sortBy('set_nr')->unique('set_nr')->map(fn ($set) => [(int) $set->registration1_score, (int) $set->registration2_score])->values()->all();
            if (!app(IndividualMatchOutcomeService::class)->winner($fixture)) return null;
            // Registrations must belong to this event, including doubles participants.
            foreach ([$fixture->registration1, $fixture->registration2] as $registration) {
                if (!$registration || !$registration->categoryEvents()->where('category_events.event_id', $fixture->draw->event_id)->exists()) return null;
            }
            $players = collect([$fixture->registration1, $fixture->registration2])->flatMap(fn ($registration) => $registration->players->pluck('id'))->unique()->sort()->values()->all();
            $imported = [];
            $homeNames = $fixture->registration1->players->pluck('full_name')->all();
            $awayNames = $fixture->registration2->players->pluck('full_name')->all();
        }
        if (!$players && !$imported) return null;
        return ['event' => $fixture->draw->event->name, 'draw' => $fixture->draw->drawName, 'draw_id' => $fixture->draw_id,
            'player_ids' => $players, 'imported_ids' => $imported, 'home_names' => $homeNames, 'away_names' => $awayNames,
            'sets' => $sets];
    }

    public function recipients(array $playerIds, array $importedIds = []): array
    {
        return Player::whereIn('id', $playerIds)->with(['users', 'user'])->get()
            ->flatMap(fn ($player) => app(\App\Services\TeamSelection\TeamSelectionContactService::class)->emails($player))
            ->concat(\App\Models\NoProfileTeamPlayer::whereIn('id', $importedIds)->with('profile.users', 'profile.user')->get()
                ->flatMap(fn ($imported) => $imported->profile ? app(\App\Services\TeamSelection\TeamSelectionContactService::class)->emails($imported->profile) : [$imported->email]))
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))->unique()->values()->all();
    }

    public function current(MatchResultNotification $notification): bool
    {
        if ($notification->invalidated_at) return false;
        $fixture = ($notification->fixture_type === 'team' ? TeamFixture::class : Fixture::class)::find($notification->fixture_id);
        if (!$fixture || (int) $fixture->draw?->event_id !== (int) $notification->event_id) return false;
        $snapshot = $this->snapshot($fixture);
        return $snapshot !== null && hash_equals($notification->snapshot_hash, hash('sha256', json_encode($snapshot, JSON_THROW_ON_ERROR)))
            && in_array($notification->recipient, $this->recipients($snapshot['player_ids'], $snapshot['imported_ids']), true)
            && !MatchResultNotification::where('fixture_type', $notification->fixture_type)->where('fixture_id', $notification->fixture_id)->where('revision', '>', $notification->revision)->exists();
    }

    public function linkedParentNames(MatchResultNotification $notification): array
    {
        $snapshot = $notification->snapshot;
        $profileIds = \App\Models\NoProfileTeamPlayer::whereIn('id', $snapshot['imported_ids'] ?? [])
            ->whereNotNull('player_profile')->pluck('player_profile');
        $recipient = mb_strtolower(trim($notification->recipient));

        return Player::whereIn('id', collect($snapshot['player_ids'])->merge($profileIds)->unique())
            ->with(['users', 'user'])->get()
            ->filter(function (Player $player) use ($recipient): bool {
                $playerEmails = collect([$player->email])
                    ->map(fn ($email) => mb_strtolower(trim((string) $email)));

                return ! $playerEmails->contains($recipient) && (
                    mb_strtolower(trim((string) $player->user?->email)) === $recipient
                    || $player->users->contains(fn ($user) => mb_strtolower(trim((string) $user->email)) === $recipient)
                );
            })->pluck('full_name')->unique()->values()->all();
    }

    public function invalidate(Fixture|TeamFixture $fixture): void
    {
        MatchResultNotification::where('fixture_type', $fixture instanceof TeamFixture ? 'team' : 'individual')
            ->where('fixture_id', $fixture->id)->whereNull('invalidated_at')->update(['invalidated_at' => now()]);
    }

    public function invalidateEvent(\App\Models\Event $event): void
    {
        // Disabling notifications revokes the existing queue permanently.
        // Re-enabling must not revive messages prepared before the switch-off.
        MatchResultNotification::where('event_id', $event->id)->whereNull('invalidated_at')
            ->whereIn('status', ['pending', 'sending'])->update(['invalidated_at' => now()]);
    }

    public function replyTo(MatchResultNotification $notification): array
    {
        return \App\Models\Event::findOrFail($notification->event_id)->admins()->pluck('users.email')
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))->unique()->sort()->values()->all();
    }

    private function teamParticipants(TeamFixture $fixture): ?array
    {
        $profiles = []; $importedIds = []; $names = [1 => [], 2 => []];
        foreach ($fixture->fixturePlayers()->with(['player1', 'player2', 'noProfile1', 'noProfile2'])->get() as $slot) {
            foreach ([1, 2] as $side) {
                $profile = $slot->{'player'.$side};
                $imported = $slot->{'noProfile'.$side};
                if ((!$profile && !$imported) || ($profile && $imported)) return null;
                if ($fixture->teamTie) {
                    $teamId = $fixture->teamTie->{$side === 1 ? 'home_team_id' : 'away_team_id'};
                    $mixed = $fixture->draw->team_draw_selection['mixed_sides'][$teamId] ?? null;
                    $sourceIds = $mixed ? [$mixed['boys'], $mixed['girls']] : [$teamId];
                    $teams = \App\Models\Team::whereIn('id', $sourceIds)->whereHas('category', fn ($query) => $query->where('event_id', $fixture->draw->event_id))->get();
                    if ($teams->count() !== count(array_unique($sourceIds))) return null;
                    $history = $slot->participant_snapshot[$side] ?? null;
                    $source = $history ? $teams->firstWhere('id', $history['source_team_id'] ?? null) : null;
                    $historic = $history && (int) ($history['event_id'] ?? 0) === (int) $fixture->draw->event_id
                        && in_array((int) ($history['source_team_id'] ?? 0), array_map('intval', $sourceIds), true)
                        && (int) ($history['category_event_id'] ?? 0) === (int) $source?->category_event_id
                        && ($history['profile_id'] ?? null) === ($profile ? (int) $profile->id : null)
                        && ($history['imported_id'] ?? null) === ($imported ? (int) $imported->id : null);
                    $member = $teams->contains(fn ($team) => $profile ? $team->team_players->contains('player_id', $profile->id) : $team->team_players_no_profile->contains('id', $imported->id));
                    if (!$historic && !$member) return null;
                } else {
                    if ($imported && (int) $imported->team?->category?->event_id !== (int) $fixture->draw->event_id) return null;
                    if ($profile && !\App\Models\TeamPlayer::where('player_id', $profile->id)
                        ->whereHas('team.category', fn ($query) => $query->where('event_id', $fixture->draw->event_id))->exists()
                        && !\App\Models\NoProfileTeamPlayer::where('player_profile', $profile->id)
                            ->whereHas('team.category', fn ($query) => $query->where('event_id', $fixture->draw->event_id))->exists()) return null;
                }
                if ($profile) $profiles[] = $profile->id; else $importedIds[] = $imported->id;
                $names[$side][] = $profile?->full_name ?? trim($imported->name.' '.$imported->surname);
            }
        }
        sort($profiles); sort($importedIds);
        return [array_values(array_unique($profiles)), array_values(array_unique($importedIds)), $names[1], $names[2]];
    }
}
