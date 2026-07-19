<?php

namespace App\Domain\TeamDraw;

/**
 * Thrown when a team-draw operation is blocked by a known domain rule
 * (e.g. published ties exist, locked state prevents mutation).
 *
 * Distinct from \RuntimeException so that controllers can safely return HTTP 409
 * for domain conflicts without silently swallowing unexpected database or system errors.
 */
class TeamDrawConflictException extends \RuntimeException
{
    public static function publishedTiesExist(): self
    {
        return new self('Cannot proceed: published or locked ties exist. Use allow_override to force regeneration.');
    }

    public static function lockedTiesExist(): self
    {
        return new self('Cannot proceed: locked ties exist. Use allow_override to force.');
    }
}
