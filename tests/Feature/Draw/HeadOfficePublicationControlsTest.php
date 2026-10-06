<?php

namespace Tests\Feature\Draw;

use App\Models\{Draw, Event, Fixture, OrderOfPlay, TeamFixture, User, Venue};
use App\Services\Scheduling\SchedulePublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HeadOfficePublicationControlsTest extends TestCase
{
    use RefreshDatabase;

    private function setupEvent(): array
    {
        $event = Event::factory()->create(['eventType' => 3]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        $venue = Venue::forceCreate(['name' => 'Main courts']);
        $event->venues()->attach($venue->id, ['num_courts' => 2]);
        return [$event, $venue];
    }

    private function saved(Draw $draw, Venue $venue, string $date): TeamFixture
    {
        return TeamFixture::create(['draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => 1, 'match_nr' => 1,
            'fixture_type' => 1, 'scheduled_at' => $date.' 09:00:00', 'venue_id' => $venue->id, 'court_label' => '1', 'duration_min' => 60]);
    }

    public function test_event_controls_include_all_tabs_and_whole_day_forms_have_no_filters(): void
    {
        [$event, $venue] = $this->setupEvent();
        $draws = collect(['u/10 Girls', 'u/13 Boys'])->map(fn ($name) => Draw::factory()->create(['event_id' => $event->id, 'drawName' => $name]));
        foreach ($draws as $draw) $this->saved($draw, $venue, '2026-10-09');
        $individual = Fixture::factory()->create(['draw_id' => $draws->last()->id]);
        OrderOfPlay::create(['fixture_id' => $individual->id, 'draw_id' => $draws->last()->id, 'venue_id' => $venue->id, 'court' => '2', 'time' => '2026-10-09 10:00:00']);
        app(SchedulePublicationService::class)->publish($event, ['date' => '2026-10-09']);
        $draws->first()->fixtures()->first()->update(['scheduled_at' => '2026-10-10 09:00:00']);
        $foreign = Draw::factory()->create(['event_id' => Event::factory()->create(['eventType' => 3])->id]);
        $this->saved($foreign, $venue, '2026-10-11');
        $response = $this->get(route('headOffice.show', ['headOffice' => $event->id, 'draw_id' => $draws->first()->id, 'venue_id' => $venue->id, 'date' => '2026-10-10']))->assertOk();
        $response->assertSee('Publish all 2 draws')->assertSee('Unpublish all 2 draws')->assertSee('Whole-day schedule publication');
        $this->assertSame(['2026-10-09' => ['saved' => 2, 'published' => 3], '2026-10-10' => ['saved' => 1, 'published' => 0]], $response->viewData('wholeDaySchedule')->all());
        $html = $response->getContent();
        preg_match('/data-draw-ids="([^"]+)"/', $html, $ids);
        $this->assertSame($draws->pluck('id')->all(), json_decode(html_entity_decode($ids[1]), true));
        preg_match_all('/<form method="post" action="[^"]+\/venue-schedule\/calendar\/(?:publish|hide)"[^>]*>(.*?)<\/form>/s', $html, $forms);
        $this->assertCount(4, $forms[1]);
        foreach ($forms[1] as $form) {
            preg_match_all('/name="([^"]+)"/', $form, $fields);
            $this->assertSame(['_token', 'date', 'revision'], $fields[1]);
        }
    }

    public function test_whole_day_actions_change_all_draws_and_venues_with_revision_protection(): void
    {
        [$event, $venue] = $this->setupEvent();
        $otherVenue = Venue::forceCreate(['name' => 'Other courts']);
        $event->venues()->attach($otherVenue->id, ['num_courts' => 1]);
        $draws = collect([1, 2])->map(fn () => Draw::factory()->create(['event_id' => $event->id]));
        $this->saved($draws->first(), $venue, '2026-10-09');
        $this->saved($draws->last(), $otherVenue, '2026-10-09');
        $this->saved($draws->last(), $otherVenue, '2026-10-10');
        $service = app(SchedulePublicationService::class);
        $revision = $service->revision($event);
        $this->post(route('backend.event-venue-schedule.calendar.publish', $event), ['date' => '2026-10-09', 'revision' => $revision])->assertRedirect();
        $this->assertDatabaseCount('published_schedule_assignments', 2);
        $this->assertFalse((bool) $draws->first()->fresh()->published);
        $this->post(route('backend.event-venue-schedule.calendar.hide', $event), ['date' => '2026-10-09', 'revision' => $revision])->assertSessionHasErrors('schedule');
        $this->assertDatabaseCount('published_schedule_assignments', 2);
        $this->post(route('backend.event-venue-schedule.calendar.hide', $event), ['date' => '2026-10-09', 'revision' => $service->revision($event)])->assertRedirect();
        $this->assertDatabaseCount('published_schedule_assignments', 0);
        $this->assertDatabaseCount('team_fixtures', 3);
    }

    public function test_unpublish_all_retains_locked_draw_and_unauthorized_user_cannot_use_controls(): void
    {
        [$event] = $this->setupEvent();
        $first = Draw::factory()->create(['event_id' => $event->id, 'published' => true, 'locked' => false, 'drawName' => 'u/10 Girls']);
        $locked = Draw::factory()->create(['event_id' => $event->id, 'published' => true, 'locked' => true, 'drawName' => 'u/13 Boys']);
        $this->postJson(route('backend.event-draws.bulk-publication', $event), ['operation' => 'draws', 'action' => 'unpublish', 'draw_ids' => [$first->id, $locked->id]])
            ->assertOk()->assertJsonPath('success', false)->assertJsonPath('changed.0', $first->id)->assertJsonPath('failed.0.id', $locked->id);
        $this->assertFalse((bool) $first->fresh()->published);
        $this->assertTrue((bool) $locked->fresh()->published);
        $this->actingAs(User::factory()->create());
        $this->get(route('headOffice.show', $event))->assertForbidden();
        $this->postJson(route('backend.event-draws.bulk-publication', $event), ['operation' => 'draws', 'action' => 'unpublish', 'draw_ids' => [$locked->id]])->assertForbidden();
        $this->post(route('backend.event-venue-schedule.calendar.publish', $event), ['date' => '2026-10-09', 'revision' => str_repeat('0', 64)])->assertForbidden();
        $this->assertTrue((bool) $locked->fresh()->published);
    }
}
