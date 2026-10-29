<?php

namespace App\Console\Commands;

use App\Services\Performance\PlayerRatingHistoryRefresh;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Artisan, Cache, Log};

class RunRequestedRatingHistory extends Command
{
    protected $signature = 'player-rating-history:requested-refresh';
    protected $description = 'Run an already requested background rating history update';

    public function handle(PlayerRatingHistoryRefresh $refresh): int
    {
        $owner = getenv('CT_HISTORY_REFRESH_OWNER');
        putenv('CT_HISTORY_REFRESH_OWNER');
        unset($_ENV['CT_HISTORY_REFRESH_OWNER'], $_SERVER['CT_HISTORY_REFRESH_OWNER']);
        if (!is_string($owner) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/', $owner)) return self::FAILURE;
        $lease = Cache::store('file')->restoreLock(PlayerRatingHistoryRefresh::LEASE, $owner);
        if (!$lease->isOwnedByCurrentProcess()) return self::FAILURE;
        try {
            set_time_limit(0);
            // The canonical builder owns the manual/nightly execution lock and status transitions.
            return Artisan::call('player-rating-history:refresh') === 0 ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $error) {
            $refresh->failed();
            Log::warning('Requested rating history update failed.', ['exception_type' => get_class($error)]);
            return self::FAILURE;
        } finally { $lease->release(); }
    }
}
