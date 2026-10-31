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
            if (($match['automatic'] ?? null) === 'challenge_disabled') {
                unset($visiting[$key]);
                return $paths[$key] = [];
            }
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
        $rolling = isset($matches['challenge_3']['rolling_challenge']);
        foreach (array_keys($matches) as $key) {
            if ($rolling && in_array($key, \App\Services\Draw\FlexibleMonradCompiler::ROLLING_STAGES, true)) continue;
            $visit($key);
        }
        if ($rolling) {
            foreach (self::rollingCounts($matches, $bookings, $date, $visit) as $player => $count) {
                if ($count) foreach ($identities[$player] ?? ['registration:'.$player] as $identity) $totals[$identity] = max($totals[$identity] ?? 0, $count);
            }
        }
        return $totals;
    }

    /** Four base-rank roles have at most sixteen feasible challenge outcomes. */
    private static function rollingCounts(array $matches, array $bookings, string $date, \Closure $basePaths): array
    {
        $candidates = [];
        foreach ([2 => ['challenge_2', 0], 3 => ['challenge_2', 1], 4 => ['challenge_4', 0], 5 => ['challenge_4', 1]] as $rank => [$key, $slot]) {
            $source = $matches[$key]['sources'][$slot];
            if (isset($source['match'])) {
                $prior = $matches[$source['match']];
                $incoming = $basePaths($source['match']);
                if ($prior['winner'] ?? null) {
                    $allowed = $source['type'] === 'winner' ? [$prior['winner']] : array_diff(array_filter($prior['players'] ?? []), [$prior['winner']]);
                    $incoming = array_intersect_key($incoming, array_flip($allowed));
                }
            } else $incoming = ($source['type'] ?? null) === 'player' ? [(int) $source['id'] => 0] : [];
            $candidates[$rank] = $incoming;
        }
        $states = [['ranks' => [2 => 2, 3 => 3, 4 => 4, 5 => 5], 'games' => [2 => 0, 3 => 0, 4 => 0, 5 => 0],
            'new_third' => false, 'meetings' => []]];
        foreach (['challenge_2', 'challenge_4', 'challenge_3', 'challenge_2_repeat'] as $key) {
            $match = $matches[$key];
            if (! ($match['challenge_enabled'] ?? true) || ($match['automatic'] ?? false)) continue;
            [$upper, $lower] = $match['challenge_positions'];
            $weight = ($bookings['individual:'.$match['id']] ?? null) === $date ? 1 : 0;
            $next = [];
            foreach ($states as $state) {
                if ($key === 'challenge_2_repeat' && ! $state['new_third']) { $next[] = $state; continue; }
                $roles = [$state['ranks'][$upper], $state['ranks'][$lower]];
                $valid = true;
                foreach ($roles as $slot => $role) {
                    if (($player = $match['players'][$slot] ?? null) && ! array_key_exists($player, $candidates[$role])) $valid = false;
                }
                if (! $valid) continue;
                $pair = $roles; sort($pair); $pair = implode(':', $pair);
                if (isset($state['meetings'][$pair])) { $next[] = $state; continue; }
                $outcomes = ($match['winner'] ?? null) ? [array_search($match['winner'], $match['players'], true)] : [0, 1];
                foreach ($outcomes as $winnerSlot) {
                    if ($winnerSlot === false) continue;
                    $branch = $state;
                    foreach ($roles as $role) $branch['games'][$role] += $weight;
                    $branch['ranks'][$upper] = $roles[$winnerSlot];
                    $branch['ranks'][$lower] = $roles[1 - $winnerSlot];
                    if ($key === 'challenge_3') $branch['new_third'] = $winnerSlot === 1;
                    $branch['meetings'][$pair] = true;
                    $next[] = $branch;
                }
            }
            $states = $next;
        }
        $counts = [];
        foreach ($states as $state) foreach ($candidates as $role => $players) foreach ($players as $player => $baseCount) {
            $counts[$player] = max($counts[$player] ?? 0, $baseCount + $state['games'][$role]);
        }
        return $counts;
    }
}
