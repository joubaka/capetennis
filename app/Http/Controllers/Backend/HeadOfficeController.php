<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\Fixtures;
use App\Http\Controllers\Controller;
use App\Models\CategoryEvent;
use App\Models\Draw;
use App\Models\DrawSetting;
use App\Models\DrawTeam;
use App\Models\DrawType;
use App\Models\Event;
use App\Models\EventRegion;
use App\Models\Team;
use App\Models\TeamRegion;
use App\Models\Venue;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Services\FixtureService;
use Illuminate\Validation\ValidationException;

use function PHPUnit\Framework\isNull;

class HeadOfficeController extends Controller
{
  /**
   * Display a listing of the resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function index()
  {
    //
  }

  /**
   * Show the form for creating a new resource.
   *
   * @return \Illuminate\Http\Response
   */
  public function create()
  {
    //
  }

  /**
   * Store a newly created resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @return \Illuminate\Http\Response
   */
  public function store(Request $request)
  {
    //
  }

  /**
   * Display the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function show($id)
  {
   
    $event = Event::findOrFail($id);

    // Eager-load draws with fixture counts for the team page (#2)
    $event->load([
      'draws' => function ($q) {
        $q->withCount(['fixtures', 'drawFixtures'])
          ->with(['draw_types', 'venues']);
      },
    ]);

    // Categories from teams in this event
    $categories = CategoryEvent::query()
      ->where('event_id', $event->id)
      ->join('categories', 'category_events.category_id', '=', 'categories.id')
      ->orderBy('categories.name')
      ->get([
        'category_events.id as pivot_id',    // category_events.id
        'category_events.category_id',       // categories.id
        'categories.name',                   // display name
      ]);

    $data['categories'] = $categories;
    $data['event'] = $event;
    $data['venues'] = Venue::all();
    $data['teamDrawTypes'] = DrawType::where('type', 'team')
      ->orderBy('drawTypeName')
      ->get();

    $data['individualDrawTypes'] = DrawType::where('type', 'individual')
      ->orderBy('drawTypeName')
      ->get();


    // ===============================
    // INDIVIDUAL SHOW (U/11–U/13 etc.)
    // ===============================
    if ($event->eventType == 6) {
      return view('backend.headOffice.individual-event-show', $data);

      // ===============================
      // CAVALIERS TRIALS (uses brackets)
      // ===============================
    } elseif ($event->eventType == 5) {
      // ----------------------------------------------
// Playing days
// ----------------------------------------------
      $data['playingDays'] = $this->getDatesBetween($event->start_date, $event->endDate);

      // ----------------------------------------------
// LOAD DRAWS WITH FIXTURES + BRACKETS
// ----------------------------------------------
      $draws = $event->draws()
        ->with(['drawFixtures.bracket'])
        ->orderBy('drawName')
        ->get();

      $data['draws'] = [];

      foreach ($draws as $draw) {

        $grouped = $draw->drawFixtures
          ->groupBy(function ($fixture) {
            return optional($fixture->bracket)->name ?? 'No Bracket';
          })
          ->map(function ($bracketGroup) {
            return $bracketGroup->groupBy('round')->sortKeys();
          });

        // Use draw ID to guarantee uniqueness
        $data['draws'][$draw->id] = [
          'name' => $draw->drawName,
          'bracket' => $grouped
        ];
      }

      return view('backend.headOffice.cavaliers-trials-show', $data);


      // ============================================
      // ⭐ NEW: EVENT TYPE 13 → INTERPRO PAGE (RR HUB)
      // ============================================
    } elseif ($event->eventType == 13) {

      return view('backend.headOffice.interpro-event-show', $data);
    }

    // ===============================
    // DEFAULT: TEAM EVENT PAGE
    // ===============================
   
    return view('backend.headOffice.team-event-show', $data);
  }


  /**
   * Show the form for editing the specified resource.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function edit($id)
  {
    //
  }

  /**
   * Update the specified resource in storage.
   *
   * @param  \Illuminate\Http\Request  $request
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function update(Request $request, $id)
  {
    //
  }

  /**
   * Remove the specified resource from storage.
   *
   * @param  int  $id
   * @return \Illuminate\Http\Response
   */
  public function destroy($id)
  {
    //
  }

  public function updateRegionOrder(Request $request)
  {

    foreach ($request->data as $key => $data) {
      if (!$data == 0) {
        $temp = EventRegion::find($data);
        $temp->ordering = ($key + 1);
        $temp->save();
      }
    }

    return $request;
  }

  public function createFormatFixturesTeam(Request $request)
  {
    \Log::debug('[createFormatFixturesTeam] incoming', [
      'request' => $request->all(),
    ]);

    $validatedData = $request->validate([
      'category'   => 'required|array',
      'category.*' => 'exists:category_events,id',
      'event_id'   => 'required|exists:events,id',
      'drawType'   => 'required|integer'
    ]);

    $categories = $validatedData['category'];
    $event_id   = $validatedData['event_id'];
    $drawType   = $validatedData['drawType'];

    \Log::debug('[createFormatFixturesTeam] validated', compact('categories', 'event_id', 'drawType'));

    $regions = EventRegion::where('event_id', $event_id)
      ->with('region')
      ->orderBy('ordering')
      ->get();

    \Log::debug('[createFormatFixturesTeam] regions loaded', [
      'count'   => $regions->count(),
      'regions' => $regions->pluck('id', 'ordering'),
    ]);

    // Check if the number of regions is odd
    if ($regions->count() % 2 != 0) {
      $orderingValues  = $regions->pluck('ordering')->toArray();
      $missingOrdering = null;

      for ($i = 1; $i < count($orderingValues); $i++) {
        if ($orderingValues[$i] - $orderingValues[$i - 1] > 1) {
          $missingOrdering = $orderingValues[$i - 1] + 1;
          break;
        }
      }

      if ($missingOrdering === null) {
        $missingOrdering = $orderingValues[count($orderingValues) - 1] + 1;
      }

      $dummyRegion = (object) [
        'id'       => 0,
        'region'   => 'bye',
        'ordering' => $missingOrdering
      ];

      \Log::debug('[createFormatFixturesTeam] adding dummy region', [
        'dummyRegion' => $dummyRegion
      ]);

      $regions->push($dummyRegion);
    }

    $regions = $regions->sortBy('ordering')->values();

    \Log::debug('[createFormatFixturesTeam] final regions after dummy/sort', [
      'regions' => $regions->map(fn($r) => ['id' => $r->id, 'ordering' => $r->ordering])->all()
    ]);

    $regionFixtures = Fixtures::makeRegionFixtures($regions);

    \Log::debug('[createFormatFixturesTeam] regionFixtures generated', [
      'rounds' => array_keys($regionFixtures),
    ]);

    $categoryNames = CategoryEvent::whereIn('category_events.id', $categories)
      ->join('categories', 'category_events.category_id', '=', 'categories.id')
      ->pluck('categories.name', 'category_events.id');

    \Log::debug('[createFormatFixturesTeam] categoryNames', $categoryNames->toArray());

    $draws       = [];
    $allFixtures = [];

    if ($drawType == 3) {
      $drawName = trim($categoryNames[$categories[0]], 'Boys') . 'Mixed';
      \Log::debug('[createFormatFixturesTeam] creating mixed draw', compact('drawName', 'categories'));

      $draws[] = $draw = $this->createDraw($event_id, $drawType, $drawName);

      $allFixtures = $this->createFixtures($draw, $regionFixtures, $categories);
    } elseif ($drawType == 6) {
      foreach ($categories as $category) {
        $drawName = $categoryNames[$category] ?? 'Unknown';
        \Log::debug('[createFormatFixturesTeam] creating drawType=6 draw', compact('drawName', 'category'));

        $draws[] = $this->createDraw($event_id, $drawType, $drawName);
      }
    } else {
      foreach ($categories as $category) {
        $drawName = $categoryNames[$category] ?? 'Unknown';
        \Log::debug('[createFormatFixturesTeam] creating standard team draw', compact('drawName', 'category'));

        $draws[] = $draw = $this->createDraw($event_id, $drawType, $drawName);

        $fixturesForDraw = $this->createFixtures($draw, $regionFixtures, [$category]);
        $allFixtures     = array_merge($allFixtures, $fixturesForDraw);
      }
    }

    \Log::debug('[createFormatFixturesTeam] completed', [
      'draw_ids'     => collect($draws)->pluck('id'),
      'fixtures_cnt' => count($allFixtures),
    ]);

    return response()->json([
      'draws'    => $draws,
      'fixtures' => $allFixtures
    ]);
  }
  private function createDraw(int $event_id, int $drawType, string $drawName): Draw
  {
    \Log::debug('[createDraw] creating', compact('event_id', 'drawType', 'drawName'));

    $draw              = new Draw();
    $draw->drawName    = $drawName;
    $draw->drawType_id = $drawType;
    $draw->event_id    = $event_id;
    $draw->save();

    $settings            = new DrawSetting();
    $settings->draw_id   = $draw->id;
    $settings->num_sets  = 3;
    $settings->save();

    \Log::debug('[createDraw] created', [
      'draw_id'    => $draw->id,
      'settingsId' => $settings->id,
    ]);

    return $draw;
  }

  private function getTeamsByRegionAndCategory($regionId, $categoryEventIds)
  {
    // Map category_event_id(s) to category_id(s)
    $categoryIds = CategoryEvent::whereIn('id', $categoryEventIds)->pluck('category_id')->all();

    \Log::debug('[getTeamsByRegionAndCategory] mapped', [
        'input_category_event_ids' => $categoryEventIds,
        'mapped_category_ids'      => $categoryIds,
        'regionId'                 => $regionId,
    ]);

    // Now filter teams to Region/Category
    $teams = Team::whereHas('regions', function ($query) use ($regionId, $categoryIds) {
      $query->where('region_id', $regionId)
            ->whereIn('category_id', $categoryIds);
    })->get();

    \Log::debug('[getTeamsByRegionAndCategory] result', [
        'regionId' => $regionId,
        'catIds'   => $categoryIds,
        'count'    => $teams->count(),
        'team_ids' => $teams->pluck('id'),
    ]);

    return $teams;
  }

  private function createFixtures($draw, $regionFixtures, $category)
  {
    \Log::debug('[createFixtures] start', [
      'draw_id'   => $draw->id,
      'draw_type' => $draw->drawType_id,
      'category'  => $category,
      'rounds'    => array_keys($regionFixtures),
    ]);

    $count    = 1;
    $fixtures = [];
    $tieCount = 1;

    foreach ($regionFixtures as $roundKey => $round) {
      foreach ($round as $matchIndex => $match) {
        $region1 = (object) $match[0];
        $region2 = (object) $match[1];

        \Log::debug('[createFixtures] match', [
          'round'    => $roundKey,
          'matchIdx' => $matchIndex,
          'region1'  => $region1,
          'region2'  => $region2,
        ]);

        if ($region1->id == 0 || $region2->id == 0) {
          \Log::debug('[createFixtures] skipping dummy match', [
            'round'    => $roundKey,
            'matchIdx' => $matchIndex,
          ]);
          continue;
        }

        if ($draw->drawType_id == 3) {
          // Mixed: $category is array [boysPivot, girlsPivot]
          $teams1['boys']  = $this->getTeamsByRegionAndCategory($region1->region_id, [$category[0]]);
          $teams1['girls'] = $this->getTeamsByRegionAndCategory($region1->region_id, [$category[1]]);

          $teams2['boys']  = $this->getTeamsByRegionAndCategory($region2->region_id, [$category[0]]);
          $teams2['girls'] = $this->getTeamsByRegionAndCategory($region2->region_id, [$category[1]]);

          \Log::debug('[createFixtures] mixed teams', [
            'round'         => $roundKey,
            'matchIdx'      => $matchIndex,
            'region1_id'    => $region1->region_id,
            'region2_id'    => $region2->region_id,
            'teams1_boys'   => $teams1['boys']->pluck('id'),
            'teams1_girls'  => $teams1['girls']->pluck('id'),
            'teams2_boys'   => $teams2['boys']->pluck('id'),
            'teams2_girls'  => $teams2['girls']->pluck('id'),
          ]);

          $count = Fixtures::createMixedFixtures(
            $draw,
            $draw->drawType_id,
            $region1,
            $region2,
            $teams1,
            $teams2,
            $count,
            $tieCount,
            $roundKey
          );
        } else {
          // Normal team draw: $category is single category_event_id
          $teams1 = $this->getTeamsByRegionAndCategory($region1->region_id, [$category]);
          $teams2 = $this->getTeamsByRegionAndCategory($region2->region_id, [$category]);

          \Log::debug('[createFixtures] normal teams', [
            'round'      => $roundKey,
            'matchIdx'   => $matchIndex,
            'region1_id' => $region1->region_id,
            'region2_id' => $region2->region_id,
            'teams1'     => $teams1->pluck('id'),
            'teams2'     => $teams2->pluck('id'),
          ]);

          if ($teams1->isNotEmpty() && $teams2->isNotEmpty()) {
            $count = Fixtures::createTeamFixtures(
              $draw,
              $draw->drawType_id,
              $region1,
              $region2,
              $teams1,
              $teams2,
              $count,
              $tieCount,
              $roundKey
            );
          } else {
            \Log::debug('[createFixtures] skipped (no teams on one side)', [
              'round'    => $roundKey,
              'matchIdx' => $matchIndex,
            ]);
          }
        }

        $tieCount++;
      }
    }

    \Log::debug('[createFixtures] done', [
      'draw_id'  => $draw->id,
      'last_tie' => $tieCount - 1,
      'last_cnt' => $count - 1,
    ]);

    return $fixtures;
  }

  /**
   * Create a single TEAM draw for an event (with fixtures).
   */






  public function createSingleDrawTeam(Request $request, Event $event, FixtureService $fixtureService)
  {
    \Log::debug('======================================================');
    \Log::debug('[createSingleDrawTeam] START');
    \Log::debug('[STEP 1] Raw Request', $request->all());
    \Log::debug('[STEP 1] Event', [
      'event_id' => $event->id,
      'event_name' => $event->name ?? null,
    ]);

    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    $validated = $request->validate([
      'draw_type_id' => ['required', 'integer', 'exists:draw_types,id'],
      'category_ids' => ['required', 'array'],
      'category_ids.*' => ['integer', 'exists:category_events,id'],
      'drawName' => ['nullable', 'string', 'max:255'],
    ]);

    \Log::debug('[STEP 2] Validated Input', $validated);

    $drawTypeId = (int) $validated['draw_type_id'];
    $categoryEventIds = array_map('intval', $validated['category_ids']);

    /*
    |--------------------------------------------------------------------------
    | DRAW TYPE DEBUG
    |--------------------------------------------------------------------------
    */

    $drawType = DrawType::find($drawTypeId);

    \Log::debug('[STEP 3] Draw Type', [
      'id' => $drawType?->id,
      'name' => $drawType?->name,
      'type' => $drawType?->type,
    ]);

    if (!$drawType || $drawType->type !== 'team') {
      throw ValidationException::withMessages([
        'draw_type_id' => 'Selected draw type is not a valid team format.',
      ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CATEGORY DEBUG
    |--------------------------------------------------------------------------
    */

    $categoryEvents = CategoryEvent::with('category')
      ->whereIn('id', $categoryEventIds)
      ->get();

    \Log::debug('[STEP 4] Category Events Loaded', [
      'count' => $categoryEvents->count(),
      'data' => $categoryEvents->map(function ($ce) {
        return [
          'category_event_id' => $ce->id,
          'category_id' => $ce->category_id,
          'category_name' => $ce->category?->name,
          'event_id' => $ce->event_id,
        ];
      })->toArray(),
    ]);

    $categoryIds = $categoryEvents->pluck('category_id')->map(fn($v) => (int) $v)->all();

    /*
    |--------------------------------------------------------------------------
    | DRAW NAME DEBUG
    |--------------------------------------------------------------------------
    */

    $drawName = $validated['drawName'] ?? 'AUTO GENERATED';

    \Log::debug('[STEP 5] Draw Name Resolved', [
      'draw_name' => $drawName,
    ]);

    /*
    |--------------------------------------------------------------------------
    | LOAD STRUCTURE
    |--------------------------------------------------------------------------
    */

    $event->loadMissing([
      'regions.teams.players',
      'regions.teams.team_players_no_profile',
    ]);

    \Log::debug('[STEP 6] Event Regions Structure', [
      'region_count' => $event->regions->count(),
    ]);

    $participatingTeams = collect();

    foreach ($event->regions as $region) {

      \Log::debug('[REGION]', [
        'region_id' => $region->id,
        'region_name' => $region->region_name ?? $region->short_name ?? null,
        'team_count' => $region->teams->count(),
      ]);

      foreach ($region->teams as $team) {

        \Log::debug('[TEAM]', [
          'team_id' => $team->id,
          'team_name' => $team->name,
          'category_event_id' => $team->category_event_id,
        ]);

        if (!in_array((int) $team->category_event_id, $categoryEventIds, true)) {
          \Log::debug('[TEAM SKIPPED] Not in selected categories');
          continue;
        }

        $profilePlayers = $team->players ?? collect();
        $noProfilePlayers = $team->team_players_no_profile ?? collect();

        $profileCount = $profilePlayers->count();
        $noProfileCount = $noProfilePlayers->count();

        // OPTION 1 LOGIC — PROFILE TAKES PRIORITY
        if ($profileCount > 0) {
          $totalPlayers = $profileCount;
          $source = 'profile';
        } else {
          $totalPlayers = $noProfileCount;
          $source = 'no_profile';
        }

        \Log::debug('[TEAM PLAYERS DETAIL]', [
          'team_id' => $team->id,
          'profile_player_ids' => $profilePlayers->pluck('id')->all(),
          'no_profile_ids' => $noProfilePlayers->pluck('id')->all(),
          'profile_count' => $profileCount,
          'no_profile_count' => $noProfileCount,
          'used_source' => $source,
          'final_player_count_used' => $totalPlayers,
        ]);

        $participatingTeams->push([
          'team' => $team,
          'region' => $region->region_name ?? $region->short_name ?? $region->id,
          'players' => $totalPlayers,
        ]);
      }
    }

    \Log::debug('[STEP 7] Participating Teams Summary', [
      'count' => $participatingTeams->count(),
      'teams' => $participatingTeams->map(fn($t) => [
        'team_id' => $t['team']->id,
        'team_name' => $t['team']->name,
        'region' => $t['region'],
        'player_count' => $t['players'],
      ])->toArray(),
    ]);

    if ($participatingTeams->isEmpty()) {
      \Log::error('[ERROR] No participating teams found');
      throw ValidationException::withMessages([
        'category_ids' => 'No teams found for selected categories.',
      ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PLAYER COUNT CONSISTENCY
    |--------------------------------------------------------------------------
    */

    $uniqueCounts = $participatingTeams->pluck('players')->unique()->values();

    \Log::debug('[STEP 8] Player Count Analysis', [
      'unique_counts' => $uniqueCounts->toArray(),
    ]);

    if ($uniqueCounts->count() > 1) {
      \Log::error('[ERROR] Mismatched player counts detected');
      throw ValidationException::withMessages([
        'category_ids' => 'Teams do not have matching player counts.',
      ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE DRAW
    |--------------------------------------------------------------------------
    */

    \Log::debug('[STEP 9] Creating Draw', [
      'event_id' => $event->id,
      'category_ids' => $categoryIds,
      'draw_type_id' => $drawTypeId,
      'draw_name' => $drawName,
    ]);

    $draw = $fixtureService->createSingleDrawAndFixtures(
      $event,
      $categoryIds,
      $drawTypeId,
      $drawName
    );

    $draw->loadCount('fixtures');

    \Log::debug('[STEP 10] Draw Created Successfully', [
      'draw_id' => $draw->id,
      'fixture_count' => $draw->fixtures_count,
    ]);

    \Log::debug('[createSingleDrawTeam] END');
    \Log::debug('======================================================');

    return response()->json([
      'success' => true,
      'draw' => $draw,
    ]);
  }

  /**
   * Preview a team draw before creating it.
   * Returns participating teams, player counts, detected problems and expected fixture count.
   */
  public function previewSingleDrawTeam(Request $request, Event $event, FixtureService $fixtureService)
  {
    // Validate minimal inputs (same rules as create but no DB changes)
    $validated = $request->validate([
        'draw_type_id' => ['required', 'integer', 'exists:draw_types,id'],
        'category_ids' => ['required', 'array'],
        'category_ids.*' => ['integer', 'exists:category_events,id'],
        'drawName' => ['nullable', 'string', 'max:255'],
    ]);

    $drawTypeId = (int) $validated['draw_type_id'];
    $categoryEventIds = $validated['category_ids'];

    if ($drawTypeId === 3 && count($categoryEventIds) !== 2) {
      return response()->json(['success' => false, 'message' => 'Mixed draw requires exactly two categories (boys and girls).'], 422);
    } elseif ($drawTypeId !== 3 && count($categoryEventIds) !== 1) {
      return response()->json(['success' => false, 'message' => 'Please select exactly one category for this draw type.'], 422);
    }

    // Load event structure
    $event->loadMissing(['regions.teams.players']);

    // Map pivot ids -> category ids and names
    $categoryIds = CategoryEvent::whereIn('id', $categoryEventIds)->pluck('category_id')->all();
    $categoryNames = CategoryEvent::whereIn('category_events.id', $categoryEventIds)
      ->join('categories', 'category_events.category_id', '=', 'categories.id')
      ->pluck('categories.name', 'category_events.id');

    // Cast category_events.id values to int for reliable comparison
    $categoryEventIdsInt = array_map('intval', $categoryEventIds);

    // Build participating teams list (region, team name, players count)
    $participating = [];
    foreach ($event->regions as $evRegion) {
      foreach ($evRegion->teams as $team) {
        // teams.category_event_id is a direct column (not a pivot)
        $teamCategoryEventId = (int) $team->category_event_id;
        if ($teamCategoryEventId && in_array($teamCategoryEventId, $categoryEventIdsInt, true)) {
          $playersCount = $team->relationLoaded('players') ? $team->players->count()
                           : (is_countable($team->team_players ?? null) ? count($team->team_players) : 0);

          $participating[] = [
            'team_id' => $team->id,
            'team_name' => $team->name,
            'region' => $evRegion->short_name ?? $evRegion->region_name ?? "Region {$evRegion->id}",
            'players' => $playersCount,
          ];
        }
      }
    }

    if (empty($participating)) {
      return response()->json(['success' => false, 'message' => 'No teams found for the selected category/categories.'], 422);
    }

    // Detect zero-player teams and mismatched counts
    $zeroTeams = array_values(array_filter($participating, fn($t) => $t['players'] === 0));
    $uniqueCounts = array_values(array_unique(array_column($participating, 'players')));
    $mismatched = count($uniqueCounts) > 1;

    // Resolve expected fixtures count using FixtureService generator (no DB changes)
    // Determine candidate category names to request from generator
    $expectedFixtures = 0;
    $drawName = $validated['drawName'] ?? null;

    if (!$drawName) {
      if ($drawTypeId == 3) {
        $firstId = $categoryEventIds[0];
        $baseName = $categoryNames[$firstId] ?? 'Mixed';
        $drawName = trim($baseName, 'Boys') . 'Mixed';
      } else {
        $firstId = $categoryEventIds[0];
        $drawName = $categoryNames[$firstId] ?? 'Team Draw';
      }
    }

    // Map draw type id -> fixture type string used by generator
    $typeMap = [1 => 'singles', 2 => 'doubles', 3 => 'mixed', 4 => 'singles_reverse'];
    $typeKey = $typeMap[$drawTypeId] ?? 'singles';

    // For mixed: generate using "U/X Mixed" category key
    if ($drawTypeId === 3) {
      $boysCat = CategoryEvent::where('id', $categoryEventIds[0])->join('categories', 'category_events.category_id', '=', 'categories.id')->value('categories.name');
      if ($boysCat) {
        $age = (int) filter_var($boysCat, FILTER_SANITIZE_NUMBER_INT);
        $mixedKey = "U/{$age} Mixed";
        $fixtures = $fixtureService->generateEventFixtures($event, 'perType', [$mixedKey]);
        $expectedFixtures = isset($fixtures[$mixedKey]) ? count(array_filter($fixtures[$mixedKey], fn($f) => $f['type'] === 'mixed')) : 0;
      }
    } else {
      // For each selected category, try to find matching detected category key and count matches of requested type
      $detected = $fixtureService->detectCategoriesFromTeams($event);
      foreach ($categoryIds as $catId) {
        $catName = \App\Models\Category::where('id', $catId)->value('name');
        if (!$catName) continue;
        $matched = collect($detected)->first(fn($d) => str_contains(strtolower($d), strtolower($catName)));
        if ($matched) {
          $fixtures = $fixtureService->generateEventFixtures($event, 'perType', [$matched]);
          $list = $fixtures[$matched] ?? [];
          $expectedFixtures += count(array_filter($list, fn($f) => $f['type'] === $typeKey));
        }
      }
    }

    return response()->json([
      'success' => true,
      'drawName' => $drawName,
      'participating' => $participating,
      'zeroTeams' => $zeroTeams,
      'mismatched' => $mismatched,
      'uniqueCounts' => $uniqueCounts,
      'expectedFixtures' => $expectedFixtures,
    ]);
  }

  private function buildRegionFixturesForEvent(int $eventId)
  {
    \Log::debug('[buildRegionFixturesForEvent] start', ['eventId' => $eventId]);

    $regions = EventRegion::where('event_id', $eventId)
      ->with('region')
      ->orderBy('ordering')
      ->get();

    \Log::debug('[buildRegionFixturesForEvent] regions loaded', [
      'count'   => $regions->count(),
      'regions' => $regions->map(fn($r) => ['id' => $r->id, 'ordering' => $r->ordering])->all(),
    ]);

    if ($regions->count() % 2 != 0) {
      $orderingValues  = $regions->pluck('ordering')->toArray();
      $missingOrdering = null;

      for ($i = 1; $i < count($orderingValues); $i++) {
        if ($orderingValues[$i] - $orderingValues[$i - 1] > 1) {
          $missingOrdering = $orderingValues[$i - 1] + 1;
          break;
        }
      }

      if ($missingOrdering === null) {
        $missingOrdering = $orderingValues[count($orderingValues) - 1] + 1;
      }

      $dummyRegion = (object) [
        'id'       => 0,
        'region'   => 'bye',
        'ordering' => $missingOrdering,
      ];

      \Log::debug('[buildRegionFixturesForEvent] adding dummy region', [
        'dummyRegion' => $dummyRegion,
      ]);

      $regions->push($dummyRegion);
    }

    $regions = $regions->sortBy('ordering')->values();

    \Log::debug('[buildRegionFixturesForEvent] final regions', [
      'regions' => $regions->map(fn($r) => ['id' => $r->id, 'ordering' => $r->ordering])->all()
    ]);

    $regionFixtures = Fixtures::makeRegionFixtures($regions);

    \Log::debug('[buildRegionFixturesForEvent] regionFixtures built', [
      'rounds' => array_keys($regionFixtures),
    ]);

    return $regionFixtures;
  }
}
