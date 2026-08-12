<?php

namespace App\Domain\Ranking\DTO;

/**
 * One ranked player row inside a RankingResult.
 */
final class RankingRow
{
    /**
     * @param int          $playerId
     * @param int          $rankPosition     Final assigned rank (1-based, shared for ties)
     * @param int          $totalPoints      Sum of counting legs
     * @param RankingLeg[] $countingLegs     The best-N legs that contributed to totalPoints
     * @param RankingLeg[] $droppedLegs      Legs excluded by the best-N rule
     * @param int          $wins             Number of position-1 results in counting legs
     * @param int          $bestSingle       Highest single-leg score
     * @param int          $positionsSum     Sum of positions (lower = better, used in tiebreak)
     * @param bool         $autoAward        Whether a synthetic leg was awarded
     * @param array        $tiebreakNotes    Human-readable tiebreak resolution notes
     */
    public function __construct(
        public readonly int   $playerId,
        public int            $rankPosition,
        public readonly int   $totalPoints,
        public readonly array $countingLegs,
        public readonly array $droppedLegs,
        public readonly int   $wins,
        public readonly int   $bestSingle,
        public readonly int   $positionsSum,
        public readonly bool  $autoAward    = false,
        public array          $tiebreakNotes = [],
    ) {}
}
