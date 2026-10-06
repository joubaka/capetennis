<?php

namespace App\Domain\Draws\Services;

use App\Models\{Draw, Fixture, FlexibleMonradDraw, OrderOfPlay, TeamFixture, Event};
use App\Services\Draw\FlexibleMonradService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Court and participant reservations shared by the individual draw schedulers. */
final class ScheduleAvailability
{
    private array $slots = [];
    private array $related = [];
    private ?Event $venueHistoryEvent = null;
    private ?Carbon $venueHistoryStart = null;
    private ?Carbon $venueHistoryEnd = null;

    public static function load(array $venues, array $registrations, array $excludeFixtures = [], ?Draw $draw = null,
        ?int $participantRest = null, array $excludeTeamFixtures = [], ?Event $venueHistoryEvent = null,
        ?Carbon $planningDate = null, ?Carbon $windowStart = null, ?Carbon $windowEnd = null): self
    {
        $calendar = new self();
        $calendar->venueHistoryEvent = $venueHistoryEvent;
        if ($venueHistoryEvent) {
            $fallback = $planningDate ?? Carbon::today();
            $calendar->venueHistoryStart = Carbon::parse($venueHistoryEvent->start_date ?: $fallback)->startOfDay();
            $calendar->venueHistoryEnd = Carbon::parse($venueHistoryEvent->end_date ?: ($venueHistoryEvent->start_date ?: $fallback))->endOfDay();
        }
        $registrations = array_values(array_unique(array_filter($registrations)));
        $profileIds = collect($registrations)->filter(fn ($id) => is_string($id) && str_starts_with($id, 'profile:'))
            ->map(fn ($id) => (int) substr($id, 8))->all();
        $registrationIds = array_values(array_filter($registrations, fn ($id) => is_numeric($id)));
        $players = DB::table('player_registrations')->whereIn('registration_id', $registrationIds)
            ->orWhereIn('player_id', $profileIds)->get();
        $memberships = DB::table('player_registrations')->whereIn('player_id', $players->pluck('player_id'))->get();
        foreach ($registrations as $id) {
            $playerIds = $players->where('registration_id', $id)->pluck('player_id')->all();
            $calendar->related[$id] = array_unique(array_merge([$id], array_map(fn ($playerId) => 'profile:'.$playerId, $playerIds), $memberships->whereIn('player_id', $playerIds)->pluck('registration_id')->all()));
        }
        foreach ($memberships->groupBy('registration_id') as $id => $rows) {
            $calendar->related[$id] = array_unique(array_merge($calendar->related[$id] ?? [$id],
                $rows->pluck('player_id')->map(fn ($playerId) => 'profile:'.$playerId)->all()));
        }
        $allIds = array_unique(array_merge($registrationIds, $memberships->pluck('registration_id')->all()));
        // Future Monrad fixtures can have no resolved participants yet. Reserve their possible entrants too.
        $monrads = $allIds ? FlexibleMonradDraw::whereNotNull('graph')->when($draw, fn ($q) => $q->where('draw_id', '!=', $draw->id))->where(function ($q) use ($allIds) {
            foreach ($allIds as $id) $q->orWhereJsonContains('graph->players', (int) $id);
        })->get() : collect();
        $possible = [];
        foreach ($monrads as $record) {
            $matches = (array) app(FlexibleMonradService::class)->state(Draw::findOrFail($record->draw_id))['matches'];
            foreach (self::participants($matches) as $key => $ids) $possible[$matches[$key]['id']] = $ids;
        }
        // A legacy finalist may still be TBD even though their qualifier identifies the possible players.
        $legacyDraws = $allIds ? Fixture::where(fn ($q) => $q->whereIn('registration1_id', $allIds)->orWhereIn('registration2_id', $allIds))
            ->whereIn('draw_id', Fixture::select('draw_id')->whereHas('orderOfPlay', fn ($q) => $q->whereNotNull('time')))
            ->whereNotIn('draw_id', FlexibleMonradDraw::select('draw_id')->whereNotNull('graph'))
            ->when($draw, fn ($q) => $q->where('draw_id', '!=', $draw->id))
            ->distinct()->pluck('draw_id') : collect();
        if ($legacyDraws->isNotEmpty()) {
            $possible += self::legacyParticipants(Fixture::whereIn('draw_id', $legacyDraws)->get()->keyBy('id'));
        }
        $reservationDraws = $monrads->pluck('draw_id')->merge($legacyDraws);
        $bookings = OrderOfPlay::with('fixture.draw')->whereNotIn('fixture_id', $excludeFixtures)->whereNotNull('time')
            ->when($windowEnd, fn ($query) => $query->where('time', '<', $windowEnd))
            ->where(function ($q) use ($venues, $allIds, $reservationDraws) {
                // Older trials bookings omit draw_id; the linked fixture owns the booking.
                $q->whereIn('venue_id', $venues)->orWhereHas('fixture', fn ($f) => $f->where(fn ($linked) =>
                    $linked->whereIn('draw_id', $reservationDraws)->orWhereIn('registration1_id', $allIds)->orWhereIn('registration2_id', $allIds)));
            })->orderBy('id')->get();
        $monrad = $draw?->usesFlexibleMonrad() ?? false;
        foreach ($bookings as $slot) {
            $startsAt = Carbon::parse($slot->time);
            $reservationMinutes = max($slot->occupiedMinutes(), (int) ($slot->duration_minutes ?: 75) + max(0, $participantRest ?? 0));
            if ($windowStart && $startsAt->copy()->addMinutes($reservationMinutes)->lte($windowStart)
                && (! $calendar->venueHistoryEvent || ! $calendar->historyEligible($slot->fixture?->draw?->event_id, $startsAt))) continue;
            $resolved = array_filter([$slot->fixture?->registration1_id, $slot->fixture?->registration2_id]);
            $sameDraw = $draw && (int) ($slot->fixture?->draw_id ?? $slot->draw_id) === $draw->id;
            // The local dependency checks separate winner and loser paths. Do not reserve
            // both sets of possible players against each other within that same draw.
            $participants = $sameDraw ? ($monrad ? [] : $resolved) : ($possible[$slot->fixture_id] ?? $resolved);
            $occupied = $slot->occupiedMinutes();
            $matchMinutes = (int) ($slot->duration_minutes ?: 75);
            $participantMinutes = $participantRest === null
                ? $occupied
                : $matchMinutes + max(0, $participantRest);
            $calendar->reserveWithRest((int) $slot->venue_id, (string) $slot->court, Carbon::parse($slot->time),
                $occupied, $participantMinutes, $participants, null,
                $calendar->historyEligible($slot->fixture?->draw?->event_id, Carbon::parse($slot->time)),
                $calendar->fixtureSource($slot->fixture, 'individual'));
        }
        $teamBookings = TeamFixture::withoutEagerLoads()->with(['draw', 'fixturePlayers.noProfile1', 'fixturePlayers.noProfile2'])
            ->whereNotNull('scheduled_at')->whereNotIn('id', $excludeTeamFixtures)
            ->when($windowEnd, fn ($query) => $query->where('scheduled_at', '<', $windowEnd))
            ->where(function ($query) use ($venues, $players, $registrations, $profileIds) {
                $query->whereIn('venue_id', $venues)->orWhereHas('fixturePlayers', function ($slots) use ($players, $registrations, $profileIds) {
                    $profiles = array_unique(array_merge($profileIds, $players->pluck('player_id')->all()));
                    $noProfiles = collect($registrations)->filter(fn ($id) => is_string($id) && str_starts_with($id, 'no-profile:'))
                        ->map(fn ($id) => (int) substr($id, 11))->all();
                    $slots->whereIn('team1_id', $profiles)->orWhereIn('team2_id', $profiles)
                        ->orWhereIn('team1_no_profile_id', $noProfiles)->orWhereIn('team2_no_profile_id', $noProfiles)
                        ->orWhereHas('noProfile1', fn ($q) => $q->whereIn('player_profile', $profiles))
                        ->orWhereHas('noProfile2', fn ($q) => $q->whereIn('player_profile', $profiles));
                });
            })->orderBy('id')->get();
        foreach ($teamBookings as $fixture) {
            $minutes = (int) ($fixture->duration_min ?: 120);
            $startsAt = Carbon::parse($fixture->scheduled_at);
            $reservationMinutes = $minutes + max((int) ($fixture->gap_minutes ?? 0), max(0, $participantRest ?? (int) ($fixture->gap_minutes ?? 0)));
            if ($windowStart && $startsAt->copy()->addMinutes($reservationMinutes)->lte($windowStart)
                && (! $calendar->venueHistoryEvent || ! $calendar->historyEligible($fixture->draw?->event_id, $startsAt))) continue;
            $calendar->reserveWithRest((int) $fixture->venue_id, (string) $fixture->court_label,
                Carbon::parse($fixture->scheduled_at), $minutes + (int) ($fixture->gap_minutes ?? 0),
                $minutes + max(0, $participantRest ?? (int) ($fixture->gap_minutes ?? 0)),
                app(\App\Services\Scheduling\UnifiedTeamScheduleService::class)->participants($fixture), null,
                $calendar->historyEligible($fixture->draw?->event_id, Carbon::parse($fixture->scheduled_at)),
                $calendar->fixtureSource($fixture, 'team'));
        }
        return $calendar;
    }

    public function fingerprint(): string
    {
        return hash('sha256', json_encode(['slots' => $this->slots, 'identities' => $this->related,
            'venue_history' => ['event_id' => $this->venueHistoryEvent?->id,
                'start' => $this->venueHistoryStart?->toDateTimeString(), 'end' => $this->venueHistoryEnd?->toDateTimeString()]], JSON_THROW_ON_ERROR));
    }

    public static function legacyParticipants($fixtures): array
    {
        $possible = [];
        foreach ($fixtures as $fixture) {
            $possible[$fixture->id] = array_values(array_filter([$fixture->registration1_id, $fixture->registration2_id]));
        }
        // Propagate to a fixed point so fixture ordering and malformed cycles cannot cause recursion failures.
        do {
            $changed = false;
            foreach ($fixtures as $fixture) {
                foreach (['parent_fixture_id' => true, 'loser_parent_fixture_id' => false] as $link => $winnerPath) {
                    $target = $fixtures[$fixture->$link] ?? null;
                    if (! $target || $target->draw_id !== $fixture->draw_id
                        || ($target->registration1_id && $target->registration2_id)) continue;
                    $ids = $possible[$fixture->id];
                    if ($fixture->winner_registration) {
                        $ids = $winnerPath ? [$fixture->winner_registration] : array_values(array_diff(
                            array_filter([$fixture->registration1_id, $fixture->registration2_id]), [$fixture->winner_registration]));
                    }
                    $merged = array_values(array_unique(array_merge($possible[$target->id], $ids)));
                    if (count($merged) !== count($possible[$target->id])) {
                        $possible[$target->id] = $merged;
                        $changed = true;
                    }
                }
            }
        } while ($changed);
        return $possible;
    }

    public static function participants(array $matches): array
    {
        $result = [];
        $visit = function ($key) use (&$visit, &$result, $matches) {
            if (isset($result[$key])) return $result[$key];
            $match = $matches[$key];
            if ($match['automatic'] ?? false) return $result[$key] = [];
            $ids = [];
            foreach ($match['sources'] as $slot => $source) {
                if ($id = $match['players'][$slot] ?? null) $ids[] = $id;
                elseif (isset($source['match'])) $ids = array_merge($ids, $visit($source['match']));
                elseif (($source['type'] ?? null) === 'player') $ids[] = $source['id'];
            }
            return $result[$key] = array_values(array_unique($ids));
        };
        foreach (array_keys($matches) as $key) $visit($key);
        return $result;
    }

    public static function courtKey(string $court): string
    {
        $court = trim($court);
        if (preg_match('/^(?:Court\\s*)?(\\d+)$/i', $court, $matches)) return (string) (int) $matches[1];
        return $court;
    }

    private function identities(array $ids): array
    {
        $expanded = [];
        foreach (array_filter($ids) as $id) $expanded = array_merge($expanded, $this->related[$id] ?? [$id]);
        return array_values(array_unique($expanded));
    }

    private function historyEligible(?int $eventId, Carbon $at): bool
    {
        return ! $this->venueHistoryEvent || ((int) $eventId === (int) $this->venueHistoryEvent->id
            && $at->betweenIncluded($this->venueHistoryStart, $this->venueHistoryEnd));
    }

    public function venueChanges(array $ids, Carbon $at, int $venue): array
    {
        $changes = [];
        foreach ($this->identities($ids) as $id) {
            if (! is_string($id) || (! str_starts_with($id, 'profile:') && ! str_starts_with($id, 'no-profile:'))) continue;
            $prior = array_filter($this->slots, fn ($slot) => $slot['venue_history_eligible'] && $slot['start']->lte($at) && in_array($id, $slot['registrations'], true) && $slot['venue']);
            usort($prior, fn ($a, $b) => $b['start'] <=> $a['start']);
            if ($prior && $prior[0]['venue'] !== $venue) $changes[] = [
                'participant_id' => $id, 'from_venue_id' => $prior[0]['venue'], 'to_venue_id' => $venue,
                'from_booking' => ['scheduled_at' => $prior[0]['start']->format('Y-m-d H:i:s'),
                    'court' => $prior[0]['court'], 'venue_id' => $prior[0]['venue'],
                    'fixture' => $prior[0]['source'] ?? null],
            ];
        }
        return $changes;
    }

    public function reserve(int $venue, string $court, Carbon $start, int $duration, array $registrations): void
    {
        $this->reserveWithRest($venue, $court, $start, $duration, $duration, $registrations);
    }

    public function reserveWithRest(int $venue, string $court, Carbon $start, int $courtMinutes,
        int $participantMinutes, array $registrations, ?string $participantGroup = null, bool $venueHistoryEligible = true, ?array $source = null): void
    {
        $this->slots[] = ['venue' => $venue, 'court' => self::courtKey($court),
            'start' => $start->copy(), 'court_end' => $start->copy()->addMinutes($courtMinutes),
            'participant_end' => $start->copy()->addMinutes($participantMinutes), 'registrations' => $this->identities($registrations),
            'participant_group' => $participantGroup, 'venue_history_eligible' => $venueHistoryEligible,
            'source' => $venueHistoryEligible ? $source : null];
    }

    private function fixtureSource(Fixture|TeamFixture|null $fixture, string $kind): ?array
    {
        if (! $fixture || ! $this->venueHistoryEvent || (int) $fixture->draw?->event_id !== (int) $this->venueHistoryEvent->id) return null;
        return ['fixture_key' => $kind.':'.$fixture->id, 'draw_name' => $fixture->draw->drawName,
            'discipline' => $kind === 'team' ? ($fixture->rubber_name ?: $fixture->rubber_code ?: 'Team rubber') : ($fixture->stage ?: 'Match'),
            'round' => $kind === 'team' ? $fixture->round_nr : $fixture->round,
            'match' => $fixture->match_nr];
    }

    public function nextAvailable(Carbon $start, int $duration, int $venue, string $court, array $registrations): Carbon
    {
        return $this->nextAvailableForMatch($start, $duration, $duration, $venue, $court, $registrations);
    }

    public function nextAvailableForMatch(Carbon $start, int $courtMinutes, int $participantMinutes,
        int $venue, string $court, array $registrations, ?string $participantGroup = null): Carbon
    {
        $ids = $this->identities($registrations);
        $court = self::courtKey($court);
        $slots = array_filter($this->slots, fn ($s) => ($s['venue'] === $venue && $s['court'] === $court)
            || (! $participantGroup || $s['participant_group'] !== $participantGroup)
                && array_intersect($ids, $s['registrations']));
        usort($slots, fn ($a, $b) => $a['start'] <=> $b['start']);
        $at = $start->copy();
        foreach ($slots as $slot) {
            $courtConflict = $slot['venue'] === $venue && $slot['court'] === $court
                && $at->lt($slot['court_end']) && $at->copy()->addMinutes($courtMinutes)->gt($slot['start']);
            $participantConflict = (! $participantGroup || $slot['participant_group'] !== $participantGroup)
                && array_intersect($ids, $slot['registrations'])
                && $at->lt($slot['participant_end']) && $at->copy()->addMinutes($participantMinutes)->gt($slot['start']);
            if ($courtConflict || $participantConflict) {
                $end = $courtConflict ? $slot['court_end']->copy() : $slot['participant_end']->copy();
                if ($participantConflict) $end = $end->max($slot['participant_end'])->copy();
                $at = $end;
            }
        }
        return $at;
    }
}
