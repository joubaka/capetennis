<?php

namespace App\Services\Draw;

use App\Domain\Draws\Services\{ScoreValidationService, TennisScoreFormat};
use App\Models\{Draw, DrawAuditLog, Fixture, FixtureResult, OrderOfPlay, User};
use App\Services\IndividualMatchOutcomeService;
use App\Services\Scheduling\RoundRobinPlayoffScheduleService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\{Auth, DB, Gate, Schema};

/** Explicit maintenance of an unconfigured one-set RR; never infer a global legacy format. */
final class OneSetRoundRobinCorrectionService
{
    public const ACTION = 'one_set_round_robin_corrected';

    public function review(Draw $draw, int $eventId, bool $preservePlayoffs = false): array
    {
        abort_unless((int) $draw->event_id === $eventId, 422, 'The draw does not belong to this event.');
        $draw->load(['settings', 'groups.groupRegistrations.registration.players', 'drawFixtures.fixtureResults', 'drawFixtures.orderOfPlay']);
        abort_if($draw->locked || $draw->isTeamDraw() || $draw->usesFlexibleMonrad(), 409, 'Use an unlocked individual round-robin draw.');
        abort_if($draw->event?->isInterprovincialTrials() || ! $draw->event?->isIndividual(), 409,
            'This maintenance operation is limited to ordinary individual tournaments.');
        $workflow = $draw->settings?->workflow;
        abort_unless(($workflow === 'round_robin' || ($preservePlayoffs && $workflow === 'round_robin_playoffs'))
            && ! $draw->groups->contains(fn ($group) => (int) $group->round_robin_phase !== 1),
            409, 'Use ordinary round robin, or explicitly preserve verified unplayed position-pair playoffs.');
        $allFixtures = $draw->drawFixtures;
        $fixtures = $allFixtures->where('stage', 'RR')->values();
        $playoffs = $allFixtures->where('stage', '!=', 'RR')->values();
        abort_if(($playoffs->isNotEmpty() && ! $preservePlayoffs) || ($workflow === 'round_robin' && $playoffs->isNotEmpty()),
            409, 'Review downstream playoff dependencies before correcting scoring.');
        abort_if($fixtures->isEmpty() || $fixtures->contains(fn ($fixture) => $fixture->parent_fixture_id || $fixture->loser_parent_fixture_id
            || $fixture->registration1_source_group_id || $fixture->registration2_source_group_id
            || $fixture->registration1_source_position || $fixture->registration2_source_position),
            409, 'Review downstream playoff dependencies before correcting scoring.');
        $fixtureIds = $allFixtures->pluck('id');
        abort_if(Fixture::whereNotIn('id', $fixtureIds)->where(fn ($query) => $query->whereIn('parent_fixture_id', $fixtureIds)->orWhereIn('loser_parent_fixture_id', $fixtureIds))->exists(),
            409, 'Other fixtures depend on this draw.');
        $sourceGroupIds = $draw->groups->pluck('id');
        abort_if(Fixture::where('draw_id', '!=', $draw->id)->where(fn ($query) => $query->whereIn('registration1_source_group_id', $sourceGroupIds)
            ->orWhereIn('registration2_source_group_id', $sourceGroupIds))->exists(), 409, 'Other draws use this group qualification.');
        $members = $draw->groups->flatMap->groupRegistrations->pluck('registration_id')->map(fn ($id) => (int) $id);
        abort_unless($members->isNotEmpty() && $members->count() === $members->unique()->count(), 409, 'Review the group membership.');
        $eligible = app(GroupAssignmentService::class)->eligible($draw)->whereIn('registration_id', $members)->pluck('registration_id')->map(fn ($id) => (int) $id)->unique();
        abort_if($members->diff($eligible)->isNotEmpty() || app(GroupAssignmentService::class)->fixturesNeedRegeneration($draw), 409,
            'Review withdrawn entrants or inconsistent group fixtures first.');
        $alreadyApplied = $draw->settings->score_format === TennisScoreFormat::ONE_FULL_SET && (int) $draw->settings->num_sets === 1
            && DrawAuditLog::where('draw_id', $draw->id)->where('action', self::ACTION)->exists();
        if ($alreadyApplied) return ['already_applied' => true, 'draw_id' => $draw->id, 'event_id' => $eventId,
            'preserved_playoff_fixture_ids' => $playoffs->pluck('id')->all()];
        abort_unless(blank($draw->settings->score_format) && $draw->settings->num_sets === null, 409,
            'This correction only fills an unconfigured scoring format; it cannot replace a chosen format.');
        $previewDraw = clone $draw;
        $previewSettings = clone $draw->settings;
        $previewSettings->score_format = TennisScoreFormat::ONE_FULL_SET;
        $previewSettings->num_sets = 1;
        $previewDraw->setRelation('settings', $previewSettings);
        $updates = [];
        $loserSupported = Schema::hasColumn('fixtures', 'loser_registration');
        foreach ($fixtures as $fixture) {
            $group = $draw->groups->firstWhere('id', $fixture->draw_group_id);
            $ids = $group?->groupRegistrations->pluck('registration_id')->map(fn ($id) => (int) $id);
            abort_unless($ids && $ids->contains((int) $fixture->registration1_id) && $ids->contains((int) $fixture->registration2_id)
                && $fixture->registration1_id !== $fixture->registration2_id, 409, 'A fixture has an invalid group participant.');
            abort_if($fixture->isWalkover() || $fixture->isRetired() || in_array((int) $fixture->match_status, [3, 4, 5], true),
                409, 'Walkovers, retirements and bye outcomes require separate review.');
            if ($fixture->fixtureResults->isEmpty()) {
                abort_if($fixture->winner_registration || $fixture->loser_registration, 409, 'An unscored fixture has a saved outcome.');
                continue;
            }
            abort_unless($fixture->fixtureResults->count() === 1 && (int) $fixture->fixtureResults->first()->set_nr === 1, 409,
                'Each recorded match must contain exactly its first completed set.');
            $recorded = $fixture->fixtureResults->first();
            abort_if(filter_var($recorded->registration1_score, FILTER_VALIDATE_INT) === false
                || filter_var($recorded->registration2_score, FILTER_VALIDATE_INT) === false, 409, 'Every recorded set needs two whole-number scores.');
            $preview = clone $fixture;
            $preview->setRelation('draw', $previewDraw);
            $sets = $preview->fixtureResults->map(fn ($set) => [(int) $set->registration1_score, (int) $set->registration2_score])->all();
            $validation = app(ScoreValidationService::class)->validate($preview, $sets);
            abort_unless($validation['valid'], 409, "Fixture {$fixture->id}: ".($validation['message'] ?? 'Invalid one-set score.'));
            $winner = app(IndividualMatchOutcomeService::class)->winner($preview);
            abort_unless($winner, 409, 'Every scored fixture must have a completed one-set winner.');
            $loser = (int) $winner === (int) $fixture->registration1_id ? $fixture->registration2_id : $fixture->registration1_id;
            abort_if(($recorded->winner_registration && (int) $recorded->winner_registration !== (int) $winner)
                || ($recorded->loser_registration && (int) $recorded->loser_registration !== (int) $loser), 409, 'A recorded set outcome conflicts with its score.');
            abort_if(($fixture->winner_registration && (int) $fixture->winner_registration !== (int) $winner)
                || ($fixture->loser_registration && (int) $fixture->loser_registration !== (int) $loser), 409, 'A stored outcome conflicts with the recorded score.');
            $update = ['fixture_id' => $fixture->id, 'winner_registration' => (int) $winner, 'match_status' => 1];
            if ($loserSupported) $update['loser_registration'] = (int) $loser;
            $updates[] = $update;
        }
        abort_if($updates === [], 409, 'No completed one-set results need correction.');
        if ($workflow === 'round_robin_playoffs') $this->assertPreservablePlayoffs($draw, $previewDraw, $playoffs);
        $plan = ['already_applied' => false, 'event_id' => $eventId, 'draw_id' => $draw->id,
            'format' => TennisScoreFormat::ONE_FULL_SET, 'num_sets' => 1, 'fixture_updates' => $updates,
            'preserve_playoffs' => $preservePlayoffs, 'preserved_playoff_fixture_ids' => $playoffs->pluck('id')->all(),
            'preserved_playoffs' => $playoffs->map->getAttributes()->all(),
            'preserved_playoff_bookings' => [
                'private' => OrderOfPlay::whereIn('fixture_id', $playoffs->pluck('id'))->orderBy('id')->get()->toArray(),
                'legacy' => DB::table('schedules')->whereIn('fixture_id', $playoffs->pluck('id'))->orderBy('id')->get()->toArray(),
                'published' => DB::table('published_schedule_assignments')->where('draw_id', $draw->id)->whereIn('fixture_id', $playoffs->pluck('id'))->orderBy('id')->get()->toArray(),
            ],
            'persisted_loser_supported' => $loserSupported,
            'preserved_fixture_ids' => $fixtureIds->all(), 'result_rows' => $fixtures->sum(fn ($fixture) => $fixture->fixtureResults->count())];
        $evidence = [$plan, $draw->getAttributes(), $draw->settings->getAttributes(),
            $draw->groups->map(fn ($group) => [$group->getAttributes(), $group->groupRegistrations->toArray()])->all(),
            $allFixtures->map(fn ($fixture) => [$fixture->getAttributes(), $fixture->fixtureResults->toArray()])->all(),
            OrderOfPlay::where('draw_id', $draw->id)->orderBy('id')->get()->toArray(),
            DB::table('schedules')->whereIn('fixture_id', $fixtureIds)->orderBy('id')->get()->toArray(),
            DB::table('published_schedule_assignments')->where('draw_id', $draw->id)->orderBy('id')->get()->toArray()];
        $plan['snapshot'] = hash('sha256', json_encode($evidence));
        return $plan;
    }

    public function apply(Draw $draw, int $eventId, string $snapshot, User $actor, bool $preservePlayoffs = false): array
    {
        return DB::transaction(function () use ($draw, $eventId, $snapshot, $actor, $preservePlayoffs) {
            $draw = Draw::whereKey($draw->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('correctRoundRobinScoring', $draw);
            $draw->settings()->lockForUpdate()->first();
            $fixtures = $draw->drawFixtures()->lockForUpdate()->get();
            FixtureResult::whereIn('fixture_id', $fixtures->pluck('id'))->lockForUpdate()->get();
            $draw->groups()->lockForUpdate()->get();
            OrderOfPlay::where('draw_id', $draw->id)->lockForUpdate()->get();
            DB::table('schedules')->whereIn('fixture_id', $fixtures->pluck('id'))->lockForUpdate()->get();
            DB::table('published_schedule_assignments')->where('draw_id', $draw->id)->lockForUpdate()->get();
            $plan = $this->review($draw, $eventId, $preservePlayoffs);
            if ($plan['already_applied']) return $plan;
            abort_unless(hash_equals($plan['snapshot'], $snapshot), 409, 'The draw changed. Review a fresh preview.');
            $before = ['settings' => $draw->settings->getAttributes(),
                'fixtures' => $draw->drawFixtures->whereIn('id', collect($plan['fixture_updates'])->pluck('fixture_id'))->map->getAttributes()->values()->all()];
            $draw->settings->update(['score_format' => TennisScoreFormat::ONE_FULL_SET, 'num_sets' => 1, 'require_full_sets' => true]);
            foreach ($plan['fixture_updates'] as $update) {
                $fixture = $draw->drawFixtures->firstWhere('id', $update['fixture_id']);
                $attributes = collect($update)->except('fixture_id')->all();
                // Some historical schemas store only the winner; the loser is always derivable.
                if (! $plan['persisted_loser_supported']) unset($attributes['loser_registration']);
                $fixture->forceFill($attributes)->save();
            }
            $previous = Auth::user();
            try {
                Auth::setUser($actor);
                DrawAuditLog::record($draw->id, self::ACTION, null, ['snapshot' => $snapshot, 'before' => $before, 'plan' => $plan,
                    'after' => ['settings' => $draw->settings->getAttributes(), 'fixture_updates' => $plan['fixture_updates']]]);
            } finally {
                $previous ? Auth::setUser($previous) : Auth::forgetUser();
            }
            return ['already_applied' => false, 'draw_id' => $draw->id, 'corrected_fixtures' => count($plan['fixture_updates']),
                'preserved_playoff_fixture_ids' => $plan['preserved_playoff_fixture_ids'],
                'preserved_result_rows' => $plan['result_rows']];
        });
    }

    /** Only independent configured position-pair finals may survive this maintenance. */
    private function assertPreservablePlayoffs(Draw $draw, Draw $previewDraw, Collection $playoffs): void
    {
        $schedule = app(RoundRobinPlayoffScheduleService::class);
        abort_unless($playoffs->isNotEmpty() && $schedule->supportsEntireConfiguration($draw), 409,
            'Only a complete saved configuration of independent two-player position finals can be preserved.');
        $definitions = $schedule->pairDefinitions($draw);
        abort_unless($definitions->count() === $playoffs->count()
            && $definitions->pluck('stage')->unique()->count() === $definitions->count(), 409, 'The saved playoff stages do not match their configuration.');
        $previewFixtures = $draw->drawFixtures->map(function ($fixture) use ($previewDraw) {
            $clone = clone $fixture;
            $clone->setRelation('draw', $previewDraw);
            return $clone;
        });
        $previewDraw->setRelation('drawFixtures', $previewFixtures);
        $standings = app(\App\Domain\Draws\Services\StandingsService::class)->forDraw($previewDraw);
        $completeGroups = [];
        foreach ($definitions as $definition) {
            $stageFixtures = $playoffs->where('stage', $definition['stage']);
            abort_unless($stageFixtures->count() === 1, 409, 'A playoff stage is missing or duplicated.');
            $fixture = $stageFixtures->first();
            abort_if((int) $fixture->match_status !== 0 || $fixture->fixtureResults->isNotEmpty()
                || $fixture->winner_registration !== null || $fixture->loser_registration !== null
                || $fixture->parent_fixture_id || $fixture->loser_parent_fixture_id || $fixture->draw_group_id
                || (int) $fixture->round !== 1 || $fixture->feeder_slot || $fixture->loser_feeder_slot,
                409, 'Only unplayed independent playoff finals with no outcome or feeder links may be preserved.');
            foreach ([1, 2] as $slot) {
                $source = $definition['sources'][$slot - 1];
                $group = $draw->groups->firstWhere('id', $source['group_id']);
                abort_unless($group && $source['position'] > 0 && $source['position'] <= $group->groupRegistrations->count()
                    && (int) $fixture->getAttribute("registration{$slot}_source_group_id") === $source['group_id']
                    && (int) $fixture->getAttribute("registration{$slot}_source_position") === $source['position'],
                    409, 'A playoff qualification source differs from the saved position-pair configuration.');
                $registration = $fixture->getAttribute("registration{$slot}_id");
                if ($registration === null) continue;
                if (! array_key_exists($group->id, $completeGroups)) {
                    $groupFixtures = $previewFixtures->where('stage', 'RR')->where('draw_group_id', $group->id);
                    $rules = app(TwoStageRoundRobinService::class);
                    $completeGroups[$group->id] = $rules->complete($previewDraw, $group, $groupFixtures)
                        && $rules->positionsResolved($previewDraw, array_values($standings[$group->id] ?? []), $groupFixtures);
                }
                abort_unless($completeGroups[$group->id]
                    && (int) $registration === (int) ($standings[$group->id][$source['position'] - 1]['reg_id'] ?? 0),
                    409, 'An occupied playoff slot does not match complete, resolved standings under the corrected format.');
            }
        }
    }
}
