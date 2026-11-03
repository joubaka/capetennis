<?php

namespace App\Services;

use App\Models\{Player, User};
use App\Support\Audit\AuditWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Existing relationships are retained; a registration or purchase never establishes one. */
class PlayerRelationshipAccess
{
    public function related(?User $user, Player $player): bool
    {
        return $user && ((int) $player->userId === (int) $user->id || $user->players()->whereKey($player->id)->exists());
    }

    public function staff(?User $user): bool
    {
        return $user?->hasAnyRole(['super-user', 'admin']) === true;
    }

    public function canView(?User $user, Player $player): bool
    {
        return $this->related($user, $player) || $this->staff($user);
    }

    public function link(User $actor, User $target, Player $player, ?string $reason = null): bool
    {
        abort_unless((int) $actor->id === (int) $target->id || $this->staff($actor), 403);
        return DB::transaction(function () use ($actor, $target, $player, $reason): bool {
            User::whereKey($target->id)->lockForUpdate()->firstOrFail();
            $player = Player::whereKey($player->id)->lockForUpdate()->firstOrFail();
            if ($target->players()->whereKey($player->id)->exists()) return false;
            $legacy = (int) $player->userId === (int) $target->id;
            if (!$legacy) {
                abort_unless($this->staff($actor), 403, 'This player is not linked to your account. Ask Cape Tennis support to review the relationship.');
                if (mb_strlen(trim((string) $reason)) < 5) throw ValidationException::withMessages(['reason' => 'Explain the verified player relationship before linking this account.']);
            }
            $target->players()->syncWithoutDetaching([$player->id]);
            $audit = app(AuditWriter::class)->record(['category' => 'authorization', 'action' => 'player.account-linked',
                'actor' => $actor, 'subject' => $player, 'metadata' => ['target_user_id' => $target->id,
                    'relationship_basis' => $legacy ? 'existing_legacy_owner' : 'staff_review', 'reason' => $legacy ? null : trim($reason)]], true);
            if (!$audit) throw new \RuntimeException('The player relationship audit could not be saved. No link was changed.');
            return true;
        });
    }
}
