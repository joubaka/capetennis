<?php

namespace App\Http\Controllers\Backend;

use App\Domain\TeamDraw\RubberType;
use App\Services\Fixtures;
use App\Http\Controllers\Controller;
use App\Domain\Draws\Services\StandingsService;
use App\Models\CategoryEvent;
use App\Models\Draw;
use App\Models\DrawSetting;
use App\Models\DrawType;
use App\Models\Event;
use App\Models\EventRegion;
use App\Models\Team;
use App\Models\TeamEventFormat;
use App\Models\TeamFixture;
use App\Models\TeamFixturePlayer;
use App\Models\TeamTie;
use App\Models\Venue;
use App\Services\FeatureFlags;
use App\Services\FixtureService;
use App\Services\TeamDrawGenerationService;
use App\Services\TeamTieGenerationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class HeadOfficeController extends Controller
{
  public function index()
  {
    //
  }

  public function create()
  {
    //
  }

  public function store(Request $request)
  {
    //
  }

  /**
   * ✅ UPDATED:
   * - LEFT: draws for this event
   * - RIGHT: venues that have scheduled fixtures for THIS event (not global)
   */
  public function show($id)
  {
    $event = Event::findOrFail($id);

    \Log::debug('[EVENT SHOW] Start', [
      'event_id' => $event->id,
      'event_name' => $event->name,
      'user_id' => auth()->id(),
      'url' => request()->fullUrl(),
    ]);

    /*
    |--------------------------------------------------------------------------
    | LOAD DRAWS (LIGHTWEIGHT)
    |--------------------------------------------------------------------------
    | Only counts — no nested fixtures, no recursive relations
    */

    $event->load([
      'draws' => function ($q) {
        $q->withCount(['fixtures']) // only count, not load
          ->with(['draw_types'])     // safe relation
          ->orderBy('drawType_id')
          ->orderBy('drawName');
      },
    ]);

    /*
    |--------------------------------------------------------------------------
    | CATEGORIES
    |--------------------------------------------------------------------------
    */

    $categories = CategoryEvent::query()
      ->where('event_id', $event->id)
      ->join('categories', 'category_events.category_id', '=', 'categories.id')
      ->orderBy('categories.name')
      ->get([
        'category_events.id as pivot_id',
        'category_events.category_id',
        'categories.name',
      ]);

    /*
    |--------------------------------------------------------------------------
    | ALL VENUES (simple list only)
    |--------------------------------------------------------------------------
    */

    $allVenues = Venue::select('id', 'name')
      ->orderBy('name')
      ->get();

    /*
    |--------------------------------------------------------------------------
    | SCHEDULED VENUES (EVENT SCOPED + COUNT ONLY)
    |--------------------------------------------------------------------------
    | IMPORTANT: No ->with('fixtures') here.
    | We use withCount instead.
    */

    // Count only fixtures that are actually scheduled for this event+venue.
    // Align the count with the venue fixtures page which requires a real
    // scheduled time. Only include fixtures that have a non-null
    // `scheduled_at` and, if the boolean `scheduled` column exists, require
    // it to be true as well.
    // Build a deterministic count via explicit join/group to ensure the
    // scheduled_fixtures_count attribute is always present and accurate.
    $fixtureScheduledCol = 'team_fixtures.scheduled_at';
    $scheduledFlagCol = 'team_fixtures.scheduled';

    // Include finished fixtures count (fixtures that have at least one result row)
    // We left join team_fixture_results and count distinct fixture ids that have results
    $scheduledQuery = Venue::select(
      'venues.id',
      'venues.name',
      DB::raw('COUNT(team_fixtures.id) as scheduled_fixtures_count'),
      DB::raw('COUNT(DISTINCT CASE WHEN team_fixture_results.id IS NOT NULL THEN team_fixtures.id END) as finished_fixtures_count')
    )
      ->join('team_fixtures', 'team_fixtures.venue_id', '=', 'venues.id')
      ->leftJoin('team_fixture_results', 'team_fixture_results.team_fixture_id', '=', 'team_fixtures.id')
      ->join('draws', 'draws.id', '=', 'team_fixtures.draw_id')
      ->where('draws.event_id', $event->id)
      ->whereNotNull($fixtureScheduledCol)
      ->when(Schema::hasColumn('team_fixtures', 'scheduled'), fn($q) => $q->where($scheduledFlagCol, 1))
      ->groupBy('venues.id', 'venues.name')
      ->orderBy('venues.name')
      ->get();

    $scheduledVenues = $scheduledQuery;

    \Log::debug('[EVENT SHOW] Scheduled venues loaded', [
      'count' => $scheduledVenues->count(),
    ]);
    /*
    |--------------------------------------------------------------------------
    | DRAW TYPES
    |--------------------------------------------------------------------------
    */

    $teamDrawTypes = DrawType::where('type', 'team')
      ->orderBy('drawTypeName')
      ->get();

    $individualDrawTypes = DrawType::where('type', 'individual')
      ->orderBy('drawTypeName')
      ->get();

    $data = [
      'categories' => $categories,
      'event' => $event,
      'allVenues' => $allVenues,
      'venues' => $allVenues,
      'scheduledVenues' => $scheduledVenues,
      'teamDrawTypes' => $teamDrawTypes,
      'individualDrawTypes' => $individualDrawTypes,
      'teamDrawV2Enabled' => FeatureFlags::enabled(FeatureFlags::TEAM_DRAW_V2, $event->id),
      'availableFormats' => FeatureFlags::enabled(FeatureFlags::TEAM_DRAW_V2, $event->id)
        ? TeamEventFormat::with('rubbers')->forEvent($event->id)->orderBy('name')->get()
        : collect(),
    ];

    /*
    |--------------------------------------------------------------------------
    | EVENT TYPE SWITCH
    |--------------------------------------------------------------------------
    */

    if ($event->eventType == 6) {
      return view('backend.headOffice.individual-event-show', $data);

    } elseif ($event->eventType == 5) {

      $data['playingDays'] = $this->getDatesBetween(
        $event->start_date,
        $event->endDate
      );

      // Keep this isolated — this page needs heavy drawFixtures
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

        $data['draws'][$draw->id] = [
          'name' => $draw->drawName,
          'bracket' => $grouped
        ];
      }

      return view('backend.headOffice.cavaliers-trials-show', $data);

    } elseif ($event->eventType == 13) {
      return view('backend.headOffice.interpro-event-show', $data);
    }

    return view('backend.headOffice.team-event-show', $data);
  }
  /**
   * ✅ NEW:
   * Venue fixtures page for a specific event + venue (clickable venue list on right)
   *
   * IMPORTANT:
   * This assumes:
   * - Venue->fixtures() exists
   * - Fixture has scheduled_at OR scheduled flag + scheduled_at
   * - Fixture->draw exists and draw->event_id exists
   */
  public function venueFixtures(Event $event, Venue $venue)
  {
    $fixtures = $venue->fixtures()
      ->with([
        'draw:id,drawName,event_id,drawType_id',
        'region1Name',
        'region2Name',
        'team1',
        'team2',
      ])
      ->whereHas('draw', function ($q) use ($event) {
        $q->where('event_id', $event->id);
      })
      ->where('scheduled', 1)
      ->orderBy('scheduled_at')
      ->orderBy('round_nr')
      ->orderBy('home_rank_nr')
      ->get();




    return view('backend.headOffice.venue-fixtures', [
      'event' => $event,
      'venue' => $venue,
      'fixtures' => $fixtures,
    ]);
  }

  public function edit($id)
  {
    //
  }

  public function update(Request $request, $id)
  {
    //
  }

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
      'category' => 'required|array',
      'category.*' => 'exists:category_events,id',
      'event_id' => 'required|exists:events,id',
      'drawType' => 'required|integer'
    ]);

    $categories = $validatedData['category'];
    $event_id = $validatedData['event_id'];
    $drawType = $validatedData['drawType'];

    $event = \App\Models\Event::findOrFail($event_id);
    $this->authorize('team-draw.createFormat', $event);

    \Log::debug('[createFormatFixturesTeam] validated', compact('categories', 'event_id', 'drawType'));

    $regions = EventRegion::where('event_id', $event_id)
      ->with('region')
      ->orderBy('ordering')
      ->get();

    \Log::debug('[createFormatFixturesTeam] regions loaded', [
      'count' => $regions->count(),
      'regions' => $regions->pluck('id', 'ordering'),
    ]);

    // Check if the number of regions is odd
    if ($regions->count() % 2 != 0) {
      $orderingValues = $regions->pluck('ordering')->toArray();
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
        'id' => 0,
        'region' => 'bye',
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

    $draws = [];
    $allFixtures = [];

    if ($drawType == 3) {
      $drawName = trim($categoryNames[$categories[0]], 'Boys') . 'Mixed';
      $draws[] = $draw = $this->createDraw($event_id, $drawType, $drawName);
      $allFixtures = $this->createFixtures($draw, $regionFixtures, $categories);
    } elseif ($drawType == 6) {
      foreach ($categories as $category) {
        $drawName = $categoryNames[$category] ?? 'Unknown';
        $draws[] = $this->createDraw($event_id, $drawType, $drawName);
      }
    } else {
      foreach ($categories as $category) {
        $drawName = $categoryNames[$category] ?? 'Unknown';
        $draws[] = $draw = $this->createDraw($event_id, $drawType, $drawName);

        $fixturesForDraw = $this->createFixtures($draw, $regionFixtures, [$category]);
        $allFixtures = array_merge($allFixtures, $fixturesForDraw);
      }
    }

    return response()->json([
      'draws' => $draws,
      'fixtures' => $allFixtures
    ]);
  }

  private function createDraw(int $event_id, int $drawType, string $drawName): Draw
  {
    $draw = new Draw();
    $draw->drawName = $drawName;
    $draw->drawType_id = $drawType;
    $draw->event_id = $event_id;
    $draw->save();

    $settings = new DrawSetting();
    $settings->draw_id = $draw->id;
    $settings->num_sets = 3;
    $settings->save();

    return $draw;
  }

  private function getTeamsByRegionAndCategory($regionId, $categoryEventIds)
  {
    $categoryIds = CategoryEvent::whereIn('id', $categoryEventIds)->pluck('category_id')->all();

    $teams = Team::whereHas('regions', function ($query) use ($regionId, $categoryIds) {
      $query->where('region_id', $regionId)
        ->whereIn('category_id', $categoryIds);
    })->get();

    return $teams;
  }

  private function createFixtures($draw, $regionFixtures, $category)
  {
    $count = 1;
    $fixtures = [];
    $tieCount = 1;

    foreach ($regionFixtures as $roundKey => $round) {
      foreach ($round as $matchIndex => $match) {
        $region1 = (object) $match[0];
        $region2 = (object) $match[1];

        if ($region1->id == 0 || $region2->id == 0) {
          continue;
        }

        if ($draw->drawType_id == 3) {
          $teams1['boys'] = $this->getTeamsByRegionAndCategory($region1->region_id, [$category[0]]);
          $teams1['girls'] = $this->getTeamsByRegionAndCategory($region1->region_id, [$category[1]]);
          $teams2['boys'] = $this->getTeamsByRegionAndCategory($region2->region_id, [$category[0]]);
          $teams2['girls'] = $this->getTeamsByRegionAndCategory($region2->region_id, [$category[1]]);

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
          $teams1 = $this->getTeamsByRegionAndCategory($region1->region_id, [$category]);
          $teams2 = $this->getTeamsByRegionAndCategory($region2->region_id, [$category]);

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
          }
        }

        $tieCount++;
      }
    }

    return $fixtures;
  }

  // --- your existing createSingleDrawTeam(), previewSingleDrawTeam(), buildRegionFixturesForEvent() stay unchanged below ---
  // (Keep your current implementations as-is)

  public function createSingleDrawTeam(
    Request                   $request,
    Event                     $event,
    FixtureService            $fixtureService,
    TeamDrawGenerationService $drawGenerator,
    TeamTieGenerationService  $tieGenerator
  ) {
    $this->authorize('team-draw.createFormat', $event);

    $validated = $request->validate([
      'draw_type_id'   => 'required|integer|exists:draw_types,id',
      'drawName'       => 'required|string|max:255',
      'category_ids'   => 'required|array|min:1',
      'category_ids.*' => 'integer|exists:category_events,id',
    ]);

    $draw = $this->createDraw($event->id, (int) $validated['draw_type_id'], $validated['drawName']);

    \Log::debug('[createSingleDrawTeam] request payload', [
      'event_id' => $event->id,
      'draw_id' => $draw->id,
      'draw_type_id' => (int) $validated['draw_type_id'],
      'draw_name' => $validated['drawName'],
      'category_ids' => $validated['category_ids'],
    ]);

    // Store the first category_event pivot on the draw for reference
    $primaryCategoryEventId = $validated['category_ids'][0] ?? null;
    if ($primaryCategoryEventId) {
      $draw->category_event_id = $primaryCategoryEventId;
      $draw->save();
    }

    // ── Resolve teams for all provided category_events ──────────────────────
    $teams = Team::whereIn('category_event_id', $validated['category_ids'])
      ->with(['team_players', 'team_players_no_profile'])
      ->get();

    // Sync teams to the draw
    if ($teams->isNotEmpty()) {
      $draw->teams_in_draw()->sync($teams->pluck('id')->all());
    }

    // ── Resolve the default format for this event ────────────────────────────
    $format = null;
    if (Schema::hasTable('team_event_formats')) {
      $format = TeamEventFormat::where('event_id', $event->id)
        ->where('is_default', true)
        ->with('rubbers')
        ->first();
    }

    // ── Generate ties / placeholder fixtures ───────────────────────────────
    $ties = collect();
    $placeholderFixturesCreated = 0;
    $generatedRubbers = 0;
    $canGenerateTeamDraw = Schema::hasTable('team_ties') && Schema::hasTable('team_fixtures');

    if (!$canGenerateTeamDraw) {
      if (Schema::hasTable('team_fixtures')) {
        $placeholderFixturesCreated = $this->createPlaceholderTeamFixtures($draw, $teams, $format);
      } else {
        \Log::warning('[createSingleDrawTeam] team_fixtures table missing; cannot create placeholder fixtures', [
          'draw_id' => $draw->id,
          'team_ties_table' => Schema::hasTable('team_ties'),
          'team_fixtures_table' => Schema::hasTable('team_fixtures'),
        ]);
      }
    } elseif ($teams->count() < 2) {
      if (Schema::hasTable('team_fixtures')) {
        $placeholderFixturesCreated = $this->createPlaceholderTeamFixtures($draw, $teams, $format);
      }
    } else {
      DB::transaction(function () use ($draw, $teams, $format, $tieGenerator, $drawGenerator, &$ties, &$generatedRubbers) {
        // Standard round-robin schedule
        $ties = $drawGenerator->generate($draw, $teams, $format);

        // ── Generate rubbers (fixtures) for each tie ─────────────────────────
        if ($format && $format->rubbers->isNotEmpty()) {
          foreach ($ties as $tie) {
            try {
              $rubbers = $tieGenerator->generateFromFormat($tie, $format);
              $generatedRubbers += $rubbers->count();
            } catch (\Throwable $e) {
              \Log::warning('[createSingleDrawTeam] Rubber generation skipped for tie', [
                'tie_id' => $tie->id,
                'reason' => $e->getMessage(),
              ]);
            }
          }
        }
      });

      if ($generatedRubbers === 0 && Schema::hasTable('team_fixtures')) {
        $placeholderFixturesCreated = $this->createPlaceholderTeamFixtures($draw, $teams, $format);
      }
    }

    \Log::info('[createSingleDrawTeam] Draw + fixtures created', [
      'draw_id'   => $draw->id,
      'event_id'  => $event->id,
      'teams'     => $teams->count(),
      'ties'      => $ties->count(),
      'format_id' => $format?->id,
    ]);

    return response()->json([
      'success' => true,
      'message' => 'Draw "' . $draw->drawName . '" created successfully.',
      'draw'    => [
        'id'                 => $draw->id,
        'name'               => $draw->drawName,
        'ties_count'         => $ties->count(),
        'placeholder_fixtures' => $placeholderFixturesCreated,
      ],
    ]);
  }

  private function createPlaceholderTeamFixtures(Draw $draw, $teams = null, ?TeamEventFormat $format = null): int
  {
    if (!Schema::hasTable('team_fixtures')) {
      return 0;
    }

    $isMixedPlaceholder = $this->isMixedPlaceholderDraw($draw, $teams);

    $templates = collect();

    if ($format && $format->relationLoaded('rubbers')) {
      $templates = $format->rubbers;
    } elseif ($format) {
      $templates = $format->rubbers()->orderBy('sequence')->get();
    }

    $teamList = $teams ? collect($teams)->values() : collect();
    $homeTeam = $teamList->get(0);
    $awayTeam = $teamList->get(1);
    $isDoublesLike = $this->isDoublesLikePlaceholderDraw($draw, $templates);
    $mixedSides = $isMixedPlaceholder ? $this->resolveMixedPlaceholderSides($teamList) : null;
    $mixedBlendTeams = (!$mixedSides && $isMixedPlaceholder) ? $this->resolveMixedBlendTeamsFromTwo($teamList) : null;

    $forcedMixedTemplateCount = null;
    if ($isMixedPlaceholder) {
      if ($mixedSides) {
        $forcedMixedTemplateCount = $this->placeholderMixedFixtureCountFromSides($mixedSides);
      } elseif ($mixedBlendTeams) {
        $forcedMixedTemplateCount = $this->placeholderMixedFixtureCountFromBlend($mixedBlendTeams['boys'], $mixedBlendTeams['girls']);
      }
    }

    if ($forcedMixedTemplateCount !== null) {
      $templates = collect(range(1, max(1, $forcedMixedTemplateCount)))->map(function (int $sequence) use ($isDoublesLike) {
        return (object) [
          'sequence' => $sequence,
          'rubber_code' => $isDoublesLike ? RubberType::DOUBLES : null,
          'name' => $isDoublesLike ? 'Placeholder Doubles Fixture' : 'Placeholder Fixture',
          'gender_rule' => null,
          'player_count_per_team' => $isDoublesLike ? 2 : null,
        ];
      });
    } elseif ($templates->isEmpty()) {
      $templateCount = $this->placeholderFixtureCount($homeTeam, $awayTeam, $isDoublesLike);

      $templates = collect(range(1, max(1, $templateCount)))->map(function (int $sequence) use ($isDoublesLike) {
        return (object) [
          'sequence' => $sequence,
          'rubber_code' => $isDoublesLike ? RubberType::DOUBLES : null,
          'name' => $isDoublesLike ? 'Placeholder Doubles Fixture' : 'Placeholder Fixture',
          'gender_rule' => null,
          'player_count_per_team' => $isDoublesLike ? 2 : null,
        ];
      });
    }

    $created = 0;
    $matchNr = 1;

    foreach ($templates as $template) {
      $position = max(1, (int) ($template->sequence ?? $matchNr));
      $payload = [
        'draw_id'      => $draw->id,
        'fixture_type' => $isMixedPlaceholder ? 3 : ($isDoublesLike ? 2 : 1),
        'match_nr'     => $matchNr,
        'numSets'      => 3,
        'round_nr'     => 1,
        'tie_nr'       => 1,
        'home_rank_nr' => $position,
        'away_rank_nr' => $position,
        'age'          => $draw->drawName,
        'scheduled'    => 0,
      ];

      $fixture = TeamFixture::create($payload);

      if ($isMixedPlaceholder) {
        if ($mixedSides) {
          $homeBoyTeam = $mixedSides['home']['boys'];
          $homeGirlTeam = $mixedSides['home']['girls'];
          $awayBoyTeam = $mixedSides['away']['boys'];
          $awayGirlTeam = $mixedSides['away']['girls'];

          [$homeBoyPos, $homeGirlPos] = $this->placeholderMixedPositionsBySide($homeBoyTeam, $homeGirlTeam, $matchNr);
          [$awayBoyPos, $awayGirlPos] = $this->placeholderMixedPositionsBySide($awayBoyTeam, $awayGirlTeam, $matchNr);

          $slotRows = [
            [
              'slot_no' => 1,
              'home_team' => $homeBoyTeam,
              'away_team' => $awayBoyTeam,
              'home_pos' => $homeBoyPos,
              'away_pos' => $awayBoyPos,
            ],
            [
              'slot_no' => 2,
              'home_team' => $homeGirlTeam,
              'away_team' => $awayGirlTeam,
              'home_pos' => $homeGirlPos,
              'away_pos' => $awayGirlPos,
            ],
          ];

          foreach ($slotRows as $slotRow) {
            $homeAssignment = $this->placeholderPlayerAssignment($slotRow['home_team'], $slotRow['home_pos'], 'team1');
            $awayAssignment = $this->placeholderPlayerAssignment($slotRow['away_team'], $slotRow['away_pos'], 'team2');

            if (!empty($homeAssignment) || !empty($awayAssignment)) {
              TeamFixturePlayer::create(array_merge([
                'team_fixture_id' => $fixture->id,
                'slot_no' => $slotRow['slot_no'],
              ], $homeAssignment, $awayAssignment));
            }
          }

          \Log::debug('[createPlaceholderTeamFixtures] mixed slots', [
            'draw_id' => $draw->id,
            'fixture_id' => $fixture->id,
            'match_nr' => $matchNr,
            'home_boy_team' => $homeBoyTeam?->id,
            'home_girl_team' => $homeGirlTeam?->id,
            'away_boy_team' => $awayBoyTeam?->id,
            'away_girl_team' => $awayGirlTeam?->id,
            'home_positions' => [$homeBoyPos, $homeGirlPos],
            'away_positions' => [$awayBoyPos, $awayGirlPos],
          ]);
        } elseif ($mixedBlendTeams) {
          $boysTeam = $mixedBlendTeams['boys'];
          $girlsTeam = $mixedBlendTeams['girls'];
          [$homePos, $awayPos] = $this->placeholderMixedBlendPositions($boysTeam, $girlsTeam, $matchNr);

          $slotRows = [
            [
              'slot_no' => 1,
              'home_team' => $boysTeam,
              'away_team' => $girlsTeam,
              'home_pos' => $homePos,
              'away_pos' => $awayPos,
            ],
            [
              'slot_no' => 2,
              'home_team' => $girlsTeam,
              'away_team' => $boysTeam,
              'home_pos' => $homePos,
              'away_pos' => $awayPos,
            ],
          ];

          foreach ($slotRows as $slotRow) {
            $homeAssignment = $this->placeholderPlayerAssignment($slotRow['home_team'], $slotRow['home_pos'], 'team1');
            $awayAssignment = $this->placeholderPlayerAssignment($slotRow['away_team'], $slotRow['away_pos'], 'team2');

            if (!empty($homeAssignment) || !empty($awayAssignment)) {
              TeamFixturePlayer::create(array_merge([
                'team_fixture_id' => $fixture->id,
                'slot_no' => $slotRow['slot_no'],
              ], $homeAssignment, $awayAssignment));
            }
          }

          \Log::debug('[createPlaceholderTeamFixtures] mixed slots blended-two-team', [
            'draw_id' => $draw->id,
            'fixture_id' => $fixture->id,
            'match_nr' => $matchNr,
            'boys_team' => $boysTeam?->id,
            'girls_team' => $girlsTeam?->id,
            'home_pos' => $homePos,
            'away_pos' => $awayPos,
          ]);
        } else {
          \Log::warning('[createPlaceholderTeamFixtures] mixed fallback unavailable', [
            'draw_id' => $draw->id,
            'fixture_id' => $fixture->id,
            'match_nr' => $matchNr,
            'team_ids' => $teamList->pluck('id')->all(),
          ]);
        }
      } elseif ($isDoublesLike) {
        $basePosition = (($matchNr - 1) * 2) + 1;

        for ($slot = 0; $slot < 2; $slot++) {
          $slotPosition = $basePosition + $slot;
          $homeAssignment = $this->placeholderPlayerAssignment($homeTeam, $slotPosition, 'team1');
          $awayAssignment = $this->placeholderPlayerAssignment($awayTeam, $slotPosition, 'team2');

          if (!empty($homeAssignment) || !empty($awayAssignment)) {
            TeamFixturePlayer::create(array_merge([
              'team_fixture_id' => $fixture->id,
              'slot_no' => $slot + 1,
            ], $homeAssignment, $awayAssignment));
          }
        }
      } else {
        $homeAssignment = $this->placeholderPlayerAssignment($homeTeam, $position, 'team1');
        $awayAssignment = $this->placeholderPlayerAssignment($awayTeam, $position, 'team2');

        if (!empty($homeAssignment) || !empty($awayAssignment)) {
          TeamFixturePlayer::create(array_merge([
            'team_fixture_id' => $fixture->id,
            'slot_no' => 1,
          ], $homeAssignment, $awayAssignment));
        }
      }

      $created++;
      $matchNr++;
    }

    return $created;
  }

  private function isDoublesLikePlaceholderDraw(Draw $draw, $templates): bool
  {
    $templateCodes = collect($templates)
      ->pluck('rubber_code')
      ->filter()
      ->map(fn ($code) => (string) $code)
      ->all();

    if (in_array(RubberType::DOUBLES, $templateCodes, true) || in_array(RubberType::MIXED_DOUBLES, $templateCodes, true)) {
      return true;
    }

    $drawType = DrawType::find($draw->drawType_id);
    $drawTypeName = strtolower((string) ($drawType?->drawTypeName ?? $drawType?->name ?? ''));

    return str_contains($drawTypeName, 'doubles') || str_contains($drawTypeName, 'mixed');
  }

  private function isMixedPlaceholderDraw(Draw $draw, $teams): bool
  {
    $drawType = DrawType::find($draw->drawType_id);
    $drawTypeName = strtolower((string) ($drawType?->drawTypeName ?? $drawType?->name ?? ''));

    if (str_contains($drawTypeName, 'mixed')) {
      return true;
    }

    $teamList = $teams ? collect($teams) : collect();
    $categoryIds = $teamList->pluck('category_event_id')->filter()->unique()->values();

    if ($categoryIds->count() < 2) {
      return false;
    }

    $categoryNames = CategoryEvent::query()
      ->whereIn('category_events.id', $categoryIds)
      ->join('categories', 'category_events.category_id', '=', 'categories.id')
      ->pluck('categories.name')
      ->map(fn ($name) => strtolower((string) $name));

    return $categoryNames->contains(fn ($name) => str_contains($name, 'boys'))
      && $categoryNames->contains(fn ($name) => str_contains($name, 'girls'));
  }

  private function resolveMixedPlaceholderSides($teamList): ?array
  {
    $teams = collect($teamList)->filter();

    if ($teams->count() < 4) {
      return null;
    }

    $withCategory = $teams->map(function ($team) {
      $categoryName = CategoryEvent::query()
        ->where('category_events.id', $team->category_event_id)
        ->join('categories', 'category_events.category_id', '=', 'categories.id')
        ->value('categories.name');

      return [
        'team' => $team,
        'category' => strtolower((string) $categoryName),
      ];
    });

    $boys = $withCategory->filter(fn ($row) => str_contains($row['category'], 'boys'))->pluck('team')->values();
    $girls = $withCategory->filter(fn ($row) => str_contains($row['category'], 'girls'))->pluck('team')->values();

    if ($boys->count() < 2 || $girls->count() < 2) {
      return null;
    }

    return [
      'home' => [
        'boys' => $boys->get(0),
        'girls' => $girls->get(0),
      ],
      'away' => [
        'boys' => $boys->get(1),
        'girls' => $girls->get(1),
      ],
    ];
  }

  private function resolveMixedBlendTeamsFromTwo($teamList): ?array
  {
    $teams = collect($teamList)->filter()->values();

    if ($teams->count() !== 2) {
      return null;
    }

    $withCategory = $teams->map(function ($team) {
      $categoryName = CategoryEvent::query()
        ->where('category_events.id', $team->category_event_id)
        ->join('categories', 'category_events.category_id', '=', 'categories.id')
        ->value('categories.name');

      return [
        'team' => $team,
        'category' => strtolower((string) $categoryName),
      ];
    });

    $boysTeam = $withCategory->first(fn($row) => str_contains($row['category'], 'boys'))['team'] ?? null;
    $girlsTeam = $withCategory->first(fn($row) => str_contains($row['category'], 'girls'))['team'] ?? null;

    if (!$boysTeam || !$girlsTeam) {
      return null;
    }

    return [
      'boys' => $boysTeam,
      'girls' => $girlsTeam,
    ];
  }

  private function placeholderMixedPositionsBySide($boyTeam, $girlTeam, int $matchNr): array
  {
    $pairOffset = max(0, $matchNr - 1);
    $boyPos = $this->placeholderPositionFromTeam($boyTeam, $pairOffset + 1);
    $girlPos = $this->placeholderPositionFromTeam($girlTeam, $pairOffset + 1);

    return [$boyPos, $girlPos];
  }

  private function placeholderMixedBlendPositions($boysTeam, $girlsTeam, int $matchNr): array
  {
    $pairOffset = max(0, $matchNr - 1);
    $boysPos = $this->placeholderPositionFromTeam($boysTeam, $pairOffset + 1);
    $girlsPos = $this->placeholderPositionFromTeam($girlsTeam, $pairOffset + 1);

    return [$boysPos, $girlsPos];
  }

  private function placeholderMixedFixtureCountFromSides(array $mixedSides): int
  {
    $homeCount = min(
      $this->placeholderTeamPlayerCount($mixedSides['home']['boys'] ?? null),
      $this->placeholderTeamPlayerCount($mixedSides['home']['girls'] ?? null)
    );

    $awayCount = min(
      $this->placeholderTeamPlayerCount($mixedSides['away']['boys'] ?? null),
      $this->placeholderTeamPlayerCount($mixedSides['away']['girls'] ?? null)
    );

    $count = min($homeCount, $awayCount);

    return max(1, $count);
  }

  private function placeholderMixedFixtureCountFromBlend($boysTeam, $girlsTeam): int
  {
    $count = min(
      $this->placeholderTeamPlayerCount($boysTeam),
      $this->placeholderTeamPlayerCount($girlsTeam)
    );

    return max(1, $count);
  }

  private function placeholderPositionFromTeam($team, int $defaultPosition): int
  {
    if (!$team || !method_exists($team, 'allPlayersOrdered')) {
      return max(1, $defaultPosition);
    }

    $count = (int) $team->allPlayersOrdered()->count();
    if ($count < 1) {
      return max(1, $defaultPosition);
    }

    return min(max(1, $defaultPosition), $count);
  }

  private function placeholderFixtureCount($homeTeam, $awayTeam, bool $isDoublesLike): int
  {
    $homeCount = $this->placeholderTeamPlayerCount($homeTeam);
    $awayCount = $this->placeholderTeamPlayerCount($awayTeam);
    $playerCount = max($homeCount, $awayCount);

    if ($homeCount > 0 && $awayCount > 0) {
      $playerCount = min($homeCount, $awayCount);
    }

    if ($playerCount < 1) {
      return 1;
    }

    return $isDoublesLike ? max(1, (int) ceil($playerCount / 2)) : $playerCount;
  }

  private function placeholderTeamPlayerCount($team): int
  {
    if (!$team || !method_exists($team, 'allPlayersOrdered')) {
      return 0;
    }

    return (int) $team->allPlayersOrdered()->count();
  }

  private function placeholderPlayerAssignment($team, int $position, string $prefix): array
  {
    if (!$team || !method_exists($team, 'allPlayersOrdered')) {
      return [];
    }

    $player = $team->allPlayersOrdered()->get($position - 1);
    if (!$player) {
      return [];
    }

    if (($player->type ?? 'profile') === 'noprofile') {
      return [$prefix . '_no_profile_id' => $player->id];
    }

    return [$prefix . '_id' => $player->id];
  }

  public function previewSingleDrawTeam(Request $request, Event $event)
  {
    $this->authorize('team-draw.createFormat', $event);

    $validated = $request->validate([
      'draw_type_id'   => 'required|integer|exists:draw_types,id',
      'drawName'       => 'required|string|max:255',
      'category_ids'   => 'required|array|min:1',
      'category_ids.*' => 'integer|exists:category_events,id',
    ]);

    $drawType = \App\Models\DrawType::find($validated['draw_type_id']);
    $categories = \App\Models\CategoryEvent::whereIn('id', $validated['category_ids'])->get();

    return response()->json([
      'preview'    => true,
      'drawName'   => $validated['drawName'],
      'draw_type'  => [
        'id'   => $drawType?->id,
        'name' => $drawType?->name ?? $drawType?->drawType,
      ],
      'categories' => $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name ?? $c->category]),
      'event_id'   => $event->id,
    ]);
  }

  private function buildRegionFixturesForEvent(int $eventId)
  {
    $regions = EventRegion::where('event_id', $eventId)
      ->with('region')
      ->orderBy('ordering')
      ->get();

    if ($regions->count() % 2 != 0) {
      $orderingValues = $regions->pluck('ordering')->toArray();
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
        'id' => 0,
        'region' => 'bye',
        'ordering' => $missingOrdering,
      ];

      $regions->push($dummyRegion);
    }

    $regions = $regions->sortBy('ordering')->values();

    return Fixtures::makeRegionFixtures($regions);
  }

  /**
   * Return print-ready JSON for a SINGLE draw.
   * Called once per draw from the JS sequential loader.
   */
  public function printDrawsData(Request $request, Event $event)
  {
    $this->authorize('event-draw.view', $event);

    $drawId = $request->input('draw_id');

    $draw = Draw::where('event_id', $event->id)
      ->where('id', $drawId)
      ->with([
        'groups.groupRegistrations.registration.players',
        'drawFixtures.registration1.players',
        'drawFixtures.registration2.players',
        'drawFixtures.fixtureResults',
        'drawFixtures.drawGroup',
      ])
      ->first();

    if (!$draw) {
      return response()->json(['draw' => null]);
    }

    return response()->json(['draw' => $this->buildDrawPrintData($draw)]);
  }

  /**
   * Generate a PDF for selected draws and stream it as a download.
   */
  public function printDrawsPdf(Request $request, Event $event)
  {
    $this->authorize('event-draw.view', $event);

    $drawIds       = $request->input('draw_ids', []);
    $printType     = $request->input('print_type', 'fixtures');
    $withStandings = (bool) $request->input('include_standings', false);

    $draws = Draw::where('event_id', $event->id)
      ->whereIn('id', $drawIds)
      ->with([
        'groups.groupRegistrations.registration.players',
        'drawFixtures.registration1.players',
        'drawFixtures.registration2.players',
        'drawFixtures.fixtureResults',
        'drawFixtures.drawGroup',
      ])
      ->orderBy('drawName')
      ->get();

    $drawsData = $draws->map(fn($d) => $this->buildDrawPrintData($d))->values();

    $pdf = Pdf::loadView('backend.draw.pdf.event-draws-pdf', [
      'event'         => $event,
      'draws'         => $drawsData,
      'printType'     => $printType,
      'withStandings' => $withStandings,
    ]);

    $pdf->setPaper('A4', 'portrait');

    $filename = str_replace(' ', '_', $event->name) . '_draws.pdf';
    return $pdf->download($filename);
  }

  /**
   * Build a structured array of print data for one draw.
   */
  private function buildDrawPrintData(Draw $draw): array
  {
    $groups = $draw->groups->map(function ($g) {
      return [
        'id'   => $g->id,
        'name' => $g->name,
        'registrations' => $g->groupRegistrations->map(function ($gr) {
          $reg    = $gr->registration;
          $player = $reg?->players?->first();
          return [
            'id'           => $reg?->id,
            'display_name' => $player?->full_name ?? 'Unknown',
            'pivot'        => ['seed' => $gr->seed ?? 9999],
          ];
        })->values()->toArray(),
      ];
    })->values()->toArray();

    $rrFixtures = [];
    foreach ($draw->drawFixtures as $fx) {
      $gid = $fx->draw_group_id ?: optional($fx->drawGroup)->id;
      if (!$gid) continue;

      $allSets = $fx->fixtureResults
        ->sortBy('set_nr')
        ->map(fn($r) => "{$r->registration1_score}-{$r->registration2_score}")
        ->values()->toArray();

      $lastSet = $fx->fixtureResults->sortBy('set_nr')->last();

      $rrFixtures[$gid][] = [
        'id'         => $fx->id,
        'group_id'   => $gid,
        'r1_id'      => $fx->registration1_id,
        'r2_id'      => $fx->registration2_id,
        'all_sets'   => $allSets,
        'score'      => implode(', ', $allSets),
        'home_score' => $lastSet?->registration1_score,
        'away_score' => $lastSet?->registration2_score,
        'winner'     => $lastSet?->winner_registration ?? null,
      ];
    }

    $stagePriority = ['RR' => 0, 'MAIN' => 1, 'PLATE' => 2, 'CONS' => 3, 'BOWL' => 4, 'SHIELD' => 5, 'SPOON' => 6];

    // Build feeder maps from parent_fixture_id / loser_parent_fixture_id
    $winnerFeeders = [];
    $loserFeeders  = [];
    foreach ($draw->drawFixtures as $fx) {
      if ($fx->parent_fixture_id) {
        $winnerFeeders[$fx->parent_fixture_id][] = $fx->match_nr;
      }
      if ($fx->loser_parent_fixture_id) {
        $loserFeeders[$fx->loser_parent_fixture_id][] = $fx->match_nr;
      }
    }

    $oops = $draw->drawFixtures
      ->sortBy(function ($fx) use ($stagePriority) {
        $sp = $stagePriority[$fx->stage ?? 'RR'] ?? 99;
        return sprintf('%02d-%05d-%05d', $sp, (int)$fx->round, (int)$fx->match_nr);
      })
      ->map(function ($fx) use ($winnerFeeders, $loserFeeders) {
        $sets = $fx->fixtureResults
          ->sortBy('set_nr')
          ->map(fn($r) => "{$r->registration1_score}-{$r->registration2_score}")
          ->implode(', ');

        $winner = optional($fx->fixtureResults->sortBy('set_nr')->last())->winner_registration;

        $wFeed = $winnerFeeders[$fx->id] ?? [];
        $lFeed = $loserFeeders[$fx->id]  ?? [];
        sort($wFeed);
        sort($lFeed);

        return [
          'id'           => $fx->id,
          'stage'        => $fx->stage,
          'round'        => $fx->round,
          'match_nr'     => $fx->match_nr,
          'playoff_type' => $fx->playoff_type,
          'home'         => $fx->registration1?->display_name ?? 'TBD',
          'away'         => $fx->registration2?->display_name ?? 'TBD',
          'r1_id'        => $fx->registration1_id,
          'r2_id'        => $fx->registration2_id,
          'score'        => $sets,
          'winner'       => $winner,
          'winner_feeders' => $wFeed,
          'loser_feeders'  => $lFeed,
        ];
      })->values()->toArray();

    $standings = app(StandingsService::class)->forDraw($draw);

    return [
      'id'         => $draw->id,
      'name'       => $draw->drawName ?? 'Draw #' . $draw->id,
      'groups'     => $groups,
      'rrFixtures' => $rrFixtures,
      'oops'       => $oops,
      'standings'  => $standings,
    ];
  }
}
