<?php

namespace App\Services\Scoring;

use App\Models\Fixture;
use Illuminate\Support\Collection;

final class FixtureParticipantLabels
{
    /** Prepare display labels only; qualification never makes a fixture ready to score. */
    public function prepare(Collection $fixtures): void
    {
        $draws = $fixtures->pluck('draw')->filter()->unique('id')
            ->filter(fn ($draw) => $draw->flexibleMonrad?->graph
                && (int) $draw->flexibleMonrad->draw_id === (int) $draw->id);
        $numbers = $draws->isEmpty() ? collect() : Fixture::query()
            ->whereIn('draw_id', $draws->pluck('id'))->where('stage', 'FM')
            ->get(['id', 'draw_id', 'match_nr'])->groupBy('draw_id');

        foreach ($fixtures as $fixture) {
            $fixture->setAttribute('scoring_participant_labels', $this->forFixture(
                $fixture, $numbers->get($fixture->draw_id, collect())
            ));
        }
    }

    public function forFixture(Fixture $fixture, Collection $drawFixtures): array
    {
        $draw = $fixture->draw;
        $record = $draw?->flexibleMonrad;
        $map = (array) ($record?->fixture_map ?? []);
        $key = array_search((int) $fixture->id, array_map('intval', $map), true);
        $sources = $record && (int) $record->draw_id === (int) $fixture->draw_id && $key !== false
            ? ($record->graph['nodes'][$key]['sources'] ?? []) : [];
        $drawNumbers = $drawFixtures->where('draw_id', $fixture->draw_id)->keyBy('id');

        $labels = [];
        foreach ([0, 1] as $slot) {
            $registration = $slot === 0 ? $fixture->registration1 : $fixture->registration2;
            $name = $registration?->players?->first()?->full_name;
            $labels[$slot] = $name ?: $this->sourceLabel((array) ($sources[$slot] ?? []), $map, $drawNumbers);
        }

        return $labels;
    }

    private function sourceLabel(array $source, array $map, Collection $fixtures): string
    {
        if (isset($source['base_position']) && (int) $source['base_position'] > 0) {
            return 'Base position '.(int) $source['base_position'];
        }
        if (($source['type'] ?? null) === 'bye') {
            return 'Bye';
        }
        $type = $source['type'] ?? null;
        $sourceKey = $source['original_match'] ?? $source['match'] ?? null;
        $sourceFixture = $sourceKey !== null && isset($map[$sourceKey])
            ? $fixtures->get((int) $map[$sourceKey]) : null;
        if (in_array($type, ['winner', 'loser'], true) && $sourceFixture?->match_nr) {
            return ucfirst($type).' of Match '.$sourceFixture->match_nr;
        }

        return 'To be decided';
    }
}
