<?php

namespace App\Services\Ranking;

use App\Models\RankType;
use App\Services\Ranking\Strategies\OverbergRankingStrategy;
use RuntimeException;

class RankingEngine
{
  /**
   * Resolve ranking strategy based on rank type code
   */
  public function resolve(string $rankType): RankingStrategy
  {
    return match ($rankType) {

      // Overberg / Platteland logic
      'overberg',
      'platteland',
      'overberg_series' => new OverbergRankingStrategy(),

      // Future strategies go here
      // 'wilson' => new WilsonRankingStrategy(),

      default => throw new RuntimeException(
        "Unknown ranking type [$rankType]"
      ),
    };
  }
}
