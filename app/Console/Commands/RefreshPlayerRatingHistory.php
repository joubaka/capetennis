<?php

namespace App\Console\Commands;

use App\Services\Performance\{PlayerAbilityConsistentRead, PlayerRatingHistoryService, PlayerRatingHistoryStore, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Cache, Log};

class RefreshPlayerRatingHistory extends Command
{
    protected $signature = 'player-rating-history:refresh';
    protected $description = 'Save private 12-month player rating histories for fast page reads';

    public function handle(PlayerRatingHistoryService $history, PlayerRatingHistoryStore $store, PlayerSharedAbilityService $ability): int
    {
        $lock = Cache::store('file')->lock('player-rating-history:refresh', 7200);
        if (!$lock->get()) { $this->error('A rating history refresh is already running.'); return self::FAILURE; }
        try {
            $start = microtime(true);
            $saved = app(PlayerAbilityConsistentRead::class)->run(fn () => $history->calculate(CarbonImmutable::today('Africa/Johannesburg')));
            $store->replace($saved, $ability->fingerprint());
            $this->info('Saved 12 monthly histories as of '.$saved['as_of'].' in '.number_format(microtime(true) - $start, 2).' seconds.');
            return self::SUCCESS;
        } catch (\Throwable $error) {
            Log::warning('Player rating history refresh failed; last successful build retained.', ['exception_type' => get_class($error)]);
            $this->error('Rating history refresh failed. The last successful build was retained.');
            return self::FAILURE;
        } finally { $lock->release(); }
    }
}
