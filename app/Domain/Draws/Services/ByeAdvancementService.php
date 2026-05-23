<?php

namespace App\Domain\Draws\Services;

use App\Domain\Draws\Guards\DrawGuard;
use App\Models\Draw;
use App\Models\Fixture;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * ByeAdvancementService
 *
 * Canonical service for all BYE/walkover advancement in bracket draws.
 *
 * Responsibilities:
 *   - Detect genuine BYE slots (one side empty, one side occupied)
 *   - Advance lone player to next bracket round
 *   - Handle consolation/loser-bracket walkovers
 *   - Cascade consolation walkovers into their own parents
 *   - Idempotent: will not overwrite an occupied slot with a different player
 *
 * Does NOT:
 *   - write scores
 *   - calculate standings
 *   - render anything
 */
final class ByeAdvancementService
{
    /** Stages checked during BYE advancement. */
    private const BRACKET_STAGES = ['MAIN', 'PLATE', 'CONS', 'BOWL', 'SHIELD', 'SPOON'];

    // ------------------------------------------------------------------
    // PUBLIC API
    // ------------------------------------------------------------------

    /**
     * Process all BYE slots in every bracket stage for the given draw.
     * Round-by-round so early rounds are resolved before later rounds.
     *
     * @throws \RuntimeException if the draw is locked.
     */
    public function advance(Draw $draw): int
    {
        DrawGuard::requireMutable($draw, 'advance BYEs for');

        $stages = $this->allStages($draw);

        $fixtures = Fixture::where('draw_id', $draw->id)
            ->whereIn('stage', $stages)
            ->orderBy('round')
            ->orderBy('match_nr')
            ->get();

        $maxRound     = $fixtures->max('round') ?? 0;
        $totalAdvanced = 0;

        for ($round = 1; $round <= $maxRound; $round++) {
            $totalAdvanced += $this->processRound($round, $fixtures);
        }

        Log::info('[ByeAdvancement] Completed', [
            'draw_id'       => $draw->id,
            'total_advanced' => $totalAdvanced,
        ]);

        return $totalAdvanced;
    }

    // ------------------------------------------------------------------
    // PRIVATE HELPERS
    // ------------------------------------------------------------------

    /** Process one round and return how many BYEs were advanced. */
    private function processRound(int $round, Collection $all): int
    {
        $advanced = 0;

        foreach ($all->where('round', $round) as $fx) {
            $hasReg1 = ! is_null($fx->registration1_id);
            $hasReg2 = ! is_null($fx->registration2_id);

            // Both present → real match; both absent → double-bye; skip.
            if ($hasReg1 === $hasReg2) continue;

            // Round 2+ sanity check: both feeder children must be done
            if ($round > 1 && ! $this->allFeedersDone($fx, $all)) {
                continue;
            }

            $winner = $hasReg1 ? $fx->registration1_id : $fx->registration2_id;
            $fx->winner_registration = $winner;
            $fx->save();

            Log::info('[ByeAdvancement] Bye advanced', [
                'fixture_id' => $fx->id,
                'round'      => $round,
                'winner_id'  => $winner,
            ]);

            // Advance winner to parent fixture.
            if ($fx->parent_fixture_id) {
                $parent = $all->firstWhere('id', $fx->parent_fixture_id)
                    ?? Fixture::find($fx->parent_fixture_id);

                if ($parent) {
                    $slot = $this->childSlot($fx, $all, 'parent_fixture_id');
                    $this->placeInSlot($parent, $slot, $winner, $fx->id);
                    $parent->save();
                    $advanced++;
                }
            }

            // Feed "nobody" into loser-parent and cascade walkovers.
            if ($fx->loser_parent_fixture_id) {
                $loserDest = $all->firstWhere('id', $fx->loser_parent_fixture_id)
                    ?? Fixture::find($fx->loser_parent_fixture_id);

                if ($loserDest) {
                    $this->handleLoserWalkover($loserDest, $all);
                }
            }
        }

        return $advanced;
    }

    /**
     * Return true when all feeder (child) fixtures feeding into $fx are resolved.
     * A child is "resolved" if:
     *   - it has a winner_registration, OR
     *   - both its slots are empty (double-bye)
     */
    private function allFeedersDone(Fixture $fx, Collection $all): bool
    {
        $children = $all->where('parent_fixture_id', $fx->id);
        if ($children->count() < 2) return false;

        return $children->every(function ($c) {
            if (! is_null($c->winner_registration)) return true;
            return is_null($c->registration1_id) && is_null($c->registration2_id);
        });
    }

    /**
     * When a BYE sends nobody to a consolation fixture, check whether
     * the other slot already has a player and auto-advance that player.
     */
    private function handleLoserWalkover(Fixture $cons, Collection $all): void
    {
        $hasReg1 = ! is_null($cons->registration1_id);
        $hasReg2 = ! is_null($cons->registration2_id);

        // Consolation fixture already has exactly one real player and no winner yet.
        if (($hasReg1 xor $hasReg2) && is_null($cons->winner_registration)) {
            $consWinner = $hasReg1 ? $cons->registration1_id : $cons->registration2_id;
            $cons->winner_registration = $consWinner;
            $cons->save();

            Log::info('[ByeAdvancement] Consolation walkover', [
                'cons_fixture_id' => $cons->id,
                'winner_id'       => $consWinner,
            ]);

            // Cascade to the consolation fixture's own parent.
            if ($cons->parent_fixture_id) {
                $consParent = $all->firstWhere('id', $cons->parent_fixture_id)
                    ?? Fixture::find($cons->parent_fixture_id);

                if ($consParent) {
                    if (is_null($consParent->registration1_id)) {
                        $consParent->registration1_id = $consWinner;
                    } elseif (is_null($consParent->registration2_id)) {
                        $consParent->registration2_id = $consWinner;
                    }
                    $consParent->save();
                }
            }
        }
    }

    /**
     * Determine whether $fixture is in slot 1 or slot 2 of its parent.
     * Lower match_nr sibling → slot 1.
     */
    private function childSlot(Fixture $fixture, Collection $all, string $parentField): int
    {
        $parentId = $fixture->{$parentField};
        $siblings = $all->where($parentField, $parentId)->sortBy('match_nr')->values();
        $index    = $siblings->search(fn($s) => $s->id === $fixture->id);
        return ($index === false || $index === 0) ? 1 : 2;
    }

    /** Place $regId into slot 1 or 2 of $target. Idempotent. */
    private function placeInSlot(Fixture $target, int $slot, int $regId, int $fromId): void
    {
        $field = $slot === 1 ? 'registration1_id' : 'registration2_id';

        if (is_null($target->{$field}) || $target->{$field} === $regId) {
            $target->{$field} = $regId;
        } else {
            Log::warning('[ByeAdvancement] Slot already occupied — skipping', [
                'target_id' => $target->id,
                'slot'      => $slot,
                'existing'  => $target->{$field},
                'attempted' => $regId,
                'from'      => $fromId,
            ]);
        }
    }

    /** Merge configured custom playoff stages with the known static ones. */
    private function allStages(Draw $draw): array
    {
        $custom = collect(optional($draw->settings)->playoff_config ?? [])
            ->pluck('slug')
            ->map(fn($s) => strtoupper($s));

        return collect(self::BRACKET_STAGES)->merge($custom)->unique()->values()->all();
    }
}
