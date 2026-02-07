<?php

namespace App\Classes;

use App\Models\CategoryEvent;
use App\Models\RankingScores;
use Illuminate\Support\Facades\DB;
use App\Models\Series;

class Rank
{
  protected int $seriesId;

  public function __construct(int $seriesId)
  {
    $this->seriesId = $seriesId;
  }

  /**
   * Calculates ranking scores per ranking list for the given series.
   * - rankType 2: auto-1st if 2 wins, bestN rule, tiebreak rules
   * - rankType 3: same rules but NO auto-1st applied
   */
  public function test(Series $series, bool $withDebug = false): array
  {
    $bestN = (int) ($series->best_num_of_scores ?: 2);
    $seriesId = $series->id;
   
    $rankType = (int) ($series->rank_type ?? 2);
 
    $posToPoints = DB::table('points')
      ->where('series_id', $seriesId)
      ->pluck('score', 'position')
      ->toArray();

    $report = [];

    DB::transaction(function () use ($series, $bestN, $seriesId, $rankType, $posToPoints, $withDebug, &$report) {
      foreach ($series->ranking_lists as $list) {
        $catEventIds = DB::table('ranking_list_category_events')
          ->where('ranking_list_id', $list->id)
          ->pluck('category_event_id');

        $catEvents = CategoryEvent::with(['event:id,name', 'category:id,name'])
          ->whereIn('id', $catEventIds)
          ->get()
          ->map(fn($ce) => [
            'id' => $ce->id,
            'event' => $ce->event?->name,
            'category' => $ce->category?->name,
          ])
          ->values();

        $entry = [
          'list_id' => $list->id,
          'list_name' => $list->category?->name ?? "List #{$list->id}",
          'categories' => $catEvents,
          'players_scored' => 0,
          'events_count' => $catEventIds->count(),
          'status' => 'ok',
          'notes' => [],
        ];

        if ($catEventIds->isEmpty()) {
          RankingScores::where('ranking_list_id', $list->id)->delete();
          $entry['status'] = 'skipped';
          $entry['notes'][] = 'No categories attached.';
          $report[] = $entry;
          continue;
        }

        // fetch all positions
        $scoredRows = DB::table('positions as p')
          ->join('ranking_list_category_events as rlce', 'rlce.category_event_id', '=', 'p.category_event_id')
          ->join('points as pts', function ($j) use ($seriesId) {
            $j->on('pts.position', '=', 'p.position')
              ->where('pts.series_id', '=', $seriesId);
          })
          ->where('rlce.ranking_list_id', $list->id)
          ->where('pts.score', '>', 0)
          ->get([
            'p.player_id',
            'p.category_event_id',
            'p.position',
            'pts.score',
          ]);

        if ($scoredRows->isEmpty()) {
          RankingScores::where('ranking_list_id', $list->id)->delete();
          $entry['status'] = 'skipped';
          $entry['notes'][] = 'No results.';
          $report[] = $entry;
          continue;
        }

        // ------------------------
        // AUTO-1ST RULE (only if rankType == 2)
        // ------------------------
        $groupedByEvent = collect($scoredRows)->groupBy('category_event_id');

        if ($rankType === 2) {
          $playerStats = [];

          foreach ($groupedByEvent as $rows) {
            foreach ($rows as $row) {
              $pid = $row->player_id;
              $playerStats[$pid]['played'] = ($playerStats[$pid]['played'] ?? 0) + 1;
              if ($row->position == 1) {
                $playerStats[$pid]['wins'] = ($playerStats[$pid]['wins'] ?? 0) + 1;
              }
            }
          }

          $winnerId = null;
          foreach ($playerStats as $pid => $stats) {
            if (($stats['wins'] ?? 0) >= 2 && ($stats['played'] ?? 0) == ($stats['wins'] ?? 0)) {
              $winnerId = $pid;
              break;
            }
          }

          if ($winnerId) {
            foreach ($groupedByEvent as $catEventId => $rows) {
              $winnerPlayed = $rows->contains(fn($r) => $r->player_id == $winnerId);

              if (!$winnerPlayed) {
                $rows->push((object) [
                  'player_id' => $winnerId,
                  'category_event_id' => $catEventId,
                  'position' => 1,
                  'score' => $posToPoints[1] ?? 0,
                ]);

                $others = $rows->reject(fn($r) => $r->player_id == $winnerId)
                  ->sortBy('position')
                  ->values();

                foreach ($others as $idx => $row) {
                  $row->position = $idx + 2;
                  $row->score = $posToPoints[$row->position] ?? 0;
                }

                $groupedByEvent[$catEventId] = $rows;
              }
            }

            $scoredRows = $groupedByEvent->flatten();
            $entry['notes'][] = "Applied auto-1st override to player {$winnerId}";
          }
        }
        // if rankType == 3 → we skip auto-1st completely

        // ------------------------
        // Group scores per player
        // ------------------------
        $byPlayer = [];
        $eventLegs = [];
        foreach ($scoredRows as $row) {
          $byPlayer[$row->player_id][] = (int) $row->score;
          $eventLegs[$row->player_id][$row->category_event_id] = [
            'event_id' => $row->category_event_id,
            'position' => $row->position,
            'points' => $row->score,
          ];
        }

        // Clean old scores + legs
        $oldIds = RankingScores::where('ranking_list_id', $list->id)->pluck('id');
        DB::table('ranking_score_legs')->whereIn('ranking_score_id', $oldIds)->delete();
        RankingScores::where('ranking_list_id', $list->id)->delete();

        $now = now();
        $legRows = [];

        // First pass: calculate totals + tiebreak stat
        $tempTotals = [];
        foreach ($byPlayer as $playerId => $scores) {
          rsort($scores);
          $top = array_slice($scores, 0, $bestN);
          $tiebreak = $scores[$bestN] ?? 0;

          $tempTotals[$playerId] = [
            'total' => array_sum($top),
            'tiebreak' => $tiebreak,
            'scores' => $scores,
          ];
        }

        // Second pass: resolve ties with head-to-head or bump
        $adjustments = [];
        $playerIds = array_keys($tempTotals);

        for ($i = 0; $i < count($playerIds); $i++) {
          for ($j = $i + 1; $j < count($playerIds); $j++) {
            $pidA = $playerIds[$i];
            $pidB = $playerIds[$j];

            $a = $tempTotals[$pidA];
            $b = $tempTotals[$pidB];

            if ($a['total'] === $b['total']) {
              // Head-to-head if both played 2 events
              if (count($eventLegs[$pidA]) === 2 && count($eventLegs[$pidB]) === 2) {
                $common = collect($eventLegs[$pidA])->keys()
                  ->intersect(collect($eventLegs[$pidB])->keys());

                if ($common->count() === 1) {
                  $ceId = $common->first();
                  $aPos = $eventLegs[$pidA][$ceId]['position'];
                  $bPos = $eventLegs[$pidB][$ceId]['position'];

                  if ($aPos < $bPos) {
                    $adjustments[$pidA] = ($adjustments[$pidA] ?? 0) + 1;
                    $entry['notes'][] = "Head-to-head bump: Player {$pidA} over Player {$pidB}";
                  } elseif ($bPos < $aPos) {
                    $adjustments[$pidB] = ($adjustments[$pidB] ?? 0) + 1;
                    $entry['notes'][] = "Head-to-head bump: Player {$pidB} over Player {$pidA}";
                  }
                  continue;
                }
              }

              // Otherwise: use 3rd best score
              if ($a['tiebreak'] > $b['tiebreak']) {
                $adjustments[$pidA] = ($adjustments[$pidA] ?? 0) + 1;
                $entry['notes'][] = "Tiebreak bump: Player {$pidA} over Player {$pidB}";
              } elseif ($b['tiebreak'] > $a['tiebreak']) {
                $adjustments[$pidB] = ($adjustments[$pidB] ?? 0) + 1;
                $entry['notes'][] = "Tiebreak bump: Player {$pidB} over Player {$pidA}";
              }
            }
          }
        }

        // Third pass: insert rows with bump
        foreach ($tempTotals as $playerId => $vals) {
          $total = $vals['total'] + ($adjustments[$playerId] ?? 0);

          $rankingScoreId = DB::table('ranking_scores')->insertGetId([
            'ranking_list_id' => $list->id,
            'player_id' => $playerId,
            'num_events' => count($vals['scores']),
            'total_points' => $total,
            'created_at' => $now,
            'updated_at' => $now,
          ]);

          $entry['notes'][] = "Player {$playerId}: total {$vals['total']} + bump " . ($adjustments[$playerId] ?? 0);

          foreach ($eventLegs[$playerId] as $ceId => $leg) {
            $eventRow = $catEvents->firstWhere('id', $ceId);
            $eventName = $eventRow['event'] ?? "Event $ceId";

            $legRows[] = [
              'ranking_score_id' => $rankingScoreId,
              'player_id' => $playerId,
              'category_event_id' => $ceId,
              'event_name' => $eventName,
              'position' => $leg['position'],
              'points' => $leg['points'],
              'created_at' => $now,
              'updated_at' => $now,
            ];
          }
        }

        if (!empty($legRows)) {
          DB::table('ranking_score_legs')->insert($legRows);
        }

        $entry['players_scored'] = count($byPlayer);
        $report[] = $entry;
      }
    });

    return $report;
  }
}
