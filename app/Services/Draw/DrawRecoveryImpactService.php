<?php

namespace App\Services\Draw;

use App\Models\Draw;
use App\Models\Fixture;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class DrawRecoveryImpactService
{
    public function preview(Draw $draw, Fixture $source): array
    {
        abort_unless((int) $source->draw_id === (int) $draw->id, 422, 'The source fixture does not belong to this draw.');
        abort_unless($source->stage === 'RR', 422, 'This recovery operation starts from a round-robin result.');

        $playoffs = $draw->drawFixtures()->where('stage', '!=', 'RR')
            ->with(['fixtureResults', 'orderOfPlay'])->orderBy('round')->orderBy('match_nr')->get();
        $scored = $playoffs->filter(fn (Fixture $fixture) => $fixture->fixtureResults->isNotEmpty());
        $scheduled = $playoffs->filter(fn (Fixture $fixture) => $fixture->orderOfPlay !== null);

        return [
            'source_fixture_id' => (int) $source->id,
            'source_match' => $source->match_nr,
            'playoff_fixture_ids' => $playoffs->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'playoff_fixtures' => $playoffs->count(),
            'scored_playoff_fixtures' => $scored->count(),
            'scored_matches' => $scored->map(fn (Fixture $fixture) => [
                'fixture_id' => (int) $fixture->id,
                'stage' => $fixture->stage,
                'round' => $fixture->round,
                'match_nr' => $fixture->match_nr,
                'players' => [$fixture->registration1_id, $fixture->registration2_id],
                'winner' => $fixture->winner_registration,
                'sets' => $fixture->fixtureResults->sortBy('set_nr')->map(fn ($set) => [
                    (int) $set->registration1_score,
                    (int) $set->registration2_score,
                ])->values()->all(),
            ])->values()->all(),
            'scheduled_playoff_fixtures' => $scheduled->count(),
            'scheduled_fixture_ids' => $scheduled->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'requires_super_user' => $scored->isNotEmpty(),
            'effect' => $playoffs->isEmpty()
                ? 'The round-robin result will be corrected and standings recalculated.'
                : 'All playoff fixtures will be removed and must be progressed again from the corrected final standings.',
        ];
    }

    public function assertSafeOrdinaryCorrection(Fixture $fixture): void
    {
        $dependents = $this->dependents($fixture);
        if ($fixture->stage === 'RR') {
            $fixedBracket = $dependents->first(fn (Fixture $dependent) =>
                ! $dependent->registration1_source_group_id
                && ! $dependent->registration2_source_group_id
            );
            abort_if($fixedBracket !== null, 409,
                'The round robin has a generated playoff bracket. Open tournament recovery so it can be snapshotted and rebuilt from corrected standings.');
        }
        $played = $dependents->first(fn (Fixture $dependent) =>
            $dependent->fixtureResults->isNotEmpty()
            || ((int) $dependent->match_status === 1 && $dependent->winner_registration !== null)
        );

        abort_if($played !== null, 409,
            'This result feeds a later played match. Delete that downstream result first, or open tournament recovery to preview and reset the affected chain safely.');
    }

    public function dependents(Fixture $fixture): Collection
    {
        $draw = $fixture->draw ?? Draw::findOrFail($fixture->draw_id);
        if ($fixture->stage === 'RR') {
            return $draw->drawFixtures()->where('stage', '!=', 'RR')->with('fixtureResults')->get();
        }

        $all = $draw->drawFixtures()->with('fixtureResults')->get()->keyBy('id');
        $found = collect();
        $queue = collect([$fixture->parent_fixture_id, $fixture->loser_parent_fixture_id])->filter()->values();
        while ($queue->isNotEmpty()) {
            $id = (int) $queue->shift();
            if ($found->has($id) || ! $all->has($id)) {
                continue;
            }
            $dependent = $all->get($id);
            $found->put($id, $dependent);
            $queue->push($dependent->parent_fixture_id, $dependent->loser_parent_fixture_id);
            $queue = $queue->filter()->values();
        }

        return $found->values();
    }
}
