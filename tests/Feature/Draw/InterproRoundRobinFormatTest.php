<?php

namespace Tests\Feature\Draw;

use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, Event, EventType, Fixture, OrderOfPlay, Player, Registration, User, Venue};
use App\Services\Draw\{DrawFinalPlacementService, IndividualScoreRevision, InterproRoundRobinFormat, RoundRobinProgressReadiness};
use App\Services\{DrawService, IndividualMatchOutcomeService};
use App\Services\Scheduling\EventVenueScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class InterproRoundRobinFormatTest extends TestCase
{
    use RefreshDatabase;

    private function context(array $sizes = [4, 4, 4, 4]): Draw
    {
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Fictional Interpro Trials', 'type' => EventType::INDIVIDUAL, 'code' => EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $event = Event::factory()->create(['eventType' => $type, 'start_date' => '2026-11-27', 'end_date' => '2026-11-29']);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'published' => false, 'oop_published' => false, 'locked' => false]);
        $draw->settings()->create(['workflow' => 'round_robin_playoffs', 'preset_key' => InterproRoundRobinFormat::PRESET,
            'playoff_config' => InterproRoundRobinFormat::configuration(), 'boxes' => 4, 'num_sets' => 1, 'score_format' => 'one_set_to_6']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        $nr = 0;
        foreach ($sizes as $index => $size) {
            $group = $draw->groups()->create(['name' => chr(65 + $index)]);
            $registrations = [];
            for ($i = 0; $i < $size; $i++) {
                $registration = Registration::factory()->create();
                $registration->players()->attach(Player::factory()->create()->id);
                CategoryEventRegistration::factory()->create(['category_event_id' => $category->id, 'registration_id' => $registration->id, 'payment_status_id' => 1]);
                $group->registrations()->attach($registration->id, ['seed' => $i + 1]);
                $registrations[] = $registration;
            }
            for ($one = 0; $one < $size; $one++) for ($two = $one + 1; $two < $size; $two++) {
                $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $group->id, 'stage' => 'RR', 'match_nr' => ++$nr,
                    'registration1_id' => $registrations[$one]->id, 'registration2_id' => $registrations[$two]->id,
                    'match_status' => 1, 'winner_registration' => $registrations[$one]->id]);
                $fixture->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2,
                    'winner_registration' => $registrations[$one]->id, 'loser_registration' => $registrations[$two]->id]);
            }
        }
        return $draw->fresh();
    }

    private function progress(Draw $draw): void
    {
        $review = $this->getJson(route('backend.draw.progress-review', $draw))->assertOk()->json('review');
        $this->postJson(route('backend.draw.progress', $draw), ['confirmed_group_ids' => collect($review['groups'])->pluck('id')->all(),
            'standings_snapshot' => $review['snapshot']])->assertOk();
    }

    private function fixture(Draw $draw, int $number): Fixture
    {
        return $draw->drawFixtures()->where('match_nr', $number)->with(['draw.settings', 'fixtureResults'])->firstOrFail();
    }

    private function score(Draw $draw, int $number, int $slot = 1): void
    {
        app(DrawService::class)->saveBracketScore($this->fixture($draw, $number), $slot === 1 ? [[6, 2]] : [[2, 6]]);
    }

    private function reachCarries(Draw $draw): void
    {
        $this->progress($draw);
        foreach ([2001 => 1, 2002 => 2, 3001 => 1, 3002 => 1, 3003 => 1, 3004 => 1, 3005 => 2, 3006 => 2] as $nr => $slot) $this->score($draw, $nr, $slot);
    }

    public function test_fixed_topology_prepares_stable_sources_and_accepts_last_years_smaller_groups(): void
    {
        $draw = $this->context([4, 4, 3, 3]);
        $service = app(InterproRoundRobinFormat::class);
        $this->assertCount(18, $service->prepare($draw));
        $ids = $draw->drawFixtures()->pluck('id')->all();
        $this->assertCount(0, $service->prepare($draw));
        $this->assertSame($ids, $draw->drawFixtures()->pluck('id')->all());
        $this->assertSame($this->fixture($draw, 3011)->id, $this->fixture($draw, 3007)->loser_parent_fixture_id);
        $this->assertSame($this->fixture($draw, 3010)->id, $this->fixture($draw, 3005)->loser_parent_fixture_id);
        $this->assertSame(5, $this->fixture($draw, 3011)->position);
        $this->assertNull($this->fixture($draw, 2001)->registration1_id);
        $rr = $draw->drawFixtures()->where('stage', 'RR')->firstOrFail();
        app(\App\Services\Draw\DrawRecoveryImpactService::class)->assertSafeOrdinaryCorrection($rr);
        $draw->settings()->update(['score_format' => 'timed', 'match_duration_minutes' => 20]);
        $rr->fixtureResults()->first()->update(['registration1_score' => 4, 'registration2_score' => 4]);
        $rr->update(['winner_registration' => null]);
        $this->assertFalse(app(RoundRobinProgressReadiness::class)->forDraw($draw->fresh())['ready']);
        $rr->fixtureResults()->first()->update(['registration1_score' => 6, 'registration2_score' => 2]);
        $rr->update(['winner_registration' => $rr->registration1_id]);
        $draw->settings()->update(['score_format' => 'one_set_to_6']);
        $this->progress($draw);
        $this->assertNotNull($this->fixture($draw, 2001)->registration1_id);
        $this->assertSame(36, $draw->drawFixtures()->count());
    }

    public function test_fixture_list_links_to_the_exact_trial_playoff_without_bypassing_publication(): void
    {
        $draw = $this->context();
        app(InterproRoundRobinFormat::class)->prepare($draw);
        $before = $draw->drawFixtures()->orderBy('id')->get()->toJson();
        $link = route('public.roundrobin.show', $draw).'#playoffs';

        // Event administrators can navigate the same draft preview through either URL.
        $this->get(route('frontend.fixtures.index', $draw))->assertOk()
            ->assertSee('href="'.$link.'"', false)->assertSee('View playoff bracket');
        $this->get(route('public.roundrobin.show', $draw))->assertOk()->assertSee('Draft preview');

        $foreignAdmin = User::factory()->create()->assignRole('admin');
        $this->actingAs($foreignAdmin)->get(route('frontend.fixtures.index', $draw))->assertForbidden();
        $this->get(route('public.roundrobin.show', $draw))->assertForbidden();
        auth()->logout();
        $this->get(route('frontend.fixtures.index', $draw))->assertForbidden()->assertDontSee('View playoff bracket');
        $this->get(route('public.roundrobin.show', $draw))->assertForbidden();

        $draw->event->update(['published' => true]);
        $draw->update(['published' => true]);
        $this->get(route('frontend.fixtures.index', $draw))->assertOk()
            ->assertSee('href="'.$link.'"', false)->assertSee('View playoff bracket');
        $this->get(route('public.roundrobin.show', $draw))->assertOk()->assertSee('id="main-bracket-tab"', false);
        $bracket = $this->get(route('public.roundrobin.main-bracket', $draw))->assertOk();
        $this->assertSame(18, substr_count($bracket->getContent(), '<foreignObject '));
        $this->assertSame($before, $draw->drawFixtures()->orderBy('id')->get()->toJson());

        $draw->settings()->update(['preset_key' => null]);
        $this->get(route('frontend.fixtures.index', $draw))->assertOk()->assertDontSee('data-playoff-bracket-link', false);
    }

    public function test_empty_trial_fixture_list_still_links_to_its_planning_bracket(): void
    {
        $source = $this->context();
        $draw = Draw::factory()->create(['event_id' => $source->event_id, 'category_event_id' => $source->category_event_id,
            'published' => false, 'oop_published' => false]);
        $draw->settings()->create(['workflow' => 'round_robin_playoffs', 'preset_key' => InterproRoundRobinFormat::PRESET,
            'playoff_config' => InterproRoundRobinFormat::configuration()]);
        $this->get(route('frontend.fixtures.index', $draw))->assertOk()->assertSee('No matches are available')
            ->assertSee('href="'.route('public.roundrobin.show', $draw).'#playoffs"', false);
        $this->get(route('public.roundrobin.main-bracket', $draw))->assertOk()->assertSee('Group A position 1');
        $this->assertSame(0, $draw->drawFixtures()->count());
    }

    public function test_first_results_cascade_without_duplicate_evidence_and_determine_all_twelve_positions(): void
    {
        $draw = $this->context();
        $preview = getenv('INTERPRO_PREVIEW_EXPORT') === '1' ? new \Tests\Support\InterproPreviewExporter() : null;
        if ($preview) {
            $draw->update(['drawName' => 'Fictional U13 Interpro trials']);
            $draw->event->update(['name' => 'Fictional Interpro format review']);
            foreach ($draw->groups()->with('groupRegistrations.registration.players')->orderBy('name')->get() as $group) {
                foreach ($group->groupRegistrations->sortBy('seed')->values() as $index => $entry) {
                    $entry->registration->players->first()->update(['name' => 'Group '.$group->name, 'surname' => 'Player '.($index + 1)]);
                }
            }
            $setupEvent = Event::factory()->create(['eventType' => $draw->event->eventType, 'name' => 'Fictional format setup']);
            $setupCategory = CategoryEvent::factory()->create(['event_id' => $setupEvent->id]);
            $setupDraw = Draw::factory()->create(['event_id' => $setupEvent->id, 'category_event_id' => $setupCategory->id,
                'drawName' => 'Fictional U13 Interpro trials', 'published' => false, 'oop_published' => false, 'locked' => false]);
            $setupDraw->settings()->create(['workflow' => 'round_robin_playoffs', 'preset_key' => InterproRoundRobinFormat::PRESET,
                'playoff_config' => InterproRoundRobinFormat::configuration(), 'boxes' => 4, 'num_sets' => 1, 'score_format' => 'one_set_to_6']);
            foreach (['A', 'B', 'C', 'D'] as $name) $setupDraw->groups()->create(['name' => $name]);
            DB::table('event_admins')->insert(['event_id' => $setupEvent->id, 'user_id' => auth()->id()]);
            $preview->setup($this->get(route('draw.setup.show', $setupDraw))->assertOk()->getContent());
        }
        app(InterproRoundRobinFormat::class)->prepare($draw);
        $preview?->roundRobin($draw->fresh());
        $this->reachCarries($draw);
        if ($preview) $preview->stage('carry.html', '3 · Feed-in with first results carried',
            $this->get(route('backend.draw.main-bracket', $draw))->assertOk()->getContent());
        foreach ([3007, 3008] as $nr) {
            $fixture = $this->fixture($draw, $nr);
            $source = InterproRoundRobinFormat::source($fixture);
            $this->assertNotNull($source);
            $this->assertSame('RR', $source->stage);
            $this->assertSame(0, $fixture->match_status);
            $this->assertFalse($fixture->isWalkover());
            $this->assertSame(0, $fixture->fixtureResults()->count());
            $this->assertSame((int) $source->winner_registration, app(IndividualMatchOutcomeService::class)->winner($fixture));
        }
        foreach ([2003, 3010, 4001, 4002, 4003, 4004, 3009, 3011] as $nr) $this->score($draw, $nr);
        $positions = app(DrawFinalPlacementService::class)->forDraw($draw->fresh());
        $this->assertCount(12, $positions);
        $this->assertCount(12, $positions->pluck('registration_id')->unique());
        $this->assertSame(40, DB::table('fixture_results')->count());
        $before = $draw->drawFixtures()->get()->toJson();
        $this->progress($draw);
        $this->assertSame($before, $draw->drawFixtures()->get()->toJson());
        $this->assertSame(42, $draw->drawFixtures()->count());
        $finalPage = $this->get(route('backend.draw.main-bracket', $draw))->assertOk()->assertSee('First result stands')->assertSee('11/12')->assertSee('Final positions');
        $this->assertSame(18, substr_count($finalPage->getContent(), '<foreignObject '));
        $this->assertSame(3, substr_count($finalPage->getContent(), 'class="interpro-bracket-svg"'));
        foreach (InterproRoundRobinFormat::template() as $number => $definition) {
            $finalPage->assertSee('data-match="'.$number.'"', false);
        }
        $finalPage->assertSee('data-from="3007" data-to="3011"', false)
            ->assertSee('data-from="3008" data-to="3011"', false);
        $blankPage = $this->get(route('backend.draw.main-bracket', [$draw, 'empty' => 1]))->assertOk()->assertDontSee('6-2')->assertSee('Loser match 2002')->assertDontSee('Final positions');
        if ($preview) {
            $preview->stage('final.html', '4 · Completed trial draw and final positions', $finalPage->getContent());
            $preview->stage('blank.html', '6 · Blank planning draw', $blankPage->getContent());
            $preview->printPage($finalPage->getContent(), $draw->fresh());
            $preview->finish($draw->fresh());
        }
        auth()->logout();
        $draw->event->update(['published' => true]);
        $this->get(route('public.roundrobin.main-bracket', $draw))->assertForbidden();
        $draw->update(['published' => true]);
        $this->get(route('public.roundrobin.main-bracket', $draw))->assertOk()->assertSee('First result stands');
        $this->get(route('public.roundrobin.main-bracket', [$draw, 'empty' => 1]))->assertOk()->assertDontSee('6-2');
    }

    public function test_carry_releases_its_booking_and_schedule_preview_excludes_it(): void
    {
        $draw = $this->context();
        app(InterproRoundRobinFormat::class)->prepare($draw);
        $venue = Venue::forceCreate(['name' => 'Fictional trial courts']);
        $draw->event->venues()->attach($venue->id, ['num_courts' => 4]);
        $draw->venues()->attach($venue->id, ['num_courts' => 4]);
        $target = $this->fixture($draw, 3007);
        OrderOfPlay::create(['fixture_id' => $target->id, 'draw_id' => $draw->id, 'venue_id' => $venue->id, 'court' => '1', 'time' => '2026-11-28 09:00']);
        $this->reachCarries($draw);
        $this->assertDatabaseMissing('order_of_plays', ['fixture_id' => $target->id]);
        try { app(\App\Services\ScheduleEngine::class)->saveFixture($draw, $target->id, '2026-11-28 09:00', $venue->id, '1', 30); $this->fail('Carried match must not be booked.'); }
        catch (HttpException $exception) { $this->assertSame(409, $exception->getStatusCode()); }
        $preview = app(EventVenueScheduleService::class)->preview($draw->event, ['draw_ids' => [$draw->id], 'venue_ids' => [$venue->id],
            'start' => '2026-11-28 08:00', 'end' => '2026-11-28 20:00', 'duration' => 30, 'player_rest' => 15]);
        $this->assertNotContains($target->id, collect($preview['matches'])->pluck('fixture_id')->all());
        $draw->update(['published' => true, 'oop_published' => true]);
        $draw->event->update(['published' => true]);
        DB::table('published_schedule_assignments')->insert(['event_id' => $draw->event_id, 'draw_id' => $draw->id, 'fixture_kind' => 'individual',
            'fixture_id' => $target->id, 'scheduled_at' => '2026-11-28 09:00', 'venue_id' => $venue->id, 'court' => '1', 'duration' => 30, 'published_at' => now()]);
        $rows = app(\App\Services\Scheduling\SchedulePublicationService::class)->publishedRows($draw->event);
        $this->assertNotContains($target->id, $rows->pluck('fixture_id')->all());
        $this->assertDatabaseHas('published_schedule_assignments', ['fixture_id' => $target->id]);
    }

    public function test_carried_target_and_original_results_cannot_be_overwritten_or_deleted(): void
    {
        $draw = $this->context();
        app(InterproRoundRobinFormat::class)->prepare($draw);
        $this->reachCarries($draw);
        $target = $this->fixture($draw, 3007);
        $source = InterproRoundRobinFormat::source($target);
        $count = DB::table('fixture_results')->count();
        foreach ([$target, $source] as $fixture) {
            try { app(DrawService::class)->saveAnyScore($fixture, [[2, 6]]); $this->fail('Expected carried result protection.'); }
            catch (HttpException $exception) { $this->assertSame(409, $exception->getStatusCode()); }
        }
        $this->assertSame($count, DB::table('fixture_results')->count());
        $this->assertNotNull(InterproRoundRobinFormat::source($target->fresh()));
        $revision = app(IndividualScoreRevision::class)->forFixture($target);
        $this->deleteJson(route('backend.roundrobin.score.delete', $target), ['score_revision' => $revision])->assertConflict();
        $this->postJson(route('backend.roundrobin.score.store', $target), ['score_revision' => $revision, 'sets' => [[6, 2]]])->assertConflict();
        foreach (['saveWalkover', 'saveRetirement'] as $method) {
            try {
                if ($method === 'saveWalkover') app(DrawService::class)->saveWalkover($target, (int) $target->registration1_id);
                else app(DrawService::class)->saveRetirement($target, [[2, 1]], (int) $target->registration2_id);
                $this->fail('Carried matches cannot be scored as walkovers or retirements.');
            } catch (HttpException $exception) { $this->assertSame(409, $exception->getStatusCode()); }
        }
        try { app(\App\Services\Draw\DrawRecoveryService::class)->preview($draw, $source); $this->fail('Expected original result recovery protection.'); }
        catch (HttpException $exception) { $this->assertSame(409, $exception->getStatusCode()); }
        $this->assertSame($count, DB::table('fixture_results')->count());
    }

    public function test_setup_selection_is_event_scoped_and_preserves_existing_draws(): void
    {
        $draw = $this->context();
        $draw->drawFixtures()->delete();
        $draw->groups()->delete();
        $draw->settings()->delete();
        $this->post(route('draw.setup.store', $draw), ['workflow' => InterproRoundRobinFormat::PRESET])->assertRedirect();
        $this->assertTrue(InterproRoundRobinFormat::selected($draw->fresh()));
        $this->assertSame(4, $draw->groups()->count());
        $this->get(route('draw.setup.show', $draw))->assertOk()->assertSee('Interpro trials — round robin');
        $other = Draw::factory()->create();
        $this->postJson(route('draw.setup.store', $other), ['workflow' => InterproRoundRobinFormat::PRESET])->assertForbidden();
        $draw->event->update(['eventType' => 6]);
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => InterproRoundRobinFormat::PRESET])->assertUnprocessable();
    }

    public function test_withdrawal_preserves_a_carried_result_and_resolves_future_unplayed_matches(): void
    {
        $draw = $this->context();
        app(InterproRoundRobinFormat::class)->prepare($draw);
        $this->reachCarries($draw);
        $carry = $this->fixture($draw, 3007);
        $entry = CategoryEventRegistration::where('category_event_id', $draw->category_event_id)->where('registration_id', $carry->winner_registration)->firstOrFail();
        $entry->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        app(\App\Services\InterprovincialTrials\TrialWithdrawalProgressionService::class)->resolve($entry);
        $this->assertSame(0, $carry->fresh()->match_status);
        $this->assertNotNull(InterproRoundRobinFormat::source($carry->fresh()));
        $future = $this->fixture($draw, 3009);
        $this->assertSame(3, $future->match_status);
        $this->assertNotSame((int) $entry->registration_id, (int) $future->winner_registration);
    }

    public function test_two_withdrawn_players_leave_no_blocking_phantom_opponent(): void
    {
        $draw = $this->context();
        app(InterproRoundRobinFormat::class)->prepare($draw);
        $this->progress($draw);
        $semi = $this->fixture($draw, 2001);
        $entries = CategoryEventRegistration::where('category_event_id', $draw->category_event_id)
            ->whereIn('registration_id', [$semi->registration1_id, $semi->registration2_id])->get();
        foreach ($entries as $entry) $entry->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        app(\App\Services\InterprovincialTrials\TrialWithdrawalProgressionService::class)->resolve($entries->first());
        $this->assertSame(5, $semi->fresh()->match_status);
        $this->score($draw, 2002);
        $final = $this->fixture($draw, 2003);
        $this->assertNull($final->registration1_id);
        $this->assertNotNull(app(IndividualMatchOutcomeService::class)->winner($final));
        $this->assertSame(0, $final->fixtureResults()->count());
        foreach (array_keys(InterproRoundRobinFormat::template()) as $number) {
            $fixture = $this->fixture($draw, $number);
            if (InterproRoundRobinFormat::noMatchRequired($fixture) || app(IndividualMatchOutcomeService::class)->winner($fixture)) continue;
            $this->score($draw, $number);
        }
        $workflow = app(\App\Services\Draw\DrawWorkflow::class)->forDraw($draw->fresh());
        $this->assertTrue($workflow['resultsComplete']);
    }

    public function test_legacy_plate_and_schedule_writers_cannot_change_selected_format(): void
    {
        $draw = $this->context();
        app(InterproRoundRobinFormat::class)->prepare($draw);
        $this->reachCarries($draw);
        $fixtures = $draw->drawFixtures()->get()->toJson();
        $this->postJson(route('backend.draw.generate-second-third-bracket', $draw))->assertConflict();
        $this->assertSame($fixtures, $draw->drawFixtures()->get()->toJson());
        $request = \Illuminate\Http\Request::create('/', 'POST', ['items' => []]);
        foreach ([\App\Http\Controllers\Api\DrawApiController::class => 'saveSchedule',
            \App\Http\Controllers\Backend\RoundRobinController::class => 'storeOrderOfPlay'] as $controller => $method) {
            try { app($controller)->$method($request, $draw); $this->fail('Legacy scheduling must be rejected.'); }
            catch (HttpException $exception) { $this->assertSame(409, $exception->getStatusCode()); }
        }
        $this->assertDatabaseCount('order_of_plays', 0);
    }

    public function test_carried_score_orients_the_original_and_rejects_foreign_or_stale_sources(): void
    {
        $draw = $this->context();
        app(InterproRoundRobinFormat::class)->prepare($draw);
        $this->reachCarries($draw);
        $target = $this->fixture($draw, 3007);
        $reverse = clone $target;
        $reverse->registration1_id = $target->registration2_id;
        $reverse->registration2_id = $target->registration1_id;
        $this->assertStringStartsWith('2-6', InterproRoundRobinFormat::score($reverse));
        $source = InterproRoundRobinFormat::source($target);
        $source->fixtureResults()->first()->update(['registration2_score' => 3]);
        $this->assertNull(app(IndividualMatchOutcomeService::class)->winner($target->fresh()));
        $config = $draw->fresh()->settings->playoff_config;
        $other = Fixture::factory()->create(['draw_id' => Draw::factory()->create()->id,
            'registration1_id' => $target->registration1_id, 'registration2_id' => $target->registration2_id]);
        $config[0]['trials_carries'][$target->id]['source'] = $other->id;
        $draw->settings()->update(['playoff_config' => $config]);
        $this->assertNull(InterproRoundRobinFormat::source($target->fresh()));
    }
}
