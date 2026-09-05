<?php

namespace App\Domain\Draws\Guards;

use App\Domain\Draws\Enums\DrawState;
use App\Domain\Draws\Enums\FixtureState;
use App\Domain\Draws\Exceptions\DrawMutationException;
use App\Models\Draw;
use App\Models\Fixture;

/**
 * Canonical mutation guards for the draw domain.
 *
 * All entry-point services must call these before mutating state.
 * Guards throw descriptive exceptions — callers decide how to surface them.
 */
final class DrawGuard
{
    // ------------------------------------------------------------------
    // DRAW GUARDS
    // ------------------------------------------------------------------

    /**
     * @throws DrawMutationException when the draw cannot be mutated.
     */
    public static function requireMutable(Draw $draw, string $operation = 'mutate'): void
    {
        if ($draw->locked) {
            throw DrawMutationException::locked($draw->id, $operation);
        }
    }

    /**
     * @throws DrawMutationException when the draw has not yet been generated.
     */
    public static function requireGenerated(Draw $draw, string $operation = 'perform'): void
    {
        if (! $draw->drawFixtures()->exists()
            && ! $draw->fixtures()->exists()
            && ! $draw->teamTies()->exists()) {
            throw DrawMutationException::notGenerated($draw->id, $operation);
        }
    }

    /**
     * @throws DrawMutationException when the draw is already published.
     */
    public static function requireUnpublished(Draw $draw, string $operation = 'mutate'): void
    {
        if ($draw->published) {
            throw DrawMutationException::published($draw->id, $operation);
        }
    }

    // ------------------------------------------------------------------
    // FIXTURE GUARDS
    // ------------------------------------------------------------------

    /**
     * @throws DrawMutationException when a score cannot be written to the fixture.
     */
    public static function requireScoreable(Fixture $fixture): void
    {
        $draw = $fixture->draw ?? Draw::find($fixture->draw_id);

        if ($draw && $draw->locked) {
            throw DrawMutationException::fixtureLocked($fixture->id, $draw->id);
        }

        $state = FixtureState::fromFixture($fixture);
        if ($state === FixtureState::Verified) {
            throw DrawMutationException::fixtureVerified($fixture->id);
        }
    }

    /**
     * @throws DrawMutationException when trying to advance a fixture that has no result.
     */
    public static function requireCompleted(Fixture $fixture, string $operation = 'advance'): void
    {
        $hasResult = $fixture->relationLoaded('fixtureResults')
            ? $fixture->fixtureResults->isNotEmpty()
            : $fixture->fixtureResults()->exists();

        if (! $hasResult) {
            throw new DrawMutationException(
                "Cannot {$operation} fixture #{$fixture->id}: no results recorded."
            );
        }
    }
}
