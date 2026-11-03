<?php

namespace App\Services\Performance;

use App\Models\{Player, PlayerPlusMembership, User};
use Illuminate\Database\Eloquent\Builder;

class PlayerPlusEntitlement
{
    public function linkedPlayers(User $user): Builder
    {
        return Player::query()->where(function ($query) use ($user) {
            $query->where('userId', $user->id)->orWhereIn('id', $user->players()->select('players.id'));
        });
    }

    public function forPlayer(User $user, Player $player): bool
    {
        return PlayerPlusService::ready()
            && $this->linkedPlayers($user)->whereKey($player->id)->exists()
            && $this->activePlayers()->whereKey($player->id)->exists();
    }

    public function discovery(User $user): bool
    {
        return PlayerPlusService::ready()
            && $this->activePlayers()->whereIn('players.id', $this->linkedPlayers($user)->select('players.id'))->exists();
    }

    public function activePlayers(): Builder
    {
        $query = Player::query();
        if (PlayerPlusManagement::ready()) $query->whereDoesntHave('plusOverride', fn ($q) => $q->where('mode', 'suspended'));
        return $query->where(function ($q) {
            $q->whereHas('plusMembership', fn ($membership) => $membership->where('starts_at', '<=', now())->where('ends_at', '>', now())
                ->whereHas('order', fn ($order) => $order->verifiedSettlement()
                    ->whereColumn('player_plus_orders.player_id', 'player_plus_memberships.player_id')));
            if (PlayerPlusManagement::ready()) $q->orWhereHas('plusOverride', fn ($override) => $override
                ->where('mode', 'grant')->where('starts_at', '<=', now())->where('ends_at', '>', now()));
        });
    }
}
