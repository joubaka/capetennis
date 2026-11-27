<?php

namespace App\Services\Draw;

use App\Models\{Draw, DrawAuditLog, Fixture};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Two pools qualify into championship/consolation groups, carrying their mutual result once. */
final class TopBottomRoundRobinService
{
    public const FORMAT = 'top_two_bottom_two_rr';

    public function prepare(Draw $draw): Collection
    {
        return DB::transaction(function () use ($draw) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            $draw->load('settings', 'groups.groupRegistrations', 'drawFixtures.fixtureResults');
            abort_unless($draw->hasTopBottomRoundRobin(), 422, 'Choose the top-two and bottom-two round-robin format.');
            if ($draw->groups->contains('round_robin_phase', 2)) {
                $this->assertPreparedStructure($draw);
                return collect();
            }
            abort_if($draw->locked, 409, 'Unlock the draw before preparing its second stage.');
            $sources = $draw->groups->where('round_robin_phase', 1)->sortBy('id')->values();
            abort_unless($sources->count() === 2 && $sources->every(fn ($group) => $group->groupRegistrations->count() === 4),
                422, 'This format requires exactly two first-stage groups of four players.');
            $members = $sources->flatMap->groupRegistrations->pluck('registration_id');
            abort_unless($members->unique()->count() === 8, 422, 'A player may appear only once in the first stage.');
            abort_if(app(GroupAssignmentService::class)->hasRepeatedPlayers($members), 422,
                'A player may appear only once in this draw, including through different registrations.');
            $eligible = app(GroupAssignmentService::class)->eligible($draw)->whereIn('registration_id', $members)->pluck('registration_id');
            abort_if($members->diff($eligible)->isNotEmpty(), 422, 'Review withdrawn or ineligible first-stage entrants before preparing matches.');
            foreach ($sources as $source) {
                $ids = $source->groupRegistrations->pluck('registration_id');
                $pairs = $draw->drawFixtures->where('stage', 'RR')->where('draw_group_id', $source->id)->map(function ($fixture) use ($ids) {
                    abort_unless($ids->contains($fixture->registration1_id) && $ids->contains($fixture->registration2_id)
                        && $fixture->registration1_id !== $fixture->registration2_id, 422, 'Review first-stage fixtures.');
                    $pair = [$fixture->registration1_id, $fixture->registration2_id]; sort($pair); return implode(':', $pair);
                });
                abort_unless($pairs->count() === 6 && $pairs->unique()->count() === 6, 422, 'Create every first-stage match before preparing the second stage.');
            }
            $number = 1 + (int) $draw->drawFixtures->max('match_nr');
            $offset = (int) $draw->drawFixtures->max('round');
            $created = collect();
            foreach ([1 => 'Stage 2 · Championship (positions 1–4)', 3 => 'Stage 2 · Consolation (positions 5–8)'] as $position => $name) {
                $group = $draw->groups()->create(['name' => $name, 'round_robin_phase' => 2, 'qualifying_position' => $position]);
                $slots = $this->sources($draw, $position);
                $fixed = array_shift($slots);
                for ($round = 1; $round <= 3; $round++) {
                    $rotation = array_merge([$fixed], $slots);
                    for ($index = 0; $index < 2; $index++) {
                        [$one, $two] = [$rotation[$index], $rotation[3 - $index]];
                        if ($one[0] === $two[0]) continue; // The original first-stage match is carried, never replayed.
                        $created->push(Fixture::create(['draw_id' => $draw->id, 'draw_group_id' => $group->id,
                            'stage' => 'RR', 'round' => $offset + $round, 'match_nr' => $number++, 'match_status' => 0,
                            'registration1_source_group_id' => $one[0], 'registration1_source_position' => $one[1],
                            'registration2_source_group_id' => $two[0], 'registration2_source_position' => $two[1]]));
                    }
                    array_unshift($slots, array_pop($slots));
                }
            }
            $this->state($draw, 'prepared');
            DrawAuditLog::record($draw->id, 'second_round_robin_prepared', null, ['created' => $created->count(), 'format' => self::FORMAT]);
            return $created;
        });
    }

    /** Qualification identity is the complete source tuple. Same-pool meetings stay in stage 1. */
    private function sources(Draw $draw, int $firstPosition, ?int $restoredId = null): array
    {
        $sources = [];
        foreach ($draw->groups->where('round_robin_phase', 1)->sortBy('id') as $group) {
            $count = TwoStageRoundRobinService::activeCount($draw, $group, $restoredId);
            foreach ([$firstPosition, $firstPosition + 1] as $position) {
                if ($position <= $count) $sources[] = [(int) $group->id, $position];
            }
        }
        return $sources;
    }

    private function pairKey(array $one, array $two): string
    {
        $pair = [implode(':', $one), implode(':', $two)]; sort($pair); return implode('|', $pair);
    }

    private function assertPreparedStructure(Draw $draw, ?int $restoredId = null): void
    {
        $first = $draw->groups->where('round_robin_phase', 1);
        $later = $draw->groups->where('round_robin_phase', 2);
        abort_unless($first->count() === 2 && $first->every(fn ($group) => $group->groupRegistrations->count() === 4) && $later->count() === 2
            && $later->pluck('qualifying_position')->map(fn ($position) => (int) $position)->sort()->values()->all() === [1, 3],
            409, 'Review the championship and consolation group structure.');
        foreach ($later as $group) {
            $slots = $this->sources($draw, (int) $group->qualifying_position, $restoredId);
            $expected = [];
            foreach ($slots as $index => $one) foreach (array_slice($slots, $index + 1) as $two) {
                if ($one[0] !== $two[0]) $expected[] = $this->pairKey($one, $two);
            }
            $actual = [];
            foreach ($draw->drawFixtures->where('draw_group_id', $group->id) as $fixture) {
                abort_unless($fixture->stage === 'RR', 409, 'Review second-stage fixture stages.');
                $actual[] = $this->pairKey([(int) $fixture->registration1_source_group_id, (int) $fixture->registration1_source_position],
                    [(int) $fixture->registration2_source_group_id, (int) $fixture->registration2_source_position]);
            }
            sort($expected); sort($actual);
            abort_unless($expected === $actual, 409, 'Review the complete second-stage source-position programme.');
        }
    }

    public function preparedStructureIsValid(Draw $draw): bool
    {
        $draw->load('groups.groupRegistrations', 'drawFixtures');
        try { $this->assertPreparedStructure($draw); return true; }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { return false; }
    }

    public function reviewEvidence(Draw $draw): array
    {
        $draw->loadMissing('groups.groupRegistrations', 'drawFixtures.fixtureResults');
        $ids = $draw->drawFixtures->modelKeys();
        return [
            'configuration' => $draw->settings->getAttributes(),
            'groups' => $draw->groups->sortBy('id')->map(fn ($group) => [$group->id, $group->round_robin_phase, $group->qualifying_position,
                $group->groupRegistrations->sortBy('id')->map(fn ($member) => [$member->registration_id, $member->seed])->all()])->values()->all(),
            'fixtures' => $draw->drawFixtures->sortBy('id')->map(fn ($fixture) => [$fixture->getAttributes(),
                $fixture->fixtureResults->sortBy('id')->map->getAttributes()->values()->all()])->values()->all(),
            'bookings' => DB::table('order_of_plays')->where('draw_id', $draw->id)->orderBy('id')->get()->all(),
            'legacy' => DB::table('schedules')->whereIn('fixture_id', $ids)->orderBy('id')->get()->all(),
            'publication' => DB::table('published_schedule_assignments')->where('draw_id', $draw->id)->where('fixture_kind', 'individual')->orderBy('id')->get()->all(),
        ];
    }

    public function progress(Draw $draw, array $input): array
    {
        return DB::transaction(function () use ($draw, $input) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            abort_if($draw->locked, 403, 'Unlock the draw before progressing.');
            $draw->drawFixtures()->lockForUpdate()->get(['id']);
            $review = app(TwoStageRoundRobinService::class)->review($draw);
            $required = collect($review['groups'])->pluck('id')->sort()->values()->all();
            $confirmed = collect($input['confirmed_group_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
            abort_unless($confirmed === $required, 422, 'Review and confirm every first-stage group.');
            if ($draw->hasConfirmedSecondRoundRobin()
                && hash_equals((string) ($draw->settings->playoff_config['_second_round_robin']['source_snapshot'] ?? ''), (string) ($input['standings_snapshot'] ?? ''))
                && !empty($input['standings_snapshot'])) return ['created' => 0, 'already_progressed' => true];
            abort_unless(hash_equals($review['snapshot'], (string) ($input['standings_snapshot'] ?? '')), 409, 'The first-stage standings changed. Review every group again.');
            if ($draw->hasConfirmedSecondRoundRobin()) return ['created' => 0, 'already_progressed' => true];
            $created = $this->prepare($draw)->count();
            $draw->load('groups.groupRegistrations', 'drawFixtures.fixtureResults');
            $this->assertPreparedStructure($draw);
            $sourceGroups = collect($review['groups'])->keyBy('id');
            $carriedGroups = [];
            foreach ($draw->groups->where('round_robin_phase', 2) as $group) {
                abort_if($group->groupRegistrations->isNotEmpty(), 409, 'Review the second-stage membership.');
                foreach ($this->sources($draw, (int) $group->qualifying_position) as $seed => [$sourceId, $position]) {
                    $group->registrations()->attach($sourceGroups[$sourceId]['standings'][$position - 1]['registration_id'], ['seed' => $seed + 1]);
                }
                $members = $group->registrations()->pluck('registrations.id');
                $carried = $draw->drawFixtures->where('stage', 'RR')->whereIn('draw_group_id', $required)
                    ->filter(fn ($fixture) => $members->contains((int) $fixture->registration1_id) && $members->contains((int) $fixture->registration2_id));
                $expectedCarried = collect($this->sources($draw, (int) $group->qualifying_position))->groupBy(fn ($source) => $source[0])
                    ->sum(fn ($sources) => $sources->count() * ($sources->count() - 1) / 2);
                abort_unless($carried->count() === $expectedCarried, 409, 'Review the original mutual results before carrying them into stage 2.');
                $carriedGroups[(string) $group->id] = $carried->modelKeys();
                foreach ($draw->drawFixtures->where('draw_group_id', $group->id) as $fixture) {
                    abort_if($fixture->registration1_id || $fixture->registration2_id || $fixture->fixtureResults->isNotEmpty()
                        || (int) $fixture->match_status !== 0, 409, 'Review the second-stage placeholders.');
                    $updates = [];
                    foreach ([1, 2] as $slot) {
                        $source = $sourceGroups->get($fixture->getAttribute("registration{$slot}_source_group_id"));
                        $position = (int) $fixture->getAttribute("registration{$slot}_source_position");
                        abort_unless(isset($source['standings'][$position - 1]), 409, 'Review second-stage qualification sources.');
                        $updates["registration{$slot}_id"] = $source['standings'][$position - 1]['registration_id'];
                    }
                    $fixture->update($updates);
                }
            }
            $this->state($draw, 'confirmed');
            $config = $draw->settings->playoff_config;
            $config['_second_round_robin']['source_snapshot'] = $review['snapshot'];
            $config['_second_round_robin']['carried_groups'] = $carriedGroups;
            $config['_second_round_robin']['carried_fixture_ids'] = collect($carriedGroups)->flatten()->unique()->values()->all();
            $draw->settings->update(['playoff_config' => $config]);
            DrawAuditLog::record($draw->id, 'second_round_robin_progressed', null,
                ['created' => $created, 'format' => self::FORMAT, 'standings_snapshot' => $review['snapshot'], 'source_group_ids' => $required,
                    'carried_groups' => $carriedGroups, 'carried_fixture_ids' => $config['_second_round_robin']['carried_fixture_ids']]);
            return ['created' => $created, 'already_progressed' => false];
        });
    }

    private function state(Draw $draw, string $state): void
    {
        $config = $draw->settings->playoff_config ?? [];
        $config['_second_round_robin'] = array_replace($config['_second_round_robin'] ?? [], ['state' => $state, 'format' => self::FORMAT]);
        $draw->settings->update(['playoff_config' => $config]);
    }

    /** Read-only clones retain the source fixture ID and results; no duplicate match evidence is persisted. */
    public static function effectiveFixtures(Draw $draw, Collection $fixtures): Collection
    {
        $state = $draw->settings?->playoff_config['_second_round_robin'] ?? [];
        if (($state['state'] ?? null) !== 'confirmed') return $fixtures;
        $sourceGroups = $draw->groups->where('round_robin_phase', 1)->keyBy('id');
        $effective = $fixtures;
        foreach ($draw->groups->where('round_robin_phase', 2) as $group) {
            $members = $group->groupRegistrations->pluck('registration_id');
            $carriedIds = $state['carried_groups'][(string) $group->id] ?? [];
            $projected = $fixtures->filter(fn ($fixture) => (int) $fixture->draw_id === (int) $draw->id && $fixture->stage === 'RR'
                && $sourceGroups->has($fixture->draw_group_id) && in_array((int) $fixture->id, $carriedIds, true)
                && $members->contains((int) $fixture->registration1_id) && $members->contains((int) $fixture->registration2_id)
                && $sourceGroups[$fixture->draw_group_id]->groupRegistrations->pluck('registration_id')->contains((int) $fixture->registration1_id)
                && $sourceGroups[$fixture->draw_group_id]->groupRegistrations->pluck('registration_id')->contains((int) $fixture->registration2_id))
                ->map(function ($fixture) use ($draw, $group) {
                    $copy = clone $fixture;
                    $copy->draw_group_id = $group->id;
                    $copy->setRelation('drawGroup', $group);
                    $copy->setRelation('draw', $draw);
                    return $copy;
                });
            $existing = $fixtures->where('draw_group_id', $group->id)->pluck('id');
            $effective = $effective->concat($projected->reject(fn ($fixture) => $existing->contains($fixture->id)));
        }
        return $effective;
    }

    public function reconcileVoidedSources(Draw $draw, int $voidedId): void
    {
        $this->assertPreparedStructure($draw, $voidedId);
        $counts = $draw->groups->where('round_robin_phase', 1)->mapWithKeys(fn ($group) => [$group->id => TwoStageRoundRobinService::activeCount($draw, $group)]);
        $laterIds = $draw->groups->where('round_robin_phase', 2)->pluck('id');
        $removed = $draw->drawFixtures->whereIn('draw_group_id', $laterIds)->filter(fn ($fixture) =>
            (int) $fixture->registration1_source_position > ($counts[$fixture->registration1_source_group_id] ?? 0)
            || (int) $fixture->registration2_source_position > ($counts[$fixture->registration2_source_group_id] ?? 0));
        TwoStageRoundRobinService::pruneUnusedPlaceholders($draw, $removed);
        $draw->load('drawFixtures');
        $this->assertPreparedStructure($draw);
    }
}
