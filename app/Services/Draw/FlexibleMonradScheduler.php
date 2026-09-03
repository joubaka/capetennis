<?php

namespace App\Services\Draw;

use App\Domain\Draws\Services\ScheduleAvailability;
use App\Models\{Draw, DrawAuditLog, Fixture, OrderOfPlay};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class FlexibleMonradScheduler
{
    public function saveFixture(Draw $draw, int $fixtureId, ?string $start, int $venueId, string $court, ?int $duration): ?OrderOfPlay
    {
        return DB::transaction(function () use ($draw, $fixtureId, $start, $venueId, $court, $duration) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            DB::table('events')->where('id', $draw->event_id)->lockForUpdate()->get();
            abort_if($draw->locked, 409, 'The draw is locked.');
            $fixture = $draw->drawFixtures()->lockForUpdate()->findOrFail($fixtureId);
            $duration ??= (int) ($fixture->orderOfPlay?->duration_minutes ?: 75);
            if ($start) {
                DB::table('venues')->where('id', $venueId)->lockForUpdate()->get();
                $assigned = $draw->venues->firstWhere('id', $venueId);
                if (! $assigned || ! ctype_digit($court) || (int) $court < 1 || (int) $court > max(1, (int) $assigned->pivot->num_courts)) {
                    throw new \InvalidArgumentException('Choose a court assigned to this draw.');
                }
                $conflict = app(\App\Domain\Draws\Services\ScheduleConflictService::class)
                    ->conflict($draw, $fixture, $venueId, $court, $start, $duration);
            } else {
                $conflict = $this->removalConflict($draw, $fixture);
            }
            if ($conflict) throw new \InvalidArgumentException($conflict);
            $slot = OrderOfPlay::firstOrNew(['fixture_id' => $fixtureId]);
            if ($start) {
                $slot->fill(['draw_id' => $draw->id, 'venue_id' => $venueId, 'court' => $court,
                    'time' => Carbon::parse($start)->format('Y-m-d H:i:s'), 'duration_minutes' => $duration,
                    'round_number' => $fixture->round]);
                $changed = $slot->isDirty() || ! $fixture->scheduled;
                $slot->save();
            } else {
                $changed = $slot->exists || (bool) $fixture->scheduled;
                if ($slot->exists) $slot->delete();
            }
            $fixture->update(['scheduled' => $start ? 1 : 0]);
            if ($changed) {
                $draw->flexibleMonrad->increment('revision');
                DrawAuditLog::record($draw->id, 'monrad_fixture_scheduled', null, ['fixture_id' => $fixtureId, 'start' => $start]);
            }
            return $start ? $slot : null;
        });
    }

    private function removalConflict(Draw $draw, Fixture $fixture): ?string
    {
        $matches = (array) app(FlexibleMonradService::class)->state($draw)['matches'];
        $key = array_key_first(array_filter($matches, fn ($m) => $m['id'] === $fixture->id));
        if ($key === null) return 'This fixture is not in the generated Monrad draw.';
        if ($matches[$key]['sets'] || $matches[$key]['automatic']) return null;
        $affected = [$key => true];
        do {
            $count = count($affected);
            foreach ($matches as $id => $match) {
                foreach ($match['sources'] as $source) {
                    if (isset($source['match'], $affected[$source['match']])) $affected[$id] = true;
                }
            }
        } while (count($affected) !== $count);
        unset($affected[$key]);
        $ids = array_map(fn ($id) => $matches[$id]['id'], array_keys($affected));
        return OrderOfPlay::whereIn('fixture_id', $ids)->whereNotNull('time')->exists()
            ? 'Remove the dependent match times first, or reset the whole schedule.' : null;
    }

    public function conflict(Draw $draw, Fixture $fixture, string $start, int $duration): ?string
    {
        if ($duration < 1 || $duration > 1440) return 'Match duration must be between 1 and 1440 minutes.';
        $matches = (array) app(FlexibleMonradService::class)->state($draw)['matches'];
        $key = array_key_first(array_filter($matches, fn ($m) => $m['id'] === $fixture->id));
        if ($key === null) return 'This fixture is not in the generated Monrad draw.';
        if ($matches[$key]['automatic']) return 'Walkovers and closed matches do not need court time.';
        $ancestors = [];
        $visit = function ($id) use (&$visit, &$ancestors, $matches) {
            foreach ($matches[$id]['sources'] as $source) {
                if (! isset($source['match']) || isset($ancestors[$source['match']])) continue;
                $ancestors[$source['match']] = true;
                $visit($source['match']);
            }
        };
        $visit($key);
        $descendants = [$key => true];
        do {
            $count = count($descendants);
            foreach ($matches as $id => $match) {
                foreach ($match['sources'] as $source) {
                    if (isset($source['match'], $descendants[$source['match']])) $descendants[$id] = true;
                }
            }
        } while (count($descendants) !== $count);
        unset($descendants[$key]);
        $slots = OrderOfPlay::where('draw_id', $draw->id)->whereNotNull('time')->get()->keyBy('fixture_id');
        $from = Carbon::parse($start);
        $to = $from->copy()->addMinutes($duration);
        foreach ($ancestors as $id => $_) {
            if ($matches[$id]['automatic']) continue;
            $slot = $slots->get($matches[$id]['id']);
            if (! $slot && ! $matches[$id]['sets']) return 'Schedule the qualifying matches before this match.';
            if ($slot && $from->lt(Carbon::parse($slot->time)->addMinutes($slot->occupiedMinutes()))) {
                return 'This match must start after its qualifying matches finish.';
            }
        }
        foreach ($descendants as $id => $_) {
            $slot = $slots->get($matches[$id]['id']);
            if ($slot && $to->gt(Carbon::parse($slot->time))) return 'This change would finish after a dependent match starts.';
        }
        return null;
    }

    public function schedule(int $drawId, int $duration, array $venues, string $startTime): bool
    {
        if ($duration < 1 || $duration > 1440) throw new \InvalidArgumentException('Match duration must be between 1 and 1440 minutes.');
        $start = Carbon::parse($startTime);
        return DB::transaction(function () use ($drawId, $duration, $venues, $start) {
            $draw = Draw::whereKey($drawId)->lockForUpdate()->firstOrFail();
            DB::table('events')->where('id', $draw->event_id)->lockForUpdate()->get();
            if ($draw->locked || $draw->published) throw new \InvalidArgumentException('Unpublish and unlock the draw before auto-scheduling.');
            // Serialize Monrad bookings of shared venues, including initially empty courts.
            DB::table('venues')->whereIn('id', array_keys($venues))->orderBy('id')->lockForUpdate()->get();
            $timeline = [];
            foreach ($venues as $venueId => $venue) {
                $assigned = $draw->venues->firstWhere('id', $venueId);
                if (! $assigned) throw new \InvalidArgumentException('The selected venue is not assigned to this draw.');
                foreach ($venue['courts'] as $court) {
                    if (! ctype_digit((string) $court) || (int) $court < 1 || (int) $court > max(1, (int) $assigned->pivot->num_courts)) {
                        throw new \InvalidArgumentException('Choose a court assigned to this draw.');
                    }
                    $timeline[$venueId][(string) $court] = $start->copy();
                }
            }
            if (! $timeline) throw new \InvalidArgumentException('Assign at least one court before scheduling.');
            $record = $draw->flexibleMonrad;
            if (! $record?->graph) throw new \InvalidArgumentException('Generate Monrad fixtures before scheduling.');
            $monrad = app(FlexibleMonradService::class);
            $record = $monrad->reconcileWithdrawals($draw, $record->revision);
            $matches = (array) $monrad->state($draw)['matches'];
            uasort($matches, fn ($a, $b) => $a['number'] <=> $b['number']);
            $existing = $draw->drawFixtures()->with('orderOfPlay')->get()->keyBy('id');
            $participants = ScheduleAvailability::participants($matches);
            $pending = [];
            $finished = [];
            foreach ($matches as $key => $match) {
                if ($match['automatic'] || $match['sets']) {
                    $finished[$key] = $start->copy();
                    // Keep played matches and their occupied court slots intact.
                    $slot = $existing[$match['id']]->orderOfPlay;
                    if ($match['sets'] && $slot?->time) {
                        $end = Carbon::parse($slot->time)->addMinutes($slot->occupiedMinutes($duration));
                        $finished[$key] = $end->max($start)->copy();
                        if (isset($timeline[$slot->venue_id][(string) $slot->court])) {
                            $timeline[$slot->venue_id][(string) $slot->court] = $timeline[$slot->venue_id][(string) $slot->court]->max($end)->copy();
                        }
                    }
                } else $pending[$key] = $match;
            }
            $plan = [];
            $calendar = ScheduleAvailability::load(array_keys($venues), $record->graph['players'],
                array_column($pending, 'id'), $draw);
            while ($pending) {
                $best = null;
                foreach ($pending as $key => $match) {
                    $release = $start->copy();
                    foreach ($match['sources'] as $source) {
                        if (! isset($source['match'])) continue;
                        if (! isset($finished[$source['match']])) continue 2;
                        $release = $release->max($finished[$source['match']])->copy();
                    }
                    foreach ($timeline as $venueId => $courts) {
                        foreach ($courts as $court => $freeAt) {
                            $at = $freeAt->copy()->max($release);
                            $at = $calendar->nextAvailable($at, $duration, (int) $venueId, (string) $court, $participants[$key]);
                            if ($best === null || $at->lt($best['time'])) {
                                $best = ['key' => $key, 'time' => $at->copy(), 'venue' => $venueId, 'court' => $court];
                            }
                        }
                    }
                }
                if ($best === null) throw new \InvalidArgumentException('The draw contains unresolved scheduling dependencies.');
                $key = $best['key'];
                $plan[$key] = $best;
                $finished[$key] = $best['time']->copy()->addMinutes($duration);
                $timeline[$best['venue']][$best['court']] = $finished[$key]->copy();
                unset($pending[$key]);
            }
            $changed = false;
            foreach ($plan as $key => $slot) {
                $match = $matches[$key];
                $oop = OrderOfPlay::firstOrNew(['fixture_id' => $match['id']]);
                $oop->fill(['draw_id' => $draw->id, 'venue_id' => $slot['venue'], 'court' => $slot['court'],
                    'time' => $slot['time']->format('Y-m-d H:i:s'), 'duration_minutes' => $duration, 'gap_minutes' => 0, 'round_number' => $match['round']]);
                $changed = $oop->isDirty() || $changed;
                $oop->save();
                $flags = Fixture::whereKey($match['id'])
                    ->where(fn ($q) => $q->whereNull('scheduled')->orWhere('scheduled', '!=', 1))->update(['scheduled' => 1]);
                $changed = $flags > 0 || $changed;
            }
            if ($changed) {
                $record->increment('revision');
                DrawAuditLog::record($draw->id, 'monrad_scheduled', null, ['matches' => count($plan), 'duration' => $duration,
                    'start' => $start->toDateTimeString(), 'revision' => $record->revision]);
            }
            return true;
        });
    }
}
