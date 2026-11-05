<?php

namespace App\Services;

use App\Models\Player;
use App\Models\User;
use App\Services\Performance\PlayerRatingAccess;
use App\Services\Performance\PlayerRatingHistoryDisplay;
use App\Services\Performance\PlayerRatingHistoryService;

/** Read-only navigation context. It never changes a checkout's named player. */
class PlayerWorkspaceContext
{
    public function forPlayer(User $user, ?Player $player = null, ?array $history = null): array
    {
        $players = app(MyTennisService::class)->playersFor($user);
        $related = $player && app(PlayerRelationshipAccess::class)->related($user, $player);
        if ($related && !$players->contains('id', $player->id)) {
            $players = $players->take(MyTennisService::PLAYER_LIMIT - 1)->push($player);
        }
        $allowed = $player && app(PlayerRatingAccess::class)->allows($user, $player);
        return ['players' => $players, 'player' => $player, 'related' => (bool) $related,
            'canViewRating' => (bool) $allowed,
            'snapshot' => $allowed
                ? PlayerRatingHistoryDisplay::snapshot($history ?? app(PlayerRatingHistoryService::class)->forPlayer($player, summaryOnly: true))
                : null];
    }

    public function fromRequest(User $user, ?int $id): array
    {
        // Generic billing pages accept navigation context only from an existing relationship.
        $player = $id ? Player::query()->whereKey($id)->where(fn ($q) => $q->where('userId', $user->id)
            ->orWhereHas('users', fn ($users) => $users->whereKey($user->id)))->first() : null;
        return $this->forPlayer($user, $player);
    }
}
