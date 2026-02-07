<?php

namespace App\Services;

use App\Models\Draw;
use App\Models\Fixture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DrawBuilder
{
  protected Draw $draw;
  protected $boxes;
  protected $fixtureMap;
  protected array $finalPositions = [];
  protected array $codeToReg = [];

  public function __construct(Draw $draw)
  {
    $this->draw = $draw;

    $this->boxes = $draw->registrations
      ->filter(fn($r) => $r->pivot->box_number)
      ->groupBy(fn($r) => $r->pivot->box_number);

    $this->fixtureMap = $draw->drawFixtures->groupBy('bracket_id');
  }

  public function rankPlayers(): static
  {
    foreach ($this->boxes as $boxNumber => $registrations) {
      $boxFixtures = $this->fixtureMap[$boxNumber] ?? collect();
      $stats = [];

      foreach ($registrations as $reg) {
        $rid = $reg->id;
        $wins = 0;
        $games = 0;

        foreach ($boxFixtures as $f) {
          foreach ($f->fixtureResults as $r) {
            if ($r->winner_registration === $rid) $wins++;
            if ($f->registration1_id === $rid) $games += $r->registration1_score;
            elseif ($f->registration2_id === $rid) $games += $r->registration2_score;
          }
        }

        $stats[$rid] = ['wins' => $wins, 'games' => $games];
      }

      $rankings = collect($stats)
        ->map(fn($s, $rid) => ['rid' => $rid] + $s)
        ->sort(function ($a, $b) use ($boxFixtures) {
          if ($a['wins'] !== $b['wins']) return $b['wins'] <=> $a['wins'];
          if ($a['games'] !== $b['games']) return $b['games'] <=> $a['games'];
          $fixture = $boxFixtures->first(
            fn($f) => ($f->registration1_id === $a['rid'] && $f->registration2_id === $b['rid']) ||
              ($f->registration1_id === $b['rid'] && $f->registration2_id === $a['rid'])
          );
          return $fixture && $fixture->fixtureResults->first()?->winner_registration === $a['rid'] ? -1 : 1;
        })
        ->values()
        ->pluck('rid')
        ->flip();

      foreach ($registrations as $reg) {
        $position = $rankings[$reg->id] ?? null;
        $this->finalPositions[$reg->id] = $position !== null ? $position + 1 : null;
      }
    }

    return $this;
  }

public function assignSeedingCodes(): static
{
    $boxLetters = range('A', 'Z');

    // Step 1: Get top-ranked player per box (skip boxes with unranked players)
    $boxRankings = collect($this->boxes)->mapWithKeys(function ($registrations, $boxNum) {
        $valid = collect($registrations)->filter(fn($r) => isset($this->finalPositions[$r->id]));
        if ($valid->isEmpty()) return []; // ⚠️ Skip this box

        $topPlayer = $valid->sortBy(fn($r) => $this->finalPositions[$r->id])->first();
        return [$boxNum => $this->finalPositions[$topPlayer->id]];
    });

    // Step 2: Sort box numbers by top player rank (1 = best)
    $sortedBoxNumbers = $boxRankings->sort()->keys()->values();

    // Step 3: Assign seeding codes using sorted box order
    foreach ($sortedBoxNumbers as $boxIndex => $originalBoxNum) {
        $registrations = $this->boxes[$originalBoxNum];

        $sorted = collect($registrations)
            ->filter(fn($r) => isset($this->finalPositions[$r->id]))
            ->sortBy(fn($r) => $this->finalPositions[$r->id])
            ->values();

        foreach ($sorted as $i => $reg) {
            $code = $boxLetters[$boxIndex] . ($i + 1);
            $this->codeToReg[$code] = $reg;
        }
    }

    return $this;
}


public function debugSeedingOrder(): void
{
    echo "===== Seeding Order Debug =====\n";

    foreach ($this->codeToReg as $code => $reg) {
        $name = $reg->players->first()?->full_name ?? 'TBD';
        $box = $reg->pivot->box_number ?? '?';
        $position = $this->finalPositions[$reg->id] ?? '?';

        echo "{$code}: {$name} (Box {$box}, Position {$position})\n";
    }

    echo "================================\n";
}


  public function getBoxStatsForPreview(): array
  {
    $result = [];
    $boxLetters = range('A', 'Z');

    foreach ($this->boxes as $boxNumber => $registrations) {
      $boxFixtures = $this->fixtureMap[$boxNumber] ?? collect();
      $stats = [];

      foreach ($registrations as $reg) {
        $rid = $reg->id;
        $wins = 0;
        $games = 0;

        foreach ($boxFixtures as $f) {
          foreach ($f->fixtureResults as $r) {
            if ($r->winner_registration === $rid) $wins++;
            if ($f->registration1_id === $rid) $games += $r->registration1_score;
            elseif ($f->registration2_id === $rid) $games += $r->registration2_score;
          }
        }

        $stats[] = [
          'reg' => $reg,
          'wins' => $wins,
          'games' => $games,
          'position' => $this->finalPositions[$rid] ?? null,
        ];
      }

      usort($stats, fn($a, $b) => $a['position'] <=> $b['position']);
      $result[$boxLetters[$boxNumber - 1]] = $stats;
    }

    return $result;
  }
  protected function buildDrawSets(): array
  {
    $drawSets = [];
    $settings = $this->draw->settings;

    // Case 1: Single group with manual seed pattern
    if ($settings->boxes == 1) {
      // Custom seeding pattern for single box
      $customSeedPattern = [1, 8, 4, 5, 6, 3, 2, 7];
      $boxCode = 'A'; // All players are in box A (or 1)

      $numDraws = ceil(count($customSeedPattern) / 8); // If more than 8, split into sets

      for ($d = 0; $d < $numDraws; $d++) {
        $offset = $d * 8;
        $set = [];

        for ($i = 0; $i < 8; $i++) {
          $pos = $customSeedPattern[$i] ?? null;
          if (!$pos) {
            $set[] = 0; // Bye
            continue;
          }

          $code = $boxCode . ($pos + $offset);
          $set[] = $this->codeToReg[$code]->id ?? 0;
        }

        $drawSets[] = $set;
      }
    }

    // Case 2: 2 boxes (A & B)
    if ($settings->boxes == 2) {
      $basePattern = ['A', 'B', 'A', 'B', 'A', 'B', 'A', 'B'];
      $positionPattern = [1, 4, 3, 2, 2, 3, 4, 1];
      $boxCodes = array_values(array_unique(array_map(fn($code) => substr($code, 0, 1), array_keys($this->codeToReg))));

      for ($i = 0; $i < count($boxCodes); $i += 2) {
        $boxA = $boxCodes[$i];
        $boxB = $boxCodes[$i + 1] ?? null;
        if (!$boxB) break;

        $maxSeeds = max(
          count(array_filter($this->codeToReg, fn($_, $code) => str_starts_with($code, $boxA), ARRAY_FILTER_USE_BOTH)),
          count(array_filter($this->codeToReg, fn($_, $code) => str_starts_with($code, $boxB), ARRAY_FILTER_USE_BOTH))
        );

        $numDraws = ceil($maxSeeds / 4);

        for ($d = 0; $d < $numDraws; $d++) {
          $offset = $d * 4;
          $set = collect($basePattern)->map(function ($letter, $j) use ($boxA, $boxB, $positionPattern, $offset) {
            $box = $letter === 'A' ? $boxA : $boxB;
            $code = $box . ($positionPattern[$j] + $offset);
            return $this->codeToReg[$code]->id ?? 0;
          })->toArray();

          $drawSets[] = $set;

        }
      }
    }

    // Case 3: 4 boxes (A, B, C, D)
    if ($settings->boxes == 4) {
      $basePattern = ['A', 'B', 'C', 'D', 'C', 'D', 'A', 'B'];
      $positionPattern = [1, 2, 2, 1, 1, 2, 2, 1];

      $maxPosition = collect(array_keys($this->codeToReg))
        ->map(fn($code) => (int) substr($code, 1))
        ->max();

      $numDraws = ceil($maxPosition / 2);

      for ($d = 0; $d < $numDraws; $d++) {
        $offset = $d * 2;
        $set = collect($basePattern)->map(function ($letter, $i) use ($positionPattern, $offset) {
          $pos = $positionPattern[$i] + $offset;
          $code = $letter . $pos;
          return $this->codeToReg[$code]->id ?? 0;
        })->toArray();

        $drawSets[] = $set;
      }
    }

    return $drawSets;
  }
public function getFinalPositionsVerbose(): array
{
    $ranked = [];

    foreach ($this->boxes as $boxNumber => $registrations) {
        $players = collect($registrations)
            ->map(function ($reg) use ($boxNumber) {
                $name = optional($reg->players)->first()?->full_name ?? "Unknown";
                $wins = 0;
                $games = 0;

                $fixtures = $this->fixtureMap[$boxNumber] ?? collect();
                foreach ($fixtures as $f) {
                    foreach ($f->fixtureResults as $r) {
                        if ($r->winner_registration === $reg->id) $wins++;
                        if ($f->registration1_id === $reg->id) $games += $r->registration1_score;
                        elseif ($f->registration2_id === $reg->id) $games += $r->registration2_score;
                    }
                }

                $rank = $this->finalPositions[$reg->id] ?? null;

                return [
                    'player' => $name,
                    'wins' => $wins,
                    'games' => $games,
                    'rank' => $rank,
                ];
            })
            ->sortBy('rank')
            ->values();

        $ranked[$boxNumber] = $players;
    }

    return $ranked;
}


  protected function clearOldFixtures(): void
  {
    Fixture::where('draw_id', $this->draw->id)
      ->where('stage', '!=', 'RR')
      ->delete();
  }

  public function generatePlayoffFixtures(): array
  {
    // Build draw sets (from box positions like A1, B2, etc.)
    $drawSets = $this->buildDrawSets();

    // Clear old non-RR fixtures before generating new ones
    $this->clearOldFixtures();

    $this->createFixtureTree($drawSets);

    // 🆕 Run auto-advancement logic for each bracket
    $bracketIds = Fixture::where('draw_id', $this->draw->id)
      ->where('stage', '!=', 'RR')
      ->pluck('bracket_id')
      ->unique()
      ->filter();




    // Return a map of fixtures for rendering
    return Fixture::where('draw_id', $this->draw->id)
      ->where('stage', '!=', 'RR')
      ->get()
      ->mapWithKeys(function ($f) {
        $group = $f->bracket_id ?? 0;
        return ["{$group}-{$f->match_nr}" => $f->id];
      })
      ->toArray();
  }

  protected function createFixtureTree(array $drawSets): void
  {
    foreach ($drawSets as $groupIndex => $codeSet) {
      $qfFixtures = [];

      // QFs
      for ($i = 0; $i < 4; $i++) {
        $r1 = $codeSet[$i * 2];
        $r2 = $codeSet[$i * 2 + 1];

        $fixture = Fixture::create([
          'draw_id' => $this->draw->id,
          'stage' => 'QF',
          'match_nr' => $i + 1,
          'round' => 1,
          'bracket_id' => $groupIndex + 1,
          'registration1_id' => $r1 ?: 0,
          'registration2_id' => $r2 ?: 0,
        ]);

        // Auto-advance BYEs
        if ($r1 === 0 && $r2 === 0) {
          $fixture->update(['winner_registration' => 0, 'match_status' => 5]);
        } elseif ($r1 === 0) {
          $fixture->update(['winner_registration' => $r2, 'match_status' => 3]);
        } elseif ($r2 === 0) {
          $fixture->update(['winner_registration' => $r1, 'match_status' => 3]);
        }

        $qfFixtures[] = $fixture;
      }

      // SFs
      $sf1 = Fixture::create([
        'draw_id' => $this->draw->id,
        'stage' => 'SF',
        'match_nr' => 5,
        'round' => 2,
        'bracket_id' => $groupIndex + 1,
      ]);

      $sf2 = Fixture::create([
        'draw_id' => $this->draw->id,
        'stage' => 'SF',
        'match_nr' => 6,
        'round' => 2,
        'bracket_id' => $groupIndex + 1,
      ]);

      // Link QFs → SFs with feeder_slot
      $qfFixtures[0]?->update(['parent_fixture_id' => $sf1->id, 'feeder_slot' => 1]);
      $qfFixtures[1]?->update(['parent_fixture_id' => $sf1->id, 'feeder_slot' => 2]);
      $qfFixtures[2]?->update(['parent_fixture_id' => $sf2->id, 'feeder_slot' => 1]);
      $qfFixtures[3]?->update(['parent_fixture_id' => $sf2->id, 'feeder_slot' => 2]);

      // Final
      $final = Fixture::create([
        'draw_id' => $this->draw->id,
        'stage' => 'F',
        'match_nr' => 7,
        'round' => 3,
        'bracket_id' => $groupIndex + 1,
      ]);

      $sf1->update(['parent_fixture_id' => $final->id, 'feeder_slot' => 1]);
      $sf2->update(['parent_fixture_id' => $final->id, 'feeder_slot' => 2]);

      // 3rd/4th playoff
      $third = Fixture::create([
        'draw_id' => $this->draw->id,
        'stage' => '3/4',
        'match_nr' => 12,
        'round' => 3,
        'bracket_id' => $groupIndex + 1,
      ]);

      $sf1->update(['loser_parent_fixture_id' => $third->id, 'loser_feeder_slot' => 1]);
      $sf2->update(['loser_parent_fixture_id' => $third->id, 'loser_feeder_slot' => 2]);

      // Consolation: CSFs + CF
      $csf1 = Fixture::create([
        'draw_id' => $this->draw->id,
        'stage' => 'C-SF1',
        'match_nr' => 8,
        'round' => 2,
        'bracket_id' => $groupIndex + 1,
      ]);

      $csf2 = Fixture::create([
        'draw_id' => $this->draw->id,
        'stage' => 'C-SF2',
        'match_nr' => 9,
        'round' => 2,
        'bracket_id' => $groupIndex + 1,
      ]);

      $cf = Fixture::create([
        'draw_id' => $this->draw->id,
        'stage' => 'C-F',
        'match_nr' => 10,
        'round' => 3,
        'bracket_id' => $groupIndex + 1,
      ]);

      $csf1->update(['parent_fixture_id' => $cf->id, 'feeder_slot' => 1]);
      $csf2->update(['parent_fixture_id' => $cf->id, 'feeder_slot' => 2]);

      // QFs → CSFs (loser flow)
      $qfFixtures[0]?->update(['loser_parent_fixture_id' => $csf1->id, 'loser_feeder_slot' => 1]);
      $qfFixtures[1]?->update(['loser_parent_fixture_id' => $csf1->id, 'loser_feeder_slot' => 2]);
      $qfFixtures[2]?->update(['loser_parent_fixture_id' => $csf2->id, 'loser_feeder_slot' => 1]);
      $qfFixtures[3]?->update(['loser_parent_fixture_id' => $csf2->id, 'loser_feeder_slot' => 2]);

      // 7th/8th playoff
      $seventh = Fixture::create([
        'draw_id' => $this->draw->id,
        'stage' => '7/8',
        'match_nr' => 11,
        'round' => 3,
        'bracket_id' => $groupIndex + 1,
      ]);

      $csf1->update(['loser_parent_fixture_id' => $seventh->id, 'loser_feeder_slot' => 1]);
      $csf2->update(['loser_parent_fixture_id' => $seventh->id, 'loser_feeder_slot' => 2]);

      // 🆕 Auto-advancement
      $this->autoAdvanceWinnersToNextStage($qfFixtures, $sf1, $sf2);
      $this->autoAdvanceFinal($sf1, $sf2, $final);
      $this->autoAdvanceFinalLosers($sf1, $sf2, $third);
      $this->autoAdvanceLosersToConsolation($qfFixtures, $csf1, $csf2, $seventh);
    }
  }

  public function getCodeToName(): array
  {
    $boxLetters = range('A', 'Z');
    $codeToName = [];

    foreach ($this->boxes as $boxNumber => $registrations) {
      $sorted = collect($registrations)
        ->sortBy(fn($r) => $this->finalPositions[$r->id] ?? 999)
        ->values();

      foreach ($sorted as $i => $reg) {
        $code = $boxLetters[$boxNumber - 1] . ($i + 1);
        $codeToName[$code] = $reg->players->first()?->full_name ?? 'TBD';
      }
    }

    return $codeToName;
  }
  protected function autoAdvanceFinalLosers(Fixture $sf1, Fixture $sf2, Fixture $third): void
  {
    $l1 = $this->getLoserId($sf1);
    $l2 = $this->getLoserId($sf2);

    $update = [];

    if (!is_null($l1)) $update['registration1_id'] = $l1;
    if (!is_null($l2)) $update['registration2_id'] = $l2;

    if ($update) {
      $third->update($update);

      // Auto-advance logic
      if (($update['registration1_id'] ?? null) === 0 && ($update['registration2_id'] ?? null) > 0) {
        $third->update(['winner_registration' => $update['registration2_id'], 'match_status' => 3]);
      } elseif (($update['registration2_id'] ?? null) === 0 && ($update['registration1_id'] ?? null) > 0) {
        $third->update(['winner_registration' => $update['registration1_id'], 'match_status' => 3]);
      } elseif (($update['registration1_id'] ?? null) === 0 && ($update['registration2_id'] ?? null) === 0) {
        $third->update(['winner_registration' => 0, 'match_status' => 5]);
      }
    }
  }

  public function generatePlayoffDraw(): string
  {
    // Ensure players are ranked and seeding codes assigned
    $this->rankPlayers()->assignSeedingCodes();

    // Build draw sets according to box count (2 or 4)
    $drawSets = $this->buildDrawSets();
    $fixtureMap = $this->draw->drawFixtures
      ->where('stage', '!=', 'RR')
      ->groupBy('bracket_id');

    $codeToName = $this->getCodeToName();
    $boxes = $this->draw->settings->boxes ?? 2;
    $fixtureMap = [];

    $fixtureMap = [];
    $fixtureMap = $this->draw->drawFixtures
      ->where('stage', '!=', 'RR')
      ->load(['registration1.players', 'registration2.players'])
      ->mapWithKeys(function ($f) {
        $key = ($f->bracket_id ?? 1) . '-' . $f->match_nr;
        return [
          $key => [
            'id' => $f->id,
            'p1' => $this->resolveName($f->match_nr, $f->registration1_id, $f->registration1, $f),
            'p2' => $this->resolveName($f->match_nr, $f->registration2_id, $f->registration2, $f),




            'parent_id' => $f->parent_fixture_id,
            'loser_id' => $f->loser_parent_fixture_id,
          ]
        ];
      })
      ->toArray();


    return view('backend.draw.partials.seeded-playoff-draw', [
      'codeToName' => $codeToName,
      'fixtureMap' => $fixtureMap,
      'drawSets' => $drawSets, // 🆕 helpful if you want to label each group like "Draw 1", "Draw 2", etc.
      'isDrawLocked' => $this->draw->locked ?? false,
      'boxCount' => $boxes,
      'draw' => $this->draw,
    ])->render();
  }

  public function generateRoundRobinFixtures($draw)
  {
    $draw = Draw::with(['registrations'])->findOrFail($draw->id);

    // Delete all existing fixtures for this draw
    $draw->drawFixtures()->delete();

    // Group registrations by box_number
    $grouped = $draw->registrations
      ->filter(fn($r) => $r->pivot->box_number)
      ->sortBy(fn($r) => $r->pivot->seed ?? PHP_INT_MAX)
      ->groupBy(fn($r) => $r->pivot->box_number);

    $matchNr = 1;

    DB::beginTransaction();

    try {
      foreach ($grouped as $boxNum => $regs) {
        $players = $regs->values(); // reset keys

        // Handle odd number of players with BYE
        if ($players->count() % 2 !== 0) {
          $players->push(null); // null = bye
        }

        $numPlayers = $players->count();
        $numRounds = $numPlayers - 1;
        $halfSize = $numPlayers / 2;

        // rotation excluding fixed player (first)
        $fixed = $players[0];
        $rotation = $players->slice(1)->values();

        for ($round = 0; $round < $numRounds; $round++) {
          $roundPlayers = collect([$fixed])->concat($rotation);

          for ($i = 0; $i < $halfSize; $i++) {
            $p1 = $roundPlayers[$i];
            $p2 = $roundPlayers[$numPlayers - 1 - $i];

            // Only create match if both players are real (no BYE)
            if ($p1 && $p2) {
              $draw->drawFixtures()->create([
                'match_nr' => $matchNr++,
                'registration1_id' => $p1->id,
                'registration2_id' => $p2->id,
                'round' => $round + 1, // 1-indexed round
                'bracket_id' => $boxNum,
                'draw_group_id' => $boxNum, // your Round Robin bracket ID
                'match_status' => '3',
                'draw_id' => $draw->id,
                'scheduled' => null,
                'stage' => 'RR'
              ]);
            }
          }

          // Rotate players clockwise (excluding fixed)
          $rotation->prepend($rotation->pop());
        }
      }

      DB::commit();
      return response()->json(['message' => 'Round Robin fixtures generated successfully.']);
    } catch (\Throwable $e) {
      DB::rollBack();
      return response()->json([
        'message' => 'Error generating fixtures.',
        'error' => $e->getMessage(),
      ], 500);
    }
  }
  protected function resolveName($matchNr, $regId, $regModel): string
  {
    if ($regId === 0) {
      return 'Bye';
    }

    if (is_null($regId)) {
      return '';
    }

    return optional(optional($regModel)->players)->first()?->full_name ?? '';
  }
  protected function autoAdvanceLosersToConsolation(array $qfFixtures, Fixture $csf1, Fixture $csf2, Fixture $seventh): void
  {
    $pairs = [
      [$qfFixtures[0], $qfFixtures[1], $csf1],
      [$qfFixtures[2], $qfFixtures[3], $csf2],
    ];

    foreach ($pairs as [$f1, $f2, $csf]) {
      $loser1 = $this->getLoserId($f1);
      $loser2 = $this->getLoserId($f2);

      $update = [];
      if (!is_null($loser1)) $update['registration1_id'] = $loser1;
      if (!is_null($loser2)) $update['registration2_id'] = $loser2;

      if ($update) {
        $csf->update($update);

        // Auto-advance if one side is Bye
        if (($update['registration1_id'] ?? null) === 0 && ($update['registration2_id'] ?? null) > 0) {
          $csf->update(['winner_registration' => $update['registration2_id'], 'match_status' => 3]);
        } elseif (($update['registration2_id'] ?? null) === 0 && ($update['registration1_id'] ?? null) > 0) {
          $csf->update(['winner_registration' => $update['registration1_id'], 'match_status' => 3]);
        } elseif (($update['registration1_id'] ?? null) === 0 && ($update['registration2_id'] ?? null) === 0) {
          $csf->update(['winner_registration' => 0, 'match_status' => 5]);
        }
      }
    }

    // Now 7/8 playoff
    $loser1 = $this->getLoserId($csf1);
    $loser2 = $this->getLoserId($csf2);

    $update = [];
    if (!is_null($loser1)) $update['registration1_id'] = $loser1;
    if (!is_null($loser2)) $update['registration2_id'] = $loser2;

    if ($update) {
      $seventh->update($update);

      if (($update['registration1_id'] ?? null) === 0 && ($update['registration2_id'] ?? null) > 0) {
        $seventh->update(['winner_registration' => $update['registration2_id'], 'match_status' => 3]);
      } elseif (($update['registration2_id'] ?? null) === 0 && ($update['registration1_id'] ?? null) > 0) {
        $seventh->update(['winner_registration' => $update['registration1_id'], 'match_status' => 3]);
      } elseif (($update['registration1_id'] ?? null) === 0 && ($update['registration2_id'] ?? null) === 0) {
        $seventh->update(['winner_registration' => 0, 'match_status' => 5]);
      }
    }
  }

  protected function getLoserId(?Fixture $fixture): ?int
  {
    if (!$fixture) return null;

    $p1 = $fixture->registration1_id;
    $p2 = $fixture->registration2_id;
    $winner = $fixture->winner_registration;

    if (is_null($winner)) return null;

    if ($p1 === $winner) return $p2;
    if ($p2 === $winner) return $p1;

    return null;
  }

  protected function autoAdvanceWinnersToNextStage(array $sourceFixtures, Fixture $targetFixture1, Fixture $targetFixture2): void
  {
    $pairs = [
      [$sourceFixtures[0], $sourceFixtures[1], $targetFixture1],
      [$sourceFixtures[2], $sourceFixtures[3], $targetFixture2],
    ];

    foreach ($pairs as [$f1, $f2, $target]) {
      $w1 = $f1?->winner_registration;
      $w2 = $f2?->winner_registration;

      $update = [];

      if (!is_null($w1)) $update['registration1_id'] = $w1;
      if (!is_null($w2)) $update['registration2_id'] = $w2;

      if ($update) {
        $target->update($update);

        // Auto-advance if one side is Bye
        if (($update['registration1_id'] ?? null) === 0 && ($update['registration2_id'] ?? null) > 0) {
          $target->update(['winner_registration' => $update['registration2_id'], 'match_status' => 3]);
        } elseif (($update['registration2_id'] ?? null) === 0 && ($update['registration1_id'] ?? null) > 0) {
          $target->update(['winner_registration' => $update['registration1_id'], 'match_status' => 3]);
        } elseif (($update['registration1_id'] ?? null) === 0 && ($update['registration2_id'] ?? null) === 0) {
          $target->update(['winner_registration' => 0, 'match_status' => 5]);
        }
      }
    }
  }

  protected function autoAdvanceFinal(Fixture $sf1, Fixture $sf2, Fixture $final): void
  {
    $w1 = $sf1->winner_registration;
    $w2 = $sf2->winner_registration;

    $update = [];

    if (!is_null($w1)) $update['registration1_id'] = $w1;
    if (!is_null($w2)) $update['registration2_id'] = $w2;

    if ($update) {
      $final->update($update);

      if ($w1 === 0 && $w2 > 0) {
        $final->update(['winner_registration' => $w2, 'match_status' => 3]);
      } elseif ($w2 === 0 && $w1 > 0) {
        $final->update(['winner_registration' => $w1, 'match_status' => 3]);
      } elseif ($w1 === 0 && $w2 === 0) {
        $final->update(['winner_registration' => 0, 'match_status' => 5]);
      }
    }
  }
  public static function autoAdvanceWinnersForFixture(Fixture $fixture): void
{
    // Skip if fixture not finished
    if (!$fixture->winner_registration || !$fixture->parent_fixture_id) return;

    $parent = Fixture::find($fixture->parent_fixture_id);
    if (!$parent) return;

    // Fill in parent registration slot
    if (!$parent->registration1_id) {
        $parent->registration1_id = $fixture->winner_registration;
    } elseif (!$parent->registration2_id) {
        $parent->registration2_id = $fixture->winner_registration;
    }

    $parent->save();

    // Auto-advance again if needed
    $r1 = $parent->registration1_id;
    $r2 = $parent->registration2_id;

    if ($r1 === 0 && $r2 > 0) {
        $parent->winner_registration = $r2;
        $parent->match_status = 3;
        $parent->save();

        self::autoAdvanceWinnersForFixture($parent);
    } elseif ($r2 === 0 && $r1 > 0) {
        $parent->winner_registration = $r1;
        $parent->match_status = 3;
        $parent->save();

        self::autoAdvanceWinnersForFixture($parent);
    } elseif ($r1 === 0 && $r2 === 0) {
        // Both BYEs, mark match as skipped and don't advance
        $parent->match_status = 3;
        $parent->winner_registration = 0;
        $parent->save();
    }
}
public static function autoAdvanceAllByesForDraw(int $drawId): array
{
    $autoAdvanced = [
        'advanced_fixture_ids' => [],
        'updated_count' => 0,
    ];

    $fixtures = Fixture::where('draw_id', $drawId)
        ->where('stage', '!=', 'RR')
        ->whereNull('winner_registration') // <-- Only if not already advanced!
        ->where(function ($q) {
            $q->where(function ($q2) {
                $q2->where('registration1_id', 0)->where('registration2_id', '>', 0);
            })->orWhere(function ($q2) {
                $q2->where('registration2_id', 0)->where('registration1_id', '>', 0);
            });
        })
        ->get();

    foreach ($fixtures as $fixture) {
        if ($fixture->registration1_id === 0 && $fixture->registration2_id > 0) {
            $fixture->winner_registration = $fixture->registration2_id;
        } elseif ($fixture->registration2_id === 0 && $fixture->registration1_id > 0) {
            $fixture->winner_registration = $fixture->registration1_id;
        } else {
            continue; // Only advance one BYE, skip weird/both-zero cases
        }

        $fixture->match_status = 3;
        $fixture->save();

        self::autoAdvanceWinnersForFixture($fixture);

        $autoAdvanced['advanced_fixture_ids'][] = $fixture->id;
        $autoAdvanced['updated_count']++;
    }

    return $autoAdvanced;
}


}
