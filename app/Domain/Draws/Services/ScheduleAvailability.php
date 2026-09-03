<?php

namespace App\Domain\Draws\Services;

use App\Models\{Draw, Fixture, FlexibleMonradDraw, OrderOfPlay};
use App\Services\Draw\FlexibleMonradService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Court and participant reservations shared by the individual draw schedulers. */
final class ScheduleAvailability
{
    private array $slots = [];
    private array $related = [];

    public static function load(array $venues, array $registrations, array $excludeFixtures = [], ?Draw $draw = null): self
    {
        $calendar = new self();
        $registrations = array_values(array_unique(array_filter($registrations)));
        $players = DB::table('player_registrations')->whereIn('registration_id', $registrations)->get();
        $memberships = DB::table('player_registrations')->whereIn('player_id', $players->pluck('player_id'))->get();
        foreach ($registrations as $id) {
            $playerIds = $players->where('registration_id', $id)->pluck('player_id')->all();
            $calendar->related[$id] = array_unique(array_merge([$id], $memberships->whereIn('player_id', $playerIds)->pluck('registration_id')->all()));
        }
        $allIds = array_unique(array_merge($registrations, $memberships->pluck('registration_id')->all()));
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
        $bookings = OrderOfPlay::with('fixture')->whereNotIn('fixture_id', $excludeFixtures)->whereNotNull('time')
            ->where(function ($q) use ($venues, $allIds, $reservationDraws) {
                // Older trials bookings omit draw_id; the linked fixture owns the booking.
                $q->whereIn('venue_id', $venues)->orWhereHas('fixture', fn ($f) => $f->where(fn ($linked) =>
                    $linked->whereIn('draw_id', $reservationDraws)->orWhereIn('registration1_id', $allIds)->orWhereIn('registration2_id', $allIds)));
            })->get();
        $monrad = $draw?->usesFlexibleMonrad() ?? false;
        foreach ($bookings as $slot) {
            $resolved = array_filter([$slot->fixture?->registration1_id, $slot->fixture?->registration2_id]);
            $sameDraw = $draw && (int) ($slot->fixture?->draw_id ?? $slot->draw_id) === $draw->id;
            // The local dependency checks separate winner and loser paths. Do not reserve
            // both sets of possible players against each other within that same draw.
            $participants = $sameDraw ? ($monrad ? [] : $resolved) : ($possible[$slot->fixture_id] ?? $resolved);
            $calendar->reserve((int) $slot->venue_id, (string) $slot->court, Carbon::parse($slot->time),
                $slot->occupiedMinutes(), $participants);
        }
        return $calendar;
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

    public function reserve(int $venue, string $court, Carbon $start, int $duration, array $registrations): void
    {
        $this->slots[] = ['venue' => $venue, 'court' => ctype_digit($court) ? (string) (int) $court : $court,
            'start' => $start->copy(), 'end' => $start->copy()->addMinutes($duration), 'registrations' => $registrations];
    }

    public function nextAvailable(Carbon $start, int $duration, int $venue, string $court, array $registrations): Carbon
    {
        $ids = [];
        foreach (array_filter($registrations) as $id) $ids = array_merge($ids, $this->related[$id] ?? [$id]);
        $court = ctype_digit($court) ? (string) (int) $court : $court;
        $slots = array_filter($this->slots, fn ($s) => ($s['venue'] === $venue && $s['court'] === $court)
            || array_intersect($ids, $s['registrations']));
        usort($slots, fn ($a, $b) => $a['start'] <=> $b['start']);
        $at = $start->copy();
        foreach ($slots as $slot) {
            if ($at->lt($slot['end']) && $at->copy()->addMinutes($duration)->gt($slot['start'])) $at = $slot['end']->copy();
        }
        return $at;
    }
}
