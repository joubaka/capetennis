<?php

namespace App\Services\Performance;

use App\Jobs\RefreshPlayerRatings;
use Illuminate\Support\Facades\Cache;

class PlayerRatingRefreshRequest
{
    public function running(): bool
    {
        foreach (['player-ability:requested-refresh', 'player-ability:requested-refresh-running', 'player-ability:refresh'] as $key) {
            if ($this->held($key)) { return true; }
        }
        return false;
    }

    private function held(string $key): bool
    {
        $lock = Cache::store('file')->lock($key, 60);
        if (!$lock->get()) { return true; }
        $lock->release();
        return false;
    }

    public function request(?string $reference = null): void
    {
        $reference = PlayerRatingRefreshDiagnostics::reference($reference);
        PlayerRatingRefreshDiagnostics::write($reference, 'request', 'received');
        if ($this->running()) { PlayerRatingRefreshDiagnostics::write($reference, 'request', 'already_running'); return; }
        $lock = Cache::store('file')->lock('player-ability:requested-refresh', 60);
        if (!$lock->get()) { PlayerRatingRefreshDiagnostics::write($reference, 'request', 'launch_lease_held'); return; }
        $owner = $lock->owner();
        try {
            if ($this->held('player-ability:requested-refresh-running') || $this->held('player-ability:refresh')) {
                $lock->release();
                PlayerRatingRefreshDiagnostics::write($reference, 'request', 'already_running');
                return;
            }
            // Bound repeated failing page loads across administrators.
            if (!Cache::store('file')->add('player-ability:requested-refresh-cooldown', true, 60)) {
                $lock->release();
                PlayerRatingRefreshDiagnostics::write($reference, 'request', 'cooldown');
                return;
            }
            if ($this->runsOnWindows()) {
                // Laravel's process driver uses a POSIX shell background command.
                // On Windows, send the response before running the same job.
                app()->terminating(function () use ($owner, $reference): void {
                    try { (new RefreshPlayerRatings($owner, $reference))->handle(); }
                    catch (\Throwable $error) { PlayerRatingRefreshDiagnostics::write($reference, 'job', 'termination_failed', $error); }
                });
            } else {
                $this->afterResponse(function () use ($owner, $reference): void {
                    PlayerRatingRefreshDiagnostics::write($reference, 'launch', 'starting');
                    try {
                        $command = \Illuminate\Console\Application::formatCommandString('player-ability:requested-refresh --reference='.escapeshellarg($reference));
                        $result = app(\Illuminate\Process\Factory::class)->path(base_path())
                            ->env(['CT_RATINGS_REFRESH_OWNER' => $owner])->run($command.' > /dev/null 2>&1 &');
                        PlayerRatingRefreshDiagnostics::write($reference, 'launch', 'submitted', null, $result->exitCode());
                        if ($result->failed()) throw new \RuntimeException('Background launch failed.');
                    } catch (\Throwable $error) {
                        $this->launchFailed($owner, $reference, $error);
                    }
                });
            }
            try {
                Cache::store('file')->forget('player-ability:requested-refresh-launch-failure');
                Cache::store('file')->put('player-ability:requested-refresh-submitted', ['reference_id' => $reference, 'submitted_at' => time()], 180);
            }
            catch (\Throwable $error) { PlayerRatingRefreshDiagnostics::write($reference, 'request', 'diagnostic_cache_unavailable', $error); }
            PlayerRatingRefreshDiagnostics::write($reference, 'request', 'scheduled');
        } catch (\Throwable $error) {
            $this->launchFailed($owner, $reference, $error);
            throw new \RuntimeException('The ratings refresh could not be scheduled.');
        }
    }
    public function launchFailure(): ?array
    {
        $submitted = Cache::store('file')->get('player-ability:requested-refresh-submitted');
        if ($submitted && time() - $submitted['submitted_at'] >= 60 && ! $this->running()) {
            PlayerRatingRefreshDiagnostics::write($submitted['reference_id'], 'launch', 'startup_unconfirmed');
            Cache::store('file')->forget('player-ability:requested-refresh-submitted');
            Cache::store('file')->put('player-ability:requested-refresh-launch-failure', ['reference_id' => $submitted['reference_id'], 'reason' => 'startup_unconfirmed'], 120);
        }
        return Cache::store('file')->get('player-ability:requested-refresh-launch-failure');
    }

    public function launchFailed(string $owner, string $reference, \Throwable $error): void
    {
        try {
            if (! Cache::store('file')->restoreLock('player-ability:requested-refresh', $owner)->isOwnedByCurrentProcess()) {
                PlayerRatingRefreshDiagnostics::write($reference, 'launch', 'stale_failure_ignored', $error);
                return;
            }
        } catch (\Throwable $leaseError) {
            PlayerRatingRefreshDiagnostics::write($reference, 'launch', 'lease_check_unavailable', $leaseError);
        }
        PlayerRatingRefreshDiagnostics::write($reference, 'launch', 'failed', $error);
        foreach (['release', 'cooldown', 'failure_marker'] as $step) {
            try {
                if ($step === 'release') Cache::store('file')->restoreLock('player-ability:requested-refresh', $owner)->release();
                elseif ($step === 'cooldown') Cache::store('file')->forget('player-ability:requested-refresh-cooldown');
                else Cache::store('file')->put('player-ability:requested-refresh-launch-failure', ['reference_id' => $reference, 'reason' => 'launch_failed'], 120);
            } catch (\Throwable $cleanupError) {
                PlayerRatingRefreshDiagnostics::write($reference, 'launch', 'cleanup_unavailable', $cleanupError);
            }
        }
    }

    protected function afterResponse(callable $callback): void
    {
        app()->terminating($callback);
    }

    protected function runsOnWindows(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }
}
