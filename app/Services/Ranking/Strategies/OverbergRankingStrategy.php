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
    Series $series
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
     */
    foreach ($byPlayer as $playerId => $legs) {
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

      $bestTwoSum = $legs->take(2)->sum('points');
      $thirdBest = $legs->get(2)['points'] ?? 0;

      if ($bestTwoSum === 0) {
        continue;
      }

      $annotatedLegs = $legs->map(function ($leg, $i) {
        return array_merge($leg, [
          'status' => $i < 2 ? 'counted' : 'dropped',
          'colour' =>
            !empty($leg['is_auto'])
            ? 'yellow'
            : ($i < 2 ? 'green' : 'red'),
        ]);
      })->values();

      $rows[] = [
        'player_id' => (int) $playerId,
        'total' => (int) $bestTwoSum,
        'third' => (int) $thirdBest,
        'meta' => [
          'legs' => $annotatedLegs,
          'best_two_sum' => $bestTwoSum,
          'third_best' => $thirdBest,
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
