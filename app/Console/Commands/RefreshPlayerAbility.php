<?php

namespace App\Console\Commands;

use App\Services\Performance\{PlayerAbilitySnapshotStore, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Cache, Log, Schema};

class RefreshPlayerAbility extends Command
{
    protected $signature = 'player-ability:refresh {--check : Read saved readiness without calculating or writing} {--dry-run : Calculate and validate without saving}';
    protected $description = 'Materialize private Cape Tennis ability estimates and their stable weighting policy';

    public function handle(PlayerAbilitySnapshotStore $store, PlayerSharedAbilityService $service): int
    {
        if ($this->option('check')) {
            $snapshot = $store->current();
            $this->line($snapshot['reason'] ?: 'Saved ability snapshot as of '.$snapshot['snapshot_as_of'].($snapshot['snapshot_stale'] ? ' (stale; awaiting nightly update).' : ' (current).'));
            return $snapshot['reason'] || $snapshot['snapshot_stale'] ? self::FAILURE : self::SUCCESS;
        }
        if (!$this->option('dry-run') && !Schema::hasTable('player_ability_snapshots')) {
            $this->error('Snapshot table is missing. Apply the explicitly reviewed migration before refreshing.');
            return self::FAILURE;
        }
        // Manual and scheduler invocations use the same host-local lock.
        $lock = Cache::store('file')->lock('player-ability:refresh', 7200);
        if (!$lock->get()) { $this->error('An ability refresh is already running.'); return self::FAILURE; }
        try {
            $asOf = CarbonImmutable::today('Africa/Johannesburg');
            $fingerprint = $service->fingerprint();
            $snapshot = $service->calculateSnapshot($asOf);
            $manifest = $store->manifest($snapshot);
            foreach ($snapshot['cohorts'] as $cohort) {
                foreach ($cohort['components'] as $component) {
                    if (!$component['converged']) { throw new \RuntimeException('Model did not converge.'); }
                }
            }
            if ($snapshot['reason'] || $fingerprint !== $service->fingerprint() || !$store->published($manifest)) {
                throw new \RuntimeException('Calculation withheld or source data changed during refresh.');
            }
            if (!$this->option('dry-run')) { $store->replace($snapshot, $asOf, $fingerprint, $manifest); }
            $this->info(($this->option('dry-run') ? 'Validated without saving' : 'Saved').' ability snapshot as of '.$asOf->toDateString().'.');
            return self::SUCCESS;
        } catch (\Throwable $error) {
            Log::warning('Player ability refresh failed; last good snapshot retained.', ['exception_type' => get_class($error)]);
            $this->error('Ability refresh failed. The last successful snapshot is retained; source publication checks still apply.');
            return self::FAILURE;
        } finally { $lock->release(); }
    }
}
