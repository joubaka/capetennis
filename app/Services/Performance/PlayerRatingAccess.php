<?php

namespace App\Services\Performance;

use App\Models\{Player, SiteSetting, User};
use Illuminate\Support\Facades\Gate;

class PlayerRatingAccess
{
    public const SETTING = 'player_rating_membership_enabled';

    public function allows(User $user, Player $player): bool
    {
        if ($user->hasRole('super-user')) return true;

        // The future premium provider must explicitly define this ability.
        // Legacy coaching subscriptions are not a Player Plus entitlement.
        return SiteSetting::get(self::SETTING, '0') === '1'
            && ((int) $player->userId === (int) $user->id || $user->players()->whereKey($player->id)->exists())
            && Gate::has('player-premium-rating')
            && Gate::forUser($user)->allows('player-premium-rating', $player);
    }
}
