<?php

namespace App\Services\Draw;

use App\Models\{Draw, DrawAuditLog, Fixture, User};
use Illuminate\Support\Facades\{Auth, DB, Gate};

/** Explicit opt-in for a three-player tail on a single-group position-pair draw. */
final class OddPlayerPlayoffService
{
    /** Generic settings may retain this topology, but cannot create/remove it. */
    public function assertRetainedOption(Draw $draw, array $configuration): void
    {
        $special = fn ($entries) => collect($entries)->contains(fn ($e) => is_array($e)
            && (($e['odd_tail'] ?? false) || (int) ($e['size'] ?? 0) === 3));
        $previous = $draw->settings?->playoff_config ?? [];
        if (!$special($previous) && !$special($configuration)) return;
        $shape = fn ($entries) => collect($entries)->filter(fn ($e) => is_array($e) && isset($e['slug']))
            ->map(fn ($e) => [strtoupper($e['slug'] ?? ''), (int) ($e['size'] ?? 0), array_map('intval', $e['positions'] ?? []),
                (bool) ($e['enabled'] ?? false), (bool) ($e['odd_tail'] ?? false), array_map('strval', $e['group_order'] ?? [])])->values()->all();
        abort_unless($shape($previous) === $shape($configuration), 422,
            'Use the dedicated odd-player playoff option to add its preliminary. Existing odd-player playoff structures cannot be reset through general settings.');
    }

    public function review(Draw $draw, int $eventId): array
    {
        abort_unless((int) $draw->event_id === $eventId, 422, 'The draw does not belong to this event.');
        $draw->load(['settings', 'groups.groupRegistrations', 'drawFixtures.fixtureResults']);
        abort_if($draw->locked || $draw->isTeamDraw() || $draw->usesFlexibleMonrad() || $draw->event?->isInterprovincialTrials()
            || $draw->settings?->workflow !== 'round_robin_playoffs' || $draw->groups->count() !== 1,
            422, 'Choose an unlocked single-group round robin with position-pair playoffs.');
        $group = $draw->groups->first();
        $count = $group->groupRegistrations->count();
        abort_unless($count >= 3 && $count % 2 === 1, 422, 'This option requires an odd number of at least three players.');
        $config = collect($draw->settings->playoff_config ?? [])->filter(fn ($e) => is_array($e) && ($e['enabled'] ?? false))->values();
        $tail = $config->last();
        $applied = (bool) ($tail['odd_tail'] ?? false);
        $expected = range(1, $applied ? $count : $count - 1);
        abort_unless($config->flatMap(fn ($e) => array_map('intval', $e['positions'] ?? []))->all() === $expected
            && $config->every(fn ($e, $i) => $applied && $i === $config->count() - 1
                ? (int) $e['size'] === 3 : (int) ($e['size'] ?? 0) === 2), 422,
            'This option requires consecutive position pairs covering all other players.');
        $stage = strtoupper($tail['slug']);
        $stageFixtures = $draw->drawFixtures->where('stage', $stage);
        if (!$applied) {
            abort_unless($stageFixtures->count() === 1, 422, 'Prepare the position-pair playoff fixtures before choosing this option.');
            $final = $stageFixtures->first();
            abort_if($final->registration1_id || $final->registration2_id || $final->winner_registration
                || (int) $final->match_status !== 0 || $final->fixtureResults->isNotEmpty()
                || $final->parent_fixture_id || $final->loser_parent_fixture_id
                || $draw->drawFixtures->contains(fn ($f) => (int) $f->parent_fixture_id === (int) $final->id
                    || (int) $f->loser_parent_fixture_id === (int) $final->id), 422,
                'The last playoff must be an unplayed, unqualified position-pair fixture without dependants.');
            abort_unless((int) $final->registration1_source_group_id === (int) $group->id
                && (int) $final->registration2_source_group_id === (int) $group->id
                && (int) $final->registration1_source_position === $count - 2
                && (int) $final->registration2_source_position === $count - 1,
                422, 'The last playoff sources do not match the final position pair.');
        }
        $evidence = [$draw->getAttributes(), $draw->settings->getAttributes(),
            $draw->groups->map(fn ($g) => [$g->getAttributes(), $g->groupRegistrations->toArray()])->all(),
            $draw->drawFixtures->map(fn ($f) => [$f->getAttributes(), $f->fixtureResults->toArray()])->all(),
            DB::table('order_of_plays')->where('draw_id', $draw->id)->orderBy('id')->get()->all(),
            DB::table('schedules')->whereIn('fixture_id', $draw->drawFixtures->modelKeys())->orderBy('id')->get()->all(),
            DB::table('published_schedule_assignments')->where('draw_id', $draw->id)->orderBy('id')->get()->all()];
        return ['draw_id' => $draw->id, 'event_id' => $eventId, 'players' => $count, 'group_id' => $group->id,
            'stage' => $stage, 'already_applied' => $applied,
            'snapshot' => hash('sha256', json_encode($evidence, JSON_THROW_ON_ERROR))];
    }

    public function apply(Draw $draw, int $eventId, string $snapshot, User $actor): array
    {
        return DB::transaction(function () use ($draw, $eventId, $snapshot, $actor) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('progress', $draw);
            $draw->drawFixtures()->lockForUpdate()->get();
            $plan = $this->review($draw, $eventId);
            abort_unless(hash_equals($plan['snapshot'], $snapshot), 409, 'The draw changed. Review the option again.');
            if ($plan['already_applied']) return $plan;
            $final = $draw->drawFixtures->firstWhere('stage', $plan['stage']);
            $before = $final->getAttributes();
            $n = $plan['players'];
            // The sole feeder uses canonical slot 1; position n-2 enters slot 2.
            $final->update(['round' => 2, 'position' => null, 'registration1_source_group_id' => null,
                'registration1_source_position' => null, 'registration2_source_group_id' => $plan['group_id'],
                'registration2_source_position' => $n - 2, 'playoff_type' => "Positions ".($n - 2)."–{$n} final"]);
            $feeder = Fixture::create(['draw_id' => $draw->id, 'stage' => $plan['stage'], 'round' => 1,
                'match_nr' => 1 + (int) $draw->drawFixtures()->max('match_nr'), 'parent_fixture_id' => $final->id,
                'playoff_type' => "Odd-player preliminary #{$n} v #".($n - 1),
                'registration1_source_group_id' => $plan['group_id'], 'registration1_source_position' => $n,
                'registration2_source_group_id' => $plan['group_id'], 'registration2_source_position' => $n - 1]);
            $config = $draw->settings->playoff_config;
            foreach ($config as &$entry) if (strtoupper($entry['slug'] ?? '') === $plan['stage']) {
                $entry['size'] = 3; $entry['positions'] = [$n - 2, $n - 1, $n]; $entry['odd_tail'] = true;
                $entry['name'] = 'Positions '.($n - 2)."–{$n}: #{$n} v #".($n - 1).' → winner v #'.($n - 2);
            }
            unset($entry);
            $draw->settings->update(['playoff_config' => $config]);
            $previousActor = Auth::user();
            try {
                Auth::setUser($actor);
                DrawAuditLog::record($draw->id, 'odd_player_playoff_enabled', $final->id,
                    ['before_final' => $before, 'after_final' => $final->getAttributes(), 'added_fixture_id' => $feeder->id]);
            } finally {
                if ($previousActor) Auth::setUser($previousActor);
                else Auth::forgetUser();
            }
            return $plan + ['final_fixture_id' => $final->id, 'added_fixture_id' => $feeder->id];
        });
    }
}
