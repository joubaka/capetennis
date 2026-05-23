<?php

namespace App\Domain\Draws\Services;

use App\Domain\Draws\Guards\DrawGuard;
use App\Models\Draw;
use App\Models\Fixture;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FeedInGenerationService
 *
 * Canonical service for consolation/feed-in and placement bracket logic.
 *
 * Responsibilities:
 *   - Map bracket losers into consolation fixtures
 *   - Create placement match structures (3rd/4th, 5th/6th, 7th/8th, …)
 *   - Loser routing from MAIN bracket into PLATE/CONS stages
 *   - Auto-resolving BYE walkovers in consolation chains
 *
 * Does NOT:
 *   - calculate standings
 *   - score fixtures
 *   - render anything
 *
 * NOTE: The current production feed-in logic is embedded in DrawService /
 * RoundRobinController. This canonical service is the target for gradual
 * migration. Legacy paths remain operational until parity is confirmed.
 */
final class FeedInGenerationService
{
    public function __construct(
        private readonly ByeAdvancementService $byeService,
    ) {}

    // ------------------------------------------------------------------
    // PUBLIC API
    // ------------------------------------------------------------------

    /**
     * Route all first-round losers from the given MAIN fixtures into the
     * supplied consolation fixture map.
     *
     * $consolationMap format:
     *   [
     *     ['source' => Fixture, 'target' => Fixture, 'slot' => 1|2],
     *     …
     *   ]
     *
     * @throws \RuntimeException if the draw is locked.
     */
    public function routeLosers(Draw $draw, array $consolationMap): void
    {
        DrawGuard::requireMutable($draw, 'route losers for');

        DB::transaction(function () use ($consolationMap) {
            foreach ($consolationMap as $mapping) {
                $source = $mapping['source'];  // Fixture that produced a loser
                $target = $mapping['target'];  // Consolation fixture to receive loser
                $slot   = $mapping['slot'];    // 1 or 2

                $loserId = $this->resolveLoser($source);
                if ($loserId === null) continue;

                $field = $slot === 1 ? 'registration1_id' : 'registration2_id';

                if (is_null($target->{$field}) || $target->{$field} === $loserId) {
                    $target->{$field} = $loserId;
                    $target->save();

                    // Link the source fixture to the consolation destination.
                    if (! $source->loser_parent_fixture_id) {
                        $source->loser_parent_fixture_id = $target->id;
                        $source->save();
                    }

                    Log::info('[FeedIn] Loser routed', [
                        'source_id' => $source->id,
                        'target_id' => $target->id,
                        'loser_id'  => $loserId,
                        'slot'      => $slot,
                    ]);
                } else {
                    Log::warning('[FeedIn] Slot already occupied — skipping', [
                        'target_id' => $target->id,
                        'slot'      => $slot,
                        'existing'  => $target->{$field},
                        'attempted' => $loserId,
                    ]);
                }
            }
        });

        // After routing resolve any immediate walkovers.
        $this->byeService->advance($draw);
    }

    /**
     * Create a single placement match (e.g. 3rd/4th, 5th/6th) and link
     * the two source fixtures' losers into it.
     *
     * @param  Fixture  $source1  First feeder fixture (loser goes to slot 1)
     * @param  Fixture  $source2  Second feeder fixture (loser goes to slot 2)
     * @param  array    $attrs    Additional attributes for Fixture::create()
     *
     * @throws \RuntimeException if the draw is locked.
     */
    public function createPlacementMatch(
        Draw    $draw,
        Fixture $source1,
        Fixture $source2,
        array   $attrs = [],
    ): Fixture {
        DrawGuard::requireMutable($draw, 'create placement match for');

        return DB::transaction(function () use ($draw, $source1, $source2, $attrs) {
            $loser1 = $this->resolveLoser($source1);
            $loser2 = $this->resolveLoser($source2);

            $placement = Fixture::create(array_merge([
                'draw_id'      => $draw->id,
                'match_status' => 0,
            ], $attrs, array_filter([
                'registration1_id' => $loser1,
                'registration2_id' => $loser2,
            ], fn($v) => ! is_null($v))));

            // Link sources to the placement match.
            $source1->loser_parent_fixture_id = $placement->id;
            $source2->loser_parent_fixture_id = $placement->id;
            $source1->save();
            $source2->save();

            Log::info('[FeedIn] Placement match created', [
                'placement_id' => $placement->id,
                'loser1'       => $loser1,
                'loser2'       => $loser2,
            ]);

            return $placement;
        });
    }

    // ------------------------------------------------------------------
    // PRIVATE HELPERS
    // ------------------------------------------------------------------

    private function resolveLoser(Fixture $fixture): ?int
    {
        $winner = $fixture->winner_registration;
        if (! $winner) return null;
        return $winner === $fixture->registration1_id
            ? $fixture->registration2_id
            : $fixture->registration1_id;
    }
}
