<?php

namespace App\Services;

use App\Models\Player;
use Illuminate\Support\Collection;
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
        return $this->findCandidates($name, $surname, $dateOfBirth, $exceptId)->first();
    }

    /**
     * Return every historical row matching the canonical identity rules.
     *
     * @return Collection<int, Player>
     */
    public function findCandidates(string $name, string $surname, string $dateOfBirth, ?int $exceptId = null): Collection
    {
        $identityKey = $this->identityKey($name, $surname, $dateOfBirth);

        return Player::query()
            ->whereDate('dateOfBirth', $dateOfBirth)
            ->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))
            ->get()
            ->filter(fn (Player $player) => $this->identityKey(
                (string) $player->name,
                (string) $player->surname,
                (string) $player->dateOfBirth
            ) === $identityKey)
            ->values();
    }

    /**
     * Return a bounded set of profiles whose normalized first name and surname match.
     *
     * @return Collection<int, Player>
     */
    public function findNameCandidates(string $name, string $surname, int $limit = 10): Collection
    {
        $normalizedName = $this->normalizeName($name);
        $normalizedSurname = $this->normalizeName($surname);

        return Player::query()
            ->select(['id', 'name', 'surname', 'dateOfBirth'])
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalizedName])
            ->whereRaw('LOWER(TRIM(surname)) = ?', [$normalizedSurname])
            ->orderByDesc('profile_updated_at')
            ->orderBy('id')
            ->limit(max(1, min(20, $limit)))
            ->get()
            ->filter(fn (Player $player) => $this->normalizeName((string) $player->name) === $normalizedName
                && $this->normalizeName((string) $player->surname) === $normalizedSurname)
            ->values();
    }

    /**
     * Load same-name candidates for a bounded integration batch in one query.
     *
     * @return array<int, Collection<int, Player>>
     */
    public function findNameCandidatesFor(array $identities): array
    {
        $pairs = collect($identities)->map(function (array $identity): array {
            $name = $this->normalizeName((string) $identity['first_name']);
            $surname = $this->normalizeName((string) $identity['last_name']);

            return [
                'reference' => (int) $identity['client_reference'],
                'key' => hash('sha256', $name."\0".$surname),
                'name' => $name,
                'surname' => $surname,
            ];
        });
        $references = $pairs->pluck('key', 'reference');
        $uniquePairs = $pairs->unique('key')->values();

        if ($uniquePairs->isEmpty()) {
            return [];
        }

        $candidates = Player::query()
            ->select(['id', 'name', 'surname', 'dateOfBirth'])
            ->where(function ($query) use ($uniquePairs): void {
                foreach ($uniquePairs as $pair) {
                    $query->orWhere(function ($query) use ($pair): void {
                        $query->whereRaw('LOWER(TRIM(name)) = ?', [$pair['name']])
                            ->whereRaw('LOWER(TRIM(surname)) = ?', [$pair['surname']]);
                    });
                }
            })
            ->orderByDesc('profile_updated_at')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Player $player) => hash('sha256',
                $this->normalizeName((string) $player->name)."\0".$this->normalizeName((string) $player->surname)
            ));

        return $references->mapWithKeys(fn (string $key, int $reference): array => [
            $reference => ($candidates->get($key) ?? collect())->values(),
        ])->all();
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
