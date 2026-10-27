<?php

namespace Tests\Feature\Draw;

use App\Models\{CategoryEvent, Draw, Event, Fixture, Registration, User};
use App\Services\Draw\FlexibleMonradService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DrawSetupTest extends TestCase
{
    use RefreshDatabase;

    private function draw(): Draw
    {
        $event = Event::factory()->create();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        return $draw;
    }

    private function slots(Draw $draw): array
    {
        $slots = [];
        foreach (['aa', 'ab', 'ba', 'bb'] as $path) {
            $player = Registration::factory()->create();
            $player->categoryEvents()->attach($draw->category_event_id, [
                'status' => 'registered',
                'payment_status_id' => 1,
            ]);
            $slots[$path] = ['type' => 'player', 'id' => $player->id];
        }
        return $slots;
    }

    public function test_first_decision_is_read_only_and_round_robin_continues_to_groups(): void
    {
        $draw = $this->draw();
        $this->get(route('backend.draw.roundrobin.show', $draw))->assertRedirect(route('draw.setup.show', $draw));
        $response = $this->get(route('draw.setup.show', $draw))->assertOk()
            ->assertSee('How should this draw start?')->assertSee('Round robin only')->assertSee('Round robin → playoffs')
            ->assertSee('Playoffs only')->assertSee('Monrad only')->assertSee('Custom Monrad')->assertSee('Feed-in Monrad')
            ->assertSee('Help me choose a format')->assertSee('Recommended')->assertSee('Advanced');
        if (getenv('DRAW_SETUP_SNAPSHOT')) {
            \Illuminate\Support\Facades\Storage::disk('local')->put('testing/draw-setup.html', $response->getContent());
        }
        $this->assertDatabaseCount('flexible_monrad_draws', 0);
        $this->assertSame(0, $draw->groups()->count());
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'round_robin_playoffs'])
            ->assertRedirect(route('backend.draw.roundrobin.show', $draw));
        $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk()->assertSee('Players &amp; Groups', false);
        $publicDraw = $this->get(route('public.roundrobin.show', $draw))->assertOk();
        $publicBracket = $this->get(route('public.roundrobin.main-bracket', $draw))->assertOk();
        foreach ([$publicDraw, $publicBracket] as $response) {
            $cacheControl = (string) $response->headers->get('Cache-Control');
            $this->assertStringContainsString('no-store', $cacheControl);
            $this->assertStringContainsString('no-cache', $cacheControl);
            $this->assertStringContainsString('must-revalidate', $cacheControl);
            $this->assertStringContainsString('max-age=0', $cacheControl);
        }
        $this->assertSame(0, $draw->drawFixtures()->count());
        $this->putJson(route('flexible-monrad.save', $draw), ['revision' => 0, 'draft' => ['size' => 4, 'slots' => []]])->assertConflict();
    }

    public function test_round_robin_only_uses_group_workspace_without_playoff_controls_or_mutations(): void
    {
        $draw = $this->draw();

        $this->post(route('draw.setup.store', $draw), ['workflow' => 'round_robin'])
            ->assertRedirect(route('backend.draw.roundrobin.show', $draw));

        $this->assertSame('round_robin', $draw->fresh()->settings->workflow);
        $this->assertTrue($draw->fresh()->isRoundRobinOnly());
        $this->assertDatabaseCount('flexible_monrad_draws', 0);

        $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk()
            ->assertSee('Step 3 of 3 · Players · Round robin only')
            ->assertDontSee('id="main-bracket-tab"', false)
            ->assertDontSee('id="btn-add-playoff"', false)
            ->assertDontSee('id="btn-print-bracket"', false)
            ->assertDontSee('id="pack-playoff-fixtures"', false);
        $this->get(route('public.roundrobin.show', $draw))->assertOk()
            ->assertDontSee('id="main-bracket-tab"', false);
        $this->get(route('public.roundrobin.main-bracket', $draw))->assertNotFound();

        $this->postJson(route('backend.draw.generate-main-bracket', $draw))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Playoffs are not available for a round-robin-only draw.');
        $this->postJson(route('backend.draw.update-playoff-config', $draw), ['playoff_config' => []])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Playoff settings are not available for a round-robin-only draw.');
        $this->assertSame(0, $draw->drawFixtures()->where('stage', '!=', 'RR')->count());
    }

    public function test_direct_formats_persist_resume_and_generate_the_correct_matches(): void
    {
        foreach (['playoffs' => 3, 'monrad' => 4, 'custom_monrad' => 4, 'feed_in_monrad' => 4] as $workflow => $count) {
            $draw = $this->draw();
            $this->post(route('draw.setup.store', $draw), ['workflow' => $workflow])->assertRedirect(route('backend.draw.roundrobin.show', $draw));
            $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk()->assertSee('Players &amp; Positions', false)->assertSee('Setup &amp; Rules', false);
            $this->get(route('flexible-monrad.show', $draw))->assertRedirect(route('backend.draw.roundrobin.show', $draw));
            $this->assertSame($workflow, $draw->fresh()->settings->workflow);
            $this->assertIsObject(app(FlexibleMonradService::class)->state($draw->fresh())['draft']['slots']);
            $this->post(route('draw.setup.store', $draw), ['workflow' => $workflow])->assertRedirect();
            $this->assertSame(1, $draw->fresh()->flexibleMonrad->revision);
            $draft = ['size' => 4, 'slots' => $this->slots($draw), 'mode' => 'custom_monrad'];
            $this->putJson(route('flexible-monrad.save', $draw), ['revision' => 1, 'draft' => $draft])->assertOk();
            $this->postJson(route('flexible-monrad.generate', $draw), ['revision' => 2])->assertOk();
            $this->postJson(route('flexible-monrad.generate', $draw), ['revision' => 3])->assertOk();
            $this->assertSame($count, $draw->drawFixtures()->count());
            $this->assertSame(0, $draw->groups()->count());
            $graph = $draw->fresh()->flexibleMonrad->graph;
            $this->assertCount($workflow === 'playoffs' ? 2 : 4, $graph['positions']);
            if ($workflow === 'playoffs') {
                foreach ($graph['nodes'] as $node) $this->assertSame('Main draw', $node['section']);
            }
        }
    }

    public function test_feed_in_sixteen_uses_shared_workspace_schedule_print_and_protected_result_lifecycle(): void
    {
        $draw = $this->draw();
        $draw->update(['drawName' => 'Feed-in Monrad — 16-player test']);
        $draw->event->update(['name' => 'Isolated Interpro feed-in test']);
        $slots = [];
        foreach (range(0, 15) as $index) {
            $registration = Registration::factory()->create();
            $registration->players()->attach(\App\Models\Player::factory()->create([
                'name' => 'Test', 'surname' => sprintf('Player %02d', $index + 1),
            ]));
            $registration->categoryEvents()->attach($draw->category_event_id, ['status' => 'registered', 'payment_status_id' => 1]);
            $path = strtr(str_pad(decbin($index), 4, '0', STR_PAD_LEFT), ['0' => 'a', '1' => 'b']);
            $slots[$path] = ['type' => 'player', 'id' => $registration->id];
        }
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'feed_in_monrad'])->assertRedirect();
        $foreign = Registration::factory()->create();
        $bad = $slots;
        $bad['aaaa']['id'] = $foreign->id;
        $this->putJson(route('flexible-monrad.save', $draw), ['revision' => 1, 'draft' => ['size' => 16, 'slots' => $bad]])->assertUnprocessable();
        // Client mode is ignored; the saved workflow controls the feed-in graph.
        $this->putJson(route('flexible-monrad.save', $draw), ['revision' => 1,
            'draft' => ['size' => 16, 'slots' => $slots, 'mode' => 'playoffs']])->assertOk();
        $this->postJson(route('flexible-monrad.generate', $draw), ['revision' => 2])->assertOk();
        $this->postJson(route('flexible-monrad.generate', $draw), ['revision' => 3])->assertOk();
        $draw->refresh();
        $this->assertSame('feed_in_monrad', $draw->flexibleMonrad->draft['mode']);
        $this->assertSame(36, $draw->drawFixtures()->count());
        $this->assertSame(16, $draw->registrations()->reorder()->count());
        $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk()->assertSee('Feed-in Monrad')
            ->assertSee('Players &amp; Positions', false)->assertSee('Setup &amp; Rules', false);
        $programme = app(\App\Services\Scheduling\ScheduleProgramme::class);
        $this->assertTrue($programme->supports($draw));
        $this->assertTrue(app(\App\Domain\Draws\Services\DrawReadinessService::class)->for($draw)['checks']['format']['ok']);
        $this->assertStringContainsString('Earlier-round losers', json_encode(app(\App\Domain\Draws\Services\DrawRulesService::class)->sections($draw)));
        $service = app(FlexibleMonradService::class);
        $state = $service->state($draw);
        $print = app(\App\Services\Draw\FlexibleMonradPdfLayout::class)->build($state);
        $this->assertNotEmpty($print);
        $preview = $this->get(route('public.flexible-monrad.show', $draw))->assertOk()->assertSee('Feed-in Monrad')
            ->assertSee('Test Player 01')->assertSee('Test Player 16');
        if (getenv('FEED_IN_WORKSPACE_SNAPSHOT')) {
            @mkdir(storage_path('app/testing'), 0777, true);
            file_put_contents(storage_path('app/testing/feed-in-monrad.html'), $preview->getContent());
        }

        $venue = DB::table('venues')->insertGetId(['name' => 'Feed-in test venue', 'event_id' => $draw->event_id]);
        $draw->venues()->attach($venue, ['num_courts' => 4]);
        $this->postJson(route('backend.individual-schedule.auto', $draw), ['start' => '2026-10-30 08:00:00', 'duration' => 60])->assertOk();
        $scheduled = \App\Models\OrderOfPlay::where('draw_id', $draw->id)->get()->keyBy('fixture_id');
        $this->assertCount(36, $scheduled);
        $state = $service->state($draw->fresh());
        $matches = (array) $state['matches'];
        foreach ($matches as $match) {
            foreach ($match['sources'] as $source) {
                if (! isset($source['match'])) continue;
                $parent = $scheduled[$matches[$source['match']]['id']];
                $this->assertGreaterThanOrEqual(\Carbon\Carbon::parse($parent->time)->addMinutes(60)->timestamp,
                    \Carbon\Carbon::parse($scheduled[$match['id']]->time)->timestamp);
            }
        }

        // Resolve every feed-in and classification path through the canonical scorer.
        for ($played = 0; $played < 36; $played++) {
            $state = $service->state($draw->fresh());
            $ready = collect((array) $state['matches'])->first(fn ($match) => ! $match['sets']
                && ! $match['automatic'] && count(array_filter($match['players'])) === 2);
            $this->assertNotNull($ready);
            $service->score($draw, $ready['id'], [[6, 2]], $state['revision'], false);
        }
        $state = $service->state($draw->fresh());
        $this->assertCount(16, array_unique(array_column($state['positions'], 'player')));
        $this->assertNotContains(null, array_column($state['positions'], 'player'));
        $before = $draw->drawFixtures()->with('fixtureResults')->get()->toArray();
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'monrad', 'reset_existing' => true])->assertConflict();
        $this->assertEquals($before, $draw->drawFixtures()->with('fixtureResults')->get()->toArray());
        $this->postJson(route('flexible-monrad.publish', $draw), ['revision' => $state['revision'], 'published' => true])->assertOk();
        $this->postJson(route('flexible-monrad.reopen', $draw), ['revision' => $state['revision'] + 1])->assertForbidden();
        $this->actingAs(User::factory()->create()->assignRole('admin'));
        $this->get(route('backend.draw.roundrobin.show', $draw))->assertForbidden();
    }

    public function test_generated_sixteen_player_monrad_can_switch_to_feed_in_with_selected_challenges(): void
    {
        $draw = $this->draw();
        $slots = [];
        foreach (range(0, 15) as $index) {
            $registration = Registration::factory()->create();
            $registration->categoryEvents()->attach($draw->category_event_id, ['status' => 'registered', 'payment_status_id' => 1]);
            $path = strtr(str_pad(decbin($index), 4, '0', STR_PAD_LEFT), ['0' => 'a', '1' => 'b']);
            $slots[$path] = ['type' => 'player', 'id' => $registration->id];
        }
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'monrad'])->assertRedirect();
        $this->putJson(route('flexible-monrad.save', $draw), ['revision' => 1,
            'draft' => ['size' => 16, 'slots' => $slots]])->assertOk();
        $this->postJson(route('flexible-monrad.generate', $draw), ['revision' => 2])->assertOk();
        $this->assertSame(32, $draw->drawFixtures()->count());

        $this->post(route('draw.setup.store', $draw), ['workflow' => 'feed_in_monrad', 'reset_existing' => 1,
            'challenge_playoffs' => 1, 'challenge_pairs' => [2, 4, 6, 8]])
            ->assertRedirect(route('backend.draw.roundrobin.show', $draw));
        $draw->refresh();
        $this->assertSame(0, $draw->drawFixtures()->count());
        $this->assertSame(16, $draw->registrations()->reorder()->count());
        $this->assertSame($slots, $draw->flexibleMonrad->draft['slots']);
        $this->assertSame([2, 4, 6, 8], $draw->flexibleMonrad->draft['challenge_pairs']);
        $this->assertSame('feed_in_monrad', $draw->settings->workflow);
        $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk()
            ->assertSee('1 · Format')->assertSee('2 · Players')->assertSee('4 · Schedule');
        $this->get(route('draw.setup.show', $draw))->assertOk()
            ->assertSee('aria-current="page"', false)->assertSee('draw-progress-modal');
        $this->postJson(route('flexible-monrad.generate', $draw), ['revision' => 1])->assertOk();
        $this->assertSame(40, $draw->drawFixtures()->count());
    }

    public function test_only_custom_monrad_accepts_later_round_entry(): void
    {
        foreach (['playoffs', 'monrad', 'custom_monrad', 'feed_in_monrad'] as $workflow) {
            $draw = $this->draw();
            $slots = $this->slots($draw);
            $this->post(route('draw.setup.store', $draw), ['workflow' => $workflow])->assertRedirect();
            $draft = ['size' => 4, 'slots' => ['a' => $slots['aa'], 'ba' => $slots['ba'], 'bb' => ['type' => 'bye']]];
            $response = $this->putJson(route('flexible-monrad.save', $draw), ['revision' => 1, 'draft' => $draft]);
            if ($workflow === 'custom_monrad') $response->assertOk();
            else $response->assertUnprocessable();
        }
    }

    public function test_compatible_monrad_switches_keep_saved_starts_and_byes_but_reset_confirmed_unplayed_fixtures(): void
    {
        $draw = $this->draw();
        $slots = $this->slots($draw);
        $slots['bb'] = ['type' => 'bye'];
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'monrad'])->assertRedirect();
        $this->putJson(route('flexible-monrad.save', $draw), ['revision' => 1, 'draft' => ['size' => 4, 'slots' => $slots]])->assertOk();
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'feed_in_monrad'])->assertConflict();
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'feed_in_monrad', 'reset_existing' => true])->assertRedirect();
        $this->assertEquals($slots, $draw->fresh()->flexibleMonrad->draft['slots']);
        $this->assertSame(4, $draw->fresh()->flexibleMonrad->draft['size']);
        $this->postJson(route('flexible-monrad.generate', $draw), ['revision' => 1])->assertOk();
        $fixture = $draw->drawFixtures()->firstOrFail();
        $venue = DB::table('venues')->insertGetId(['name' => 'Switch test court', 'event_id' => $draw->event_id]);
        \App\Models\OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $fixture->id, 'venue_id' => $venue,
            'court' => '1', 'time' => '2026-10-30 08:00:00']);
        $draw->update(['published' => true]);
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'monrad', 'reset_existing' => true])->assertForbidden();
        $draw->update(['published' => false]);
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'monrad'])->assertConflict();
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'monrad', 'reset_existing' => true])->assertRedirect();
        $this->assertEquals($slots, $draw->fresh()->flexibleMonrad->draft['slots']);
        $this->assertSame(0, $draw->drawFixtures()->count());
        $this->assertSame(0, \App\Models\OrderOfPlay::where('draw_id', $draw->id)->count());
        $this->postJson(route('flexible-monrad.generate', $draw), ['revision' => 1])->assertOk();
        $state = app(FlexibleMonradService::class)->state($draw->fresh());
        $ready = collect((array) $state['matches'])->first(fn ($match) => ! $match['automatic'] && count(array_filter($match['players'])) === 2);
        app(FlexibleMonradService::class)->score($draw, $ready['id'], [[6, 2]], $state['revision'], false);
        $before = $draw->fresh()->flexibleMonrad->draft;
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'feed_in_monrad', 'reset_existing' => true])->assertConflict();
        $this->assertEquals($before, $draw->fresh()->flexibleMonrad->draft);
    }

    public function test_challenge_option_is_server_owned_default_off_and_same_workflow_toggle_preserves_starts(): void
    {
        $draw = $this->draw();
        $slots = $this->slots($draw);
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'feed_in_monrad'])->assertRedirect();
        $this->putJson(route('flexible-monrad.save', $draw), ['revision' => 1,
            'draft' => ['size' => 4, 'slots' => $slots, 'challenge_playoffs' => true]])->assertOk();
        $this->assertFalse($draw->fresh()->flexibleMonrad->draft['challenge_playoffs'] ?? false);
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'feed_in_monrad', 'challenge_playoffs' => true])->assertUnprocessable();
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'feed_in_monrad', 'challenge_playoffs' => true, 'challenge_pairs' => [2]])->assertConflict();
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'feed_in_monrad', 'challenge_playoffs' => true, 'challenge_pairs' => [2], 'reset_existing' => true])->assertRedirect();
        $this->assertTrue($draw->fresh()->settings->playoff_config['challenge_playoffs']);
        $this->assertTrue($draw->fresh()->flexibleMonrad->draft['challenge_playoffs']);
        $this->assertEquals($slots, $draw->fresh()->flexibleMonrad->draft['slots']);
        $this->postJson(route('flexible-monrad.generate', $draw), ['revision' => 1])->assertOk();
        $this->postJson(route('flexible-monrad.generate', $draw), ['revision' => 2])->assertOk();
        $this->assertSame(5, $draw->drawFixtures()->count());
        $new = $this->draw();
        $this->slots($new);
        $category = $new->category_event_id;
        $new->update(['category_event_id' => null]);
        $this->post(route('draw.setup.store', $new), ['workflow' => 'feed_in_monrad', 'challenge_playoffs' => true, 'challenge_pairs' => [2]])
            ->assertOk()->assertSee('name="challenge_playoffs" value="1"', false);
        $this->postJson(route('draw.setup.store', $new), ['workflow' => 'feed_in_monrad', 'challenge_playoffs' => true, 'challenge_pairs' => [30], 'category_event_id' => $category])->assertUnprocessable();
        $this->post(route('draw.setup.store', $new), ['workflow' => 'feed_in_monrad', 'challenge_playoffs' => true, 'challenge_pairs' => [2], 'category_event_id' => $category])->assertRedirect();
        $this->assertTrue($new->fresh()->settings->playoff_config['challenge_playoffs']);
    }

    public function test_individual_challenge_pairs_are_explicit_validated_canonical_and_reset_guarded(): void
    {
        $draw = $this->draw();
        foreach ([[], [1], [3], [64], [2, 2]] as $pairs) {
            $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'feed_in_monrad', 'challenge_playoffs' => true,
                'challenge_pairs' => $pairs])->assertUnprocessable();
        }
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'feed_in_monrad', 'challenge_playoffs' => true, 'challenge_pairs' => [4, 2]])->assertRedirect();
        $this->assertSame([2, 4], $draw->fresh()->settings->playoff_config['challenge_pairs']);
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'feed_in_monrad', 'challenge_playoffs' => true, 'challenge_pairs' => [2, 4]])->assertRedirect();
        $this->assertSame(1, $draw->fresh()->flexibleMonrad->revision);
        $slots = $this->slots($draw);
        $this->putJson(route('flexible-monrad.save', $draw), ['revision' => 1,
            'draft' => ['size' => 4, 'slots' => $slots, 'challenge_pairs' => [6]]])->assertOk();
        $this->assertSame([2, 4], $draw->fresh()->flexibleMonrad->draft['challenge_pairs']);
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'feed_in_monrad', 'challenge_playoffs' => true,
            'challenge_pairs' => [2]])->assertConflict();
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'feed_in_monrad', 'challenge_playoffs' => true,
            'challenge_pairs' => [2], 'reset_existing' => true])->assertRedirect();
        $this->assertEquals($slots, $draw->fresh()->flexibleMonrad->draft['slots']);
        $this->get(route('draw.setup.show', $draw))->assertOk()->assertSee('Choose the base-position pairs')
            ->assertSee('name="challenge_pairs[]" value="2"', false);
        $this->postJson(route('flexible-monrad.generate', $draw), ['revision' => 1])->assertOk();
        $this->assertSame([2], $draw->fresh()->flexibleMonrad->graph['challenge_pairs']);
    }

    public function test_direct_format_suggests_the_smallest_bracket_that_fits_the_category(): void
    {
        $draw = $this->draw();
        foreach (range(1, 5) as $unused) {
            $registration = Registration::factory()->create();
            $registration->categoryEvents()->attach($draw->category_event_id, [
                'status' => 'registered',
                'payment_status_id' => 1,
            ]);
        }

        $this->post(route('draw.setup.store', $draw), ['workflow' => 'monrad'])->assertRedirect();

        $this->assertSame(8, $draw->fresh()->flexibleMonrad->draft['size']);
    }

    public function test_selection_is_authorized_validated_and_locked_draws_are_protected(): void
    {
        $draw = $this->draw();
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'invalid'])->assertUnprocessable();
        foreach (['locked', 'published'] as $flag) {
            $draw->update([$flag => true]);
            $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'monrad'])->assertForbidden();
            $draw->update([$flag => false]);
        }
        $this->actingAs(User::factory()->create()->assignRole('admin'));
        $this->get(route('draw.setup.show', $draw))->assertForbidden();
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'monrad'])->assertForbidden();
        $this->assertDatabaseCount('flexible_monrad_draws', 0);
    }

    public function test_existing_assignments_and_fixtures_cannot_be_replaced(): void
    {
        $draw = $this->draw();
        $group = $draw->groups()->create(['name' => 'A']);
        $slots = $this->slots($draw);
        $group->registrations()->attach($slots['aa']['id']);
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'monrad'])->assertConflict();
        $this->assertSame(1, $group->registrations()->count());
        $draw = $this->draw();
        Fixture::factory()->create(['draw_id' => $draw->id]);
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'playoffs'])->assertConflict();
        $this->assertSame(1, $draw->drawFixtures()->count());
        $draw = $this->draw();
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'custom_monrad'])->assertRedirect();
        app(FlexibleMonradService::class)->save($draw, ['size' => 4, 'slots' => $this->slots($draw)], 1);
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'round_robin_playoffs'])->assertConflict();
        $this->assertCount(4, $draw->fresh()->flexibleMonrad->draft['slots']);

        $draw = $this->draw();
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'round_robin'])->assertRedirect();
        Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN']);
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'round_robin_playoffs'])->assertConflict();
        $this->assertSame('round_robin', $draw->fresh()->settings->workflow);
    }

    public function test_confirmed_format_reset_removes_unplayed_structure_but_preserves_the_roster(): void
    {
        $draw = $this->draw();
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'custom_monrad'])->assertRedirect();
        $slots = $this->slots($draw);
        app(FlexibleMonradService::class)->save($draw, ['size' => 4, 'slots' => $slots], 1);
        app(FlexibleMonradService::class)->generate($draw, 2);
        $fixture = $draw->drawFixtures()->first();
        DB::table('order_of_plays')->insert([
            'draw_id' => $draw->id, 'fixture_id' => $fixture->id, 'venue_id' => DB::table('venues')->insertGetId(['name' => 'Reset venue']),
            'court' => '1', 'time' => '2026-09-06 08:00:00', 'duration_minutes' => 45,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->get(route('draw.setup.show', $draw))->assertOk()
            ->assertSee('Changing format resets this draw.')
            ->assertSee('Change format and reset draw')
            ->assertSee('I understand this removes the current fixtures and scheduled times.');
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'round_robin'])->assertConflict();
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'round_robin', 'reset_existing' => 1])->assertRedirect();

        $this->assertSame('round_robin', $draw->fresh()->settings->workflow);
        $this->assertSame(4, $draw->registrations()->count());
        $this->assertSame(0, $draw->drawFixtures()->count());
        $this->assertSame(0, DB::table('order_of_plays')->where('draw_id', $draw->id)->count());
        $this->assertNull($draw->fresh()->flexibleMonrad);
        $this->assertDatabaseHas('draw_audit_logs', ['draw_id' => $draw->id, 'action' => 'format_reset']);
    }

    public function test_format_reset_refuses_to_delete_recorded_results(): void
    {
        $draw = $this->draw();
        Fixture::factory()->create(['draw_id' => $draw->id]);
        $fixture = $draw->drawFixtures()->first();
        DB::table('fixture_results')->insert([
            'fixture_id' => $fixture->id, 'set_nr' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->postJson(route('draw.setup.store', $draw), [
            'workflow' => 'round_robin', 'reset_existing' => 1,
        ])->assertConflict();
        $this->assertDatabaseHas('fixtures', ['id' => $fixture->id]);
        $this->assertDatabaseHas('fixture_results', ['fixture_id' => $fixture->id]);
    }

    public function test_round_robin_with_existing_groups_and_fixtures_can_add_playoffs_without_replacing_them(): void
    {
        $draw = $this->draw();
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'round_robin'])->assertRedirect();

        $group = $draw->groups()->create(['name' => 'A']);
        $slots = $this->slots($draw);
        $group->registrations()->attach($slots['aa']['id']);
        $fixture = Fixture::factory()->create([
            'draw_id' => $draw->id,
            'draw_group_id' => $group->id,
            'stage' => 'RR',
        ]);

        $this->post(route('draw.setup.store', $draw), ['workflow' => 'round_robin_playoffs'])
            ->assertRedirect(route('backend.draw.roundrobin.show', $draw));

        $draw->refresh();
        $this->assertSame('round_robin_playoffs', $draw->settings->workflow);
        $this->assertSame([$fixture->id], $draw->drawFixtures()->pluck('id')->all());
        $this->assertSame([$slots['aa']['id']], $group->registrations()->pluck('registrations.id')->all());
        $this->get(route('backend.draw.roundrobin.show', $draw))
            ->assertOk()
            ->assertSee('id="btn-add-playoff"', false);
    }

    public function test_legacy_round_robin_without_a_workflow_marker_can_add_playoffs(): void
    {
        $draw = $this->draw();
        $group = $draw->groups()->create(['name' => 'A']);
        $fixture = Fixture::factory()->create([
            'draw_id' => $draw->id,
            'draw_group_id' => $group->id,
            'stage' => 'RR',
        ]);

        $this->assertNull($draw->settings?->workflow);
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'round_robin_playoffs'])
            ->assertRedirect(route('backend.draw.roundrobin.show', $draw));

        $draw->refresh();
        $this->assertSame('round_robin_playoffs', $draw->settings->workflow);
        $this->assertSame([$fixture->id], $draw->drawFixtures()->pluck('id')->all());
    }

    public function test_empty_selection_can_change_without_creating_groups_or_fixtures(): void
    {
        $draw = $this->draw();
        foreach (['monrad', 'playoffs', 'round_robin', 'round_robin_playoffs'] as $workflow) {
            $this->post(route('draw.setup.store', $draw), ['workflow' => $workflow])->assertRedirect();
            $this->assertSame($workflow, $draw->fresh()->settings->workflow);
        }
        $this->assertFalse($draw->fresh()->usesFlexibleMonrad());
        $this->assertSame(0, $draw->groups()->count());
        $this->assertSame(0, $draw->drawFixtures()->count());
    }

    public function test_event_wide_draw_chooses_format_before_category_and_rejects_foreign_category(): void
    {
        $draw = $this->draw();
        $categoryId = $draw->category_event_id;
        $draw->update(['category_event_id' => null]);
        $this->get(route('backend.draw.roundrobin.show', $draw))->assertRedirect(route('draw.setup.show', $draw));
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'playoffs'])->assertOk()->assertSee('Which category will play?');
        $this->assertNull($draw->fresh()->settings?->workflow);
        $foreign = CategoryEvent::factory()->create();
        $this->postJson(route('draw.setup.store', $draw), ['workflow' => 'playoffs', 'category_event_id' => $foreign->id])->assertUnprocessable();
        $this->post(route('draw.setup.store', $draw), ['workflow' => 'playoffs', 'category_event_id' => $categoryId])->assertRedirect(route('backend.draw.roundrobin.show', $draw));
        $this->assertEquals($categoryId, $draw->fresh()->category_event_id);
    }

    public function test_playoffs_with_byes_have_only_a_champion_and_runner_up(): void
    {
        $graph = app(\App\Services\Draw\FlexibleMonradCompiler::class)->compile([
            'mode' => 'playoffs', 'size' => 4, 'slots' => [
                'aa' => ['type' => 'player', 'id' => 1], 'ab' => ['type' => 'player', 'id' => 2],
                'ba' => ['type' => 'bye'], 'bb' => ['type' => 'bye'],
            ],
        ]);
        $this->assertCount(1, $graph['nodes']);
        $this->assertSame(['type' => 'winner', 'match' => 'main_a'], $graph['positions'][1]);
        $this->assertSame(['type' => 'loser', 'match' => 'main_a'], $graph['positions'][2]);
    }
}
