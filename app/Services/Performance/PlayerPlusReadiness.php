<?php

namespace App\Services\Performance;

use App\Models\{Player, User};

/** A linked account may see availability before purchase, never another player's estimates. */
class PlayerPlusReadiness
{
    public function forPlayer(User $user, Player $player): ?array
    {
        if (! app(PlayerPlusEntitlement::class)->linkedPlayers($user)->whereKey($player->id)->exists()) return null;
        try {
            $history = app(PlayerRatingHistoryService::class)->forPlayer($player);
            if (($history['reason'] ?? null) !== null || ($history['stale'] ?? false)) {
                return ['state' => 'pending', 'label' => 'Awaiting a saved rating update',
                    'description' => 'Current progress data is not ready. Updates run nightly; purchasing does not create or accelerate a rating.'];
            }
            foreach ($history['series'] ?? [] as $points) {
                foreach ($points as $point) {
                    if (is_numeric($point['score'] ?? null) && is_finite((float) $point['score'])) {
                        return ['state' => 'available', 'label' => 'Saved progress is available',
                            'description' => 'This player has saved rating history. Estimates may still be provisional, and suitable ball-level evidence is needed for pathway guidance.'];
                    }
                }
            }
            return ['state' => 'insufficient', 'label' => 'No eligible rating evidence yet',
                'description' => 'This player currently has no usable saved rating history. Consider waiting for eligible published singles results before purchasing.'];
        } catch (\Throwable) {
            return ['state' => 'pending', 'label' => 'Rating availability cannot currently be checked',
                'description' => 'Please check again before purchasing. Membership does not guarantee that a rating will be available.'];
        }
    }
}
