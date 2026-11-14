<?php

namespace App\Services\Draw;

use App\Models\Draw;
use App\Models\Fixture;
use App\Models\Registration;
use Illuminate\Support\Collection;

/** Read-only final-position projection from the draw's placement fixtures. */
final class DrawFinalPlacementService
{
    public const ADJUSTMENT_STAGE = 'PLACEMENT_ADJUSTMENT';

    public function forDraw(Draw $draw): Collection
    {
        if ($draw->isTwoStageRoundRobin()) {
            $draw->loadMissing(['groups.groupRegistrations.registration.players', 'drawFixtures.fixtureResults']);
            $standings = app(\App\Domain\Draws\Services\StandingsService::class)->forDraw($draw);
            $rows = collect();
            $offset = $draw->hasHybridRoundRobin() ? 4 : 0;
            if ($draw->hasHybridRoundRobin()) {
                $draw->loadMissing(['drawFixtures.registration1.players', 'drawFixtures.registration2.players']);
                foreach ([1, 3] as $position) {
                    $fixture = $draw->drawFixtures->where('stage', 'MAIN')->firstWhere('position', $position);
                    if ($fixture) $this->applyPair($rows, $fixture, $position);
                }
                $rows = $rows->values();
            }
            foreach ($draw->groups->where('round_robin_phase', 2)->sortBy('qualifying_position') as $group) {
                $fixtures = HybridRoundRobinService::effectiveFixtures($draw)->where('draw_group_id', $group->id)->where('stage', 'RR');
                $groupRows = collect($standings[$group->id] ?? []);
                $complete = app(TwoStageRoundRobinService::class)->complete($draw, $group, $fixtures)
                    && app(TwoStageRoundRobinService::class)->positionsResolved($draw, $groupRows->all(), $fixtures);
                foreach ($groupRows as $index => $standing) {
                    $position = $offset + $index + 1;
                    $registration = $group->groupRegistrations->firstWhere('registration_id', $standing['reg_id'])?->registration;
                    $rows->push($complete && $registration ? $this->resolved($position, $registration) : $this->awaiting($position));
                }
                $offset += $groupRows->count();
            }
            return $rows;
        }
        $draw->loadMissing([
            'settings',
            'drawFixtures.registration1.players',
            'drawFixtures.registration2.players',
        ]);

        $adjustments = $this->adjustmentsForDraw($draw);
        $fixtures = $draw->drawFixtures
            ->where('stage', '!=', 'RR')
            ->where('stage', '!=', self::ADJUSTMENT_STAGE)
            ->values();
        if ($fixtures->isEmpty()) {
            return $this->applyAdjustments(collect(), $adjustments);
        }

        if ($fixtures->contains(fn (Fixture $fixture) => in_array(strtoupper((string) $fixture->stage), ['F', '3/4', 'C-F', '7/8'], true))) {
            $placements = $this->legacyEightPlayerPlacements($fixtures);
        } else {
            $config = collect($draw->settings?->playoff_config ?? [])->where('enabled', true)->values();
            $placements = $config->isNotEmpty()
                ? $this->configuredPlacements($fixtures, $config)
                : $this->terminalPlacements($fixtures);
        }

        $placements = $this->unpairedRoundRobinPosition($draw, $placements);
        return $this->applyAdjustments($placements, $adjustments);
    }

    /** Completed supplementary matches that explicitly change adjacent final positions. */
    public function adjustmentsForDraw(Draw $draw): Collection
    {
        if (! $draw->relationLoaded('drawFixtures')) {
            $draw->load([
                'drawFixtures.registration1.players',
                'drawFixtures.registration2.players',
                'drawFixtures.fixtureResults',
            ]);
        }

        return $draw->drawFixtures
            ->filter(fn (Fixture $fixture) => strtoupper((string) $fixture->stage) === self::ADJUSTMENT_STAGE)
            ->filter(fn (Fixture $fixture) => $fixture->winner_registration
                && $fixture->registration1_id
                && $fixture->registration2_id
                && $fixture->fixtureResults->isNotEmpty())
            ->sortBy(fn (Fixture $fixture) => sprintf('%04d_%010d', (int) $fixture->position, (int) $fixture->id))
            ->values();
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

            if ($size === 3 && ($definition['odd_tail'] ?? false)) {
                $final = $stageFixtures->sortByDesc('round')->first();
                if ($final) $this->applyPair($rows, $final, $start);
                $preliminary = $stageFixtures->first(fn ($f) => $final && (int) $f->parent_fixture_id === (int) $final->id);
                if ($preliminary && $preliminary->winner_registration) {
                    $loser = (int) $preliminary->winner_registration === (int) $preliminary->registration1_id
                        ? $preliminary->registration2 : $preliminary->registration1;
                    if ($loser) $rows->put($start + 2, $this->resolved($start + 2, $loser));
                }
                $positionOffset += 3;
                continue;
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

    /** A completed single-group pair draw may leave only its last player unpaired. */
    private function unpairedRoundRobinPosition(Draw $draw, Collection $placements): Collection
    {
        if ($draw->settings?->workflow !== 'round_robin_playoffs') return $placements;
        $draw->loadMissing(['groups.groupRegistrations.registration.players', 'drawFixtures.fixtureResults']);
        if ($draw->groups->count() !== 1) return $placements;
        $group = $draw->groups->first();
        $n = $group->groupRegistrations->count();
        $config = collect($draw->settings->playoff_config ?? [])->filter(fn ($e) => is_array($e) && ($e['enabled'] ?? false))->values();
        if ($n < 3 || $n % 2 !== 1 || $config->contains(fn ($e) => (int) ($e['size'] ?? 0) !== 2)
            || $config->flatMap(fn ($e) => array_map('intval', $e['positions'] ?? []))->all() !== range(1, $n - 1)) return $placements;
        $fixtures = $draw->drawFixtures->where('stage', 'RR')->where('draw_group_id', $group->id);
        $outcomes = app(\App\Services\IndividualMatchOutcomeService::class);
        $complete = $fixtures->count() === intdiv($n * ($n - 1), 2)
            && $fixtures->every(fn ($f) => $outcomes->winner($f) !== null || $outcomes->isDraw($f));
        $rows = collect(app(\App\Domain\Draws\Services\StandingsService::class)->forDraw($draw)[$group->id] ?? []);
        $complete = $complete && app(TwoStageRoundRobinService::class)->positionsResolved($draw, $rows->values()->all(), $fixtures);
        $last = $rows->values()->last();
        $registration = $group->groupRegistrations->firstWhere('registration_id', $last['reg_id'] ?? null)?->registration;
        return $placements->push($complete && $registration ? $this->resolved($n, $registration) : $this->awaiting($n));
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

    private function applyAdjustments(Collection $placements, Collection $adjustments): Collection
    {
        if ($adjustments->isEmpty()) {
            return $placements;
        }

        $rows = $placements->keyBy(fn (array $placement) => (int) $placement['position']);

        foreach ($adjustments as $fixture) {
            $start = (int) $fixture->position;
            if ($start > 0 && $fixture->winner_registration) {
                $this->applyPair($rows, $fixture, $start);
            }
        }

        // A bye is not a finishing position. Historical bracket projections
        // retain the placeholder, but an explicit correction should publish
        // only the competitors who actually received a final position.
        return $rows
            ->reject(fn (array $placement) => $placement['status'] === 'bye')
            ->sortKeys()
            ->values();
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
