<?php

namespace App\Services;

use App\Models\Draw;
use App\Models\Fixture;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BracketEngine
{
  protected Draw $draw;
  protected Collection $fixtures;

  public function __construct(Draw $draw)
  {
    $this->draw = $draw;

    Log::info("🎾 BracketEngine: Loading fixtures for draw {$draw->id}");

    $this->fixtures = Fixture::where('draw_id', $draw->id)
      ->with([
        'schedule',            // schedule row
        'schedule.venue',      // venue name
      ])
      ->orderBy('stage')
      ->orderBy('round')
      ->orderBy('match_nr')
      ->get();

    Log::info("📦 Fixtures loaded", [
      'count' => $this->fixtures->count(),
    ]);
  }

  // ============================================================
  // PUBLIC API
  // ============================================================
  public function build(): array
  {
    Log::info("🏗 Building MAIN stage");
    $main = $this->buildStage('MAIN');

    Log::info("🏗 Building PLATE stage");
    $plate = $this->buildStage('PLATE');

    Log::info("🏗 Building CONS stage");
    $cons = $this->buildStage('CONS');

    Log::info("🎉 BracketEngine build complete");

    return [
      'main' => $main,
      'plate' => $plate,
      'consolation' => $cons,
      'feedins' => $this->getFeedins(),
    ];
  }

  // ============================================================
  // BUILD STAGE
  // ============================================================
  protected function buildStage(string $stage): array
  {
    Log::info("▶️ Building stage: $stage");

    $stageFixtures = $this->fixtures->where('stage', $stage);

    if ($stageFixtures->isEmpty()) {
      Log::info("❌ Stage $stage has no fixtures");
      return [];
    }

    $grouped = $stageFixtures->groupBy('round');

    Log::info("🔢 Stage $stage rounds:", [
      'rounds' => $grouped->keys()->all(),
    ]);

    $rounds = [];

    foreach ($grouped as $roundNumber => $matches) {

      $matches = $matches->sortBy('match_nr')->values();

      Log::info("  ➤ Preparing round $roundNumber", [
        'match_ids' => $matches->pluck('id')->all(),
      ]);

      $rounds[$roundNumber] = $matches->map(function ($fx, $i) {
        return [
          'id' => $fx->id,
          'match_nr' => $fx->match_nr,
          'round' => $fx->round,
          'position' => $fx->position,
          'fx' => $fx,
          'i' => $i,
        ];
      })->all();
    }

    return $this->assignCoordinates($rounds);
  }

  // ============================================================
  // COORDINATE ENGINE — WITH PLATE SPECIAL LOGIC
  // ============================================================
  protected function assignCoordinates(array $stage): array
  {
    Log::info("📐 Assigning coordinates...");

    $startX = 120;
    $boxWidth = 150;
    $topOffset = 80;
    $defaultHeight = 40;

    $output = [];

    // ============================================================
    // DETERMINE STAGE TYPE
    // ============================================================
    $first = reset($stage);
    $firstFx = $first[0]['fx'] ?? null;

    $stageName = 'MAIN';
    if ($firstFx) {
      $nr = $firstFx->match_nr;
      if ($nr >= 3000 && $nr < 4000)
        $stageName = 'PLATE';
      elseif ($nr >= 4000 && $nr < 5000)
        $stageName = 'CONS';
    }

    $isPlate = ($stageName === 'PLATE');
    $isCons = ($stageName === 'CONS');

    Log::info("➡ Stage detected as: $stageName");

    // ============================================================
    // PASS 1 — DEFAULT LAYOUT FOR ALL STAGES
    // ============================================================
    foreach ($stage as $round => $matches) {

      $roundIndex = $round - 1;
      $roundX = $startX + ($roundIndex * $boxWidth);
      $matchSpace = 80 * pow(2, $roundIndex);

      Log::info("  ➤ Round $round default placement", [
        'roundX' => $roundX,
      ]);

      foreach ($matches as $i => $m) {

        $y = $topOffset + ($i * $matchSpace);

        Log::info("     • Default R{$round}-M{$i}", [
          'id' => $m['id'],
          'match_nr' => $m['match_nr'],
          'x' => $roundX,
          'y' => $y,
        ]);

        $output[$round][$i] = [
          'fx' => $m['fx'],
          'x' => $roundX,
          'y' => $y,
          'round' => $round,
          'i' => $i,
          'height' => $defaultHeight,
        ];
      }
    }
    // ============================================================
// PLATE: Force correct top/bottom order for feed-in round (3007,3008)
// ============================================================
    if ($isPlate) {
      foreach ($stage as $round => &$matches) {
        // Plate Round 3 is always 2 matches: 3007 + 3008
        if (count($matches) === 2) {
          $a = $matches[0]['fx']->match_nr;
          $b = $matches[1]['fx']->match_nr;

          // Ensure 3007 is index 0 (TOP)
          // And 3008 is index 1 (BOTTOM)
          if ($a > $b) {
            $matches = array_reverse($matches);
          }
        }
      }
    }

    // ============================================================
    // PASS 2 — STRETCH / FEED-IN LOGIC
    // ONLY APPLIES TO MAIN + PLATE (NEVER CONS)
    // ============================================================
    foreach ($output as $round => $matches) {

      $prev = $round - 1;
      if (!isset($output[$prev]))
        continue;

      $prevCount = count($output[$prev]);
      $thisCount = count($matches);

      // ------------------------------------------------------------
      // MAIN — Single final (SF → Final)
      // ------------------------------------------------------------
      if ($stageName === 'MAIN' && $thisCount === 1 && $prevCount === 2) {

        Log::info("     • Stretching 1-match MAIN FINAL");

        $sf1center = $output[$prev][0]['y'] + ($output[$prev][0]['height'] / 2);
        $sf2center = $output[$prev][1]['y'] + ($output[$prev][1]['height'] / 2);

        $output[$round][0]['y'] = $sf1center;
        $output[$round][0]['height'] = $sf2center - $sf1center;

        continue;
      }

      // ------------------------------------------------------------
      // PLATE — Half-size rounds (4→2, 8→4)
      // ------------------------------------------------------------
      if ($isPlate && $prevCount === $thisCount * 2) {

        Log::info("     • Stretching PLATE half-size round");

        foreach ($output[$round] as $i => &$row) {

          $p1 = $output[$prev][$i * 2];
          $p2 = $output[$prev][$i * 2 + 1];

          $c1 = $p1['y'] + ($p1['height'] / 2);
          $c2 = $p2['y'] + ($p2['height'] / 2);

          $row['y'] = $c1;
          $row['height'] = $c2 - $c1;
        }

        continue;
      }

      // ------------------------------------------------------------
      // PLATE — Feed-in midpoint (R3: 3007 + 3008)
      // ------------------------------------------------------------
      if ($isPlate && $prevCount === 2 && $thisCount === 2) {

        Log::info("     • PLATE feed-in midpoint alignment");

        $pTop = $output[$prev][0];
        $pBottom = $output[$prev][1];

        $midpoint = ($pTop['y'] + $pBottom['y'] + $pBottom['height']) / 2;

        $h = $pTop['height'];

        $output[$round][0]['height'] = $h;
        $output[$round][0]['y'] = $midpoint - ($h * 2);

        $output[$round][1]['height'] = $h;
        $output[$round][1]['y'] = $midpoint;

        continue;
      }

      // ------------------------------------------------------------
      // PLATE FINAL (1 match stretching)
      // ------------------------------------------------------------
      if ($isPlate && $prevCount === 2 && isset($output[$round][0])) {

        Log::info("     • Stretching PLATE FINAL");

        $p_top = $output[$prev][0];
        $p_bottom = $output[$prev][1];

        $topCenter = $p_top['y'] + ($p_top['height'] / 2);
        $bottomCenter = $p_bottom['y'] + ($p_bottom['height'] / 2);

        $output[$round][0]['y'] = $topCenter;
        $output[$round][0]['height'] = $bottomCenter - $topCenter;

        continue;
      }
    }


    // ============================================================
// PASS 3 — PLATE Special: Move 3010 / 3011 lower + shift 3010 left
// ============================================================

    if ($isPlate && isset($output[1]) && isset($output[4])) {

      $plateR1_last = end($output[1]);
      $plateBottom = $plateR1_last['y'] + $plateR1_last['height'];

      $baseY = $plateBottom + 40;

      // 3010 — 3rd/4th playoff (index 1)
      if (isset($output[4][1])) {
        $output[4][1]['y'] = $baseY;

        // ⭐ Shift match 3010 LEFT by 300px
        $output[4][1]['x'] -= 400;
        $output[4][1]['y'] += 80;
      }

      // 3011 — 7th/8th playoff (index 2)
      if (isset($output[4][2])) {
        $output[4][2]['y'] = $baseY + 80;
        // 3011 stays where it is horizontally
      }
    }



    // ============================================================
// PASS 4 — CONSOLATION SPECIAL: 4003 = double height, centered
// ============================================================
    if ($isCons && isset($output[1]) && isset($output[2]) && isset($output[2][0])) {

      Log::info("⭐ CONS: proper stretch for 4003");

      // Top and bottom R1 matches
      $top = $output[1][0];
      $bottom = $output[1][1];

      // Their Y positions
      $topY = $top['y'];
      $bottomY = $bottom['y'] + $bottom['height'];

      // First-round match height (normally 40, but use actual)
      $h1 = $top['height'];

      // Desired height = 2 × first-round height
      $newHeight = $h1 * 2;   // e.g. 40×2 = 80

      // Midpoint between R1 brackets
      $center = ($topY + $bottomY) / 2;

      // Position 4003 so it's centered
      $newY = $center - ($newHeight / 2);

      // Apply
      $output[2][0]['height'] = $newHeight;
      $output[2][0]['y'] = $newY;

      Log::info("⭐ CONS 4003 final", [
        'newY' => $output[2][0]['y'],
        'newHeight' => $output[2][0]['height']
      ]);
    }
    // Move CONS match 4004 down by 100px
    if ($isCons && isset($output[2][1])) {

      Log::info("⭐ CONS: moving 4004 down by 100px", [
        'oldY' => $output[2][1]['y'],
        'newY' => $output[2][1]['y'] + 100
      ]);

      $output[2][1]['y'] += 100;
    }



    return $output;
  }

  // ============================================================
  // FEED INS
  // ============================================================
  protected function getFeedins(): array
  {
    return [];
  }
}
