<?php

declare(strict_types=1);

namespace App\Services\TeamSelection;

use App\Models\TeamSelectionInvitation;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class TeamSelectionInvitationDecisionAccessService
{
    public function authorizePlayerDecision(User $actor, TeamSelectionInvitation $invitation): void
    {
        $invitation->loadMissing(['player.user', 'player.users']);
        $player = $invitation->player;

        if (! $player || (
            (int) $player->userId !== (int) $actor->id
            && ! $player->users->contains(fn (User $user): bool => (int) $user->id === (int) $actor->id)
        )) {
            throw new AuthorizationException('You cannot make decisions for this selected player.');
        }
    }
}
