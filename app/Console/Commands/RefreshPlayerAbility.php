<?php

namespace App\Console\Commands;

use App\Services\Performance\{PlayerAbilityConsistentRead, PlayerAbilityRefreshState, PlayerRatingRefreshDiagnostics, PlayerAbilitySnapshotStore, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\{Cache, Log, Schema};

class RefreshPlayerAbility extends Command
{
    protected $signature = 'player-ability:refresh {--pending : Refresh only changed or ageing sources} {--check : Read saved readiness without calculating or writing} {--dry-run : Calculate and validate without saving} {--reference= : Diagnostic reference for a requested refresh}';
    protected $description = 'Materialize private Cape Tennis ability estimates and their stable weighting policy';

    public function handle(PlayerAbilitySnapshotStore $store, PlayerSharedAbilityService $service, PlayerAbilityRefreshState $state): int
    {
        $reference = PlayerRatingRefreshDiagnostics::reference($this->option('reference'));
        if ($this->option('check')) {
            $snapshot = $store->current();
            $status = $state->status();
            $this->line($snapshot['reason'] ?: 'Saved ability snapshot as of '.$snapshot['snapshot_as_of'].($status['failed'] ? ' (update failed; retry pending).' : ($status['pending'] ? ' (update pending).' : ' (current).')));
            return $snapshot['reason'] || $snapshot['snapshot_stale'] || $status['pending'] || $status['failed'] ? self::FAILURE : self::SUCCESS;
        }
        if (!$this->option('dry-run') && !Schema::hasTable('player_ability_snapshots')) {
            PlayerRatingRefreshDiagnostics::write($reference, 'command', 'snapshot_table_missing', null, self::FAILURE);
            $this->error('Snapshot table is missing. Apply the explicitly reviewed migration before refreshing.');
            return self::FAILURE;
        }
        if ($this->option('pending') && $state->generation() === null) {
            PlayerRatingRefreshDiagnostics::write($reference, 'command', 'refresh_state_missing', null, self::FAILURE);
            $this->error('Background refresh state is missing. Apply the explicitly reviewed migration.');
            return self::FAILURE;
        }
        // Manual and scheduler invocations use the same host-local lock.
        $lock = Cache::store('file')->lock('player-ability:refresh', 7200);
        if (!$lock->get()) { PlayerRatingRefreshDiagnostics::write($reference, 'command', 'builder_lock_held'); $this->error('An ability refresh is already running.'); return $this->option('pending') ? self::SUCCESS : self::FAILURE; }
        PlayerRatingRefreshDiagnostics::write($reference, 'command', 'starting');
        $stage = 'preparation';
        $failureReason = 'unexpected_error';
        try {
            if ($this->option('pending') && !$state->checkDue()) { PlayerRatingRefreshDiagnostics::write($reference, 'command', 'not_due'); return self::SUCCESS; }
            $asOf = CarbonImmutable::today('Africa/Johannesburg');
            if (!$this->option('dry-run')) { $state->checked(); }
            if ($this->option('pending') && !$state->status()['pending'] && $store->matches($asOf, $service->fingerprint())) {
                PlayerRatingRefreshDiagnostics::write($reference, 'command', 'current');
                $this->info('Saved ability snapshot is current; no calculation needed.');
                return self::SUCCESS;
            }
            [$generation, $fingerprint, $snapshot, $manifest] = app(PlayerAbilityConsistentRead::class)->run(function () use ($asOf, $state, $service, $store, &$stage, &$failureReason) {
                $generation = $state->generation();
                $fingerprint = $service->fingerprint();
                $stage = 'calculation';
                $snapshot = $service->calculateSnapshot($asOf);
                $stage = 'manifest';
                $manifest = $store->manifest($snapshot);
                if ($fingerprint !== $service->fingerprint()) {
                    $failureReason = 'source_changed_during_build';
                    throw new \RuntimeException('Source data changed inside the consistent read.');
                }
                return [$generation, $fingerprint, $snapshot, $manifest];
            });
            $stage = 'validation';
            foreach ($snapshot['cohorts'] as $cohort) {
                foreach ($cohort['components'] as $component) {
                    if (!$component['converged']) {
                        $failureReason = 'model_nonconverged';
                        throw new \RuntimeException('Model did not converge.');
                    }
                }
            }
            if ($snapshot['reason']) {
                $failureReason = str_starts_with($snapshot['reason'], 'Shared calibration exceeds its safe local processing limit')
                    ? 'safe_processing_limit' : 'calculation_withheld';
                throw new \RuntimeException('Calculation withheld.');
            }
            if (!$store->published($manifest)) {
                $failureReason = 'publication_or_context_revoked';
                throw new \RuntimeException('Source publication or context changed during refresh.');
            }
            if (!$this->option('dry-run')) {
                $stage = 'save';
                $store->replace($snapshot, $asOf, $fingerprint, $manifest);
                // Never consume generations that arrived after the build began.
                $stage = 'completion';
                if ($generation !== null) { $state->completed($generation); }
            }
            PlayerRatingRefreshDiagnostics::write($reference, 'command', $this->option('dry-run') ? 'validated' : 'saved');
            $this->info(($this->option('dry-run') ? 'Validated without saving' : 'Saved').' ability snapshot as of '.$asOf->toDateString().'.');
            return self::SUCCESS;
        } catch (\Throwable $error) {
            if (!$this->option('dry-run')) {
                try { $state->failed(); }
                catch (\Throwable $markerError) { PlayerRatingRefreshDiagnostics::write($reference, 'command', 'failure_marker_unavailable', $markerError); }
            }
            // Only allowlisted categories and stages reach diagnostics. Exception
            // messages may contain SQL, credentials, names or other source data.
            try { Log::warning('Player ability refresh failed; last good snapshot retained.', [
                'reference_id' => $reference, 'exception_type' => get_class($error), 'reason' => $failureReason, 'stage' => $stage,
            ]); } catch (\Throwable) { /* Keep the builder lease cleanup independent of logging. */ }
            $this->error('Ability refresh failed ['.$failureReason.'; stage: '.$stage.']. The last successful snapshot is retained; source publication checks still apply.');
            return self::FAILURE;
        } finally { $lock->release(); }
    }
}
