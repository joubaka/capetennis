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

        return collect([$player->email, $player->user?->email])
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
        return $this->emails($player)->first();
    }
}
