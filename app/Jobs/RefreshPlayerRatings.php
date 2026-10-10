<?php

namespace App\Jobs;

use Illuminate\Support\Facades\{Artisan, Cache};

/** Runs the canonical, independently locked snapshot builder outside page rendering. */
class RefreshPlayerRatings
{
    public function __construct(public string $owner) {}

    public function handle(): void
    {
        $requested = Cache::store('file')->restoreLock('player-ability:requested-refresh', $this->owner);
        $running = Cache::store('file')->lock('player-ability:requested-refresh-running', 7200);
        if (!$running->get()) { $requested->release(); return; }
        // A short launch lease recovers failed child startup. Once the child
        // acknowledges startup, the longer execution lease owns this work.
        $requested->release();
        // The sync fallback runs after the response on Windows. CLI background
        // processes also need enough time for the full shared-opponent network.
        set_time_limit(0);
        try {
            Artisan::call('player-ability:refresh');
        } finally {
            $running->release();
        }
    }
}
