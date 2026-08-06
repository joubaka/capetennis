<?php

namespace App\Services\Ranking\Strategies;

use App\Models\Series;
use App\Services\Ranking\RankingStrategy;
use Illuminate\Support\Collection;

class OverbergRankingStrategy implements RankingStrategy
{
  public function rank(
    Collection $placements,
    array $pointsMap,
    Series $series,
    int $bestN = 2
  ): array {

    /*
     * Normalize placements
     */
    $placements = $placements
      ->flatten(1)
      ->filter(fn($p) => is_object($p) && isset($p->player_id, $p->event_id));

    /*
     * Group by event for auto-award logic
     */
    $byEvent = $placements->groupBy('event_id');
    $byPlayer = $placements->groupBy('player_id');

    $autoPlayerId = null;
    $missedEventId = null;

    /*
     * Detect auto-award player:
     * - exactly 2 legs
     * - both wins
     * - only when auto_award_rule is enabled on the series
     */
    if ($series->auto_award_rule ?? true) foreach ($byPlayer as $playerId => $legs) {
      if ($legs->count() === 2 && $legs->where('position', 1)->count() === 2) {

        $autoPlayerId = (int) $playerId;

        $playedEvents = $legs->pluck('event_id')->all();
        $missedEventId = $byEvent->keys()
          ->first(fn($eid) => !in_array($eid, $playedEvents));

        break;
      }
    }

    /*
     * Apply AUTO-AWARD bumping
     */
    if ($autoPlayerId && $missedEventId) {

      $original = $byEvent[$missedEventId]
        ->sortBy('position')
        ->values();

      $bumped = collect();

      // 🔹 Inject auto winner at position 1
      $bumped->push((object) [
        'player_id' => $autoPlayerId,
        'event_id' => $missedEventId,
        'position' => 1,
        'is_auto' => true,
      ]);

      // 🔹 Shift everyone else down by 1
      foreach ($original as $row) {
        $bumped->push((object) [
          'player_id' => $row->player_id,
          'event_id' => $row->event_id,
          'position' => $row->position + 1,
          'is_auto' => false,
        ]);
      }

      /*
       * Replace event placements
       */
      $placements = $placements
        ->reject(fn($p) => $p->event_id == $missedEventId)
        ->merge($bumped);
    }

    /*
     * Build ranking rows
     */
    $rows = [];

    foreach ($placements->groupBy('player_id') as $playerId => $playerPlacements) {

      $legs = $playerPlacements
        ->map(function ($p) use ($pointsMap) {
          return [
            'event_id' => (int) $p->event_id,
            'position' => (int) $p->position,
            'points' => (int) ($pointsMap[$p->position] ?? 0),
            'is_auto' => (bool) ($p->is_auto ?? false),
          ];
        })
        ->sortByDesc('points')
        ->values();

      $countedLegs = $legs->take($bestN);
      $bestNSum = $countedLegs->sum('points');
      $nextBest = $legs->get($bestN)['points'] ?? 0;

      if ($bestNSum === 0) {
        continue;
      }

      $annotatedLegs = $legs->map(function ($leg, $i) use ($bestN) {
        return array_merge($leg, [
          'status' => $i < $bestN ? 'counted' : 'dropped',
          'colour' =>
            !empty($leg['is_auto'])
            ? 'yellow'
            : ($i < $bestN ? 'green' : 'red'),
        ]);
      })->values();

      $rows[] = [
        'player_id' => (int) $playerId,
        'total' => (int) $bestNSum,
        'third' => (int) $nextBest,
        'meta' => [
          'legs' => $annotatedLegs,
          'best_two_sum' => $bestNSum,
          'third_best' => $nextBest,
          'auto_award' => $playerId === $autoPlayerId,
        ],
      ];
    }

    /*
     * Final ranking order
     */
    usort(
      $rows,
      fn($a, $b) =>
      [$b['total'], $b['third']]
      <=>
      [$a['total'], $a['third']]
    );


    return $rows;
  }
}
