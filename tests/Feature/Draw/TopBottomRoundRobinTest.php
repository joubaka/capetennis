<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\{RoundRobinGenerationService, StandingsService, TennisScoreFormat};
use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, Event, OrderOfPlay, Player, Registration, User, Venue};
use App\Services\Draw\{DrawFinalPlacementService, DrawResultOrderService, GroupAssignmentService, TopBottomRoundRobinService, TwoStageRoundRobinService};
use App\Services\DrawService;
use App\Services\Scheduling\EventVenueScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TopBottomRoundRobinTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(bool $complete = false): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create(['eventType' => 6, 'published' => true, 'start_date' => '2026-11-28', 'end_date' => '2026-11-28']);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id,
            'drawName' => 'Eight fictional green players', 'published' => false, 'oop_published' => false, 'locked' => false]);
        $this->actingAs($actor)->post(route('draw.setup.store', $draw), ['workflow' => 'round_robin_round_robin',
            'groups' => 2, 'second_stage_format' => TopBottomRoundRobinService::FORMAT])->assertRedirect();
        $draw = $draw->fresh();
        $draw->settings->update(['score_format' => TennisScoreFormat::ONE_FULL_SET, 'num_sets' => 1]);
        $entrants = [];
        foreach ($draw->groups()->orderBy('name')->get() as $group) foreach (range(1, 4) as $seed) {
            $registration = Registration::factory()->create();
            $registration->players()->attach(Player::factory()->create(['name' => $group->name.$seed, 'surname' => 'Example'])->id);
            CategoryEventRegistration::factory()->create(['category_event_id' => $category->id, 'registration_id' => $registration->id, 'payment_status_id' => 1]);
            $draw->registrations()->attach($registration->id);
            $group->registrations()->attach($registration->id, ['seed' => $seed]);
            $entrants[$group->name.$seed] = $registration->id;
        }
        app(RoundRobinGenerationService::class)->generate($draw->fresh());
        if ($complete) $this->complete($draw->drawFixtures()->whereNotNull('registration1_id')->get());
        return [$draw->fresh(), $entrants, $actor];
    }

    private function complete($fixtures): void
    {
        foreach ($fixtures as $fixture) app(DrawService::class)->saveScore($fixture,
            $fixture->registration1_id < $fixture->registration2_id ? [[6, 0]] : [[0, 6]]);
    }

    private function review(Draw $draw): array
    {
        $review = $this->getJson(route('backend.draw.progress-review', $draw))->assertOk()->json('review');
        return ['confirmed_group_ids' => collect($review['groups'])->pluck('id')->all(), 'standings_snapshot' => $review['snapshot']];
    }

    public function test_selection_prepares_twenty_real_matches_and_only_cross_group_second_stage_tuples(): void
    {
        [$draw] = $this->scenario();
        $this->assertTrue($draw->hasTopBottomRoundRobin());
        $this->assertSame(20, $draw->drawFixtures()->count());
        $this->assertSame(0, DB::table('fixture_results')->count());
        $this->assertCount(0, app(TwoStageRoundRobinService::class)->prepare($draw));
        $this->assertFalse(app(GroupAssignmentService::class)->fixturesNeedRegeneration($draw));
        $ids = $draw->groups->where('round_robin_phase', 1)->sortBy('id')->pluck('id')->all();
        foreach ($draw->groups->where('round_robin_phase', 2) as $group) {
            $fixtures = $draw->drawFixtures->where('draw_group_id', $group->id);
            $this->assertCount(4, $fixtures);
            $this->assertTrue($fixtures->every(fn ($fixture) => !$fixture->registration1_id && !$fixture->registration2_id));
            $start = (int) $group->qualifying_position;
            $expected = [];
            $sources = [[$ids[0], $start], [$ids[0], $start + 1], [$ids[1], $start], [$ids[1], $start + 1]];
            foreach ($sources as $index => $one) foreach (array_slice($sources, $index + 1) as $two) {
                if ($one[0] === $two[0]) continue;
                $pair = [implode(':', $one), implode(':', $two)]; sort($pair); $expected[] = implode('|', $pair);
            }
            $actual = $fixtures->map(function ($fixture) {
                $pair = [$fixture->registration1_source_group_id.':'.$fixture->registration1_source_position,
                    $fixture->registration2_source_group_id.':'.$fixture->registration2_source_position]; sort($pair); return implode('|', $pair);
            })->all();
            sort($expected); sort($actual); $this->assertSame($expected, $actual);
        }
        $placements = app(DrawFinalPlacementService::class)->forDraw($draw);
        $this->assertSame(range(1, 8), $placements->pluck('position')->all());
        $this->assertTrue($placements->every(fn ($row) => $row['status'] === 'awaiting'));
        $setup = $this->get(route('draw.setup.show', $draw))->assertOk()->assertSee('top_two_bottom_two_rr')->assertSee('mutual first-stage result carries forward', false);
        if (getenv('CT_TOP_BOTTOM_PREVIEW') === '1') {
            if (!app()->environment('testing') || DB::getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') {
                throw new \LogicException('Preview export requires fictional isolated SQLite memory tests.');
            }
            $directory = storage_path('framework/testing');
            if (!is_dir($directory)) mkdir($directory, 0777, true);
            file_put_contents($directory.'/top-two-setup.html', str_replace('http://localhost', '/public', $setup->getContent()));
        }
        $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk()->assertSee('championship and consolation round robins')->assertDontSee('id="btn-add-playoff"', false);
        $this->postJson(route('backend.draw.update-settings', $draw), ['score_format' => TennisScoreFormat::ONE_FULL_SET])->assertOk();
        $this->assertTrue($draw->fresh()->hasTopBottomRoundRobin());
        $rules = collect(app(\App\Domain\Draws\Services\DrawRulesService::class)->sections($draw))->firstWhere('key', 'second_round_robin');
        $this->assertStringContainsString('A1, A2, B1 and B2', $rules['rules']);
        $this->assertStringNotContainsString('All first-place finishers enter', $rules['rules']);
    }

    public function test_progression_preserves_ids_and_bookings_and_carries_original_mutual_results_without_copying(): void
    {
        [$draw, $entrants] = $this->scenario(true);
        $second = $draw->drawFixtures->filter(fn ($fixture) => !$fixture->registration1_id);
        $venue = Venue::forceCreate(['name' => 'Example courts']);
        OrderOfPlay::create(['fixture_id' => $second->first()->id, 'draw_id' => $draw->id, 'venue_id' => $venue->id,
            'court' => '1', 'time' => '2026-11-28 12:00', 'duration_minutes' => 30]);
        $ids = $draw->drawFixtures()->orderBy('id')->pluck('id')->all();
        $originalResults = DB::table('fixture_results')->orderBy('id')->get()->toJson();
        $bookings = OrderOfPlay::where('draw_id', $draw->id)->get()->toJson();
        $request = $this->review($draw);
        $this->postJson(route('backend.draw.progress', $draw), $request)->assertOk();
        $this->postJson(route('backend.draw.progress', $draw), $request)->assertOk();
        $draw = $draw->fresh(['groups.groupRegistrations', 'drawFixtures.fixtureResults']);
        $top = $draw->groups->where('round_robin_phase', 2)->firstWhere('qualifying_position', 1);
        $lower = $draw->groups->where('round_robin_phase', 2)->firstWhere('qualifying_position', 3);
        $this->assertEqualsCanonicalizing([$entrants['A1'], $entrants['A2'], $entrants['B1'], $entrants['B2']], $top->groupRegistrations->pluck('registration_id')->all());
        $this->assertEqualsCanonicalizing([$entrants['A3'], $entrants['A4'], $entrants['B3'], $entrants['B4']], $lower->groupRegistrations->pluck('registration_id')->all());
        $this->assertSame($ids, $draw->drawFixtures()->orderBy('id')->pluck('id')->all());
        $this->assertSame($bookings, OrderOfPlay::where('draw_id', $draw->id)->get()->toJson());
        $this->assertSame(12, DB::table('fixture_results')->count());
        $this->assertSame($originalResults, DB::table('fixture_results')->orderBy('id')->get()->toJson());
        $this->assertTrue($draw->drawFixtures->whereIn('id', $second->modelKeys())->every(fn ($fixture) => $fixture->fixtureResults->isEmpty()));
        $standings = app(StandingsService::class)->forDraw($draw);
        $this->assertSame(2, array_sum(array_column($standings[$top->id], 'wins')));
        $state = $draw->settings->playoff_config['_second_round_robin'];
        $this->assertCount(4, $state['carried_fixture_ids']);
        $effective = \App\Services\Draw\HybridRoundRobinService::effectiveFixtures($draw);
        $this->assertCount(24, $effective);
        $this->assertCount(4, $effective->filter(fn ($fixture) => $fixture->isDirty('draw_group_id')));
        foreach ([$top, $lower] as $group) {
            $this->assertCount(6, $effective->where('draw_group_id', $group->id));
            $this->assertCount(2, $state['carried_groups'][(string) $group->id]);
        }
        $this->assertFalse(app(GroupAssignmentService::class)->fixturesNeedRegeneration($draw));
        $hub = app(DrawService::class)->loadRoundRobinHub($draw);
        $this->assertCount(2, collect($hub['rrFixtures'][$top->id])->where('carried', true));
        $original = $draw->drawFixtures->firstWhere('id', $state['carried_fixture_ids'][0]);
        $this->assertSame(1, (int) $original->drawGroup->round_robin_phase);
        $this->postJson(route('backend.roundrobin.score.store', $original), ['score_revision' => app(\App\Services\Draw\IndividualScoreRevision::class)->forFixture($original), 'sets' => ['0-6']])->assertConflict();
        $this->complete($draw->drawFixtures->where('draw_group_id', $lower->id));
        $places = app(DrawFinalPlacementService::class)->forDraw($draw->fresh());
        $this->assertSame([1, 2, 3, 4], $places->where('status', 'awaiting')->pluck('position')->all());
        $this->assertSame([5, 6, 7, 8], $places->where('status', 'resolved')->pluck('position')->all());
        $this->complete($draw->drawFixtures->where('draw_group_id', $top->id));
        $places = app(DrawFinalPlacementService::class)->forDraw($draw->fresh());
        $this->assertSame(range(1, 8), $places->pluck('position')->all());
        $this->assertTrue($places->every(fn ($row) => $row['status'] === 'resolved'));
        $this->assertSame(20, DB::table('fixture_results')->count());
    }

    public function test_stale_settings_sources_and_bookings_each_reject_progression_without_partial_membership(): void
    {
        [$draw] = $this->scenario(true);
        $request = $this->review($draw);
        $draw->settings->update(['match_duration_minutes' => 45]);
        $this->postJson(route('backend.draw.progress', $draw), $request)->assertConflict();
        $request = $this->review($draw);
        $fixture = $draw->drawFixtures()->whereNotNull('registration1_source_group_id')->firstOrFail();
        $venue = Venue::forceCreate(['name' => 'Changed booking courts']);
        OrderOfPlay::create(['fixture_id' => $fixture->id, 'draw_id' => $draw->id, 'venue_id' => $venue->id,
            'court' => '1', 'time' => '2026-11-28 12:00', 'duration_minutes' => 30]);
        $this->postJson(route('backend.draw.progress', $draw), $request)->assertConflict();
        $request = $this->review($draw);
        $fixture->update(['registration1_source_position' => 99]);
        $this->postJson(route('backend.draw.progress', $draw), $request)->assertConflict();
        $this->assertSame(0, $draw->groups()->where('round_robin_phase', 2)->withCount('groupRegistrations')->get()->sum('group_registrations_count'));
        $this->assertFalse(app(TwoStageRoundRobinService::class)->preparedStructureIsValid($draw->fresh()));
    }

    public function test_forged_carried_references_cannot_project_foreign_or_wrong_band_evidence(): void
    {
        [$draw, $entrants] = $this->scenario(true);
        $this->postJson(route('backend.draw.progress', $draw), $this->review($draw))->assertOk();
        $draw = $draw->fresh(['groups.groupRegistrations', 'drawFixtures.fixtureResults']);
        $top = $draw->groups->where('round_robin_phase', 2)->firstWhere('qualifying_position', 1);
        $wrongBand = $draw->drawFixtures->first(fn ($fixture) => in_array((int) $fixture->registration1_id, [$entrants['A3'], $entrants['A4']], true)
            && in_array((int) $fixture->registration2_id, [$entrants['A3'], $entrants['A4']], true));
        $foreign = \App\Models\Fixture::factory()->create(['draw_id' => Draw::factory()->create()->id,
            'draw_group_id' => $draw->groups->where('round_robin_phase', 1)->first()->id, 'stage' => 'RR',
            'registration1_id' => $entrants['A1'], 'registration2_id' => $entrants['A2']]);
        $config = $draw->settings->playoff_config;
        $config['_second_round_robin']['carried_groups'][(string) $top->id] = [$wrongBand->id, $foreign->id];
        $draw->settings->update(['playoff_config' => $config]);
        $records = DB::table('fixture_results')->orderBy('id')->get()->toJson();
        $effective = \App\Services\Draw\HybridRoundRobinService::effectiveFixtures($draw->fresh(['groups.groupRegistrations', 'drawFixtures.fixtureResults']));
        $this->assertCount(4, $effective->where('draw_group_id', $top->id));
        $explicit = TopBottomRoundRobinService::effectiveFixtures($draw->fresh(['groups.groupRegistrations', 'drawFixtures.fixtureResults']), $draw->drawFixtures->concat([$foreign]));
        $this->assertCount(4, $explicit->where('draw_group_id', $top->id));
        $this->assertSame($records, DB::table('fixture_results')->orderBy('id')->get()->toJson());
        $this->assertSame(20, $draw->drawFixtures()->count());
    }

    public function test_authorization_foreign_confirmations_and_unresolved_scores_are_rejected(): void
    {
        [$draw, , $actor] = $this->scenario();
        $other = User::factory()->create()->assignRole('admin');
        $this->actingAs($other)->getJson(route('backend.draw.progress-review', $draw))->assertForbidden();
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'round_robin_round_robin', 'groups' => 2,
            'second_stage_format' => TopBottomRoundRobinService::FORMAT])->assertForbidden();
        $this->actingAs($actor);
        $this->getJson(route('backend.draw.progress-review', $draw))->assertUnprocessable();
        $second = $draw->drawFixtures()->whereNotNull('registration1_source_group_id')->firstOrFail();
        $this->postJson(route('backend.roundrobin.score.store', $second), ['score_revision' => app(\App\Services\Draw\IndividualScoreRevision::class)->forFixture($second), 'sets' => ['6-0']])->assertConflict();
        $this->complete($draw->drawFixtures()->whereNotNull('registration1_id')->get());
        $request = $this->review($draw);
        $request['confirmed_group_ids'][] = Draw::factory()->create()->groups()->create(['name' => 'Other event'])->id;
        $this->postJson(route('backend.draw.progress', $draw), $request)->assertUnprocessable();
        $this->assertSame(20, $draw->drawFixtures()->count());
    }

    public function test_invalid_group_shapes_roll_back_generation_and_setup_rejects_extra_groups(): void
    {
        [$draw] = $this->scenario();
        $ids = $draw->drawFixtures()->orderBy('id')->pluck('id')->all();
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'round_robin_round_robin', 'groups' => 3,
            'second_stage_format' => TopBottomRoundRobinService::FORMAT])->assertUnprocessable();
        $this->assertSame($ids, $draw->drawFixtures()->orderBy('id')->pluck('id')->all());
        $later = $draw->groups()->where('round_robin_phase', 2)->pluck('id');
        $draw->drawFixtures()->whereIn('draw_group_id', $later)->delete();
        $draw->groups()->whereIn('id', $later)->delete();
        $groups = $draw->groups()->orderBy('id')->get();
        $moved = $groups[0]->groupRegistrations()->first()->registration_id;
        $groups[0]->registrations()->detach($moved);
        $groups[1]->registrations()->attach($moved, ['seed' => 5]);
        $firstIds = $draw->drawFixtures()->orderBy('id')->pluck('id')->all();
        try {
            app(RoundRobinGenerationService::class)->generate($draw->fresh());
            $this->fail('Expected exact four-player pool validation.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertSame($firstIds, $draw->drawFixtures()->orderBy('id')->pluck('id')->all());
    }

    public function test_voided_first_stage_player_prunes_only_missing_fourth_position_and_preserves_bands(): void
    {
        [$draw, $entrants] = $this->scenario(true);
        $withdrawn = $entrants['A1'];
        $groupId = $draw->groups->where('round_robin_phase', 1)->sortBy('id')->first()->id;
        $removed = $draw->drawFixtures->filter(fn ($fixture) =>
            ((int) $fixture->registration1_source_group_id === (int) $groupId && (int) $fixture->registration1_source_position === 4)
            || ((int) $fixture->registration2_source_group_id === (int) $groupId && (int) $fixture->registration2_source_position === 4))->modelKeys();
        $allIds = $draw->drawFixtures()->pluck('id');
        DB::transaction(function () use ($draw, $withdrawn) {
            $draw->drawFixtures()->where(fn ($query) => $query->where('registration1_id', $withdrawn)->orWhere('registration2_id', $withdrawn))
                ->update(['round_robin_voided_registration_id' => $withdrawn]);
            app(TwoStageRoundRobinService::class)->reconcileVoidedSources($draw, $withdrawn);
        });
        $this->assertCount(2, $removed);
        $this->assertSame($allIds->diff($removed)->values()->all(), $draw->drawFixtures()->pluck('id')->all());
        $this->assertTrue(app(TwoStageRoundRobinService::class)->preparedStructureIsValid($draw->fresh()));
        $request = $this->review($draw);
        $this->postJson(route('backend.draw.progress', $draw), $request)->assertOk();
        $draw = $draw->fresh(['groups.groupRegistrations']);
        $top = $draw->groups->where('round_robin_phase', 2)->firstWhere('qualifying_position', 1);
        $lower = $draw->groups->where('round_robin_phase', 2)->firstWhere('qualifying_position', 3);
        $this->assertCount(4, $top->groupRegistrations);
        $this->assertCount(3, $lower->groupRegistrations);
        $this->assertNotContains($withdrawn, $draw->groups->where('round_robin_phase', 2)->flatMap->groupRegistrations->pluck('registration_id'));
        $places = app(DrawFinalPlacementService::class)->forDraw($draw);
        $this->assertSame(range(1, 8), $places->pluck('position')->all());
        $this->assertSame('bye', $places->firstWhere('position', 8)['status']);
    }

    public function test_full_programme_schedules_after_first_stage_with_no_possible_player_overlap(): void
    {
        [$draw] = $this->scenario();
        $venue = Venue::forceCreate(['name' => 'Example courts']);
        $draw->event->venues()->attach($venue->id, ['num_courts' => 4]);
        $draw->venues()->attach($venue->id, ['num_courts' => 4]);
        $options = ['day_batch' => true, 'full_day_scope' => '2026-11-28', 'start' => '2026-11-28 08:00', 'end' => '2026-11-28 22:00',
            'draw_ids' => [$draw->id], 'draw_order' => [$draw->id], 'draw_order_mode' => 'mixed',
            'draw_rounds' => [['draw_id' => $draw->id, 'rounds' => [1, 2, 3, 4, 5]]],
            'selected_fixture_keys' => $draw->drawFixtures()->pluck('id')->map(fn ($id) => 'individual:'.$id)->all(),
            'venue_ids' => [$venue->id], 'day_court_labels' => [$venue->id => ['1', '2', '3', '4']],
            'duration' => 30, 'player_rest' => 15, 'court_gap' => 0];
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($draw->event, $options);
        $this->assertSame([], $preview['unscheduled']);
        $this->assertCount(20, $preview['matches']);
        $service->apply($draw->event, $options, $preview['revision']);
        $firstIds = $draw->drawFixtures->whereNotNull('registration1_id')->modelKeys();
        $lastFirst = OrderOfPlay::whereIn('fixture_id', $firstIds)->max('time');
        $earliestSecond = OrderOfPlay::where('draw_id', $draw->id)->whereNotIn('fixture_id', $firstIds)->min('time');
        $this->assertGreaterThanOrEqual(strtotime($lastFirst) + 45 * 60, strtotime($earliestSecond));
    }

    public function test_ordinary_name_form_uses_existing_authorization_and_preserves_the_draw_programme(): void
    {
        [$draw, , $actor] = $this->scenario();
        $fixture = $draw->drawFixtures()->whereNotNull('registration1_id')->firstOrFail();
        app(DrawService::class)->saveScore($fixture, [[6, 0]]);
        $venue = Venue::forceCreate(['name' => 'Published name review courts']);
        OrderOfPlay::create(['fixture_id' => $fixture->id, 'draw_id' => $draw->id, 'venue_id' => $venue->id,
            'court' => '1', 'time' => '2026-11-28 08:00', 'duration_minutes' => 30]);
        DB::table('published_schedule_assignments')->insert(['event_id' => $draw->event_id, 'draw_id' => $draw->id,
            'fixture_kind' => 'individual', 'fixture_id' => $fixture->id, 'venue_id' => $venue->id, 'court' => '1',
            'scheduled_at' => '2026-11-28 08:00:00', 'duration' => 30, 'published_at' => now()]);
        $draw->update(['published' => true, 'oop_published' => true]);
        $results = DB::table('fixture_results')->orderBy('id')->get()->toJson();
        $bookings = OrderOfPlay::where('draw_id', $draw->id)->orderBy('id')->get()->toJson();
        $publication = DB::table('published_schedule_assignments')->where('draw_id', $draw->id)->orderBy('id')->get()->toJson();
        $page = $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk();
        $page->assertSee('id="round-robin-draw-name"', false)->assertSee(route('backend.draw.rename', $draw), false);
        $fixtures = $draw->drawFixtures()->orderBy('id')->get()->toJson();
        $groups = $draw->groups()->with('groupRegistrations')->orderBy('id')->get()->toJson();
        $settings = $draw->settings->only(['workflow', 'playoff_config', 'score_format', 'num_sets']);
        $this->post(route('backend.draw.rename', $draw), ['name' => 'Group B'])->assertRedirect();
        $this->assertSame('Group B', $draw->fresh()->drawName);
        $this->assertSame($fixtures, $draw->drawFixtures()->orderBy('id')->get()->toJson());
        $this->assertSame($groups, $draw->groups()->with('groupRegistrations')->orderBy('id')->get()->toJson());
        $this->assertSame($settings, $draw->fresh()->settings->only(array_keys($settings)));
        $this->assertSame($results, DB::table('fixture_results')->orderBy('id')->get()->toJson());
        $this->assertSame($bookings, OrderOfPlay::where('draw_id', $draw->id)->orderBy('id')->get()->toJson());
        $this->assertSame($publication, DB::table('published_schedule_assignments')->where('draw_id', $draw->id)->orderBy('id')->get()->toJson());
        $audit = \App\Models\DrawAuditLog::where('draw_id', $draw->id)->where('action', 'renamed')->sole();
        $this->assertSame($actor->id, $audit->user_id);
        $this->assertSame('Group B', $audit->payload['name']);
        $this->actingAs(User::factory()->create()->assignRole('admin'))->post(route('backend.draw.rename', $draw), ['name' => 'Foreign change'])->assertForbidden();
        $this->actingAs($actor);
        $draw->update(['published' => true]);
        $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk()->assertSee('id="round-robin-draw-name"', false);
        $draw->update(['oop_published' => true]);
        $this->post(route('backend.draw.rename', $draw), ['name' => 'Published Group B', 'published' => false, 'score_format' => 'timed', 'draw_type' => 123])->assertRedirect();
        $this->assertSame('Published Group B', $draw->fresh()->drawName);
        $this->assertSame(1, (int) $draw->fresh()->published);
        $this->assertSame(1, (int) $draw->fresh()->oop_published);
        $this->assertSame($fixtures, $draw->drawFixtures()->orderBy('id')->get()->toJson());
        $this->assertSame($groups, $draw->groups()->with('groupRegistrations')->orderBy('id')->get()->toJson());
        $this->assertSame($settings, $draw->fresh()->settings->only(array_keys($settings)));
        $this->assertSame($results, DB::table('fixture_results')->orderBy('id')->get()->toJson());
        $this->assertSame($bookings, OrderOfPlay::where('draw_id', $draw->id)->orderBy('id')->get()->toJson());
        $this->assertSame($publication, DB::table('published_schedule_assignments')->where('draw_id', $draw->id)->orderBy('id')->get()->toJson());
        $draw->update(['locked' => true]);
        $this->post(route('backend.draw.rename', $draw), ['name' => 'Locked change'])->assertForbidden();
        $this->assertSame('Published Group B', $draw->fresh()->drawName);
        $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk()->assertDontSee('id="round-robin-draw-name"', false);
    }
}
