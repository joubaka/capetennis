<?php

namespace App\Services\Scheduling;

use App\Models\{Draw, Event, Fixture};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RoundRobinPlayoffScheduleService
{
    public function prepareEvent(Event $event, array $drawIds = []): int
    {
        $draws = $event->draws()->with(['settings', 'groups'])
            ->when($drawIds, fn ($query) => $query->whereIn('id', array_map('intval', $drawIds)))
            ->get();

        return $draws->sum(fn (Draw $draw) => $this->prepare($draw)->count());
    }

    /**
     * Materialise direct position-pair playoffs such as A1 v A2 or A1 v B1.
     * Larger knockout brackets retain their existing generation workflow.
     */
    public function prepare(Draw $draw): Collection
    {
        $draw->loadMissing(['settings', 'groups']);
        if ($draw->settings?->workflow !== 'round_robin_playoffs'
            || ! $draw->drawFixtures()->where('stage', 'RR')->exists()) {
            return collect();
        }

        return DB::transaction(function () use ($draw) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            $draw->loadMissing(['settings', 'groups']);
            if ($draw->locked) return collect();
            $created = collect();
            $matchNr = max(999, (int) $draw->drawFixtures()->max('match_nr'));

            foreach ($this->pairDefinitions($draw) as $definition) {
                $stageFixtures = $draw->drawFixtures()->where('stage', $definition['stage'])->get();
                $existing = $stageFixtures->first();
                if ($existing) {
                    // Retrofit an unplayed fixture created by the older button,
                    // replacing provisional player names with stable sources.
                    if ($stageFixtures->count() === 1 && (int) $existing->match_status === 0 && ! $existing->fixtureResults()->exists()
                        && ! $existing->registration1_source_group_id
                        && ! $existing->registration2_source_group_id) {
                        $existing->update([
                            'registration1_id' => null,
                            'registration2_id' => null,
                            'registration1_source_group_id' => $definition['sources'][0]['group_id'],
                            'registration1_source_position' => $definition['sources'][0]['position'],
                            'registration2_source_group_id' => $definition['sources'][1]['group_id'],
                            'registration2_source_position' => $definition['sources'][1]['position'],
                        ]);
                    }
                    continue;
                }

                $created->push(Fixture::create([
                    'draw_id' => $draw->id,
                    'stage' => $definition['stage'],
                    'round' => 1,
                    'match_nr' => ++$matchNr,
                    'playoff_type' => $definition['name'],
                    'registration1_source_group_id' => $definition['sources'][0]['group_id'],
                    'registration1_source_position' => $definition['sources'][0]['position'],
                    'registration2_source_group_id' => $definition['sources'][1]['group_id'],
                    'registration2_source_position' => $definition['sources'][1]['position'],
                ]));
            }

            return $created;
        });
    }

    public function supportsEntireConfiguration(Draw $draw): bool
    {
        $draw->loadMissing(['settings', 'groups']);
        $config = collect($draw->settings?->playoff_config ?? [])->where('enabled', true)
            ->reject(fn ($entry) => app(\App\Services\Draw\PlayoffParticipationService::class)->isDormant($draw, $entry));

        return $config->isNotEmpty()
            && $config->every(fn ($entry) => (int) ($entry['size'] ?? 0) === 2)
            && $this->pairDefinitions($draw)->count() === $config->count();
    }

    public function resolveFromStandings(Draw $draw, array $standings, bool $preserveOccupiedSlots = false): int
    {
        $fixtures = $draw->drawFixtures()->where(function ($query) {
            $query->whereNotNull('registration1_source_group_id')
                ->orWhereNotNull('registration2_source_group_id');
        })->with('fixtureResults')->lockForUpdate()->get();
        $resolved = 0;

        foreach ($fixtures as $fixture) {
            if (! $preserveOccupiedSlots && $fixture->fixtureResults->isNotEmpty()) {
                continue;
            }

            $updates = [];
            foreach ([1, 2] as $slot) {
                $groupId = (int) $fixture->getAttribute("registration{$slot}_source_group_id");
                $position = (int) $fixture->getAttribute("registration{$slot}_source_position");
                if (! $groupId) continue;
                $column = "registration{$slot}_id";
                $registrationId = $this->groupIsComplete($draw, $groupId)
                    ? $this->registrationAt($standings[$groupId] ?? [], $position)
                    : null;

                if ($preserveOccupiedSlots && $registrationId === null) {
                    throw new \InvalidArgumentException('A playoff group-position source could not be resolved from this draw\'s completed standings. Review the playoff sources.');
                }
                if ($preserveOccupiedSlots && (int) $fixture->$column !== (int) $registrationId
                    && ($fixture->$column !== null || $fixture->fixtureResults->isNotEmpty()
                        || $fixture->winner_registration !== null || (int) $fixture->match_status !== 0)) {
                    throw new \InvalidArgumentException('An occupied or played playoff slot differs from the confirmed standings. Review the draw through tournament recovery.');
                }
                $updates[$column] = $registrationId;
            }

            if (collect($updates)->contains(fn ($id, $column) => (int) $fixture->$column !== (int) $id)) {
                $fixture->update($updates);
                $resolved++;
            }
        }

        return $resolved;
    }

    public function pairDefinitions(Draw $draw): Collection
    {
        $groups = $draw->groups->sortBy('name')->values();
        if ($groups->isEmpty()) {
            return collect();
        }

        return collect($draw->settings?->playoff_config ?? [])->filter(function ($entry) {
            return ($entry['enabled'] ?? false) && (int) ($entry['size'] ?? 0) === 2;
        })->reject(fn ($entry) => app(\App\Services\Draw\PlayoffParticipationService::class)->isDormant($draw, $entry))->map(function ($entry) use ($groups) {
            $sources = collect($entry['positions'] ?? [])->flatMap(fn ($position) => $groups->map(fn ($group) => [
                'group_id' => (int) $group->id,
                'group_name' => (string) $group->name,
                'position' => (int) $position,
            ]))->values();

            if ($sources->count() !== 2) {
                return null;
            }

            return [
                'stage' => strtoupper((string) ($entry['slug'] ?? 'MAIN')),
                'name' => (string) ($entry['name'] ?? 'Position playoff'),
                'sources' => $sources->all(),
            ];
        })->filter()->values();
    }

    private function groupIsComplete(Draw $draw, int $groupId): bool
    {
        if (! $groupId) {
            return false;
        }

        $fixtures = $draw->drawFixtures()->where('stage', 'RR')->where('draw_group_id', $groupId);
        $total = (clone $fixtures)->count();

        return $total > 0 && (clone $fixtures)->whereHas('fixtureResults')->count() === $total;
    }

    private function registrationAt(array|Collection $rows, int $position): ?int
    {
        $row = collect($rows)->values()->get($position - 1);
        if (is_object($row)) {
            $row = (array) $row;
        }

        $id = $row['registration_id'] ?? $row['reg_id'] ?? null;

        return $id ? (int) $id : null;
    }
}
