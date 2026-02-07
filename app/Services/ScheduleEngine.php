<?php

namespace App\Services;

use App\Models\Fixture;
use App\Models\OrderOfPlay;
use Carbon\Carbon;

class ScheduleEngine
{
  /**
   * Schedule the entire draw.
   */
  public function scheduleDraw(int $drawId, int $venueId, string $startTime, ?string $court = null, int $duration = 75)
  {
    $fixtures = Fixture::where('draw_id', $drawId)
      ->orderBy('round')
      ->orderBy('match_nr')
      ->get();

    $current = Carbon::parse($startTime);

    foreach ($fixtures as $fx) {
      OrderOfPlay::updateOrCreate(
        ['fixture_id' => $fx->id],
        [
          'draw_id' => $drawId,
          'round_number' => $fx->round,
          'venue_id' => $venueId,
          'court' => $court,
          'start_time' => $current->copy(),
          'duration_minutes' => $duration
        ]
      );

      $current->addMinutes($duration);
    }

    return true;
  }


  /**
   * Schedule all fixtures in one round.
   */
  public function scheduleRound(int $drawId, int $round, int $venueId, string $startTime, ?string $court = null, int $duration = 75)
  {
    $fixtures = Fixture::where('draw_id', $drawId)
      ->where('round', $round)
      ->orderBy('match_nr')
      ->get();

    $current = Carbon::parse($startTime);

    foreach ($fixtures as $fx) {
      OrderOfPlay::updateOrCreate(
        ['fixture_id' => $fx->id],
        [
          'draw_id' => $drawId,
          'round_number' => $round,
          'venue_id' => $venueId,
          'court' => $court,
          'start_time' => $current->copy(),
          'duration_minutes' => $duration
        ]
      );

      $current->addMinutes($duration);
    }

    return true;
  }


  /**
   * Schedule a single match.
   */
  public function scheduleMatch(int $fixtureId, int $venueId, string $startTime, ?string $court = null, int $duration = 75)
  {
    $fx = Fixture::findOrFail($fixtureId);

    return OrderOfPlay::updateOrCreate(
      ['fixture_id' => $fixtureId],
      [
        'draw_id' => $fx->draw_id,
        'round_number' => $fx->round,
        'venue_id' => $venueId,
        'court' => $court,
        'start_time' => Carbon::parse($startTime),
        'duration_minutes' => $duration
      ]
    );
  }


  /**
   * Auto-schedule: multi-court, multi-venue logic.
   * Simple version for now; can expand later.
   */
  public function autoSchedule(int $drawId, int $duration = 75)
  {
    // Fetch fixtures by round, then by match order
    $fixtures = Fixture::where('draw_id', $drawId)
      ->orderBy('round')
      ->orderBy('match_nr')
      ->get();

    // Load venues/courts from your frontend AJAX definition
    // You will plug in: $venues = Venue::where('event_id', ...)->get();
    // For now: pseudo-structure expected to be injected by controller
    if (!property_exists($this, 'venues') || !$this->venues) {
      throw new \Exception("AutoSchedule requires ->venues to be injected from controller");
    }

    // For example:
    // $this->venues = [
    //   12 => ['name' => 'Hermanus Primary', 'courts' => [1,2,3]],
    //   14 => ['name' => 'Laerskool Eikestad', 'courts' => [1,2]]
    // ];

    // Let's transform into a timeline structure
    $timeline = [];

    foreach ($this->venues as $venueId => $venueData) {
      foreach ($venueData['courts'] as $court) {
        $timeline[$venueId][$court] = Carbon::parse($this->startTime);
      }
    }

    // Assign fixtures to the earliest available court
    foreach ($fixtures as $fx) {

      // Find earliest court across all venues
      $earliestVenue = null;
      $earliestCourt = null;
      $earliestTime = null;

      foreach ($timeline as $venueId => $courts) {
        foreach ($courts as $court => $time) {

          if (!$earliestTime || $time < $earliestTime) {
            $earliestTime = $time;
            $earliestVenue = $venueId;
            $earliestCourt = $court;
          }
        }
      }

      // Assign match
      OrderOfPlay::updateOrCreate(
        ['fixture_id' => $fx->id],
        [
          'draw_id' => $drawId,
          'round_number' => $fx->round,
          'venue_id' => $earliestVenue,
          'court' => $earliestCourt,
          'start_time' => $earliestTime->copy(),
          'duration_minutes' => $duration
        ]
      );

      // Advance that court’s timeline
      $timeline[$earliestVenue][$earliestCourt] =
        $earliestTime->copy()->addMinutes($duration);
    }

    return true;
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
      'start_time' => null,
      'duration_minutes' => null,
      'round_number' => null,
    ]);

    return true;
  }
}
