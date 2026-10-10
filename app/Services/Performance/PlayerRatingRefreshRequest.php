<?php

namespace App\Services\Performance;

use App\Jobs\RefreshPlayerRatings;
use Illuminate\Support\Facades\{Cache, Concurrency};

class PlayerRatingRefreshRequest
{
    public function running(): bool
    {
        foreach (['player-ability:requested-refresh', 'player-ability:requested-refresh-running', 'player-ability:refresh'] as $key) {
            if ($this->held($key)) { return true; }
        }
        return false;
    }

    private function held(string $key): bool
    {
        $lock = Cache::store('file')->lock($key, 60);
        if (!$lock->get()) { return true; }
        $lock->release();
        return false;
    }

    public function request(): void
    {
        if ($this->running()) { return; }
        $lock = Cache::store('file')->lock('player-ability:requested-refresh', 60);
        if (!$lock->get()) { return; }
        $owner = $lock->owner();
        try {
            if ($this->held('player-ability:requested-refresh-running') || $this->held('player-ability:refresh')) {
                $lock->release();
                return;
            }
            // Bound repeated failing page loads across administrators.
            if (!Cache::store('file')->add('player-ability:requested-refresh-cooldown', true, 60)) {
                $lock->release();
                return;
            }
            if (PHP_OS_FAMILY === 'Windows') {
                // Laravel's process driver uses a POSIX shell background command.
                // On Windows, send the response before running the same job.
                app()->terminating(fn () => (new RefreshPlayerRatings($owner))->handle());
            } else {
                Concurrency::driver('process')->defer(function () use ($owner): void {
                    (new RefreshPlayerRatings($owner))->handle();
                });
            }
        } catch (\Throwable $error) {
            $lock->release();
            throw $error;
        }
    }
}
