<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Fixture;
use App\Models\OrderOfPlay;
use App\Models\Draw;
use App\Services\ScheduleEngine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ScheduleController extends Controller
{

  // ---------------------------------------------------------
  // PAGE
  // ---------------------------------------------------------
  public function schedulePage(Draw $draw)
  {
    $draw->load(['event', 'venues']);

    // Cavaliers Trials (eventType 5)
    if ($draw->event->eventType == 5) {
    
      return view('backend.schedule.cavaliers-trials-schedule', [
        'draw' => $draw,
        'event' => $draw->event,
      ]);
    }

    // Default (individual schedule)
    return view('backend.schedule.individual-schedule', [
      'draw' => $draw,
      'event' => $draw->event,
    ]);
  }



  // ---------------------------------------------------------
  // DATA FOR DATATABLE
  // ---------------------------------------------------------
  public function scheduleData(Draw $draw)
  {
    $eventType = $draw->event->eventType;

    // ---------------------------------------------------
    // CAVALIERS TRIALS (eventType = 5)
    // ---------------------------------------------------
    if ($eventType == 5) {

      $fixtures = Fixture::where('draw_id', $draw->id)
        ->with(['registration1.players', 'registration2.players', 'oop'])
        ->orderBy('bracket_id')
        ->orderBy('round')
        ->orderBy('match_nr')
        ->get()
        ->map(function ($fx) {

          $p1 = $fx->registration1?->players?->pluck('full_name')->join(' / ') ?? 'TBD';
          $p2 = $fx->registration2?->players?->pluck('full_name')->join(' / ') ?? 'TBD';

          return [
            'id' => $fx->id,
            'bracket_id' => $fx->bracket_id,
            'round' => $fx->round,
            'match_nr' => $fx->match_nr,
            'p1' => $p1,
            'p2' => $p2,
            'scheduled_at' => optional($fx->oop)->time,
            'venue_id' => optional($fx->oop)->venue_id,
            'court_label' => optional($fx->oop)->court,
            'scheduled' => $fx->oop ? true : false,
          ];
        });

      // TRIALS NOW USE REAL EVENT VENUES
      $venues = $draw->venues()
    
        ->orderBy('name')
        ->get();

      return response()->json([
        'fixtures' => $fixtures,
        'venues' => $venues,
      ]);
    }

    // ---------------------------------------------------
    // DEFAULT INDIVIDUAL DRAW
    // ---------------------------------------------------
    $fixtures = Fixture::with(['registration1.players', 'registration2.players', 'orderOfPlay'])
      ->where('draw_id', $draw->id)
      ->orderByRaw("
            CASE
                WHEN stage = 'RR' THEN 1
                WHEN stage = 'MAIN' THEN 2
                WHEN stage = 'PLATE' THEN 3
                WHEN stage = 'CONS' THEN 4
                ELSE 5
            END
        ")
      ->orderBy('round')
      ->orderBy('match_nr')
      ->get()
      ->map(function ($fx) {

        $p1 = $fx->registration1?->players?->pluck('full_name')->join(' / ') ?? 'TBD';
        $p2 = $fx->registration2?->players?->pluck('full_name')->join(' / ') ?? 'TBD';

        return [
          'id' => $fx->id,
          'round' => $fx->round,
          'match_nr' => $fx->match_nr,
          'stage' => $fx->stage,
          'p1' => $p1,
          'p2' => $p2,
          'scheduled_at' => optional($fx->orderOfPlay)->time,
          'venue_id' => optional($fx->orderOfPlay)->venue_id,
          'court_label' => optional($fx->orderOfPlay)->court,
          'scheduled' => (int) $fx->scheduled,
        ];
      });

    $venues = $draw->venues()
      ->withPivot('num_courts')
      ->orderBy('venues.id')
      ->get()
      ->map(fn($v) => [
        'id' => $v->id,
        'name' => $v->name,
        'num_courts' => $v->pivot->num_courts,
      ]);

    return response()->json([
      'fixtures' => $fixtures,
      'venues' => $venues,
    ]);
  }

  public function autoScheduleTrials(Request $request, Draw $draw)
  {
    $start = $request->input('start');
    $duration = (int) $request->input('duration', 60);
    $gap = (int) $request->input('gap', 0);

    if (!$start) {
      return response()->json(['error' => 'Start time is required'], 422);
    }

    $start = Carbon::parse($start);

    // ===============================
    // GET VENUE + COURT COUNT FROM DRAW
    // ===============================
    $venue = $draw->venues()->first();

    if (!$venue) {
      return response()->json([
        'error' => 'No venue assigned. Add a venue first.'
      ], 422);
    }

    $venueId = $venue->id;
    $totalCourts = (int) ($venue->pivot->num_courts ?? 1);

    if ($totalCourts < 1)
      $totalCourts = 1;

    // ===============================
    // BUILD FIXTURE QUERY (optional filters)
    // ===============================
    $brackets = $request->input('brackets', []);
    $rounds = $request->input('rounds', []);

    $query = Fixture::where('draw_id', $draw->id)
      ->orderBy('bracket_id')
      ->orderBy('round')
      ->orderBy('match_nr');

    if (!empty($brackets)) {
      $query->whereIn('bracket_id', $brackets);
    }

    if (!empty($rounds)) {
      $query->whereIn('round', $rounds);
    }

    $fixtures = $query->get();

    // ===============================
    // AUTO-SCHEDULE ACROSS COURTS
    // ===============================
    $court = 1;

    foreach ($fixtures as $fx) {

      // ==============================================
      // SKIP BYE MATCHES IN BRACKET 1, ROUND 1
      // ==============================================
      if (
        $fx->bracket_id == 1 &&
        $fx->round == 1 &&
        ($fx->registration1_id == 0 || $fx->registration2_id == 0)
      ) {

        // Mark as unscheduled (just to be safe)
        $fx->scheduled = 0;
        $fx->save();
        continue;
      }

      // ==============================================
      // NORMAL SCHEDULING
      // ==============================================
      OrderOfPlay::updateOrCreate(
        ['fixture_id' => $fx->id],
        [
          'time' => $start->copy(),
          'venue_id' => $venueId,
          'court' => $court,
        ]
      );

      $fx->scheduled = 1;
      $fx->save();

      // Move to next court
      $court++;

      // Wrap courts
      if ($court > $totalCourts) {
        $court = 1;
        $start->addMinutes($duration + $gap);
      }
    }


    return response()->json([
      'success' => true,
      'count' => $fixtures->count(),
      'venue_id' => $venueId,
      'num_courts' => $totalCourts,
      'message' => 'Scheduled successfully'
    ]);
  }

  // ---------------------------------------------------------
  // SAVE A SINGLE MATCH
  // ---------------------------------------------------------
  public function saveFixture(Request $request, Draw $draw)
  {
    $fx = Fixture::where('draw_id', $draw->id)
      ->findOrFail($request->fixture_id);

    // Remove previous
    OrderOfPlay::where('fixture_id', $fx->id)->delete();

    if ($request->scheduled_at) {
      OrderOfPlay::create([
        'fixture_id' => $fx->id,
        'draw_id' => $draw->id,
        'venue_id' => $request->venue_id,
        'court' => $request->court_label,
        'time' => $request->scheduled_at,
      ]);

      $fx->scheduled = 1;
    } else {
      $fx->scheduled = 0;
    }

    $fx->save();

    return response()->json(['status' => 'ok']);
  }


  // ---------------------------------------------------------
  // NEW: CLEAN SERVICE-BASED AUTO-SCHEDULE
  // ---------------------------------------------------------
  public function autoSchedule(Request $request, Draw $draw)
  {
    Log::info("🟦 AutoSchedule invoked", $request->all());

    $engine = new ScheduleEngine();

    // Build venue → court list (matches your existing logic)
    $venues = [];

    foreach ($draw->venues as $v) {
      $venues[$v->id] = [
        'name' => $v->name,
        'courts' => range(1, $v->pivot->num_courts)
      ];
    }

    // Inject into service
    $engine->venues = $venues;
    $engine->startTime = $request->start;
    $engine->autoSchedule($draw->id, $request->duration ?? 75);

    return response()->json(['status' => 'ok']);
  }


  // ---------------------------------------------------------
  // CLEAR SCHEDULE
  // ---------------------------------------------------------
  public function clearSchedule(Draw $draw)
  {
    OrderOfPlay::where('draw_id', $draw->id)->delete();

    Fixture::where('draw_id', $draw->id)->update([
      'scheduled' => 0
    ]);

    return response()->json(['message' => 'All schedules cleared']);
  }

  public function resetTrials(Draw $draw)
  {
    $fixtureIds = $draw->drawFixtures()->pluck('id');

    OrderOfPlay::whereIn('fixture_id', $fixtureIds)->delete();

    Fixture::whereIn('id', $fixtureIds)->update([
      'scheduled' => 0
    ]);

    return response()->json([
      'success' => true,
      'message' => 'Trials schedule reset'
    ]);
  }

  // ---------------------------------------------------------
  // RESET = CLEAR + AUTO
  // ---------------------------------------------------------
  public function resetSchedule(Request $request, Draw $draw)
  {
    $this->clearSchedule($draw);
    return $this->autoSchedule($request, $draw);
  }

  private function autoAdvanceFixture(Fixture $fx)
  {
    $r1 = $fx->registration1_id;
    $r2 = $fx->registration2_id;

    // No players → ignore
    if ($r1 == 0 && $r2 == 0) {
      return;
    }

    // Only one real player → auto winner
    if ($r1 == 0 && $r2 > 0) {
      $winner = $r2;
    } elseif ($r2 == 0 && $r1 > 0) {
      $winner = $r1;
    } else {
      return; // Not a bye → cannot auto advance
    }

    // Mark auto completion
    $fx->winner_registration = $winner;
    $fx->scheduled = 0;
    $fx->save();

    // Feed into parent if exists
    if ($fx->parent_fixture_id) {
      $parent = Fixture::find($fx->parent_fixture_id);

      if ($parent) {
        // Decide slot
        if ($parent->child1_id == $fx->id) {
          $parent->registration1_id = $winner;
        } else {
          $parent->registration2_id = $winner;
        }

        $parent->save();

        // Recursively auto advance next round if possible
        if ($parent->registration1_id == 0 || $parent->registration2_id == 0) {
          $this->autoAdvanceFixture($parent);
        }
      }
    }
  }

}
