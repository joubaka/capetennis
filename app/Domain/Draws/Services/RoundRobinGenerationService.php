<?php

namespace App\Domain\Draws\Services;

use App\Domain\Draws\Guards\DrawGuard;
use App\Models\Draw;
use App\Models\Fixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RoundRobinGenerationService
 *
 * Canonical service for generating Round Robin fixtures.
 *
 * Responsibilities:
 *   - RR box/group fixture creation via the circle-rotation algorithm
 *   - Serpentine seeding within each group
 *   - BYE injection for odd-player groups
 *   - Idempotent regeneration (clears old RR fixtures first)
 *
 * Does NOT:
 *   - render anything
 *   - calculate standings
 *   - advance playoff brackets
 *   - schedule matches
 */
final class RoundRobinGenerationService
{
    // ------------------------------------------------------------------
    // PUBLIC API
    // ------------------------------------------------------------------

    /**
     * (Re)generate all RR fixtures for the draw.
     * Deletes existing RR fixtures before creating new ones.
     *
     * @throws \RuntimeException if the draw is locked.
     */
    public function generate(Draw $draw): void
    {
        DrawGuard::requireMutable($draw, 'regenerate RR fixtures for');

        $draw->loadMissing('groups.groupRegistrations');

        DB::transaction(function () use ($draw) {
            // Remove existing RR fixtures only — leave MAIN/PLATE untouched.
            $draw->drawFixtures()->where('stage', 'RR')->delete();

            $matchNr = 1;
            foreach ($draw->groups as $group) {
                $matchNr = $this->generateGroupFixtures($draw, $group, $matchNr);
            }
        });

        Log::info('[RoundRobinGenerationService] Generated RR fixtures', [
            'draw_id' => $draw->id,
        ]);
    }

    // ------------------------------------------------------------------
    // PRIVATE HELPERS
    // ------------------------------------------------------------------

    /**
     * Generate all RR fixtures for one group using the circle-rotation algorithm.
     * Returns the next available match number.
     */
    private function generateGroupFixtures(Draw $draw, $group, int $matchNr): int
    {
        $registrations = $group->groupRegistrations
            ->sortBy(fn($r) => $r->seed ?? PHP_INT_MAX)
            ->values();

        Log::debug('[RR] Group', [
            'group_id' => $group->id,
            'players'  => $registrations->count(),
        ]);

        $ids = $registrations->pluck('registration_id')->all();
        $n   = count($ids);

        if ($n < 2) {
            Log::warning('[RR] Group skipped — fewer than 2 players', ['group_id' => $group->id]);
            return $matchNr;
        }

        // Inject a virtual BYE (null) for odd-player groups.
        if ($n % 2 === 1) {
            $ids[] = null;
            $n++;
        }

        $rounds = $n - 1;
        $half   = $n / 2;

        // Circle algorithm: fix first player, rotate the rest.
        $fixed    = $ids[0];
        $rotation = array_slice($ids, 1);

        for ($round = 1; $round <= $rounds; $round++) {
            $players = array_merge([$fixed], $rotation);

            for ($i = 0; $i < $half; $i++) {
                $home = $players[$i];
                $away = $players[$n - 1 - $i];

                // Skip BYE slots — BYE advancement is handled separately.
                if ($home === null || $away === null) {
                    continue;
                }

                Fixture::create([
                    'draw_id'          => $draw->id,
                    'draw_group_id'    => $group->id,
                    'stage'            => 'RR',
                    'round'            => $round,
                    'match_nr'         => $matchNr++,
                    'registration1_id' => $home,
                    'registration2_id' => $away,
                    'match_status'     => 0,
                ]);
            }

            // Rotate clockwise: move last element to front of rotation.
            array_unshift($rotation, array_pop($rotation));
        }

        return $matchNr;
    }
}
