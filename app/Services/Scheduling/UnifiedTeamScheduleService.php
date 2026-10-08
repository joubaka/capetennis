<?php

namespace App\Services\Scheduling;

use App\Domain\Draws\Services\ScheduleAvailability;
use App\Models\{Draw, DrawAuditLog, Event, OrderOfPlay, TeamFixture, Venue};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Team storage adapter for the shared event calendar; never writes individual bookings. */
final class UnifiedTeamScheduleService
{
    public function participants(TeamFixture $fixture): array
    {
        $ids = [];
        foreach ($fixture->fixturePlayers as $slot) {
            foreach ([1, 2] as $side) {
                if ($id = $slot->getAttribute('team'.$side.'_id')) $ids[] = 'profile:'.$id;
                elseif ($id = $slot->getAttribute('team'.$side.'_no_profile_id')) {
                    $profile = $slot->getRelationValue('noProfile'.$side)?->player_profile;
                    $ids[] = $profile ? 'profile:'.$profile : 'no-profile:'.$id;
                }
            }
        }
        return array_values(array_unique($ids));
    }

    public function protected(TeamFixture $fixture): bool
    {
        return $fixture->fixtureResults->isNotEmpty() || (int) $fixture->match_status !== 0
            || $fixture->teamTie?->isCompleted();
    }

    public function slot(TeamFixture $fixture): ?OrderOfPlay
    {
        if (! $fixture->scheduled_at) return null;
        return new OrderOfPlay(['time' => $fixture->scheduled_at, 'venue_id' => $fixture->venue_id,
            'court' => ScheduleAvailability::courtKey((string) $fixture->court_label),
            'duration_minutes' => $fixture->duration_min ?: 120, 'gap_minutes' => (int) ($fixture->gap_minutes ?? 0)]);
    }

    public function nodes(Draw $draw, string $progression): array
    {
        $fixtures = TeamFixture::with(['fixturePlayers.noProfile1', 'fixturePlayers.noProfile2',
            'teamTie.homeTeam', 'teamTie.awayTeam', 'region1Name', 'region2Name', 'fixtureResults'])
            ->where('draw_id', $draw->id)->orderBy('round_nr')->orderBy('tie_nr')->orderBy('rubber_sequence')->orderBy('id')->get();
        $nodes = [];
        foreach ($fixtures as $fixture) {
            $teams = array_filter([$fixture->teamTie?->home_team_id, $fixture->teamTie?->away_team_id]);
            $regions = array_filter([$fixture->region1, $fixture->region2]);
            $dependencies = $fixtures->filter(function ($earlier) use ($fixture, $teams, $regions, $progression) {
                if ((int) $earlier->round_nr >= (int) $fixture->round_nr) return false;
                if ($progression === 'all_round') return true;
                return (bool) array_intersect($teams, array_filter([$earlier->teamTie?->home_team_id, $earlier->teamTie?->away_team_id]))
                    || (bool) array_intersect($regions, array_filter([$earlier->region1, $earlier->region2]));
            })->map(fn ($earlier) => 'team:'.$earlier->id)->values()->all();
            $home = $fixture->teamTie?->home_side_name ?: $fixture->region1Name?->name ?: 'Home team';
            $away = $fixture->teamTie?->away_side_name ?: $fixture->region2Name?->name ?: 'Away team';
            $nodes['team:'.$fixture->id] = [
                'fixture' => $fixture, 'fixture_kind' => 'team', 'draw_id' => $draw->id, 'draw_name' => $draw->drawName,
                'stage' => $fixture->rubber_name ?: $fixture->rubber_code ?: 'Team rubber',
                'round' => max(1, (int) $fixture->round_nr), 'match' => $fixture->match_nr ?: $fixture->rubber_sequence,
                'rank' => app(TeamFixtureOrder::class)->rank($fixture),
                'play_order' => (int) ($fixture->rubber_sequence ?: $fixture->match_nr ?: $fixture->id),
                'dependencies' => $dependencies, 'participants' => $this->participants($fixture),
                'participant_names' => [$home, $away], 'participant_group' => null,
                'automatic' => false, 'played' => $this->protected($fixture),
            ];
        }
        return $nodes;
    }

    public function manualError(Event $event, TeamFixture $fixture, array $data): ?string
    {
        if ((int) $fixture->draw?->event_id !== (int) $event->id) return 'This rubber does not belong to the event.';
        if ($fixture->draw->locked) return 'The draw is locked.';
        if ($this->protected($fixture)) return 'A rubber with play or results cannot be rescheduled.';
        $venueId = (int) ($data['venue_id'] ?? 0);
        $draft = json_decode((string) DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true) ?: [];
        $roundSetup = collect($draft['round_venue_setups'] ?? [])->first(fn ($row) => (int) $row['draw_id'] === (int) $fixture->draw_id && (int) $row['round'] === (int) $fixture->round_nr);
        $venue = $roundSetup
            ? (in_array($venueId, array_map('intval', $roundSetup['venue_ids']), true) ? \App\Models\Venue::find($venueId) : null)
            : $fixture->draw->venues()->where('venues.id', $venueId)->first();
        if (! $venue) return 'Select a venue assigned to this draw.';
        $court = ScheduleAvailability::courtKey((string) ($data['court'] ?? $data['court_label'] ?? ''));
        $labels = DB::table('event_venue_courts')->where('event_id', $event->id)->where('venue_id', $venueId)
            ->where('active', true)->pluck('label')->map(fn ($label) => ScheduleAvailability::courtKey((string) $label))->all();
        if (! $labels && ! DB::table('event_venue_courts')->where('event_id', $event->id)->where('venue_id', $venueId)->exists()) {
            $courtCount = max((int) ($venue->pivot?->num_courts ?? 0), (int) DB::table('event_venues')->where('event_id', $event->id)->where('venue_id', $venueId)->value('num_courts'));
            $labels = $courtCount > 0 ? array_map('strval', range(1, $courtCount)) : [];
        }
        $allocated = DB::table('draw_venue_court_allocations')->where('draw_id', $fixture->draw_id)
            ->where('venue_id', $venueId)->pluck('court_label')->map(fn ($label) => ScheduleAvailability::courtKey((string) $label))->all();
        if ($roundSetup) $allocated = collect($roundSetup['court_allocations'])->where('venue_id', $venueId)->flatMap(fn ($allocation) => $allocation['court_labels'])->map(fn ($label) => ScheduleAvailability::courtKey((string) $label))->all();
        if (! in_array($court, $labels, true) || ($allocated && ! in_array($court, $allocated, true))) return 'Select a permitted court for this draw.';
        $time = $data['scheduled_at'] ?? $data['start'] ?? null;
        if (! $time) return 'Choose a start time.';
        $start = Carbon::parse($time);
        $duration = (int) ($data['duration'] ?? $data['duration_min'] ?? 75);
        $rest = (int) ($data['player_rest'] ?? 0);
        $gap = (int) ($data['court_gap'] ?? $fixture->gap_minutes ?? 0);
        $progression = $data['round_progression'] ?? 'team_ready';
        if (! in_array($progression, ['team_ready', 'all_round'], true)) return 'Choose a valid round progression rule.';
        $nodes = $this->nodes($fixture->draw, $progression);
        $node = $nodes['team:'.$fixture->id] ?? null;
        foreach ($node['dependencies'] ?? [] as $key) {
            $earlier = TeamFixture::find((int) substr($key, 5));
            if (! $earlier?->scheduled_at) return 'Schedule the preceding team tie before this rubber.';
            if (Carbon::parse($earlier->scheduled_at)->addMinutes((int) ($earlier->duration_min ?: 120) + $rest)->gt($start)) {
                return 'This rubber starts before the preceding team tie and required rest finish.';
            }
        }
        foreach (($data['adaptation'] ?? false) ? [] : $nodes as $later) {
            if (! in_array('team:'.$fixture->id, $later['dependencies'], true) || ! $later['fixture']->scheduled_at) continue;
            if ($start->copy()->addMinutes($duration + $rest)->gt(Carbon::parse($later['fixture']->scheduled_at))) {
                return 'This rubber and required rest finish after a saved later team tie starts.';
            }
        }
        $ids = $this->participants($fixture);
        $calendar = ScheduleAvailability::load([$venueId], $ids, [], null, $rest, [$fixture->id]);
        if ($calendar->nextAvailableForMatch($start, $duration + $gap, $duration + $rest, $venueId, $court, $ids)->gt($start)) {
            return 'Schedule conflict: the court or a participant is already booked during this time.';
        }
        return null;
    }

    public function warnings(TeamFixture $fixture, array $data): array
    {
        $at = Carbon::parse($data['scheduled_at'] ?? $data['start']);
        $ids = $this->participants($fixture);
        $calendar = ScheduleAvailability::load([(int) $data['venue_id']], $ids, [], null, 0, [$fixture->id], $fixture->draw?->event, $at);
        return array_merge(app(RankVenuePreferences::class)->manualWarnings($fixture, (int) $data['venue_id']), array_map(function ($change) {
            [$kind, $id] = explode(':', $change['participant_id'], 2);
            $player = $kind === 'profile' ? \App\Models\Player::find($id) : \App\Models\NoProfileTeamPlayer::find($id);
            $name = $player ? trim($player->name.' '.$player->surname) : 'Player '.$id;
            $from = Venue::find($change['from_venue_id'])?->name ?: 'another venue';
            $to = Venue::find($change['to_venue_id'])?->name ?: 'another venue';
            return $name.' changes venue from '.$from.' to '.$to.'.';
        }, $calendar->venueChanges($ids, $at, (int) $data['venue_id'])));
    }

    public function assign(Event $event, array $data): TeamFixture
    {
        return DB::transaction(function () use ($event, $data) {
            // Match the stable venue lock used by the legacy team scheduler.
            Venue::orderBy('id')->limit(1)->lockForUpdate()->get();
            DB::table('events')->where('id', $event->id)->lockForUpdate()->get();
            Draw::where('event_id', $event->id)->orderBy('id')->lockForUpdate()->get();
            Venue::whereKey($data['venue_id'])->lockForUpdate()->get();
            $fixture = TeamFixture::whereKey($data['fixture_id'])
                ->whereHas('draw', fn ($q) => $q->where('event_id', $event->id))->lockForUpdate()->firstOrFail();
            if ($error = $this->manualError($event, $fixture, $data)) throw new \InvalidArgumentException($error);
            $before = $fixture->only(['scheduled_at', 'venue_id', 'court_label', 'duration_min', 'gap_minutes']);
            $fixture->forceFill(['scheduled_at' => $data['scheduled_at'] ?? $data['start'], 'venue_id' => $data['venue_id'],
                'court_label' => $data['court'] ?? $data['court_label'], 'duration_min' => $data['duration'] ?? $data['duration_min'] ?? 75,
                'gap_minutes' => (int) ($data['court_gap'] ?? $fixture->gap_minutes ?? 0), 'scheduled' => 1, 'clash_flag' => false])->save();
            DrawAuditLog::record($fixture->draw_id, 'event_venue_schedule_adjusted', null,
                ['before' => $before, 'fixture_kind' => 'team', 'fixture_id' => $fixture->id, 'event_id' => $event->id,
                    'assignment' => $fixture->only(['scheduled_at', 'venue_id', 'court_label', 'duration_min', 'gap_minutes'])]);
            return $fixture;
        });
    }

    public function removalError(Event $event, array $fixtureIds, ?string $progression = null): ?string
    {
        $saved = json_decode((string) DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true) ?: [];
        $progression ??= ($saved['round_progression'] ?? 'team_ready') === 'all_round' ? 'all_round' : 'team_ready';
        $drawIds = TeamFixture::whereIn('id', $fixtureIds)->whereHas('draw', fn ($q) => $q->where('event_id', $event->id))->pluck('draw_id')->unique();
        $keys = array_map(fn ($id) => 'team:'.$id, $fixtureIds);
        foreach (Draw::whereIn('id', $drawIds)->get() as $draw) {
            foreach ($this->nodes($draw, $progression) as $key => $node) {
                if (in_array($key, $keys, true) || ! $node['fixture']->scheduled_at) continue;
                if (array_intersect($keys, $node['dependencies'])) return 'A saved later team tie depends on this assignment. Return the dependent tie to planning too.';
            }
        }
        return null;
    }

    public function unapply(Event $event, ?int $drawId = null, ?int $venueId = null, ?int $fixtureId = null): array
    {
        return $this->unapplyScope($event, $drawId, $venueId, $fixtureId);
    }

    public function unapplyForCourtCorrection(Event $event, int $venueId): array
    {
        return $this->unapplyScope($event, null, $venueId, null, true);
    }

    private function unapplyScope(Event $event, ?int $drawId, ?int $venueId, ?int $fixtureId, bool $courtCorrection = false): array
    {
        return DB::transaction(function () use ($event, $drawId, $venueId, $fixtureId, $courtCorrection) {
            Venue::orderBy('id')->limit(1)->lockForUpdate()->get();
            DB::table('events')->where('id', $event->id)->lockForUpdate()->get();
            if ($drawId && ! $event->draws()->whereKey($drawId)->exists()) throw new \InvalidArgumentException('This draw does not belong to the event.');
            $fixtures = TeamFixture::whereHas('draw', fn ($q) => $q->where('event_id', $event->id))
                ->whereNotNull('scheduled_at')->when($drawId, fn ($q) => $q->where('draw_id', $drawId))
                ->when($venueId, fn ($q) => $q->where('venue_id', $venueId))
                ->when($fixtureId, fn ($q) => $q->whereKey($fixtureId))->orderBy('id')->lockForUpdate()->get();
            if ($fixtures->isEmpty()) throw new \InvalidArgumentException('No applied team rubbers matched this selection.');
            if ($fixtures->contains(fn ($fixture) => (! $courtCorrection && $fixture->draw->locked) || $this->protected($fixture))) {
                throw new \InvalidArgumentException('A locked draw or rubber with play or results cannot be returned to planning.');
            }
            if ($error = $this->removalError($event, $fixtures->pluck('id')->all())) throw new \InvalidArgumentException($error);
            foreach ($fixtures as $fixture) {
                $before = $fixture->only(['scheduled_at', 'venue_id', 'court_label', 'duration_min', 'gap_minutes']);
                $fixture->forceFill(['scheduled_at' => null, 'venue_id' => null, 'court_label' => null,
                    'duration_min' => null, 'gap_minutes' => 0, 'scheduled' => 0, 'clash_flag' => false])->save();
                DrawAuditLog::record($fixture->draw_id, 'event_venue_schedule_unapplied', null,
                    ['before' => $before, 'fixture_kind' => 'team', 'fixture_id' => $fixture->id, 'event_id' => $event->id]);
            }
            return ['count' => $fixtures->count(), 'message' => $fixtures->count().' rubbers returned to planning.'];
        });
    }
}
