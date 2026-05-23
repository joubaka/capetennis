<?php

namespace App\Domain\Draws\Services;

use App\Domain\Draws\Guards\DrawGuard;
use App\Models\Draw;
use Illuminate\Support\Facades\Log;

/**
 * DrawLockService
 *
 * Canonical service for locking and unlocking draws.
 *
 * A locked draw cannot have its fixtures or scores mutated.
 * Only administrators should unlock a draw once it has been locked.
 */
final class DrawLockService
{
    /**
     * Lock the draw, preventing all score / fixture mutations.
     *
     * @throws \RuntimeException if the draw has not been generated yet.
     */
    public function lock(Draw $draw): void
    {
        DrawGuard::requireGenerated($draw, 'lock');

        $draw->locked = true;
        $draw->save();

        Log::info('[DrawLock] Draw locked', ['draw_id' => $draw->id]);
    }

    /**
     * Unlock the draw, allowing mutations again.
     * Use with caution — unlocking a published draw may expose inconsistent state.
     */
    public function unlock(Draw $draw): void
    {
        $draw->locked = false;
        $draw->save();

        Log::info('[DrawLock] Draw unlocked', ['draw_id' => $draw->id]);
    }

    public function isLocked(Draw $draw): bool
    {
        return (bool) $draw->locked;
    }
}
