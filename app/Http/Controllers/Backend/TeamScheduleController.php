<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Draw;
use App\Models\TeamFixture;
use App\Models\RankVenueMapping;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\Event;
use App\Models\Venues;


class TeamScheduleController extends Controller
{
  /**
   * Return fixtures + venues + mappings for DataTable
   */
  public function scheduleData(Draw $draw)
  {
    
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
    $fx = TeamFixture::where('draw_id', $draw->id)->findOrFail($request->fixture_id);
    $fx->scheduled_at = $request->scheduled_at ? Carbon::parse($request->scheduled_at) : null;
    $fx->scheduled = 1;
    $fx->venue_id = $request->venue_id ?: null;
    $fx->court_label = $request->court_label ?: null;
    $fx->duration_min = $request->duration_min ?: null;
    $fx->save();

    return response()->json(['status' => 'ok']);
  }

  /**
   * Auto-schedule fixtures (apply duration/gap/venues/map)
   */
  public function autoSchedule(Request $request, Draw $draw)
  {
    $map = $request->input('rank_venue_map', []);
    // Example stub logic: loop fixtures, assign sequentially
    $fixtures = TeamFixture::where('draw_id', $draw->id)->get();
    $assigned = [];
    $start = Carbon::parse($request->start);
    $duration = (int) $request->duration;
    $gap = (int) $request->gap;

    foreach ($fixtures as $i => $fx) {
      $dt = (clone $start)->addMinutes(($duration + $gap) * $i);
      $fx->scheduled_at = $dt;
      $fx->duration_min = $duration;
      $fx->scheduled = 1;
      // apply rank→venue map if available
      if ($fx->home_rank && isset($map[$fx->home_rank])) {
        $fx->venue_id = $map[$fx->home_rank];
      } else {
        $fx->venue_id = $request->venues[$i % count($request->venues)] ?? null;
      }
    
      $fx->save();
      $assigned[] = $fx->id;
    }

    return response()->json(['assigned' => $assigned]);
  }

  /**
   * Clear all schedules for this draw
   */
  public function clearSchedule(Draw $draw)
  {
    TeamFixture::where('draw_id', $draw->id)->update([
      'scheduled_at' => null,
      'scheduled' => 0,
      'venue_id' => null,
      'court_label' => null,
      'duration_min' => null,
    ]);

    return response()->json(['message' => 'All schedules cleared']);
  }

  /**
   * Reset + auto-schedule again
   */
  public function resetSchedule(Request $request, Draw $draw)
  {
    $this->clearSchedule($draw);
    return $this->autoSchedule($request, $draw);
  }

  /**
   * Persist rank→venue mapping
   */
  public function saveRankVenues(Request $request, Draw $draw)
  {
    $map = $request->input('rank_venue_map', []);

    RankVenueMapping::where('draw_id', $draw->id)->delete();
    foreach ($map as $rank => $venueId) {
      if ($venueId) {
        RankVenueMapping::create([
          'draw_id' => $draw->id,
          'rank' => $rank,
          'venue_id' => $venueId,
        ]);
      }
    }

    return response()->json(['status' => 'ok']);
  }

  public function indexAll(Event $event)
  {
   
    $event->load('draws');
    return view('backend.team-schedule.all', compact('event'));
  }

  public function dataAll(Event $event)
  {
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

    $venues = \App\Models\Venues::select('id', 'name', 'num_courts')->get();
    return response()->json([
      'draws' => $data,
      'venues' => $venues
    ]);
  }

  public function autoAll(Request $request, Event $event)
  {
    $scheduledCount = 0;
    $clashes = [];
    $skipped = [];

    foreach ($event->draws as $draw) {
      $result = app(FixtureService::class)->autoScheduleDraw($draw, $request->all());
      $scheduledCount += $result['count'] ?? 0;
      $clashes = array_merge($clashes, $result['clashes'] ?? []);
      $skipped = array_merge($skipped, $result['skipped'] ?? []);
    }

    return response()->json([
      'message' => 'Auto schedule completed for all categories',
      'count' => $scheduledCount,
      'clashes' => $clashes,
      'skipped' => $skipped
    ]);
  }

}
