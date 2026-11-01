<?php

namespace App\Console\Commands;

use App\Services\Performance\PlayerEventRatingHistoryRefresh;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Artisan, Cache};

class RunRequestedPlayerEventRatings extends Command
{
    protected $signature = 'player-event-ratings:requested-refresh {player}';
    protected $description = 'Run an already requested private past-event history reconstruction';

    public function handle(PlayerEventRatingHistoryRefresh $refresh): int
    {
        $owner = getenv('CT_EVENT_HISTORY_OWNER'); $id = (string) $this->argument('player');
        putenv('CT_EVENT_HISTORY_OWNER'); unset($_ENV['CT_EVENT_HISTORY_OWNER'], $_SERVER['CT_EVENT_HISTORY_OWNER']);
        if (!is_string($owner) || !preg_match('/^[A-Za-z0-9_-]{1,128}$/', $owner) || !preg_match('/^[1-9][0-9]{0,14}$/', $id)) return self::FAILURE;
        $lease = Cache::store('file')->restoreLock($refresh->lease((int) $id), $owner);
        if (!$lease->isOwnedByCurrentProcess()) return self::FAILURE;
        try {
            $result = Artisan::call('player-event-ratings:refresh', ['player' => $id]);
            if ($result !== 0) $refresh->mark((int) $id, 'failed');
            return $result === 0 ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable) { $refresh->mark((int) $id, 'failed'); return self::FAILURE; }
        finally { $lease->release(); }
    }
}
