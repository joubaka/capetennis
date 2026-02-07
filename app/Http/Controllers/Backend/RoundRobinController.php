<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Draw;
use App\Models\Fixture;
use App\Models\CategoryEvent;
use Illuminate\Http\Request;
use App\Services\InterproDrawBuilder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class RoundRobinController extends Controller
{
  protected InterproDrawBuilder $builder;

  public function __construct(InterproDrawBuilder $builder)
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
      'groups.groupRegistrations.registration.players'
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
    // NOT INTERPRO — SIMPLE VIEW
    // -------------------------------------------------------------
    Log::warning("⚠️ Draw {$draw->id} is NOT Interpro — using default view");

    return view('backend.draw.default_show', ['draw' => $draw]);
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
    Log::info("🎾 [Controller] saveScore() called", [
      'fixture_id' => $id,
      'raw_sets' => $request->input('sets'),
    ]);

    $fixture = Fixture::with(['registration1', 'registration2'])
      ->findOrFail($id);

    Log::info("🎾 [Controller] Loaded fixture", [
      'id' => $fixture->id,
      'stage' => $fixture->stage,
      'r1' => $fixture->registration1?->display_name,
      'r2' => $fixture->registration2?->display_name,
    ]);

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

    Log::info("🎾 [Controller] Parsed valid sets", [
      'validSets' => $validSets,
    ]);

    if (empty($validSets)) {
      Log::warning("⚠️ [Controller] No valid sets found — aborting");
      return response()->json([
        'message' => 'Please enter at least one valid set (e.g., 6-4)',
      ], 422);
    }

    // ------------------------
    // SELECT MODE (RR / BRACKET)
    // ------------------------
    if ($fixture->stage === 'RR') {

      Log::info("🎯 [Controller] RR MODE detected — calling saveScoreRoundRobin");

      $response = $this->builder->saveScore($fixture, $validSets);

      Log::info("🎯 [Controller] RR Response received", [
        'response' => $response,
      ]);

      return response()->json($response);
    }


    // ------------------------
    // BRACKET MODE
    // ------------------------
    Log::info("🏆 [Controller] BRACKET MODE detected — calling saveBracketScore");

    $response = $this->builder->saveBracketScore($fixture, $validSets);

    Log::info("🏆 [Controller] Bracket Response received", [
      'response' => $response,
    ]);

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
      'draw_id' => $draw->id
    ]);

    try {

      // 1) Build main seeds
      $seeds = $this->builder->buildMainSeedsFromRRStandings($draw)
      ;

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
        'third_id' => null,   // Main bracket no longer has a 3rd/4th playoff

      ]);

      // ⭐ NEW plate system already included inside generateFullBracketFixtures()
      $plateFixtures = $fixtures['plate'] ?? [];

      \Log::info("🏁 [PlateBracket] FULL PLATE (11 matches) CREATED", $plateFixtures);

      return response()->json([
        'success' => true,
        'message' => 'Main playoff bracket created.',
        'fixtures' => $fixtures,
        'plateFixtures' => $plateFixtures,
      ]);

    } catch (\Throwable $e) {

      \Log::error('[MainBracket] ERROR', [
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
  // MAIN BRACKET VIEW (SERVICE)
  // ============================================================
  public function mainBracket(Draw $draw)
  {
    $engine = new \App\Services\BracketEngine($draw);
    $svgData = $engine->build();

    return view('backend.draw.roundrobin.draw-svg', [
      'draw' => $draw,
      'svg' => $svgData,
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
  public function regenerateRR(Draw $draw)
  {
    $this->builder->generateRoundRobinFixtures($draw);

    return back()->with('success', 'Round robin fixtures regenerated.');
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
