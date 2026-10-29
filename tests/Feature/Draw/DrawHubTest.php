<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, Fixture, OrderOfPlay, User, Venue};
use App\Services\Scheduling\{SchedulePublicationService, SchedulePublicationStatusService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DrawHubTest extends TestCase
{
    use RefreshDatabase;

    private function context(): array
    {
        $event = Event::factory()->create(['eventType' => 6, 'published' => true]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        $draw->settings()->create(['workflow' => 'round_robin']);
        $venue = Venue::forceCreate(['name' => 'Hub courts']);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id]);
        $time = OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $fixture->id, 'venue_id' => $venue->id, 'court' => '1', 'time' => '2026-10-30 09:00:00']);
        return [$event, $draw, $time];
    }

    public function test_hub_distinguishes_partial_changed_and_preview_only_times(): void
    {
        [$event, $draw, $time] = $this->context();
        $publication = app(SchedulePublicationService::class);
        $publication->publish($event, ['draw_id' => $draw->id]);
        $page = $this->get(route('headOffice.show', $event))->assertOk()->assertSee('Schedule published');
        if (getenv('DRAW_HUB_SNAPSHOT_PATH')) file_put_contents(getenv('DRAW_HUB_SNAPSHOT_PATH'), $page->getContent());
        $other = Fixture::factory()->create(['draw_id' => $draw->id]);
        OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $other->id, 'venue_id' => $time->venue_id, 'court' => '2', 'time' => '2026-10-30 10:00:00']);
        $this->get(route('headOffice.show', $event))->assertOk()->assertSee('Partly published')->assertSee('Publish updates');
        $time->update(['time' => '2026-10-30 11:00:00']);
        $this->get(route('headOffice.show', $event))->assertOk()->assertSee('Updates not published')->assertSee('Publish updates');
        $publication->publish($event, ['draw_id' => $draw->id]);
        $event->update(['published' => false]);
        $this->get(route('headOffice.show', $event))->assertOk()->assertSee('Times prepared · preview only')->assertDontSee('>Schedule published<', false);
    }

    public function test_explicit_publish_updates_retains_draw_visibility_and_rejects_stale_revision(): void
    {
        [$event, $draw, $time] = $this->context();
        $service = app(SchedulePublicationService::class);
        $service->publish($event, ['draw_id' => $draw->id]);
        $stale = $service->revision($event);
        $time->update(['court' => '2']);
        $this->postJson(route('draw.toggle.publish.schedule', $draw), ['action' => 'publish', 'revision' => $stale])->assertStatus(422);
        $this->assertDatabaseHas('published_schedule_assignments', ['fixture_id' => $time->fixture_id, 'court' => '1']);
        $revision = $service->revision($event);
        $this->postJson(route('draw.toggle.publish.schedule', $draw), ['action' => 'publish', 'revision' => $revision])->assertOk()->assertJsonPath('oop_published', true);
        $this->assertDatabaseHas('published_schedule_assignments', ['fixture_id' => $time->fixture_id, 'court' => '2']);
        $this->assertTrue((bool) $draw->fresh()->published);
        $this->assertDatabaseCount('published_schedule_assignments', 1);
        $this->postJson(route('draw.toggle.publish.schedule', $draw), ['action' => 'publish', 'revision' => $service->revision($event)])->assertOk();
        $this->assertDatabaseCount('published_schedule_assignments', 1);
    }

    public function test_bulk_can_publish_updates_to_existing_snapshots_without_hiding_them_first(): void
    {
        [$event, $draw, $time] = $this->context();
        $service = app(SchedulePublicationService::class);
        $service->publish($event, ['draw_id' => $draw->id]);
        $time->update(['court' => '3']);
        $this->postJson(route('backend.event-draws.bulk-publication', $event), ['operation' => 'schedules', 'action' => 'publish', 'draw_ids' => [$draw->id], 'revision' => $service->revision($event)])
            ->assertOk()->assertJsonPath('changed.0', $draw->id);
        $this->assertDatabaseHas('published_schedule_assignments', ['fixture_id' => $time->fixture_id, 'court' => '3']);
        $this->assertDatabaseCount('published_schedule_assignments', 1);
        $this->actingAs(User::factory()->create())->postJson(route('draw.toggle.publish.schedule', $draw), ['action' => 'unpublish'])->assertForbidden();
        $this->assertDatabaseCount('published_schedule_assignments', 1);
    }

    public function test_removed_saved_times_keep_snapshot_visible_until_explicit_unpublish(): void
    {
        [$event, $draw, $time] = $this->context();
        $service = app(SchedulePublicationService::class);
        $service->publish($event, ['draw_id' => $draw->id]);
        $time->delete();
        $state = app(SchedulePublicationStatusService::class)->draws($event)->get($draw->id);
        $this->assertSame('Updates not published', $state['status']);
        $this->assertSame(0, $state['saved']);
        $this->get(route('headOffice.show', $event))->assertOk()->assertSee('Unpublish times');
        $this->assertDatabaseCount('published_schedule_assignments', 1);
        $this->postJson(route('draw.toggle.publish.schedule', $draw), ['action' => 'unpublish', 'revision' => $service->revision($event)])->assertOk();
        $this->assertDatabaseCount('published_schedule_assignments', 0);
        $this->assertTrue((bool) $draw->fresh()->published);
    }

    public function test_stale_bulk_revision_and_foreign_draw_are_rejected_before_any_change(): void
    {
        [$event, $draw, $time] = $this->context();
        $service = app(SchedulePublicationService::class);
        $revision = $service->revision($event);
        $time->update(['court' => '4']);
        $this->postJson(route('backend.event-draws.bulk-publication', $event), ['operation' => 'schedules', 'action' => 'publish', 'draw_ids' => [$draw->id], 'revision' => $revision])->assertUnprocessable();
        $this->assertDatabaseCount('published_schedule_assignments', 0);
        $foreign = Draw::factory()->create(['event_id' => Event::factory()->create()->id]);
        $this->postJson(route('draw.toggle.publish.schedule', $foreign), ['action' => 'publish'])->assertForbidden();
        $this->postJson(route('backend.event-draws.bulk-publication', $event), ['operation' => 'schedules', 'action' => 'publish', 'draw_ids' => [$draw->id, $foreign->id]])->assertUnprocessable();
        $this->assertDatabaseCount('published_schedule_assignments', 0);
    }

    public function test_setup_planner_and_calendar_have_clear_links_to_this_draw_hub(): void
    {
        [$event, $draw] = $this->context();
        foreach ([route('draw.guide.show', [$event, $draw]), route('draw.setup.show', $draw), route('backend.event-venue-schedule.index', $event), route('backend.event-venue-schedule.calendar', ['event' => $event, 'date' => 'all'])] as $url) {
            $page = $this->get($url)->assertOk()->assertSee('Back to draws');
            $this->assertStringContainsString('href="'.route('headOffice.show', $event).'"', $page->getContent());
        }
    }
}
