<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\TeamFixture;
use App\Models\Event;
use App\Models\Draw;
use App\Models\Player;
use App\Models\Venue;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use App\Models\TeamFixtureResult;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TeamFixtureController extends Controller
{
  public function index(Request $request)
  {
    $query = TeamFixture::query()->with([
      'draw:id,drawName,event_id',
      'draw.event:id,name',
      'team1:id,name,surname',
      'team2:id,name,surname',
      'region1Name:id,short_name',
      'region2Name:id,short_name',
    ]);

    $dateCol = Schema::hasColumn('team_fixtures', 'scheduled_at')
      ? 'scheduled_at'
      : (Schema::hasColumn('team_fixtures', 'date') ? 'date' : null);

    // 🔍 Filters
    $query->when(
      $request->event_id,
      fn($q, $eventId) =>
      $q->whereHas('draw.event', fn($evt) => $evt->where('id', $eventId))
    );

    $query->when(
      $request->draw_id,
      fn($q, $drawId) => $q->where('draw_id', $drawId)
    );

    if ($dateCol) {
      $query->when($request->date_from, fn($q, $d) => $q->whereDate($dateCol, '>=', $d));
      $query->when($request->date_to, fn($q, $d) => $q->whereDate($dateCol, '<=', $d));
    }

    $query->when($request->search, function ($q, $term) {
      $q->where(function ($sub) use ($term) {
        $sub->whereHas('homeTeam', fn($t) => $t->where('name', 'like', "%{$term}%"))
          ->orWhereHas('awayTeam', fn($t) => $t->where('name', 'like', "%{$term}%"))
          ->orWhereHas('draw', fn($d) => $d->where('drawName', 'like', "%{$term}%"))
          ->orWhere('round_nr', 'like', "%{$term}%")
          ->orWhere('tie_nr', 'like', "%{$term}%");
      });
    });

    // ============================================================
    // 🔽 Sorting logic (add home_rank_nr as last level)
    // ============================================================
    $allowedSorts = collect(['scheduled_at', 'date', 'id', 'round', 'tie', 'round_nr', 'tie_nr'])
      ->filter(fn($c) => Schema::hasColumn('team_fixtures', $c))
      ->values()
      ->all();

    $defaultSort = 'round_tie';
    $sort = $request->get('sort', $defaultSort);
    $dir = $request->get('dir', 'asc');

    if ($sort === 'round_tie') {
      if (Schema::hasColumn('team_fixtures', 'round') && Schema::hasColumn('team_fixtures', 'tie')) {
        $query->orderBy('round', $dir)->orderBy('tie', $dir);
      } else {
        $query->orderBy('round_nr', $dir)->orderBy('tie_nr', $dir);
      }

      // 🟢 Add home_rank_nr as final sort level
      if (Schema::hasColumn('team_fixtures', 'home_rank_nr')) {
        $query->orderBy('home_rank_nr', 'asc');
      }
    } elseif (in_array($sort, $allowedSorts, true)) {
      $query->orderBy($sort, $dir);
    } else {
      if (Schema::hasColumn('team_fixtures', 'scheduled_at')) {
        $query->orderBy('scheduled_at', 'asc');
      } elseif (Schema::hasColumn('team_fixtures', 'date')) {
        $query->orderBy('date', 'asc');
      } else {
        $query->orderBy('id', 'asc');
      }
    }

    // ============================================================

    $event = Draw::find($request->draw_id)?->event;
    $fixtures = $query->get();

    $events = Event::orderBy('start_date', 'desc')->get(['id', 'name', 'start_date']);
    $draws = Draw::orderBy('id', 'desc')->get(['id', 'drawName', 'event_id']);
    $venues = Venue::orderBy('name')->get(['id', 'name']);
    $allPlayers = Player::all();
 
    return view('backend.team-fixtures.index', compact(
      'fixtures',
      'events',
      'draws',
      'venues',
      'sort',
      'dir',
      'dateCol',
      'allPlayers',
      'event',
    ));
  }

  /**
   * Admin page for fixtures per event.
   * URL: backend/team-fixtures/admin/{event}
   */
  public function admin(Event $event)
   
  {
    // ensure related data is available
    $event->load(['draws', 'regions.teams']);

    $draws = $event->draws()->orderBy('id')->get(['id', 'drawName']);
    // collect teams across regions for this event (if teams are region-scoped)
    $teams = Team::whereIn('region_id', $event->regions->pluck('id'))->orderBy('name')->get(['id', 'name']);
    $venues = Venue::orderBy('name')->get(['id', 'name']);

    // fixtures belonging to any draw of this event
    $fixtures = TeamFixture::with(['draw', 'team1', 'team2', 'venue'])
      ->whereIn('draw_id', $draws->pluck('id'))
      ->orderBy('scheduled_at', 'asc')
      ->get();

    return view('backend.team-fixtures.admin', compact('event', 'draws', 'teams', 'venues', 'fixtures'));
  }

  /**
   * Show create form for a fixture (standalone)
   */
  public function create()
  {
    $draws = Draw::orderBy('id', 'desc')->get(['id', 'drawName', 'event_id']);
    $venues = Venue::orderBy('name')->get(['id', 'name']);
    $teams = Team::orderBy('name')->get(['id', 'name']);

    return view('backend.team-fixtures.create', compact('draws', 'venues', 'teams'));
  }

  /**
   * Store a new TeamFixture created from admin page.
   */
  public function store(Request $request)
  {
    $validated = $request->validate([
      'draw_id' => 'required|integer|exists:draws,id',
      'home_team_id' => 'required|integer|exists:teams,id',
      'away_team_id' => 'required|integer|exists:teams,id|different:home_team_id',
      'round_nr' => 'nullable',
      'tie_nr' => 'nullable',
      'scheduled_at' => 'nullable|date',
      'venue_id' => 'nullable|integer|exists:venues,id',
      'court_label' => 'nullable|string|max:50',
      'duration_min' => 'nullable|integer|min:10|max:480',
      'fixture_type' => 'nullable|string|max:20',
    ]);

    $fx = new TeamFixture();
    $fx->draw_id = $validated['draw_id'];
    // store team ids as expected by model fields (field names may vary per schema)
    $fx->team1_ids = $validated['home_team_id'];
    $fx->team2_ids = $validated['away_team_id'];
    $fx->round_nr = $validated['round_nr'] ?? null;
    $fx->tie_nr = $validated['tie_nr'] ?? null;
    $fx->scheduled_at = $validated['scheduled_at'] ?? null;
    $fx->venue_id = $validated['venue_id'] ?? null;
    $fx->court_label = $validated['court_label'] ?? null;
    $fx->duration_min = $validated['duration_min'] ?? null;
    $fx->fixture_type = $validated['fixture_type'] ?? null;
    $fx->scheduled = $fx->scheduled_at ? 1 : 0;
    $fx->save();

    return redirect()
      ->route('backend.team-fixtures.admin', $fx->draw?->event?->id ?? null)
      ->with('success', 'Fixture created successfully.');
  }

  /**
   * Insert or update scores for a fixture via admin page (AJAX or standard POST).
   * Endpoint: backend/team-fixtures/{team_fixture}/insert-score
   */
  public function insertScore(Request $request, TeamFixture $team_fixture)
  {
    $rules = [];
    for ($i = 1; $i <= 3; $i++) {
      $rules["set{$i}_home"] = 'nullable|integer|min:0';
      $rules["set{$i}_away"] = 'nullable|integer|min:0';
    }
    $validated = $request->validate($rules);

    foreach (range(1, 3) as $i) {
      $home = $validated["set{$i}_home"] ?? null;
      $away = $validated["set{$i}_away"] ?? null;

      if ($home !== null || $away !== null) {
        $winnerId = null;
        $loserId = null;
        if ($home > $away) {
          $winnerId = 1;
          $loserId = 2;
        } elseif ($away > $home) {
          $winnerId = 2;
          $loserId = 1;
        }

        TeamFixtureResult::updateOrCreate(
          ['team_fixture_id' => $team_fixture->id, 'set_nr' => $i],
          [
            'team1_score' => $home,
            'team2_score' => $away,
            'match_winner_id' => $winnerId,
            'match_loser_id' => $loserId,
          ]
        );
      } else {
        TeamFixtureResult::where('team_fixture_id', $team_fixture->id)
          ->where('set_nr', $i)
          ->delete();
      }
    }

    if ($request->ajax()) {
      $team_fixture->load('fixtureResults');
      return response()->json([
        'success' => true,
        'html' => view('backend.team-fixtures.partials.result-col', compact('team_fixture'))->render(),
      ]);
    }

    return redirect()
      ->route('backend.team-fixtures.admin', $team_fixture->draw?->event?->id ?? null)
      ->with('success', 'Scores saved.');
  }

  public function show(TeamFixture $team_fixture)
  {
    $team_fixture->loadMissing([
      'draw:id,drawName,event_id',
      'draw.event:id,name',
      'homeTeam:id,name',
      'awayTeam:id,name',
      'venue:id,name',
    ]);

    return view('backend.team-fixtures.show', compact('team_fixture'));
  }

  public function edit(TeamFixture $team_fixture)
  {
    $team_fixture->loadMissing(['homeTeam', 'awayTeam', 'venue']);
    $venues = Venue::orderBy('name')->get(['id', 'name']);

    return view('backend.team-fixtures.edit', [
      'team_fixture' => $team_fixture,
      'venues' => $venues,
    ]);
  }

  public function update(Request $request, TeamFixture $team_fixture)
  {
    $rules = [];
    for ($i = 1; $i <= 3; $i++) {
      $rules["set{$i}_home"] = 'nullable|integer|min:0';
      $rules["set{$i}_away"] = 'nullable|integer|min:0';
    }

    $validated = $request->validate($rules);

    foreach (range(1, 3) as $i) {
      $home = $validated["set{$i}_home"] ?? null;
      $away = $validated["set{$i}_away"] ?? null;

      if ($home !== null || $away !== null) {
        $winnerId = null;
        $loserId = null;

        if ($home > $away) {
          $winnerId = 1;
          $loserId = 2;
        } elseif ($away > $home) {
          $winnerId = 2;
          $loserId = 1;
        }

        TeamFixtureResult::updateOrCreate(
          ['team_fixture_id' => $team_fixture->id, 'set_nr' => $i],
          [
            'team1_score' => $home,
            'team2_score' => $away,
            'match_winner_id' => $winnerId,
            'match_loser_id' => $loserId,
          ]
        );
      } else {
        TeamFixtureResult::where('team_fixture_id', $team_fixture->id)
          ->where('set_nr', $i)
          ->delete();
      }
    }

    if ($request->ajax()) {
      $team_fixture->load('fixtureResults');
      $lastSet = $team_fixture->fixtureResults->last();
      $winner = null;

      if ($lastSet) {
        if ($lastSet->team1_score > $lastSet->team2_score) {
          $winner = 'home';
        } elseif ($lastSet->team2_score > $lastSet->team1_score) {
          $winner = 'away';
        } else {
          $winner = 'draw';
        }
      }

      return response()->json([
        'success' => true,
        'html' => view('backend.team-fixtures.partials.result-col', compact('team_fixture'))->render(),
        'winner' => $winner,
        'scores' => $team_fixture->fixtureResults->mapWithKeys(function ($r) {
          return [
            "set{$r->set_nr}_home" => $r->team1_score,
            "set{$r->set_nr}_away" => $r->team2_score,
          ];
        }),
      ]);
    }

    return redirect()
      ->route('backend.team-fixtures.index')
      ->with('success', 'Scores updated successfully.');
  }

  public function destroy(TeamFixture $team_fixture)
  {
    $team_fixture->delete();

    return redirect()
      ->route('backend.team-fixtures.index')
      ->with('success', 'Fixture deleted successfully.');
  }

  public function destroyResult(TeamFixture $team_fixture)
  {
    $team_fixture->fixtureResults()->delete();

    if (request()->ajax()) {
      return response()->json([
        'success' => true,
        'html' => '<span class="text-muted">No result</span>',
        'winner' => null,
        'scores' => [],
      ]);
    }

    return redirect()
      ->route('backend.team-fixtures.index')
      ->with('success', 'Result deleted successfully.');
  }

  public function updatePlayers(Request $request, TeamFixture $team_fixture)
  {
    if ($team_fixture->fixture_type === 'singles') {
      $rules = [
        'home_players' => 'array|max:1',
        'home_players.*' => 'integer|exists:players,id',
        'away_players' => 'array|max:1',
        'away_players.*' => 'integer|exists:players,id',
      ];
    } elseif ($team_fixture->fixture_type === 'doubles') {
      $rules = [
        'home_players' => 'array|max:2',
        'home_players.*' => 'integer|exists:players,id',
        'away_players' => 'array|max:2',
        'away_players.*' => 'integer|exists:players,id',
      ];
    } else {
      $rules = [
        'home_players' => 'array',
        'home_players.*' => 'integer|exists:players,id',
        'away_players' => 'array',
        'away_players.*' => 'integer|exists:players,id',
      ];
    }

    $validated = $request->validate($rules);

    $homePlayers = $validated['home_players'] ?? [];
    $awayPlayers = $validated['away_players'] ?? [];

    \App\Models\TeamFixturePlayer::where('team_fixture_id', $team_fixture->id)->delete();

    $rows = [];
    $max = max(count($homePlayers), count($awayPlayers));

    for ($i = 0; $i < $max; $i++) {
      $rows[] = [
        'team_fixture_id' => $team_fixture->id,
        'team1_id' => $homePlayers[$i] ?? null,
        'team2_id' => $awayPlayers[$i] ?? null,
      ];
    }

    if (!empty($rows)) {
      \App\Models\TeamFixturePlayer::insert($rows);
    }

    $team_fixture->load(['team1', 'team2', 'region1Name', 'region2Name']);

    if ($request->ajax()) {
      return response()->json([
        'success' => true,
        'homeHtml' => view('backend.team-fixtures.partials.home-cell', compact('team_fixture'))->render(),
        'awayHtml' => view('backend.team-fixtures.partials.away-cell', compact('team_fixture'))->render(),
      ]);
    }

    return redirect()
      ->route('backend.team-fixtures.index')
      ->with('success', 'Players updated successfully.');
  }

  public function showJson(TeamFixture $fixture)
  {
    return response()->json([
      'id' => $fixture->id,
      'team1_ids' => $fixture->team1_ids ? explode(',', $fixture->team1_ids) : [],
      'team2_ids' => $fixture->team2_ids ? explode(',', $fixture->team2_ids) : [],
    ]);
  }

  public function schedulePage(Draw $draw)
  {
    $draw->load(['event', 'venues']);
    return view('backend.team-schedule.schedule', [
      'draw' => $draw,
      'event' => $draw->event,
    ]);
  }

  public function scheduleData(Draw $draw)
  {
    $fixtures = TeamFixture::with(['team1', 'team2'])
      ->where('draw_id', $draw->id)
      // ✅ Force numeric sorting; null-safe (NULL → 9999 so they appear last)
      ->orderByRaw('COALESCE(NULLIF(round_nr, ""), 9999) + 0 ASC')
      ->orderByRaw('COALESCE(NULLIF(tie_nr, ""), 9999) + 0 ASC')
      ->orderByRaw('COALESCE(NULLIF(home_rank_nr, ""), 9999) + 0 ASC')
      ->orderBy('scheduled_at', 'asc')
      ->get()
      ->map(function ($fx) {
        $p1 = $fx->team1 && $fx->team1->count()
          ? $fx->team1->map(fn($p) => $p->full_name ?? $p->name)->implode(' + ')
          : 'TBD';

        $p2 = $fx->team2 && $fx->team2->count()
          ? $fx->team2->map(fn($p) => $p->full_name ?? $p->name)->implode(' + ')
          : 'TBD';

        return [
          'id' => $fx->id,
          'round' => $fx->round_nr ?? null,
          'match' => $fx->match_nr ?? null,
          'p1' => $p1,
          'p2' => $p2,
          'scheduled_at' => $fx->scheduled_at,
          'venue_id' => $fx->venue_id,
          'court_label' => $fx->court_label,
          'duration_min' => $fx->duration_min,
          'clash_flag' => $fx->clash_flag ?? false,
        ];
      });

    $venues = $draw->venues;
    if ($venues->isEmpty()) {
      $venues = \App\Models\Venue::all()->map(function ($v) {
        $v->pivot = (object) ['num_courts' => 1];
        return $v;
      });
    }

    $venuesArr = $venues->map(fn($v) => [
      'id' => $v->id,
      'name' => $v->name,
      'num_courts' => $v->pivot->num_courts ?? 1,
    ])->values();

    return response()->json([
      'venues' => $venuesArr,
      'fixtures' => $fixtures,
    ]);
  }

  public function scheduleSave(Request $request, Draw $draw)
  {
    $data = $request->validate([
      'fixture_id' => 'required|integer|exists:team_fixtures,id',
      'scheduled_at' => 'nullable|date',
      'venue_id' => 'nullable|integer|exists:venues,id',
      'court_label' => 'nullable|string|max:50',
      'duration_min' => 'nullable|integer|min:20|max:480',
    ]);

    $fx = TeamFixture::where('draw_id', $draw->id)
      ->where('id', $data['fixture_id'])
      ->firstOrFail();

    $fx->scheduled_at = $data['scheduled_at'] ?? null;
    $fx->venue_id = $data['venue_id'] ?? null;
    $fx->court_label = $data['court_label'] ?? null;
    $fx->duration_min = $data['duration_min'] ?? $fx->duration_min;
    $fx->clash_flag = false;
    $fx->scheduled = $fx->scheduled_at ? 1 : 0; // ✅ mark scheduled
    $fx->save();

    return response()->json(['success' => true]);
  }

  public function scheduleBulk(Request $request, Draw $draw)
  {
    $data = $request->validate([
      'rows' => 'required|array',
      'rows.*.id' => 'required|integer|exists:team_fixtures,id',
      'rows.*.scheduled_at' => 'nullable|date',
      'rows.*.venue_id' => 'nullable|integer|exists:venues,id',
      'rows.*.court_label' => 'nullable|string|max:50',
      'rows.*.duration_min' => 'nullable|integer|min:20|max:480',
      'recheck_clashes' => 'sometimes|boolean',
    ]);

    DB::transaction(function () use ($draw, $data) {
      foreach ($data['rows'] as $r) {
        $fx = TeamFixture::where('draw_id', $draw->id)->where('id', $r['id'])->first();
        if (!$fx)
          continue;
        $fx->scheduled_at = $r['scheduled_at'] ?? $fx->scheduled_at;
        $fx->venue_id = $r['venue_id'] ?? $fx->venue_id;
        $fx->court_label = $r['court_label'] ?? $fx->court_label;
        if (array_key_exists('duration_min', $r) && $r['duration_min']) {
          $fx->duration_min = (int) $r['duration_min'];
        }
        $fx->clash_flag = false;
        $fx->scheduled = $fx->scheduled_at ? 1 : 0; // ✅ mark scheduled
        $fx->save();
      }
    });

    if ($request->boolean('recheck_clashes', true)) {
      $this->recomputeTeamClashes($draw);
    }

    return response()->json(['success' => true]);
  }

  public function scheduleAuto(Request $request, Draw $draw)
  {
    $data = $request->validate([
      'start' => 'required|date',
      'end' => 'required|date|after:start',
      'duration' => 'required|integer|min:20|max:480',
      'gap' => 'nullable|integer|min:0|max:120',
      'round' => 'nullable',
      'venues' => 'nullable|array',
      'venues.*' => 'integer|exists:venues,id',
      'rank_venue_map' => 'nullable|array',
    ]);

    $gap = (int) ($data['gap'] ?? 0);

    // 🔹 Normalize "round" input — can be single value, CSV string, or array
    $rounds = collect(
      is_array($data['round'])
      ? $data['round']
      : explode(',', (string) $data['round'])
    )
      ->map(fn($r) => trim($r))
      ->filter(fn($r) => $r !== '')
      ->values();

    // 🔹 Load fixtures (for all requested rounds, or all if none specified)
    $q = TeamFixture::where('draw_id', $draw->id)
      ->whereNull('scheduled_at');

    if ($rounds->isNotEmpty()) {
      $q->whereIn('round_nr', $rounds);
    }

    $fixtures = $q->orderByRaw('COALESCE(NULLIF(round_nr, ""), 9999) + 0 ASC')
      ->orderByRaw('COALESCE(NULLIF(tie_nr, ""), 9999) + 0 ASC')
      ->orderByRaw('COALESCE(NULLIF(home_rank_nr, ""), 9999) + 0 ASC')
      ->get();

    // 🔹 Venue logic stays identical
    $venues = !empty($data['venues'])
      ? $draw->venues()->whereIn('venues.id', $data['venues'])->get()
      : $draw->venues;

    $start = Carbon::parse($data['start']);
    $end = Carbon::parse($data['end']);

    $slotsByVenue = [];
    foreach ($venues as $v) {
      $courts = max(1, (int) ($v->pivot->num_courts ?? $v->num_courts ?? 1));
      for ($c = 1; $c <= $courts; $c++) {
        $cursor = $start->copy();
        while ($cursor->lt($end)) {
          $from = $cursor->copy();
          $to = $cursor->copy()->addMinutes($data['duration']);
          if ($to->gt($end))
            break;

          $timeKey = $from->format('Y-m-d H:i');
          $slotsByVenue[$v->id][$timeKey][] = [
            'venue_id' => $v->id,
            'court' => $c,
            'from' => $from,
            'to' => $to,
            'booked' => false,
          ];
          $cursor = $to->copy()->addMinutes($gap);
        }
      }
    }

    // 🔹 Mark existing bookings
    TeamFixture::where('draw_id', $draw->id)
      ->whereNotNull('scheduled_at')
      ->get()
      ->each(function ($fx) use (&$slotsByVenue) {
        $from = Carbon::parse($fx->scheduled_at);
        $timeKey = $from->format('Y-m-d H:i');
        $venueId = $fx->venue_id;
        if (isset($slotsByVenue[$venueId][$timeKey])) {
          foreach ($slotsByVenue[$venueId][$timeKey] as &$slot) {
            if ((int) $slot['court'] === (int) str_replace('Court ', '', $fx->court_label)) {
              $slot['booked'] = true;
            }
          }
        }
      });

    // 🔹 Fallback rank→venue map
    $defaultMap = [1 => 1, 2 => 1, 3 => 1, 4 => 1, 5 => 1, 6 => 2, 7 => 2, 8 => 2];
    $rankVenueMap = $data['rank_venue_map'] ?? $defaultMap;

    // 🔹 Flatten all available time slots per venue
    $slotQueues = [];
    foreach ($slotsByVenue as $venueId => $timeGroups) {
      $queue = [];
      foreach (collect($timeGroups)->sortKeys() as $timeKey => $venueSlots) {
        foreach ($venueSlots as $slot) {
          $queue[] = $slot;
        }
      }
      $slotQueues[$venueId] = $queue;
    }

    $slotPointers = array_fill_keys(array_keys($slotQueues), 0);
    $assigned = [];

    // 🔹 Assign fixtures to slots
    foreach ($fixtures as $fx) {
      $targetVenue = $rankVenueMap[$fx->home_rank_nr] ?? null;
      if (!$targetVenue || empty($slotQueues[$targetVenue]))
        continue;

      $index = $slotPointers[$targetVenue] ?? 0;
      if (!isset($slotQueues[$targetVenue][$index]))
        continue;

      $slot = &$slotQueues[$targetVenue][$index];
      while ($slot['booked'] && isset($slotQueues[$targetVenue][++$index])) {
        $slot = &$slotQueues[$targetVenue][$index];
      }

      if (!$slot['booked']) {
        $fx->scheduled_at = $slot['from']->copy();
        $fx->venue_id = $slot['venue_id'];
        $fx->court_label = "Court {$slot['court']}";
        $fx->duration_min = (int) $data['duration'];
        $fx->scheduled = 1;
        $fx->save();

        $slot['booked'] = true;

        $assigned[] = [
          'fixture_id' => $fx->id,
          'venue_id' => $slot['venue_id'],
          'court' => "Court {$slot['court']}",
          'scheduled_at' => $slot['from']->format('Y-m-d H:i'),
          'home_rank' => $fx->home_rank_nr,
          'targetVenue' => $targetVenue,
        ];

        $slotPointers[$targetVenue] = $index + 1;
      }
    }

    $this->recomputeTeamClashes($draw);

    return response()->json([
      'success' => true,
      'assigned' => $assigned,
      'rounds_processed' => $rounds, // 👈 helpful debug info
      'rankvenuemap' => $rankVenueMap,
      'count' => count($assigned)
    ]);
  }

  protected function recomputeTeamClashes(Draw $draw): void
  {
    $fx = TeamFixture::with('fixturePlayers')
      ->where('draw_id', $draw->id)
      ->whereNotNull('scheduled_at')
      ->get();

    DB::table('team_fixtures')
      ->whereIn('id', $fx->pluck('id'))
      ->update(['clash_flag' => false]);

    $sorted = $fx->sortBy('scheduled_at')->values();

    for ($i = 0; $i < $sorted->count(); $i++) {
      for ($j = $i + 1; $j < $sorted->count(); $j++) {
        $a = $sorted[$i];
        $b = $sorted[$j];

        $aStart = Carbon::parse($a->scheduled_at);
        $bStart = Carbon::parse($b->scheduled_at);

        $aEnd = $aStart->copy()->addMinutes((int) ($a->duration_min ?: 120));
        $bEnd = $bStart->copy()->addMinutes((int) ($b->duration_min ?: 120));

        if ($bStart->gte($aEnd)) {
          break;
        }

        $aPlayers = $a->fixturePlayers->pluck('team1_id')
          ->merge($a->fixturePlayers->pluck('team2_id'))
          ->filter()->unique()->toArray();

        $bPlayers = $b->fixturePlayers->pluck('team1_id')
          ->merge($b->fixturePlayers->pluck('team2_id'))
          ->filter()->unique()->toArray();

        $clash = count(array_intersect($aPlayers, $bPlayers)) > 0;

        if ($clash) {
          DB::table('team_fixtures')
            ->whereIn('id', [$a->id, $b->id])
            ->update(['clash_flag' => true]);
        }
      }
    }
  }

  public function scheduleClear(Draw $draw)
  {
    TeamFixture::where('draw_id', $draw->id)
      ->update([
        'scheduled_at' => null,
        'venue_id' => null,
        'court_label' => null,
        'clash_flag' => false,
        'scheduled' => 0, // ✅ reset
      ]);

    return response()->json([
      'success' => true,
      'message' => 'All schedules cleared for this draw.',
    ]);
  }

  public function scheduleReset(Request $request, Draw $draw)
  {
    TeamFixture::where('draw_id', $draw->id)
      ->update([
        'scheduled_at' => null,
        'venue_id' => null,
        'court_label' => null,
        'clash_flag' => false,
        'scheduled' => 0, // ✅ reset
      ]);

    return $this->scheduleAuto($request, $draw);
  }

  // FixtureController.php
  public function byVenue($eventId, $venueId)
  {
    $event = Event::findOrFail($eventId);

    $fixtures = TeamFixture::with(['team1', 'team2', 'venue'])
      ->whereIn('draw_id', $event->draws->pluck('id'))
      ->where('venue_id', $venueId)
      ->orderBy('scheduled_at', 'asc')
      ->orderBy('round_nr', 'asc')
      ->orderBy('tie_nr', 'asc')
      ->orderBy('home_rank_nr', 'asc')
      ->get();

    // 🧭 Custom weekday order (Fri → Sat → Sun)
    $order = ['Fri' => 1, 'Sat' => 2, 'Sun' => 3];

    $fixtures = $fixtures->sortBy(function ($fx) use ($order) {
      $day = \Carbon\Carbon::parse($fx->scheduled_at)->format('D');
      return sprintf(
        '%02d-%s',
        $order[$day] ?? 99,
        \Carbon\Carbon::parse($fx->scheduled_at)->format('Y-m-d H:i:s')
      );
    });

    \Log::info('[byVenue] Final sorted fixture order', [
      'venue_id' => $venueId,
      'event_id' => $eventId,
      'by_day_count' => $fixtures->groupBy(fn($fx) => \Carbon\Carbon::parse($fx->scheduled_at)->format('D'))->map->count(),
    ]);

    $venue = Venue::findOrFail($venueId);

    return view('frontend.fixture.byVenue', compact('event', 'venue', 'fixtures'));
  }

  public function orderOfPlay($eventId, $venueId, $date)
  {
    $event = Event::findOrFail($eventId);
    $venue = Venue::findOrFail($venueId);

    $query = TeamFixture::with([
      'fixturePlayers',
      'team1',
      'team2',
      'region1Name',
      'region2Name',
      'draw',
    ])->where('venue_id', $venueId);

    // 🗓 Handle "all" vs specific date
    if (strtolower($date) !== 'all') {
      $query->whereDate('scheduled_at', $date);
    } else {
      // Show all fixtures for this venue between Friday–Sunday of the event
      $start = \Carbon\Carbon::parse('Friday this week');
      $end = $start->copy()->addDays(2); // Sunday
      $query->whereBetween('scheduled_at', [$start->startOfDay(), $end->endOfDay()]);
    }

    $fixtures = $query
     
     
      ->orderBy('scheduled_at', 'asc')
      ->orderBy('round_nr', 'asc')
      ->orderBy('tie_nr', 'asc')
      ->orderBy('home_rank_nr', 'asc')
      ->get();

    // 🧭 Custom weekday sorting logic (Fri → Sat → Sun)
    $order = ['Fri' => 1, 'Sat' => 2, 'Sun' => 3];

    $fixtures = $fixtures->sortBy(function ($fx) use ($order) {
      $day = \Carbon\Carbon::parse($fx->scheduled_at)->format('D');
      return sprintf(
        '%02d-%s',
        $order[$day] ?? 99,
        \Carbon\Carbon::parse($fx->scheduled_at)->format('Y-m-d H:i:s')
      );
    });

    // 🧾 Log grouped result
    \Log::info('[orderOfPlay] Sorted fixtures', [
      'venue_id' => $venueId,
      'event_id' => $eventId,
      'date' => $date,
      'total' => $fixtures->count(),
      'by_day_count' => $fixtures->groupBy(fn($fx) => \Carbon\Carbon::parse($fx->scheduled_at)->format('D'))->map->count(),
      'sample' => $fixtures->take(5)->map(function ($fx) {
        return [
          'id' => $fx->id,
          'scheduled_at' => $fx->scheduled_at,
          'day' => \Carbon\Carbon::parse($fx->scheduled_at)->format('D'),
          'time' => \Carbon\Carbon::parse($fx->scheduled_at)->format('H:i'),
        ];
      })->toArray(),
    ]);

    return view('frontend.fixture.orderOfPlay', compact('event', 'venue', 'fixtures', 'date'));
  }

  public function recreateFixturesForDraw($drawId)
  {
    try {
      // 1️⃣ Load the Draw record
      $draw = \App\Models\Draw::with('event')->findOrFail($drawId);

      // 2️⃣ Run the service to rebuild only this draw
      app(\App\Services\FixtureService::class)->rebuildForDraw($draw);

      return response()->json([
        'success' => true,
        'message' => "Fixtures recreated successfully for {$draw->drawName}."
      ]);
    } catch (\Throwable $e) {
      \Log::error('[TeamFixtureController] recreateFixturesForDraw error', [
        'draw_id' => $drawId,
        'error' => $e->getMessage(),
      ]);

      return response()->json([
        'success' => false,
        'message' => 'Error recreating fixtures: ' . $e->getMessage(),
      ], 500);
    }
  }

}
