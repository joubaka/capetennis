<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Fixture;
use App\Models\OrderOfPlay;
use App\Models\Draw;
use App\Services\ScheduleEngine;
use App\Domain\Draws\Services\ScheduleConflictService;
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
    $this->authorize('view', $draw);
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
    $this->authorize('view', $draw);
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
    $this->authorize('modifySchedule', $draw);
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
    $this->authorize('modifySchedule', $draw);
    $fx = Fixture::where('draw_id', $draw->id)
      ->findOrFail($request->fixture_id);

    if ($request->scheduled_at) {
      $venueId = (int) $request->venue_id;
      $conflict = app(ScheduleConflictService::class)->conflict(
        $draw,
        $fx,
        $venueId,
        (string) $request->court_label,
        (string) $request->scheduled_at,
        (int) ($request->duration_minutes ?: 75)
      );
      if ($conflict) {
        return response()->json(['status' => 'error', 'message' => $conflict], 422);
      }
    }

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
    $this->authorize('modifySchedule', $draw);
    Log::info("🟦 AutoSchedule invoked", $request->all());

    $startTime = $request->input('start');
    $duration  = (int) ($request->input('duration', 75));

    if (!$startTime) {
      return response()->json(['error' => 'Start time is required'], 422);
    }

    // Build venue → court list from draw's assigned venues
    $venues = [];
    foreach ($draw->venues as $v) {
      $courts = range(1, max(1, (int) ($v->pivot->num_courts ?? 1)));
      $venues[$v->id] = [
        'name'   => $v->name,
        'courts' => $courts,
      ];
    }

    if (empty($venues)) {
      return response()->json(['error' => 'No venues assigned to this draw.'], 422);
    }

    try {
      $engine = new ScheduleEngine();
      $engine->autoSchedule($draw->id, $duration, $venues, $startTime);
    } catch (\InvalidArgumentException $e) {
      return response()->json(['error' => $e->getMessage()], 422);
    }

    $count = OrderOfPlay::where('draw_id', $draw->id)->whereNotNull('time')->count();

    return response()->json(['status' => 'ok', 'count' => $count]);
  }


  // ---------------------------------------------------------
  // CLEAR SCHEDULE
  // ---------------------------------------------------------
  public function clearSchedule(Draw $draw)
  {
    $this->authorize('modifySchedule', $draw);
    OrderOfPlay::where('draw_id', $draw->id)->delete();

    Fixture::where('draw_id', $draw->id)->update([
      'scheduled' => 0
    ]);

    return response()->json(['message' => 'All schedules cleared']);
  }

  public function resetTrials(Draw $draw)
  {
    $this->authorize('modifySchedule', $draw);
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
    $this->authorize('modifySchedule', $draw);
    $this->clearSchedule($draw);
    return $this->autoSchedule($request, $draw);
  }

  // ---------------------------------------------------------
  // AUDIT DATA
  // ---------------------------------------------------------
  public function auditData(Draw $draw)
  {
    $this->authorize('view', $draw);
    $draw->load(['venues' => fn($q) => $q->withPivot('num_courts')]);

    $fixtures = Fixture::with(['registration1.players', 'registration2.players', 'orderOfPlay'])
      ->where('draw_id', $draw->id)
      ->orderByRaw("CASE WHEN stage='RR' THEN 1 WHEN stage='MAIN' THEN 2 WHEN stage='PLATE' THEN 3 WHEN stage='CONS' THEN 4 ELSE 5 END")
      ->orderBy('round')
      ->orderBy('match_nr')
      ->get();

    $total       = $fixtures->count();
    $scheduled   = $fixtures->filter(fn($f) => $f->orderOfPlay && $f->orderOfPlay->time)->count();
    $unscheduled = $total - $scheduled;

    // ------------------------------------------------------------------
    // Unscheduled fixtures detail
    // ------------------------------------------------------------------
    $unscheduledFixtures = $fixtures
      ->filter(fn($f) => !$f->orderOfPlay || !$f->orderOfPlay->time)
      ->map(fn($f) => [
        'id'    => $f->id,
        'stage' => $f->stage,
        'round' => $f->round,
        'match' => $f->match_nr,
        'p1'    => $f->registration1?->players?->pluck('full_name')->join(' / ') ?? 'TBD',
        'p2'    => $f->registration2?->players?->pluck('full_name')->join(' / ') ?? 'TBD',
      ])->values();

    // ------------------------------------------------------------------
    // Stage completion summary
    // ------------------------------------------------------------------
    $stages = $fixtures->groupBy('stage')->map(function ($group) {
      $total     = $group->count();
      $scheduled = $group->filter(fn($f) => $f->orderOfPlay && $f->orderOfPlay->time)->count();
      return ['total' => $total, 'scheduled' => $scheduled];
    });

    // ------------------------------------------------------------------
    // Court conflicts — use actual gap between consecutive slots per court
    // ------------------------------------------------------------------
    $conflicts = [];
    $slotsByCourtVenue = [];

    foreach ($fixtures as $fx) {
      $oop = $fx->orderOfPlay;
      if (!$oop || !$oop->time || !$oop->venue_id) continue;
      $key = $oop->venue_id . '|' . ($oop->court ?? '?');
      $slotsByCourtVenue[$key][] = [
        'start'      => \Carbon\Carbon::parse($oop->time),
        'fixture_id' => $fx->id,
        'time_str'   => $oop->time,
      ];
    }

    // Infer slot duration = minimum gap between consecutive matches across all courts
    $minGap = null;
    foreach ($slotsByCourtVenue as $key => $slots) {
      usort($slots, fn($a, $b) => $a['start']->timestamp <=> $b['start']->timestamp);
      $slotsByCourtVenue[$key] = $slots;
      for ($i = 1; $i < count($slots); $i++) {
        $gap = $slots[$i]['start']->diffInMinutes($slots[$i-1]['start']);
        if ($gap > 0 && ($minGap === null || $gap < $minGap)) {
          $minGap = $gap;
        }
      }
    }
    $slotDuration = $minGap ?? 60; // fallback 60 min if only one match per court

    foreach ($slotsByCourtVenue as $key => $slots) {
      for ($i = 0; $i < count($slots); $i++) {
        $aStart = $slots[$i]['start'];
        $aEnd   = $aStart->copy()->addMinutes($slotDuration);
        for ($j = $i + 1; $j < count($slots); $j++) {
          $bStart = $slots[$j]['start'];
          // Once bStart >= aEnd there can be no more overlaps (slots are sorted)
          if ($bStart->gte($aEnd)) break;
          $bEnd = $bStart->copy()->addMinutes($slotDuration);
          if ($aStart->lt($bEnd) && $aEnd->gt($bStart)) {
            [$venueId, $court] = explode('|', $key);
            $conflicts[] = [
              'fixture_a' => $slots[$i]['fixture_id'],
              'fixture_b' => $slots[$j]['fixture_id'],
              'venue_id'  => $venueId,
              'court'     => $court,
              'time_a'    => $aStart->format('Y-m-d H:i'),
              'time_b'    => $bStart->format('Y-m-d H:i'),
            ];
          }
        }
      }
    }

    // ------------------------------------------------------------------
    // Player double-booking — overlap-aware using same inferred duration
    // ------------------------------------------------------------------
    $playerConflicts = [];
    $playerSlots     = [];

    foreach ($fixtures as $fx) {
      $oop = $fx->orderOfPlay;
      if (!$oop || !$oop->time) continue;

      $start   = \Carbon\Carbon::parse($oop->time);
      $end     = $start->copy()->addMinutes($slotDuration);
      $players = collect()
        ->merge($fx->registration1?->players ?? [])
        ->merge($fx->registration2?->players ?? []);

      foreach ($players as $p) {
        if (isset($playerSlots[$p->id])) {
          foreach ($playerSlots[$p->id] as $slot) {
            if ($start->lt($slot['end']) && $end->gt($slot['start'])) {
              $playerConflicts[] = [
                'player'    => $p->full_name,
                'time_a'    => $slot['start']->format('Y-m-d H:i'),
                'time_b'    => $start->format('Y-m-d H:i'),
                'fixture_a' => $slot['fixture_id'],
                'fixture_b' => $fx->id,
              ];
            }
          }
        }
        $playerSlots[$p->id][] = [
          'start'      => $start,
          'end'        => $end,
          'fixture_id' => $fx->id,
        ];
      }
    }

    // ------------------------------------------------------------------
    // Per-player match load
    // ------------------------------------------------------------------
    $playerLoad = [];
    foreach ($fixtures as $fx) {
      $players = collect()
        ->merge($fx->registration1?->players ?? [])
        ->merge($fx->registration2?->players ?? []);
      foreach ($players as $p) {
        $playerLoad[$p->id] = $playerLoad[$p->id] ?? ['name' => $p->full_name, 'total' => 0, 'scheduled' => 0];
        $playerLoad[$p->id]['total']++;
        if ($fx->orderOfPlay && $fx->orderOfPlay->time) {
          $playerLoad[$p->id]['scheduled']++;
        }
      }
    }
    usort($playerLoad, fn($a, $b) => $b['total'] <=> $a['total']);

    // ------------------------------------------------------------------
    // Venue usage
    // ------------------------------------------------------------------
    $venueUsage = [];
    foreach ($fixtures as $fx) {
      $oop = $fx->orderOfPlay;
      if (!$oop || !$oop->venue_id) continue;
      $venueUsage[$oop->venue_id] = ($venueUsage[$oop->venue_id] ?? 0) + 1;
    }

    $venues = $draw->venues->map(fn($v) => [
      'id'         => $v->id,
      'name'       => $v->name,
      'num_courts' => $v->pivot->num_courts,
      'matches'    => $venueUsage[$v->id] ?? 0,
    ]);

    return response()->json([
      'total'                => $total,
      'scheduled'            => $scheduled,
      'unscheduled'          => $unscheduled,
      'unscheduled_fixtures' => $unscheduledFixtures,
      'stages'               => $stages,
      'conflicts'            => $conflicts,
      'player_conflicts'     => $playerConflicts,
      'player_load'          => array_values($playerLoad),
      'venues'               => $venues,
    ]);
  }

  // ---------------------------------------------------------
  // SHOW DATA (grouped by date → court)
  // ---------------------------------------------------------
  public function showData(Draw $draw)
  {
    $this->authorize('view', $draw);
    $fixtures = Fixture::with(['registration1.players', 'registration2.players', 'orderOfPlay'])
      ->where('draw_id', $draw->id)
      ->get()
      ->filter(fn($f) => $f->orderOfPlay && $f->orderOfPlay->time)
      ->sortBy(fn($f) => $f->orderOfPlay->time);

    $grouped = [];
    foreach ($fixtures as $fx) {
      $oop  = $fx->orderOfPlay;
      $date = \Carbon\Carbon::parse($oop->time)->format('Y-m-d');
      $court = $oop->court ?: 'Unassigned';
      $grouped[$date][$court][] = [
        'id'    => $fx->id,
        'time'  => \Carbon\Carbon::parse($oop->time)->format('H:i'),
        'stage' => $fx->stage,
        'round' => $fx->round,
        'match' => $fx->match_nr,
        'p1'    => $fx->registration1?->players?->pluck('full_name')->join(' / ') ?? 'TBD',
        'p2'    => $fx->registration2?->players?->pluck('full_name')->join(' / ') ?? 'TBD',
      ];
    }

    return response()->json(['grouped' => $grouped]);
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
