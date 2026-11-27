<?php

namespace App\Services\Draw;

use App\Models\{Draw, Event, Fixture};
use Illuminate\Support\Facades\DB;

/** A score edit must apply to the exact participants, rules and result that were reviewed. */
final class IndividualScoreRevision
{
    public function forFixture(Fixture $fixture): string
    {
        $fixture->loadMissing('draw.settings', 'fixtureResults', 'orderOfPlay');
        return hash('sha256', json_encode([
            (int) $fixture->draw->event_id,
            $fixture->only(['id', 'draw_id', 'stage', 'registration1_id', 'registration2_id', 'winner_registration', 'match_status', 'draw_group_id', 'parent_fixture_id', 'loser_parent_fixture_id']),
            $fixture->draw->settings?->only(['score_format', 'playoff_score_format', 'num_sets', 'match_duration_minutes', 'changeover_minutes', 'require_full_sets']),
            InterproRoundRobinFormat::selected($fixture->draw) ? $fixture->draw->settings?->playoff_config : null,
            $fixture->draw->settings?->scoreFormatForStage($fixture->stage) === 'timed' ? (int) ($fixture->orderOfPlay?->duration_minutes ?: $fixture->draw->settings?->match_duration_minutes) : null,
            $fixture->fixtureResults->sortBy('set_nr')->map(fn ($set) => $set->only(['id', 'set_nr', 'registration1_score', 'registration2_score']))->values()->all(),
        ]));
    }

    public function forDraw(Draw $draw): array
    {
        return $draw->drawFixtures()->with(['fixtureResults', 'orderOfPlay'])->get()->mapWithKeys(function ($fixture) use ($draw) {
            $fixture->setRelation('draw', $draw);
            return [$fixture->id => $this->forFixture($fixture)];
        })->all();
    }

    public function assertCurrent(Fixture $fixture, mixed $revision): void
    {
        abort_unless(is_string($revision) && strlen($revision) === 64 && hash_equals($this->forFixture($fixture), $revision),
            409, 'This match or its scoring rules changed. Reload the scores before saving or clearing this result.');
    }

    public function locked(Draw $draw, Fixture $fixture, callable $operation): mixed
    {
        return DB::transaction(function () use ($draw, $fixture, $operation) {
            Event::whereKey($draw->event_id)->lockForUpdate()->firstOrFail();
            $currentDraw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $currentDraw->event_id === (int) $draw->event_id, 409, 'The draw changed. Reload before scoring.');
            $currentFixture = Fixture::whereKey($fixture->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $currentFixture->draw_id === (int) $currentDraw->id, 422, 'Fixture does not belong to this draw.');
            $currentFixture->setRelation('draw', $currentDraw);
            return $operation($currentDraw, $currentFixture);
        });
    }
}
