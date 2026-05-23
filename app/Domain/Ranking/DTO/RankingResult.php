<?php

namespace App\Domain\Ranking\DTO;

use Illuminate\Support\Collection;

/**
 * The complete output of the ranking calculation pipeline for one ranking list.
 */
final class RankingResult
{
    /**
     * @param  int                    $rankingListId
     * @param  string                 $listName
     * @param  Collection<RankingRow> $rows       Ordered, position-assigned rows
     * @param  array                  $audit      Detailed per-player audit trail
     * @param  string[]               $warnings   Non-fatal warnings from the pipeline
     */
    public function __construct(
        public readonly int        $rankingListId,
        public readonly string     $listName,
        public readonly Collection $rows,
        public readonly array      $audit    = [],
        public readonly array      $warnings = [],
    ) {}
}
