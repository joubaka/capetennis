<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Draw;
use App\Models\Fixture;
use App\Models\CategoryEvent;
use Illuminate\Http\Request;
use App\Services\DrawService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\DrawSetting;

class RoundRobinController extends Controller
{
  protected DrawService $builder;

  public function __construct(DrawService $builder)
  {
    $this->builder = $builder;
  }

  // ============================================================
  // SHOW ROUND ROBIN HUB
  // ============================================================
  public function showd(Draw $draw)
  {
    $engine = new \App\Services\BracketEngine($draw);
    $svgData = $engine->build();

    return view('backend.draw.roundrobin.draw-svg', [
      'draw' => $draw,
      'svg' => $svgData,
    ]);
  }

  public function show(Draw $draw)
  {
    Log::info("🎾 [RoundRobinController@show] Entering method", [
      'draw_id' => $draw->id,
      'draw_name' => $draw->name,
      'event_id' => $draw->event_id,
      'category_event_id' => $draw->category_event_id,
      'eventType' => $draw->event->eventType ?? null,
    ]);

    // -------------------------------------------------------------
    // AUTOCREATE GROUPS IF MISSING
    // -------------------------------------------------------------
    if (!$draw->groups()->exists()) {
      foreach (['A', 'B', 'C', 'D'] as $name) {
        $draw->groups()->create(['name' => $name]);
      }
    }

    Log::info("📥 Loading base groups + registrations + players");

    $draw->load([
      'settings',
      'groups.groupRegistrations.registration.players',
      'event.draws.groups'  // Load all draws in the event for the draw switcher
    ]);

    // -------------------------------------------------------------
    // CREATE RR FIXTURES IF NONE EXIST
    // -------------------------------------------------------------
    if ($draw->drawFixtures->isEmpty()) {
      Log::warning("⚠️ No RR fixtures found — generating new fixtures...");
      $this->builder->regenerateRoundRobinFixtures($draw);
    }

    // -------------------------------------------------------------
    // ONLY FOR INTERPRO EVENT TYPE 13
    // -------------------------------------------------------------
    if ($draw->event->eventType == 13) {

      Log::info("📥 Reloading ALL RR relationships (fixtures + groups + registrations + players)");

      $draw->load([
        'groups.registrations.players',
        'groups.groupRegistrations',
        'drawFixtures.registration1.players',
        'drawFixtures.registration2.players',
        'drawFixtures.fixtureResults',
        'drawFixtures.schedule',
        'drawFixtures.groupRegistration1',
        'drawFixtures.groupRegistration2',
      ]);

      $groups = $draw->groups;

      // ---------------------------------------------------------
      // DEEP FIXTURE DEBUG LOG
      // ---------------------------------------------------------
      Log::info("🔎 Deep logging: ALL fixtures with players & groups");

      foreach ($draw->drawFixtures as $fx) {

        $r1 = $fx->registration1;
        $r2 = $fx->registration2;

        $r1Players = $r1?->players?->pluck('full_name')->join(', ') ?? 'NONE';
        $r2Players = $r2?->players?->pluck('full_name')->join(', ') ?? 'NONE';

        Log::info("🧪 [FIXTURE-DEBUG]", [
          'fx_id' => $fx->id,
          'stage' => $fx->stage,
          'group_id' => $fx->draw_group_id,
          'round' => $fx->round,
          'match_nr' => $fx->match_nr,
          'registration1_id' => $fx->registration1_id,
          'registration1_players' => $r1Players,
          'registration2_id' => $fx->registration2_id,
          'registration2_players' => $r2Players,
          'fixture_results' => $fx->fixtureResults?->map(fn($r) => [
            "set_nr" => $r->set_nr,
            "p1" => $r->registration1_score,
            "p2" => $r->registration2_score
          ]),
          'schedule' => $fx->schedule ? [
            'venue_id' => $fx->schedule->venue_id,
            'court' => $fx->schedule->court ?? null,
            'time' => $fx->schedule->time ?? null
          ] : null
        ]);
      }

      Log::info("📦 [REL LOAD] Fixtures + Groups loaded", [
        'fixtures_count' => $draw->drawFixtures->count(),
        'groups_count' => $draw->groups->count()
      ]);

      // -------------------------------------------------------------
      // CHECK FOR FIXTURE REGEN
      // -------------------------------------------------------------
      if ($draw->drawFixtures->isEmpty()) {
        Log::warning("⚠️ No drawFixtures found — regenerating RR fixtures");
        $this->builder->regenerateRoundRobinFixtures($draw);
        $draw->load('drawFixtures');
      }

      // -------------------------------------------------------------
      // BUILD HUB
      // -------------------------------------------------------------
      Log::info("🔄 Loading RR Hub");
      $hub = $this->builder->loadRoundRobinHub($draw);

      // -------------------------------------------------------------
      // BRACKET ENGINE
      // -------------------------------------------------------------
      Log::info("🎾 BracketEngine: Building knockout stages...");
      $engine = new \App\Services\BracketEngine($draw);
      $svgData = $engine->build();

      // -------------------------------------------------------------
      // CATEGORY EVENTS
      // -------------------------------------------------------------
      Log::info("📥 Loading ALL Category Events for EVENT {$draw->event_id}");

      $categoryEvents = CategoryEvent::where('event_id', $draw->event_id)
        ->with(['registrations', 'registrations.players'])
        ->get();

      Log::info("📦 CategoryEvents Loaded", [
        'categoryEvents_count' => $categoryEvents->count(),
        'sample' => $categoryEvents->take(3)->map(function ($ce) {
          return [
            'ce_id' => $ce->id,
            'category_name' => $ce->category->name ?? 'N/A',
            'registrations' => $ce->registrations->count()
          ];
        }),
      ]);

      // -------------------------------------------------------------
      // BUILD GROUP JSON & LOG DEEP
      // -------------------------------------------------------------
      Log::info("📦 Building Groups JSON (deep log)");

      $groupsJson = $groups->map(function ($g) {
        Log::info("🔘 Group {$g->name} (ID {$g->id})", [
          "registrations_count" => $g->groupRegistrations->count()
        ]);

        return [
          'id' => $g->id,
          'name' => $g->name,
          'registrations' => $g->groupRegistrations->map(function ($gr) {
            $reg = $gr->registration;
            $player = $reg?->players?->first();

            Log::info("➡️ GroupReg ID {$gr->id}", [
              'seed' => $gr->seed,
              'reg_id' => $reg?->id,
              'player' => $player?->full_name ?? 'UNKNOWN'
            ]);

            return [
              'id' => $reg?->id,
              'display_name' => $player?->full_name ?? 'Unknown',
              'seed' => $gr->seed ?? 9999,
            ];
          })->values(),
        ];
      });

      Log::info("📤 Returning Round Robin View");

      return view('backend.draw.roundrobin.show', [
        'draw' => $draw,
        'svg' => $svgData,

        // JS-friendly version for RR matrix
        'groupsJs' => $draw->groups->values()->toArray(),

        // Blade-friendly original version
        'groups' => $draw->groups,

        'groupsjson' => $groupsJson,
        'fixtures' => $draw->drawFixtures,
        'categoryEvents' => $categoryEvents,
        'rrFixtures' => $hub['rrFixtures'],
        'oops' => $hub['oops'],
        'standings' => $hub['standings'],
      ]);

    }

    // -------------------------------------------------------------
    // NOT INTERPRO — Render same round-robin view with minimal hub data
    // -------------------------------------------------------------
    Log::warning("⚠️ Draw {$draw->id} is NOT Interpro — rendering default RR view");

    // Ensure relations required by the view are loaded
    $draw->load([
      'settings',
      'groups.groupRegistrations.registration.players',
      'drawFixtures.registration1.players',
      'drawFixtures.registration2.players',
      'drawFixtures.fixtureResults',
      'drawFixtures.schedule',
    ]);

    // Build a lightweight hub for non-interpro draws
    $hub = $this->builder->loadRoundRobinHub($draw);

    // Bracket svg (may be empty for simple draws)
    $engine = new \App\Services\BracketEngine($draw);
    $svgData = $engine->build();

    $groups = $draw->groups;

    $groupsJson = $groups->map(function ($g) {
      return [
        'id' => $g->id,
        'name' => $g->name,
        'registrations' => $g->groupRegistrations->map(function ($gr) {
          $reg = $gr->registration;
          $player = $reg?->players?->first();
          return [
            'id' => $reg?->id,
            'display_name' => $player?->full_name ?? 'Unknown',
            'seed' => $gr->seed ?? 9999,
          ];
        })->values(),
      ];
    });

    $categoryEvents = CategoryEvent::where('event_id', $draw->event_id)
      ->with(['registrations', 'registrations.players'])
      ->get();

    return view('backend.draw.roundrobin.show', [
      'draw' => $draw,
      'svg' => $svgData,
      'groupsJs' => $draw->groups->values()->toArray(),
      'groups' => $draw->groups,
      'groupsjson' => $groupsJson,
      'fixtures' => $draw->drawFixtures,
      'categoryEvents' => $categoryEvents,
      'rrFixtures' => $hub['rrFixtures'] ?? [],
      'oops' => $hub['oops'] ?? [],
      'standings' => $hub['standings'] ?? [],
    ]);
  }

  public function adminScoresPage(Draw $draw)
  {
    $fixtures = Fixture::where('draw_id', $draw->id)
      //->where('stage', 'RR')
      ->with([
        'registration1.players',
        'registration2.players',
        'fixtureResults'          // ← THIS IS REQUIRED
      ])
      ->orderBy('id')
      ->get();


    return view('backend.draw.roundrobin.admin-scores', [
      'draw' => $draw,
      'fixtures' => $fixtures,
      'rr' => $fixtures,   // same data
    ]);
  }



  // ============================================================
  // SAVE ORDER OF PLAY
  // ============================================================
  public function storeOrderOfPlay(Request $request, Draw $draw)
  {
    $data = $request->validate([
      'items' => 'required|array',
      'items.*.fixture_id' => 'required|integer',
      'items.*.court' => 'nullable|string|max:50',
      'items.*.start_time' => 'nullable|string|max:50',
      'items.*.round' => 'nullable|string|max:50',
    ]);

    foreach ($data['items'] as $item) {
      $fixture = $draw->drawFixtures()->find($item['fixture_id']);
      if (!$fixture)
        continue;

      $fixture->court = $item['court'] ?? null;
      $fixture->start_time = $item['start_time'] ?? null;
      $fixture->round = $item['round'] ?? $fixture->round;
      $fixture->save();
    }

    return response()->json([
      'status' => 'ok',
      'message' => 'Order of play updated',
    ]);
  }

  // ============================================================
  // SAVE SCORE (AUTO-DETECT RR vs BRACKET)
  // ============================================================
    public function saveScore(Request $request, $id)
    {
      $fixture = Fixture::with(['registration1', 'registration2'])
        ->findOrFail($id);

      // ------------------------
      // PARSE SETS
      // ------------------------
      $sets = $request->input('sets', []);
      $validSets = [];

      foreach ($sets as $set) {
        $set = trim($set);

        if ($set !== '' && str_contains($set, '-')) {
          [$a, $b] = array_map('intval', explode('-', $set));
          $validSets[] = [$a, $b];
        }
      }

      if (empty($validSets)) {
        return response()->json([
          'message' => 'Please enter at least one valid set (e.g., 6-4)',
        ], 422);
      }

      // ------------------------
      // SELECT MODE (RR / BRACKET)
      // ------------------------
      if ($fixture->stage === 'RR') {
        $response = $this->builder->saveScore($fixture, $validSets);

        // Reload full hub so frontend can refresh matrix, OOP & standings
        $draw = \App\Models\Draw::findOrFail($fixture->draw_id);
        $hub  = $this->builder->loadRoundRobinHub($draw);

        $response['oop']        = $hub['oops'] ?? [];
        $response['rrFixtures'] = $hub['rrFixtures'] ?? [];
        $response['standings']  = $hub['standings'] ?? [];

        return response()->json($response);
      }

      // BRACKET MODE
      $response = $this->builder->saveBracketScore($fixture, $validSets);
      return response()->json($response);
    }

  // ============================================================
  // DELETE SCORE
  // ============================================================
  public function deleteScore($id)
  {
    $fixture = Fixture::with(['fixtureResults'])->findOrFail($id);

    $fixture->fixtureResults()->delete();
    $fixture->winner_registration = null;
    $fixture->match_status = 0;
    $fixture->save();

    // Reload full OOP for the draw so the front-end table refreshes
    $draw = \App\Models\Draw::findOrFail($fixture->draw_id);
    $hub  = $this->builder->loadRoundRobinHub($draw);

    return response()->json([
      'success'    => true,
      'message'    => 'Score deleted',
      'oop'        => $hub['oops'] ?? [],
      'rrFixtures' => $hub['rrFixtures'] ?? [],
      'standings'  => $hub['standings'] ?? [],
    ]);
  }

  // ============================================================
  // GENERATE MAIN BRACKET (SERVICE)
  // ============================================================
  // ============================================================
  // GENERATE MAIN BRACKET (CALLED VIA AJAX)
  // ============================================================
  public function generateMainBracket(Request $request, Draw $draw)
  {
    \Log::info("===============================================");
    \Log::info("🎾 [MainBracket] START GENERATION", [
      'draw_id' => $draw->id,
      'event_type' => $draw->event->eventType ?? null,
    ]);

    $eventType = $draw->event->eventType ?? null;

    try {
      // ============================================================
      // INTERPRO (eventType 13) - Use InterproDrawBuilder
      // ============================================================
      if ($eventType == 13) {
        // 1) Build main seeds
        $seeds = $this->builder->buildMainSeedsFromRRStandings($draw);
        \Log::info("🧬 [MainBracket] SEEDS BUILT", $seeds);

        // 2) Clear only MAIN fixtures
        $this->builder->clearMainPlayoffFixtures($draw);
        \Log::info("🧬 [MainBracket] Incoming seeds before full bracket:", $seeds);

        // 3) Build FULL BRACKET (MAIN + PLATE + CONS)
        $fixtures = $this->builder->generateFullBracketFixtures($draw, $seeds);

        \Log::info("🏁 [MainBracket] MAIN fixtures created", [
          'sf1_id' => optional($fixtures['sf1'])->id,
          'sf2_id' => optional($fixtures['sf2'])->id,
          'final_id' => optional($fixtures['final'])->id,
        ]);

        $plateFixtures = $fixtures['plate'] ?? [];
        \Log::info("🏁 [PlateBracket] FULL PLATE CREATED", $plateFixtures);

        return response()->json([
          'success' => true,
          'message' => 'Main playoff bracket created.',
          'fixtures' => $fixtures,
          'plateFixtures' => $plateFixtures,
        ]);
      }

      // ============================================================
      // INDIVIDUAL DRAWS - Use DynamicBracketGenerator
      // ============================================================
      \Log::info("🎾 [MainBracket] Using Dynamic generator for individual draw");

      // Get playoff config from draw settings
      $playoffConfig = optional($draw->settings)->playoff_config 
        ?? \App\Models\DrawSetting::defaultPlayoffConfig(
            optional($draw->settings)->boxes ?? $draw->groups()->count() ?? 4
          );

      // Build seeds from RR standings using loadRoundRobinHub
      $hub = $this->builder->loadRoundRobinHub($draw);
      $standings = $hub['standings'] ?? [];
      $seeds = $this->buildDynamicSeeds($draw, $standings, $playoffConfig);

      \Log::info("🧬 [MainBracket] Dynamic seeds built", $seeds);

      // Clear existing playoff fixtures (include custom stages from config)
      $allStages = collect(['MAIN', 'PLATE', 'CONS', 'BOWL', 'SHIELD', 'SPOON'])
        ->merge(collect($playoffConfig)->pluck('slug')->map(fn($s) => strtoupper($s)))
        ->unique()->values()->all();
      Fixture::where('draw_id', $draw->id)
        ->whereIn('stage', $allStages)
        ->delete();

      // Generate fixtures for each enabled bracket
      $allFixtures = $this->generateDynamicPlayoffFixtures($draw, $playoffConfig, $seeds);

      \Log::info("🏁 [MainBracket] Dynamic fixtures created", [
        'total_fixtures' => count($allFixtures),
      ]);

      return response()->json([
        'success' => true,
        'message' => 'Playoff brackets created successfully.',
        'fixtures' => $allFixtures,
      ]);

    } catch (\Throwable $e) {

      \Log::error('[MainBracket] ERROR', [
        'draw_id' => $draw->id,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);

      return response()->json([
        'success' => false,
        'message' => $e->getMessage(),
      ], 422);
    }
  }

  /**
   * Build seeds from RR standings for dynamic playoff config
   */
  protected function buildDynamicSeeds(Draw $draw, array $standings, array $playoffConfig): array
  {
    $seeds = [];
    $groups = $draw->groups;
    
    foreach ($groups as $group) {
      $groupStandings = $standings[$group->id] ?? [];
      
      // Standings are already sorted from loadRoundRobinHub
      // Convert to array if it's an object/collection
      if (is_object($groupStandings)) {
        $groupStandings = collect($groupStandings)->values()->all();
      } else {
        $groupStandings = array_values($groupStandings);
      }
      
      // Assign positions
      foreach ($groupStandings as $pos => $player) {
        $position = $pos + 1; // 1-based position
        $key = $group->name . $position; // e.g., "A1", "A2", "B1", etc.
        
        // Handle different data formats
        $regId = $player['registration_id'] ?? $player['reg_id'] ?? null;
        $seeds[$key] = $regId;
        
        \Log::info("🔑 [buildDynamicSeeds] Seed assigned", [
          'key' => $key,
          'registration_id' => $regId,
          'player' => $player['player'] ?? 'unknown',
        ]);
      }
    }
    
    \Log::info("🧬 [buildDynamicSeeds] Final seeds map", $seeds);
    
    return $seeds;
  }

  /**
   * Generate playoff fixtures based on playoff_config
   * Includes position playoffs (3rd/4th, 5th/6th, etc.)
   */
  protected function generateDynamicPlayoffFixtures(Draw $draw, array $playoffConfig, array $seeds): array
  {
    $allFixtures = [];
    $groups = $draw->groups;
    $numGroups = $groups->count();
    $matchNr = 1000; // Starting match number
    $overallPosition = 0; // Running position offset across brackets

    foreach ($playoffConfig as $config) {
      if (!($config['enabled'] ?? false)) {
        continue;
      }

      $stage = strtoupper($config['slug'] ?? 'MAIN');
      $size = $config['size'] ?? 4;
      $positions = $config['positions'] ?? [];
      
      \Log::info("🎾 [GenerateDynamic] Creating bracket", [
        'stage' => $stage,
        'size' => $size,
        'positions' => $positions,
      ]);

      // Collect players for this bracket based on positions
      $players = [];
      
      // If positions array is empty or missing, auto-populate based on bracket size and number of groups
      if (empty($positions)) {
        \Log::warning("🚨 [GenerateDynamic] Empty positions array, calculating automatically", [
          'stage' => $stage,
          'size' => $size,
          'num_groups' => $numGroups,
        ]);
        
        // Calculate how many positions needed per group to fill the bracket
        $positionsPerGroup = (int) ceil($size / $numGroups);
        $positions = range(1, $positionsPerGroup);
        
        \Log::info("🔧 [GenerateDynamic] Auto-calculated positions", [
          'positions' => $positions,
          'positions_per_group' => $positionsPerGroup,
        ]);
      }
      
      // Collect players: iterate POSITIONS first, then GROUPS.
      // Standard bracket seeding ([1,N],[2,N-1],...) naturally pairs
      // Group A with Group D and Group B with Group C when groups are
      // listed in straight alphabetical order.  For EVEN group counts
      // (2,4,6,8) we keep offset=0 so this cross-group pairing works.
      // For ODD group counts (3,5,7) we rotate by floor(N/2) per
      // position to avoid same-group R1 clashes.
      // IMPORTANT: Always push a value (null for missing) to preserve seed
      // slot positions — skipping would collapse the array and shift players
      // into wrong bracket halves.
      $sortedGroups = $groups->sortBy('name')->values();
      $halfOffset = (int) floor($numGroups / 2);
      
      foreach ($positions as $posIdx => $pos) {
        // Rotate only for odd group counts; even groups get straight order
        $offset = ($numGroups >= 3 && $numGroups % 2 !== 0) ? ($posIdx * $halfOffset) % $numGroups : 0;
        
        for ($g = 0; $g < $numGroups; $g++) {
          $group = $sortedGroups[($g + $offset) % $numGroups];
          $key = $group->name . $pos;
          $players[] = $seeds[$key] ?? null;
        }
      }

      \Log::info("🎾 [GenerateDynamic] Players for bracket (seeded order)", [
        'stage' => $stage,
        'player_count' => count($players),
        'player_ids' => $players,
      ]);

      // Generate bracket fixtures
      $numRounds = (int) ceil(log(max($size, 2), 2));
      $fixtures = [];
      $positionPlayoffs = [];
      
      // Round 1 fixtures — use standard bracket seeding matchups
      $bracketMatchups = $this->getStandardBracketMatchups($size);
      
      foreach ($bracketMatchups as $i => $matchup) {
        $p1 = $players[$matchup[0] - 1] ?? null; // seeds are 1-indexed
        $p2 = $players[$matchup[1] - 1] ?? null;
        
        $fx = Fixture::create([
          'draw_id' => $draw->id,
          'stage' => $stage,
          'round' => 1,
          'match_nr' => $matchNr++,
          'position' => null,
          'registration1_id' => $p1,
          'registration2_id' => $p2,
        ]);
        
        $fixtures[1][] = $fx;
      }

      // Subsequent rounds (empty, will be filled as winners advance)
      for ($round = 2; $round <= $numRounds; $round++) {
        $matchesInRound = pow(2, $numRounds - $round);
        $prevRound = $fixtures[$round - 1] ?? [];
        
        for ($i = 0; $i < $matchesInRound; $i++) {
          $isFinal = ($round == $numRounds && $matchesInRound == 1);
          
          $fx = Fixture::create([
            'draw_id' => $draw->id,
            'stage' => $stage,
            'round' => $round,
            'match_nr' => $matchNr++,
            'position' => $isFinal ? 1 : null, // Final determines 1st/2nd
          ]);

          // Link parent fixtures (winners advance)
          $parent1Idx = $i * 2;
          $parent2Idx = $i * 2 + 1;
          
          if (isset($prevRound[$parent1Idx])) {
            $prevRound[$parent1Idx]->parent_fixture_id = $fx->id;
            $prevRound[$parent1Idx]->save();
          }
          if (isset($prevRound[$parent2Idx])) {
            $prevRound[$parent2Idx]->parent_fixture_id = $fx->id;
            $prevRound[$parent2Idx]->save();
          }

          $fixtures[$round][] = $fx;
        }

        // Create position playoff for losers of this round
        $positionPlayoff = $this->createPositionPlayoff(
          $draw, 
          $stage, 
          $round, 
          $numRounds, 
          $prevRound, 
          $matchNr,
          $overallPosition
        );
        
        if ($positionPlayoff) {
          $positionPlayoffs = array_merge($positionPlayoffs, $positionPlayoff['fixtures']);
          $matchNr = $positionPlayoff['nextMatchNr'];
        }
      }

      $allFixtures[$stage] = [
        'main' => $fixtures,
        'playoffs' => $positionPlayoffs,
      ];

      $overallPosition += $size;
    }

    // Auto-advance all byes in the brackets
    $this->autoAdvanceByes($draw);

    return $allFixtures;
  }

  /**
   * Standard bracket seeding matchups (seed1 vs seed2 per match).
   * Ensures top seeds don't meet until later rounds.
   */
  protected function getStandardBracketMatchups(int $size): array
  {
    return match($size) {
      2  => [[1, 2]],
      4  => [[1, 4], [2, 3]],
      8  => [[1, 8], [4, 5], [2, 7], [3, 6]],
      16 => [[1, 16], [8, 9], [4, 13], [5, 12], [2, 15], [7, 10], [3, 14], [6, 11]],
      32 => [
        [1, 32], [16, 17], [8, 25], [9, 24],
        [4, 29], [13, 20], [5, 28], [12, 21],
        [2, 31], [15, 18], [7, 26], [10, 23],
        [3, 30], [14, 19], [6, 27], [11, 22],
      ],
      default => collect(range(1, $size / 2))->map(fn($i) => [$i * 2 - 1, $i * 2])->toArray(),
    };
  }

  protected function autoAdvanceByes(Draw $draw): void
  {
    \Log::info("🚀 [AutoAdvance] Starting bye advancement", ['draw_id' => $draw->id]);
    
    // Load all bracket fixtures into a single in-memory collection.
    // Object references stay in sync when we mutate + save.
    // Include custom stages from playoff config
    $playoffConfig = optional($draw->settings)->playoff_config ?? [];
    $allStages = collect(['MAIN', 'PLATE', 'CONS', 'BOWL', 'SHIELD', 'SPOON'])
      ->merge(collect($playoffConfig)->pluck('slug')->map(fn($s) => strtoupper($s)))
      ->unique()->values()->all();
    $fixtures = \App\Models\Fixture::where('draw_id', $draw->id)
      ->whereIn('stage', $allStages)
      ->orderBy('round')
      ->orderBy('match_nr')
      ->get();
    
    $maxRound = $fixtures->max('round') ?? 0;
    $totalAdvanced = 0;
    
    // Process round by round (R1 → R2 → R3 …).
    // By the time we reach round N, all round N-1 matches are already resolved.
    for ($round = 1; $round <= $maxRound; $round++) {
      $roundFixtures = $fixtures->where('round', $round);
      
      foreach ($roundFixtures as $fx) {
        $hasReg1 = !is_null($fx->registration1_id);
        $hasReg2 = !is_null($fx->registration2_id);
        
        // Both players present → real match, nothing to do
        if ($hasReg1 && $hasReg2) {
          continue;
        }
        
        // Both empty → double-bye, no one to advance. Skip.
        // (Parent will see this child as "done" because both slots are null.)
        if (!$hasReg1 && !$hasReg2) {
          continue;
        }
        
        // Exactly one player. Before treating as a bye, for R2+ we must
        // verify the empty side is truly unresolvable (child is done).
        if ($round > 1) {
          $children = $fixtures->where('parent_fixture_id', $fx->id);
          
          // Both children must be "done":
          //   - has a winner_registration (resolved normally or via bye), OR
          //   - both slots empty (double-bye — nobody can ever come from it)
          $allChildrenDone = $children->count() >= 2 && $children->every(function ($c) {
            if (!is_null($c->winner_registration)) return true;                              // resolved
            if (is_null($c->registration1_id) && is_null($c->registration2_id)) return true; // double-bye
            return false;                                                                     // still pending
          });
          
          if (!$allChildrenDone) {
            // The other feeder match is a real match that still needs to be played.
            continue;
          }
        }
        
        // This is a genuine bye — advance the lone player
        $winnerId = $hasReg1 ? $fx->registration1_id : $fx->registration2_id;
        $fx->winner_registration = $winnerId;
        $fx->save();
        
        \Log::info("🎯 [AutoAdvance] Bye in R{$round}", [
          'fixture_id' => $fx->id,
          'match_nr'   => $fx->match_nr,
          'winner_id'  => $winnerId,
        ]);
        
        // Place winner into the parent fixture
        if ($fx->parent_fixture_id) {
          $parent = $fixtures->firstWhere('id', $fx->parent_fixture_id);
          
          if ($parent) {
            $childIndex = $fixtures
              ->where('parent_fixture_id', $parent->id)
              ->sortBy('match_nr')
              ->values()
              ->search(fn ($c) => $c->id === $fx->id);
            
            if ($childIndex === 0) {
              $parent->registration1_id = $winnerId;
            } else {
              $parent->registration2_id = $winnerId;
            }
            $parent->save();
            $totalAdvanced++;
          }
        }
        
        // Handle loser side → consolation / position playoff fixtures.
        // A bye has no real loser, so we must tell the consolation fixture
        // that nobody is coming from this side.  We do that by marking
        // the loser-parent fixture's slot as "resolved bye":
        //   - leave registration null (no player)
        //   - set winner_registration on the consolation fixture if the
        //     OTHER slot already has a player (that player gets a walkover)
        if ($fx->loser_parent_fixture_id) {
          $loserDest = $fixtures->firstWhere('id', $fx->loser_parent_fixture_id)
                    ?? \App\Models\Fixture::find($fx->loser_parent_fixture_id);
          
          if ($loserDest) {
            \Log::info("🔀 [AutoAdvance] Bye loser feed → consolation fixture", [
              'from_fixture' => $fx->id,
              'cons_fixture' => $loserDest->id,
            ]);
            
            // The bye side sends nobody. Check if the consolation fixture
            // now has exactly one player — if so, auto-advance that player.
            $consHasReg1 = !is_null($loserDest->registration1_id);
            $consHasReg2 = !is_null($loserDest->registration2_id);
            
            if (($consHasReg1 xor $consHasReg2) && is_null($loserDest->winner_registration)) {
              $consWinner = $consHasReg1 ? $loserDest->registration1_id : $loserDest->registration2_id;
              $loserDest->winner_registration = $consWinner;
              $loserDest->save();
              
              \Log::info("🎯 [AutoAdvance] Consolation bye walkover", [
                'cons_fixture' => $loserDest->id,
                'winner'       => $consWinner,
              ]);
              
              // Cascade: advance consolation winner to its parent
              if ($loserDest->parent_fixture_id) {
                $consParent = $fixtures->firstWhere('id', $loserDest->parent_fixture_id)
                           ?? \App\Models\Fixture::find($loserDest->parent_fixture_id);
                if ($consParent) {
                  if (is_null($consParent->registration1_id)) {
                    $consParent->registration1_id = $consWinner;
                  } elseif (is_null($consParent->registration2_id)) {
                    $consParent->registration2_id = $consWinner;
                  }
                  $consParent->save();
                }
              }
              
              // Cascade: if consolation fixture has a loser_parent (e.g. 7th/8th),
              // nobody goes there from this side either
              if ($loserDest->loser_parent_fixture_id) {
                \Log::info("🔀 [AutoAdvance] Consolation bye also feeds loser bracket (no player)", [
                  'cons_fixture'    => $loserDest->id,
                  'loser_dest'      => $loserDest->loser_parent_fixture_id,
                ]);
              }
            }
          }
        }
      }
    }
    
    \Log::info("🏁 [AutoAdvance] Complete", ['total_advanced' => $totalAdvanced]);
  }

  /**
   * Create position playoff matches (3rd/4th, 5th/6th, etc.)
   */
  protected function createPositionPlayoff(
    Draw $draw, 
    string $stage, 
    int $currentRound, 
    int $totalRounds, 
    array $prevRoundFixtures,
    int $matchNr,
    int $positionOffset = 0
  ): ?array {
    $roundsFromFinal = $totalRounds - $currentRound;
    $fixtures = [];
    
    if ($roundsFromFinal == 0) {
      $position = 3 + $positionOffset;
      $positionLabel = $this->ordinalRange($position);
      
      $fx = Fixture::create([
        'draw_id' => $draw->id,
        'stage' => $stage,
        'round' => $currentRound,
        'match_nr' => $matchNr++,
        'position' => $position,
        'playoff_type' => $positionLabel,
      ]);
      
      // Link SF fixtures to feed losers to this playoff
      foreach ($prevRoundFixtures as $sfFx) {
        $sfFx->loser_parent_fixture_id = $fx->id;
        $sfFx->save();
      }
      
      $fixtures[] = $fx;
      
      \Log::info("🏆 [PositionPlayoff] Created $positionLabel playoff", [
        'fixture_id' => $fx->id,
        'match_nr' => $fx->match_nr,
      ]);
      
    } elseif ($roundsFromFinal == 1 && count($prevRoundFixtures) >= 4) {
      // QF round - create consolation SF and position playoffs
      $pos5 = 5 + $positionOffset;
      $pos7 = 7 + $positionOffset;

      $consSF1 = Fixture::create([
        'draw_id' => $draw->id,
        'stage' => $stage,
        'round' => $currentRound,
        'match_nr' => $matchNr++,
        'playoff_type' => 'cons_sf1',
      ]);
      
      $consSF2 = Fixture::create([
        'draw_id' => $draw->id,
        'stage' => $stage,
        'round' => $currentRound,
        'match_nr' => $matchNr++,
        'playoff_type' => 'cons_sf2',
      ]);
      
      // Link QF losers to consolation SF
      if (isset($prevRoundFixtures[0])) {
        $prevRoundFixtures[0]->loser_parent_fixture_id = $consSF1->id;
        $prevRoundFixtures[0]->save();
      }
      if (isset($prevRoundFixtures[1])) {
        $prevRoundFixtures[1]->loser_parent_fixture_id = $consSF1->id;
        $prevRoundFixtures[1]->save();
      }
      if (isset($prevRoundFixtures[2])) {
        $prevRoundFixtures[2]->loser_parent_fixture_id = $consSF2->id;
        $prevRoundFixtures[2]->save();
      }
      if (isset($prevRoundFixtures[3])) {
        $prevRoundFixtures[3]->loser_parent_fixture_id = $consSF2->id;
        $prevRoundFixtures[3]->save();
      }
      
      $playoff56 = Fixture::create([
        'draw_id' => $draw->id,
        'stage' => $stage,
        'round' => $currentRound + 1,
        'match_nr' => $matchNr++,
        'position' => $pos5,
        'playoff_type' => $this->ordinalRange($pos5),
      ]);
      
      $playoff78 = Fixture::create([
        'draw_id' => $draw->id,
        'stage' => $stage,
        'round' => $currentRound + 1,
        'match_nr' => $matchNr++,
        'position' => $pos7,
        'playoff_type' => $this->ordinalRange($pos7),
      ]);
      
      // Link cons SF to playoffs
      $consSF1->parent_fixture_id = $playoff56->id;
      $consSF1->loser_parent_fixture_id = $playoff78->id;
      $consSF1->save();
      
      $consSF2->parent_fixture_id = $playoff56->id;
      $consSF2->loser_parent_fixture_id = $playoff78->id;
      $consSF2->save();
      
      $fixtures = [$consSF1, $consSF2, $playoff56, $playoff78];
      
      \Log::info("🏆 [PositionPlayoff] Created 5th-8th playoffs", [
        'cons_sf1' => $consSF1->id,
        'cons_sf2' => $consSF2->id,
        'playoff_56' => $playoff56->id,
        'playoff_78' => $playoff78->id,
      ]);
    }
    
    if (empty($fixtures)) {
      return null;
    }
    
    return [
      'fixtures' => $fixtures,
      'nextMatchNr' => $matchNr,
    ];
  }

  /**
   * Generate ordinal position range label from a position number.
   * e.g. 3 → "3rd/4th", 5 → "5th/6th", 11 → "11th/12th"
   */
  protected function ordinalRange(int $pos): string
  {
    $next = $pos + 1;
    return $this->ordinal($pos) . '/' . $this->ordinal($next);
  }

  protected function ordinal(int $n): string
  {
    $s = ['th','st','nd','rd'];
    $v = $n % 100;
    return $n . ($s[($v - 20) % 10] ?? $s[$v] ?? $s[0]);
  }



  // ============================================================
  // GENERATE 2nd/3rd PLAYOFF BRACKET (SERVICE)
  // ============================================================
  // ============================================================
// GENERATE 2nd/3rd PLAYOFF BRACKET (8-player, PLATE)
// ============================================================
  public function generateSecondThirdBracket(Request $request, Draw $draw)
  {
    \Log::info("===============================================");
    \Log::info("🎾 [PlateBracket] START GENERATION", [
      'draw_id' => $draw->id
    ]);

    try {
      // Step 1 — Build seeds from RR standings (2nd & 3rd in each box)
      $seeds = $this->builder->buildSecondThirdSeedsFromRRStandings($draw);

      \Log::info("🧬 [PlateBracket] SEEDS BUILT", $seeds);

      // Step 2 — Clear any old PLATE fixtures
      $this->builder->clearSecondThirdPlayoffFixtures($draw);

      // Step 3 — Create QF, SF, Final, 3rd/4th
      $fixtures = $this->builder->createSecondThirdPlayoffFixtures($draw, $seeds);

      \Log::info("🏁 [PlateBracket] DONE – Fixtures Created", [
        'qf1' => $fixtures['qf1']->id,
        'qf2' => $fixtures['qf2']->id,
        'qf3' => $fixtures['qf3']->id,
        'qf4' => $fixtures['qf4']->id,
        'sf1' => $fixtures['sf1']->id,
        'sf2' => $fixtures['sf2']->id,
        'final' => $fixtures['final']->id,
        'third' => $fixtures['third']->id,
      ]);

      return response()->json([
        'success' => true,
        'message' => '2nd/3rd playoff bracket (PLATE) created.',
        'fixtures' => $fixtures,
      ]);

    } catch (\Throwable $e) {

      \Log::error('[PlateBracket] ERROR', [
        'draw_id' => $draw->id,
        'error' => $e->getMessage(),
      ]);

      return response()->json([
        'success' => false,
        'message' => $e->getMessage(),
      ], 422);
    }
  }
  // ============================================================
// PLATE BRACKET VIEW (2nd/3rd Playoff)
// ============================================================



 // ============================================================
  // MAIN BRACKET VIEW (SVG)
  // Uses BracketEngine for Interpro, DynamicBracketEngine for others
  // ============================================================
  public function mainBracket(Draw $draw)
  {
    $eventType = $draw->event->eventType ?? null;
    $isEmpty = request()->boolean('empty');

    // Use original BracketEngine for Interpro (eventType 13)
    if ($eventType == 13) {
      $engine = new \App\Services\BracketEngine($draw);
      $svgData = $engine->build();

      return view('backend.draw.roundrobin.draw-svg', [
        'draw' => $draw,
        'svg' => $svgData,
      ]);
    }

    // Use DynamicBracketEngine for all other event types (individual draws)
    $engine = new \App\Services\DynamicBracketEngine($draw);
    $svgData = $engine->build();

    return view('backend.draw.roundrobin.dynamic-bracket-svg', [
      'draw' => $draw,
      'svgData' => $svgData,
      'emptyBracket' => $isEmpty,
    ]);
  }



  public function plateBracket(Draw $draw)
  {
    $data = $this->builder->getMainAndPlateFixtures($draw);

    return view('backend.draw.roundrobin.plate-bracket', [
      'draw' => $draw,
      'qf_plate' => $data['qf_plate'] ?? [],
      'sf_plate' => $data['sf_plate'] ?? [],
      'final_plate' => $data['final_plate'] ?? null,
    ]);
    }
  
    public function toggleLock(Request $request, Draw $draw)
    {
      $draw->locked = !$draw->locked;
      $draw->save();

      Log::info("🔒 [toggleLock] Draw lock toggled", [
        'draw_id' => $draw->id,
        'locked' => $draw->locked,
      ]);

      return response()->json([
        'success' => true,
        'locked' => $draw->locked,
        'message' => $draw->locked ? 'Draw has been locked.' : 'Draw has been unlocked.',
      ]);
    }

    public function regenerateRR(Request $request, Draw $draw)
    {
      Log::info("🔄 [regenerateRR] Starting fixture regeneration", [
        'draw_id' => $draw->id,
        'draw_name' => $draw->name,
      ]);

      try {
        // Clear existing RR fixtures for this draw
        $deletedRR = $draw->drawFixtures()->where('stage', 'RR')->delete();

        // Clear existing bracket/playoff fixtures for this draw
        $deletedBracket = $draw->drawFixtures()->where('stage', '!=', 'RR')->delete();
      
        Log::info("🗑 [regenerateRR] Deleted existing fixtures", [
          'deleted_rr' => $deletedRR,
          'deleted_bracket' => $deletedBracket,
        ]);

        // Regenerate fixtures based on current group assignments
        $this->builder->regenerateRoundRobinFixtures($draw);
      
        // Reload to get count
        $draw->load('drawFixtures');
      
        Log::info("✅ [regenerateRR] Fixtures regenerated successfully", [
          'new_fixture_count' => $draw->drawFixtures->count(),
        ]);

        // Return JSON for AJAX requests
        if ($request->ajax() || $request->wantsJson()) {
          return response()->json([
            'success' => true,
            'message' => 'Round robin fixtures regenerated successfully.',
            'fixture_count' => $draw->drawFixtures->count(),
          ]);
        }

        return back()->with('success', 'Round robin fixtures regenerated.');
      
      } catch (\Throwable $e) {
        Log::error("❌ [regenerateRR] Error", [
          'draw_id' => $draw->id,
          'error' => $e->getMessage(),
        ]);

        if ($request->ajax() || $request->wantsJson()) {
          return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
          ], 422);
        }

        return back()->with('error', 'Failed to regenerate fixtures: ' . $e->getMessage());
      }
    }
  
    public function saveGroups(Request $request, Draw $draw)
  {
    Log::info("🎾 [saveGroups] Start", [
      'draw_id' => $draw->id,
      'payload' => $request->all(),
    ]);

    // Validate structure
    if (!$request->has('groups') || !is_array($request->groups)) {
      Log::error("❌ [saveGroups] Invalid payload - 'groups' missing or not array", [
        'payload' => $request->all()
      ]);

      return response()->json([
        'status' => 'error',
        'message' => 'Invalid groups payload.'
      ], 422);
    }

    $updatedGroups = 0;

    foreach ($request->groups as $groupData) {

      $groupId = $groupData['group_id'] ?? null;
      $registrationIds = $groupData['registration_ids'] ?? [];

      Log::info("➡️ [saveGroups] Processing group", [
        'group_id' => $groupId,
        'registration_ids' => $registrationIds,
      ]);

      if (!$groupId) {
        Log::warning("⚠️ [saveGroups] Skipping group - missing group_id", [
          'data' => $groupData
        ]);
        continue;
      }

      // Remove existing links
      Log::info("🗑 [saveGroups] Clearing old assignments", [
        'group_id' => $groupId,
      ]);

      DB::table('draw_group_registrations')
        ->where('draw_group_id', $groupId)
        ->delete();

      // Insert new assignments with seed based on array order
      foreach ($registrationIds as $index => $regId) {
        Log::info("➕ [saveGroups] Adding registration to group", [
          'group_id' => $groupId,
          'registration_id' => $regId,
          'seed' => $index + 1,
        ]);

        DB::table('draw_group_registrations')->insert([
          'draw_group_id' => $groupId,
          'registration_id' => $regId,
          'seed' => $index + 1,
        ]);
      }

      $updatedGroups++;
    }

    Log::info("✅ [saveGroups] Complete", [
      'draw_id' => $draw->id,
      'groups_processed' => $updatedGroups
    ]);

    return response()->json([
      'status' => 'ok',
      'groups_processed' => $updatedGroups,
    ]);
  }
}
