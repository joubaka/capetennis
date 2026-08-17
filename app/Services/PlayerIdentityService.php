<?php

namespace App\Services;

use App\Models\Player;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class PlayerIdentityService
{
    /**
     * Find or create the one canonical profile for a name and date of birth.
     *
     * @return array{player: Player, created: bool}
     */
    public function findOrCreate(array $attributes): array
    {
        $attributes['name'] = $this->cleanName($attributes['name'] ?? '');
        $attributes['surname'] = $this->cleanName($attributes['surname'] ?? '');
        $dateOfBirth = (string) ($attributes['dateOfBirth'] ?? '');
        $lockName = 'player-identity:'.hash('sha256', $this->identityKey(
            $attributes['name'],
            $attributes['surname'],
            $dateOfBirth
        ));

        return Cache::lock($lockName, 10)->block(5, function () use ($attributes, $dateOfBirth) {
            $existing = $this->find(
                $attributes['name'],
                $attributes['surname'],
                $dateOfBirth
            );

            if ($existing) {
                return ['player' => $existing, 'created' => false];
            }

            return ['player' => Player::create($attributes), 'created' => true];
        });
    }

    public function find(string $name, string $surname, string $dateOfBirth, ?int $exceptId = null): ?Player
    {
        $identityKey = $this->identityKey($name, $surname, $dateOfBirth);

        return Player::query()
            ->whereDate('dateOfBirth', $dateOfBirth)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->get()
            ->first(fn (Player $player) => $this->identityKey(
                (string) $player->name,
                (string) $player->surname,
                (string) $player->dateOfBirth
            ) === $identityKey);
    }

    public function ensureAvailable(string $name, string $surname, string $dateOfBirth, ?int $exceptId = null): void
    {
        $duplicate = $this->find($name, $surname, $dateOfBirth, $exceptId);

        if ($duplicate) {
            throw ValidationException::withMessages([
                'dateOfBirth' => "A profile for this player and date of birth already exists (profile #{$duplicate->id}). Please use the existing profile.",
            ]);
        }
    }

    private function identityKey(string $name, string $surname, string $dateOfBirth): string
    {
        return $this->normalizeName($name).'|'.$this->normalizeName($surname).'|'.substr($dateOfBirth, 0, 10);
    }

    private function cleanName(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $value));
    }

    private function normalizeName(string $value): string
    {
        return mb_strtolower($this->cleanName($value));
    }
}
