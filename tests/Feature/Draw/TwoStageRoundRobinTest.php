<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\{DrawRulesService, RoundRobinGenerationService, StandingsService, TennisScoreFormat};
use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, Event, Fixture, OrderOfPlay, Player, Registration, User, Venue};
use App\Services\Draw\{DrawFinalPlacementService, DrawResultOrderService, TwoStageRoundRobinService};
use App\Services\{DrawService, ScheduleEngine};
use App\Services\Scheduling\EventVenueScheduleService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TwoStageRoundRobinTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(bool $complete = true, bool $timed = false): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $event = Event::factory()->create(['eventType' => 6, 'published' => true, 'start_date' => '2026-10-30', 'end_date' => '2026-10-31']);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id,
            'drawName' => 'Nine-player two-stage round robin', 'published' => false, 'locked' => false]);
        $draw->settings()->create(['workflow' => 'round_robin_round_robin', 'boxes' => 3, 'num_sets' => 1,
            'score_format' => $timed ? TennisScoreFormat::TIMED : TennisScoreFormat::ONE_FULL_SET,
            'match_duration_minutes' => $timed ? 20 : null, 'changeover_minutes' => $timed ? 5 : null]);
        $entrants = [];
        foreach (['A', 'B', 'C'] as $name) {
            $group = $draw->groups()->create(['name' => $name]);
            foreach (range(1, 3) as $seed) {
                $registration = Registration::factory()->create();
                $registration->players()->attach(Player::factory()->create(['name' => $name.$seed, 'surname' => 'Example'])->id);
                CategoryEventRegistration::factory()->create(['category_event_id' => $category->id,
                    'registration_id' => $registration->id, 'payment_status_id' => 1]);
                $draw->registrations()->attach($registration->id);
                $group->registrations()->attach($registration->id, ['seed' => $seed]);
                $entrants[$name.$seed] = $registration->id;
            }
        }
        app(RoundRobinGenerationService::class)->generate($draw);
        if ($complete) $this->completeFixtures($draw->drawFixtures()->get());
        return [$draw->fresh(), $entrants, $admin];
    }

    private function completeFixtures($fixtures): void
    {
        foreach ($fixtures->filter(fn ($fixture) => $fixture->registration1_id && $fixture->registration2_id) as $fixture) {
            app(DrawService::class)->saveScore($fixture, $fixture->registration1_id < $fixture->registration2_id ? [[6, 0]] : [[0, 6]]);
        }
    }

    private function progress(Draw $draw)
    {
        $review = $this->getJson(route('backend.draw.progress-review', $draw))->assertOk()->json('review');
        return $this->postJson(route('backend.draw.progress', $draw), [
            'confirmed_group_ids' => collect($review['groups'])->pluck('id')->all(), 'standings_snapshot' => $review['snapshot'],
        ]);
    }

    public function test_new_draws_prepare_the_full_programme_without_qualifying_anyone(): void
    {
        [$draw] = $this->scenario(false);
        $second = $draw->drawFixtures()->whereHas('drawGroup', fn ($query) => $query->where('round_robin_phase', 2))->get();
        $this->assertCount(9, $second);
        $this->assertFalse($draw->hasConfirmedSecondRoundRobin());
        $this->assertTrue($second->every(fn ($fixture) => !$fixture->registration1_id && !$fixture->registration2_id));
        $this->assertSame(0, $draw->groups()->where('round_robin_phase', 2)->withCount('groupRegistrations')->get()->sum('group_registrations_count'));
        $this->assertCount(0, app(TwoStageRoundRobinService::class)->prepare($draw));
        $this->assertFalse(app(\App\Services\Draw\GroupAssignmentService::class)->fixturesNeedRegeneration($draw));
        $fixture = $second->first();
        $this->postJson(route('backend.roundrobin.score.store', $fixture), ['sets' => ['6-0']])->assertConflict();
        $this->postJson(route('api.draws.fixtures.score.store', ['draw' => $draw, 'fixture' => $fixture]), ['sets' => ['6-0']])->assertConflict();
        $first = $draw->drawFixtures()->whereHas('drawGroup', fn ($query) => $query->where('round_robin_phase', 1))->get();
        foreach ($first as $fixture) {
            $this->postJson(route('backend.roundrobin.score.store', $fixture), ['sets' => [$fixture->registration1_id < $fixture->registration2_id ? '6-0' : '0-6']])->assertOk();
        }
        $this->assertTrue($second->every(fn ($fixture) => !$fixture->fresh()->registration1_id && !$fixture->fresh()->registration2_id));
        $this->assertSame(9, DB::table('fixture_results')->count());
    }

    public function test_placeholder_schedule_and_public_labels_survive_confirmed_progression(): void
    {
        [$draw] = $this->scenario(false);
        $venue = Venue::forceCreate(['name' => 'Placeholder courts']);
        $draw->event->venues()->attach($venue->id, ['num_courts' => 3]);
        $draw->venues()->attach($venue->id, ['num_courts' => 3]);
        $options = ['day_batch' => true, 'full_day_scope' => '2026-10-30', 'start' => '2026-10-30 08:00', 'end' => '2026-10-30 22:00',
            'draw_ids' => [$draw->id], 'draw_order' => [$draw->id], 'draw_order_mode' => 'mixed',
            'draw_rounds' => [['draw_id' => $draw->id, 'rounds' => [1, 2, 3, 4, 5, 6]]],
            'selected_fixture_keys' => $draw->drawFixtures()->pluck('id')->map(fn ($id) => 'individual:'.$id)->all(),
            'venue_ids' => [$venue->id], 'day_court_labels' => [$venue->id => ['1', '2', '3']],
            'duration' => 30, 'player_rest' => 15, 'court_gap' => 0];
        $service = app(EventVenueScheduleService::class);
        $preview = $service->preview($draw->event, $options);
        $this->assertSame([], $preview['unscheduled']);
        $this->assertCount(18, $preview['matches']);
        $service->apply($draw->event, $options, $preview['revision']);
        $before = OrderOfPlay::where('draw_id', $draw->id)->orderBy('id')->get()->toJson();
        $ids = $draw->drawFixtures()->orderBy('id')->pluck('id')->all();
        $publication = app(\App\Services\Scheduling\SchedulePublicationService::class);
        $working = $publication->workingRows($draw->event);
        $placeholder = $working->first(fn ($row) => str_starts_with($row['participants'][0] ?? '', 'Group '));
        $this->assertNotNull($placeholder);
        $draw->update(['published' => true]);
        $draw->settings->update(['schedule_visibility' => 'full_schedule']);
        $publication->publish($draw->event, ['draw_id' => $draw->id]);
        $publishedBefore = DB::table('published_schedule_assignments')->where('draw_id', $draw->id)->orderBy('id')->get()->toJson();
        $publicBefore = $publication->publishedRows($draw->event)->firstWhere('fixture_id', $placeholder['fixture_id']);
        $this->assertSame($placeholder['participants'], $publicBefore['participants']);
        $hub = app(DrawService::class)->loadRoundRobinHub($draw->fresh());
        $this->assertStringStartsWith('Group ', collect($hub['oops'])->firstWhere('id', $placeholder['fixture_id'])['home']);
        $this->completeFixtures($draw->drawFixtures()->get());
        $this->progress($draw)->assertOk();
        $this->assertTrue($draw->fresh()->hasConfirmedSecondRoundRobin());
        $this->assertSame($before, OrderOfPlay::where('draw_id', $draw->id)->orderBy('id')->get()->toJson());
        $this->assertSame($ids, $draw->drawFixtures()->orderBy('id')->pluck('id')->all());
        $this->assertSame($publishedBefore, DB::table('published_schedule_assignments')->where('draw_id', $draw->id)->orderBy('id')->get()->toJson());
        request()->attributes->remove('published_schedule_rows_'.$draw->event_id);
        $this->assertStringNotContainsString('Group ', $publication->publishedRows($draw->event)->firstWhere('fixture_id', $placeholder['fixture_id'])['participants'][0]);
        $this->assertStringNotContainsString('Group ', $publication->workingRows($draw->event)->firstWhere('fixture_id', $placeholder['fixture_id'])['participants'][0]);
    }

    public function test_existing_draw_preparation_is_authorized_event_scoped_and_idempotent(): void
    {
        [$draw, , $admin] = $this->scenario(false);
        $groupIds = $draw->groups()->where('round_robin_phase', 2)->pluck('id');
        $draw->drawFixtures()->whereIn('draw_group_id', $groupIds)->delete();
        $draw->groups()->whereIn('id', $groupIds)->delete();
        $url = route('backend.event-venue-schedule.prepare-playoffs', $draw->event_id);
        $this->actingAs(User::factory()->create()->assignRole('admin'))->post($url, ['draw_ids' => [$draw->id]])->assertForbidden();
        $this->actingAs($admin);
        $other = Draw::factory()->create();
        $this->post($url, ['draw_ids' => [$other->id]])->assertUnprocessable();
        $this->assertSame(9, $draw->drawFixtures()->count());
        $this->post($url, ['draw_ids' => [$draw->id]])->assertRedirect();
        $ids = $draw->drawFixtures()->orderBy('id')->pluck('id')->all();
        $this->post($url, ['draw_ids' => [$draw->id]])->assertRedirect();
        $this->assertSame($ids, $draw->drawFixtures()->orderBy('id')->pluck('id')->all());
        $this->assertCount(18, $ids);
    }

    public function test_missing_placeholder_match_blocks_confirmation_without_partial_membership(): void
    {
        [$draw] = $this->scenario();
        $draw->drawFixtures()->whereHas('drawGroup', fn ($query) => $query->where('round_robin_phase', 2))->first()->delete();
        $this->progress($draw)->assertConflict();
        $this->assertFalse($draw->fresh()->hasConfirmedSecondRoundRobin());
        $this->assertSame(0, $draw->groups()->where('round_robin_phase', 2)->withCount('groupRegistrations')->get()->sum('group_registrations_count'));
    }

    public function test_unequal_four_and_five_player_groups_prepare_four_matches_and_a_singleton_position(): void
    {
        [$draw] = $this->scenario(false);
        $draw->drawFixtures()->delete();
        $draw->groups()->where('round_robin_phase', 2)->delete();
        $groups = $draw->groups()->orderBy('name')->get();
        $moved = $groups[2]->groupRegistrations->pluck('registration_id')->all();
        $groups[2]->registrations()->detach();
        $groups[2]->delete();
        $groups[0]->registrations()->attach($moved[0], ['seed' => 4]);
        $groups[1]->registrations()->attach($moved[1], ['seed' => 4]);
        $groups[1]->registrations()->attach($moved[2], ['seed' => 5]);
        app(RoundRobinGenerationService::class)->generate($draw->fresh());
        $first = $draw->drawFixtures()->whereHas('drawGroup', fn ($query) => $query->where('round_robin_phase', 1))->get();
        $second = $draw->drawFixtures()->whereHas('drawGroup', fn ($query) => $query->where('round_robin_phase', 2))->get();
        $this->assertCount(16, $first);
        $this->assertCount(4, $second);
        $this->assertSame([1, 2, 3, 4], $second->pluck('registration1_source_position')->all());
        $this->assertTrue($second->every(fn ($fixture) => !$fixture->registration1_id && !$fixture->registration2_id));
        $this->completeFixtures($first);
        $this->progress($draw)->assertOk();
        $singleton = $draw->groups()->where('round_robin_phase', 2)->where('qualifying_position', 5)->firstOrFail();
        $this->assertSame(1, $singleton->groupRegistrations()->count());
        $this->assertSame(0, $singleton->fixtures()->count());
        $this->assertSame(20, $draw->drawFixtures()->count());
    }

    public function test_format_is_selectable_without_replacing_unplayed_round_robin_groups_or_fixtures(): void
    {
        [$draw] = $this->scenario(false);
        $secondGroupIds = $draw->groups()->where('round_robin_phase', 2)->pluck('id');
        $draw->drawFixtures()->whereIn('draw_group_id', $secondGroupIds)->delete();
        $draw->groups()->whereIn('id', $secondGroupIds)->delete();
        $draw->settings->update(['workflow' => 'round_robin']);
        $groups = $draw->groups()->pluck('id')->all();
        $fixtures = $draw->drawFixtures()->pluck('id')->all();
        $this->get(route('draw.setup.show', $draw))->assertOk()->assertSee('Round robin → round robin');
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'round_robin_round_robin'])->assertRedirect();
        $this->assertSame('round_robin_round_robin', $draw->fresh()->settings->workflow);
        $this->assertSame($groups, $draw->groups()->pluck('id')->all());
        $this->assertSame($fixtures, $draw->drawFixtures()->pluck('id')->all());
        $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk()->assertSee('value="round_robin_round_robin"', false);
        $this->get(route('headOffice.show', $draw->event_id))->assertOk()->assertSee('Round robin → round robin');
        $this->get(route('backend.event-venue-schedule.index', ['event' => $draw->event_id, 'draw_ids' => [$draw->id]]))
            ->assertOk()->assertSee('Prepare second-stage matches');
    }

    public function test_nine_players_progress_once_preserving_first_stage_and_finish_with_four_matches_each(): void
    {
        [$draw, $entrants] = $this->scenario();
        $firstIds = $draw->drawFixtures()->whereHas('drawGroup', fn ($query) => $query->where('round_robin_phase', 1))->orderBy('id')->pluck('id')->all();
        $firstResults = DB::table('fixture_results')->whereIn('fixture_id', $firstIds)->orderBy('id')->get()->toJson();
        $venue = Venue::forceCreate(['name' => 'Example courts']);
        $booking = OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $firstIds[0], 'venue_id' => $venue->id,
            'court' => '1', 'time' => '2026-10-30 08:00', 'duration_minutes' => 30]);
        $draw->update(['published' => true]);
        $this->progress($draw)->assertOk()->assertJsonPath('progress.created', 0);
        $this->progress($draw)->assertOk()->assertJsonPath('progress.created', 0);
        $this->assertSame($firstIds, $draw->drawFixtures()->whereIn('id', $firstIds)->orderBy('id')->pluck('id')->all());
        $this->assertSame($firstResults, DB::table('fixture_results')->whereIn('fixture_id', $firstIds)->orderBy('id')->get()->toJson());
        $this->assertSame('2026-10-30 08:00:00', Carbon::parse($booking->fresh()->time)->toDateTimeString());
        $this->assertSame(18, $draw->drawFixtures()->count());
        $secondGroups = $draw->groups()->where('round_robin_phase', 2)->orderBy('qualifying_position')->get();
        foreach ($secondGroups as $index => $group) {
            $position = $index + 1;
            $this->assertSame([$entrants['A'.$position], $entrants['B'.$position], $entrants['C'.$position]],
                $group->groupRegistrations->pluck('registration_id')->all());
            $rows = app(StandingsService::class)->forDraw($draw->fresh())[$group->id];
            $this->assertSame(0, collect($rows)->sum('wins'));
        }
        $secondIds = $secondGroups->pluck('id');
        $second = $draw->drawFixtures()->whereIn('draw_group_id', $secondIds)->get();
        foreach ($second as $index => $fixture) {
            $route = $index % 2 ? route('backend.roundrobin.score.store', $fixture)
                : route('api.draws.fixtures.score.store', ['draw' => $draw, 'fixture' => $fixture]);
            $this->postJson($route, ['sets' => [$fixture->registration1_id < $fixture->registration2_id ? '6-0' : '0-6']])->assertOk();
        }
        $placements = app(DrawFinalPlacementService::class)->forDraw($draw->fresh());
        $this->assertSame(range(1, 9), $placements->pluck('position')->all());
        $this->assertSame(['resolved'], $placements->pluck('status')->unique()->values()->all());
        $expected = [];
        foreach (range(1, 3) as $position) foreach (['A', 'B', 'C'] as $name) $expected[] = $entrants[$name.$position];
        $this->assertSame($expected, $placements->pluck('registration_id')->all());
        foreach ($entrants as $id) $this->assertSame(4, $draw->drawFixtures()->where(fn ($q) => $q->where('registration1_id', $id)->orWhere('registration2_id', $id))->count());
        $hub = app(DrawService::class)->loadRoundRobinHub($draw->fresh());
        $this->assertSame([7, 8, 9], array_column($hub['standings'][$secondGroups[2]->id], 'display_position'));
        $this->assertCount(18, $hub['oops']);
        $page = $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk()->assertSee('Stage 2 groups and matches are ready');
        if (getenv('DRAW_GUIDE_SNAPSHOT_DIR')) file_put_contents(getenv('DRAW_GUIDE_SNAPSHOT_DIR').'/rr-two-stage-complete.html', $page->getContent());
        $this->app['auth']->forgetGuards();
        $public = $this->get(route('public.roundrobin.show', $draw))->assertOk();
        if (getenv('DRAW_GUIDE_SNAPSHOT_DIR')) file_put_contents(getenv('DRAW_GUIDE_SNAPSHOT_DIR').'/rr-two-stage-public.html', $public->getContent());
    }

    public function test_incomplete_stale_and_unconfirmed_reviews_cannot_progress(): void
    {
        [$draw] = $this->scenario(false);
        $this->getJson(route('backend.draw.progress-review', $draw))->assertUnprocessable();
        $this->postJson(route('backend.draw.progress', $draw))->assertUnprocessable();
        $this->completeFixtures($draw->drawFixtures()->get());
        $review = $this->getJson(route('backend.draw.progress-review', $draw))->assertOk()->json('review');
        $this->postJson(route('backend.draw.progress', $draw), ['standings_snapshot' => $review['snapshot']])->assertUnprocessable();
        $fixture = $draw->drawFixtures()->first();
        app(DrawService::class)->saveScore($fixture, $fixture->registration1_id < $fixture->registration2_id ? [[6, 1]] : [[1, 6]]);
        $this->postJson(route('backend.draw.progress', $draw), ['confirmed_group_ids' => collect($review['groups'])->pluck('id')->all(),
            'standings_snapshot' => $review['snapshot']])->assertConflict();
        $this->assertSame(6, $draw->groups()->count());
        $this->assertSame(18, $draw->drawFixtures()->count());
    }

    public function test_lower_second_stage_group_cannot_prefill_the_undecided_top_positions(): void
    {
        [$draw, $entrants] = $this->scenario();
        $this->progress($draw)->assertOk();
        $lowerGroup = $draw->groups()->where('round_robin_phase', 2)->where('qualifying_position', 2)->firstOrFail();
        $this->completeFixtures($lowerGroup->fixtures);
        $placements = app(DrawFinalPlacementService::class)->forDraw($draw->fresh());
        $this->assertSame([4, 5, 6], $placements->where('status', 'resolved')->pluck('position')->values()->all());
        $order = app(DrawResultOrderService::class)->forCategory($draw->categoryEvent,
            collect([$draw->fresh()]), collect(array_values($entrants)), collect([$draw->category_event_id => collect(array_values($entrants))]));
        $this->assertTrue($order->isEmpty());
    }

    public function test_equal_cyclic_positions_are_blocked_in_both_phases(): void
    {
        [$draw] = $this->scenario();
        $this->progress($draw)->assertOk();
        $second = $draw->groups()->where('round_robin_phase', 2)->first();
        $this->cycle($draw, $second);
        $rows = app(DrawFinalPlacementService::class)->forDraw($draw->fresh())->where('position', '<=', 3);
        $this->assertSame(['awaiting'], $rows->pluck('status')->unique()->values()->all());
        [$other] = $this->scenario();
        $this->cycle($other, $other->groups()->first());
        $this->getJson(route('backend.draw.progress-review', $other))->assertUnprocessable();
        $this->assertSame(18, $other->drawFixtures()->count());
    }

    private function cycle(Draw $draw, $group): void
    {
        $ids = $group->groupRegistrations->pluck('registration_id')->sort()->values()->all();
        $winners = [$ids[0].':'.$ids[1] => $ids[0], $ids[1].':'.$ids[2] => $ids[1], $ids[0].':'.$ids[2] => $ids[2]];
        foreach ($group->fixtures as $fixture) {
            $pair = [$fixture->registration1_id, $fixture->registration2_id]; sort($pair);
            app(DrawService::class)->saveScore($fixture, $winners[implode(':', $pair)] === $fixture->registration1_id ? [[6, 0]] : [[0, 6]]);
        }
    }

    public function test_phase_one_is_frozen_on_both_scoring_routes_and_rollback_without_affecting_phase_two(): void
    {
        [$draw] = $this->scenario();
        $first = $draw->drawFixtures()->first();
        $this->progress($draw)->assertOk();
        $this->postJson(route('backend.roundrobin.score.store', $first), ['sets' => ['6-1']])->assertConflict();
        $this->postJson(route('api.draws.fixtures.score.store', ['draw' => $draw, 'fixture' => $first]), ['sets' => ['6-1']])->assertConflict();
        $this->deleteJson(route('backend.roundrobin.score.delete', $first))->assertConflict();
        $this->deleteJson(route('api.draws.fixtures.score.delete', ['draw' => $draw, 'fixture' => $first]))->assertConflict();
        $draw->update(['published' => false]);
        $this->postJson(route('backend.draw.save-groups', $draw), ['groups' => []])->assertConflict();
        $this->assertSame(18, $draw->drawFixtures()->count());
        $this->assertSame(9, DB::table('fixture_results')->count());
    }

    public function test_timed_second_phase_accepts_draws_and_does_not_award_unresolved_final_positions(): void
    {
        [$draw] = $this->scenario(true, true);
        $this->progress($draw)->assertOk();
        $group = $draw->groups()->where('round_robin_phase', 2)->first();
        foreach ($group->fixtures as $fixture) $this->postJson(route('backend.roundrobin.score.store', $fixture), ['sets' => ['3-3']])->assertOk();
        $standings = app(StandingsService::class)->forDraw($draw->fresh())[$group->id];
        $this->assertSame([2, 2, 2], array_column($standings, 'points'));
        $this->assertSame([2, 2, 2], array_column($standings, 'draws'));
        $this->assertSame(['awaiting'], app(DrawFinalPlacementService::class)->forDraw($draw->fresh())->where('position', '<=', 3)->pluck('status')->unique()->values()->all());
        [$other] = $this->scenario(false, true);
        foreach ($other->drawFixtures->filter(fn ($fixture) => $fixture->registration1_id && $fixture->registration2_id) as $fixture) app(DrawService::class)->saveScore($fixture, [[3, 3]]);
        $this->getJson(route('backend.draw.progress-review', $other))->assertUnprocessable();
    }

    public function test_progression_respects_event_authorization_and_locks(): void
    {
        [$draw, , $admin] = $this->scenario();
        $outsider = User::factory()->create()->assignRole('admin');
        $this->actingAs($outsider)->getJson(route('backend.draw.progress-review', $draw))->assertForbidden();
        $this->postJson(route('backend.draw.progress', $draw))->assertForbidden();
        $this->actingAs($admin);
        $draw->update(['locked' => true]);
        $this->getJson(route('backend.draw.progress-review', $draw))->assertForbidden();
        $this->postJson(route('backend.draw.progress', $draw))->assertForbidden();
        $this->assertSame(18, $draw->drawFixtures()->count());
    }

    public function test_public_views_and_print_data_include_both_stages_and_hide_unpublished_times(): void
    {
        [$draw] = $this->scenario();
        $this->progress($draw)->assertOk();
        $second = $draw->drawFixtures()->whereNotIn('draw_group_id', $draw->groups()->where('round_robin_phase', 1)->pluck('id'))->first();
        $venue = Venue::forceCreate(['name' => 'Private unsaved venue']);
        OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $second->id, 'venue_id' => $venue->id,
            'court' => 'Secret court', 'time' => '2026-10-30 17:43', 'duration_minutes' => 30]);
        $draw->update(['published' => true, 'oop_published' => false]);
        $this->app['auth']->forgetGuards();
        $public = $this->get(route('public.roundrobin.show', $draw))->assertOk()
            ->assertSee('Stage 1')->assertSee('Stage 2')->assertDontSee('Secret court')->assertDontSee('Private unsaved venue');
        $this->assertCount(6, $public->viewData('groupsJson'));
        $this->assertCount(18, $public->viewData('oops'));
        $this->assertSame([], collect($public->viewData('oops'))->pluck('time')->filter()->all());
        $rules = app(DrawRulesService::class)->printable($draw->fresh());
        $this->assertArrayHasKey('Two-stage round robin', $rules);
        $this->assertStringContainsString('All first-place finishers', $rules['Two-stage round robin']);
        $this->assertSame(18, $draw->drawFixtures()->count());
        $draw->update(['published' => false]);
        $this->get(route('public.roundrobin.show', $draw))->assertForbidden();
    }

    public function test_automatic_and_manual_scheduling_enforce_phase_barrier_and_rest(): void
    {
        [$draw] = $this->scenario();
        $event = $draw->event;
        $venue = Venue::forceCreate(['name' => 'Three courts']);
        $event->venues()->attach($venue->id, ['num_courts' => 3]);
        $draw->venues()->attach($venue->id, ['num_courts' => 3]);
        foreach ($draw->groups->where('round_robin_phase', 1)->values() as $court => $group) foreach ($group->fixtures->values() as $index => $fixture) {
            OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $fixture->id, 'venue_id' => $venue->id,
                'court' => (string) ($court + 1), 'time' => '2026-10-30 '.(8 + $index).':00', 'duration_minutes' => 30, 'gap_minutes' => 0]);
        }
        $this->progress($draw)->assertOk();
        $service = app(EventVenueScheduleService::class);
        $options = ['day_batch' => true, 'full_day_scope' => '2026-10-30', 'start' => '2026-10-30 08:00', 'end' => '2026-10-30 22:00',
            'draw_ids' => [$draw->id], 'draw_order' => [$draw->id], 'draw_order_mode' => 'mixed',
            'draw_rounds' => [['draw_id' => $draw->id, 'rounds' => [1, 2, 3, 4, 5, 6]]],
            'selected_fixture_keys' => $draw->drawFixtures()->pluck('id')->map(fn ($id) => 'individual:'.$id)->all(),
            'venue_ids' => [$venue->id], 'day_court_labels' => [$venue->id => ['1', '2', '3']],
            'duration' => 30, 'player_rest' => 15, 'court_gap' => 0];
        $preview = $service->preview($event, $options);
        $this->assertSame([], $preview['unscheduled']);
        $secondIds = $draw->drawFixtures()->whereIn('draw_group_id', $draw->groups()->where('round_robin_phase', 2)->pluck('id'))->pluck('id');
        $secondMatches = collect($preview['matches'])->filter(fn ($match) => $secondIds->contains($match['fixture_id']));
        $this->assertCount(9, $secondMatches);
        $this->assertGreaterThanOrEqual('2026-10-30 10:45:00', $secondMatches->min('scheduled_at'));
        $service->apply($event, $options, $preview['revision']);
        $this->assertSame(18, OrderOfPlay::where('draw_id', $draw->id)->count());
        $fixture = Fixture::findOrFail($secondIds->first());
        $error = app(ScheduleEngine::class)->dependencyConflict($draw->fresh(), $fixture, '2026-10-30 10:40', 30, 15);
        $this->assertNotNull($error);
        $this->assertNull(app(ScheduleEngine::class)->dependencyConflict($draw->fresh(), $fixture, '2026-10-30 10:45', 30, 15));
        $before = $fixture->orderOfPlay->fresh()->toArray();
        $this->postJson(route('api.draws.schedule.save', $draw), ['items' => [[
            'fixture_id' => $fixture->id, 'venue_id' => $venue->id, 'court' => '1',
            'start_time' => '2026-10-30 10:10', 'duration_minutes' => 30,
        ]]])->assertUnprocessable();
        $this->assertSame($before, $fixture->orderOfPlay->fresh()->toArray());
        $rounds = $service->availableDrawRounds(collect([$draw->fresh()]));
        $this->assertSame([1, 2, 3, 4, 5, 6], $rounds[$draw->id]);
        // The legacy draw auto-scheduler must also retain phase ordering and
        // rest between two new matches sharing a player.
        OrderOfPlay::whereIn('fixture_id', $secondIds)->delete();
        app(ScheduleEngine::class)->autoSchedule($draw->id, 30,
            [$venue->id => ['courts' => ['1', '2', '3']]], '2026-10-30 08:00');
        $secondSlots = Fixture::whereIn('id', $secondIds)->with('orderOfPlay')->get();
        foreach ($secondSlots->flatMap(fn ($match) => [$match->registration1_id, $match->registration2_id])->unique() as $id) {
            $times = $secondSlots->filter(fn ($match) => in_array($id, [$match->registration1_id, $match->registration2_id], true))
                ->map(fn ($match) => Carbon::parse($match->orderOfPlay->time))->sort()->values();
            $this->assertGreaterThanOrEqual(45, $times[0]->diffInMinutes($times[1]));
        }
        $this->assertGreaterThanOrEqual('2026-10-30 10:45:00', $secondSlots->map(fn ($match) => (string) $match->orderOfPlay->time)->min());
    }
}
