<?php

namespace App\Services\Performance;

use App\Models\{Player, SiteSetting, User};
use Illuminate\Support\Facades\Gate;

class PlayerRatingAccess
{
    public const SETTING = 'player_rating_membership_enabled';

    /** Discovery is separate from access to a linked player's private history. */
    public function allowsDiscovery(?User $user): bool
    {
        if (!$user) return false;
        if ($user->hasRole('super-user')) return true;

        // Entitlements belong to players; sponsorship and legacy coaching payments grant no access.
        return SiteSetting::get(self::SETTING, '0') === '1'
            && Gate::has('player-premium-rating-discovery')
            && Gate::forUser($user)->allows('player-premium-rating-discovery');
    }

    public function allows(User $user, Player $player): bool
    {
        if ($user->hasRole('super-user')) return true;

        // A current player link and an active per-player entitlement are both required.
        return SiteSetting::get(self::SETTING, '0') === '1'
            && ((int) $player->userId === (int) $user->id || $user->players()->whereKey($player->id)->exists())
            && Gate::has('player-premium-rating')
            && Gate::forUser($user)->allows('player-premium-rating', $player);
    }
}
