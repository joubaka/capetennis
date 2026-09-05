<?php

namespace Tests\Feature\Draw;

use App\Models\{CategoryEvent, Draw, Event, OrderOfPlay, Registration, User};
use App\Services\Draw\FlexibleMonradService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CanonicalDrawWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function draw(): Draw
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create(['eventType' => 5]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id]);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $draw->settings()->create(['workflow' => 'custom_monrad']);
        $slots = [];
        foreach (['aa', 'ab', 'ba', 'bb'] as $path) {
            $registration = Registration::factory()->create();
            $registration->categoryEvents()->attach($category->id, [
                'status' => 'registered',
                'payment_status_id' => 1,
            ]);
            $slots[$path] = ['type' => 'player', 'id' => $registration->id];
        }
        app(FlexibleMonradService::class)->save($draw, ['size' => 4, 'slots' => $slots], 0);
        app(FlexibleMonradService::class)->generate($draw, 1);
        $this->actingAs($admin);
        return $draw->fresh();
    }

    public function test_every_entry_point_returns_to_the_canonical_workspace_without_mutating_the_draw(): void
    {
        $draw = $this->draw();
        $url = route('backend.draw.roundrobin.show', $draw);
        foreach (['draws.show' => '', 'flexible-monrad.show' => '', 'draws.manage' => '#settings',
            'draws.settings' => '#settings', 'draws.players' => '#groups', 'event.draw.get.pdf' => '#print'] as $route => $hash) {
            $this->get(route($route, $draw))->assertRedirect($url.$hash);
        }
        $this->get($url)->assertOk()->assertSee('Players &amp; Positions', false)
            ->assertSee('Draw &amp; Results', false)->assertSee('Setup &amp; Rules', false)
            ->assertSee('Print draw &amp; results', false)->assertSee('data-share-draw', false)
            ->assertSee('Back to Event')->assertSee('name="name"', false)
            ->assertSee('Manage full schedule')
            ->assertSee('Schedule only this draw')
            ->assertSee(route('backend.event-venue-schedule.index', ['event' => $draw->event_id, 'manual' => 1]))
            ->assertSee(route('backend.event-venue-schedule.index', ['event' => $draw->event_id, 'draw_ids' => [$draw->id], 'manual' => 1]));
        $this->assertSame(4, $draw->drawFixtures()->count());
        $this->assertSame(0, $draw->groups()->count());
        $this->assertSame(2, $draw->flexibleMonrad->revision);
    }

    public function test_trials_custom_monrad_uses_individual_scheduler_and_its_data_contract(): void
    {
        $draw = $this->draw();
        $this->get(route('backend.individual-schedule.page', $draw))->assertOk()
            ->assertViewIs('backend.schedule.individual-schedule')->assertSee('Draw &amp; Results', false);
        $this->getJson(route('backend.individual-schedule.data', $draw))->assertOk()
            ->assertJsonCount(4, 'fixtures')->assertJsonPath('fixtures.0.stage', 'FM');
    }

    public function test_public_schedule_is_hidden_until_separately_published_and_legacy_graph_writes_stay_blocked(): void
    {
        $draw = $this->draw();
        $venueId = DB::table('venues')->insertGetId(['name' => 'Private timetable venue']);
        OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $draw->drawFixtures()->first()->id,
            'venue_id' => $venueId, 'court' => 'Confidential court', 'time' => '2026-09-20 09:00:00']);
        app(FlexibleMonradService::class)->publish($draw, 2, true);
        $public = route('public.flexible-monrad.show', $draw);
        $this->get($public)->assertOk()
            ->assertSee('data-fm-public-tab="schedule"', false)
            ->assertSee('data-fm-public-tab="draw"', false)
            ->assertSee('id="fm-schedule-panel"', false)
            ->assertSee('id="fm-schedule-panel" class="fm-public-panel fm-public-timetable" role="tabpanel" aria-labelledby="fm-schedule-tab" hidden', false)
            ->assertSee('id="fm-draw-panel" class="fm-public-panel" role="tabpanel" aria-labelledby="fm-draw-tab"', false)
            ->assertSee('Match times have not been published yet.')
            ->assertDontSee('Private timetable venue')->assertDontSee('Confidential court');
        $this->postJson(route('draw.toggle.publish.schedule', $draw))->assertOk();
        $this->get($public)->assertOk()->assertSee('Private timetable venue')->assertSee('Confidential court')
            ->assertSee('Find your name, then confirm the date, time, venue and court.')
            ->assertSee('2026-09-20 09:00:00');
        $this->assertStringContainsString('td[data-label="Time"]', file_get_contents(public_path('css/flexible-monrad.css')));
        $publicScript = file_get_contents(public_path('js/flexible-monrad.js'));
        $this->assertStringContainsString("const followedBy = 'Followed by';", $publicScript);
        foreach (['Date', 'Time', 'Venue', 'Court'] as $column) {
            $this->assertStringContainsString("['{$column}', scheduleHidden ? followedBy", $publicScript);
        }
        $this->postJson(route('draw.toggle.publish', $draw))->assertConflict();
        $this->postJson(route('draws.players.update', $draw), ['players' => []])->assertForbidden();
        $this->assertSame(4, $draw->drawFixtures()->count());
        $this->postJson(route('draw.toggle.publish.schedule', $draw))->assertOk();
        $this->get($public)->assertOk()->assertDontSee('Private timetable venue');
    }

    public function test_authorised_flexible_draft_opens_an_honest_public_preview(): void
    {
        $draw = $this->draw();

        $this->get(route('public.roundrobin.show', $draw))
            ->assertRedirect(route('public.flexible-monrad.show', $draw));
        $this->get(route('public.flexible-monrad.show', $draw))
            ->assertOk()
            ->assertSee('Draft preview')
            ->assertSee('Draw not published')
            ->assertSee('Manage this draw')
            ->assertSee('Back to tournament')
            ->assertDontSee('CAPE TENNIS / DRAW BUILDER');
    }

    public function test_other_event_admin_cannot_open_workspace_scheduler_or_publish_schedule(): void
    {
        $draw = $this->draw();
        $this->actingAs(User::factory()->create()->assignRole('admin'));
        foreach (['backend.draw.roundrobin.show', 'flexible-monrad.show', 'backend.individual-schedule.page', 'draws.players', 'draws.settings'] as $route) {
            $this->get(route($route, $draw))->assertForbidden();
        }
        $this->postJson(route('draw.toggle.publish.schedule', $draw))->assertForbidden();
        $this->assertFalse((bool) $draw->fresh()->oop_published);
    }

    public function test_rules_use_the_existing_notes_endpoint_without_changing_the_graph(): void
    {
        $draw = $this->draw();
        $graph = $draw->flexibleMonrad->graph;
        $this->postJson(route('backend.draw.update-notes', $draw), ['notes' => ['general' => 'Arrive ten minutes early.']])->assertOk();
        $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk()->assertSee('Arrive ten minutes early.');
        $this->assertEquals($graph, $draw->fresh()->flexibleMonrad->graph);
        $this->actingAs(User::factory()->create()->assignRole('admin'));
        $this->postJson(route('backend.draw.update-notes', $draw), ['notes' => ['general' => 'Foreign edit']])->assertForbidden();
    }
}
