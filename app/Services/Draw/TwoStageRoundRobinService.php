<?php

namespace App\Services\Draw;

use App\Domain\Draws\Services\{ScoreValidationService, StandingsService};
use App\Models\{Draw, DrawAuditLog, Fixture};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Equal finishing positions qualify into independent second-phase groups. */
final class TwoStageRoundRobinService
{
    public function review(Draw $draw): array
    {
        $draw->load(['settings', 'groups.groupRegistrations.registration.players', 'drawFixtures.fixtureResults']);
        abort_unless($draw->isTwoStageRoundRobin(), 422, 'Choose round robin → round robin.');
        $groups = $draw->groups->filter(fn ($group) => (int) $group->round_robin_phase === 1)->sortBy('id');
        abort_unless($groups->count() >= 2, 422, 'Create at least two first-stage groups.');
        $allIds = $groups->flatMap->groupRegistrations->pluck('registration_id');
        abort_unless($allIds->count() === $allIds->unique()->count(), 422, 'A player may appear only once in the first stage.');
        $eligible = app(GroupAssignmentService::class)->eligible($draw)->whereIn('registration_id', $allIds)->pluck('registration_id')->unique();
        abort_if($allIds->diff($eligible)->isNotEmpty(), 422, 'Review withdrawn or ineligible first-stage entrants before progression.');
        $standings = app(StandingsService::class)->forDraw($draw);
        $review = [];
        foreach ($groups as $group) {
            $fixtures = $draw->drawFixtures->where('draw_group_id', $group->id)->where('stage', 'RR');
            $rows = array_values($standings[$group->id] ?? []);
            abort_unless($group->groupRegistrations->count() >= 2 && $this->complete($draw, $group, $fixtures), 422,
                'Complete every first-stage round-robin match before progressing.');
            abort_unless($this->positionsResolved($draw, $rows, $fixtures), 422,
                'Resolve the tied first-stage positions before progressing.');
            $review[] = ['id' => (int) $group->id, 'name' => (string) $group->name,
                'standings' => collect($rows)->map(fn ($row, $index) => $row + [
                    'position' => $index + 1, 'registration_id' => (int) $row['reg_id'],
                    'played' => (int) $row['wins'] + (int) $row['losses'] + (int) ($row['draws'] ?? 0),
                ])->all()];
        }
        $evidence = $draw->drawFixtures->whereIn('draw_group_id', $groups->pluck('id'))->map(fn ($fixture) => [
            $fixture->id, $fixture->registration1_id, $fixture->registration2_id,
            $fixture->fixtureResults->map(fn ($set) => [$set->set_nr, $set->registration1_score, $set->registration2_score])->all(),
        ])->all();

        return ['groups' => $review, 'destination' => 'second round robin',
            'snapshot' => hash('sha256', json_encode([$review, $evidence]))];
    }

    public function progress(Draw $draw, array $input): array
    {
        return DB::transaction(function () use ($draw, $input) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            abort_if($draw->locked, 403, 'Unlock the draw before progressing.');
            $draw->drawFixtures()->lockForUpdate()->get(['id']);
            $review = $this->review($draw);
            $confirmed = collect($input['confirmed_group_ids'] ?? [])->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();
            $required = collect($review['groups'])->pluck('id')->sort()->values()->all();
            abort_unless($confirmed === $required, 422, 'Review and confirm every first-stage group.');
            abort_unless(hash_equals($review['snapshot'], (string) ($input['standings_snapshot'] ?? '')), 409,
                'The first-stage standings changed. Review every group again.');
            if ($draw->hasConfirmedSecondRoundRobin()) return ['created' => 0, 'already_progressed' => true];
            $created = $this->prepare($draw)->count();
            $draw->load('groups.groupRegistrations', 'drawFixtures');
            $sourceGroups = collect($review['groups']);
            $this->assertPreparedStructure($draw, $sourceGroups);
            foreach ($draw->groups->where('round_robin_phase', 2) as $group) {
                $position = (int) $group->qualifying_position;
                $qualifiers = $sourceGroups->filter(fn ($group) => count($group['standings']) >= $position)->values();
                abort_if($group->groupRegistrations->isNotEmpty(), 409, 'Review the second-stage membership.');
                foreach ($qualifiers as $seed => $source) {
                    $group->registrations()->attach($source['standings'][$position - 1]['registration_id'], ['seed' => $seed + 1]);
                }
                foreach ($draw->drawFixtures->where('draw_group_id', $group->id) as $fixture) {
                    abort_if($fixture->registration1_id || $fixture->registration2_id || $fixture->fixtureResults()->exists()
                        || (int) $fixture->match_status !== 0, 409, 'Review the second-stage placeholders.');
                    $updates = [];
                    foreach ([1, 2] as $slot) {
                        $source = $qualifiers->firstWhere('id', $fixture->getAttribute("registration{$slot}_source_group_id"));
                        abort_unless($source && (int) $fixture->getAttribute("registration{$slot}_source_position") === $position,
                            409, 'Review the second-stage qualification sources.');
                        $updates["registration{$slot}_id"] = $source['standings'][$position - 1]['registration_id'];
                    }
                    $fixture->update($updates);
                }
            }
            $this->setState($draw, 'confirmed');
            DrawAuditLog::record($draw->id, 'second_round_robin_progressed', null,
                ['created' => $created, 'standings_snapshot' => $review['snapshot'], 'source_group_ids' => $required]);

            return ['created' => $created, 'already_progressed' => false];
        });
    }

    /** Stable source-position fixtures are schedulable before anyone qualifies. */
    public function prepare(Draw $draw): Collection
    {
        if (! $draw->isTwoStageRoundRobin()) return collect();
        return DB::transaction(function () use ($draw) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            $draw->load('settings', 'groups.groupRegistrations');
            if ($draw->groups->contains('round_robin_phase', 2)) return collect();
            abort_if($draw->locked, 409, 'Unlock the draw before preparing its second stage.');
            $groups = $draw->groups->where('round_robin_phase', 1)->sortBy('id')->values();
            abort_unless($groups->count() >= 2 && $groups->every(fn ($group) => $group->groupRegistrations->count() >= 2),
                422, 'Create at least two first-stage groups with two or more entrants.');
            $members = $groups->flatMap->groupRegistrations->pluck('registration_id');
            abort_unless($members->count() === $members->unique()->count(), 422, 'A player may appear only once in the first stage.');
            $eligible = app(GroupAssignmentService::class)->eligible($draw)->whereIn('registration_id', $members)->pluck('registration_id');
            abort_if($members->diff($eligible)->isNotEmpty(), 422, 'Review withdrawn or ineligible first-stage entrants before preparing matches.');
            foreach ($groups as $group) {
                $ids = $group->groupRegistrations->pluck('registration_id');
                $pairs = $draw->drawFixtures()->where('stage', 'RR')->where('draw_group_id', $group->id)->get()->map(function ($fixture) use ($ids) {
                    abort_unless($ids->contains($fixture->registration1_id) && $ids->contains($fixture->registration2_id)
                        && $fixture->registration1_id !== $fixture->registration2_id, 422, 'Review first-stage fixtures.');
                    $pair = [$fixture->registration1_id, $fixture->registration2_id]; sort($pair); return implode(':', $pair);
                });
                abort_unless($pairs->count() === $ids->count() * ($ids->count() - 1) / 2 && $pairs->unique()->count() === $pairs->count(),
                    422, 'Create every first-stage match before preparing the second stage.');
            }
            $nextMatch = 1 + (int) $draw->drawFixtures()->max('match_nr');
            $offset = (int) $draw->drawFixtures()->max('round');
            $created = collect();
            for ($position = 1; $position <= $groups->max(fn ($group) => $group->groupRegistrations->count()); $position++) {
                $sources = $groups->filter(fn ($group) => $group->groupRegistrations->count() >= $position)->pluck('id')->all();
                $group = $draw->groups()->create(['name' => 'Stage 2 · Group '.$position,
                    'round_robin_phase' => 2, 'qualifying_position' => $position]);
                if (count($sources) < 2) continue;
                if (count($sources) % 2) $sources[] = null;
                $fixed = array_shift($sources);
                $rounds = count($sources);
                for ($round = 1; $round <= $rounds; $round++) {
                    $slots = array_merge([$fixed], $sources);
                    for ($index = 0; $index < count($slots) / 2; $index++) {
                        $one = $slots[$index]; $two = $slots[count($slots) - 1 - $index];
                        if ($one === null || $two === null) continue;
                        $created->push(Fixture::create(['draw_id' => $draw->id, 'draw_group_id' => $group->id,
                            'stage' => 'RR', 'round' => $offset + $round, 'match_nr' => $nextMatch++, 'match_status' => 0,
                            'registration1_source_group_id' => $one, 'registration1_source_position' => $position,
                            'registration2_source_group_id' => $two, 'registration2_source_position' => $position]));
                    }
                    array_unshift($sources, array_pop($sources));
                }
            }
            $this->setState($draw, 'prepared');
            DrawAuditLog::record($draw->id, 'second_round_robin_prepared', null, ['created' => $created->count()]);
            return $created;
        });
    }

    private function setState(Draw $draw, string $state): void
    {
        $config = $draw->settings->playoff_config ?? [];
        $config['_second_round_robin'] = ['state' => $state];
        $draw->settings->update(['playoff_config' => $config]);
    }

    private function assertPreparedStructure(Draw $draw, Collection $sources): void
    {
        $positions = (int) $sources->max(fn ($source) => count($source['standings']));
        $groups = $draw->groups->where('round_robin_phase', 2);
        abort_unless($groups->count() === $positions && $groups->pluck('qualifying_position')->sort()->values()->all() === range(1, $positions),
            409, 'Review the second-stage group structure.');
        foreach ($groups as $group) {
            $position = (int) $group->qualifying_position;
            $ids = $sources->filter(fn ($source) => count($source['standings']) >= $position)->pluck('id')->all();
            $expected = [];
            foreach ($ids as $index => $one) foreach (array_slice($ids, $index + 1) as $two) {
                $pair = [(int) $one, (int) $two]; sort($pair); $expected[] = implode(':', $pair);
            }
            $actual = [];
            foreach ($draw->drawFixtures->where('draw_group_id', $group->id) as $fixture) {
                abort_unless($fixture->stage === 'RR' && (int) $fixture->registration1_source_position === $position
                    && (int) $fixture->registration2_source_position === $position, 409, 'Review second-stage qualification positions.');
                $pair = [(int) $fixture->registration1_source_group_id, (int) $fixture->registration2_source_group_id];
                sort($pair); $actual[] = implode(':', $pair);
            }
            sort($expected); sort($actual);
            abort_unless($expected === $actual, 409, 'Review the complete second-stage fixture programme.');
        }
    }

    public function preparedStructureIsValid(Draw $draw): bool
    {
        $draw->load('groups.groupRegistrations', 'drawFixtures');
        $sources = $draw->groups->where('round_robin_phase', 1)->map(fn ($group) => [
            'id' => $group->id, 'standings' => array_fill(0, $group->groupRegistrations->count(), null),
        ]);
        try {
            $this->assertPreparedStructure($draw, $sources);
            return true;
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            return false;
        }
    }

    public function complete(Draw $draw, $group, Collection $fixtures): bool
    {
        $ids = $group->groupRegistrations->pluck('registration_id')->map(fn ($id) => (int) $id);
        if ($ids->isEmpty() || $fixtures->count() !== $ids->count() * ($ids->count() - 1) / 2) return false;
        $pairs = [];
        foreach ($fixtures as $fixture) {
            $pair = [(int) $fixture->registration1_id, (int) $fixture->registration2_id];
            sort($pair);
            $key = implode(':', $pair);
            $sets = $fixture->fixtureResults->map(fn ($set) => [(int) $set->registration1_score, (int) $set->registration2_score])->all();
            if ($pair[0] === $pair[1] || ! $ids->contains($pair[0]) || ! $ids->contains($pair[1]) || isset($pairs[$key])
                || (!$fixture->isWalkover() && !$fixture->isRetired() && ! app(ScoreValidationService::class)->validate($fixture, $sets)['valid'])) return false;
            $pairs[$key] = true;
        }
        return true;
    }

    public function positionsResolved(Draw $draw, array $rows, Collection $fixtures): bool
    {
        foreach (array_keys($rows) as $index) {
            if (! app(StandingsService::class)->hasUniqueLeader($draw, array_slice($rows, $index), $fixtures)) return false;
        }
        return $rows !== [];
    }

    /** Used by every score writer/rollback, including legacy engine paths. */
    public static function assertScoreMutable(Fixture $fixture): void
    {
        $draw = $fixture->draw;
        if ($draw?->isTwoStageRoundRobin() && (int) $fixture->drawGroup?->round_robin_phase === 2) {
            abort_unless($draw->hasConfirmedSecondRoundRobin() && $fixture->registration1_id && $fixture->registration2_id, 409,
                'Confirm first-stage progression before scoring second-stage matches.');
        }
        if ($draw?->isTwoStageRoundRobin() && (int) $fixture->drawGroup?->round_robin_phase === 1) {
            abort_if($draw->hasConfirmedSecondRoundRobin(), 409,
                'First-stage results are frozen after progression to the second round robin.');
        }
    }
}
