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
        $this->assertSame(['2026-10-09' => ['saved' => 2, 'published' => 3, 'matched' => 2, 'pending' => 1, 'status' => 'Updates not published'],
            '2026-10-10' => ['saved' => 1, 'published' => 0, 'matched' => 0, 'pending' => 1, 'status' => 'Updates not published']], $response->viewData('wholeDaySchedule')->all());
        $html = $response->getContent();
        preg_match('/data-draw-ids="([^"]+)"/', $html, $ids);
        $this->assertSame($draws->pluck('id')->all(), json_decode(html_entity_decode($ids[1]), true));
        $cardsHtml = strstr($html, '<template data-day-card-template>', true);
        preg_match_all('/<form[^>]*method="post"[^>]*action="[^"]+\/venue-schedule\/calendar\/(?:publish|hide)"[^>]*>(.*?)<\/form>/s', $cardsHtml, $forms);
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

    public function test_day_status_compares_assignments_and_reports_partial_changed_removed_and_unpublished_times(): void
    {
        [$event, $venue] = $this->setupEvent();
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        $changed = $this->saved($draw, $venue, '2026-10-09');
        $this->saved($draw, $venue, '2026-10-10');
        $this->saved($draw, $venue, '2026-10-11');
        $removed = $this->saved($draw, $venue, '2026-10-13');
        $service = app(SchedulePublicationService::class);
        $service->publish($event, ['draw_id' => $draw->id]);
        $changed->update(['court_label' => '2', 'duration_min' => 45]);
        $this->saved($draw, $venue, '2026-10-10');
        $this->saved($draw, $venue, '2026-10-12');
        $removed->update(['scheduled_at' => null]);
        $before = DB::table('team_fixtures')->get()->toJson();
        $snapshots = DB::table('published_schedule_assignments')->get()->toJson();
        $response = $this->get(route('headOffice.show', $event))->assertOk();
        $days = $response->viewData('wholeDaySchedule');
        $this->assertSame('Updates not published', $days['2026-10-09']['status']);
        $this->assertSame($days['2026-10-09']['saved'], $days['2026-10-09']['published']);
        $this->assertSame(1, $days['2026-10-09']['pending']);
        $this->assertSame('Partly published', $days['2026-10-10']['status']);
        $this->assertSame(1, $days['2026-10-10']['matched']);
        $this->assertSame('Published', $days['2026-10-11']['status']);
        $this->assertSame(0, $days['2026-10-11']['pending']);
        $this->assertSame('Unpublished', $days['2026-10-12']['status']);
        $this->assertSame('Updates not published', $days['2026-10-13']['status']);
        $this->assertSame(0, $days['2026-10-13']['saved']);
        $response->assertSee('published snapshot times')->assertSee('matches with pending changes');
        $this->assertSame($before, DB::table('team_fixtures')->get()->toJson());
        $this->assertSame($snapshots, DB::table('published_schedule_assignments')->get()->toJson());
    }

    public function test_draw_summary_uses_draw_flags_and_empty_event_has_explicit_status(): void
    {
        [$event] = $this->setupEvent();
        $first = Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        $last = Draw::factory()->create(['event_id' => $event->id, 'published' => false]);
        $summary = fn () => $this->get(route('headOffice.show', $event))->assertOk()->viewData('drawPublicationSummary');
        $this->assertSame(['published' => 1, 'unpublished' => 1, 'status' => 'Partly published'], $summary());
        $last->update(['published' => true]);
        $this->assertSame(['published' => 2, 'unpublished' => 0, 'status' => 'All published'], $summary());
        $first->update(['published' => false]); $last->update(['published' => false]);
        $this->assertSame(['published' => 0, 'unpublished' => 2, 'status' => 'Unpublished'], $summary());
        $first->delete(); $last->delete();
        $response = $this->get(route('headOffice.show', $event))->assertOk()->assertSee('No draws')->assertSee('Not scheduled');
        $this->assertSame(['published' => 0, 'unpublished' => 0, 'status' => 'No draws'], $response->viewData('drawPublicationSummary'));
    }

    public function test_ajax_day_toggle_returns_all_day_statuses_and_fresh_revision_without_redirect(): void
    {
        [$event, $venue] = $this->setupEvent();
        $otherVenue = Venue::forceCreate(['name' => 'Other venue']);
        $event->venues()->attach($otherVenue->id, ['num_courts' => 1]);
        $first = Draw::factory()->create(['event_id' => $event->id]);
        $second = Draw::factory()->create(['event_id' => $event->id]);
        $this->saved($first, $venue, '2026-10-09');
        $this->saved($second, $otherVenue, '2026-10-09');
        $this->saved($first, $venue, '2026-10-10');
        $service = app(SchedulePublicationService::class);
        $revision = $service->revision($event);
        $published = $this->postJson(route('backend.event-venue-schedule.calendar.publish', $event), ['date' => '2026-10-09', 'revision' => $revision])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('event_id', $event->id)->assertJsonPath('changed', 2)
            ->assertJsonPath('days.0.status', 'Published')->assertJsonPath('days.1.status', 'Unpublished');
        $this->assertNotSame($revision, $published->json('revision'));
        $this->assertFalse((bool) $first->fresh()->published);
        $this->postJson(route('backend.event-venue-schedule.calendar.hide', $event), ['date' => '2026-10-09', 'revision' => $published->json('revision')])
            ->assertOk()->assertJsonPath('action', 'hide')->assertJsonPath('days.0.status', 'Unpublished')->assertJsonPath('changed', 2);
        $this->assertDatabaseCount('published_schedule_assignments', 0);
        $this->assertDatabaseCount('team_fixtures', 3);
    }

    public function test_ajax_moved_snapshot_updates_old_day_and_stale_revision_is_an_error(): void
    {
        [$event, $venue] = $this->setupEvent();
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $fixture = $this->saved($draw, $venue, '2026-10-09');
        $service = app(SchedulePublicationService::class);
        $service->publish($event, ['draw_id' => $draw->id]);
        $oldRevision = $service->revision($event);
        $fixture->update(['scheduled_at' => '2026-10-10 09:00:00']);
        $this->postJson(route('backend.event-venue-schedule.calendar.publish', $event), ['date' => '2026-10-10', 'revision' => $oldRevision])
            ->assertUnprocessable()->assertJsonPath('success', false);
        $this->assertDatabaseHas('published_schedule_assignments', ['fixture_id' => $fixture->id, 'scheduled_at' => '2026-10-09 09:00:00']);
        $before = $fixture->fresh()->toJson();
        $status = $this->getJson(route('backend.event-venue-schedule.calendar.publication-status', $event))->assertOk()
            ->assertJsonPath('days.0.status', 'Updates not published')->assertJsonPath('days.1.status', 'Updates not published');
        $this->assertSame($before, $fixture->fresh()->toJson());
        $this->postJson(route('backend.event-venue-schedule.calendar.publish', $event), ['date' => '2026-10-10', 'revision' => $status->json('revision')])
            ->assertOk()->assertJsonCount(1, 'days')->assertJsonPath('days.0.date', '2026-10-10')->assertJsonPath('days.0.status', 'Published');
        $this->assertDatabaseHas('published_schedule_assignments', ['fixture_id' => $fixture->id, 'scheduled_at' => '2026-10-10 09:00:00']);
    }

    public function test_ajax_status_and_mutations_require_same_event_authorization(): void
    {
        [$event, $venue] = $this->setupEvent();
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $this->saved($draw, $venue, '2026-10-09');
        $foreign = Event::factory()->create(['eventType' => 3]);
        $foreignDraw = Draw::factory()->create(['event_id' => $foreign->id]);
        $this->getJson(route('backend.event-venue-schedule.calendar.publication-status', $foreign))->assertForbidden();
        $this->postJson(route('backend.event-venue-schedule.calendar.publish', $event), ['date' => '2026-10-09', 'revision' => app(SchedulePublicationService::class)->revision($event), 'draw_id' => $foreignDraw->id])->assertUnprocessable();
        $this->actingAs(User::factory()->create());
        $this->getJson(route('backend.event-venue-schedule.calendar.publication-status', $event))->assertForbidden();
        $this->postJson(route('backend.event-venue-schedule.calendar.publish', $event), ['date' => '2026-10-09', 'revision' => str_repeat('0', 64)])->assertForbidden();
        $this->assertDatabaseCount('published_schedule_assignments', 0);
    }

    public function test_status_read_retries_boundedly_instead_of_pairing_stale_days_with_a_new_revision(): void
    {
        [$event, $venue] = $this->setupEvent();
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $fixture = $this->saved($draw, $venue, '2026-10-09');
        $revisionReads = 0;
        DB::listen(function ($query) use ($fixture, &$revisionReads) {
            if (str_contains($query->sql, 'published_schedule_assignments') && str_contains($query->sql, 'order by')) {
                $revisionReads++;
                // Simulate another scheduler changing timings between status reads.
                DB::table('team_fixtures')->where('id', $fixture->id)->update(['scheduled_at' => $revisionReads % 2 ? '2026-10-10 09:00:00' : '2026-10-09 09:00:00']);
            }
        });
        $this->getJson(route('backend.event-venue-schedule.calendar.publication-status', $event))->assertStatus(503)->assertJsonPath('success', false);
        $this->assertSame(4, $revisionReads);
        $this->assertDatabaseCount('published_schedule_assignments', 0);
    }

    public function test_initial_page_uses_stable_snapshot_and_pauses_actions_when_status_cannot_be_confirmed(): void
    {
        [$event, $venue] = $this->setupEvent();
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $fixture = $this->saved($draw, $venue, '2026-10-09');
        $revisionReads = 0;
        DB::listen(function ($query) use ($fixture, &$revisionReads) {
            if (str_contains($query->sql, 'published_schedule_assignments') && str_contains($query->sql, 'order by')) {
                $revisionReads++;
                DB::table('team_fixtures')->where('id', $fixture->id)->update(['scheduled_at' => $revisionReads % 2 ? '2026-10-10 09:00:00' : '2026-10-09 09:00:00']);
            }
        });
        $response = $this->get(route('headOffice.show', $event))->assertOk()->assertSee('Publication status unconfirmed. Actions are paused; retry the status check before changing a day.')
            ->assertSee('data-initial-unconfirmed="true"', false);
        $this->assertTrue($response->viewData('schedulePublicationUnconfirmed'));
        $this->assertNull($response->viewData('schedulePublicationRevision'));
        $this->assertTrue($response->viewData('wholeDaySchedule')->isEmpty());
        $this->assertSame(4, $revisionReads);
        $this->assertDatabaseCount('published_schedule_assignments', 0);
    }
}
