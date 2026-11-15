<?php

namespace App\Console\Commands;

use App\Models\Player;
use App\Services\Performance\{PlayerAbilityConsistentRead, PlayerMatchProgress, PlayerMatchProgressStore, PlayerSharedAbilityService};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Cache, Log};

class RefreshPlayerMatchProgress extends Command
{
    protected $signature = 'player-match-progress:refresh {player : Existing Cape Tennis player ID}';
    protected $description = 'Explicitly save reconstructed Overall CTR before/after five matches using recorded scheduled order';

    public function handle(PlayerMatchProgress $progress, PlayerMatchProgressStore $store, PlayerSharedAbilityService $ability): int
    {
        $id = (string) $this->argument('player');
        if (!preg_match('/^[1-9][0-9]{0,14}$/', $id) || !$player = Player::find((int) $id)) { $this->error('Choose an existing player ID.'); return self::FAILURE; }
        $lock = Cache::store('file')->lock('player-match-progress:'.$id, 1800);
        if (!$lock->get()) { $this->error('This player’s reconstruction is already running.'); return self::FAILURE; }
        try {
            $saved = app(PlayerAbilityConsistentRead::class)->run(fn () => $progress->build($player));
            if (!$progress->current($saved, freshSnapshot: true)) throw new \RuntimeException('Saved rating context changed before replacement.');
            $store->replace((int) $id, $saved, $ability->fingerprint());
            $this->info('Saved '.count($saved['rows']).' scheduled-order match reconstructions for player #'.$id.'.');
            return self::SUCCESS;
        } catch (\Throwable $error) {
            Log::warning('Player scheduled-order reconstruction failed; last valid artifact retained.', ['exception_type' => get_class($error)]);
            $this->error('Before/after reconstruction could not be saved. Refresh matching saved ratings and history first. The last valid artifact was retained.');
            return self::FAILURE;
        } finally { $lock->release(); }
    }
}
