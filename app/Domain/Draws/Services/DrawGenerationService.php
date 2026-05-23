<?php

namespace App\Domain\Draws\Services;

use App\Domain\Draws\Guards\DrawGuard;
use App\Models\Draw;
use Illuminate\Support\Facades\Log;

/**
 * DrawGenerationService
 *
 * Top-level orchestrator for draw generation.
 * Delegates to specialised services; holds no generation logic itself.
 *
 * Sequence for a full draw:
 *   1. generateRoundRobin()   — create all RR fixtures
 *   2. (play RR matches)
 *   3. generateMainPlayoff()  — seed and create MAIN bracket
 *   4. generatePlatePlayoff() — seed and create PLATE bracket
 *   5. advanceByes()          — resolve BYE slots across all brackets
 */
final class DrawGenerationService
{
    public function __construct(
        private readonly RoundRobinGenerationService $rrService,
        private readonly PlayoffGenerationService    $playoffService,
        private readonly StandingsService            $standingsService,
        private readonly ByeAdvancementService       $byeService,
    ) {}

    // ------------------------------------------------------------------
    // PUBLIC API
    // ------------------------------------------------------------------

    /**
     * Generate (or regenerate) all RR fixtures for the draw.
     *
     * @throws \RuntimeException if the draw is locked.
     */
    public function generateRoundRobin(Draw $draw): void
    {
        DrawGuard::requireMutable($draw, 'generate RR for');
        $this->rrService->generate($draw);
        Log::info('[DrawGeneration] RR generation complete', ['draw_id' => $draw->id]);
    }

    /**
     * Generate the MAIN playoff bracket from current RR standings.
     * Seeds are the group winners (position 1 per group).
     *
     * @param  array  $seeds  Associative seed map, e.g. ['A1' => $regId, 'B1' => $regId, …]
     */
    public function generateMainPlayoff(Draw $draw, array $seeds): array
    {
        DrawGuard::requireMutable($draw, 'generate main playoff for');
        $fixtures = $this->playoffService->createMainBracket($draw, $seeds);
        $this->byeService->advance($draw);
        Log::info('[DrawGeneration] Main playoff generation complete', ['draw_id' => $draw->id]);
        return $fixtures;
    }

    /**
     * Generate the PLATE playoff bracket from current RR standings.
     * Seeds are 2nd and 3rd place finishers per group.
     *
     * @param  array  $seeds  Associative seed map, e.g. ['A2' => $regId, 'D3' => $regId, …]
     */
    public function generatePlatePlayoff(Draw $draw, array $seeds): array
    {
        DrawGuard::requireMutable($draw, 'generate plate playoff for');
        $fixtures = $this->playoffService->createPlateBracket($draw, $seeds);
        $this->byeService->advance($draw);
        Log::info('[DrawGeneration] Plate playoff generation complete', ['draw_id' => $draw->id]);
        return $fixtures;
    }

    /**
     * Resolve all BYE/walkover slots across all bracket stages.
     */
    public function advanceByes(Draw $draw): int
    {
        return $this->byeService->advance($draw);
    }

    /**
     * Build standings for the draw (delegates to StandingsService).
     *
     * @return array<int, array>  keyed by group_id
     */
    public function standings(Draw $draw): array
    {
        return $this->standingsService->forDraw($draw);
    }
}
