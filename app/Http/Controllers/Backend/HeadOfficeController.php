<?php

namespace App\Http\Controllers\Backend;

use App\Helpers\Fixtures;
use App\Http\Controllers\Controller;
use App\Models\CategoryEvent;
use App\Models\Draw;
use App\Models\DrawSetting;
use App\Models\DrawType;
use App\Models\Event;
use App\Models\EventRegion;
use App\Models\Team;
use App\Models\Venue;
use App\Services\FixtureService;
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

    $standings = [];
    foreach ($draw->groups as $group) {
      foreach ($group->groupRegistrations as $gr) {
        $reg = $gr->registration;
        if (!$reg) continue;
        $player = $reg->players?->first();
        $standings[$group->id][$reg->id] = [
          'reg_id'   => $reg->id,
          'player'   => $player?->full_name ?? 'Unknown',
          'wins'     => 0,
          'losses'   => 0,
          'sets_won' => 0,
          'sets_lost'=> 0,
        ];
      }
    }

    foreach ($draw->drawFixtures as $fx) {
      if (($fx->stage ?? 'RR') !== 'RR') continue;
      if ($fx->fixtureResults->isEmpty()) continue;

      $gid = $fx->draw_group_id ?: optional($fx->drawGroup)->id;
      if (!$gid || !isset($standings[$gid])) continue;

      foreach ($fx->fixtureResults->sortBy('set_nr') as $set) {
        $s1 = (int) $set->registration1_score;
        $s2 = (int) $set->registration2_score;

        if (isset($standings[$gid][$fx->registration1_id])) {
          $standings[$gid][$fx->registration1_id]['sets_won']  += $s1;
          $standings[$gid][$fx->registration1_id]['sets_lost'] += $s2;
        }
        if (isset($standings[$gid][$fx->registration2_id])) {
          $standings[$gid][$fx->registration2_id]['sets_won']  += $s2;
          $standings[$gid][$fx->registration2_id]['sets_lost'] += $s1;
        }
      }

      $lastSet = $fx->fixtureResults->sortBy('set_nr')->last();
      if ($lastSet) {
        $s1 = (int) $lastSet->registration1_score;
        $s2 = (int) $lastSet->registration2_score;
        if ($s1 > $s2) {
          if (isset($standings[$gid][$fx->registration1_id])) $standings[$gid][$fx->registration1_id]['wins']++;
          if (isset($standings[$gid][$fx->registration2_id])) $standings[$gid][$fx->registration2_id]['losses']++;
        } elseif ($s2 > $s1) {
          if (isset($standings[$gid][$fx->registration2_id])) $standings[$gid][$fx->registration2_id]['wins']++;
          if (isset($standings[$gid][$fx->registration1_id])) $standings[$gid][$fx->registration1_id]['losses']++;
        }
      }
    }

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
