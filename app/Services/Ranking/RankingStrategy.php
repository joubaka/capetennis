<?php

namespace App\Services\Ranking;

use App\Models\Series;
use Illuminate\Support\Collection;

interface RankingStrategy
{
  public function rank(
    Collection $placements,
    array $pointsMap,
    Series $series
  ): array;
}
