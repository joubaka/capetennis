<?php

namespace App\Services\Performance;

use App\Models\{Player, User};
use Illuminate\Support\Facades\Gate;

class PlayerRatingAccess
{
    public const SETTING = 'player_rating_membership_enabled';
    public const COMING_SOON_SETTING = 'player_plus_coming_soon_enabled';

    public function comingSoon(): bool
    {
        return !PlayerPlusService::salesEnabled()
            && \App\Models\SiteSetting::get(self::COMING_SOON_SETTING, '1') === '1';
    }

    /** Public offers never grant access to premium rating data. */
    public function allowsMarketing(?User $user): bool
    {
        return $user?->hasRole('super-user') || PlayerPlusService::salesEnabled() || $this->comingSoon();
    }

    /** Discovery is separate from access to a linked player's private history. */
    public function allowsDiscovery(?User $user): bool
    {
        if (!$user) return false;
        if ($user->hasRole('super-user')) return true;

        // Entitlements belong to players; sponsorship and legacy coaching payments grant no access.
        return Gate::has('player-premium-rating-discovery')
            && Gate::forUser($user)->allows('player-premium-rating-discovery');
    }

    public function allows(User $user, Player $player): bool
    {
        if ($user->hasRole('super-user')) return true;

        // A current player link and an active per-player entitlement are both required.
        return ((int) $player->userId === (int) $user->id || $user->players()->whereKey($player->id)->exists())
            && Gate::has('player-premium-rating')
            && Gate::forUser($user)->allows('player-premium-rating', $player);
    }
}
