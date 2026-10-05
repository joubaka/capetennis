<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\TeamFixture;
use App\Models\Event;
use App\Models\Draw;
use App\Models\Player;
use App\Models\Venue;
use App\Models\Team;
use App\Models\TeamTie;
use App\Models\TeamFixturePlayer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use App\Models\TeamFixtureResult;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Models\NoProfileTeamPlayer;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use App\Domain\Draws\Guards\DrawGuard;
use App\Services\TeamFixtureScoreService;
use App\Services\PublicTournamentVisibility;

class TeamFixtureController extends Controller
{
  public function index(Request $request)
  {
    $eventIds = $this->managedEventIds($request);
    $query = TeamFixture::query()->with([
      'draw:id,drawName,event_id,team_draw_selection,team_format_snapshot',
      'draw.event:id,name',
      'team1:id,name,surname',
      'team2:id,name,surname',
      'region1Name:id,short_name,region_name',
      'region2Name:id,short_name,region_name',
      'teamTie:id,draw_id,round_nr,tie_nr,home_team_id,away_team_id',
      'fixturePlayers.player1',
      'fixturePlayers.player2',
      'fixturePlayers.noProfile1',
      'fixturePlayers.noProfile2',
      'fixtureResults',
      'venue',
      'teamTie.homeTeam:id,name',
      'teamTie.awayTeam:id,name',
    ]);

    if ($eventIds !== null) {
      $query->whereHas('draw', fn($draw) => $draw->whereIn('event_id', $eventIds));
    }

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

    $event = Draw::when($eventIds !== null, fn($draw) => $draw->whereIn('event_id', $eventIds))->find($request->draw_id)?->event;
    $fixtures = $query->paginate(100)->withQueryString();
    app(\App\Services\TeamFixtureLineupPresenter::class)->prepare($fixtures->getCollection());

    $events = Event::when($eventIds !== null, fn($query) => $query->whereIn('id', $eventIds))
      ->orderBy('start_date', 'desc')->get(['id', 'name', 'start_date']);
    $draws = Draw::when($eventIds !== null, fn($query) => $query->whereIn('event_id', $eventIds))
      ->orderBy('id', 'desc')->get(['id', 'drawName', 'event_id']);
    $venues = Venue::orderBy('name')->get(['id', 'name']);
    $playerIds = TeamFixturePlayer::query()
      ->when($eventIds !== null, fn($query) => $query->whereHas(
        'fixture.draw', fn($draw) => $draw->whereIn('event_id', $eventIds)
      ))
      ->get(['team1_id', 'team2_id'])->flatMap(fn($row) => [$row->team1_id, $row->team2_id])
      ->filter()->unique();
    $allPlayers = Player::whereIn('id', $playerIds)->get();

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
    $this->authorize('event-draw.view', $event);

    // ensure related data is available
    $event->load(['draws', 'regions.teams']);

    $draws = $event->draws()->orderBy('id')->get(['id', 'drawName']);
    // collect teams across regions for this event (if teams are region-scoped)
    $teams = Team::where(function ($query) use ($event) {
      $query->whereHas('category', fn($category) => $category->where('event_id', $event->id))
        ->orWhereIn('region_id', $event->regions->pluck('id'));
    })->orderBy('name')->get(['id', 'name']);
    $venues = Venue::orderBy('name')->get(['id', 'name']);

    // fixtures belonging to any draw of this event
    $fixtures = TeamFixture::with(['draw', 'team1', 'team2', 'venue', 'teamTie.homeTeam', 'teamTie.awayTeam'])
      ->whereIn('draw_id', $draws->pluck('id'))
      ->orderBy('scheduled_at', 'asc')
      ->get();

    return view('backend.team-fixtures.admin', compact('event', 'draws', 'teams', 'venues', 'fixtures'));
  }

  /**
   * Show create form for a fixture (standalone)
   */
  public function create(Request $request)
  {
    $eventIds = $this->managedEventIds($request);
    $draws = Draw::when($eventIds !== null, fn($query) => $query->whereIn('event_id', $eventIds))
      ->orderBy('id', 'desc')->get(['id', 'drawName', 'event_id']);
    $venues = Venue::orderBy('name')->get(['id', 'name']);
    $teams = Team::when($eventIds !== null, fn($query) => $query->whereHas(
      'category', fn($category) => $category->whereIn('event_id', $eventIds)
    ))->orderBy('name')->get(['id', 'name']);

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
      'round_nr' => 'required|integer|min:1',
      'tie_nr' => 'required|integer|min:1',
      'scheduled_at' => 'nullable|date',
      'venue_id' => 'nullable|integer|exists:venues,id',
      'court_label' => 'nullable|string|max:50',
      'duration_min' => 'nullable|integer|min:10|max:480',
      'fixture_type' => 'nullable|integer|in:1,2,3,4',
    ]);

    $draw = \App\Models\Draw::findOrFail($validated['draw_id']);
    $this->authorize('team-fixture.update', $draw);
    DrawGuard::requireMutable($draw, 'create team fixture');
    DrawGuard::requireUnpublished($draw, 'create team fixture');

    $teamEventIds = Team::whereIn('id', [
      $validated['home_team_id'],
      $validated['away_team_id'],
    ])->with('category:id,event_id')->get()
      ->map(fn(Team $team) => (int) $team->category?->event_id);

    if ($teamEventIds->count() !== 2 || $teamEventIds->contains(fn(int $eventId) => $eventId !== (int) $draw->event_id)) {
      return back()->withErrors([
        'home_team_id' => 'Both teams must belong to the draw event.',
      ])->withInput();
    }

    $fx = DB::transaction(function () use ($validated, $draw) {
      $draw = \App\Models\Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
      DrawGuard::requireMutable($draw, 'create team fixture');
      DrawGuard::requireUnpublished($draw, 'create team fixture');
      abort_if($draw->team_format_snapshot !== null, 409, 'Generate rubbers from the draw pairing format instead of adding individual rubbers.');
      $tieNumberInUse = TeamTie::where('draw_id', $draw->id)
        ->where('round_nr', $validated['round_nr'])
        ->where('tie_nr', $validated['tie_nr'])
        ->where(function ($query) use ($validated) {
          $query->where('home_team_id', '!=', $validated['home_team_id'])
            ->orWhere('away_team_id', '!=', $validated['away_team_id']);
        })->exists();

      if ($tieNumberInUse) {
        throw ValidationException::withMessages([
          'tie_nr' => 'This tie number is already used by another pairing in the round.',
        ]);
      }

      $tie = TeamTie::query()->firstOrCreate([
        'draw_id' => $draw->id,
        'round_nr' => $validated['round_nr'],
        'home_team_id' => $validated['home_team_id'],
        'away_team_id' => $validated['away_team_id'],
      ], [
        'tie_nr' => $validated['tie_nr'],
        'status' => TeamTie::STATUS_DRAFT,
      ]);

      if ((int) $tie->tie_nr !== (int) $validated['tie_nr']) {
        throw ValidationException::withMessages([
          'tie_nr' => 'These teams already have a different tie number in this round.',
        ]);
      }

      $tie = TeamTie::whereKey($tie->id)->lockForUpdate()->firstOrFail();
      abort_if($tie->isLocked() || $tie->rubbers()->where(fn ($query) => $query
        ->whereHas('fixtureResults')->orWhere('match_status', '!=', \App\Domain\Draws\Enums\FixtureState::STATUS_PENDING))->exists(),
        409, 'A published tie or a tie with play cannot receive additional rubbers.');
      $sequence = ((int) $tie->rubbers()->max('rubber_sequence')) + 1;
      $matchNr = ((int) TeamFixture::where('draw_id', $draw->id)->max('match_nr')) + 1;

      return TeamFixture::create([
        'draw_id' => $draw->id,
        'team_tie_id' => $tie->id,
        'round_nr' => $tie->round_nr,
        'tie_nr' => $tie->tie_nr,
        'match_nr' => $matchNr,
        'rubber_sequence' => $sequence,
        'fixture_type' => $validated['fixture_type'] ?? 1,
        'scheduled_at' => $validated['scheduled_at'] ?? null,
        'venue_id' => $validated['venue_id'] ?? null,
        'court_label' => $validated['court_label'] ?? null,
        'duration_min' => $validated['duration_min'] ?? null,
        'scheduled' => empty($validated['scheduled_at']) ? 0 : 1,
      ]);
    });

    return redirect()
      ->route('backend.team-fixtures.admin', $fx->draw?->event?->id ?? null)
      ->with('success', 'Fixture created successfully.');
  }

  /** Null means unrestricted super-user access; an empty collection means no managed events. */
  private function managedEventIds(Request $request): ?\Illuminate\Support\Collection
  {
    $user = $request->user();
    if ($user?->hasRole('super-user')) {
      return null;
    }

    if (! $user) {
      return collect();
    }

    return DB::table('event_admins')->where('user_id', $user->id)->pluck('event_id')
      ->merge(DB::table('event_convenors')->where('user_id', $user->id)->pluck('event_id'))
      ->map(fn($id) => (int) $id)->unique()->values();
  }

  /**
   * Insert or update scores for a fixture via admin page (AJAX or standard POST).
   * Endpoint: backend/team-fixtures/{team_fixture}/insert-score
   */
  public function insertScore(Request $request, TeamFixture $team_fixture)
  {
    $this->authorize('team-fixture.saveScore', $team_fixture);
    for ($i = 1; $i <= 3; $i++) {
      $rules["set{$i}_home"] = "nullable|required_with:set{$i}_away|integer|min:0";
      $rules["set{$i}_away"] = "nullable|required_with:set{$i}_home|integer|min:0";
    }
    $rules['participant_revision'] = 'nullable|string|size:64';
    $validated = $request->validate($rules);

    app(TeamFixtureScoreService::class)->save($team_fixture, $validated);

    if ($request->ajax()) {
      $team_fixture->load('fixtureResults', 'teamResults');
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
    $this->authorize('team-fixture.view', $team_fixture);

    $team_fixture->loadMissing([
      'draw:id,drawName,event_id,team_draw_selection,team_format_snapshot',
      'draw.event:id,name',
      'homeTeam:id,name',
      'awayTeam:id,name',
      'venue:id,name',
    ]);

    return view('backend.team-fixtures.show', compact('team_fixture'));
  }

  public function edit(TeamFixture $team_fixture)
  {
    $this->authorize('team-fixture.update', $team_fixture);

    $team_fixture->loadMissing(['homeTeam', 'awayTeam', 'venue']);
    $venues = Venue::orderBy('name')->get(['id', 'name']);

    return view('backend.team-fixtures.edit', [
      'team_fixture' => $team_fixture,
      'venues' => $venues,
    ]);
  }

  public function update(Request $request, TeamFixture $team_fixture)
  {
    $this->authorize('team-fixture.update', $team_fixture);

    $rules = [
      'scheduled_at' => ['nullable', 'date'],
      'venue_id' => ['nullable', 'integer', 'exists:venues,id'],
      'court_label' => ['nullable', 'string', 'max:50'],
      'duration_min' => ['nullable', 'integer', 'min:10', 'max:480'],
    ];
    for ($i = 1; $i <= 3; $i++) {
      $rules["set{$i}_home"] = "nullable|required_with:set{$i}_away|integer|min:0";
      $rules["set{$i}_away"] = "nullable|required_with:set{$i}_home|integer|min:0";
    }

    $rules['participant_revision'] = 'nullable|string|size:64';
    $validated = $request->validate($rules);

    DB::transaction(function () use ($team_fixture, $validated) {
      \App\Models\Event::whereKey($team_fixture->draw->event_id)->lockForUpdate()->firstOrFail();
      $schedule = array_intersect_key($validated, array_flip(['scheduled_at', 'venue_id', 'court_label', 'duration_min']));
      if ($schedule !== []) {
        $schedule['fixture_id'] = $team_fixture->id;
        app(\App\Services\TeamScheduleService::class)->save($team_fixture->draw, $schedule);
      }
      if (array_filter($validated, fn($value, $key) => str_starts_with($key, 'set') && $value !== null, ARRAY_FILTER_USE_BOTH)) {
        $this->authorize('team-fixture.saveScore', $team_fixture);
        app(TeamFixtureScoreService::class)->save($team_fixture, $validated);
      }
    });

    if ($request->ajax()) {
      $team_fixture->load('fixtureResults', 'teamResults');
      $winner = $team_fixture->winnerSide();

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
    $this->authorize('team-fixture.update', $team_fixture);

    DB::transaction(function () use ($team_fixture) {
      $draw = Draw::whereKey($team_fixture->draw_id)->lockForUpdate()->firstOrFail();
      $fixture = TeamFixture::whereKey($team_fixture->id)->lockForUpdate()->firstOrFail();
      app(\App\Services\TeamDrawMutationGuard::class)->destructive($draw);
      $fixture->fixturePlayers()->delete();
      $fixture->delete();
    });

    return redirect()
      ->route('backend.team-fixtures.index')
      ->with('success', 'Fixture deleted successfully.');
  }

  public function destroyResult(TeamFixture $team_fixture)
  {
    $this->authorize('team-fixture.saveScore', $team_fixture);

    app(TeamFixtureScoreService::class)->delete($team_fixture);

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
    $this->authorize('team-fixture.update', $team_fixture);

    DB::transaction(function () use ($request, $team_fixture) {
      $draw = Draw::whereKey($team_fixture->draw_id)->lockForUpdate()->firstOrFail();
      $tie = $team_fixture->team_tie_id ? TeamTie::whereKey($team_fixture->team_tie_id)->lockForUpdate()->firstOrFail() : null;
      $team_fixture = TeamFixture::whereKey($team_fixture->id)->lockForUpdate()->firstOrFail();
      app(\App\Services\TeamDrawMutationGuard::class)->fixture($team_fixture);
      abort_if($draw->published || $tie?->isLocked(), 409, 'Published assignments cannot be replaced.');
      $team_fixture->setRelation('teamTie', $tie);
      if (! $team_fixture->teamTie) {
        throw ValidationException::withMessages(['team_tie_id' => 'This legacy fixture has no team tie and cannot accept roster assignments.']);
      }

      if ($team_fixture->isSingles()) {
        $rules = [
          'home_players' => 'array|max:1',
          'home_players.*' => 'integer|exists:players,id',
          'away_players' => 'array|max:1',
          'away_players.*' => 'integer|exists:players,id',
        ];
      } elseif ($team_fixture->isDoubles()) {
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

      $resolver = app(\App\Services\TeamDrawSideResolver::class);
      $validHome = $resolver->side($draw, $tie->homeTeam)->team_players->pluck('player_id')->map(fn ($id) => (int) $id)->all();
      $validAway = $resolver->side($draw, $tie->awayTeam)->team_players->pluck('player_id')->map(fn ($id) => (int) $id)->all();

      if (array_diff($homePlayers, $validHome) || array_diff($awayPlayers, $validAway)) {
        throw ValidationException::withMessages([
          'home_players' => 'Every selected player must belong to the corresponding team roster.',
        ]);
      }

      if ($draw->team_draw_selection['mixed_sides'] ?? []) {
        foreach ([$tie->home_team_id => $homePlayers, $tie->away_team_id => $awayPlayers] as $teamId => $ids) {
          if (count($ids) === 2) {
            try { $resolver->assertMixedSources($draw, $teamId, $ids, []); }
            catch (\InvalidArgumentException $e) { throw ValidationException::withMessages(['home_players' => $e->getMessage()]); }
          }
        }
      }

      // ✅ Delete using Eloquent (fires events)
      $team_fixture->fixturePlayers()->each(fn($p) => $p->delete());

      // ✅ Create using Eloquent (fires events)
      $max = max(count($homePlayers), count($awayPlayers));
      for ($i = 0; $i < $max; $i++) {
          TeamFixturePlayer::create([
              'team_fixture_id' => $team_fixture->id,
              'slot_no' => $i + 1,
              'team1_id' => $homePlayers[$i] ?? null,
              'team2_id' => $awayPlayers[$i] ?? null,
          ]);
      }

    });

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
    $this->authorize('team-fixture.view', $fixture);

    return response()->json([
      'id' => $fixture->id,
      'team1_ids' => $fixture->fixturePlayers()->whereNotNull('team1_id')->pluck('team1_id')->values(),
      'team2_ids' => $fixture->fixturePlayers()->whereNotNull('team2_id')->pluck('team2_id')->values(),
    ]);
  }

  public function schedulePage(Draw $draw)
  {
    $this->authorize('team-fixture.view', $draw);
    $this->authorize('event.manage', $draw->event);
    return redirect()->route('backend.event-venue-schedule.index', ['event' => $draw->event_id, 'draw_ids' => [$draw->id]]);
  }

  public function scheduleData(Draw $draw)
  {
    $this->authorize('team-fixture.view', $draw);

    $fixtures = TeamFixture::with(['fixturePlayers.player1', 'fixturePlayers.player2', 'draw', 'venue'])
      ->where('draw_id', $draw->id)
      ->orderByRaw('COALESCE(NULLIF(round_nr, ""), 9999) + 0 ASC')
      ->orderByRaw('COALESCE(NULLIF(tie_nr, ""), 9999) + 0 ASC')
      ->orderByRaw('COALESCE(NULLIF(home_rank_nr, ""), 9999) + 0 ASC')
      ->orderBy('scheduled_at', 'asc')
      ->get()
      ->map(function ($fx) {
        $homeNames = [];
        $awayNames = [];
        $homeRegionShort = $fx->region1Name?->short_name ?? null;
        $awayRegionShort = $fx->region2Name?->short_name ?? null;

        foreach ($fx->fixturePlayers as $fpRow) {
            // HOME
            if ($fpRow->team1_id && $fpRow->player1) {
                $name = $fpRow->player1->full_name;
                if ($homeRegionShort) $name .= " ({$homeRegionShort})";
                $homeNames[] = $name;
            } elseif ($fpRow->team1_no_profile_id) {
                $np = \App\Models\NoProfileTeamPlayer::find($fpRow->team1_no_profile_id);
                if ($np) {
                    $name = trim($np->name . ' ' . $np->surname);
                    if ($homeRegionShort) $name .= " ({$homeRegionShort})";
                    $homeNames[] = $name;
                }
            }
            // AWAY
            if ($fpRow->team2_id && $fpRow->player2) {
                $name = $fpRow->player2->full_name;
                if ($awayRegionShort) $name .= " ({$awayRegionShort})";
                $awayNames[] = $name;
            } elseif ($fpRow->team2_no_profile_id) {
                $np2 = \App\Models\NoProfileTeamPlayer::find($fpRow->team2_no_profile_id);
                if ($np2) {
                    $name = trim($np2->name . ' ' . $np2->surname);
                    if ($awayRegionShort) $name .= " ({$awayRegionShort})";
                    $awayNames[] = $name;
                }
            }
        }

        $p1 = count($homeNames) ? collect($homeNames)->implode(' + ') : 'TBD';
        $p2 = count($awayNames) ? collect($awayNames)->implode(' + ') : 'TBD';

        return [
            'id' => $fx->id,
            'round' => $fx->round_nr ?? null,
            'tie' => $fx->tie_nr ?? null,
            'match' => $fx->match_nr ?? null,
            'home_rank' => $fx->home_rank_nr ?? null,
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
    $this->authorize('team-fixture.schedule', $draw);

    $data = $request->validate([
      'fixture_id' => 'required|integer|exists:team_fixtures,id',
      'scheduled_at' => 'nullable|date',
      'venue_id' => 'nullable|integer|exists:venues,id',
      'court_label' => 'nullable|string|max:50',
      'duration_min' => 'nullable|integer|min:20|max:480',
    ]);

    app(\App\Services\TeamScheduleService::class)->save($draw, $data);

    return response()->json(['success' => true]);
  }

  public function scheduleBulk(Request $request, Draw $draw)
  {
    $this->authorize('team-fixture.schedule', $draw);

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
      foreach ($data['rows'] as $row) {
        $row['fixture_id'] = $row['id'];
        app(\App\Services\TeamScheduleService::class)->save($draw, $row);
      }
    });

    return response()->json(['success' => true]);
  }

  public function scheduleAuto(Request $request, Draw $draw)
  {
    $this->authorize('team-fixture.schedule', $draw);
    $scheduler = app(\App\Services\TeamScheduleService::class);
    return response()->json($scheduler->automatic(collect([$draw]), $scheduler->validate($request)));
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
    $this->authorize('team-fixture.schedule', $draw);
    app(\App\Services\TeamScheduleService::class)->clear(collect([$draw]));
    return response()->json(['success' => true, 'message' => 'All schedules cleared for this draw.']);
  }

  public function scheduleReset(Request $request, Draw $draw)
  {
    $this->authorize('team-fixture.schedule', $draw);
    $scheduler = app(\App\Services\TeamScheduleService::class);
    return response()->json($scheduler->automatic(collect([$draw]), $scheduler->validate($request), reset: true));
  }

  public function saveRankVenues(Request $request, Draw $draw)
  {
    $this->authorize('team-fixture.schedule', $draw);
    $data = $request->validate(['rank_venue_map' => 'required|array', 'rank_venue_map.*' => 'nullable|integer|exists:venues,id']);
    if (array_diff(array_filter($data['rank_venue_map']), $draw->venues()->pluck('venues.id')->all())) {
      throw ValidationException::withMessages(['rank_venue_map' => 'Select venues configured for this draw.']);
    }
    DB::transaction(function () use ($draw, $data) {
      $locked = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
      app(\App\Services\TeamDrawMutationGuard::class)->schedule($locked);
      \App\Models\RankVenueMapping::where('draw_id', $draw->id)->delete();
      foreach ($data['rank_venue_map'] as $rank => $venue) {
        if ($venue) {
          \App\Models\RankVenueMapping::create(['draw_id' => $draw->id, 'rank' => $rank, 'venue_id' => $venue]);
        }
      }
    });
    return response()->json(['success' => true]);
  }

  // FixtureController.php
  public function byVenue($eventId, $venueId, PublicTournamentVisibility $visibility)
  {
    $event = Event::findOrFail($eventId);
    $visibility->ensureEventIsVisible($event, request()->user());
    $venue = Venue::findOrFail($venueId);
    abort_unless(
      $event->venues()->whereKey($venue->id)->exists()
        || $this->publicVenueFixtureQuery($event, $venue)->exists(),
      404
    );

    $fixtures = $this->publicVenueFixtureQuery($event, $venue)
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

    return view('frontend.fixture.byVenue', compact('event', 'venue', 'fixtures'));
  }

  public function orderOfPlay($eventId, $venueId, string $date, PublicTournamentVisibility $visibility)
  {
    $event = Event::findOrFail($eventId);
    $visibility->ensureEventIsVisible($event, request()->user());
    $venue = Venue::findOrFail($venueId);
    abort_unless(
      $event->venues()->whereKey($venue->id)->exists()
        || $this->publicVenueFixtureQuery($event, $venue)->exists(),
      404
    );

    abort_unless(
      strtolower($date) === 'all'
        || preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1,
      404
    );

    $query = $this->publicVenueFixtureQuery($event, $venue);
    $availableDates = (clone $query)->get()
      ->map(fn(TeamFixture $fixture) => Carbon::parse($fixture->scheduled_at)->toDateString())
      ->unique()
      ->sort()
      ->values();

    if (strtolower($date) !== 'all') {
      $query->whereDate('scheduled_at', $date);
    }

    $fixtures = $query->orderBy('scheduled_at')->get();

    return view('frontend.fixture.orderOfPlay', compact(
      'event', 'venue', 'fixtures', 'date', 'availableDates'
    ));
  }

  private function publicVenueFixtureQuery(Event $event, Venue $venue)
  {
    $drawIds = app(PublicTournamentVisibility::class)
      ->publishedDrawsFor($event, scheduleRequired: true)
      ->pluck('id');

    return TeamFixture::query()
      ->with([
        'draw:id,drawName,event_id,team_draw_selection,team_format_snapshot',
        'team1', 'team2', 'venue', 'region1Name', 'region2Name',
        'fixturePlayers.player1', 'fixturePlayers.player2',
        'fixturePlayers.noProfile1', 'fixturePlayers.noProfile2',
        'fixtureResults',
      ])
      ->whereIn('draw_id', $drawIds)
      ->where('venue_id', $venue->id)
      ->whereNotNull('scheduled_at')
      ->when(Schema::hasColumn('team_fixtures', 'scheduled'), fn($q) => $q->where('scheduled', 1));
  }



  public function recreateFixturesForDraw($drawId)
  {
    try {
      // 1️⃣ Load the Draw record
      $draw = \App\Models\Draw::with('event')->findOrFail($drawId);
      $this->authorize('team-fixture.update', $draw);

      // 2️⃣ Run the service to rebuild only this draw
      app(\App\Services\FixtureService::class)->rebuildForDraw($draw);

      return response()->json([
        'success' => true,
        'message' => "Fixtures recreated successfully for {$draw->drawName}."
      ]);
    } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) {
      throw $e;
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

  // Add these two methods inside the existing TeamFixtureController class

  public function replacePlayerForm(Request $request)
  {
    $eventIds = $this->managedEventIds($request);
    $teams = Team::whereHas('category', fn($q) => $q->when($eventIds !== null, fn($q) => $q->whereIn('event_id', $eventIds))->when($request->query('event_id') ?? $request->query('event'), fn($q, $id) => $q->where('event_id', $id)))->with('category.event')->orderBy('name')->paginate(50)->withQueryString();
    return view('backend.team-fixtures.substitution-teams', compact('teams'));
  }

  /**
   * Create a NoProfileTeamPlayer via AJAX for quick inline additions.
   */
  public function createNoProfile(Request $request)
  {
    abort(409, 'Use the team roster workflow for roster additions or the replacement wizard for a standalone competition identity.');
  }

  /**
   * Replace a player (or no-profile entry) across remaining fixtures in an event.
   * Accepts prefixed ids for unambiguous type selection:
   *  - registered player: "p_{id}" (e.g. p_123)
   *  - no-profile player: "np_{id}" (e.g. np_45)
   *
   * Request fields: event_id, old_id (prefixed string), new_id (prefixed string), side
   */
  public function replacePlayerInEvent(Request $request)
  {
    $event = Event::findOrFail($request->input('event_id'));
    $this->authorize('individual-draw.create', $event);
    return response()->json(['message' => 'Global participant replacement is disabled. Choose the source team and use its replacement wizard; completed and started matches remain unchanged.'], 409);
  }

  public function playerFixtures(Request $request): JsonResponse
  {
    $request->validate([
        'event_id' => 'required|integer|exists:events,id',
        'player' => 'required|string' // expected prefixed: p_{id} or np_{id}
    ]);

    $eventId = (int) $request->input('event_id');
    $playerPref = $request->input('player');

    // parse prefixed id
    [$type, $raw] = explode('_', $playerPref, 2) + [null, null];
    if (!in_array($type, ['p', 'np']) || !is_numeric($raw)) {
        return response()->json(['success' => false, 'message' => 'Invalid player identifier'], 422);
    }

    $playerId = (int) $raw;
    $isPlayer = $type === 'p';
    $isNoProfile = $type === 'np';

    // Query fixtures for this event where fixturePlayers reference this player (either player id or no-profile)
    $fixtures = TeamFixture::whereHas('draw', function ($q) use ($eventId) {
        $q->where('event_id', $eventId);
    })->whereHas('fixturePlayers', function ($q) use ($isPlayer, $isNoProfile, $playerId) {
        if ($isPlayer) {
            $q->where('team1_id', $playerId)->orWhere('team2_id', $playerId);
        } else {
            $q->where('team1_no_profile_id', $playerId)->orWhere('team2_no_profile_id', $playerId);
        }
    })->with([
        'fixturePlayers.player1',
        'fixturePlayers.player2',
        'fixturePlayers.noProfile1',
        'fixturePlayers.noProfile2',
        'venue:id,name',
        'draw:id,drawName'
    ])->orderBy('scheduled_at')->get();

    $out = $fixtures->map(function ($fx) {
        $homeNames = [];
        $awayNames = [];
        $homeRegionShort = $fx->region1Name?->short_name ?? null;
        $awayRegionShort = $fx->region2Name?->short_name ?? null;

        foreach ($fx->fixturePlayers as $fp) {
            if ($fp->team1_id && $fp->player1) {
                $name = $fp->player1->full_name ?? ($fp->player1->name ?? '');
                if ($homeRegionShort) $name .= " ({$homeRegionShort})";
                $homeNames[] = $name;
            } elseif ($fp->team1_no_profile_id && $fp->noProfile1) {
                $npn = trim($fp->noProfile1->name . ' ' . $fp->noProfile1->surname);
                if ($homeRegionShort) $npn .= " ({$homeRegionShort})";
                $homeNames[] = $npn;
            }

            if ($fp->team2_id && $fp->player2) {
                $name = $fp->player2->full_name ?? ($fp->player2->name ?? '');
                if ($awayRegionShort) $name .= " ({$awayRegionShort})";
                $awayNames[] = $name;
            } elseif ($fp->team2_no_profile_id && $fp->noProfile2) {
                $npn = trim($fp->noProfile2->name . ' ' . $fp->noProfile2->surname);
                if ($awayRegionShort) $npn .= " ({$awayRegionShort})";
                $awayNames[] = $npn;
            }
        }

        $homeLabel = count($homeNames) ? collect($homeNames)->implode(' + ') : 'TBD';
        $awayLabel = count($awayNames) ? collect($awayNames)->implode(' + ') : 'TBD';

        return [
            'id' => $fx->id,
            'draw' => $fx->draw?->drawName,
            'round' => $fx->round_nr,
            'tie' => $fx->tie_nr,
            'home' => $homeLabel,
            'away' => $awayLabel,
            'scheduled' => $fx->scheduled_at ? $fx->scheduled_at->format('Y-m-d H:i') : null,
            'venue' => $fx->venue?->name,
            'rowHtml' => view('backend.team-fixtures.partials.replace_player_fixture_row', compact('fx'))->render(),
        ];
    })->values();

    return response()->json(['success' => true, 'fixtures' => $out]);
  }
}
