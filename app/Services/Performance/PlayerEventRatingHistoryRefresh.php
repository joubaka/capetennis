<?php

namespace App\Services\Performance;

use Illuminate\Support\Facades\{Cache, Log};

class PlayerEventRatingHistoryRefresh
{
    public const BUILDER = 'player-event-ratings:builder';

    public function __construct(private PlayerEventRatingHistoryLauncher $launcher) {}
    public function lease(int $player): string { return 'player-event-ratings:lease:'.$player; }
    public function key(int $player): string { return 'player-event-ratings:status:'.$player; }

    public function status(int $player): array
    {
        $state = Cache::store('file')->get($this->key($player), ['state' => 'idle']);
        if (($state['state'] === 'pending' && time() - ($state['at'] ?? 0) > 120)
            || ($state['state'] === 'running' && time() - ($state['at'] ?? 0) > 1800)) $state['state'] = 'failed';
        $messages = ['idle' => 'Event history is prepared in the background when requested.', 'pending' => 'Event history update requested. Refresh this page shortly.',
            'running' => 'Past-event ratings are being reconstructed in the background. Refresh this page shortly.',
            'completed' => 'The saved past-event history was updated.', 'failed' => 'The update did not complete. The last valid history was retained. Please try again later.'];
        return ['state' => $state['state'], 'message' => $messages[$state['state']] ?? $messages['failed']];
    }

    public function request(int $player): array
    {
        $guard = Cache::store('file')->lock('player-event-ratings:request-guard', 15);
        if (!$guard->get()) return ['state' => 'pending', 'message' => 'Another update request is being submitted. Try again shortly.'];
        $lease = null;
        try {
            $status = $this->status($player);
            if (in_array($status['state'], ['pending', 'running'], true)) return $status;
            // Only one expensive reconstruction may run across all requested players.
            if (Cache::store('file')->lock(self::BUILDER, 1)->isLocked()) return ['state' => 'pending', 'message' => 'Another player’s history is being updated. Please request this update again shortly.'];
            if (!Cache::store('file')->add('player-event-ratings:cooldown:'.$player, true, 60)) return $status;
            $lease = Cache::store('file')->lock($this->lease($player), 120);
            if (!$lease->get()) return ['state' => 'pending', 'message' => 'An update has already been requested. Refresh this page shortly.'];
            $this->mark($player, 'pending');
            $this->launcher->launch($player, $lease->owner());
            return $this->status($player);
        } catch (\Throwable $error) {
            $lease?->release(); $this->mark($player, 'failed');
            Log::warning('Event rating history launch failed.', ['exception_type' => get_class($error)]);
            return $this->status($player);
        } finally { $guard->release(); }
    }

    public function mark(int $player, string $state): void
    {
        Cache::store('file')->put($this->key($player), ['state' => $state, 'at' => time()], 86400);
    }
}
