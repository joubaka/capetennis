<?php

namespace App\Services\Scheduling;

use App\Models\{Event, Fixture, TeamFixture};
use App\Services\Draw\FlexibleMonradService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Event-wide workload, counting alternative winner/loser paths only once. */
final class DailyMatchLimit
{
    private array $graphs = [];
    private array $bookings = [];
    private array $identities = [];
    private array $flat = [];
    private array $unknown = [];

    public static function configured(Event $event, array $exclude = []): ?self
    {
        $stored = json_decode((string) DB::table('event_venue_schedule_drafts')->where('event_id', $event->id)->value('options'), true) ?: [];
        return ! empty($stored['limit_daily_matches']) ? self::load($event, $exclude) : null;
    }

    public static function load(Event $event, array $exclude = []): self
    {
        $limit = new self();
        $draws = $event->draws()->with('flexibleMonrad')->get();
        $fixtures = Fixture::withoutEagerLoads()->with('orderOfPlay')->whereIn('draw_id', $draws->pluck('id'))->get();
        if ($fixtures->count() > 20000) throw new \InvalidArgumentException('The daily player limit cannot verify more than 20,000 event matches. Reduce the event scope before using this option.');
        $registrationIds = $fixtures->flatMap(fn ($fixture) => [$fixture->registration1_id, $fixture->registration2_id]);
        foreach ($draws as $draw) {
            if (! $draw->usesFlexibleMonrad() || ! $draw->flexibleMonrad?->graph) continue;
            $matches = (array) app(FlexibleMonradService::class)->state($draw)['matches'];
            $limit->graphs[$draw->id] = $matches;
            $registrationIds = $registrationIds->merge($draw->flexibleMonrad->graph['players'] ?? []);
        }
        $memberships = DB::table('player_registrations')->whereIn('registration_id', $registrationIds->filter()->unique())->get();
        foreach ($registrationIds->filter()->unique() as $id) {
            $profiles = $memberships->where('registration_id', $id)->pluck('player_id')->map(fn ($player) => 'profile:'.$player)->all();
            $limit->identities[(int) $id] = $profiles ?: ['registration:'.$id];
        }
        foreach ($fixtures as $fixture) {
            $key = 'individual:'.$fixture->id;
            if (! isset($limit->graphs[$fixture->draw_id])) {
                if ((! $fixture->registration1_id || ! $fixture->registration2_id) && ! $fixture->winner_registration) $limit->unknown[$key] = true;
                $limit->flat[$key] = array_values(array_unique(array_merge(...array_map(
                    fn ($id) => $limit->identities[(int) $id] ?? [], array_filter([$fixture->registration1_id, $fixture->registration2_id])))));
            }
            if ($fixture->orderOfPlay?->time && ! in_array($key, $exclude, true)) $limit->bookings[$key] = Carbon::parse($fixture->orderOfPlay->time)->toDateString();
        }
        $team = TeamFixture::withoutEagerLoads()->with(['fixturePlayers.noProfile1', 'fixturePlayers.noProfile2'])
            ->whereIn('draw_id', $draws->pluck('id'))->get();
        if ($fixtures->count() + $team->count() > 20000) throw new \InvalidArgumentException('The daily player limit cannot verify more than 20,000 event matches.');
        foreach ($team as $fixture) {
            $key = 'team:'.$fixture->id;
            $limit->flat[$key] = app(UnifiedTeamScheduleService::class)->participants($fixture);
            if (count($limit->flat[$key]) < 2 || array_filter($limit->flat[$key], fn ($player) => str_starts_with((string) $player, 'placement:'))) $limit->unknown[$key] = true;
            if ($fixture->scheduled_at && ! in_array($key, $exclude, true)) $limit->bookings[$key] = Carbon::parse($fixture->scheduled_at)->toDateString();
        }
        return $limit;
    }

    public function reserve(string $key, string $date): void
    {
        $this->bookings[$key] = substr($date, 0, 10);
    }

    public function error(string $key, string $date, int $maximum = 3): ?string
    {
        $date = substr($date, 0, 10);
        $bookings = $this->bookings;
        $bookings[$key] = $date; // Replaces the old booking when a match is moved.
        foreach ($this->unknown as $fixture => $_) if (($bookings[$fixture] ?? null) === $date) {
            return 'Resolve the players in non-Monrad or team matches on '.$date.' before using the daily player limit.';
        }
        $totals = [];
        $affected = $this->flat[$key] ?? [];
        foreach ($this->graphs as $matches) {
            $counts = self::pathCounts($matches, $bookings, $date, $this->identities);
            foreach ($counts as $player => $count) $totals[$player] = ($totals[$player] ?? 0) + $count;
            foreach ($matches as $match) if ('individual:'.$match['id'] === $key) {
                // The path counter with only this booking identifies every feasible entrant.
                $affected = array_keys(self::pathCounts($matches, [$key => $date], $date, $this->identities));
            }
        }
        foreach ($this->flat as $fixture => $players) {
            if (($bookings[$fixture] ?? null) !== $date) continue;
            foreach (array_unique($players) as $player) $totals[$player] = ($totals[$player] ?? 0) + 1;
        }
        foreach ($affected as $player) if (($totals[$player] ?? 0) > $maximum) {
            return 'Maximum '.$maximum.' matches per player on '.$date.'. Move this match or an earlier round to another day; conditional play-offs count too.';
        }
        return null;
    }

    /** Maximum booked appearances on any feasible route, for each real player. */
    public static function pathCounts(array $matches, array $bookings, string $date, array $identities): array
    {
        $paths = []; $visiting = []; $totals = [];
        $visit = function ($key) use (&$visit, &$paths, &$visiting, &$totals, $matches, $bookings, $date, $identities): array {
            if (isset($paths[$key])) return $paths[$key];
            if (isset($visiting[$key])) throw new \InvalidArgumentException('The draw contains a circular match dependency.');
            $visiting[$key] = true;
            $match = $matches[$key]; $counts = [];
            foreach ($match['sources'] as $slot => $source) {
                if (isset($source['match'])) {
                    $prior = $matches[$source['match']];
                    $incoming = $visit($source['match']);
                    if ($prior['winner'] ?? null) {
                        $allowed = $source['type'] === 'winner' ? [$prior['winner']] : array_diff(array_filter($prior['players'] ?? []), [$prior['winner']]);
                        $incoming = array_intersect_key($incoming, array_flip($allowed));
                    }
                } else {
                    $incoming = ($source['type'] ?? null) === 'player' ? [(int) $source['id'] => 0] : [];
                }
                if ($match['players'][$slot] ?? null) $incoming = array_intersect_key($incoming, [(int) $match['players'][$slot] => true]);
                foreach ($incoming as $player => $count) $counts[$player] = max($counts[$player] ?? 0, $count);
            }
            $weight = ! ($match['automatic'] ?? false) && ($bookings['individual:'.$match['id']] ?? null) === $date ? 1 : 0;
            foreach ($counts as $player => &$count) {
                $count += $weight;
                if ($count) foreach ($identities[$player] ?? ['registration:'.$player] as $identity) $totals[$identity] = max($totals[$identity] ?? 0, $count);
            }
            unset($count, $visiting[$key]);
            return $paths[$key] = $counts;
        };
        foreach (array_keys($matches) as $key) $visit($key);
        return $totals;
    }
}
