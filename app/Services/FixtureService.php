<?php

namespace App\Services;

use App\Models\Draw;
use App\Models\Event;
use App\Models\TeamFixture;
use Illuminate\Support\Facades\DB;
use App\Models\TeamFixturePlayer;
use Illuminate\Support\Str;

class FixtureService
{
  private array $mixedDebug = [];

  /**
   * Create Draws per category/type and save fixtures.
   */
  public function createDrawsAndFixtures(Event $event, string $mode = 'perType', array $onlyCategories = null): void
  {
    $fixtures = $this->generateEventFixtures($event, $mode, $onlyCategories);

    DB::transaction(function () use ($event, $fixtures) {
      $drawIds = Draw::where('event_id', $event->id)->pluck('id');
      TeamFixture::whereIn('draw_id', $drawIds)->delete();
      Draw::where('event_id', $event->id)->delete();

      foreach ($fixtures as $category => $matches) {
        $grouped = collect($matches)->groupBy('type');

        foreach ($grouped as $type => $typeMatches) {
          $draw = Draw::create([
            'event_id' => $event->id,
            'drawName' => "$category " . ucfirst(str_replace('_', ' ', $type)),
            'drawType_id' => match ($type) {
              'singles' => 1,
              'doubles' => 2,
              'mixed' => 3,
              'singles_reverse' => 4,
              default => 0,
            },
            'rounds' => $typeMatches->max('round'),
          ]);

          $this->saveFixtures($typeMatches->toArray(), $draw->id, $event);
        }
      }
    });
  }
  /**
   * Persist fixtures into team_fixtures.
   */
  // ----------------------------------------------------------------
  // Saving Fixtures
  // ----------------------------------------------------------------

  public function saveFixtures(array $matches, int $drawId, Event $event): void
  {
    DB::transaction(function () use ($matches, $drawId, $event) {
      $playerIndex = $this->buildPlayerIndex($event);
      $matchCounter = 1;

      $grouped = collect($matches)->groupBy('round');

      foreach ($grouped as $roundNr => $roundFixtures) {
        $tieMap = [];
        $tieCounter = 1;

        \Log::debug("[FixtureService] Saving round {$roundNr}", [
          'fixtures' => count($roundFixtures),
        ]);

        foreach ($roundFixtures as $fixture) {
          if (empty($fixture['match'])) {
            continue;
          }

          [$region1Id, $region2Id] = $this->extractRegionIds($fixture['match'], $event);

          if (!$region1Id || !$region2Id) {
            \Log::warning("Fixture skipped: cannot resolve regions", [
              'match' => $fixture['match']
            ]);
            continue;
          }

          $pairKey = implode('-', collect([$region1Id, $region2Id])->sort()->all());

          if (!isset($tieMap[$pairKey])) {
            $tieMap[$pairKey] = $tieCounter++;
          }
          $currentTie = $tieMap[$pairKey];

          \Log::debug("[FixtureService] Creating fixture", [
            'round' => $roundNr,
            'tie' => $currentTie,
            'matchNr' => $matchCounter,
            'match' => $fixture['match'],
          ]);

          $fx = TeamFixture::create([
            'draw_id' => $drawId,
            'match_nr' => $matchCounter++,
            'round_nr' => (int) $roundNr,
            'tie_nr' => $currentTie,
            'draw_group_id' => $currentTie,
            'region1' => $region1Id,
            'region2' => $region2Id,
            'match_status' => 0,
            'scheduled' => 0,
            'home_rank_nr' => $fixture['ranks'][0] ?? null,
            'away_rank_nr' => $fixture['ranks'][1] ?? null,
          ]);

          $this->attachPlayersRows_2col($fx->id, $fixture['match'], $playerIndex);
        }
      }
    });
  }
  /**
   * Extract region IDs from a "RegionA PlayerA vs RegionB PlayerB" match string.
   */
  private function extractRegionIds(string $match, Event $event): array
  {
    [$left, $right] = array_pad(explode(' vs ', $match, 2), 2, '');

    $nameToId = $event->regions->pluck('id', 'region_name')->toArray();

    // normalize all region names
    $normalized = [];
    foreach ($nameToId as $name => $id) {
      $normalized[$this->normalizeKey($name)] = $id;
    }

    $resolve = function (string $side) use ($normalized): ?int {
      foreach ($normalized as $normName => $id) {
        if (str_starts_with($this->normalizeKey($side), $normName)) {
          return $id;
        }
      }
      return null;
    };

    return [$resolve($left), $resolve($right)];
  }

  // ----------------------------------------------------------------
  // Fixture Generation
  // ----------------------------------------------------------------

  public function generateEventFixtures(Event $event, string $mode = 'perType', array $onlyCategories = null): array
  {
    // ✅ Withdrawn teams (region name + category pattern)
    $withdrawals = [
      ['region' => 'Drakenstein 2025', 'category' => 'U/13 Boys'],
      ['region' => 'Drakenstein 2025', 'category' => 'U/13 Girls'],
    ];

    // ✅ Load nested relations
    $event->loadMissing(['regions.teams.players']);

    // ✅ Separate structures
    $regionMeta = [];
    $regionPlayers = [];

    foreach ($event->regions as $teamRegion) {
      $regionName = $teamRegion->region_name ?? "Region {$teamRegion->id}";
      $ordering = $teamRegion->pivot->ordering ?? 0;

      $regionMeta[] = [
        'id' => $teamRegion->id,
        'region_id' => $teamRegion->id,
        'region_name' => $regionName,
        'ordering' => $ordering,
      ];

      // Players grouped by age/gender
      foreach ($teamRegion->teams as $team) {
        if (preg_match('/\bu[\/\s-]?(\d+)\s*(boys|girls)\b/i', $team->name, $m)) {
          $age = (int) $m[1];
          $gender = strtolower($m[2]);

          $categoryName = "U/{$age} " . ucfirst($gender);
          $isWithdrawn = collect($withdrawals)->contains(function ($w) use ($regionName, $categoryName) {
            return strcasecmp($w['region'], $regionName) === 0 &&
              strcasecmp($w['category'], $categoryName) === 0;
          });

          if ($isWithdrawn) {
            \Log::info("[FixtureService] 🚫 Skipping withdrawn team", [
              'region' => $regionName,
              'team' => $team->name,
              'category' => $categoryName,
            ]);
            continue;
          }

          foreach ($team->players as $player) {
            $full = trim($player->full_name ?: "{$player->first_name} {$player->last_name}");
            $regionPlayers[$regionName][$age][$gender][] = $this->normalizeKey("{$regionName} {$full}");
          }
        }
      }
    }

    // ✅ Detect categories (e.g. "U/12 Boys", etc.)
    $categories = $this->detectCategoriesFromTeams($event);
    if ($onlyCategories) {
      $categories = array_values(array_filter(
        $categories,
        fn($c) => in_array($c, $onlyCategories, true)
      ));
    }

    if (empty($regionMeta) || empty($categories)) {
      \Log::warning('[FixtureService] ⚠️ No regions or categories found', [
        'regions' => count($regionMeta),
        'categories' => count($categories)
      ]);
      return [];
    }

    \Log::debug('[FixtureService] 🚀 Regions prepared for fixture generation', [
      'count' => count($regionMeta),
      'names' => array_column($regionMeta, 'region_name'),
      'ordering' => array_column($regionMeta, 'ordering'),
    ]);

    // ✅ Delegate with full structure
    return $mode === 'perTie'
      ? $this->generatePerTieFromData($categories, $regionMeta, $regionPlayers)
      : $this->generatePerTypeFromData($categories, $regionMeta, $regionPlayers);
  }

  public function detectCategoriesFromTeams(Event $event): array
  {
    // ✅ Withdrawn list — simplified to allow partial region matches
    $withdrawals = [
      ['region' => 'Drakenstein', 'category' => 'U/13 Boys'],
      ['region' => 'Drakenstein', 'category' => 'U/13 Girls'],
    ];

    $categories = [];
    $ages = [];
    $regionMeta = [];
    $regionPlayers = [];

    foreach ($event->regions as $teamRegion) {
      $regionName = $teamRegion->short_name ?? $teamRegion->region_name ?? "Region {$teamRegion->id}";
      $ordering = $teamRegion->pivot->ordering ?? 0;

      // Store region metadata for debugging
      $regionMeta[] = [
        'id' => $teamRegion->id,
        'region_id' => $teamRegion->id,
        'region_name' => $regionName,
        'ordering' => $ordering,
      ];

      // 🔍 Loop through region teams
      foreach ($teamRegion->teams as $team) {
        if (preg_match('/\bu[\/\s-]?(\d+)\s*(boys|girls)\b/i', $team->name, $m)) {
          $age = (int) $m[1];
          $gender = strtolower($m[2]);
          $categoryName = "U/{$age} " . ucfirst($gender);

          // 🚫 Skip withdrawn teams safely (partial name match allowed)
          $isWithdrawn = collect($withdrawals)->contains(function ($w) use ($regionName, $categoryName) {
            return stripos($regionName, $w['region']) !== false &&
              stripos($categoryName, $w['category']) !== false;
          });

          if ($isWithdrawn) {
            \Log::info("[FixtureService] 🚫 Skipping withdrawn team", [
              'region' => $regionName,
              'team' => $team->name,
              'category' => $categoryName,
            ]);
            continue;
          }

          // ✅ Collect players into the structured region player list
          foreach ($team->players as $player) {
            $full = trim($player->full_name ?: "{$player->first_name} {$player->last_name}");
            $regionPlayers[$regionName][$age][$gender][] = $this->normalizeKey("{$regionName} {$full}");
          }

          // ✅ Store category & gender info
          $ages[$age][] = ucfirst($gender);
          $categories[] = $categoryName;
        }
      }
    }

    // 🧩 Add mixed categories when both Boys + Girls exist for a given age
    foreach ($ages as $age => $genders) {
      if (in_array('Boys', $genders, true) && in_array('Girls', $genders, true)) {
        $categories[] = "U/{$age} Mixed";
      }
    }

    // 🧠 Fallback: if no categories built, infer from region player structure
    if (empty($categories) && !empty($regionPlayers)) {
      foreach ($regionPlayers as $region => $byAge) {
        foreach ($byAge as $age => $genders) {
          foreach (array_keys($genders) as $gender) {
            $categories[] = "U/{$age} " . ucfirst($gender);
          }
        }
      }
    }

    // 🪵 Log summary for debugging
    \Log::debug('[FixtureService] 🧮 Category detection summary', [
      'total_categories' => count($categories),
      'sample' => array_slice($categories, 0, 5),
      'regions' => array_column($regionMeta, 'region_name'),
    ]);

    // ✅ Return unique, sorted categories
    return array_values(array_unique($categories, SORT_STRING));
  }

  private function roundRobin(array $regionNames): array
  {
    $n = count($regionNames);
    if ($n < 2)
      return [];

    if ($n % 2 !== 0) {
      $regionNames[] = "Bye";
      $n++;
    }

    $rounds = [];
    for ($round = 0; $round < $n - 1; $round++) {
      $pairings = [];
      for ($i = 0; $i < $n / 2; $i++) {
        $home = $regionNames[$i];
        $away = $regionNames[$n - 1 - $i];
        if ($home !== "Bye" && $away !== "Bye") {
          $pairings[] = [$home, $away];
        }
      }

      $rounds[] = $pairings;

      // Rotate array (keeping first fixed)
      $last = array_pop($regionNames);
      array_splice($regionNames, 1, 0, $last);
    }

    return $rounds;
  }

  private function generatePerTypeFromData(array $categories, array $regionMeta, array $regionPlayers): array
  {
    // ✅ Sort regions by ordering
    usort($regionMeta, fn($a, $b) => ($a['ordering'] ?? 0) <=> ($b['ordering'] ?? 0));

    // Extract names for round robin
    $regionNames = array_values(array_map(fn($r) => $r['region_name'], $regionMeta));

    // ✅ Generate round robin and normalize
    $rounds = $this->roundRobin($regionNames);
    $rounds = array_map(function ($round) {
      $sorted = array_map(function ($pair) {
        sort($pair, SORT_STRING);
        return $pair;
      }, $round);
      usort($sorted, fn($a, $b) => strcmp($a[0] . $a[1], $b[0] . $b[1]));
      return $sorted;
    }, $rounds);

    // 🧭 Log normalized schedule
    \Log::debug('[FixtureService] ✅ Final round order', [
      'rounds' => collect($rounds)->map(function ($r, $i) {
        return ['round' => $i + 1, 'pairings' => collect($r)->map(fn($p) => "{$p[0]} vs {$p[1]}")->all()];
      }),
    ]);

    $fixtures = [];
    $roundCounter = 1;

    foreach ($rounds as $round) {
      foreach ($categories as $category) {
        if (!preg_match('/^U\/(\d+)\s+(Boys|Girls|Mixed)$/i', $category, $m))
          continue;

        $age = (int) $m[1];
        $catType = strtolower($m[2]);

        foreach ($round as [$regionA, $regionB]) {
          $playersA = $regionPlayers[$regionA][$age] ?? ['boys' => [], 'girls' => []];
          $playersB = $regionPlayers[$regionB][$age] ?? ['boys' => [], 'girls' => []];

          if ($catType === 'mixed') {
            $fixtures[$category] = array_merge(
              $fixtures[$category] ?? [],
              $this->buildMixedFixtures($category, $roundCounter, $playersA, $playersB)
            );
            continue;
          }

          $genderKey = $catType === 'boys' ? 'boys' : 'girls';
          $sideA = array_values($playersA[$genderKey] ?? []);
          $sideB = array_values($playersB[$genderKey] ?? []);

          $fixtures[$category] = array_merge(
            $fixtures[$category] ?? [],
            $this->buildSinglesFixtures($category, $roundCounter, $sideA, $sideB),
            $this->buildReverseSinglesFixtures($category, $roundCounter, $sideA, $sideB),
            $this->buildDoublesFixtures($category, $roundCounter, $sideA, $sideB)
          );
        }
      }

      $roundCounter++;
    }

    // ✅ Sort fixtures by round + region
    foreach ($fixtures as &$fxList) {
      usort($fxList, fn($a, $b) => [$a['round'], $a['match']] <=> [$b['round'], $b['match']]);
    }

    \Log::debug('[FixtureService] ✅ Fixtures generated', ['total' => count($fixtures, COUNT_RECURSIVE)]);
    return $fixtures;
  }

  /**
   * Build Mixed Fixtures
   */
  private function buildMixedFixtures(string $category, int $roundCounter, array $playersA, array $playersB): array
  {
    $fixtures = [];

    $boysA = array_values($playersA['boys'] ?? []);
    $girlsA = array_values($playersA['girls'] ?? []);
    $boysB = array_values($playersB['boys'] ?? []);
    $girlsB = array_values($playersB['girls'] ?? []);

    $maxMixed = min(count($boysA), count($girlsA), count($boysB), count($girlsB), 10);

    \Log::debug("[FixtureService] Mixed setup", ['maxMixed' => $maxMixed]);

    for ($i = 0; $i < $maxMixed; $i++) {
      $fixtures[] = [
        'round' => $roundCounter,
        'category' => $category,
        'type' => 'mixed',
        'match' => "{$boysA[$i]} + {$girlsA[$i]} vs {$boysB[$i]} + {$girlsB[$i]}",
        'board' => $i + 1,
        'ranks' => [$i + 1, $i + 1],
        'label' => 'MX' . ($i + 1),
      ];
    }

    return $fixtures;
  }

  /**
   * Build Singles Fixtures
   */
  private function buildSinglesFixtures(string $category, int $roundCounter, array $sideA, array $sideB): array
  {
    $fixtures = [];
    $maxSingles = min(count($sideA), count($sideB), 10);

    \Log::debug("[FixtureService] Singles setup", [
      'countA' => count($sideA),
      'countB' => count($sideB),
      'maxSingles' => $maxSingles,
    ]);

    for ($i = 0; $i < $maxSingles; $i++) {
      $fixtures[] = [
        'round' => $roundCounter,
        'category' => $category,
        'type' => 'singles',
        'match' => "{$sideA[$i]} vs {$sideB[$i]}",
        'board' => $i + 1,
        'ranks' => [$i + 1, $i + 1],
        'label' => 'S' . ($i + 1),
      ];
    }

    return $fixtures;
  }

  /**
   * Build Reverse Singles Fixtures
   */
  private function buildReverseSinglesFixtures(string $category, int $roundCounter, array $sideA, array $sideB): array
  {
    $fixtures = [];
    $maxReverse = (int) floor(min(count($sideA), count($sideB), 10) / 2) * 2;
    $srIndex = 1;

    \Log::debug("[FixtureService] Reverse singles setup", ['maxReverse' => $maxReverse]);

    for ($i = 0; $i < $maxReverse; $i += 2) {
      $fixtures[] = [
        'round' => $roundCounter,
        'category' => $category,
        'type' => 'singles_reverse',
        'match' => "{$sideA[$i]} vs {$sideB[$i + 1]}",
        'board' => null,
        'ranks' => [$i + 1, $i + 2],
        'label' => 'SR' . ($srIndex++),
      ];
      $fixtures[] = [
        'round' => $roundCounter,
        'category' => $category,
        'type' => 'singles_reverse',
        'match' => "{$sideA[$i + 1]} vs {$sideB[$i]}",
        'board' => null,
        'ranks' => [$i + 2, $i + 1],
        'label' => 'SR' . ($srIndex++),
      ];
    }

    return $fixtures;
  }

  /**
   * Build Doubles Fixtures
   */
  private function buildDoublesFixtures(string $category, int $roundCounter, array $sideA, array $sideB): array
  {
    $fixtures = [];
    $maxDoublesPlayers = (int) floor(min(count($sideA), count($sideB), 10) / 2) * 2;

    \Log::debug("[FixtureService] Doubles setup", ['maxDoublesPlayers' => $maxDoublesPlayers]);

    $pair = 1;
    for ($i = 0; $i < $maxDoublesPlayers; $i += 2) {
      $fixtures[] = [
        'round' => $roundCounter,
        'category' => $category,
        'type' => 'doubles',
        'match' => "{$sideA[$i]} + {$sideA[$i + 1]} vs {$sideB[$i]} + {$sideB[$i + 1]}",
        'board' => $pair,
        'ranks' => [$pair, $pair],
        'label' => 'D' . $pair,
      ];
      $pair++;
    }

    return $fixtures;
  }

  private function generatePerTieFromData(array $categories, array $regionMeta, array $regionPlayers): array
  {
    return $this->generatePerTypeFromData($categories, $regionMeta, $regionPlayers);
  }

  public function dumpFixturesByDraw(Event $event, string $mode = 'perType', array $onlyCategories = null): array
  {
    $fixtures = $this->generateEventFixtures($event, $mode, $onlyCategories);
    if (empty($fixtures)) {
      return [];
    }

    // Map region IDs → short name or fallback
    $regionNames = $event->regions
      ->mapWithKeys(fn($r) => [$r->id => ($r->short_name ?? $r->region_name ?? "Region {$r->id}")])
      ->toArray();

    // Friendly labels for fixture types
    $typeLabels = [
      'singles' => 'Singles',
      'singles_reverse' => 'Singles (Reverse)',
      'doubles' => 'Doubles',
      'mixed' => 'Mixed Doubles',
    ];

    $result = [];

    foreach ($fixtures as $category => $matches) {
      // Group matches by type
      $byType = collect($matches)->groupBy('type');

      foreach ($byType as $type => $typeMatches) {
        $label = $typeLabels[$type] ?? ucfirst(str_replace('_', ' ', $type));
        $drawName = "{$category} {$label}";

        // Group matches by round
        $byRound = $typeMatches->groupBy('round')->sortKeys();

        foreach ($byRound as $roundNr => $roundMatches) {
          // Group matches by tie (region vs region)
          $tieGroups = $roundMatches->groupBy(function ($fx) use ($event, $regionNames) {
            [$r1, $r2] = $this->extractRegionIds($fx['match'], $event);
            $n1 = $regionNames[$r1] ?? 'Unknown';
            $n2 = $regionNames[$r2] ?? 'Unknown';
            return "{$n1} vs {$n2}";
          });

          $added = false;

          foreach ($tieGroups as $tieKey => $tieFixtures) {
            $list = [];
            foreach ($tieFixtures as $fx) {
              if (!empty($fx['match'])) {
                $list[] = $fx['match'];
              }
            }
            if ($list) {
              $result[$drawName]["Round {$roundNr}"][$tieKey] = $list;
              $added = true;
            }
          }

          // Clean up empty rounds
          if (!$added && isset($result[$drawName]["Round {$roundNr}"]) && empty($result[$drawName]["Round {$roundNr}"])) {
            unset($result[$drawName]["Round {$roundNr}"]);
          }
        }

        // Clean up empty draw groups
        if (isset($result[$drawName]) && empty($result[$drawName])) {
          unset($result[$drawName]);
        }
      }
    }

    return $result;
  }


  private function buildPlayerIndex(Event $event): array
  {
    $index = [];
    foreach ($event->regions as $region) {
      $regionName = $region->region_name ?? "Region {$region->id}";
      foreach ($region->teams as $team) {
        foreach ($team->players as $p) {
          $full = trim($p->full_name ?: "{$p->first_name} {$p->last_name}");
          $key = $this->normalizeKey("{$regionName} {$full}");

          $index[$key] = [
            'player_id' => $p->id,
            'region_id' => $region->id,
          ];
        }
      }
    }
    return $index;
  }

 
  private function parseMatchPlayers(string $match): array
  {
    [$left, $right] = array_pad(explode(' vs ', $match, 2), 2, '');
    $split = fn(string $s) => array_values(array_filter(array_map('trim', explode('+', $s))));
    return ['home' => $split($left), 'away' => $split($right)];
  }

  private function normalizeKey(string $key): string
  {
    // collapse whitespace + lowercase
    return strtolower(trim(preg_replace('/\s+/', ' ', $key)));
  }

  public function generateDrawFixtures(Draw $draw): array
{
    // Build fixtures only for this draw's category/type
    $event = $draw->event;

    // Generate fixture list (adapt from your per-event logic)
    $fixtures = $this->generateEventFixtures($event, 'single', [$draw->category_id]);

    // Save to DB
    DB::transaction(function () use ($draw, $fixtures) {
        TeamFixture::where('draw_id', $draw->id)->delete();

        foreach ($fixtures as $match) {
            TeamFixture::create([
                'draw_id' => $draw->id,
                'round'   => $match['round'],
                'tie'     => $match['tie'],
                'home_team_id' => $match['home'],
                'away_team_id' => $match['away'],
            ]);
        }
    });

    return $fixtures;
}

  public function createSingleDrawAndFixtures(Event $event, array $categoryIds, int $drawTypeId, string $drawName): Draw
  {
    $event->loadMissing(['regions.teams.players']);

    $typeMap = [
      1 => 'singles',
      2 => 'doubles',
      3 => 'mixed',
      4 => 'singles_reverse',
    ];
    $type = $typeMap[$drawTypeId] ?? 'singles';

    $categories = \App\Models\Category::whereIn('id', $categoryIds)->get();
    if ($categories->isEmpty()) {
      \Log::warning("[FixtureService] No valid categories found", $categoryIds);
      return $this->createEmptyDraw($event, $categoryIds[0] ?? null, $drawTypeId, $drawName);
    }

    $detected = $this->detectCategoriesFromTeams($event);
    $matches = [];

    if ($type === 'mixed') {
      // ✅ Explicit Boys + Girls pairing
      $boysCategory = $categories->first(fn($c) => str_contains(strtolower($c->name), 'boys'));
      $girlsCategory = $categories->first(fn($c) => str_contains(strtolower($c->name), 'girls'));

      \Log::debug('[FixtureService] Mixed lookup', [
        'detected' => $detected,
        'boysCategory' => $boysCategory?->name,
        'girlsCategory' => $girlsCategory?->name,
      ]);

      if ($boysCategory && $girlsCategory) {
        // Extract age from boys category name (works the same for girls)
        $age = (int) filter_var($boysCategory->name, FILTER_SANITIZE_NUMBER_INT);
        $mixedCategoryName = "U/{$age} Mixed";

        // Ask generator explicitly for the Mixed category
        $fixtures = $this->generateEventFixtures($event, 'perType', [
          $mixedCategoryName,
        ]);

        if (isset($fixtures[$mixedCategoryName])) {
          $matches = array_filter(
            $fixtures[$mixedCategoryName],
            fn($fx) => $fx['type'] === 'mixed'
          );
          \Log::debug('[FixtureService] Using mixed key fixtures', [
            'mixedKey' => $mixedCategoryName,
            'count' => count($matches),
          ]);
        } else {
          \Log::warning('[FixtureService] No mixed fixtures generated', [
            'requested' => $mixedCategoryName,
            'available' => array_keys($fixtures),
          ]);
        }
      } else {
        \Log::warning('[FixtureService] Missing boys or girls category for mixed', [
          'boysCategory' => $boysCategory?->name,
          'girlsCategory' => $girlsCategory?->name,
        ]);
      }
    } else {
      // 🔹 Normal singles/doubles/reverse path
      foreach ($categories as $category) {
        $categoryName = $category->name;
        $matchedCategory = collect($detected)->first(
          fn($c) => str_contains(strtolower($c), strtolower($categoryName))
        );

        \Log::debug('[FixtureService] Category matching', [
          'requested' => $categoryName,
          'detected' => $detected,
          'matched' => $matchedCategory,
        ]);

        if ($matchedCategory) {
          $fixtures = $this->generateEventFixtures($event, 'perType', [$matchedCategory]);
          $catMatches = $fixtures[$matchedCategory] ?? $fixtures[$categoryName] ?? [];
          $catMatches = array_filter($catMatches, fn($fx) => $fx['type'] === $type);
          $matches = array_merge($matches, $catMatches);
        }
      }
    }

    \Log::debug('[FixtureService] Matches after merging', [
      'categories' => $categories->pluck('name'),
      'type' => $type,
      'count' => count($matches),
    ]);

    $draw = Draw::create([
      'event_id' => $event->id,
      'category_id' => $categoryIds[0],
      'drawName' => $drawName,
      'drawType_id' => $drawTypeId,
      'rounds' => collect($matches)->max('round') ?? 0,
    ]);

    if (method_exists($draw, 'categories')) {
      $draw->categories()->sync($categoryIds);
    }

    $this->saveFixtures($matches, $draw->id, $event);

    \Log::debug('[FixtureService] Draw persisted', [
      'draw_id' => $draw->id,
      'fixtures' => count($matches),
    ]);

    return $draw;
  }

  public function rebuildForDraw(\App\Models\Draw $draw): void
  {
    \Log::info('[FixtureService::rebuildForDraw] Starting', [
      'draw_id' => $draw->id,
      'draw_name' => $draw->drawName,
      'event_id' => $draw->event_id,
    ]);

    // ✅ Generate fixtures fresh for this draw
    $matches = $this->generateFixturesForDraw($draw);

    if (empty($matches)) {
      \Log::warning('[FixtureService::rebuildForDraw] ⚠️ No matches returned', [
        'draw_id' => $draw->id,
      ]);
      return;
    }

    // ✅ Load full event context
    $event = $draw->event()->with(['regions.teams.players'])->first();
    if (!$event) {
      \Log::error('[FixtureService::rebuildForDraw] ❌ Event not found', ['draw_id' => $draw->id]);
      return;
    }

    DB::transaction(function () use ($matches, $draw, $event) {
      // 🔎 Keep old fixture venue mapping
      $existingVenues = \App\Models\TeamFixture::where('draw_id', $draw->id)
        ->get(['id', 'region1', 'region2', 'round_nr', 'venue_id'])
        ->mapWithKeys(function ($fx) {
          $key = "{$fx->round_nr}-" . implode('-', collect([$fx->region1, $fx->region2])->sort()->all());
          return [$key => $fx->venue_id];
        });

      // 🧹 Delete all old fixtures + players
      $fixtureIds = \App\Models\TeamFixture::where('draw_id', $draw->id)->pluck('id');
      if ($fixtureIds->isNotEmpty()) {
        \App\Models\TeamFixturePlayer::whereIn('team_fixture_id', $fixtureIds)->delete();
        \App\Models\TeamFixture::whereIn('id', $fixtureIds)->delete();
      }

      $playerIndex = $this->buildPlayerIndex($event);
      $matchCounter = 1;
      $grouped = collect($matches)->groupBy('round');

      foreach ($grouped as $roundNr => $roundFixtures) {
        $tieMap = [];
        $tieCounter = 1;

        \Log::debug("[FixtureService::rebuildForDraw] Creating round {$roundNr}", [
          'fixtures' => count($roundFixtures),
        ]);

        foreach ($roundFixtures as $fx) {
          if (empty($fx['match']))
            continue;

          [$region1Id, $region2Id] = $this->extractRegionIds($fx['match'], $event);
          if (!$region1Id || !$region2Id) {
            \Log::warning("[FixtureService::rebuildForDraw] Skipping fixture (missing region IDs)", [
              'match' => $fx['match'],
            ]);
            continue;
          }

          // 🧩 Same tie mapping as saveFixtures()
          $pairKey = implode('-', collect([$region1Id, $region2Id])->sort()->all());
          if (!isset($tieMap[$pairKey])) {
            $tieMap[$pairKey] = $tieCounter++;
          }
          $currentTie = $tieMap[$pairKey];
          $formattedTieNr = ($roundNr * 100) + $currentTie;

          // 🏟️ Try to restore venue if previously assigned
          $venueKey = "{$roundNr}-" . implode('-', collect([$region1Id, $region2Id])->sort()->all());
          $venueId = $existingVenues[$venueKey] ?? null;

          $fixture = \App\Models\TeamFixture::create([
            'draw_id' => $draw->id,
            'match_nr' => $matchCounter++,
            'round_nr' => (int) $roundNr,
            'tie_nr' => $formattedTieNr,
            'draw_group_id' => $currentTie,
            'region1' => $region1Id,
            'region2' => $region2Id,
            'venue_id' => $venueId,   // ✅ Keep previous venue if found
            'match_status' => 0,
            'scheduled' => 0,
            'home_rank_nr' => $fx['ranks'][0] ?? null,
            'away_rank_nr' => $fx['ranks'][1] ?? null,
          ]);

          // 🎾 Attach players to fixture
          $this->attachPlayersRows_2col($fixture->id, $fx['match'], $playerIndex);
        }
      }

      \Log::info('[FixtureService::rebuildForDraw] ✅ Full rebuild complete', [
        'draw_id' => $draw->id,
        'draw_name' => $draw->drawName,
        'fixtures_created' => $matchCounter - 1,
        'venues_retained' => count(array_filter($existingVenues->toArray())),
        'total_db' => \App\Models\TeamFixture::where('draw_id', $draw->id)->count(),
      ]);
    });
  }

  private function attachPlayersRows_2col(int $fixtureId, string $match, array $playerIndex): void
  {
    // 🧹 Always clear previous links
    \App\Models\TeamFixturePlayer::where('team_fixture_id', $fixtureId)->delete();

    $sides = $this->parseMatchPlayers($match);
    $home = $sides['home'] ?? [];
    $away = $sides['away'] ?? [];

    $pairs = min(count($home), count($away));
    for ($i = 0; $i < $pairs; $i++) {
      $homeKey = $this->normalizeKey($home[$i] ?? '');
      $awayKey = $this->normalizeKey($away[$i] ?? '');

      if (!isset($playerIndex[$homeKey]) || !isset($playerIndex[$awayKey])) {
        \Log::warning("⚠️ Player not resolved for fixture", [
          'fixtureId' => $fixtureId,
          'homeKey' => $homeKey,
          'awayKey' => $awayKey,
        ]);
        continue;
      }

      \App\Models\TeamFixturePlayer::create([
        'team_fixture_id' => $fixtureId,
        'team1_id' => $playerIndex[$homeKey]['player_id'],
        'team2_id' => $playerIndex[$awayKey]['player_id'],
      ]);
    }
  }

  public function generateFixturesForDraw(Draw $draw): array
  {
    \Log::debug('[FixtureService::generateFixturesForDraw] Starting', [
      'draw_id' => $draw->id,
      'event_id' => $draw->event_id,
      'draw_name' => $draw->drawName,
      'draw_type_id' => $draw->drawType_id,
    ]);

    $event = $draw->event()->with(['regions.teams.players'])->first();
    if (!$event) {
      \Log::error('[FixtureService::generateFixturesForDraw] ❌ Event not found', ['draw_id' => $draw->id]);
      return [];
    }

    // ✅ Robust parser for category from drawName
    $categoryName = $draw->category?->name;
    if (!$categoryName && preg_match('/(U[\/\-\s]?\d+)\s*(Boys|Girls|Mixed)/i', $draw->drawName, $m)) {
      $categoryName = sprintf('%s %s', strtoupper($m[1]), ucfirst(strtolower($m[2])));
    }

    if (!$categoryName) {
      \Log::warning('[FixtureService::generateFixturesForDraw] ⚠️ No category detected', [
        'draw_id' => $draw->id,
        'draw_name' => $draw->drawName,
      ]);
      return [];
    }

    $typeMap = [
      1 => 'singles',
      2 => 'doubles',
      3 => 'mixed',
      4 => 'singles_reverse',
    ];
    $type = $typeMap[$draw->drawType_id] ?? 'singles';

    \Log::debug('[FixtureService::generateFixturesForDraw] Category + Type', [
      'category' => $categoryName,
      'type' => $type,
    ]);

    $fixtures = $this->generateEventFixtures($event, 'perType', [$categoryName]);
    if (empty($fixtures))
      return [];

    $matches = [];
    foreach ($fixtures as $cat => $fxList) {
      $matches = array_merge($matches, array_filter($fxList, fn($fx) => $fx['type'] === $type));
    }

    \Log::debug('[FixtureService::generateFixturesForDraw] ✅ Fixtures generated', [
      'draw_id' => $draw->id,
      'count' => count($matches),
    ]);

    return $matches;
  }

  public function createIndividualDraw(Event $event, string $drawName): Draw
  {
    return Draw::create([
      'event_id' => $event->id,
      'drawName' => $drawName,
      'drawType_id' => 1,     // always singles for individual events
      'category_id' => null,  // no team category
      'rounds' => 0,
    ]);
  }


}
