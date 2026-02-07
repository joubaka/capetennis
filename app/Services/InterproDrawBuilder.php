<?php

namespace App\Services;

use App\Models\Draw;
use App\Models\Fixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InterproDrawBuilder
{
  // ============================================================
  // PUBLIC: ROUND ROBIN HUB (MATRIX + OOP + STANDINGS)
  // ============================================================
  public function loadRoundRobinHub(Draw $draw): array
  {
    Log::info("===============================================");
    Log::info("🎾 [RR HUB] Loading Round Robin Hub", [
      'draw_id' => $draw->id,
      'draw_name' => $draw->name,
    ]);

    // Load all relationships
    $draw->load([
      'groups.groupRegistrations.registration.players',
      'drawFixtures.registration1.players',
      'drawFixtures.registration2.players',
      'drawFixtures.fixtureResults',
      'drawFixtures.drawGroup',
      'drawFixtures.orderOfPlay.venue',     // FIX
      'drawFixtures.venue',                 // FIX (venues coming from fixtures)
    ]);

    Log::info("📦 [RR HUB] Relationships loaded", [
      'groups' => $draw->groups->count(),
      'fixtures' => $draw->drawFixtures->count(),
    ]);

    // If no RR fixtures: generate
    if ($draw->drawFixtures->isEmpty()) {
      Log::warning("⚠️ [RR HUB] No fixtures found — generating RR fixtures");
      $this->generateRoundRobinFixtures($draw);

      $draw->load([
        'groups.registrations.players',
        'drawFixtures.registration1.players',
        'drawFixtures.registration2.players',
        'drawFixtures.fixtureResults',
        'drawFixtures.drawGroup',
      ]);

      Log::info("🔄 [RR HUB] Fixtures regenerated", [
        'fixtures' => $draw->drawFixtures->count(),
      ]);
    }

    // ---------------------------------------------------------------------
    // RR FIXTURES MATRIX
    // ---------------------------------------------------------------------
    $rrFixtures = [];

    foreach ($draw->drawFixtures as $fx) {

      // Build set strings
      $allSets = $fx->fixtureResults
        ->sortBy('set_nr')
        ->map(fn($r) => "{$r->registration1_score}-{$r->registration2_score}")
        ->toArray();

      // Extract last-set score for matrix
      $lastSet = $fx->fixtureResults->sortBy('set_nr')->last();
      $home_score = $lastSet?->registration1_score;
      $away_score = $lastSet?->registration2_score;

      // Determine draw group
      $gid = $fx->draw_group_id ?: $fx->drawGroup?->id;
      if (!$gid) {
        continue;
      }

      // FIX: Correct assignment (remove “[]= [] =”)
      $rrFixtures[$gid][] = [
        'id' => $fx->id,
        'group_id' => $gid,
        'r1_id' => $fx->registration1_id,
        'r2_id' => $fx->registration2_id,

        'name1' => $fx->registration1?->display_name ?? 'TBD',
        'name2' => $fx->registration2?->display_name ?? 'TBD',

        'all_sets' => $allSets,
        'score' => implode(', ', $allSets),

        'home_score' => $home_score,
        'away_score' => $away_score,

        // FIX: Winner must come from results, not score comparison
        'winner' => $lastSet?->winner_registration,

        // FIX: include time + venue
        'time' => optional($fx->orderOfPlay)->time,
        'venue_name' => optional(optional($fx->orderOfPlay)->venue)->name,
      ];
    }

    // ---------------------------------------------------------------------
    // ORDER OF PLAY
    // ---------------------------------------------------------------------
    $oops = $draw->drawFixtures()
      ->with(['registration1.players', 'registration2.players', 'fixtureResults', 'orderOfPlay.venue'])
      ->orderByRaw("
            FIELD(stage, 'RR', 'MAIN', 'PLATE', 'CONS'),
            round ASC,
            match_nr ASC
        ")
      ->get()
      ->map(function ($fx) {

        $sets = $fx->fixtureResults
          ->sortBy('set_nr')
          ->map(fn($r) => "{$r->registration1_score}-{$r->registration2_score}")
          ->implode(', ');

        $winner = optional($fx->fixtureResults->sortBy('set_nr')->last())->winner_registration;

        return [
          'id' => $fx->id,
          'stage' => $fx->stage,
          'round' => $fx->round,
          'match_nr' => $fx->match_nr,

          'home' => $fx->registration1?->display_name ?? 'TBD',
          'away' => $fx->registration2?->display_name ?? 'TBD',
          'r1_id' => $fx->registration1_id,
          'r2_id' => $fx->registration2_id,

          'time' => optional($fx->orderOfPlay)->time,
          'venue_name' => optional(optional($fx->orderOfPlay)->venue)->name,

          'score' => $sets,
          'winner' => $winner,
        ];
      });

    // ---------------------------------------------------------------------
    // STANDINGS
    // ---------------------------------------------------------------------
    $standings = $this->buildStandingsFromFixtures($draw);

    Log::info("✅ [RR HUB] READY");

    return [
      'rrFixtures' => $rrFixtures,
      'oops' => $oops,
      'standings' => $standings,
    ];
  }

  // ============================================================
  // PUBLIC: SAVE SCORE (FROM 3-SET MODAL)
  // ============================================================
  /**
   * @param Fixture $fixture  RR fixture with registration1/2 loaded
   * @param array   $validSets  [[6,4],[3,6],[10,7]]...
   */
  public function saveScore(Fixture $fixture, array $sets): array
  {
    Log::info("🎾 [SaveScore RR] Saving Round Robin score", [
      'fixture' => $fixture->id,
      'r1' => $fixture->registration1_id,
      'r2' => $fixture->registration2_id,
      'sets' => $sets
    ]);

    return $this->saveScoreRoundRobin($fixture, $sets);
  }


  // ============================================================
  // PUBLIC: MAIN BRACKET (TOP-OF-BOX PLAYOFF)
  // ============================================================
  public function buildMainSeedsFromRRStandings(Draw $draw): array
  {
    Log::info("🎯 [MainSeeds] START buildMainSeedsFromRRStandings", [
      'draw_id' => $draw->id,
      'group_count' => $draw->groups->count(),
    ]);

    $draw->loadMissing([
      'groups.registrations',
      'drawFixtures.fixtureResults',
    ]);
    Log::info("📦 [MainSeeds] Relations loaded");

    // 1) Init standings
    Log::info("🧮 [MainSeeds] Step 1 — Init standings");

    $standings = [];
    foreach ($draw->groups as $group) {
      Log::info("  ➤ Init Group {$group->name} ({$group->id})");
      foreach ($group->registrations as $reg) {
        $standings[$group->id][$reg->id] = [
          'reg_id' => $reg->id,
          'player' => $reg->display_name,
          'wins' => 0,
          'losses' => 0,
          'sets_won' => 0,
          'sets_lost' => 0,
        ];
      }
    }

    // 2) Fill from fixtures
    Log::info("🧮 [MainSeeds] Step 2 — Fill standings from fixtures");

    foreach ($draw->drawFixtures as $fx) {
      if ($fx->stage !== 'RR')
        continue;

      if ($fx->fixtureResults->isEmpty()) {
        Log::debug("  ⏭ Fixture {$fx->id} has no results — skipping");
        continue;
      }

      $gid = $fx->draw_group_id;
      $home = $fx->registration1_id;
      $away = $fx->registration2_id;

      $homeSets = 0;
      $awaySets = 0;

      foreach ($fx->fixtureResults as $set) {
        if ($set->registration1_score > $set->registration2_score) {
          $homeSets++;
        } else {
          $awaySets++;
        }
      }

      $standings[$gid][$home]['sets_won'] += $homeSets;
      $standings[$gid][$home]['sets_lost'] += $awaySets;

      $standings[$gid][$away]['sets_won'] += $awaySets;
      $standings[$gid][$away]['sets_lost'] += $homeSets;

      $last = $fx->fixtureResults->sortBy('set_nr')->last();

      if ($last) {
        $winner = $last->winner_registration;
        if ($winner == $home) {
          $standings[$gid][$home]['wins']++;
          $standings[$gid][$away]['losses']++;
        } else {
          $standings[$gid][$away]['wins']++;
          $standings[$gid][$home]['losses']++;
        }
      }
    }

    // 3) Sort
    Log::info("🧮 [MainSeeds] Step 3 — Sort standings");

    $sorted = [];
    foreach ($standings as $gid => $rows) {
      $rows = array_values($rows);

      usort($rows, function ($a, $b) {
        if ($a['wins'] !== $b['wins'])
          return $b['wins'] <=> $a['wins'];

        $diffA = $a['sets_won'] - $a['sets_lost'];
        $diffB = $b['sets_won'] - $b['sets_lost'];
        return $diffB <=> $diffA;
      });

      $sorted[$gid] = $rows;
    }

    // 4) Build seeds
    Log::info("🧮 [MainSeeds] Step 4 — Build seeds");

    $groups = $draw->groups->sortBy('name')->values();
    $groupCount = $groups->count();

    if ($groupCount === 4) {
      Log::info("  🎯 Mode: 4 groups (A, B, C, D)");

      $gA = $groups[0];
      $gB = $groups[1];
      $gC = $groups[2];
      $gD = $groups[3];

      $result = [
        // A group
        'A1' => $sorted[$gA->id][0]['reg_id'] ?? null,
        'A2' => $sorted[$gA->id][1]['reg_id'] ?? null,
        'A3' => $sorted[$gA->id][2]['reg_id'] ?? null,

        // B group
        'B1' => $sorted[$gB->id][0]['reg_id'] ?? null,
        'B2' => $sorted[$gB->id][1]['reg_id'] ?? null,
        'B3' => $sorted[$gB->id][2]['reg_id'] ?? null,

        // C group
        'C1' => $sorted[$gC->id][0]['reg_id'] ?? null,
        'C2' => $sorted[$gC->id][1]['reg_id'] ?? null,
        'C3' => $sorted[$gC->id][2]['reg_id'] ?? null,

        // D group
        'D1' => $sorted[$gD->id][0]['reg_id'] ?? null,
        'D2' => $sorted[$gD->id][1]['reg_id'] ?? null,
        'D3' => $sorted[$gD->id][2]['reg_id'] ?? null,
      ];

      Log::info("  ✔ FULL 12-SEED MAP", $result);
      return $result;
    }

    // 2-group unchanged
    if ($groupCount === 2) {
      // your existing 2-group logic stays
    }

    throw new \Exception("Main bracket currently supports only 2 or 4 groups.");
  }

  // ============================================================
  // PUBLIC: 2nd/3rd PLAYOFF SEEDING (PLATE)
  // ============================================================
  public function buildSecondThirdSeedsFromRRStandings(Draw $draw): array
  {
    Log::info("🎯 [PlateSeeds] START buildSecondThirdSeedsFromRRStandings", [
      'draw_id' => $draw->id,
      'group_count' => $draw->groups->count(),
    ]);

    $draw->loadMissing([
      'groups.registrations',
      'drawFixtures.fixtureResults',
    ]);
    Log::info("📦 [PlateSeeds] Relations loaded");

    // 1) Init standings
    Log::info("🧮 [PlateSeeds] Step 1 — Init standings");

    $standings = [];
    foreach ($draw->groups as $group) {
      Log::info("  ➤ Init Group {$group->name} ({$group->id})");

      foreach ($group->registrations as $reg) {
        $standings[$group->id][$reg->id] = [
          'reg_id' => $reg->id,
          'player' => $reg->display_name,
          'wins' => 0,
          'losses' => 0,
          'sets_won' => 0,
          'sets_lost' => 0,
        ];
      }
    }

    // 2) Fill from fixtures
    Log::info("🧮 [PlateSeeds] Step 2 — Fill standings from fixtures");

    foreach ($draw->drawFixtures as $fx) {
      if ($fx->stage !== 'RR') {
        continue;
      }

      if ($fx->fixtureResults->isEmpty()) {
        Log::debug("  ⏭ Fixture {$fx->id} has no results — skipping");
        continue;
      }

      $gid = $fx->draw_group_id;
      $home = $fx->registration1_id;
      $away = $fx->registration2_id;

      Log::info("  ⚔ Fixture {$fx->id}: {$home} vs {$away}");

      $homeSets = 0;
      $awaySets = 0;

      foreach ($fx->fixtureResults as $set) {
        if ($set->registration1_score > $set->registration2_score) {
          $homeSets++;
        } else {
          $awaySets++;
        }
      }

      Log::info("     → Set totals", [
        'home_sets' => $homeSets,
        'away_sets' => $awaySets,
      ]);

      $standings[$gid][$home]['sets_won'] += $homeSets;
      $standings[$gid][$home]['sets_lost'] += $awaySets;

      $standings[$gid][$away]['sets_won'] += $awaySets;
      $standings[$gid][$away]['sets_lost'] += $homeSets;

      $last = $fx->fixtureResults->sortBy('set_nr')->last();
      if ($last) {
        $winner = $last->winner_registration;

        if ($winner == $home) {
          $standings[$gid][$home]['wins']++;
          $standings[$gid][$away]['losses']++;
        } else {
          $standings[$gid][$away]['wins']++;
          $standings[$gid][$home]['losses']++;
        }

        Log::info("     ✔ Match Winner", ['winner' => $winner]);
      }
    }

    // 3) Sort inside each group
    Log::info("🧮 [PlateSeeds] Step 3 — Sort standings inside each group");

    $sorted = [];
    foreach ($standings as $gid => $rows) {
      $rows = array_values($rows);

      Log::info("  📊 Before sort (Group ID {$gid})", $rows);

      usort($rows, function ($a, $b) {
        if ($a['wins'] !== $b['wins']) {
          return $b['wins'] <=> $a['wins'];
        }

        $diffA = $a['sets_won'] - $a['sets_lost'];
        $diffB = $b['sets_won'] - $b['sets_lost'];

        return $diffB <=> $diffA;
      });

      Log::info("  📈 After sort (Group ID {$gid})", $rows);

      $sorted[$gid] = $rows;
    }

    // 4) Build seeds for 2nd & 3rd positions
    Log::info("🧮 [PlateSeeds] Step 4 — Build seeds (2nd & 3rd)");

    $groups = $draw->groups->sortBy('name')->values();
    $groupCount = $groups->count();

    if ($groupCount !== 4) {
      Log::error("❌ [PlateSeeds] Needs exactly 4 groups (A–D)", [
        'group_count' => $groupCount,
      ]);
      throw new \Exception("2nd/3rd playoff requires exactly 4 groups (A, B, C, D).");
    }

    $gA = $groups[0];
    $gB = $groups[1];
    $gC = $groups[2];
    $gD = $groups[3];

    foreach ([$gA, $gB, $gC, $gD] as $g) {
      if (!isset($sorted[$g->id]) || count($sorted[$g->id]) < 3) {
        throw new \Exception("Group {$g->name} needs at least 3 players for 2nd/3rd playoff.");
      }
    }

    $result = [
      'A2' => $sorted[$gA->id][1]['reg_id'],
      'A3' => $sorted[$gA->id][2]['reg_id'],
      'B2' => $sorted[$gB->id][1]['reg_id'],
      'B3' => $sorted[$gB->id][2]['reg_id'],
      'C2' => $sorted[$gC->id][1]['reg_id'],
      'C3' => $sorted[$gC->id][2]['reg_id'],
      'D2' => $sorted[$gD->id][1]['reg_id'],
      'D3' => $sorted[$gD->id][2]['reg_id'],
    ];

    Log::info("  ✔ [PlateSeeds] Seeds (2nd/3rd) built", $result);

    return $result;
  }

  // ============================================================
  // PUBLIC: CLEAR / CREATE MAIN & PLATE FIXTURES
  // ============================================================
  public function clearMainPlayoffFixtures(Draw $draw): void
  {
    Log::info("🧹 [MainBracket] Clearing old MAIN fixtures", [
      'draw_id' => $draw->id,
    ]);

    Fixture::where('draw_id', $draw->id)
      ->where('stage', 'MAIN')
      ->delete();
  }

  public function clearSecondThirdPlayoffFixtures(Draw $draw): void
  {
    Log::info("🧹 [PlateBracket] Clearing old PLATE fixtures", [
      'draw_id' => $draw->id,
    ]);

    Fixture::where('draw_id', $draw->id)
      ->where('stage', 'PLATE')
      ->delete();
  }

  public function createMainPlayoffFixtures(Draw $draw, array $s): array
  {
    Log::info("🎯 [MainBracket] Creating playoff fixtures", [
      'draw_id' => $draw->id,
      'seeds' => $s,
    ]);

    $final = Fixture::create([
      'draw_id' => $draw->id,
      'stage' => 'MAIN',
      'round' => 2,
      'match_nr' => 2003,
      'position' => 3,
    ]);

    $third = Fixture::create([
      'draw_id' => $draw->id,
      'stage' => 'MAIN',
      'round' => 2,
      'match_nr' => 2004,
      'position' => 4,
    ]);

    // 4-group mode
    if (isset($s['A1'], $s['B1'], $s['C1'], $s['D1'])) {
      Log::info("🏆 [MainBracket] Using 4-group seeding (A1,B1,C1,D1)");

      $sf1 = Fixture::create([
        'draw_id' => $draw->id,
        'stage' => 'MAIN',
        'round' => 1,
        'match_nr' => 2001,
        'position' => 1,
        'registration1_id' => $s['A1'],
        'registration2_id' => $s['D1'],
      ]);

      $sf2 = Fixture::create([
        'draw_id' => $draw->id,
        'stage' => 'MAIN',
        'round' => 1,
        'match_nr' => 2002,
        'position' => 2,
        'registration1_id' => $s['B1'],
        'registration2_id' => $s['C1'],
      ]);

      Log::info("✅ [MainBracket] 4-group SF fixtures created", [
        'sf1' => $sf1->id,
        'sf2' => $sf2->id,
      ]);

      return compact('sf1', 'sf2', 'final', 'third');
    }

    // 2-group mode
    if (isset($s['A1'], $s['A2'], $s['B1'], $s['B2'])) {
      Log::info("🏆 [MainBracket] Using 2-group seeding (A1,A2,B1,B2)");

      $sf1 = Fixture::create([
        'draw_id' => $draw->id,
        'stage' => 'MAIN',
        'round' => 1,
        'match_nr' => 2001,
        'position' => 1,
        'registration1_id' => $s['A1'],
        'registration2_id' => $s['B2'],
      ]);

      $sf2 = Fixture::create([
        'draw_id' => $draw->id,
        'stage' => 'MAIN',
        'round' => 1,
        'match_nr' => 2002,
        'position' => 2,
        'registration1_id' => $s['B1'],
        'registration2_id' => $s['A2'],
      ]);

      Log::info("✅ [MainBracket] 2-group SF fixtures created", [
        'sf1' => $sf1->id,
        'sf2' => $sf2->id,
      ]);

      return compact('sf1', 'sf2', 'final', 'third');
    }

    throw new \Exception("Invalid seeding structure for playoffs.");
  }

  public function createSecondThirdPlayoffFixtures(Draw $draw, array $s): array
  {
    Log::info("🎯 [PlateBracket] Creating 2nd/3rd playoff fixtures", [
      'draw_id' => $draw->id,
      'seeds' => $s,
    ]);

    // -------------------------------
    // QUARTERFINALS
    // -------------------------------
    $qf1 = Fixture::create([
      'draw_id' => $draw->id,
      'stage' => 'PLATE',
      'round' => 1,
      'match_nr' => 3001,
      'position' => 1,
      'registration1_id' => $s['A2'],
      'registration2_id' => $s['D3'],
    ]);

    $qf2 = Fixture::create([
      'draw_id' => $draw->id,
      'stage' => 'PLATE',
      'round' => 1,
      'match_nr' => 3002,
      'position' => 2,
      'registration1_id' => $s['B2'],
      'registration2_id' => $s['C3'],
    ]);

    $qf3 = Fixture::create([
      'draw_id' => $draw->id,
      'stage' => 'PLATE',
      'round' => 1,
      'match_nr' => 3003,
      'position' => 3,
      'registration1_id' => $s['C2'],
      'registration2_id' => $s['B3'],
    ]);

    $qf4 = Fixture::create([
      'draw_id' => $draw->id,
      'stage' => 'PLATE',
      'round' => 1,
      'match_nr' => 3004,
      'position' => 4,
      'registration1_id' => $s['D2'],
      'registration2_id' => $s['A3'],
    ]);


    // -------------------------------
    // SEMIFINALS (NOW CORRECTLY LINKED)
    // -------------------------------
    $sf1 = Fixture::create([
      'draw_id' => $draw->id,
      'stage' => 'PLATE',
      'round' => 2,
      'match_nr' => 3005,
      'position' => 5,
      'parent_fixture_id' => $qf1->id,
      'loser_parent_fixture_id' => $qf2->id,
    ]);

    $sf2 = Fixture::create([
      'draw_id' => $draw->id,
      'stage' => 'PLATE',
      'round' => 2,
      'match_nr' => 3006,
      'position' => 6,
      'parent_fixture_id' => $qf3->id,
      'loser_parent_fixture_id' => $qf4->id,
    ]);


    // -------------------------------
    // FINAL + 3rd/4th
    // -------------------------------
    $final = Fixture::create([
      'draw_id' => $draw->id,
      'stage' => 'PLATE',
      'round' => 3,
      'match_nr' => 3007,
      'position' => 7,
      'parent_fixture_id' => $sf1->id,
      'loser_parent_fixture_id' => $sf2->id,
    ]);

    $third = Fixture::create([
      'draw_id' => $draw->id,
      'stage' => 'PLATE',
      'round' => 3,
      'match_nr' => 3008,
      'position' => 8,
      'parent_fixture_id' => $sf2->id,
      'loser_parent_fixture_id' => $sf1->id,
    ]);


    Log::info("✅ [PlateBracket] Fixtures created", [
      'qf1' => $qf1->id,
      'qf2' => $qf2->id,
      'qf3' => $qf3->id,
      'qf4' => $qf4->id,
      'sf1' => $sf1->id,
      'sf2' => $sf2->id,
      'final' => $final->id,
      'third' => $third->id,
    ]);

    return compact('qf1', 'qf2', 'qf3', 'qf4', 'sf1', 'sf2', 'final', 'third');
  }

  // ============================================================
  // PRIVATE: RR FIXTURE GENERATOR (CIRCLE METHOD)
  // ============================================================
  private function generateRoundRobinFixtures(Draw $draw): void
  {
    Log::info("🧹 [RR] Cleaning old RR fixtures for draw {$draw->id}");

    $draw->drawFixtures()
      ->where('stage', 'RR')
      ->delete();

    Log::info("🎾 [RR] GENERATE FIXTURES — Draw {$draw->id}");

    $matchNr = 1;

    foreach ($draw->groups as $group) {
      Log::info("👉 [RR] Group {$group->id} — {$group->name}");

      $registrations = $group->groupRegistrations
        ->sortBy(fn($r) => $r->pivot->seed ?? $r->id)
        ->values();

      $debugList = $registrations->map(function ($r) {
        return [
          'id' => $r->id,
          'seed' => $r->pivot->seed ?? null,
          'name' => $r->display_name,
        ];
      });
      Log::info("   🧍 Players:", $debugList->toArray());

      $ids = $registrations->pluck('registration_id')->all();

      $n = count($ids);

      if ($n < 2) {
        Log::warning("   ⚠ Group has <2 players — SKIPPED");
        continue;
      }

      if ($n % 2 === 1) {
        $ids[] = 0;
        $n++;
        Log::info("   ➕ Added BYE (odd number of players)");
      }
      
      $rounds = $n - 1;
      $half = $n / 2;
      $players = $ids;

      Log::info("   🔄 Total rounds: {$rounds}");
      Log::info("   🔄 Players array for algorithm:", $players);

      for ($round = 1; $round <= $rounds; $round++) {
        Log::info("------");
        Log::info("   🎯 ROUND {$round}");

        for ($i = 0; $i < $half; $i++) {
          $home = $players[$i];
          $away = $players[$n - 1 - $i];

          if ($home === 0 || $away === 0) {
            Log::info("      ⏭ BYE skipped (home={$home}, away={$away})");
            continue;
          }

          Log::info("      ➤ Match {$matchNr}: {$home} vs {$away}");

          Fixture::create([
            'draw_id' => $draw->id,
            'draw_group_id' => $group->id,
            'match_nr' => $matchNr++,
            'round' => $round,
            'registration1_id' => $home,
            'registration2_id' => $away,
            'match_status' => 0,
            'scheduled' => 0,
            'stage' => 'RR',
          ]);
        }

        // rotate (circle)
        $first = array_shift($players);
        $last = array_pop($players);
        array_unshift($players, $first);
        array_splice($players, 1, 0, [$last]);

        Log::info("   ↪ Rotated players: ", $players);
      }
    }

    Log::info("🎉 [RR] Finished generating Round Robin for draw {$draw->id}");
    Log::info("==============================================================");
  }

  // ============================================================
// PRIVATE: FULL STANDINGS (MATCH → SET → H2H) FOR HUB
// ============================================================
  private function buildStandingsFromFixtures(Draw $draw): array
  {
    $standings = [];

    // ============================================================
    // 1) INIT STANDINGS PER GROUP
    // ============================================================
    foreach ($draw->groups as $group) {
      Log::info("📘 [RR HUB] Init standings for group {$group->name}");

      foreach ($group->groupRegistrations as $gr) {
        $reg = $gr->registration;

        if (!$reg)
          continue; // safety

        $standings[$group->id][$reg->id] = [
          'reg_id' => $reg->id,
          'player' => $reg->display_name,
          'box' => $group->name,
          'wins' => 0,
          'losses' => 0,
          'sets_won' => 0,
          'sets_lost' => 0,
        ];
      }

    }

    // ============================================================
    // 2) PROCESS ONLY RR FIXTURES
    // ============================================================
    foreach ($draw->drawFixtures as $fx) {

      // ❗ Skip ALL Main / Plate / Consolation fixtures
      if ($fx->stage !== 'RR') {
        continue;
      }

      // ❗ Round robin fixtures MUST have a group id
      if (empty($fx->draw_group_id)) {
        continue;
      }

      $box = $fx->draw_group_id;

      // Ensure indexes exist to avoid undefined key
      if (!isset($standings[$box])) {
        continue;
      }

      $last = $fx->fixtureResults->sortBy('set_nr')->last();
      if (!$last) {
        continue;
      }

      $winner = $last->winner_registration;
      $home = $fx->registration1_id;
      $away = $fx->registration2_id;

      // If for some reason RR fixtures reference a removed registration
      if (!isset($standings[$box][$home]) || !isset($standings[$box][$away])) {
        continue;
      }

      // Count sets
      $homeSets = 0;
      $awaySets = 0;

      foreach ($fx->fixtureResults as $set) {
        if ($set->registration1_score > $set->registration2_score) {
          $homeSets++;
        } else {
          $awaySets++;
        }
      }

      Log::debug("🏆 [RR HUB] Add to standings", [
        'fixture_id' => $fx->id,
        'home_sets' => $homeSets,
        'away_sets' => $awaySets,
        'winner' => $winner,
      ]);

      // Update sets
      $standings[$box][$home]['sets_won'] += $homeSets;
      $standings[$box][$home]['sets_lost'] += $awaySets;
      $standings[$box][$away]['sets_won'] += $awaySets;
      $standings[$box][$away]['sets_lost'] += $homeSets;

      // Update wins/losses
      if ($winner == $home) {
        $standings[$box][$home]['wins']++;
        $standings[$box][$away]['losses']++;
      } else {
        $standings[$box][$away]['wins']++;
        $standings[$box][$home]['losses']++;
      }
    }

    // ============================================================
    // 3) HEAD-TO-HEAD ONLY WITH RR MATCHES
    // ============================================================
    $headToHead = function ($regA, $regB) use ($draw) {

      foreach ($draw->drawFixtures as $fx) {

        // Only RR fixtures matter
        if ($fx->stage !== 'RR')
          continue;
        if (!$fx->draw_group_id)
          continue;

        if (
          ($fx->registration1_id == $regA && $fx->registration2_id == $regB) ||
          ($fx->registration1_id == $regB && $fx->registration2_id == $regA)
        ) {
          $last = $fx->fixtureResults->sortBy('set_nr')->last();
          if (!$last)
            return null;

          return $last->winner_registration;
        }
      }

      return null;
    };

    // ============================================================
    // 4) SORT EACH GROUP
    // ============================================================
    foreach ($standings as $gid => $rows) {

      Log::info("📊 [RR HUB] Standings before sort", $rows);

      usort($rows, function ($a, $b) use ($headToHead) {

        // wins first
        if ($a['wins'] !== $b['wins']) {
          return $b['wins'] <=> $a['wins'];
        }

        // sets difference next
        $aDiff = $a['sets_won'] - $a['sets_lost'];
        $bDiff = $b['sets_won'] - $b['sets_lost'];

        if ($aDiff !== $bDiff) {
          return $bDiff <=> $aDiff;
        }

        // head-to-head (if they played)
        $hh = $headToHead($a['reg_id'], $b['reg_id']);
        if ($hh) {
          return $hh == $a['reg_id'] ? -1 : 1;
        }

        return 0; // identical
      });

      Log::info("📈 [RR HUB] Standings AFTER sort", $rows);

      $standings[$gid] = $rows;
    }

    return $standings;
  }

  // ============================================================
  // PRIVATE: GAMES-BASED STANDINGS (USED BY AJAX SAVE)
  // ============================================================
  private function calculateStandings(int $groupId): array
  {
    $fixtures = Fixture::where('draw_group_id', $groupId)
      ->with(['fixtureResults', 'registration1', 'registration2'])
      ->get();

    $scores = [];

    foreach ($fixtures as $fx) {
      if (!isset($scores[$fx->registration1_id])) {
        $scores[$fx->registration1_id] = [
          'player' => $fx->registration1->display_name,
          'wins' => 0,
          'losses' => 0,
          'games_plus' => 0,
          'games_minus' => 0,
        ];
      }

      if (!isset($scores[$fx->registration2_id])) {
        $scores[$fx->registration2_id] = [
          'player' => $fx->registration2->display_name,
          'wins' => 0,
          'losses' => 0,
          'games_plus' => 0,
          'games_minus' => 0,
        ];
      }

      if ($fx->fixtureResults->count()) {
        foreach ($fx->fixtureResults as $set) {
          $scores[$fx->registration1_id]['games_plus'] += $set->registration1_score;
          $scores[$fx->registration1_id]['games_minus'] += $set->registration2_score;

          $scores[$fx->registration2_id]['games_plus'] += $set->registration2_score;
          $scores[$fx->registration2_id]['games_minus'] += $set->registration1_score;
        }

        // NOTE: this relies on fixture->winner_registration existing
        if ($fx->winner_registration == $fx->registration1_id) {
          $scores[$fx->registration1_id]['wins']++;
          $scores[$fx->registration2_id]['losses']++;
        } elseif ($fx->winner_registration == $fx->registration2_id) {
          $scores[$fx->registration2_id]['wins']++;
          $scores[$fx->registration1_id]['losses']++;
        }
      }
    }

    return $scores;
  }
  public function getMainAndPlateFixtures(Draw $draw): array
  {
    // MAIN FIXTURES
    $fixturesMain = Fixture::where('draw_id', $draw->id)
      ->where('stage', 'MAIN')
      ->orderBy('match_nr')
      ->get();

    $sf_main = $fixturesMain->where('round', 1)->sortBy('match_nr')->values();
    $final_main = $fixturesMain->firstWhere('position', 3);
    $third_main = $fixturesMain->firstWhere('position', 4);

    // PLATE FIXTURES (2nd/3rd)
    $fixturesPlate = Fixture::where('draw_id', $draw->id)
      ->where('stage', 'PLATE')
      ->orderBy('match_nr')
      ->get();

    $qf_plate = $fixturesPlate->where('round', 1)->sortBy('match_nr')->values();
    $sf_plate = $fixturesPlate->where('round', 2)->sortBy('match_nr')->values();
    $final_plate = $fixturesPlate->firstWhere('position', 7);
    $third_plate = $fixturesPlate->firstWhere('position', 8);

    return [
      'sf_main' => $sf_main,
      'final_main' => $final_main,
      'third_main' => $third_main,

      'qf_plate' => $qf_plate,
      'sf_plate' => $sf_plate,
      'final_plate' => $final_plate,
      'third_plate' => $third_plate,
    ];
  }
  public function renderMainBracket(Draw $draw): array
  {
    // Fixtures
    $sf1 = $draw->drawFixtures->where('stage', 'MAIN')->where('round', 2)->first();
    $sf2 = $draw->drawFixtures->where('stage', 'MAIN')->where('round', 2)->skip(1)->first();
    $final = $draw->drawFixtures->where('stage', 'MAIN')->where('round', 3)->first();

    // Helper closures
    $name = function ($fx, $slot) {
      if (!$fx)
        return '---';
      $reg = $slot === 1 ? $fx->registration1 : $fx->registration2;
      if (!$reg)
        return '---';
      return $reg->players->pluck('full_name')->join(' / ');
    };

    $score = function ($fx) {
      if (!$fx || !$fx->fixtureResults->count())
        return '';
      return $fx->fixtureResults->pluck('score_line')->join(', ');
    };

    return [
      'sf1' => [
        'p1' => $name($sf1, 1),
        'p2' => $name($sf1, 2),
        'score' => $score($sf1),
      ],
      'sf2' => [
        'p1' => $name($sf2, 1),
        'p2' => $name($sf2, 2),
        'score' => $score($sf2),
      ],
      'final' => [
        'p1' => $name($final, 1),
        'p2' => $name($final, 2),
        'score' => $score($final),
        'winner' => $final?->winner?->players?->pluck('full_name')->join(' / ') ?? '',
      ],
    ];
  }
  public function generateFullBracketFixtures(Draw $draw, $seeds)
  {
    \Log::info("🧬 [FullBracket] START with incoming seeds", $seeds);

    $result = [];

    DB::transaction(function () use ($draw, $seeds, &$result) {

      // ============================================================
      // CLEAR OLD MAIN / PLATE / CONS
      // ============================================================
      Fixture::where('draw_id', $draw->id)
        ->whereIn('stage', ['MAIN', 'PLATE', 'CONS'])
        ->delete();


      // ============================================================
      // =========================== MAIN ============================
      // ============================================================
      $sf1 = $this->make($draw, 'MAIN', 1, 2001);
      $sf2 = $this->make($draw, 'MAIN', 1, 2002);
      $final = $this->make($draw, 'MAIN', 2, 2003);

      // Link MAIN → Final
      $sf1->parent_fixture_id = $final->id;
      $sf2->parent_fixture_id = $final->id;
      $sf1->save();
      $sf2->save();

      // Seed MAIN
      if (isset($seeds['A1'], $seeds['B1'], $seeds['C1'], $seeds['D1'])) {
        $sf1->registration1_id = $seeds['A1'];
        $sf1->registration2_id = $seeds['D1'];
        $sf1->save();

        $sf2->registration1_id = $seeds['B1'];
        $sf2->registration2_id = $seeds['C1'];
        $sf2->save();
      }


      $result['main_sf'] = [$sf1, $sf2];
      $result['sf1'] = $sf1;
      $result['sf2'] = $sf2;
      $result['final'] = $final;



      // ============================================================
      // ===================== PLATE ROUND 1 (QF) ====================
      // ============================================================
      $qf1 = $this->make($draw, 'PLATE', 1, 3001);
      $qf2 = $this->make($draw, 'PLATE', 1, 3002);
      $qf3 = $this->make($draw, 'PLATE', 1, 3003);
      $qf4 = $this->make($draw, 'PLATE', 1, 3004);


      // Seed PLATE QF
      if (
        isset(
        $seeds['A2'],
        $seeds['A3'],
        $seeds['B2'],
        $seeds['B3'],
        $seeds['C2'],
        $seeds['C3'],
        $seeds['D2'],
        $seeds['D3']
      )
      ) {

        $qf1->registration1_id = $seeds['A2'];
        $qf1->registration2_id = $seeds['D3'];

        $qf2->registration1_id = $seeds['B2'];
        $qf2->registration2_id = $seeds['C3'];

        $qf3->registration1_id = $seeds['C2'];
        $qf3->registration2_id = $seeds['B3'];

        $qf4->registration1_id = $seeds['D2'];
        $qf4->registration2_id = $seeds['A3'];

        $qf1->save();
        $qf2->save();
        $qf3->save();
        $qf4->save();
      }



      // ============================================================
      // ===================== CONSOLATION BRACKET ==================
      // ============================================================
      $c1 = $this->make($draw, 'CONS', 1, 4001);
      $c2 = $this->make($draw, 'CONS', 1, 4002);

      $cfinal = $this->make($draw, 'CONS', 2, 4003);
      $c34 = $this->make($draw, 'CONS', 2, 4004);

      // Winners → cons final
      $c1->parent_fixture_id = $cfinal->id;
      $c2->parent_fixture_id = $cfinal->id;

      // Losers → cons 3/4
      $c1->loser_parent_fixture_id = $c34->id;
      $c2->loser_parent_fixture_id = $c34->id;

      $c1->save();
      $c2->save();

      // QF losers to CONS
      $qf1->loser_parent_fixture_id = $c1->id;
      $qf2->loser_parent_fixture_id = $c1->id;
      $qf3->loser_parent_fixture_id = $c2->id;
      $qf4->loser_parent_fixture_id = $c2->id;

      $qf1->save();
      $qf2->save();
      $qf3->save();
      $qf4->save();



      // ============================================================
      // ===================== PLATE ROUND 2 (SF) ====================
      // ============================================================
      $sf_p1 = $this->make($draw, 'PLATE', 2, 3005);
      $sf_p2 = $this->make($draw, 'PLATE', 2, 3006);

      // QF winners → SF
      $qf1->parent_fixture_id = $sf_p1->id;
      $qf2->parent_fixture_id = $sf_p1->id;

      $qf3->parent_fixture_id = $sf_p2->id;
      $qf4->parent_fixture_id = $sf_p2->id;

      $qf1->save();
      $qf2->save();
      $qf3->save();
      $qf4->save();



      // ============================================================
      // ===================== PLATE ROUND 3 (FEED) =================
      // ============================================================
      $p7 = $this->make($draw, 'PLATE', 3, 3007); // TOP
      $p8 = $this->make($draw, 'PLATE', 3, 3008); // BOTTOM

      // MAIN SF LOSERS → FEED MATCHES (CORRECT)
      $sf1->loser_parent_fixture_id = $p8->id; // SF1 loser → 3008
      $sf2->loser_parent_fixture_id = $p7->id; // SF2 loser → 3007

      $sf1->save();
      $sf2->save();

      // PLATE SF WINNERS → FEED MATCHES (CORRECT)
      $sf_p1->parent_fixture_id = $p7->id; // 3005 winner → 3007
      $sf_p2->parent_fixture_id = $p8->id; // 3006 winner → 3008

      $sf_p1->save();
      $sf_p2->save();



      // ============================================================
      // ===================== PLATE ROUND 4 (FINAL) ================
      // ============================================================
      $p9 = $this->make($draw, 'PLATE', 4, 3009);

      // winners of feed matches → final
      $p7->parent_fixture_id = $p9->id;
      $p8->parent_fixture_id = $p9->id;
      $p7->save();
      $p8->save();



      // ============================================================
      // ===================== PLATE ROUND 4 (3/4) ==================
      // ============================================================
      $p10 = $this->make($draw, 'PLATE', 4, 3010);

      $sf_p1->loser_parent_fixture_id = $p10->id;
      $sf_p2->loser_parent_fixture_id = $p10->id;

      $sf_p1->save();
      $sf_p2->save();



      // ============================================================
      // ===================== PLATE ROUND 4 (7/8) ==================
      // ============================================================
      // ------------------ PLATE ROUND 4 (7/8) -------------------
      $p11 = $this->make($draw, 'PLATE', 4, 3011);

      // Losers of feed matches → 7/8 playoff
// Losers of feed matches → 3rd/4th playoff (correct)
      $p7->loser_parent_fixture_id = $p10->id;   // 3007 loser → 3010
      $p8->loser_parent_fixture_id = $p10->id;   // 3008 loser → 3010

      $p7->save();
      $p8->save();



      $result['plate'] = [
        'qf1' => $qf1,
        'qf2' => $qf2,
        'qf3' => $qf3,
        'qf4' => $qf4,
        'sf1' => $sf_p1,
        'sf2' => $sf_p2,
        'feed1' => $p7,
        'feed2' => $p8,
        'final' => $p9,
        'third' => $p10,
        'seventh' => $p11,
      ];
    });

    return $result;
  }

  private function make(
    Draw $draw,
    string $stage,
    int $round,
    int $match_nr,
    $parent = null,
    $losersTo = null
  ) {
    return Fixture::create([
      'draw_id' => $draw->id,
      'stage' => $stage,
      'round' => $round,
      'match_nr' => $match_nr,
      'parent_fixture_id' => $parent,
      'loser_parent_fixture_id' => $losersTo,
    ]);
  }

  public function regenerateRoundRobinFixtures(Draw $draw): void
  {
    $this->generateRoundRobinFixtures($draw);
  }

  public function saveBracketScore(Fixture $fixture, array $sets): array
  {
    Log::info("🎾 [BracketScore] Saving score for fixture {$fixture->id}", [
      'fixture_id' => $fixture->id,
      'stage' => $fixture->stage,
      'r1' => $fixture->registration1_id,
      'r2' => $fixture->registration2_id,
      'incoming_sets' => $sets
    ]);

    DB::transaction(function () use ($fixture, $sets) {

      // DELETE OLD RESULTS
      Log::info("🧹 [BracketScore] Clearing old fixtureResults for fixture {$fixture->id}");
      $fixture->fixtureResults()->delete();

      $wins1 = 0;
      $wins2 = 0;

      foreach ($sets as $i => [$s1, $s2]) {

        $winner = $s1 > $s2
          ? $fixture->registration1_id
          : $fixture->registration2_id;

        $loser = $s1 > $s2
          ? $fixture->registration2_id
          : $fixture->registration1_id;

        Log::info("➕ [BracketScore] Add Set", [
          'fixture_id' => $fixture->id,
          'set_nr' => $i + 1,
          'p1_score' => $s1,
          'p2_score' => $s2,
          'winner_of_set' => $winner,
          'loser_of_set' => $loser,
        ]);

        $fixture->fixtureResults()->create([
          'set_nr' => $i + 1,
          'registration1_score' => $s1,
          'registration2_score' => $s2,
          'winner_registration' => $winner,
          'loser_registration' => $loser,
        ]);

        if ($s1 > $s2)
          $wins1++;
        if ($s2 > $s1)
          $wins2++;
      }

      // DETERMINE MATCH WINNER
      $winner = $wins1 > $wins2
        ? $fixture->registration1_id
        : $fixture->registration2_id;

      $loser = ($winner === $fixture->registration1_id)
        ? $fixture->registration2_id
        : $fixture->registration1_id;

      Log::info("🏆 [BracketScore] Fixture Winner Calculated", [
        'fixture_id' => $fixture->id,
        'winner' => $winner,
        'loser' => $loser,
      ]);

      $fixture->winner_registration = $winner;
      $fixture->match_status = 1;
      $fixture->save();

      // AUTO ADVANCE
      $this->autoAdvanceBracket($fixture, $winner, $loser);
    });
    // Rebuild updated OOP
    $draw = $fixture->draw()->with([
      'drawFixtures.registration1.players',
      'drawFixtures.registration2.players',
      'drawFixtures.fixtureResults',
    ])->first();

    $oop = $draw->drawFixtures
      ->sortBy(fn($fx) => sprintf(
        "%02d_%02d_%04d",
        $fx->stage === 'RR' ? 0 : ($fx->stage === 'MAIN' ? 1 : ($fx->stage === 'PLATE' ? 2 : 3)),
        $fx->round,
        $fx->match_nr
      ))
      ->map(function ($fx) {

        $sets = $fx->fixtureResults
          ->sortBy('set_nr')
          ->map(fn($r) => "{$r->registration1_score}-{$r->registration2_score}")
          ->implode(', ');

        return [
          'id' => $fx->id,
          'stage' => $fx->stage,
          'round' => $fx->round,
          'match_nr' => $fx->match_nr,

          'home' => $fx->registration1?->display_name ?? '',
          'away' => $fx->registration2?->display_name ?? '',
          'r1_id' => $fx->registration1_id,
          'r2_id' => $fx->registration2_id,

          'time' => $fx->start_time ?? '',
          'score' => $sets,
          'winner' => optional($fx->fixtureResults->sortBy('set_nr')->last())->winner_registration,
        ];
      })
      ->values();


    return [
      'success' => true,
      'mode' => 'BRACKET',    // ⭐ REQUIRED
      'fixture_id' => $fixture->id,
      'oop' => $oop,
    ];


  }




  private function autoAdvanceBracket(Fixture $fixture, int $winner, int $loser): void
  {
    Log::info("➡ [Advance] Starting auto-advance for fixture {$fixture->id}", [
      'winner' => $winner,
      'loser' => $loser,
      'parent_fixture_id' => $fixture->parent_fixture_id,
      'loser_parent_fixture_id' => $fixture->loser_parent_fixture_id,
    ]);

    if ($fixture->fixtureResults->count() == 0) {
      Log::warning("❗ [Advance] Fixture {$fixture->id} has no results — SKIPPING loser feed");
      return;
    }


    // ============================================================
    // WINNER → PARENT FIXTURE
    // ============================================================
    if ($fixture->parent_fixture_id) {

      $next = Fixture::find($fixture->parent_fixture_id);

      if (!$next) {
        Log::warning("❗ [Advance] Parent fixture {$fixture->parent_fixture_id} NOT FOUND");
      } else {

        Log::info("➡ [Advance:W] Insert winner into parent fixture {$next->id}", [
          'r1' => $next->registration1_id,
          'r2' => $next->registration2_id,
        ]);


        // ============================================================
        // SPECIAL RULE: Plate feed matches (3007, 3008)
        // Winner must always be put in BOTTOM slot (registration2)
        // ============================================================
        if (in_array($next->match_nr, [3007, 3008])) {

          // Check origin fixture (3003 or 3004)
          if ($fixture->match_nr == 3003) {
            // 3003 winner → BOTTOM (registration2)
            if (!$next->registration2_id) {
              $next->registration2_id = $winner;
            } else {
              $next->registration1_id = $winner; // fallback
            }

          } elseif ($fixture->match_nr == 3004) {
            // 3004 winner → TOP (registration1)
            if (!$next->registration1_id) {
              $next->registration1_id = $winner;
            } else {
              $next->registration2_id = $winner; // fallback
            }

          } else {
            // all other feed matches keep existing behaviour
            if (!$next->registration2_id) {
              $next->registration2_id = $winner;
            } elseif (!$next->registration1_id) {
              $next->registration1_id = $winner;
            }
          }

          $next->save();
          
        } else {
          // Normal behavior for all other matches
          if (!$next->registration1_id) {
            $next->registration1_id = $winner;
            Log::info("   ✔ Winner placed into registration1");
          } elseif (!$next->registration2_id) {
            $next->registration2_id = $winner;
            Log::info("   ✔ Winner placed into registration2");
          } else {
            Log::warning("   ❗ Both slots FULL in parent fixture {$next->id}");
          }
        }


        $next->save();
      }
    }



    // ============================================================
    // LOSER → LOSER BRACKET (unchanged)
    // ============================================================
    if ($fixture->loser_parent_fixture_id) {

      $next = Fixture::find($fixture->loser_parent_fixture_id);

      if (!$next) {
        Log::warning("❗ [Advance] Loser-parent fixture {$fixture->loser_parent_fixture_id} NOT FOUND");
      } else {

        Log::info("➡ [Advance:L] Insert loser into loser fixture {$next->id}", [
          'r1' => $next->registration1_id,
          'r2' => $next->registration2_id,
        ]);

        if (!$next->registration1_id) {
          $next->registration1_id = $loser;
          Log::info("   ✔ Loser placed into registration1");
        } elseif (!$next->registration2_id) {
          $next->registration2_id = $loser;
          Log::info("   ✔ Loser placed into registration2");
        } else {
          Log::warning("   ❗ Both slots FULL in loser fixture {$next->id}");
        }

        $next->save();
      }
    }

    Log::info("✅ [Advance] Completed auto-advance for fixture {$fixture->id}");
  }

  private function saveScoreRoundRobin(Fixture $fixture, array $sets): array
  {
    Log::info("🎾 [RR Save] Saving RR score for fixture {$fixture->id}", [
      'fixture_id' => $fixture->id,
      'sets' => $sets
    ]);

    DB::transaction(function () use ($fixture, $sets) {

      // CLEAR OLD RESULTS
      $fixture->fixtureResults()->delete();
      $wins1 = 0;
      $wins2 = 0;

      foreach ($sets as $i => [$s1, $s2]) {

        $winner = $s1 > $s2
          ? $fixture->registration1_id
          : $fixture->registration2_id;

        $loser = ($winner === $fixture->registration1_id)
          ? $fixture->registration2_id
          : $fixture->registration1_id;

        $fixture->fixtureResults()->create([
          'set_nr' => $i + 1,
          'registration1_score' => $s1,
          'registration2_score' => $s2,
          'winner_registration' => $winner,
          'loser_registration' => $loser,
        ]);

        if ($s1 > $s2)
          $wins1++;
        if ($s2 > $s1)
          $wins2++;
      }

      // SAVE MATCH WINNER
      $fixture->winner_registration = $wins1 > $wins2
        ? $fixture->registration1_id
        : $fixture->registration2_id;

      $fixture->match_status = 1;
      $fixture->save();
    });

    // === REFRESH RR HUB DATA ===
    $draw = $fixture->draw()
      ->with([
        'groups.registrations.players',
        'drawFixtures.registration1.players',
        'drawFixtures.registration2.players',
        'drawFixtures.fixtureResults',
        'drawFixtures.drawGroup',
      ])
      ->first();


    // === USE EXISTING HUB BUILDERS ===
    $rrFixtures = $this->loadRoundRobinHub($draw)['rrFixtures'];
    $standings = $this->loadRoundRobinHub($draw)['standings'];
    $oops = $this->loadRoundRobinHub($draw)['oops'];


    // Build updated fixture object (JS expects this)
    $fixture->load([
      'registration1.players',
      'registration2.players',
      'fixtureResults',
    ]);

    $allSets = $fixture->fixtureResults
      ->sortBy('set_nr')
      ->map(fn($r) => "{$r->registration1_score}-{$r->registration2_score}")
      ->toArray();

    $scoreString = implode(', ', $allSets);

    $updatedFixture = [
      'id' => $fixture->id,
      'draw_group_id' => $fixture->draw_group_id,
      'r1_id' => $fixture->registration1_id,
      'r2_id' => $fixture->registration2_id,

      'home' => $fixture->registration1?->display_name ?? '',
      'away' => $fixture->registration2?->display_name ?? '',

      'score' => $scoreString,
      'all_sets' => $allSets,
      'winner_registration' => $fixture->winner_registration,

      'home_score' => $fixture->fixtureResults->last()->registration1_score ?? null,
      'away_score' => $fixture->fixtureResults->last()->registration2_score ?? null,
    ];

    return [
      'success' => true,
      'mode' => 'RR',

      // REQUIRED by JS
      'fixture' => $updatedFixture,

      // REQUIRED by JS
      'standings' => $standings,
      'oop' => $oops,

      // OPTIONAL but useful
      'rrFixtures' => $rrFixtures,
    ];

  }



  public function saveAnyScore(Fixture $fixture, array $sets): array
  {
    if ($fixture->stage === 'RR') {
      // Round Robin logic
      return $this->saveScore($fixture, $sets);
    }

    // Bracket match logic
    return $this->saveBracketScore($fixture, $sets);
  }
  private function deepLogDrawStructure(Draw $draw, string $context = 'INIT'): void
  {
    Log::info("🔎 [DEEP-LOG:$context] Draw Structure Start", [
      'draw_id' => $draw->id,
      'groups' => $draw->groups->count(),
      'fixtures' => $draw->drawFixtures->count(),
    ]);

    // -------------------------------------------------------------
    // GROUPS
    // -------------------------------------------------------------
    foreach ($draw->groups as $g) {
      Log::info("🔵 GROUP {$g->name} (#{$g->id})", [
        'registrations_count' => $g->groupRegistrations->count()
      ]);

      foreach ($g->groupRegistrations as $gr) {
        $reg = $gr->registration;
        $playerNames = $reg?->players?->pluck('full_name')->join(', ') ?? 'NONE';

        Log::info("   ↳ GR #{$gr->id}", [
          'registration_id' => $gr->registration_id,
          'seed' => $gr->seed,
          'reg_found' => (bool) $reg,
          'players' => $playerNames
        ]);
      }
    }

    // -------------------------------------------------------------
    // FIXTURES
    // -------------------------------------------------------------
    foreach ($draw->drawFixtures as $fx) {
      Log::info("🟣 FIXTURE {$fx->id}", [
        'stage' => $fx->stage,
        'round' => $fx->round,
        'match_nr' => $fx->match_nr,
        'group_id' => $fx->draw_group_id,
        'reg1_id' => $fx->registration1_id,
        'reg2_id' => $fx->registration2_id,
      ]);

      // REGISTRATION 1
      $r1 = $fx->registration1;
      $r1Names = $r1?->players?->pluck('full_name')->join(', ') ?? 'NONE';

      Log::info("     • R1", [
        'exists' => (bool) $r1,
        'players' => $r1Names
      ]);

      // REGISTRATION 2
      $r2 = $fx->registration2;
      $r2Names = $r2?->players?->pluck('full_name')->join(', ') ?? 'NONE';

      Log::info("     • R2", [
        'exists' => (bool) $r2,
        'players' => $r2Names
      ]);

      // RESULTS
      foreach ($fx->fixtureResults as $res) {
        Log::info("     ✔ Result Set {$res->set_nr}", [
          'p1' => $res->registration1_score,
          'p2' => $res->registration2_score,
          'winner' => $res->winner_registration,
        ]);
      }
    }

    Log::info("🔎 [DEEP-LOG:$context] Draw Structure END");
  }

}
