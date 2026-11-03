<?php

namespace App\Services;

use App\Jobs\SendPlusPublicationNotification;
use App\Models\{Draw, Event, Fixture, Player, PlusPublicationDelivery, PlusPublicationState, TeamFixture};
use App\Services\Performance\{PlayerPlusEntitlement, PlayerPlusService};
use App\Services\Scheduling\SchedulePublicationService;
use App\Services\TeamSelection\TeamSelectionContactService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{DB, Schema};

/** Publication-only notifications. Working edits and historical publications never seed a campaign. */
final class PlusPublicationNotificationService
{
    public function ready(): bool
    {
        return Schema::hasTable('plus_publication_states') && Schema::hasTable('plus_publication_deliveries');
    }

    public function record(Draw $draw, bool $trackedOnly = false, bool $baseline = false): void
    {
        if (! $this->ready()) return;
        DB::transaction(function () use ($draw, $trackedOnly, $baseline): void {
            $event = Event::whereKey($draw->event_id)->lockForUpdate()->first();
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->first();
            if (! $event || ! $draw || (int) $draw->event_id !== (int) $event->id) return;
            $state = PlusPublicationState::where('draw_id', $draw->id)->lockForUpdate()->first();
            if ($trackedOnly && ! $state) return;
            $snapshot = $this->snapshot($draw, $event);
            if ($baseline) {
                if (! $state) PlusPublicationState::create(['draw_id' => $draw->id, 'event_id' => $event->id,
                    'generation' => 0, 'snapshot' => $snapshot, 'changes' => ['draw' => false, 'fixtures' => []], 'prepared_at' => now()]);
                return;
            }
            if ($state && $state->snapshot === $snapshot) return;
            $before = $state?->snapshot ?? ['visible' => false, 'draw' => [], 'schedule' => []];
            $changes = $state && ! $state->prepared_at ? $state->changes : ['draw' => false, 'fixtures' => []];
            $visible = $snapshot['visible'];
            if ($visible) {
                // A worker backlog must not erase unsent notices for unaffected players.
                // Inherit only pending recipients, never already accepted/ambiguous messages.
                $changes['carry'] ??= [];
                if ($state) foreach (PlusPublicationDelivery::where('state_id', $state->id)->where('status', 'pending')->get() as $pending) {
                    $changes['carry'][$pending->player_id.'|'.$pending->recipient] = [
                        'player_id' => (int) $pending->player_id, 'recipient' => $pending->recipient, 'changes' => $pending->changes,
                    ];
                }
                $changes['draw'] = $changes['draw'] || ! $before['visible'] || $before['draw'] !== $snapshot['draw'];
                $changes['schedule_initial'] = ($changes['schedule_initial'] ?? false) || (! $before['schedule'] && (bool) $snapshot['schedule']);
                $keys = array_unique(array_merge(array_keys($before['schedule']), array_keys($snapshot['schedule'])));
                foreach ($keys as $key) {
                    if (($before['schedule'][$key] ?? null) !== ($snapshot['schedule'][$key] ?? null)) $changes['fixtures'][] = $key;
                }
                $changes['fixtures'] = array_values(array_unique($changes['fixtures']));
                // Hidden slots have no public destination. Never announce their availability.
                $changes['fixtures'] = array_values(array_intersect($changes['fixtures'], array_keys($snapshot['schedule'])));
            } else {
                // Hidden content invalidates every old generation permanently.
                $changes = ['draw' => false, 'fixtures' => []];
            }
            $state ??= new PlusPublicationState(['draw_id' => $draw->id, 'event_id' => $event->id, 'generation' => 0]);
            $state->fill(['generation' => $state->generation + 1, 'snapshot' => $snapshot, 'changes' => $changes,
                'due_at' => $visible && ($changes['draw'] || $changes['fixtures'] || ! empty($changes['carry'])) ? now()->addMinutes(5) : null,
                'prepared_at' => null])->save();
            PlusPublicationDelivery::where('state_id', $state->id)->where('status', 'pending')->update(['status' => 'superseded']);
        }, 3);
    }

    public function eventVisibilityChanged(Event $event): void
    {
        if (! $this->ready()) return;
        Draw::whereIn('id', PlusPublicationState::where('event_id', $event->id)->select('draw_id'))
            ->chunkById(50, fn ($draws) => $draws->each(fn ($draw) => $this->record($draw, true)));
    }

    public function snapshot(Draw $draw, Event $event): array
    {
        request()->attributes->remove('published_schedule_rows_'.$event->id.'_timing');
        $visible = (bool) $draw->published && app(PublicTournamentVisibility::class)->eventIsVisible($event);
        if (! $visible) return ['visible' => false, 'draw' => [], 'schedule' => []];
        $schedule = app(SchedulePublicationService::class)->publishedRows($event, false)->where('draw_id', $draw->id)
            ->mapWithKeys(fn ($row) => [$row['fixture_key'] => array_intersect_key($row, array_flip([
                'fixture_kind', 'fixture_id', 'draw_id', 'scheduled_at', 'venue_id', 'court', 'duration',
            ]))])->sortKeys()->all();
        return ['visible' => true, 'draw' => [
            'registrations' => $draw->registrations()->orderBy('registrations.id')->pluck('registrations.id')->all(),
            'fixtures' => Fixture::where('draw_id', $draw->id)->orderBy('id')->get(['id', 'registration1_id', 'registration2_id', 'round', 'match_nr'])->toArray(),
            'teams' => $draw->teams_in_draw()->orderBy('teams.id')->pluck('teams.id')->all(),
            'team_fixtures' => TeamFixture::without(['draw', 'fixturePlayers', 'teamResults'])->where('draw_id', $draw->id)
                ->orderBy('id')->get(['id', 'team_tie_id', 'round_nr', 'match_nr'])->toArray(),
            'team_ties' => $draw->teamTies()->orderBy('id')->get(['id', 'home_team_id', 'away_team_id'])->toArray(),
            'legacy_team_players' => \App\Models\TeamFixturePlayer::whereIn('team_fixture_id', TeamFixture::where('draw_id', $draw->id)->whereNull('team_tie_id')->select('id'))
                ->orderBy('id')->get(['id', 'team_fixture_id', 'team1_id', 'team2_id', 'team1_no_profile_id', 'team2_no_profile_id'])->toArray(),
        ], 'schedule' => $schedule];
    }

    /** Active participants only. Team publication is a team-roster notice, not an assignment promise. */
    public function participants(Draw $draw, ?array $fixtureKeys = null): Collection
    {
        $ids = collect();
        $registrations = $draw->registrations()->with('players')->get();
        if ($fixtureKeys === null) {
            $matches = Fixture::where('draw_id', $draw->id)->get(['registration1_id', 'registration2_id']);
            $registrations = $registrations->concat(\App\Models\Registration::whereIn('id', $matches->pluck('registration1_id')
                ->concat($matches->pluck('registration2_id'))->filter()->unique())->with('players')->get())->unique('id');
        }
        if ($fixtureKeys !== null) {
            $fixtureIds = collect($fixtureKeys)->filter(fn ($key) => str_starts_with($key, 'individual:'))->map(fn ($key) => (int) substr($key, 11));
            $matches = Fixture::where('draw_id', $draw->id)->whereIn('id', $fixtureIds)->get();
            $registrationIds = $matches->pluck('registration1_id')->concat($matches->pluck('registration2_id'))->filter()->unique();
            $registrations = \App\Models\Registration::whereIn('id', $registrationIds)->with('players')->get();
        }
        foreach ($registrations as $registration) {
            if ($registration->categoryEventRegistrations()->active()->whereNull('withdrawn_at')
                ->where('category_event_id', $draw->category_event_id)
                ->whereHas('categoryEvent', fn ($q) => $q->where('event_id', $draw->event_id))->exists()) {
                $ids = $ids->concat($registration->players->pluck('id'));
            }
        }
        $teamFixtures = TeamFixture::where('draw_id', $draw->id)->publicDrawFixtures()
            ->when($fixtureKeys !== null, fn ($q) => $q->whereIn('id', collect($fixtureKeys)
                ->filter(fn ($key) => str_starts_with($key, 'team:'))->map(fn ($key) => (int) substr($key, 5))))->get();
        $sides = $fixtureKeys === null ? $draw->teams_in_draw->map(fn ($team) => [$team, null, null]) : collect();
        foreach ($teamFixtures as $fixture) {
            if ($fixture->teamTie) {
                foreach ([$fixture->teamTie->homeTeam, $fixture->teamTie->awayTeam] as $team) $sides->push([$team, $fixture->round_nr, $fixture->id]);
            } else {
                // Legacy direct-player fixtures still require an active roster in this event.
                $legacy = $fixture->team1->pluck('id')->concat($fixture->team2->pluck('id'));
                $importedIds = $fixture->fixturePlayers->pluck('team1_no_profile_id')->concat($fixture->fixturePlayers->pluck('team2_no_profile_id'))->filter();
                $sourceTeams = \App\Models\Team::whereHas('category', fn ($q) => $q->where('event_id', $draw->event_id))
                    ->where(fn ($q) => $q->whereHas('team_players', fn ($members) => $members->whereIn('player_id', $legacy))
                        ->orWhereHas('team_players_no_profile', fn ($members) => $members->whereIn('id', $importedIds)))->get();
                foreach ($sourceTeams as $sourceTeam) {
                    $roster = app(TeamDrawSideResolver::class)->activeRoster($sourceTeam, $fixture->round_nr, $fixture->id, $draw->id);
                    $anchored = fn ($member) => ($member->competition_anchor_type === 'profile' && $legacy->contains((int) $member->competition_anchor_id))
                        || ($member->competition_anchor_type === 'imported' && $importedIds->contains((int) $member->competition_anchor_id));
                    $ids = $ids->concat($roster->team_players->filter(fn ($member) => $legacy->contains((int) $member->player_id) || $anchored($member))->pluck('player_id'))
                        ->concat($roster->team_players_no_profile->filter(fn ($member) => $importedIds->contains((int) $member->id) || $anchored($member))->pluck('player_profile')->filter());
                }
            }
        }
        foreach ($sides as [$team, $round, $fixtureId]) {
            if (! $team || (int) $team->category?->event_id !== (int) $draw->event_id) continue;
            try {
                $side = app(TeamDrawSideResolver::class)->side($draw, $team, $round, $fixtureId);
                $ids = $ids->concat($side?->team_players->pluck('player_id') ?? [])
                    ->concat($side?->team_players_no_profile->pluck('player_profile')->filter() ?? []);
            } catch (\InvalidArgumentException) {
                // Invalid source relationships cannot produce an audience.
            }
        }
        return $ids->map(fn ($id) => (int) $id)->unique()->sort()->values();
    }

    /** Bounded due processing; no transport is invoked here. */
    public function prepareDue(int $limit = 50): int
    {
        if (! config('publication_notifications.delivery_enabled') || ! $this->ready() || ! PlayerPlusService::ready()) return 0;
        $count = 0;
        foreach (PlusPublicationState::whereNotNull('due_at')->where('due_at', '<=', now())->whereNull('prepared_at')->orderBy('id')->limit($limit)->pluck('id') as $id) {
            $count += DB::transaction(function () use ($id): int {
                $candidate = PlusPublicationState::find($id);
                if (! $candidate) return 0;
                Event::whereKey($candidate->event_id)->lockForUpdate()->first();
                $state = PlusPublicationState::lockForUpdate()->find($id);
                if (! $state || $state->prepared_at || ! $state->due_at || $state->due_at->isFuture()) return 0;
                $draw = Draw::find($state->draw_id);
                if (! $draw || (int) $draw->event_id !== (int) $state->event_id || $this->snapshot($draw, $draw->event) !== $state->snapshot) {
                    if ($draw && (int) $draw->event_id === (int) $state->event_id) $this->record($draw, trackedOnly: true);
                    else $state->update(['due_at' => null]);
                    return 0;
                }
                $ids = $this->participants($draw, $state->changes['draw'] ? null : $state->changes['fixtures']);
                $ids = $ids->concat(collect($state->changes['carry'] ?? [])->pluck('player_id'))->unique();
                $created = 0;
                app(PlayerPlusEntitlement::class)->activePlayers()->whereIn('players.id', $ids)->with(['user', 'users'])->chunkById(50, function ($players) use ($state, $draw, &$created): void {
                    foreach ($players as $player) foreach (app(TeamSelectionContactService::class)->emails($player) as $recipient) {
                        $notices = $this->noticesFor($state, $draw, (int) $player->id, $recipient);
                        if (! $notices['draw'] && ! $notices['fixtures']) continue;
                        $key = hash('sha256', implode('|', [$state->id, $state->generation, $player->id, $recipient]));
                        $delivery = PlusPublicationDelivery::firstOrCreate(['deduplication_key' => $key], [
                            'state_id' => $state->id, 'generation' => $state->generation, 'player_id' => $player->id, 'recipient' => $recipient, 'changes' => $notices,
                        ]);
                        if (! $delivery->wasRecentlyCreated) continue;
                        // The database queue shares this transaction: rollback removes both reservation and job.
                        if (config('queue.connections.database.connection') && config('queue.connections.database.connection') !== DB::getDefaultConnection()) {
                            throw new \LogicException('Publication notifications require the database queue on the application database.');
                        }
                        \Illuminate\Support\Facades\Bus::dispatch((new SendPlusPublicationNotification($delivery->id))->onConnection('database')->beforeCommit());
                        $created++;
                    }
                });
                $state->update(['prepared_at' => now()]);
                return $created;
            }, 3);
        }
        return $count;
    }

    public function current(PlusPublicationDelivery $delivery): bool
    {
        if (! config('publication_notifications.delivery_enabled') || ! PlayerPlusService::ready()) return false;
        $state = PlusPublicationState::find($delivery->state_id);
        $draw = $state ? Draw::find($state->draw_id) : null;
        if (! $draw || (int) $draw->event_id !== (int) $state->event_id || (int) $state->generation !== (int) $delivery->generation
            || ! $state->due_at || $state->due_at->isFuture() || ! $state->prepared_at
            || $this->snapshot($draw, $draw->event) !== $state->snapshot) return false;
        $player = app(PlayerPlusEntitlement::class)->activePlayers()->whereKey($delivery->player_id)->with(['user', 'users'])->first();
        $notices = $this->noticesFor($state, $draw, (int) $delivery->player_id, $delivery->recipient);
        return $player && ($notices['draw'] || $notices['fixtures']) && $notices === $delivery->changes
            && app(TeamSelectionContactService::class)->emails($player)->contains($delivery->recipient);
    }

    private function noticesFor(PlusPublicationState $state, Draw $draw, int $playerId, string $recipient): array
    {
        $carry = $state->changes['carry'][$playerId.'|'.$recipient]['changes'] ?? [];
        $drawNotice = ($state->changes['draw'] || ($carry['draw'] ?? false)) && $this->participants($draw)->contains($playerId);
        $keys = array_unique(array_merge($state->changes['fixtures'], $carry['fixtures'] ?? []));
        $keys = array_values(array_filter($keys, fn ($key) => isset($state->snapshot['schedule'][$key])
            && $this->participants($draw, [$key])->contains($playerId)));
        sort($keys);
        return ['draw' => $drawNotice, 'fixtures' => $keys,
            'schedule_initial' => (bool) (($state->changes['schedule_initial'] ?? false) || ($carry['schedule_initial'] ?? false))];
    }
}
