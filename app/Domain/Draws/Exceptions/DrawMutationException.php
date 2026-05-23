<?php

namespace App\Domain\Draws\Exceptions;

/**
 * Thrown when a draw or fixture mutation is blocked by a guard condition
 * (locked draw, published draw, verified fixture, etc.).
 *
 * Controllers should surface this as HTTP 403 Forbidden, not 422.
 */
class DrawMutationException extends \RuntimeException
{
    public static function locked(int $drawId, string $operation = 'mutate'): self
    {
        return new self("Cannot {$operation} draw #{$drawId}: draw is locked.");
    }

    public static function published(int $drawId, string $operation = 'mutate'): self
    {
        return new self("Cannot {$operation} draw #{$drawId}: draw is already published.");
    }

    public static function fixtureVerified(int $fixtureId): self
    {
        return new self("Cannot score fixture #{$fixtureId}: result is verified.");
    }

    public static function fixtureLocked(int $fixtureId, int $drawId): self
    {
        return new self("Cannot score fixture #{$fixtureId}: draw #{$drawId} is locked.");
    }

    public static function fixturePublished(int $fixtureId, int $drawId): self
    {
        return new self("Cannot score fixture #{$fixtureId}: draw #{$drawId} is published.");
    }

    public static function notGenerated(int $drawId, string $operation = 'perform'): self
    {
        return new self("Cannot {$operation} on draw #{$drawId}: draw has not been generated yet.");
    }
}
