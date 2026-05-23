<?php

namespace App\Domain\Fixtures\Services;

use App\Domain\Draws\Guards\DrawGuard;
use App\Models\Draw;
use App\Models\Fixture;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FixtureProgressionService
 *
 * Canonical service for all winner/loser advancement and rollback.
 *
 * Responsibilities:
 *   - advance winner into parent fixture (slot determined by child ordering)
 *   - advance loser into loser-bracket parent fixture
 *   - handle special feed-in match slot rules (match_nr 3007/3008)
 *   - rollback progression when a score is deleted
 *   - idempotent: never overwrites an occupied slot with a different player
 *
 * Does NOT:
 *   - save scores
 *   - call standings
 *   - render anything
 */
final class FixtureProgressionService
{
    // ------------------------------------------------------------------
    // PUBLIC API
    // ------------------------------------------------------------------

    /**
     * Advance winner and loser from a completed fixture into their respective
     * parent fixtures.  Idempotent — safe to call more than once.
     *
     * @throws \RuntimeException if the draw is locked.
     */
    public function advance(Fixture $fixture, int $winner, int $loser): void
    {
        $draw = $this->loadDraw($fixture);
        DrawGuard::requireMutable($draw, 'advance progression for');

        DB::transaction(function () use ($fixture, $winner, $loser) {
            $this->advanceWinner($fixture, $winner);
            $this->advanceLoser($fixture, $loser);
        });
    }

    /**
     * Roll back all progression caused by this fixture's result.
     * Clears the winner/loser registration from parent fixtures and resets
     * this fixture's own winner_registration and match_status.
     *
     * @throws \RuntimeException if the draw is locked.
     */
    public function rollback(Fixture $fixture): void
    {
        $draw = $this->loadDraw($fixture);
        DrawGuard::requireMutable($draw, 'roll back progression for');

        DB::transaction(function () use ($fixture) {
            $this->rollbackWinnerSlot($fixture);
            $this->rollbackLoserSlot($fixture);
            $this->clearFixtureResult($fixture);
        });
    }

    // ------------------------------------------------------------------
    // WINNER ADVANCEMENT
    // ------------------------------------------------------------------

    private function advanceWinner(Fixture $fixture, int $winner): void
    {
        if (! $fixture->parent_fixture_id) {
            return;
        }

        $parent = Fixture::find($fixture->parent_fixture_id);
        if (! $parent) {
            Log::warning('[Progression] Parent fixture not found', [
                'fixture_id' => $fixture->id,
                'parent_id'  => $fixture->parent_fixture_id,
            ]);
            return;
        }

        // Special slot rule for plate feed-in matches.
        if ($this->isFeedInTarget($parent)) {
            $this->placeWinnerFeedIn($fixture, $parent, $winner);
        } else {
            $slot = $this->childSlot($fixture, 'parent_fixture_id');
            $this->placeInSlot($parent, $slot, $winner, 'winner', $fixture->id);
        }

        $parent->save();
    }

    // ------------------------------------------------------------------
    // LOSER ADVANCEMENT
    // ------------------------------------------------------------------

    private function advanceLoser(Fixture $fixture, int $loser): void
    {
        if (! $fixture->loser_parent_fixture_id) {
            return;
        }

        $parent = Fixture::find($fixture->loser_parent_fixture_id);
        if (! $parent) {
            Log::warning('[Progression] Loser-parent fixture not found', [
                'fixture_id' => $fixture->id,
                'loser_parent_id' => $fixture->loser_parent_fixture_id,
            ]);
            return;
        }

        $slot = $this->childSlot($fixture, 'loser_parent_fixture_id');
        $this->placeInSlot($parent, $slot, $loser, 'loser', $fixture->id);
        $parent->save();
    }

    // ------------------------------------------------------------------
    // ROLLBACK HELPERS
    // ------------------------------------------------------------------

    private function rollbackWinnerSlot(Fixture $fixture): void
    {
        if (! $fixture->parent_fixture_id) {
            return;
        }

        $parent = Fixture::find($fixture->parent_fixture_id);
        if (! $parent) return;

        $slot = $this->childSlot($fixture, 'parent_fixture_id');
        $field = $slot === 1 ? 'registration1_id' : 'registration2_id';

        // Only clear if the slot still holds the winner from this fixture.
        if ($parent->{$field} === $fixture->winner_registration) {
            $parent->{$field} = null;
        }

        // Clear parent's own winner if it was derived from this child.
        if ($parent->winner_registration === $fixture->winner_registration) {
            $parent->winner_registration = null;
            $parent->match_status        = 0;
        }

        $parent->save();

        Log::info('[Progression] Rolled back winner slot', [
            'fixture_id' => $fixture->id,
            'parent_id'  => $parent->id,
            'slot'       => $slot,
        ]);
    }

    private function rollbackLoserSlot(Fixture $fixture): void
    {
        if (! $fixture->loser_parent_fixture_id) {
            return;
        }

        $parent = Fixture::find($fixture->loser_parent_fixture_id);
        if (! $parent) return;

        $loser = $this->resolveLoser($fixture);
        $slot  = $this->childSlot($fixture, 'loser_parent_fixture_id');
        $field = $slot === 1 ? 'registration1_id' : 'registration2_id';

        if ($loser !== null && $parent->{$field} === $loser) {
            $parent->{$field} = null;
        }

        $parent->save();
    }

    private function clearFixtureResult(Fixture $fixture): void
    {
        $fixture->fixtureResults()->delete();
        $fixture->winner_registration = null;
        $fixture->match_status        = 0;
        $fixture->save();
    }

    // ------------------------------------------------------------------
    // SLOT HELPERS
    // ------------------------------------------------------------------

    /**
     * Determine whether this fixture feeds into slot 1 or slot 2 of the parent.
     * Lower match_nr sibling → slot 1, higher → slot 2.
     * If this fixture is the only child, default to slot 1.
     */
    private function childSlot(Fixture $fixture, string $parentField): int
    {
        $parentId = $fixture->{$parentField};

        $siblings = Fixture::where($parentField, $parentId)
            ->orderBy('match_nr')
            ->pluck('id');

        $index = $siblings->search($fixture->id);
        return ($index === false || $index === 0) ? 1 : 2;
    }

    /**
     * Place a registration into slot 1 or 2 of a fixture.
     * Idempotent: will not overwrite an occupied slot with a *different* player.
     */
    private function placeInSlot(Fixture $target, int $slot, int $regId, string $role, int $fromFixtureId): void
    {
        $field = $slot === 1 ? 'registration1_id' : 'registration2_id';

        if (is_null($target->{$field}) || $target->{$field} === $regId) {
            $target->{$field} = $regId;
            Log::info("[Progression] Placed {$role} into slot {$slot}", [
                'target_id'       => $target->id,
                'from_fixture_id' => $fromFixtureId,
                'reg_id'          => $regId,
            ]);
        } else {
            Log::warning("[Progression] Slot {$slot} already occupied — skipping duplicate", [
                'target_id'  => $target->id,
                'existing'   => $target->{$field},
                'attempted'  => $regId,
            ]);
        }
    }

    // ------------------------------------------------------------------
    // FEED-IN SPECIAL RULES (match_nr 3007 / 3008)
    // ------------------------------------------------------------------

    private function isFeedInTarget(Fixture $parent): bool
    {
        return in_array($parent->match_nr, [3007, 3008], true);
    }

    /**
     * Feed-in matches (3007/3008) have a specific slot convention:
     *   - Match 3003 winner → 3007 slot 2 (registration2)
     *   - Match 3004 winner → 3008 slot 1 (registration1)
     *   - All others → first empty slot.
     */
    private function placeWinnerFeedIn(Fixture $source, Fixture $target, int $winner): void
    {
        if ($source->match_nr === 3003) {
            if (! $target->registration2_id) {
                $target->registration2_id = $winner;
            } else {
                $target->registration1_id = $winner;
            }
        } elseif ($source->match_nr === 3004) {
            if (! $target->registration1_id) {
                $target->registration1_id = $winner;
            } else {
                $target->registration2_id = $winner;
            }
        } else {
            // Generic feed-in: fill first empty slot.
            if (! $target->registration2_id) {
                $target->registration2_id = $winner;
            } elseif (! $target->registration1_id) {
                $target->registration1_id = $winner;
            }
        }
    }

    // ------------------------------------------------------------------
    // UTILITIES
    // ------------------------------------------------------------------

    private function loadDraw(Fixture $fixture): Draw
    {
        return $fixture->draw ?? Draw::findOrFail($fixture->draw_id);
    }

    /**
     * Resolve the loser from a fixture's results.
     * Returns null when no result is recorded.
     */
    private function resolveLoser(Fixture $fixture): ?int
    {
        $winner = $fixture->winner_registration;
        if (! $winner) return null;
        return $winner === $fixture->registration1_id
            ? $fixture->registration2_id
            : $fixture->registration1_id;
    }
}
