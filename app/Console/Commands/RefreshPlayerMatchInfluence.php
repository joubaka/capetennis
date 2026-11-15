<?php

namespace App\Console\Commands;

use App\Models\Player;
use App\Services\Performance\{PlayerAbilityConsistentRead, PlayerMatchInfluence, PlayerMatchInfluenceStore, PlayerSharedAbilityService};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Cache, Log};

class RefreshPlayerMatchInfluence extends Command
{
    protected $signature = 'player-match-influence:refresh {player : Existing Cape Tennis player ID}';
    protected $description = 'Explicitly save private last-five-match Overall CTR influence for one player; never changes results or ratings';

    public function handle(PlayerMatchInfluence $influence, PlayerMatchInfluenceStore $store, PlayerSharedAbilityService $ability): int
    {
        $id = (string) $this->argument('player');
        if (!preg_match('/^[1-9][0-9]{0,14}$/', $id) || !$player = Player::find((int) $id)) { $this->error('Choose an existing player ID.'); return self::FAILURE; }
        $lock = Cache::store('file')->lock('player-match-influence:'.$id, 1800);
        if (!$lock->get()) { $this->error('A saved influence calculation for this player is already running.'); return self::FAILURE; }
        try {
            $saved = app(PlayerAbilityConsistentRead::class)->run(fn () => $influence->build($player));
            if (!$influence->current($saved, freshSnapshot: true)) throw new \RuntimeException('Saved rating context changed before replacement.');
            $store->replace((int) $id, $saved, $ability->fingerprint());
            $this->info('Saved '.count($saved['rows']).' estimated match influences for player #'.$id.'.');
            return self::SUCCESS;
        } catch (\Throwable $error) {
            Log::warning('Player match influence calculation failed; last valid artifact retained.', ['exception_type' => get_class($error)]);
            $this->error('Match influence could not be saved. Refresh matching saved ratings and history first. The last valid artifact was retained.');
            return self::FAILURE;
        } finally {
            $lock->release();
        }
    }
}
