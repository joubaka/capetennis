<?php

namespace App\Services\Draw;

use App\Domain\Draws\Services\{RoundRobinGenerationService, ScoreValidationService, StandingsService};
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
            if ($draw->groups->contains('round_robin_phase', 2)) return ['created' => 0, 'already_progressed' => true];
            $sourceGroups = collect($review['groups']);
            $positions = $sourceGroups->max(fn ($group) => count($group['standings']));
            $nextMatch = 1 + (int) $draw->drawFixtures()->max('match_nr');
            $roundOffset = (int) $draw->drawFixtures()->max('round');
            $created = 0;
            for ($position = 1; $position <= $positions; $position++) {
                $qualifiers = $sourceGroups->filter(fn ($group) => count($group['standings']) >= $position)->values();
                $group = $draw->groups()->create(['name' => 'Stage 2 · Group '.$position,
                    'round_robin_phase' => 2, 'qualifying_position' => $position]);
                foreach ($qualifiers as $seed => $source) {
                    $group->registrations()->attach($source['standings'][$position - 1]['registration_id'], ['seed' => $seed + 1]);
                }
                $group->load('groupRegistrations');
                $nextMatch = app(RoundRobinGenerationService::class)->generateGroupFixtures($draw, $group, $nextMatch);
                $sources = $qualifiers->mapWithKeys(fn ($source) => [$source['standings'][$position - 1]['registration_id'] => $source['id']]);
                foreach ($group->fixtures()->get() as $fixture) {
                    $fixture->update(['round' => $roundOffset + (int) $fixture->round,
                        'registration1_source_group_id' => $sources[$fixture->registration1_id],
                        'registration1_source_position' => $position,
                        'registration2_source_group_id' => $sources[$fixture->registration2_id],
                        'registration2_source_position' => $position]);
                    $created++;
                }
            }
            DrawAuditLog::record($draw->id, 'second_round_robin_progressed', null,
                ['created' => $created, 'standings_snapshot' => $review['snapshot'], 'source_group_ids' => $required]);

            return ['created' => $created, 'already_progressed' => false];
        });
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
        if ($draw?->isTwoStageRoundRobin() && (int) $fixture->drawGroup?->round_robin_phase === 1) {
            abort_if($draw->groups()->where('round_robin_phase', 2)->exists(), 409,
                'First-stage results are frozen after progression to the second round robin.');
        }
    }
}
