<?php

namespace App\Services;

use App\Models\Draw;
use App\Models\Fixture;
use App\Models\OrderOfPlay;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Domain\Draws\Services\{ScheduleAvailability, ScheduleConflictService};

class ScheduleEngine
{
  /**
   * @throws \RuntimeException if the draw is locked or published
   */
  private function assertSchedulable(int $drawId): void
  {
    $draw = Draw::findOrFail($drawId);

    if ($draw->locked) {
      throw new \RuntimeException("Cannot schedule a locked draw (draw #{$drawId}).");
    }

    if ($draw->published) {
      throw new \RuntimeException("Cannot schedule a published draw (draw #{$drawId}).");
    }
  }

  /**
   * Schedule the entire draw.
   */
  public function scheduleDraw(int $drawId, int $venueId, string $startTime, ?string $court = null, int $duration = 75)
  {
    if (Draw::findOrFail($drawId)->usesFlexibleMonrad()) {
      return app(\App\Services\Draw\FlexibleMonradScheduler::class)->schedule($drawId, $duration,
        [$venueId => ['courts' => [$court ?? 1]]], $startTime);
    }
    return $this->scheduleFixtures($drawId, $duration, [$venueId => ['courts' => [$court ?? 1]]], $startTime);
  }


  /**
   * Schedule all fixtures in one round.
   */
  public function scheduleRound(int $drawId, int $round, int $venueId, string $startTime, ?string $court = null, int $duration = 75)
  {
    if (Draw::findOrFail($drawId)->usesFlexibleMonrad()) {
      throw new \InvalidArgumentException('Monrad rounds belong to separate placement paths. Auto-schedule the whole draw or adjust individual matches.');
    }
    return $this->scheduleFixtures($drawId, $duration, [$venueId => ['courts' => [$court ?? 1]]], $startTime, $round);
  }


  /**
   * Schedule a single match.
   */
  public function scheduleMatch(int $fixtureId, int $venueId, string $startTime, ?string $court = null, int $duration = 75)
  {
    $fx = Fixture::findOrFail($fixtureId);

    $this->assertSchedulable($fx->draw_id);

    $draw = Draw::findOrFail($fx->draw_id);
    if ($draw->usesFlexibleMonrad()) {
      return app(\App\Services\Draw\FlexibleMonradScheduler::class)->saveFixture($draw, $fixtureId, $startTime,
        $venueId, (string) ($court ?? 1), $duration);
    }

    return $this->saveFixture($draw, $fixtureId, $startTime, $venueId, (string) ($court ?? 1), $duration);
  }


  /**
   * Auto-schedule: multi-court, multi-venue logic.
   *
   * @param int    $drawId
   * @param int    $duration   Minutes per match.
   * @param array  $venues     [ venueId => ['name' => '...', 'courts' => [1,2,...]], ... ]
   * @param string $startTime  ISO datetime string for the first slot.
   */
  public function autoSchedule(int $drawId, int $duration = 75, array $venues = [], string $startTime = '')
  {
    if (empty($venues)) {
      throw new \InvalidArgumentException('ScheduleEngine::autoSchedule() requires a non-empty $venues array.');
    }

    if (empty($startTime)) {
      throw new \InvalidArgumentException('ScheduleEngine::autoSchedule() requires a $startTime string.');
    }

    if (Draw::findOrFail($drawId)->usesFlexibleMonrad()) {
      return app(\App\Services\Draw\FlexibleMonradScheduler::class)->schedule($drawId, $duration, $venues, $startTime);
    }

    return $this->scheduleFixtures($drawId, $duration, $venues, $startTime);
  }


  public function scheduleTrials(int $drawId, int $duration, int $gap, string $start, array $brackets = [], array $rounds = []): bool
  {
    $draw = Draw::findOrFail($drawId);
    if ($draw->usesFlexibleMonrad()) throw new \InvalidArgumentException('Use the individual draw auto-scheduler for Flexible Monrad.');
    $venue = $draw->venues()->first();
    if (! $venue) throw new \InvalidArgumentException('No venue assigned. Add a venue first.');
    return $this->scheduleFixtures($drawId, $duration, [$venue->id => ['courts' => range(1, max(1, (int) $venue->pivot->num_courts))]],
      $start, null, $brackets, $rounds, $gap);
  }

  private function feederMap($fixtures): array
  {
    $feeders = [];
    foreach ($fixtures as $fixture) {
      foreach ([$fixture->parent_fixture_id, $fixture->loser_parent_fixture_id] as $target) {
        if ($target && isset($fixtures[$target])) $feeders[$target][$fixture->id] = $fixture->id;
      }
    }
    return $feeders;
  }

  private function automatic(Fixture $fixture, array $feeders): bool
  {
    return ! $fixture->fixtureResults->count() && (! $fixture->registration1_id || ! $fixture->registration2_id)
      && ($fixture->winner_registration || (empty($feeders[$fixture->id])
        && (int) $fixture->bracket_id === 1 && (int) $fixture->round === 1));
  }

  private function scheduleFixtures(int $drawId, int $duration, array $venues, string $startTime, ?int $round = null,
    array $brackets = [], array $rounds = [], int $gap = 0): bool
  {
    if ($duration < 1 || $duration > 1440) throw new \InvalidArgumentException('Match duration must be between 1 and 1440 minutes.');
    if ($gap < 0 || $gap > 1440) throw new \InvalidArgumentException('The gap must be between 0 and 1440 minutes.');
    $start = Carbon::parse($startTime);
    return DB::transaction(function () use ($drawId, $duration, $venues, $start, $round, $brackets, $rounds, $gap) {
      $draw = Draw::whereKey($drawId)->lockForUpdate()->firstOrFail();
      DB::table('events')->where('id', $draw->event_id)->lockForUpdate()->get();
      DB::table('venues')->whereIn('id', array_keys($venues))->orderBy('id')->lockForUpdate()->get();
      $this->assertSchedulable($drawId);
      $all = Fixture::where('draw_id', $drawId)->with(['orderOfPlay', 'fixtureResults'])->orderBy('round')->orderBy('match_nr')->get()->keyBy('id');
      $fixtures = $all->filter(fn ($f) => ($round === null || (int) $f->round === $round)
        && (! $brackets || in_array($f->bracket_id, $brackets)) && (! $rounds || in_array($f->round, $rounds)));
      $feeders = $this->feederMap($all);
      $participants = ScheduleAvailability::legacyParticipants($all);
      $ids = $fixtures->flatMap(fn ($f) => $participants[$f->id])->all();
      $calendar = ScheduleAvailability::load(array_keys($venues), $ids, $fixtures->pluck('id')->all(), $draw);
      $pending = $fixtures->all();
      $finished = [];
      foreach ($all as $id => $fixture) {
        if ($this->automatic($fixture, $feeders)) {
          $finished[$id] = $start->copy();
          if (isset($pending[$id])) {
            OrderOfPlay::where('fixture_id', $id)->delete();
            $fixture->update(['scheduled' => 0]);
            unset($pending[$id]);
          }
        } elseif (! isset($pending[$id])) {
          $slot = $fixture->orderOfPlay;
          if ($slot?->time) $finished[$id] = Carbon::parse($slot->time)->addMinutes($slot->occupiedMinutes());
          elseif ($fixture->fixtureResults->isNotEmpty()) $finished[$id] = $start->copy();
        }
      }
      while ($pending) {
        $ready = false;
        foreach ($pending as $fixtureId => $fixture) {
          $release = $start->copy();
          foreach ($feeders[$fixtureId] ?? [] as $feeder) {
            if (! isset($finished[$feeder])) continue 2;
            $release = $release->max($finished[$feeder])->copy();
          }
          $ready = true;
          $registrations = $participants[$fixtureId];
          $best = null;
          foreach ($venues as $venueId => $venue) {
            foreach ($venue['courts'] as $court) {
              $at = $calendar->nextAvailable($release, $duration + $gap, (int) $venueId, (string) $court, $registrations);
              if ($best === null || $at->lt($best['time'])) $best = ['venue' => $venueId, 'court' => $court, 'time' => $at];
            }
          }
          if ($best === null) throw new \InvalidArgumentException('Assign at least one court before scheduling.');
          $finished[$fixtureId] = $best['time']->copy()->addMinutes($duration + $gap);
          foreach ([$fixture->parent_fixture_id, $fixture->loser_parent_fixture_id] as $target) {
            if ($target && ! isset($fixtures[$target]) && isset($all[$target]) && $all[$target]->orderOfPlay?->time
              && $finished[$fixtureId]->gt(Carbon::parse($all[$target]->orderOfPlay->time))) {
              throw new \InvalidArgumentException('This change would finish after a dependent match starts. Include that match in the schedule update.');
            }
          }
          OrderOfPlay::updateOrCreate(['fixture_id' => $fixture->id], [
            'draw_id' => $drawId, 'venue_id' => $best['venue'], 'court' => $best['court'],
            'time' => $best['time']->format('Y-m-d H:i:s'), 'duration_minutes' => $duration, 'gap_minutes' => $gap, 'round_number' => $fixture->round,
          ]);
          $fixture->update(['scheduled' => 1]);
          $calendar->reserve((int) $best['venue'], (string) $best['court'], $best['time'], $duration + $gap,
            array_filter([$fixture->registration1_id, $fixture->registration2_id]));
          unset($pending[$fixtureId]);
        }
        if (! $ready) throw new \InvalidArgumentException('Schedule the qualifying matches first, or include them in this update. Check for circular fixture links.');
      }
      return true;
    });
  }

  public function dependencyConflict(Draw $draw, Fixture $fixture, ?string $start, int $duration): ?string
  {
    $all = $draw->drawFixtures()->with(['orderOfPlay', 'fixtureResults'])->get()->keyBy('id');
    $feeders = $this->feederMap($all);
    $ancestors = [];
    $visit = function ($id) use (&$visit, &$ancestors, $feeders) {
      foreach ($feeders[$id] ?? [] as $source) {
        if (isset($ancestors[$source])) continue;
        $ancestors[$source] = true;
        $visit($source);
      }
    };
    $visit($fixture->id);
    $descendants = [$fixture->id => true];
    do {
      $count = count($descendants);
      foreach ($feeders as $target => $sources) {
        if (array_intersect_key($sources, $descendants)) $descendants[$target] = true;
      }
    } while (count($descendants) !== $count);
    unset($descendants[$fixture->id]);
    if ($start) {
      $from = Carbon::parse($start);
      foreach ($ancestors as $id => $_) {
        $parent = $all[$id];
        if ($this->automatic($parent, $feeders)) continue;
        $slot = $parent->orderOfPlay;
        if (! $slot?->time && $parent->fixtureResults->isEmpty()) return 'Schedule the qualifying matches first.';
        if ($slot?->time && $from->lt(Carbon::parse($slot->time)->addMinutes($slot->occupiedMinutes()))) {
          return 'This match must start after its qualifying matches finish.';
        }
      }
    }
    foreach ($descendants as $id => $_) {
      $slot = $all[$id]->orderOfPlay;
      if (! $slot?->time) continue;
      if (! $start && $all[$fixture->id]->fixtureResults->isEmpty() && ! $this->automatic($all[$fixture->id], $feeders)) {
        return 'Remove the dependent match times first.';
      }
      if ($start && $from->copy()->addMinutes($duration)->gt(Carbon::parse($slot->time))) {
        return 'This change would finish after a dependent match starts.';
      }
    }
    return null;
  }

  public function saveFixture(Draw $draw, int $fixtureId, ?string $start, int $venueId, string $court,
    ?int $duration, bool $allowPublished = false, bool $requireCourtDurationFit = true): ?OrderOfPlay
  {
    return DB::transaction(function () use ($draw, $fixtureId, $start, $venueId, $court, $duration, $allowPublished, $requireCourtDurationFit) {
      $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
      DB::table('events')->where('id', $draw->event_id)->lockForUpdate()->get();
      abort_if($draw->locked || (! $allowPublished && $draw->published), 409, 'Unpublish and unlock the draw before scheduling.');
      if ($draw->usesFlexibleMonrad()) {
        return app(\App\Services\Draw\FlexibleMonradScheduler::class)->saveFixture(
          $draw, $fixtureId, $start, $venueId, $court, $duration, $requireCourtDurationFit
        );
      }
      $fixture = $draw->drawFixtures()->lockForUpdate()->findOrFail($fixtureId);
      $duration ??= (int) ($fixture->orderOfPlay?->duration_minutes ?: 75);
      if ($start) {
        DB::table('venues')->where('id', $venueId)->lockForUpdate()->get();
        if ($duration < 1 || $duration > 1440) throw new \InvalidArgumentException('Match duration must be between 1 and 1440 minutes.');
        $conflict = app(ScheduleConflictService::class)->conflict(
          $draw, $fixture, $venueId, $court, $start, $duration, null, null, $requireCourtDurationFit
        );
        if ($conflict) throw new \InvalidArgumentException($conflict);
        $slot = OrderOfPlay::updateOrCreate(['fixture_id' => $fixtureId], [
          'draw_id' => $draw->id, 'venue_id' => $venueId, 'court' => $court,
          'time' => Carbon::parse($start)->format('Y-m-d H:i:s'), 'duration_minutes' => $duration, 'round_number' => $fixture->round,
        ]);
      } else {
        $conflict = $this->dependencyConflict($draw, $fixture, null, $duration);
        if ($conflict) throw new \InvalidArgumentException($conflict);
        OrderOfPlay::where('fixture_id', $fixtureId)->delete();
        $slot = null;
      }
      $fixture->update(['scheduled' => $start ? 1 : 0]);
      return $slot;
    });
  }

  /**
   * Clear all schedule data for the draw.
   */
  public function clear(int $drawId)
  {
    OrderOfPlay::where('draw_id', $drawId)->delete();
    return true;
  }


  /**
   * Reset scheduling for the draw back to NULL.
   */
  public function reset(int $drawId)
  {
    OrderOfPlay::where('draw_id', $drawId)->update([
      'venue_id' => null,
      'court' => null,
      'time' => null,
      'duration_minutes' => null,
      'gap_minutes' => 0,
      'round_number' => null,
    ]);

    return true;
  }
}

