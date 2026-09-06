<?php

namespace App\Services\Draw;

use App\Models\Draw;
use App\Models\Fixture;
use App\Models\Registration;
use Illuminate\Support\Collection;

/** Read-only final-position projection from the draw's placement fixtures. */
final class DrawFinalPlacementService
{
    public function forDraw(Draw $draw): Collection
    {
        $draw->loadMissing([
            'settings',
            'drawFixtures.registration1.players',
            'drawFixtures.registration2.players',
        ]);

        $fixtures = $draw->drawFixtures->where('stage', '!=', 'RR')->values();
        if ($fixtures->isEmpty()) {
            return collect();
        }

        if ($fixtures->contains(fn (Fixture $fixture) => in_array(strtoupper((string) $fixture->stage), ['F', '3/4', 'C-F', '7/8'], true))) {
            return $this->legacyEightPlayerPlacements($fixtures);
        }

        $config = collect($draw->settings?->playoff_config ?? [])->where('enabled', true)->values();
        if ($config->isNotEmpty()) {
            return $this->configuredPlacements($fixtures, $config);
        }

        return $this->terminalPlacements($fixtures);
    }

    private function configuredPlacements(Collection $fixtures, Collection $config): Collection
    {
        $rows = collect();
        $positionOffset = 0;

        foreach ($config as $definition) {
            $size = max(2, (int) ($definition['size'] ?? 2));
            $start = $positionOffset + 1;
            $stage = strtoupper((string) ($definition['slug'] ?? 'MAIN'));
            $stageFixtures = $fixtures->where('stage', $stage)->values();

            for ($position = $start; $position < $start + $size; $position++) {
                $rows->put($position, $this->awaiting($position));
            }

            if ($size === 2) {
                $fixture = $stageFixtures->sortBy([
                    ['round', 'desc'],
                    ['match_nr', 'desc'],
                ])->first();
                if ($fixture) {
                    $this->applyPair($rows, $fixture, $start);
                }
                $positionOffset += $size;
                continue;
            }

            $final = $stageFixtures
                ->filter(fn (Fixture $fixture) => blank($fixture->playoff_type))
                ->sortBy([
                    ['round', 'desc'],
                    ['match_nr', 'desc'],
                ])->first();
            if ($final) {
                $this->applyPair($rows, $final, $start);
            }

            $stageFixtures
                ->filter(fn (Fixture $fixture) => filled($fixture->playoff_type)
                    && ! str_contains((string) $fixture->playoff_type, 'cons_sf')
                    && (int) $fixture->position >= $start
                    && (int) $fixture->position < $start + $size)
                ->each(fn (Fixture $fixture) => $this->applyPair($rows, $fixture, (int) $fixture->position));

            $positionOffset += $size;
        }

        return $rows->sortKeys()->values();
    }

    private function legacyEightPlayerPlacements(Collection $fixtures): Collection
    {
        $rows = collect();
        $groups = $fixtures->groupBy(fn (Fixture $fixture) => (int) ($fixture->bracket_id ?: 1))->sortKeys();

        foreach ($groups->values() as $index => $groupFixtures) {
            $base = ($index * 8) + 1;
            for ($position = $base; $position < $base + 8; $position++) {
                $rows->put($position, $this->awaiting($position));
            }

            foreach (['F' => 0, '3/4' => 2, 'C-F' => 4, '7/8' => 6] as $stage => $offset) {
                $fixture = $groupFixtures->first(fn (Fixture $candidate) => strtoupper((string) $candidate->stage) === $stage);
                if ($fixture) {
                    $this->applyPair($rows, $fixture, $base + $offset);
                }
            }
        }

        return $rows->sortKeys()->values();
    }

    private function terminalPlacements(Collection $fixtures): Collection
    {
        $rows = collect();
        $referenced = $fixtures->pluck('parent_fixture_id')
            ->merge($fixtures->pluck('loser_parent_fixture_id'))
            ->filter()->map(fn ($id) => (int) $id)->all();

        foreach ($fixtures->reject(fn (Fixture $fixture) => in_array((int) $fixture->id, $referenced, true)) as $fixture) {
            $start = $this->legacyMatchPosition($fixture)
                ?? $this->labelPosition($fixture->playoff_type)
                ?? ((int) $fixture->position > 0 ? (int) $fixture->position : null);
            if (! $start) {
                continue;
            }
            $this->applyPair($rows, $fixture, $start);
        }

        return $rows->sortKeys()->values();
    }

    private function applyPair(Collection $rows, Fixture $fixture, int $start): void
    {
        $first = $fixture->registration1;
        $second = $fixture->registration2;
        $winnerId = (int) ($fixture->winner_registration ?? 0);

        if (! $winnerId) {
            $rows->put($start, $this->awaiting($start));
            $rows->put($start + 1, $this->awaiting($start + 1));
            return;
        }

        if ((int) ($first?->id ?? 0) === $winnerId) {
            $winner = $first;
            $loser = $second;
        } elseif ((int) ($second?->id ?? 0) === $winnerId) {
            $winner = $second;
            $loser = $first;
        } else {
            $rows->put($start, $this->awaiting($start));
            $rows->put($start + 1, $this->awaiting($start + 1));
            return;
        }

        $rows->put($start, $winner ? $this->resolved($start, $winner) : $this->awaiting($start));
        $rows->put($start + 1, $loser
            ? $this->resolved($start + 1, $loser)
            : $this->bye($start + 1));
    }

    private function resolved(int $position, Registration $registration): array
    {
        return [
            'position' => $position,
            'registration_id' => (int) $registration->id,
            'name' => $registration->display_name ?: $registration->players->pluck('full_name')->join(' / '),
            'status' => 'resolved',
        ];
    }

    private function awaiting(int $position): array
    {
        return ['position' => $position, 'registration_id' => null, 'name' => 'Awaiting result', 'status' => 'awaiting'];
    }

    private function bye(int $position): array
    {
        return ['position' => $position, 'registration_id' => null, 'name' => 'Bye · no position awarded', 'status' => 'bye'];
    }

    private function legacyMatchPosition(Fixture $fixture): ?int
    {
        return match ((int) $fixture->match_nr) {
            2003 => 1,
            2004 => 3,
            default => null,
        };
    }

    private function labelPosition(?string $label): ?int
    {
        return $label && preg_match('/(^|\D)(\d{1,3})(?:st|nd|rd|th)?\s*\//i', $label, $match)
            ? (int) $match[2]
            : null;
    }
}
