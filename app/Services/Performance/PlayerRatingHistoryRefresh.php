<?php

namespace App\Services\Performance;

use Illuminate\Support\Facades\{Cache, Log};

class PlayerRatingHistoryRefresh
{
    public const STATE = 'player-rating-history:request-state';
    public const LEASE = 'player-rating-history:requested';
    public const BUILDER = 'player-rating-history:refresh';

    public function __construct(private readonly PlayerRatingHistoryProcessLauncher $launcher) {}

    public function status(): array
    {
        $state = Cache::store('file')->get(self::STATE, ['state' => 'idle']);
        $busy = $this->builderHeld();
        if ($busy && $state['state'] !== 'running') $state = ['state' => 'running', 'started_at' => $state['started_at'] ?? time()];
        elseif ($state['state'] === 'pending' && time() - ($state['submitted_at'] ?? 0) > 120) $state['state'] = 'failed';
        elseif ($state['state'] === 'running' && !$busy) $state['state'] = 'failed';
        $messages = ['idle' => 'Saved history updates nightly at 03:00 SAST.', 'pending' => 'Background update requested. Starting shortly…',
            'running' => 'Recalculating saved rating history for all players in the background…',
            'completed' => 'Saved rating history was updated.', 'failed' => 'The update could not complete. The last successful history was retained. You can try again.'];
        return ['state' => $state['state'], 'message' => $messages[$state['state']], 'completed_at' => $state['completed_at'] ?? null];
    }

    public function request(): array
    {
        $guard = Cache::store('file')->lock('player-rating-history:request-guard', 30);
        if (!$guard->get()) {
            $status = $this->status();
            return in_array($status['state'], ['pending', 'running'], true) ? $status
                : ['state' => 'pending', 'message' => 'A background update request is being submitted.', 'completed_at' => null];
        }
        $lease = null;
        try {
            $status = $this->status();
            if (in_array($status['state'], ['pending', 'running'], true)) return $status;
            $lease = Cache::store('file')->lock(self::LEASE, 120);
            if (!$lease->get()) return ['state' => 'pending', 'message' => 'A background update has already been requested.', 'completed_at' => null];
            Cache::store('file')->put(self::STATE, ['state' => 'pending', 'submitted_at' => time()], 86400);
            $this->launcher->launch($lease->owner());
            return $this->status();
        } catch (\Throwable $error) {
            $lease?->release();
            $this->failed();
            Log::warning('Rating history background launch failed.', ['exception_type' => get_class($error)]);
            return $this->status();
        } finally { $guard->release(); }
    }

    public function running(): void { Cache::store('file')->put(self::STATE, ['state' => 'running', 'started_at' => time()], 86400); }
    public function completed(): void { Cache::store('file')->put(self::STATE, ['state' => 'completed', 'completed_at' => time()], 86400); }
    public function failed(): void { Cache::store('file')->put(self::STATE, ['state' => 'failed'], 86400); }

    private function builderHeld(): bool
    {
        return Cache::store('file')->lock(self::BUILDER, 1)->isLocked();
    }
}
