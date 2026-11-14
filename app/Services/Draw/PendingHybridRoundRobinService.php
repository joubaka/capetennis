<?php

namespace App\Services\Draw;

use App\Models\{Draw, DrawAuditLog, Fixture};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{DB, Gate};

/** Prepare and qualify a selected hybrid without replacing any match or booking. */
final class PendingHybridRoundRobinService
{
    private function sourceGroups(Draw $draw): Collection
    {
        $draw->load(['settings', 'groups.groupRegistrations', 'drawFixtures.fixtureResults']);
        $groups = $draw->groups->where('round_robin_phase', 1)->sortBy('id')->values();
        abort_unless($draw->hasHybridRoundRobin() && !$draw->isTeamDraw() && !$draw->event?->isInterprovincialTrials()
            && $groups->count() === 2 && $groups->every(fn ($group) => $group->groupRegistrations->count() >= 3),
            422, 'The hybrid format needs two first-stage groups with at least three players each.');
        $ids = $groups->flatMap->groupRegistrations->pluck('registration_id');
        abort_unless($ids->count() === $ids->unique()->count(), 422, 'A player may appear only once in the first stage.');
        $eligible = app(GroupAssignmentService::class)->eligible($draw)->pluck('registration_id');
        abort_if($ids->diff($eligible)->isNotEmpty(), 422, 'Review withdrawn or ineligible first-stage entrants.');
        foreach ($groups as $group) {
            $members = $group->groupRegistrations->pluck('registration_id');
            $fixtures = $draw->drawFixtures->where('stage', 'RR')->where('draw_group_id', $group->id);
            $pairs = $fixtures->map(function ($fixture) use ($members) {
                abort_unless($members->contains($fixture->registration1_id) && $members->contains($fixture->registration2_id)
                    && $fixture->registration1_id !== $fixture->registration2_id, 422, 'Review the first-stage match programme.');
                $pair = [(int) $fixture->registration1_id, (int) $fixture->registration2_id]; sort($pair); return implode(':', $pair);
            });
            abort_unless($pairs->count() === intdiv($members->count() * ($members->count() - 1), 2)
                && $pairs->unique()->count() === $pairs->count(), 422, 'Create every first-stage match before preparing the hybrid stage.');
        }
        return $groups;
    }

    /** Rotate the full lower field; only cross-group pairs need new fixtures. */
    private function crossPairs(Collection $groups): array
    {
        $slots = [];
        foreach ($groups as $group) foreach (range(3, $group->groupRegistrations->count()) as $position) {
            $slots[] = ['group_id' => $group->id, 'position' => $position];
        }
        if (count($slots) % 2) $slots[] = null;
        $fixed = array_shift($slots); $pairs = [];
        for ($round = 1; $round <= count($slots); $round++) {
            $line = array_merge([$fixed], $slots);
            for ($i = 0; $i < count($line) / 2; $i++) {
                $one = $line[$i]; $two = $line[count($line) - 1 - $i];
                if (!$one || !$two || $one['group_id'] === $two['group_id']) continue;
                $pairs[] = ['one' => $one, 'two' => $two, 'round' => $round];
            }
            array_unshift($slots, array_pop($slots));
        }
        return $pairs;
    }

    public function prepare(Draw $draw): Collection
    {
        return DB::transaction(function () use ($draw) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            abort_if($draw->locked, 409, 'Unlock the draw before preparing its hybrid stage.');
            $groups = $this->sourceGroups($draw);
            if ($draw->hasConfirmedSecondRoundRobin()) return collect();
            if ($draw->groups->contains('round_robin_phase', 2)) {
                $this->assertPrepared($draw, $groups);
                return collect();
            }
            abort_if($draw->published || $draw->oop_published, 409, 'Prepare the hybrid programme before publishing its draw or schedule.');
            abort_if($draw->drawFixtures->contains(fn ($fixture) => $fixture->stage !== 'RR'
                || !$groups->contains('id', $fixture->draw_group_id)), 409,
                'Existing later-stage matches cannot be replaced by hybrid preparation.');
            $lowerCount = $groups->sum(fn ($group) => $group->groupRegistrations->count() - 2);
            $lower = $draw->groups()->create(['name' => 'Stage 2 · Positions 5–'.(4 + $lowerCount),
                'round_robin_phase' => 2, 'qualifying_position' => 3]);
            $number = (int) $draw->drawFixtures->max('match_nr');
            $offset = (int) $draw->drawFixtures->max('round');
            $created = collect(); $lowerIds = [];
            foreach ($this->crossPairs($groups) as $pair) {
                $fixture = Fixture::create(['draw_id' => $draw->id, 'draw_group_id' => $lower->id,
                    'stage' => 'RR', 'round' => $offset + $pair['round'], 'match_nr' => ++$number, 'match_status' => 0,
                    'registration1_source_group_id' => $pair['one']['group_id'], 'registration1_source_position' => $pair['one']['position'],
                    'registration2_source_group_id' => $pair['two']['group_id'], 'registration2_source_position' => $pair['two']['position']]);
                $created->push($fixture); $lowerIds[] = $fixture->id;
            }
            $semis = [];
            foreach ([0, 1] as $index) {
                $semi = Fixture::create(['draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 1,
                    'bracket_id' => 1, 'match_nr' => ++$number, 'match_status' => 0, 'feeder_slot' => $index + 1, 'loser_feeder_slot' => $index + 1,
                    'registration1_source_group_id' => $groups[$index]->id, 'registration1_source_position' => 1,
                    'registration2_source_group_id' => $groups[1 - $index]->id, 'registration2_source_position' => 2]);
                $created->push($semi); $semis[] = $semi;
            }
            $final = Fixture::create(['draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 2, 'bracket_id' => 1,
                'match_nr' => ++$number, 'match_status' => 0, 'position' => 1]);
            $bronze = Fixture::create(['draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 2, 'bracket_id' => 1,
                'match_nr' => ++$number, 'match_status' => 0, 'position' => 3, 'playoff_type' => '3rd/4th']);
            foreach ($semis as $semi) $semi->update(['parent_fixture_id' => $final->id, 'loser_parent_fixture_id' => $bronze->id]);
            $created->push($final, $bronze);
            $config = $draw->settings->playoff_config ?? [];
            $config['_second_round_robin'] = ['state' => 'prepared', 'format' => HybridRoundRobinService::FORMAT,
                'source_group_ids' => $groups->pluck('id')->all(), 'source_group_sizes' => $groups->map(fn ($group) => $group->groupRegistrations->count())->all(),
                'lower_group_id' => $lower->id, 'lower_fixture_ids' => $lowerIds, 'semi_fixture_ids' => array_map(fn ($fixture) => $fixture->id, $semis),
                'final_fixture_id' => $final->id, 'bronze_fixture_id' => $bronze->id,
                'prepared_fixture_ids' => $created->pluck('id')->all(), 'carried_fixture_ids' => []];
            $draw->settings->update(['playoff_config' => $config]);
            DrawAuditLog::record($draw->id, 'hybrid_round_robin_prepared', null, ['created_fixture_ids' => $created->pluck('id')->all()]);
            return $created;
        });
    }

    private function assertPrepared(Draw $draw, Collection $groups): void
    {
        $config = $draw->settings->playoff_config['_second_round_robin'];
        $championship = collect($draw->settings->playoff_config)->first(fn ($entry) => is_array($entry) && strtoupper($entry['slug'] ?? '') === 'MAIN');
        abort_unless($championship && ($championship['enabled'] ?? false) && (int) ($championship['size'] ?? 0) === 4
            && array_map('intval', $championship['positions'] ?? []) === [1, 2], 409, 'Review the hybrid top-four playoff configuration.');
        $lower = $draw->groups->where('round_robin_phase', 2);
        abort_unless($lower->count() === 1 && (int) $lower->first()->id === (int) ($config['lower_group_id'] ?? 0)
            && $lower->first()->groupRegistrations->isEmpty()
            && $groups->pluck('id')->all() === ($config['source_group_ids'] ?? [])
            && $groups->map(fn ($group) => $group->groupRegistrations->count())->all() === ($config['source_group_sizes'] ?? []),
            409, 'Review the prepared hybrid groups and source membership.');
        $later = $draw->drawFixtures->reject(fn ($fixture) => $groups->contains('id', $fixture->draw_group_id));
        $actualIds = $later->modelKeys(); $expectedIds = $config['prepared_fixture_ids'] ?? []; sort($actualIds); sort($expectedIds);
        abort_unless($actualIds === $expectedIds && $later->every(fn ($fixture) => !$fixture->registration1_id && !$fixture->registration2_id
            && !$fixture->winner_registration && (int) $fixture->match_status === 0 && $fixture->fixtureResults->isEmpty()),
            409, 'Review the prepared hybrid fixtures; occupied or played matches cannot be replaced.');
        $crossPairs = $this->crossPairs($groups);
        abort_unless(count($config['lower_fixture_ids'] ?? []) === count($crossPairs), 409, 'Review the complete hybrid cross-group programme.');
        foreach ($crossPairs as $index => $pair) {
            $fixture = $later->firstWhere('id', $config['lower_fixture_ids'][$index]);
            abort_unless($fixture && $fixture->stage === 'RR' && (int) $fixture->draw_group_id === (int) $lower->first()->id
                && [(int) $fixture->registration1_source_group_id, (int) $fixture->registration1_source_position,
                    (int) $fixture->registration2_source_group_id, (int) $fixture->registration2_source_position]
                === [(int) $pair['one']['group_id'], $pair['one']['position'], (int) $pair['two']['group_id'], $pair['two']['position']],
                409, 'Review the hybrid cross-group qualification sources.');
        }
        foreach ([0, 1] as $index) {
            $fixture = $later->firstWhere('id', $config['semi_fixture_ids'][$index] ?? 0);
            abort_unless($fixture && $fixture->stage === 'MAIN' && (int) $fixture->round === 1
                && (int) $fixture->registration1_source_group_id === (int) $groups[$index]->id && (int) $fixture->registration1_source_position === 1
                && (int) $fixture->registration2_source_group_id === (int) $groups[1 - $index]->id && (int) $fixture->registration2_source_position === 2
                && (int) $fixture->feeder_slot === $index + 1 && (int) $fixture->loser_feeder_slot === $index + 1
                && (int) $fixture->parent_fixture_id === (int) ($config['final_fixture_id'] ?? 0)
                && (int) $fixture->loser_parent_fixture_id === (int) ($config['bronze_fixture_id'] ?? 0),
                409, 'Review the crossed semifinal qualification and advancement sources.');
        }
        foreach (['final_fixture_id' => 1, 'bronze_fixture_id' => 3] as $key => $position) {
            $fixture = $later->firstWhere('id', $config[$key] ?? 0);
            abort_unless($fixture && $fixture->stage === 'MAIN' && (int) $fixture->round === 2 && (int) $fixture->position === $position
                && !$fixture->registration1_source_group_id && !$fixture->registration2_source_group_id
                && !$fixture->parent_fixture_id && !$fixture->loser_parent_fixture_id, 409, 'Review the hybrid placement finals.');
        }
        abort_if(Fixture::where('draw_id', '!=', $draw->id)->where(fn ($query) => $query->whereIn('parent_fixture_id', $expectedIds)
            ->orWhereIn('loser_parent_fixture_id', $expectedIds))->exists(), 409, 'A hybrid fixture has an external dependency.');
    }

    public function preparedStructureIsValid(Draw $draw): bool
    {
        try { $groups = $this->sourceGroups($draw); $this->assertPrepared($draw, $groups); return true; }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) { return false; }
    }

    public function decorateReview(Draw $draw, array $review): array
    {
        if ($draw->hasConfirmedSecondRoundRobin()) return $review;
        $groups = $this->sourceGroups($draw); $this->assertPrepared($draw, $groups);
        $evidence = [$review, $draw->getAttributes(), $draw->settings->getAttributes(),
            $draw->groups->map(fn ($group) => [$group->getAttributes(), $group->groupRegistrations->toArray()])->all(),
            $draw->drawFixtures->map(fn ($fixture) => [$fixture->getAttributes(), $fixture->fixtureResults->toArray()])->all(),
            DB::table('order_of_plays')->where('draw_id', $draw->id)->orderBy('id')->get()->all(),
            DB::table('schedules')->whereIn('fixture_id', $draw->drawFixtures->modelKeys())->orderBy('id')->get()->all(),
            DB::table('published_schedule_assignments')->where('draw_id', $draw->id)->orderBy('id')->get()->all()];
        return array_replace($review, ['snapshot' => hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR))]);
    }

    public function progress(Draw $draw, array $input): array
    {
        return DB::transaction(function () use ($draw, $input) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            Gate::authorize('progress', $draw);
            $draw->drawFixtures()->lockForUpdate()->get(); $draw->groups()->lockForUpdate()->get(); $draw->settings()->lockForUpdate()->first();
            $review = app(TwoStageRoundRobinService::class)->review($draw);
            $confirmed = collect($input['confirmed_group_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
            $required = collect($review['groups'])->pluck('id')->sort()->values()->all();
            abort_unless($confirmed === $required, 422, 'Review and confirm every first-stage group.');
            $config = $draw->settings->playoff_config;
            $state = $config['_second_round_robin'];
            $snapshot = (string) ($input['standings_snapshot'] ?? '');
            $accepted = [$review['snapshot']];
            if ($draw->hasConfirmedSecondRoundRobin()) $accepted[] = $state['source_snapshot'] ?? '';
            abort_unless($snapshot !== '' && in_array($snapshot, $accepted, true), 409, 'The hybrid draw changed. Review every group again.');
            if ($draw->hasConfirmedSecondRoundRobin()) return ['created' => 0, 'already_progressed' => true];
            $groups = collect($review['groups'])->keyBy('id');
            $lowerIds = $groups->flatMap(fn ($group) => array_slice($group['standings'], 2))->pluck('registration_id')->map(fn ($id) => (int) $id)->values();
            $lower = $draw->groups->firstWhere('id', $state['lower_group_id']);
            foreach ($lowerIds as $index => $id) $lower->registrations()->attach($id, ['seed' => $index + 1]);
            foreach ($draw->drawFixtures->whereIn('id', array_merge($state['lower_fixture_ids'], $state['semi_fixture_ids'])) as $fixture) {
                $updates = [];
                foreach ([1, 2] as $slot) {
                    $source = $groups->get($fixture->getAttribute("registration{$slot}_source_group_id"));
                    $position = (int) $fixture->getAttribute("registration{$slot}_source_position");
                    $id = $source['standings'][$position - 1]['registration_id'] ?? null;
                    abort_unless($id, 409, 'A hybrid qualification source could not be resolved.');
                    $updates["registration{$slot}_id"] = $id;
                }
                $fixture->update($updates);
            }
            $sourceIds = collect($review['groups'])->pluck('id');
            $carried = $draw->drawFixtures->where('stage', 'RR')->whereIn('draw_group_id', $sourceIds)
                ->filter(fn ($fixture) => $lowerIds->contains((int) $fixture->registration1_id) && $lowerIds->contains((int) $fixture->registration2_id))->modelKeys();
            $config['_second_round_robin'] = array_replace($state, ['state' => 'confirmed', 'carried_fixture_ids' => $carried, 'source_snapshot' => $snapshot]);
            $draw->settings->update(['playoff_config' => $config]);
            DrawAuditLog::record($draw->id, 'hybrid_round_robin_progressed', null,
                ['source_group_ids' => $required, 'carried_fixture_ids' => $carried, 'standings_snapshot' => $snapshot, 'preserved_fixture_ids' => $draw->drawFixtures->modelKeys()]);
            return ['created' => 0, 'already_progressed' => false];
        });
    }
}
