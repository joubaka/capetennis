<?php

namespace App\Console\Commands;

use App\Models\Player;
use App\Services\Performance\{PlayerAbilityConsistentRead, PlayerEventRatingHistory, PlayerEventRatingHistoryRefresh, PlayerEventRatingHistoryStore, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Cache, Log};

class RefreshPlayerEventRatings extends Command
{
    protected $signature = 'player-event-ratings:refresh {player : Existing Cape Tennis player ID}';
    protected $description = 'Save a private reconstruction of one player’s ratings at past event completion dates';

    public function handle(PlayerEventRatingHistory $history, PlayerEventRatingHistoryStore $store, PlayerEventRatingHistoryRefresh $refresh, PlayerSharedAbilityService $ability): int
    {
        $id = (string) $this->argument('player');
        if (!preg_match('/^[1-9][0-9]{0,14}$/', $id) || !$player = Player::find((int) $id)) { $this->error('Choose an existing player ID.'); return self::FAILURE; }
        $lock = Cache::store('file')->lock(PlayerEventRatingHistoryRefresh::BUILDER, 1800);
        if (!$lock->get()) { $this->error('Another event history reconstruction is running.'); return self::FAILURE; }
        $original = (string) ini_get('memory_limit'); $limit = ini_parse_quantity($original);
        if ($limit > 0 && $limit < 512 * 1024 * 1024) ini_set('memory_limit', '512M');
        try {
            $refresh->mark((int) $id, 'running'); set_time_limit(0);
            $saved = app(PlayerAbilityConsistentRead::class)->run(fn () => $history->build($player, CarbonImmutable::today('Africa/Johannesburg')));
            $store->replace((int) $id, $saved, $ability->fingerprint());
            $refresh->mark((int) $id, 'completed');
            $this->info('Saved '.count($saved['events']).' past-event reconstructions for player #'.$id.'.');
            return self::SUCCESS;
        } catch (\Throwable $error) {
            $refresh->mark((int) $id, 'failed');
            Log::warning('Event rating history reconstruction failed.', ['exception_type' => get_class($error)]);
            $this->error($error instanceof \OverflowException ? $error->getMessage() : 'Past-event ratings could not be reconstructed. The last valid history was retained.');
            return self::FAILURE;
        } finally {
            $lock->release();
            if ($limit > 0 && $limit < 512 * 1024 * 1024 && memory_get_usage(true) < $limit) ini_set('memory_limit', $original);
        }
    }
}
