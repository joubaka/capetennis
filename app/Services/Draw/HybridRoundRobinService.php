<?php

namespace App\Services\Draw;

use App\Models\{Draw, DrawAuditLog, Fixture, OrderOfPlay, User};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, DB, Gate};

/** A four-player championship and one lower round robin, without copying results. */
final class HybridRoundRobinService
{
    public const FORMAT = 'top_two_playoffs_remainder_rr';

    public static function active(Draw $draw): bool
    {
        return $draw->isTwoStageRoundRobin()
            && ($draw->settings?->playoff_config['_second_round_robin']['format'] ?? null) === self::FORMAT;
    }

    /** Added instances are read-only projections; their IDs identify frozen source matches. */
    public static function effectiveFixtures(Draw $draw, ?Collection $fixtures = null): Collection
    {
        $fixtures ??= $draw->drawFixtures;
        if (! self::active($draw)) return $fixtures;
        $config = $draw->settings->playoff_config['_second_round_robin'];
        $lower = $draw->groups->firstWhere('id', $config['lower_group_id']);
        if (! $lower) return $fixtures;
        $members = $lower->groupRegistrations->pluck('registration_id');
        $sourceGroups = $draw->groups->where('round_robin_phase', 1)->pluck('id');
        $carried = $fixtures->filter(fn ($fixture) => (int) $fixture->draw_id === (int) $draw->id
            && $fixture->stage === 'RR' && $sourceGroups->contains($fixture->draw_group_id)
            && in_array((int) $fixture->id, $config['carried_fixture_ids'], true)
            && $members->contains($fixture->registration1_id) && $members->contains($fixture->registration2_id))
            ->map(function ($fixture) use ($draw, $lower) {
                $copy = clone $fixture;
                $copy->draw_group_id = $lower->id;
                $copy->setRelation('drawGroup', $lower);
                $copy->setRelation('draw', $draw);
                return $copy;
            });
        $existing = $fixtures->where('draw_group_id', $lower->id)->pluck('id');
        return $fixtures->concat($carried->reject(fn ($fixture) => $existing->contains($fixture->id)));
    }

    public function review(Draw $draw, int $eventId): array
    {
        abort_unless((int) $draw->event_id === $eventId, 422, 'The draw does not belong to this event.');
        $draw->load(['settings', 'groups.groupRegistrations', 'drawFixtures.fixtureResults', 'drawFixtures.orderOfPlay']);
        abort_if($draw->locked || $draw->isTeamDraw(), 409, 'Use an unlocked individual draw.');
        if (self::active($draw)) return ['already_applied' => true, 'configuration' => $draw->settings->playoff_config['_second_round_robin']];
        $review = app(TwoStageRoundRobinService::class)->review($draw);
        $groups = collect($review['groups'])->values();
        abort_unless($groups->count() === 2 && $groups->every(fn ($group) => count($group['standings']) >= 3), 422,
            'This format needs exactly two completed groups with at least three players each.');
        $sourceIds = $groups->pluck('id');
        $sources = $draw->drawFixtures->whereIn('draw_group_id', $sourceIds);
        $old = $draw->drawFixtures->reject(fn ($fixture) => $sourceIds->contains($fixture->draw_group_id));
        $secondIds = $draw->groups->where('round_robin_phase', 2)->pluck('id');
        abort_unless($old->every(fn ($fixture) => $fixture->stage === 'RR' && $secondIds->contains($fixture->draw_group_id)
            && (int) $fixture->match_status === 0 && ! $fixture->winner_registration && $fixture->fixtureResults->isEmpty()),
            409, 'Only an unplayed second round-robin stage may be replaced.');
        $lower = $groups->flatMap(fn ($group) => array_slice($group['standings'], 2))->pluck('registration_id')->map(fn ($id) => (int) $id)->values();
        $carried = $sources->filter(fn ($fixture) => $lower->contains((int) $fixture->registration1_id) && $lower->contains((int) $fixture->registration2_id));
        $plan = ['already_applied' => false, 'event_id' => $eventId, 'draw_id' => $draw->id,
            'source_snapshot' => $review['snapshot'], 'groups' => $groups->all(),
            'semifinals' => [[$groups[0]['standings'][0]['registration_id'], $groups[1]['standings'][1]['registration_id']],
                [$groups[1]['standings'][0]['registration_id'], $groups[0]['standings'][1]['registration_id']]],
            'lower_registration_ids' => $lower->all(), 'carried_fixture_ids' => $carried->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'preserved_fixture_ids' => $sources->pluck('id')->all(), 'replaced_fixture_ids' => $old->pluck('id')->all(),
            'new_round_robin_matches' => (int) ($lower->count() * ($lower->count() - 1) / 2 - $carried->count()),
            'new_playoff_matches' => 4];
        $evidence = [$plan, $draw->only(['published', 'oop_published', 'locked']), $draw->settings->toArray(),
            $draw->groups->map(fn ($group) => [$group->getAttributes(), $group->groupRegistrations->toArray()])->all(),
            $draw->drawFixtures->map(fn ($fixture) => [$fixture->getAttributes(), $fixture->orderOfPlay?->toArray()])->all(),
            OrderOfPlay::where('draw_id', $draw->id)->orderBy('id')->get()->toArray(),
            DB::table('schedules')->whereIn('fixture_id', $old->pluck('id'))->orderBy('id')->get()->toArray(),
            DB::table('published_schedule_assignments')->where('draw_id', $draw->id)->orderBy('id')->get()->toArray()];
        $plan['snapshot'] = hash('sha256', json_encode($evidence));
        return $plan;
    }

    public function apply(Draw $draw, int $eventId, string $snapshot, User $actor): array
    {
        return DB::transaction(function () use ($draw, $eventId, $snapshot, $actor) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('convertHybridRoundRobin', $draw);
            $draw->drawFixtures()->lockForUpdate()->get();
            $draw->groups()->lockForUpdate()->get();
            $draw->settings()->lockForUpdate()->first();
            $plan = $this->review($draw, $eventId);
            if ($plan['already_applied']) return $plan;
            abort_unless(hash_equals($plan['snapshot'], $snapshot), 409, 'The draw changed. Review a fresh preview.');
            $oldIds = $plan['replaced_fixture_ids'];
            $backup = ['groups' => $draw->groups->where('round_robin_phase', 2)->map(fn ($group) => [
                'group' => $group->getAttributes(), 'members' => $group->groupRegistrations->toArray()])->values()->all(),
                'fixtures' => $draw->drawFixtures->whereIn('id', $oldIds)->map->getAttributes()->values()->all(),
                'schedules' => OrderOfPlay::where('draw_id', $draw->id)->whereIn('fixture_id', $oldIds)->get()->toArray(),
                'legacy_schedules' => DB::table('schedules')->whereIn('fixture_id', $oldIds)->get()->toArray(),
                'published_schedules' => DB::table('published_schedule_assignments')->where('draw_id', $draw->id)->whereIn('fixture_id', $oldIds)->get()->toArray(),
                'settings' => $draw->settings->getAttributes()];
            OrderOfPlay::where('draw_id', $draw->id)->whereIn('fixture_id', $oldIds)->delete();
            DB::table('schedules')->whereIn('fixture_id', $oldIds)->delete();
            DB::table('published_schedule_assignments')->where('draw_id', $draw->id)->whereIn('fixture_id', $oldIds)->delete();
            $draw->drawFixtures()->whereIn('id', $oldIds)->delete();
            foreach ($draw->groups->where('round_robin_phase', 2) as $group) {
                $group->registrations()->detach();
                $group->delete();
            }
            $lower = $draw->groups()->create(['name' => 'Stage 2 · Positions 5–'.(4 + count($plan['lower_registration_ids'])),
                'round_robin_phase' => 2, 'qualifying_position' => 3]);
            foreach ($plan['lower_registration_ids'] as $index => $id) $lower->registrations()->attach($id, ['seed' => $index + 1]);
            $number = (int) $draw->drawFixtures()->max('match_nr');
            $sourceRound = (int) $draw->drawFixtures()->max('round');
            $existingPairs = $draw->drawFixtures->whereIn('id', $plan['carried_fixture_ids'])->map(fn ($fixture) => $this->pair($fixture->registration1_id, $fixture->registration2_id))->all();
            $slots = $plan['lower_registration_ids'];
            if (count($slots) % 2) $slots[] = null;
            $fixed = array_shift($slots);
            $created = [];
            for ($round = 1; $round <= count($slots); $round++) {
                $positions = array_merge([$fixed], $slots);
                for ($index = 0; $index < count($positions) / 2; $index++) {
                    $one = $positions[$index]; $two = $positions[count($positions) - 1 - $index];
                    if (! $one || ! $two || in_array($this->pair($one, $two), $existingPairs, true)) continue;
                    $created[] = Fixture::create(['draw_id' => $draw->id, 'draw_group_id' => $lower->id, 'stage' => 'RR',
                        'round' => $sourceRound + $round, 'match_nr' => ++$number, 'match_status' => 0,
                        'registration1_id' => $one, 'registration2_id' => $two])->id;
                }
                array_unshift($slots, array_pop($slots));
            }
            $base = ['draw_id' => $draw->id, 'stage' => 'MAIN', 'match_status' => 0, 'bracket_id' => 1];
            $semis = [];
            foreach ($plan['semifinals'] as $index => $pair) {
                $semis[] = Fixture::create($base + ['round' => 1, 'match_nr' => ++$number,
                    'registration1_id' => $pair[0], 'registration2_id' => $pair[1], 'feeder_slot' => $index + 1, 'loser_feeder_slot' => $index + 1,
                    'registration1_source_group_id' => $plan['groups'][$index]['id'], 'registration1_source_position' => 1,
                    'registration2_source_group_id' => $plan['groups'][1 - $index]['id'], 'registration2_source_position' => 2]);
            }
            $final = Fixture::create($base + ['round' => 2, 'match_nr' => ++$number, 'position' => 1]);
            $bronze = Fixture::create($base + ['round' => 2, 'match_nr' => ++$number, 'position' => 3, 'playoff_type' => '3rd/4th']);
            foreach ($semis as $semi) $semi->update(['parent_fixture_id' => $final->id, 'loser_parent_fixture_id' => $bronze->id]);
            $created = array_merge($created, array_map(fn ($fixture) => $fixture->id, $semis), [$final->id, $bronze->id]);
            $config = [];
            $config['_second_round_robin'] = ['state' => 'confirmed', 'format' => self::FORMAT, 'lower_group_id' => $lower->id,
                'carried_fixture_ids' => $plan['carried_fixture_ids'], 'source_snapshot' => $plan['source_snapshot']];
            $config['main'] = ['enabled' => true, 'slug' => 'MAIN', 'name' => 'Positions 1–4', 'size' => 4, 'positions' => [1, 2]];
            $draw->settings->update(['playoff_config' => $config]);
            $previous = Auth::user();
            try {
                Auth::setUser($actor);
                DrawAuditLog::record($draw->id, 'hybrid_round_robin_applied', null, ['snapshot' => $snapshot, 'plan' => $plan, 'replaced' => $backup, 'created_fixture_ids' => $created]);
            } finally {
                $previous ? Auth::setUser($previous) : Auth::forgetUser();
            }
            return ['already_applied' => false, 'created_fixture_ids' => $created, 'carried_fixture_ids' => $plan['carried_fixture_ids'], 'lower_group_id' => $lower->id];
        });
    }

    private function pair(int $one, int $two): string
    {
        $ids = [$one, $two]; sort($ids); return implode(':', $ids);
    }
}
