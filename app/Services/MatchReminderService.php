<?php

namespace App\Services;

use App\Models\{Event, Fixture, Team, TeamFixture, TeamTie, User};
use App\Services\Scheduling\SchedulePublicationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Read-only reminders from the public schedule, never working schedule values. */
final class MatchReminderService
{
    public function for(User $user): array
    {
        $today = CarbonImmutable::now('Africa/Johannesburg')->startOfDay();
        $end = $today->addDays(2);
        $players = app(MyTennisService::class)->playersFor($user)->keyBy('id');
        $groups = [];
        if ($players->isEmpty()) return ['day' => $today->toDateString(), 'players' => []];

        $teamEventIds = Team::query()->where(fn ($q) => $q
            ->whereHas('team_players', fn ($members) => $members->whereIn('player_id', $players->keys()))
            ->orWhereHas('competitionSubstitutions', fn ($subs) => $subs->where('details->new_type', 'profile')
                ->whereIn('details->new_identity_id', $players->keys())))
            ->join('category_events', 'category_events.id', '=', 'teams.category_event_id')
            ->distinct()->pluck('category_events.event_id');
        $individualEventIds = DB::table('player_registrations')
            ->join('category_event_registrations', 'category_event_registrations.registration_id', '=', 'player_registrations.registration_id')
            ->join('category_events', 'category_events.id', '=', 'category_event_registrations.category_event_id')
            ->whereIn('player_registrations.player_id', $players->keys())->distinct()->pluck('category_events.event_id');
        // Legacy team pairings may have direct players rather than team rosters.
        $legacyEventIds = TeamFixture::query()->whereNull('team_tie_id')
            ->where(fn ($q) => $q->whereHas('team1', fn ($p) => $p->whereIn('players.id', $players->keys()))
                ->orWhereHas('team2', fn ($p) => $p->whereIn('players.id', $players->keys())))
            ->join('draws', 'draws.id', '=', 'team_fixtures.draw_id')->distinct()->pluck('draws.event_id');
        $eventIds = DB::table('published_schedule_assignments')
            ->whereIn('event_id', $teamEventIds->concat($individualEventIds)->concat($legacyEventIds)->unique())
            ->where('scheduled_at', '>=', $today->toDateTimeString())
            ->where('scheduled_at', '<', $end->toDateTimeString())->distinct()->pluck('event_id');
        $events = Event::query()->visibleTo(null)->whereIn('id', $eventIds)->get();

        foreach ($events as $event) {
            // Canonical projection also applies first-match-only publication rules.
            $rows = app(SchedulePublicationService::class)->publishedRows($event)
                ->filter(fn ($row) => $row['scheduled_at'] >= $today->toDateTimeString()
                    && $row['scheduled_at'] < $end->toDateTimeString());
            $individual = Fixture::with(['draw', 'registration1.players', 'registration2.players'])
                ->whereIn('id', $rows->where('fixture_kind', 'individual')->pluck('fixture_id'))
                ->where(fn ($q) => $q
                    ->whereHas('registration1.players', fn ($p) => $p->whereIn('players.id', $players->keys()))
                    ->orWhereHas('registration2.players', fn ($p) => $p->whereIn('players.id', $players->keys())))
                ->whereHas('draw', fn ($q) => $q->where('event_id', $event->id))
                ->whereDoesntHave('fixtureResults')->where('match_status', 0)->get()->keyBy('id');
            $team = TeamFixture::with(['teamTie.homeTeam.category', 'teamTie.awayTeam.category', 'team1', 'team2'])
                ->publicDrawFixtures()->whereIn('id', $rows->where('fixture_kind', 'team')->pluck('fixture_id'))
                ->whereHas('draw', fn ($q) => $q->where('event_id', $event->id))
                ->whereDoesntHave('fixtureResults')->where('match_status', 0)
                ->whereDoesntHave('teamTie', fn ($q) => $q->where('status', TeamTie::STATUS_COMPLETED))
                ->get()->keyBy('id');
            foreach ($rows as $row) {
                $fixture = ($row['fixture_kind'] === 'individual' ? $individual : $team)->get($row['fixture_id']);
                if (! $fixture || (int) $fixture->draw_id !== (int) $row['draw_id']) continue;
                $members = collect();
                $isTeam = $fixture instanceof TeamFixture && $fixture->teamTie;
                if ($fixture instanceof Fixture) {
                    $activeMatch = true;
                    foreach ([$fixture->registration1, $fixture->registration2] as $registration) {
                        if (! $registration) { $activeMatch = false; break; }
                        $active = $registration->categoryEventRegistrations()->active()->whereNull('withdrawn_at')
                            ->where('category_event_id', $fixture->draw->category_event_id)
                            ->whereHas('categoryEvent', fn ($q) => $q->where('event_id', $event->id))->exists();
                        if (! $active) { $activeMatch = false; break; }
                        $members = $members->concat($registration->players->pluck('id'));
                    }
                    if (! $activeMatch) continue;
                } elseif ($isTeam && $teamEventIds->contains($event->id)) {
                    foreach ([$fixture->teamTie->homeTeam, $fixture->teamTie->awayTeam] as $side) {
                        if (! $side || (int) $side->category?->event_id !== (int) $event->id) continue;
                        try {
                            $roster = app(TeamDrawSideResolver::class)->side($fixture->draw, $side, $fixture->teamTie->round_nr, $fixture->id);
                            $members = $members->concat($roster?->team_players->pluck('player_id') ?? []);
                        } catch (\InvalidArgumentException) {
                            continue;
                        }
                    }
                } elseif (! $isTeam) {
                    $members = $fixture->team1->pluck('id')->concat($fixture->team2->pluck('id'));
                }
                foreach ($members->unique()->intersect($players->keys()) as $playerId) {
                    $key = $isTeam ? 'tie:'.$fixture->team_tie_id : $row['fixture_key'];
                    $time = CarbonImmutable::parse($row['scheduled_at'], 'Africa/Johannesburg');
                    $match = ['key' => $key, 'kind' => $isTeam ? 'team' : 'individual',
                        'label' => $isTeam ? 'Your team plays' : 'Your match', 'event' => $event->name,
                        'draw' => $row['draw_name'], 'participants' => $row['participants'],
                        'day' => $time->isSameDay($today) ? 'Today' : 'Tomorrow',
                        'date' => $time->format('D j M'), 'time' => $time->format('H:i'),
                        'scheduled_at' => $row['scheduled_at'], 'venue' => $row['venue_name'] ?: 'Venue to be confirmed',
                        'court' => $row['court'] ?: null, 'url' => route($fixture instanceof TeamFixture ? 'frontend.fixtures.show' : 'frontend.showDraw', $row['draw_id'])];
                    $groups[$playerId] ??= ['name' => $players[$playerId]->full_name, 'matches' => []];
                    // A tie may have several rubber slots; show its earliest scheduled start.
                    if (! isset($groups[$playerId]['matches'][$key])
                        || $match['scheduled_at'] < $groups[$playerId]['matches'][$key]['scheduled_at']) {
                        $groups[$playerId]['matches'][$key] = $match;
                    }
                }
            }
        }
        foreach ($groups as &$group) $group['matches'] = collect($group['matches'])->sortBy('scheduled_at')->values()->all();
        unset($group);
        return ['day' => $today->toDateString(), 'players' => array_values($groups)];
    }
}
