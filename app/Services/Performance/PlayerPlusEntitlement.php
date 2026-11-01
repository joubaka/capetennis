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
            && $this->active()->where('player_id', $player->id)->exists();
    }

    public function discovery(User $user): bool
    {
        return PlayerPlusService::ready()
            && $this->active()->whereIn('player_id', $this->linkedPlayers($user)->select('players.id'))->exists();
    }

    private function active(): Builder
    {
        return PlayerPlusMembership::query()->where('starts_at', '<=', now())->where('ends_at', '>', now())
            ->whereHas('order', fn ($query) => $query->where('status', 'completed')->where('pay_status', true)
                ->where('payfast_paid', true)->whereNotNull('activated_at')
                ->whereColumn('player_plus_orders.player_id', 'player_plus_memberships.player_id'));
    }
}
