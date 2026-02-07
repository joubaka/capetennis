<?php

namespace App\Services;

use App\Models\Player;
use App\Models\Registration;
use App\Models\Series;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\Point;
use App\Support\DebugTrace;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SeriesRanker
{
  /**
   * Compute rankings for a series.
   *
   * @param Series $series
   * @param array{bestN?:int,dryRun?:bool,debug?:bool} $options
   * @return array
   */
  public function compute(Series $series, array $options = []): array
  {
  
    $bestN = (int) ($options['bestN'] ?? ($series->best_num_of_scores ?: 9999));
    $dry = (bool) ($options['dryRun'] ?? false);
    $dbg = new DebugTrace((bool) ($options['debug'] ?? false));

    $dbg->step('start', [
      'series_id' => $series->id,
      'bestN' => $bestN,
      'dryRun' => $dry
    ]);

    // Position → points
    $posToScore = Point::where('series_id', $series->id)
      ->pluck('score', 'position');

    if ($posToScore->isEmpty()) {
      $dbg->warn('No points mapping found for series.');
    }

    $dbg->info('Loaded points map', [
      'positions' => $posToScore->keys()->values(),
      'count' => $posToScore->count()
    ]);

    $report = ['lists' => [], 'debug' => []];

    DB::transaction(function () use ($series, $bestN, $posToScore, $dry, $dbg, &$report) {
      foreach ($series->ranking_lists as $list) {
        $dbg->step('list.begin', [
          'ranking_list_id' => $list->id,
          'name' => $list->name
        ]);

        $catEventIds = DB::table('ranking_list_category_events')
          ->where('ranking_list_id', $list->id)
          ->pluck('category_event_id');

        // Load CategoryEvents with related Event + Category, ordered by events.start_date
        $catEvents = CategoryEvent::query()
          ->with(['event:id,name,start_date', 'category:id,name'])
          ->whereIn('id', $catEventIds)
          ->orderBy(
            Event::select('start_date')
              ->whereColumn('events.id', 'category_events.event_id')
          )
          ->get();

        $dbg->info('Loaded category events', [
          'count' => $catEvents->count(),
          'ids' => $catEvents->pluck('id')->values()
        ]);

        // Collect results
        $perPlayer = $this->collectResults($catEvents, $posToScore, $dbg);
      
        // Apply 2-of-3 rule
        $this->applyTwoOfThreeRule($catEvents, $perPlayer, $posToScore, $dbg);

        // Reduce to best N
        $rankRows = $this->reduceToBestN($perPlayer, $bestN, $dbg);

        // Apply tie-break
        $rankRows = $this->applyTwoWayTiebreak($rankRows, $perPlayer, $dbg);

        // Save or skip
        if ($dry) {
          $dbg->info('Dry run: skipping persist', ['rows' => $rankRows->count()]);
        } else {
          $this->persistRanking($list, $rankRows, $dbg);
        }

        // Build feedback
        $feedback = $this->buildFeedback($catEvents, $perPlayer, $rankRows, $bestN);

        $report['lists'][] = [
          'ranking_list_id' => $list->id,
          'list_name' => $list->name,
          'categories' => $catEvents->map(fn($ce) => [
            'id' => $ce->id,
            'label' => "{$ce->event->name} – {$ce->category->name}",
            'date' => optional($ce->event)->start_date,
          ]),
          'rows' => $rankRows->values(),
          'feedback' => $feedback
        ];

        $dbg->step('list.end', ['ranking_list_id' => $list->id]);
      }
    });

    $report['debug'] = $dbg->dump();
    return $report;
  }

  /**
   * Collect results from positions table
   */
  protected function collectResults(Collection $catEvents, Collection $posToScore, DebugTrace $dbg): array
  {
    $dbg->step('collect.start');
    $perPlayer = [];
    $missingPositions = [];

    foreach ($catEvents as $ce) {
      $results = $ce->positions()
        ->select('player_id', 'position')
        ->get();

      if ($results->isEmpty()) {
        $dbg->warn('No results for category event', ['category_event_id' => $ce->id]);
      }

      foreach ($results as $r) {
        $points = (int) ($posToScore[$r->position] ?? 0);
        if (!isset($posToScore[$r->position])) {
          $missingPositions[$r->position] = true;
        }
        $perPlayer[$r->player_id][] = [
          'category_event_id' => $ce->id,
          'position' => (int) $r->position,
          'points' => $points,
          'date' => optional($ce->event)->start_date,
        ];
      }
    }

    if (!empty($missingPositions)) {
      $dbg->warn('Positions found without point mapping', [
        'positions' => array_keys($missingPositions)
      ]);
    }

    $dbg->info('Collected results', [
      'players' => count($perPlayer),
      'legs_total' => array_sum(array_map('count', $perPlayer))
    ]);
    $dbg->step('collect.end');

    return $perPlayer;
  }

  protected function applyTwoOfThreeRule(Collection $catEvents, array &$perPlayer, Collection $posToScore, DebugTrace $dbg): void
  {
    if ($catEvents->count() !== 3) {
      $dbg->info('2-of-3 rule skipped: list has not exactly 3 legs.', [
        'legs' => $catEvents->count()
      ]);
      return;
    }

    $dbg->step('two_of_three.start');

    $ceIds = $catEvents->pluck('id')->all();
    $firstPts = (int) ($posToScore[1] ?? 0);
    $capPts = min((int) ($posToScore[2] ?? 800), $firstPts);

    $wins = [];
    $played = [];
    $winnersByCE = [];

    foreach ($ceIds as $ceId) {
      $winner = null;
      foreach ($perPlayer as $pid => $rows) {
        foreach ($rows as $row) {
          if ($row['category_event_id'] === $ceId && $row['position'] === 1) {
            $winner = $pid;
            break 2;
          }
        }
      }
      if ($winner !== null) {
        $winnersByCE[$ceId] = $winner;
        $wins[$winner] = ($wins[$winner] ?? 0) + 1;
      }
      foreach ($perPlayer as $pid => $rows) {
        foreach ($rows as $row) {
          if ($row['category_event_id'] === $ceId) {
            $played[$pid][$ceId] = true;
          }
        }
      }
    }

    $clinched = array_keys(array_filter($wins, fn($w) => $w >= 2));
    if (empty($clinched)) {
      $dbg->info('No player clinched 2 legs. Rule not applied.');
      $dbg->step('two_of_three.end');
      return;
    }

    foreach ($clinched as $pid) {
      foreach ($ceIds as $ceId) {
        if (!isset($played[$pid][$ceId])) {
          // Award synthetic 1st
          $perPlayer[$pid][] = [
            'category_event_id' => $ceId,
            'position' => 1,
            'points' => $firstPts,
            'date' => optional($catEvents->firstWhere('id', $ceId)->event)->start_date,
            'synthetic' => true,
            'note' => '2-of-3 clinched rule',
          ];
          $dbg->info('Awarded synthetic 1st for missed leg', [
            'player_id' => $pid,
            'category_event_id' => $ceId,
            'points' => $firstPts
          ]);

          // Cap actual winner if different
          if (isset($winnersByCE[$ceId]) && $winnersByCE[$ceId] !== $pid) {
            $winnerId = $winnersByCE[$ceId];
            foreach ($perPlayer[$winnerId] as &$row) {
              if ($row['category_event_id'] === $ceId && $row['position'] === 1) {
                $row['points'] = $capPts;
                $row['note'] = 'capped due to 2-of-3 clinched rule';
                $dbg->warn('Capped event winner points', [
                  'winner_id' => $winnerId,
                  'category_event_id' => $ceId,
                  'capped_to' => $capPts
                ]);
                break;
              }
            }
            unset($row);
          }
        }
      }
    }

    $dbg->step('two_of_three.end');
  }

  protected function reduceToBestN(array $perPlayer, int $bestN, DebugTrace $dbg): Collection
  {
    $dbg->step('reduce.start', ['bestN' => $bestN]);

    $rows = collect();
    foreach ($perPlayer as $playerId => $legs) {
      usort($legs, function ($a, $b) {
        if ($a['points'] === $b['points']) {
          return strcmp((string) $a['date'], (string) $b['date']);
        }
        return $b['points'] <=> $a['points'];
      });

      $counting = array_slice($legs, 0, $bestN);
      $non = array_slice($legs, $bestN);

      $rows->push([
        'player_id' => $playerId,
        'total_points' => array_sum(array_column($counting, 'points')),
        'counting_legs' => $counting,
        'other_legs' => $non,
        'wins' => count(array_filter($legs, fn($r) => $r['position'] === 1)),
        'best_single' => empty($legs) ? 0 : max(array_column($legs, 'points')),
        'positions_sum' => array_sum(array_column($legs, 'position')),
      ]);
    }

    $dbg->info('Reduced to best N', ['players' => $rows->count()]);
    $dbg->step('reduce.end');

    return $rows->sortBy([['total_points', 'desc']])->values();
  }

  protected function applyTwoWayTiebreak(Collection $rankRows, array $perPlayer, DebugTrace $dbg): Collection
  {
    $dbg->step('tiebreak.start');

    $sorted = $rankRows->groupBy('total_points')->flatMap(function ($group) use ($perPlayer, $dbg) {
      if ($group->count() <= 1)
        return $group;

      $dbg->info('Resolving tie group', [
        'total_points' => $group->first()['total_points'],
        'player_ids' => $group->pluck('player_id')->values(),
      ]);

      return $group->sort(function ($a, $b) use ($perPlayer, $dbg) {
        // 1) most wins
        if ($a['wins'] !== $b['wins']) {
          return $b['wins'] <=> $a['wins'];
        }
        // 2) best single-leg score
        if ($a['best_single'] !== $b['best_single']) {
          return $b['best_single'] <=> $a['best_single'];
        }
        // 3) lowest sum positions
        if ($a['positions_sum'] !== $b['positions_sum']) {
          return $a['positions_sum'] <=> $b['positions_sum'];
        }
        // 4) earliest winning date
        $aWin = $this->earliestWinDate($perPlayer[$a['player_id']] ?? []);
        $bWin = $this->earliestWinDate($perPlayer[$b['player_id']] ?? []);
        if ($aWin !== $bWin) {
          if ($aWin === null)
            return 1;
          if ($bWin === null)
            return -1;
          return strcmp($aWin, $bWin);
        }
        return 0;
      })->values();
    })->values();

    $dbg->step('tiebreak.end');
    return $sorted;
  }

  protected function earliestWinDate(array $legs): ?string
  {
    $wins = array_filter($legs, fn($r) => $r['position'] === 1);
    if (empty($wins))
      return null;
    usort($wins, fn($x, $y) => strcmp((string) $x['date'], (string) $y['date']));
    return (string) $wins[0]['date'];
  }

  protected function persistRanking($rankingList, Collection $rows, DebugTrace $dbg): void
  {
    $dbg->step('persist.start', [
      'ranking_list_id' => $rankingList->id,
      'rows' => $rows->count()
    ]);
    // Persist logic here...
    $dbg->step('persist.end', ['ranking_list_id' => $rankingList->id]);
  }

  protected function buildFeedback(Collection $catEvents, array $perPlayer, Collection $rankRows, int $bestN): array
  {
    return [
      'summary' => [
        'legs' => $catEvents->count(),
        'players' => count($perPlayer),
        'bestN' => $bestN,
        'countingNote' => "Top {$bestN} results per player were counted.",
      ],
      'emptyEvents' => $catEvents->filter(
        fn($ce) => empty(array_filter(
          $perPlayer,
          fn($rows) => in_array($ce->id, array_column($rows, 'category_event_id'))
        ))
      )->map(fn($ce) => ['category_event_id' => $ce->id])->values(),

      'topPreview' => $rankRows->take(10)->map(function ($r, $i) use ($perPlayer) {
        $player = Player::find($r['player_id']);

        return [
          'rank' => $i + 1,
          'player' => $player?->fullName ?? 'Unknown',
          'points' => $r['total_points'],

          // ✅ only Best-N legs
          'legs' => collect($r['counting_legs'])->map(fn($x) => [
            'ce' => $x['category_event_id'],
            'pos' => $x['position'],
            'pts' => $x['points'],
            'synthetic' => isset($x['synthetic']) ? true : false,
          ])->values(),

          // ✅ all events this player played
          'allLegs' => collect($perPlayer[$r['player_id']] ?? [])->map(fn($x) => [
            'ce' => $x['category_event_id'],
            'pos' => $x['position'],
            'pts' => $x['points'],
            'synthetic' => isset($x['synthetic']) ? true : false,
          ])->values(),
        ];
      })->values(),
    ];
  }

}
