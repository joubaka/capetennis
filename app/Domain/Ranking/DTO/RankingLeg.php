<?php

namespace App\Domain\Ranking\DTO;

/**
 * A single player–event scoring leg used as input to the calculation pipeline.
 */
final readonly class RankingLeg
{
    public function __construct(
        public readonly int     $playerId,
        public readonly int     $categoryEventId,
        public readonly int     $position,
        public readonly int     $points,
        public readonly ?string $eventDate,
        public readonly bool    $synthetic = false,
        public readonly ?string $note      = null,
    ) {}

    public function withPoints(int $points): self
    {
        return new self(
            $this->playerId,
            $this->categoryEventId,
            $this->position,
            $points,
            $this->eventDate,
            $this->synthetic,
            $this->note,
        );
    }

    public function withPositionAndPoints(int $position, int $points): self
    {
        return new self(
            $this->playerId,
            $this->categoryEventId,
            $position,
            $points,
            $this->eventDate,
            $this->synthetic,
            $this->note,
        );
    }
}
