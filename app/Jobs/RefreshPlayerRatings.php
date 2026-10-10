<?php

namespace App\Jobs;

use App\Services\Performance\{PlayerAbilityRefreshState, PlayerRatingRefreshDiagnostics};
use Illuminate\Support\Facades\{Artisan, Cache};

/** Runs the canonical, independently locked snapshot builder outside page rendering. */
class RefreshPlayerRatings
{
    public function __construct(public string $owner, public ?string $reference = null) {}

    public function handle(): int
    {
        $reference = PlayerRatingRefreshDiagnostics::reference($this->reference);
        PlayerRatingRefreshDiagnostics::write($reference, 'job', 'received');
        $requested = null;
        $running = null;
        try {
            $requested = Cache::store('file')->restoreLock('player-ability:requested-refresh', $this->owner);
            if (! $requested->isOwnedByCurrentProcess()) {
                PlayerRatingRefreshDiagnostics::write($reference, 'job', 'launch_lease_expired');
                return 0;
            }
            try {
                $submitted = Cache::store('file')->get('player-ability:requested-refresh-submitted');
                if (($submitted['reference_id'] ?? null) === $reference) Cache::store('file')->forget('player-ability:requested-refresh-submitted');
            } catch (\Throwable $error) { PlayerRatingRefreshDiagnostics::write($reference, 'job', 'diagnostic_cache_unavailable', $error); }

            $running = Cache::store('file')->lock('player-ability:requested-refresh-running', 7200);
            if (!$running->get()) {
                PlayerRatingRefreshDiagnostics::write($reference, 'job', 'already_running');
                return 0;
            }
            PlayerRatingRefreshDiagnostics::write($reference, 'job', 'starting');
            $requested->release();
            set_time_limit(0);
            $exitCode = Artisan::call('player-ability:refresh', ['--reference' => $reference]);
            PlayerRatingRefreshDiagnostics::write($reference, 'job', 'command_exited', null, $exitCode);
            if ($exitCode !== 0) {
                app(PlayerAbilityRefreshState::class)->failed();
                return 1;
            }
            PlayerRatingRefreshDiagnostics::write($reference, 'job', 'completed');
            return 0;
        } catch (\Throwable $error) {
            PlayerRatingRefreshDiagnostics::write($reference, 'job', 'failed', $error);
            try { app(PlayerAbilityRefreshState::class)->failed(); }
            catch (\Throwable $markerError) { PlayerRatingRefreshDiagnostics::write($reference, 'job', 'failure_marker_unavailable', $markerError); }
            throw new \RuntimeException('Player ratings background refresh failed. Reference: '.$reference);
        } finally {
            foreach ([$requested, $running] as $lease) {
                try { $lease?->release(); }
                catch (\Throwable $error) { PlayerRatingRefreshDiagnostics::write($reference, 'job', 'lease_cleanup_unavailable', $error); }
            }
        }
    }
}
