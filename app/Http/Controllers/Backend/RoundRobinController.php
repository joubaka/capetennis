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
        return response()->json($response);
      }

      // BRACKET MODE
      $response = $this->builder->saveBracketScore($fixture, $validSets);
      return response()->json($response);
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

      // Clear existing playoff fixtures
      Fixture::where('draw_id', $draw->id)
        ->whereIn('stage', ['MAIN', 'PLATE', 'CONS', 'BOWL', 'SHIELD', 'SPOON'])
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
      
      foreach ($groups as $group) {
        foreach ($positions as $pos) {
          $key = $group->name . $pos;
          if (isset($seeds[$key]) && $seeds[$key]) {
            $players[] = $seeds[$key];
          }
        }
      }

      \Log::info("🎾 [GenerateDynamic] Players for bracket", [
        'stage' => $stage,
        'player_count' => count($players),
        'player_ids' => $players,
      ]);

      // Generate bracket fixtures
      $numRounds = (int) ceil(log(max($size, 2), 2));
      $fixtures = [];
      $positionPlayoffs = [];
      
      // Round 1 fixtures
      $matchesInR1 = $size / 2;
      for ($i = 0; $i < $matchesInR1; $i++) {
        $p1 = $players[$i * 2] ?? null;
        $p2 = $players[$i * 2 + 1] ?? null;
        
        $fx = Fixture::create([
          'draw_id' => $draw->id,
          'stage' => $stage,
          'round' => 1,
          'match_nr' => $matchNr++,
          'position' => null, // Will be set for playoff matches
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
        // Semi-final losers → 3rd/4th playoff
        // Quarter-final losers → 5th/8th playoffs (if enabled)
        $positionPlayoff = $this->createPositionPlayoff(
          $draw, 
          $stage, 
          $round, 
          $numRounds, 
          $prevRound, 
          $matchNr
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
    }

    // Auto-advance all byes in the brackets
    $this->autoAdvanceByes($draw);

    return $allFixtures;
  }

  /**
   * Auto-advance players when opponent is missing (bye)
   * Cascades through the bracket until player meets an actual opponent
   */
  protected function autoAdvanceByes(Draw $draw): void
  {
    \Log::info("🚀 [AutoAdvance] Starting bye advancement", ['draw_id' => $draw->id]);
    
    $maxIterations = 10; // Prevent infinite loops
    $iteration = 0;
    $totalAdvanced = 0;
    
    do {
      $iteration++;
      $advancedThisRound = 0;
      
      // Reload fixtures with relationships
      $fixtures = \App\Models\Fixture::where('draw_id', $draw->id)
        ->whereIn('stage', ['MAIN', 'PLATE', 'CONS', 'BOWL', 'SHIELD', 'SPOON'])
        ->orderBy('round')
        ->orderBy('match_nr')
        ->get();
      
      foreach ($fixtures as $fx) {
        $hasReg1 = !is_null($fx->registration1_id);
        $hasReg2 = !is_null($fx->registration2_id);
        
        // Skip if both players present or both missing
        if ($hasReg1 === $hasReg2) {
          continue;
        }
        
        // Determine the winner (player with no opponent)
        $winnerId = $hasReg1 ? $fx->registration1_id : $fx->registration2_id;
        $winnerSlot = $hasReg1 ? 1 : 2;
        
        \Log::info("🎯 [AutoAdvance] Bye detected", [
          'fixture_id' => $fx->id,
          'match_nr' => $fx->match_nr,
          'round' => $fx->round,
          'winner_id' => $winnerId,
          'slot' => $winnerSlot,
        ]);
        
        // Advance to parent fixture (next round)
        if ($fx->parent_fixture_id) {
          $parent = \App\Models\Fixture::find($fx->parent_fixture_id);
          
          if ($parent) {
            // Determine which slot in parent this match feeds
            // Check if this is the first or second child
            $childFixtures = \App\Models\Fixture::where('parent_fixture_id', $parent->id)
              ->orderBy('match_nr')
              ->pluck('id');
            
            $childIndex = $childFixtures->search($fx->id);
            $parentSlot = ($childIndex === 0) ? 1 : 2;
            
            // Set winner in parent fixture
            if ($parentSlot == 1) {
              $parent->registration1_id = $winnerId;
            } else {
              $parent->registration2_id = $winnerId;
            }
            $parent->save();
            
            \Log::info("✅ [AutoAdvance] Advanced to parent", [
              'from_fixture' => $fx->id,
              'to_fixture' => $parent->id,
              'parent_round' => $parent->round,
              'parent_slot' => $parentSlot,
              'winner_id' => $winnerId,
            ]);
            
            $advancedThisRound++;
          }
        }
        
        // Also mark this fixture as complete with winner
        $fx->winner_registration = $winnerId;
        $fx->save();
      }
      
      $totalAdvanced += $advancedThisRound;
      
      \Log::info("🔄 [AutoAdvance] Iteration $iteration complete", [
        'advanced' => $advancedThisRound,
        'total' => $totalAdvanced,
      ]);
      
    } while ($advancedThisRound > 0 && $iteration < $maxIterations);
    
    \Log::info("🏁 [AutoAdvance] Complete", [
      'iterations' => $iteration,
      'total_advanced' => $totalAdvanced,
    ]);
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
    int $matchNr
  ): ?array {
    $roundsFromFinal = $totalRounds - $currentRound;
    $fixtures = [];
    
    // Determine position based on round
    // SF (1 round from final) → 3rd/4th playoff
    // QF (2 rounds from final) → 5th-8th playoffs
    // R16 (3 rounds from final) → 9th-16th playoffs
    
    if ($roundsFromFinal == 0) {
      // This is the final round - SF losers play for 3rd/4th
      $position = 3; // 3rd/4th playoff
      $positionLabel = '3rd/4th';
      
      // Create single 3rd/4th playoff match
      // Losers from SF (which are the fixtures in prevRound that feed into Final)
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
      // QF round - create 5th/6th and 7th/8th playoffs
      // First, create SF for losers (consolation SF)
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
      // QF1 & QF2 losers → Cons SF1
      // QF3 & QF4 losers → Cons SF2
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
      
      // Create 5th/6th playoff (winners of cons SF)
      $playoff56 = Fixture::create([
        'draw_id' => $draw->id,
        'stage' => $stage,
        'round' => $currentRound + 1,
        'match_nr' => $matchNr++,
        'position' => 5,
        'playoff_type' => '5th/6th',
      ]);
      
      // Create 7th/8th playoff (losers of cons SF)
      $playoff78 = Fixture::create([
        'draw_id' => $draw->id,
        'stage' => $stage,
        'round' => $currentRound + 1,
        'match_nr' => $matchNr++,
        'position' => 7,
        'playoff_type' => '7th/8th',
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
  
    public function regenerateRR(Request $request, Draw $draw)
    {
      Log::info("🔄 [regenerateRR] Starting fixture regeneration", [
        'draw_id' => $draw->id,
        'draw_name' => $draw->name,
      ]);

      try {
        // Clear existing RR fixtures for this draw
        $deleted = $draw->drawFixtures()->where('stage', 'RR')->delete();
      
        Log::info("🗑 [regenerateRR] Deleted existing RR fixtures", [
          'deleted_count' => $deleted,
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

      // Insert new assignments
      foreach ($registrationIds as $regId) {
        Log::info("➕ [saveGroups] Adding registration to group", [
          'group_id' => $groupId,
          'registration_id' => $regId,
        ]);

        DB::table('draw_group_registrations')->insert([
          'draw_group_id' => $groupId,
          'registration_id' => $regId,
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
