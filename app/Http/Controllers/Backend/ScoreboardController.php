<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use App\Models\Team;
  use Illuminate\Support\Facades\Schema;
  use Illuminate\Support\Facades\DB;
class ScoreboardController extends Controller
{
  public function index()
  {
    //
  }

  public function store(Request $request)
  {
    //
  }

  /**
   * Show scoreboard for event.
   */
  /**
   * Show scoreboard for event.
   */
  /**
   * Show scoreboard for event.
   */
  /**
   * Show scoreboard for event.
   */


  /**
   * Main scoreboard display — recalculates after filtering
   */
  public function show(Request $request, $id)
  {
    // ============================================================
    // DEBUG SWITCH
    // ============================================================
    $debug = (bool) $request->boolean('debug'); // enable with ?debug=1
    $exclude = 'Eden';     // e.g. ?exclude=zfm

    $t0 = hrtime(true);
    $log = function ($msg, array $ctx = []) use ($debug) {
      if ($debug)
        \Log::debug($msg, $ctx);
    };

    // ============================================================
    // [STEP 1] LOAD EVENT + RELATIONSHIPS
    // ============================================================
    $event = $this->getFilteredEvent($id, $exclude);
    // If getFilteredEvent doesn't eager-load, you can switch to:
    // $event = Event::with([
    //   'regions.teams.players',
    //   'draws.fixtures.teamResults',
    //   'draws.fixtures.fixturePlayers',
    // ])->findOrFail($id);

    $log('[STEP 1] EVENT LOADED', [
      'event_id' => $event->id,
      'event_name' => $event->name ?? null,
      'regions_count' => $event->regions?->count() ?? 0,
      'draws_count' => $event->draws?->count() ?? 0,
      'exclude_param' => $exclude,
    ]);
   
    // quick structure probe
    $regionSumm = $event->regions->map(fn($r) => [
      'region' => $r->short_name ?? $r->name ?? $r->id,
      'teams' => $r->teams?->count() ?? 0,
      'players_across_teams' => ($r->teams?->sum(fn($t) => $t->players?->count() ?? 0)) ?? 0,
    ])->all();
    $log('[STEP 1A] REGION STRUCTURE SUMMARY', ['regions' => $regionSumm]);

    $playerStats = [];
    $baseRanks = [];
    $dump['event'] = $event;
     $dump['regionSumm'] = $regionSumm;

    // ============================================================
    // [STEP 2] BUILD BASE RANK MAP FROM TEAMS -> PLAYERS
    // ============================================================
    $totalPlayersSeen = 0;
    foreach ($event->regions as $region) {
      foreach ($region->teams as $team) {
        foreach ($team->players as $rank => $player) {
          $age = preg_match('/(u\s*\/?\d+)/i', $team->name, $m)
            ? strtoupper(str_replace([' ', '/'], '', $m[1])) : 'Other';
          $gender = str_contains(strtolower($team->name), 'girl')
            ? 'Girls' : (str_contains(strtolower($team->name), 'boy') ? 'Boys' : 'Mixed');
          $groupKey = "{$age} {$gender}";
          $baseRanks[$groupKey][$player->id] = $rank + 1;
          $totalPlayersSeen++;
        }
      }
    }
    $log('[STEP 2] BASE RANK MAP BUILT', [
      'groups' => array_keys($baseRanks),
      'total_players' => $totalPlayersSeen,
      'groups_count' => count($baseRanks),
    ]);
    $dump['baseRanks'] = $baseRanks;
    $dump['totalPlayersSeen'] = $totalPlayersSeen;
    $dump['CountbaseRanks'] = count($baseRanks);
    foreach ($baseRanks as $gKey => $map) {
      $rankCounts = array_count_values(array_values($map));
      ksort($rankCounts);
      $log('[STEP 2A] BASE RANK DISTRIBUTION', [
        'group' => $gKey,
        'players_in_group' => count($map),
        'rank_counts' => $rankCounts,
        'sample_player_ids' => array_slice(array_keys($map), 0, 6),
      ]);
    }
 
    // ============================================================
    // [STEP 3] DRAW DISCOVERY + SINGLES FILTER
    // ============================================================
    $drawNames = $event->draws->pluck('drawName');
    $log('[STEP 3] DRAWS LOADED', [
      'draws_count' => $event->draws->count(),
      'draw_names' => $drawNames,
    ]);

    // ============================================================
// [STEP 4] PROCESS SINGLES FIXTURES + RESULTS (Refactored)
// ============================================================
    $processedMatches = 0;
    $skippedNoResults = 0;
    $skippedNoPlayers = 0;
    $singlesDrawsProcessed = 0;

    foreach ($event->draws as $draw) {
      $name = strtolower($draw->drawName);
      if (!preg_match('/singles?/i', $name)) {
        $log('[STEP 4] SKIP NON-SINGLES DRAW', ['draw' => $draw->drawName]);
        continue;
      }

      $age = preg_match('/(u\s*\/?\d+)/i', $draw->drawName, $m)
        ? strtoupper(str_replace([' ', '/'], '', $m[1])) : 'Other';
      $gender = str_contains($name, 'girl') ? 'Girls' : (str_contains($name, 'boy') ? 'Boys' : 'Mixed');
      $groupKey = "{$age} {$gender}";
      $singlesDrawsProcessed++;

      $log('[STEP 4A] DRAW ENTER', [
        'draw' => $draw->drawName,
        'group_key' => $groupKey,
        'fixtures_count' => $draw->fixtures?->count() ?? 0,
      ]);

      foreach ($draw->fixtures as $fixture) {
        $results = $fixture->teamResults->sortBy('set_nr');
        if ($results->isEmpty()) {
          $skippedNoResults++;
          $log('[STEP 4B] SKIP FIXTURE NO RESULTS', ['fixture_id' => $fixture->id]);
          continue;
        }

        $final = $results->last();
        $winnerId = $final->match_winner_id;
        $loserId = $final->match_loser_id;

        $winner = $loser = null;
        foreach ($fixture->fixturePlayers as $fp) {
          if ($fp->team1_id == $winnerId)
            $winner = $fp->team1;
          if ($fp->team2_id == $winnerId)
            $winner = $fp->team2;
          if ($fp->team1_id == $loserId)
            $loser = $fp->team1;
          if ($fp->team2_id == $loserId)
            $loser = $fp->team2;
        }

        if (!$winner || !$loser) {
          $skippedNoPlayers++;
          $log('[STEP 4C] SKIP FIXTURE NO WINNER/LOSER', [
            'fixture_id' => $fixture->id,
            'winner_id' => $winnerId,
            'loser_id' => $loserId,
            'fp_count' => $fixture->fixturePlayers?->count() ?? 0,
          ]);
          continue;
        }

        // --- Determine sides (for set counting and region lookup)
        $sideWinner = $sideLoser = null;
        foreach ($fixture->fixturePlayers as $fp) {
          if ($fp->team1_id == $winner->id)
            $sideWinner = 'region1';
          if ($fp->team2_id == $winner->id)
            $sideWinner = 'region2';
          if ($fp->team1_id == $loser->id)
            $sideLoser = 'region1';
          if ($fp->team2_id == $loser->id)
            $sideLoser = 'region2';
        }

        // --- Region resolution via team_fixtures.region1 / region2 ---
        $winnerRegion = $sideWinner && $fixture->{$sideWinner}
          ? \App\Models\TeamRegion::find($fixture->{$sideWinner})?->short_name
          : null;

        $loserRegion = $sideLoser && $fixture->{$sideLoser}
          ? \App\Models\TeamRegion::find($fixture->{$sideLoser})?->short_name
          : null;

        // --- Count sets ---
        $setsWonWinner = $setsLostWinner = $setsWonLoser = $setsLostLoser = 0;
        foreach ($results as $set) {
          if (!is_numeric($set->team1_score) || !is_numeric($set->team2_score))
            continue;

          $winnerIsTeam1 = ($sideWinner === 'region1');
          $loserIsTeam1 = ($sideLoser === 'region1');

          if ($winnerIsTeam1) {
            $setsWonWinner += (int) ($set->team1_score > $set->team2_score);
            $setsLostWinner += (int) ($set->team2_score > $set->team1_score);
          } else {
            $setsWonWinner += (int) ($set->team2_score > $set->team1_score);
            $setsLostWinner += (int) ($set->team1_score > $set->team2_score);
          }

          if ($loserIsTeam1) {
            $setsWonLoser += (int) ($set->team1_score > $set->team2_score);
            $setsLostLoser += (int) ($set->team2_score > $set->team1_score);
          } else {
            $setsWonLoser += (int) ($set->team2_score > $set->team1_score);
            $setsLostLoser += (int) ($set->team1_score > $set->team2_score);
          }
        }

        $winnerRank = $baseRanks[$groupKey][$winner->id] ?? null;
        $loserRank = $baseRanks[$groupKey][$loser->id] ?? null;

        $log('[STEP 4D] REGION TRACE', [
          'fixture_id' => $fixture->id,
          'group' => $groupKey,
          'winner_id' => $winner->id,
          'winner_name' => "{$winner->name} {$winner->surname}",
          'winner_region' => $winnerRegion,
          'winner_rank' => $winnerRank,
          'loser_id' => $loser->id,
          'loser_name' => "{$loser->name} {$loser->surname}",
          'loser_region' => $loserRegion,
          'loser_rank' => $loserRank,
          'sets_winner' => ['W' => $setsWonWinner, 'L' => $setsLostWinner],
          'sets_loser' => ['W' => $setsWonLoser, 'L' => $setsLostLoser],
        ]);

        // --- Points + record updates ---
        $points = 1;
        $setSummary = $results->map(fn($s) => "{$s->team1_score}-{$s->team2_score}")->implode(', ');

        // WINNER record
        if (!isset($playerStats[$groupKey][$winner->id])) {
          $playerStats[$groupKey][$winner->id] = [
            'id' => $winner->id,
            'name' => "{$winner->name} {$winner->surname}",
            'region_short' => $winnerRegion ?? '',
            'wins' => 0,
            'losses' => 0,
            'sets_won' => 0,
            'sets_lost' => 0,
            'points' => 0,
            'rank' => $winnerRank,
            'won_against' => [],
            'lost_to' => [],
          ];
        }

        $playerStats[$groupKey][$winner->id]['wins']++;
        $playerStats[$groupKey][$winner->id]['points'] += $points;
        $playerStats[$groupKey][$winner->id]['sets_won'] += $setsWonWinner;
        $playerStats[$groupKey][$winner->id]['sets_lost'] += $setsLostWinner;
        $playerStats[$groupKey][$winner->id]['won_against'][] = [
          'name' => "{$loser->name} {$loser->surname}",
          'score' => $setSummary,
        ];

        // LOSER record
        if (!isset($playerStats[$groupKey][$loser->id])) {
          $playerStats[$groupKey][$loser->id] = [
            'id' => $loser->id,
            'name' => "{$loser->name} {$loser->surname}",
            'region_short' => $loserRegion ?? '',
            'wins' => 0,
            'losses' => 0,
            'sets_won' => 0,
            'sets_lost' => 0,
            'points' => 0,
            'rank' => $loserRank,
            'won_against' => [],
            'lost_to' => [],
          ];
        }

        $playerStats[$groupKey][$loser->id]['losses']++;
        $playerStats[$groupKey][$loser->id]['sets_won'] += $setsWonLoser;
        $playerStats[$groupKey][$loser->id]['sets_lost'] += $setsLostLoser;
        $playerStats[$groupKey][$loser->id]['lost_to'][] = [
          'name' => "{$winner->name} {$winner->surname}",
          'score' => $setSummary,
        ];

        $processedMatches++;
      }
    }

    // summary log
    $dump['singles_draws_processed'] = $singlesDrawsProcessed;
    $dump['processed_matches'] = $processedMatches;
    $dump['skipped_no_results'] = $skippedNoResults;
    $dump['skipped_no_players'] = $skippedNoPlayers;

    $log('[STEP 4Z] FIXTURE PASS SUMMARY', [
      'singles_draws_processed' => $singlesDrawsProcessed,
      'processed_matches' => $processedMatches,
      'skipped_no_results' => $skippedNoResults,
      'skipped_no_players' => $skippedNoPlayers,
    ]);


    // ============================================================
    // [STEP 5] BONUS POINTS (odd ranks)
    // ============================================================
    $bonusApplied = 0;
    foreach ($playerStats as $groupKey => &$players) {
      foreach ($players as &$p) {
        if (isset($p['rank']) && ($p['rank'] % 2) === 1) {
          $p['points'] += 1;
          $bonusApplied++;
        }
      }

      // ========================================================
      // [STEP 6] EXCLUDE REGION FOR RANKING + SORT
      // ========================================================
      $filteredForRanking = collect($players)->reject(function ($p) use ($exclude) {
        if (!$exclude)
          return false;
        return strtolower($p['region_short'] ?? '') === strtolower($exclude);
      });

      // Pairing & null rank diagnostics
      $pairBuckets = [];
      $nullRank = [];
      foreach ($filteredForRanking as $pp) {
        $r = $pp['rank'] ?? null;
        if ($r === null) {
          $nullRank[] = [
            'id' => $pp['id'] ?? null,
            'name' => $pp['name'] ?? null,
            'region' => $pp['region_short'] ?? '',
            'points' => $pp['points'] ?? 0,
          ];
          continue;
        }
        $pair = $pp['region_short'] . '-' . (int) ceil($r / 2);
        $pairBuckets[$pair][] = [
          'id' => $pp['id'],
          'name' => $pp['name'],
          'rank' => $r,
          'region' => $pp['region_short'] ?? '',
          'points' => $pp['points'] ?? 0,
        ];
      }
      $overflow = [];
      foreach ($pairBuckets as $pairIndex => $list) {
        if (count($list) > 2)
          $overflow[$pairIndex] = count($list);
      }

      ksort($pairBuckets);
      $log('[STEP 6A] PAIRING PRE-SORT', [
        'group' => $groupKey,
        'excluded_region' => $exclude,
        'total_in_group' => count($players),
        'considered_count' => count($filteredForRanking),
        'null_rank_count' => count($nullRank),
        'pair_bucket_sizes' => collect($pairBuckets)->map(fn($v) => count($v))->all(),
        'overflow_buckets' => $overflow,
        'null_rank_players_sample' => array_slice($nullRank, 0, 6),
      ]);

      // Sort
      $sorted = $filteredForRanking->sort(function ($a, $b) {
        $pairA = ceil(($a['rank'] ?? 99) / 2);
        $pairB = ceil(($b['rank'] ?? 99) / 2);
        if ($pairA !== $pairB)
          return $pairA <=> $pairB;

        if (($a['points'] ?? 0) !== ($b['points'] ?? 0))
          return ($b['points'] ?? 0) <=> ($a['points'] ?? 0);

        $diffA = ($a['sets_won'] ?? 0) - ($a['sets_lost'] ?? 0);
        $diffB = ($b['sets_won'] ?? 0) - ($b['sets_lost'] ?? 0);
        if ($diffA !== $diffB)
          return $diffB <=> $diffA;

        if (($a['sets_won'] ?? 0) !== ($b['sets_won'] ?? 0))
          return ($b['sets_won'] ?? 0) <=> ($a['sets_won'] ?? 0);

        return strcmp($a['name'] ?? '', $b['name'] ?? '');
      })->values()->all();

      $sortedPreview = collect($sorted)->take(12)->map(function ($p) {
        $pair = ($p['rank'] ?? null) ? (int) ceil($p['rank'] / 2) : null;
        return [
          'id' => $p['id'],
          'name' => $p['name'],
          'rank' => $p['rank'],
          'pair' => $pair,
          'region' => $p['region_short'] ?? '',
          'points' => $p['points'] ?? 0,
        ];
      })->all();

      $log('[STEP 6B] PAIRING POST-SORT', [
        'group' => $groupKey,
        'excluded_region' => $exclude,
        'top12_preview' => $sortedPreview,
      ]);

      // Re-append excluded (still visible at bottom)
      $excludedPlayers = collect($players)->filter(function ($p) use ($exclude) {
        return strtolower($p['region_short'] ?? '') === strtolower($exclude);
      });

      $players = collect($sorted)->merge($excludedPlayers)->values()->all();
    }
    $log('[STEP 5Z] BONUS SUMMARY', ['bonus_applied' => $bonusApplied]);

    // ============================================================
    // [STEP 7] FLAT SIDEBAR LIST
    // ============================================================
    $flatPlayersByGroup = collect($playerStats)->map(function ($players) {
      return collect($players)->sortBy('rank')->map(fn($p) => [
        'id' => $p['id'],
        'name' => $p['name'],
        'region_short' => $p['region_short'] ?? '',
        'rank' => $p['rank'] ?? null,
      ])->values()->toArray();
    });

    // ============================================================
    // [STEP 8] FINAL EXCLUDE (HARD FILTER OUT OF OUTPUT)
    // ============================================================
    if ($exclude) {
      $excludeLower = strtolower($exclude);

      foreach ($playerStats as $groupKey => &$players) {
        $players = collect($players)
          ->reject(fn($p) => strtolower($p['region_short'] ?? '') === $excludeLower)
          ->values()->all();
      }
      foreach ($flatPlayersByGroup as $groupKey => &$flatList) {
        $flatList = collect($flatList)
          ->reject(fn($p) => strtolower($p['region_short'] ?? '') === $excludeLower)
          ->values()->toArray();
      }

      $log('[STEP 8] FINAL FILTER APPLIED', [
        'excluded_region' => strtoupper($exclude),
        'groups_remaining' => count($playerStats),
        'total_players_remaining' => collect($playerStats)->flatten(1)->count(),
      ]);
    }

    // ============================================================
    // [STEP 9] SUMMARY + RETURN
    // ============================================================
    $t1 = hrtime(true);
    $log('[STEP 9] SUMMARY', [
      'groups_count' => count($playerStats),
      'groups' => array_keys($playerStats),
      'sidebar_groups' => $flatPlayersByGroup->keys()->all(),
      'elapsed_ms' => round(($t1 - $t0) / 1e6, 2),
    ]);

    return view('backend.scoreboard.show-scoreboard', [
      'event' => $event,
      'playerStats' => $playerStats,
      'excluded' => $exclude,
      'flatPlayersByGroup' => $flatPlayersByGroup,
    ]);
  }

  public function showScoreboard($id)
  {
    $event = Event::with(['regions', 'draws.fixtures.teamResults', 'draws.draw_types'])
      ->findOrFail($id);

    $drawTypeMap = [
      1 => 'Singles',
      2 => 'Doubles',
      3 => 'Mixed',
      4 => 'Reverse',
    ];

    $scoreboard = [];
    $debugData = [];

    \Log::debug("[SCOREBOARD] START", [
      'event_id' => $event->id,
      'event_name' => $event->name,
      'regions_count' => $event->regions->count(),
      'draws_count' => $event->draws->count(),
    ]);

    foreach ($event->draws as $draw) {
      $name = strtolower($draw->drawName);

      // 🧩 Detect age (U10, U/10, U-10, U 10, Under 10, etc.)
      if (preg_match('/u[\/\-\s]?(\d{1,2})/i', $name, $m)) {
        $age = 'U' . trim($m[1]);
      } elseif (preg_match('/under\s*(\d{1,2})/i', $name, $m)) {
        $age = 'U' . trim($m[1]);
      } else {
        $age = 'Other';
      }

      // 🧩 Detect gender
      $gender = str_contains($name, 'girl') ? 'Girls'
        : (str_contains($name, 'boy') ? 'Boys' : 'Mixed');

      \Log::debug("[DRAW] Processing", [
        'draw_id' => $draw->id,
        'draw_name' => $draw->drawName,
        'age_group' => $age,
        'gender' => $gender,
        'drawType_id' => $draw->drawType_id,
        'fixtures_count' => $draw->fixtures->count(),
      ]);

      foreach ($event->regions as $region) {
        $filtered = $draw->fixtures->filter(
          fn($f) => $f->region1 == $region->id || $f->region2 == $region->id
        );

        if ($filtered->isEmpty())
          continue;

        // 🧮 Calculate score for this region
        $points = $this->getScoreFromFixtures($draw->drawType_id, $filtered, $region->id);
        $typeName = $drawTypeMap[$draw->drawType_id] ?? 'Other';
        $p = $points['points'] ?? 0;

        // 🧱 Store points
        if ($gender === 'Mixed') {
          // Split half to Boys, half to Girls
          foreach (['Boys', 'Girls'] as $splitGender) {
            $scoreboard[$age][$splitGender][$region->region_name][$typeName]['points'] =
              ($scoreboard[$age][$splitGender][$region->region_name][$typeName]['points'] ?? 0) + ($p / 2);

            $scoreboard[$age][$splitGender][$region->region_name]['Total']['points'] =
              ($scoreboard[$age][$splitGender][$region->region_name]['Total']['points'] ?? 0) + ($p / 2);
          }
          $assignedTo = 'Boys+Girls (split)';
        } else {
          // Normal gendered draw
          $scoreboard[$age][$gender][$region->region_name][$typeName]['points'] =
            ($scoreboard[$age][$gender][$region->region_name][$typeName]['points'] ?? 0) + $p;

          $scoreboard[$age][$gender][$region->region_name]['Total']['points'] =
            ($scoreboard[$age][$gender][$region->region_name]['Total']['points'] ?? 0) + $p;

          $assignedTo = $gender;
        }

        $debugData[$age][$gender][$region->region_name][$typeName][] = [
          'draw' => $draw->drawName,
          'points' => $p,
        ];

        \Log::debug("[POINTS] Added", [
          'age' => $age,
          'gender' => $gender,
          'region' => $region->region_name,
          'type' => $typeName,
          'points' => $p,
          'assigned_to' => $assignedTo,
          'draw' => $draw->drawName,
        ]);
      }
    }

    // 🧮 Sort and build Overall per age group
    foreach ($scoreboard as $age => &$genderGroups) {
      foreach ($genderGroups as $gender => &$regions) {
        uasort(
          $regions,
          fn($a, $b) =>
          ($b['Total']['points'] ?? 0) <=> ($a['Total']['points'] ?? 0)
        );
      }

      // Combine Boys + Girls into Overall
      $combined = [];
      foreach (['Boys', 'Girls'] as $gender) {
        if (!isset($genderGroups[$gender]))
          continue;

        foreach ($genderGroups[$gender] as $region => $types) {
          foreach ($types as $type => $data) {
            $combined[$region][$type]['points'] =
              ($combined[$region][$type]['points'] ?? 0) + ($data['points'] ?? 0);
          }
        }
      }

      uasort(
        $combined,
        fn($a, $b) =>
        ($b['Total']['points'] ?? 0) <=> ($a['Total']['points'] ?? 0)
      );

      $scoreboard[$age]['Overall'] = $combined;

      \Log::debug("[SUMMARY] Age group processed", [
        'age' => $age,
        'girls_regions' => isset($genderGroups['Girls']) ? count($genderGroups['Girls']) : 0,
        'boys_regions' => isset($genderGroups['Boys']) ? count($genderGroups['Boys']) : 0,
        'overall_regions' => count($combined),
      ]);
    }

    \Log::debug("[SCOREBOARD] DONE");

    return view('backend.scoreboard.teamScoreboard', [
      'event' => $event,
      'scoreboard' => $scoreboard,
      'debugData' => $debugData,
    ]);
  }


  protected function getFilteredEvent($id, $excludeRegionShort = null)
  {
    $query = \App\Models\Event::with([
      'regions:id,region_name,short_name',
      'regions.teams:id,region_id,name',
      'regions.teams.players:id,name,surname',
      'draws.fixtures.teamResults',
      'draws.fixtures.fixturePlayers.team1',
      'draws.fixtures.fixturePlayers.team2',
      'draws.draw_types'
    ]);

    $event = $query->findOrFail($id);

    if ($excludeRegionShort) {
      $excludedRegionIds = $event->regions
        ->filter(function ($r) use ($excludeRegionShort) {
          return str_contains(strtolower($r->short_name), strtolower(trim($excludeRegionShort)));
        })

        ->pluck('id')
        ->values();

      \Log::debug('[SCOREBOARD FILTER]', [
        'exclude' => $excludeRegionShort,
        'matched_region_ids' => $excludedRegionIds,
        'matched_region_names' => $event->regions
          ->whereIn('id', $excludedRegionIds)
          ->pluck('region_name'),
      ]);
      foreach ($event->draws as $draw) {
        $before = $draw->fixtures->count();

        $draw->fixtures = $draw->fixtures->reject(function ($fixture) use ($excludedRegionIds) {
          $r1 = $fixture->region1Name?->id ?? null;
          $r2 = $fixture->region2Name?->id ?? null;
          return $r1 && in_array($r1, $excludedRegionIds->toArray()) ||
            $r2 && in_array($r2, $excludedRegionIds->toArray());
        })->values();

        $after = $draw->fixtures->count();

        \Log::debug('[SCOREBOARD FILTER FIXTURES]', [
          'draw' => $draw->drawName,
          'before' => $before,
          'after' => $after,
          'excludedRegionIds' => $excludedRegionIds->toArray(),
        ]);
      }


      // 🔹 Remove fixtures involving excluded regions
      foreach ($event->draws as $draw) {
        $draw->fixtures = $draw->fixtures->reject(function ($fixture) use ($excludedRegionIds) {
          $r1 = $fixture->region1Name?->id ?? null;
          $r2 = $fixture->region2Name?->id ?? null;
          return $r1 && in_array($r1, $excludedRegionIds->toArray()) ||
            $r2 && in_array($r2, $excludedRegionIds->toArray());
        })->values();
      }

      // 🔹 Remove all teams from excluded regions
      foreach ($event->regions as $region) {
        if ($excludedRegionIds->contains($region->id)) {
          $region->setRelation('teams', collect());
        }
      }
    }

    return $event;
  }

  /**
   * Get winner region id from fixture.
   */
  protected function getWinner($fixture)
  {
    $results = $fixture->teamResults
      ->sortBy('set_nr')
      ->values()
      ->filter(fn($r) => is_numeric($r->team1_score) && is_numeric($r->team2_score));

    if ($results->isEmpty()) {
      return null;
    }

    $team1Sets = $results->filter(fn($r) => (int) $r->team1_score > (int) $r->team2_score)->count();
    $team2Sets = $results->filter(fn($r) => (int) $r->team2_score > (int) $r->team1_score)->count();

    if ($team1Sets === $team2Sets) {
      $last = $results->last();
      if (!$last)
        return null;
      return ((int) $last->team1_score > (int) $last->team2_score) ? $fixture->region1 : $fixture->region2;
    }

    return $team1Sets > $team2Sets ? $fixture->region1 : $fixture->region2;
  }

  /**
   * Get loser region id from fixture.
   */
  protected function getLoser($fixture)
  {
    $winner = $this->getWinner($fixture);
    if (!$winner)
      return null;
    return $winner === $fixture->region1 ? $fixture->region2 : $fixture->region1;
  }

  /**
   * Unified scoring from fixtures (uses winner/loser + detailed scoring).
   */
  protected function getScoreFromFixtures(int $drawTypeId, $fixtures, int $regionId): array
  {
    $points = $wins = $losses = $played = 0;

    foreach ($fixtures as $fixture) {
      // Skip if no valid results
      $hasResults = $fixture->teamResults
        ->filter(fn($r) => is_numeric($r->team1_score) && is_numeric($r->team2_score))
        ->isNotEmpty();
      if (!$hasResults)
        continue;

      $winner = $this->getWinner($fixture);
      $loser = $this->getLoser($fixture);

      if (!$winner || !$loser)
        continue;

      $played++;

      if ($winner == $regionId) {
        $wins++;
        $points += $this->getScoreWinner($drawTypeId, $fixture);
      } elseif ($loser == $regionId) {
        $losses++;
        $points += $this->getScoreLoser($drawTypeId, $fixture);
      }
    }

    return compact('points', 'wins', 'losses', 'played');
  }

  /**
   * Points for winners depending on draw type and result.
   */
  protected function getScoreWinner($drawtype, $fixture)
  {
    $results = $fixture->teamResults
      ->sortBy('set_nr')
      ->values()
      ->filter(fn($r) => is_numeric($r->team1_score) && is_numeric($r->team2_score));

    if ($results->isEmpty())
      return 0;

    switch ((int) $drawtype) {
      case 1: // singles
      case 4: // singles_reverse
        $team1Sets = $results->filter(fn($r) => $r->team1_score > $r->team2_score)->count();
        $team2Sets = $results->filter(fn($r) => $r->team2_score > $r->team1_score)->count();

        if ($team1Sets === 2 && $team2Sets === 0)
          return 3; // 2–0
        if ($team1Sets === 2 && $team2Sets === 1)
          return 2; // 2–1
        if ($team2Sets === 2 && $team1Sets === 0)
          return 3; // 0–2
        if ($team2Sets === 2 && $team1Sets === 1)
          return 2; // 1–2
        return 0;

      case 2: // doubles
      case 3: // mixed
      default:
        return 2; // always 2 for winner
    }
  }

  /**
   * Points for losers depending on draw type and result.
   */
  protected function getScoreLoser($drawtype, $fixture)
  {
    $results = $fixture->teamResults
      ->sortBy('set_nr')
      ->values()
      ->filter(fn($r) => is_numeric($r->team1_score) && is_numeric($r->team2_score));

    if ($results->isEmpty())
      return 0;

    switch ((int) $drawtype) {
      case 1: // singles
      case 4: // singles_reverse
        $team1Sets = $results->filter(fn($r) => $r->team1_score > $r->team2_score)->count();
        $team2Sets = $results->filter(fn($r) => $r->team2_score > $r->team1_score)->count();

        if ($team1Sets === 2 && $team2Sets === 1)
          return 1; // loser took 1 set
        if ($team2Sets === 2 && $team1Sets === 1)
          return 1; // loser took 1 set
        return 0;

      case 2: // doubles
      case 3: // mixed
      default:
        $totalGamesTeam1 = $results->sum('team1_score');
        $totalGamesTeam2 = $results->sum('team2_score');
        $diff = abs($totalGamesTeam1 - $totalGamesTeam2);

        return $diff === 1 ? 1 : 0;
    }
  }
  
}
