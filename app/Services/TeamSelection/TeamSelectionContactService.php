<?php

declare(strict_types=1);

namespace App\Services\TeamSelection;

use App\Models\Player;
use Illuminate\Support\Collection;

final class TeamSelectionContactService
{
    /** @return Collection<int, string> */
    public function rawEmails(?Player $player): Collection
    {
        if (! $player) {
            return collect();
        }

        $player->loadMissing(['user', 'users']);

        return collect([$player->email, $player->email_2, $player->email_3, $player->user?->email])
            ->merge($player->users->pluck('email'))
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values();
    }

    /** @return Collection<int, string> */
    public function emails(?Player $player): Collection
    {
        return $this->rawEmails($player)
            ->filter(fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->values();
    }

    public function primaryEmail(?Player $player): ?string
    {
        if (! $player) {
            return null;
        }

        $player->loadMissing(['user', 'users']);

        // Actionable invitations keep their existing contact policy. Update-only
        // addresses must never become the fallback holder of an invitation link.
        return collect([$player->email, $player->user?->email])
            ->merge($player->users->pluck('email'))
            ->map(fn ($email) => mb_strtolower(trim((string) $email)))
            ->first(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL));
    }
}
