<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Draw;
use App\Models\TeamFixture;
use App\Models\RankVenueMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Event;
use App\Models\Venue;


class TeamScheduleController extends Controller
{
  /**
   * Return fixtures + venues + mappings for DataTable
   */
  public function scheduleData(Draw $draw)
  {
    $this->authorize('team-schedule.view', $draw);

    $fixtures = TeamFixture::with(['team1', 'team2'])
      ->where('draw_id', $draw->id)
      ->get()
      ->map(function ($fx) {
        return [
          'id' => $fx->id,
          'round_nr' => $fx->round_nr,
          'match' => $fx->match,
          'p1' => $fx->team1->map(fn($p) => $p->full_name)->implode(' + ') ?: 'TBD',
          'p2' => $fx->team2->map(fn($p) => $p->full_name)->implode(' + ') ?: 'TBD',
          'scheduled_at' => $fx->scheduled_at ? $fx->scheduled_at->format('Y-m-d H:i') : null,
          'venue_id' => $fx->venue_id,
          'court_label' => $fx->court_label,
          'scheduled' => (int) $fx->scheduled,

          'duration_min' => $fx->duration_min,
          'clash_flag' => false, // TODO: detect clashes if needed
        ];
      });

    $venues = $draw->venues()->select('id', 'name', 'num_courts')->get();

    $rankVenues = RankVenueMapping::where('draw_id', $draw->id)->pluck('venue_id', 'rank');

    return response()->json([
      'fixtures' => $fixtures,
      'venues' => $venues,
      'rankVenues' => $rankVenues,
    ]);
  }

  /**
   * Save one fixture row (from “Save” button)
   */
  public function saveFixture(Request $request, Draw $draw)
  {
    return app(TeamFixtureController::class)->scheduleSave($request, $draw);
  }


  /**
   * Auto-schedule fixtures (apply duration/gap/venues/map)
   */
  public function autoSchedule(Request $request, Draw $draw)
  {
    return app(TeamFixtureController::class)->scheduleAuto($request, $draw);
  }


  /**
   * Clear all schedules for this draw
   */
  public function clearSchedule(Draw $draw)
  {
    return app(TeamFixtureController::class)->scheduleClear($draw);
  }


  /**
   * Reset + auto-schedule again
   */
  public function resetSchedule(Request $request, Draw $draw)
  {
    return app(TeamFixtureController::class)->scheduleReset($request, $draw);
  }


  /**
   * Persist rank→venue mapping
   */
  public function saveRankVenues(Request $request, Draw $draw)
  {
    return app(TeamFixtureController::class)->saveRankVenues($request, $draw);
  }

  public function indexAll(Event $event)
  {
    $this->authorize('team-schedule.view', $event);

    $event->load('draws');
    return view('backend.team-schedule.all', compact('event'));
  }

  public function dataAll(Event $event)
  {
    $this->authorize('team-schedule.view', $event);

    $event->load('draws');

    $data = [];
    foreach ($event->draws as $draw) {
      $fixtures = TeamFixture::with(['team1', 'team2'])
        ->where('draw_id', $draw->id)
        ->orderByRaw('CAST(round_nr AS UNSIGNED)')
        ->get()
        ->map(function ($fx) use ($draw) {
          return [
            'id' => $fx->id,
            'round' => $fx->round_nr,
            'match' => $fx->match_nr,
            'p1' => $fx->team1->pluck('name')->join(' + ') ?: 'TBD',
            'p2' => $fx->team2->pluck('name')->join(' + ') ?: 'TBD',
            'scheduled_at' => $fx->scheduled_at,
            'venue_id' => $fx->venue_id,
            'court_label' => $fx->court_label,
            'duration_min' => $fx->duration_min,
          ];
        });

      $data[] = [
        'id' => $draw->id,
        'name' => $draw->drawName,
        'fixtures' => $fixtures,
      ];
    }

    $venues = $event->draws->flatMap(fn ($draw) => $draw->venues)
      ->groupBy('id')->map(fn ($group) => [
        'id' => $group->first()->id,
        'name' => $group->first()->name,
        'num_courts' => $group->max(fn ($venue) => (int) $venue->pivot->num_courts),
      ])->values();
    return response()->json([
      'draws' => $data,
      'venues' => $venues
    ]);
  }

  public function autoAll(Request $request, Event $event)
  {
    $this->authorize('team-schedule.manage', $event);

    $scheduler = app(\App\Services\TeamScheduleService::class);
    return response()->json($scheduler->automatic($event->draws, $scheduler->validate($request)));
  }

  public function clearAll(Event $event)
  {
    $this->authorize('team-schedule.manage', $event);
    app(\App\Services\TeamScheduleService::class)->clear($event->draws);
    return response()->json(['success' => true, 'message' => 'All schedules cleared for this event.']);
  }
}
