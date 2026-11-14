<?php

namespace Tests\Feature;

use App\Models\Draw;
use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\File;
use App\Models\OrderOfPlay;
use App\Models\TeamFixture;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicTournamentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_cards_and_draw_pages_agree_when_publication_flag_has_no_visible_assignments(): void
    {
        $event = Event::factory()->create(['eventType' => 6]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true, 'oop_published' => true]);
        $draw->settings()->create(['workflow' => 'round_robin']);
        $this->get(route('events.show', $event))->assertOk()->assertSee('Times to follow')->assertDontSee('Draw &amp; times live', false);
        $this->get(route('public.roundrobin.show', $draw))->assertOk()
            ->assertSee('Match times to follow')->assertDontSee('Match times published')
            ->assertSee('Match times and venues have not been published yet.')
            ->assertSee(route('events.show', $event).'#event-draws-match-times', false);
    }

    public function test_summary_uses_published_venues_and_dates_not_later_working_changes(): void
    {
        $event = Event::factory()->create(['eventType' => 6]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'Synthetic mobile draw', 'published' => true, 'oop_published' => true]);
        $draw->settings()->create(['workflow' => 'round_robin']);
        $venue = new Venue; $venue->name = 'Published synthetic venue'; $venue->save();
        $planned = new Venue; $planned->name = 'Provisional synthetic venue'; $planned->save();
        $working = new Venue; $working->name = 'PRIVATE WORKING VENUE'; $working->save();
        $draw->venues()->attach($planned);
        $fixture = \App\Models\Fixture::factory()->create(['draw_id' => $draw->id]);
        $slot = OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $fixture->id, 'venue_id' => $venue->id, 'time' => '2026-11-14 08:00:00']);
        app(\App\Services\Scheduling\SchedulePublicationService::class)->publish($event, ['draw_id' => $draw->id]);
        $slot->update(['venue_id' => $working->id, 'time' => '2026-11-15 13:00:00']);
        $response = $this->get(route('events.show', $event))->assertOk()
            ->assertSee('Published matches from Sat 14 Nov 2026, 08:00')->assertSee('Check the schedule for your own match time.')
            ->assertSee('Published synthetic venue')->assertDontSee('PRIVATE WORKING VENUE');
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'id="event-draws-match-times"'));
        $this->assertLessThan(strpos($html, '>Event information</h4>'), strpos($html, 'id="event-draws-match-times"'));
        if (getenv('PUBLIC_WORKFLOW_SNAPSHOT') === '1') {
            @mkdir(storage_path('app/testing'), 0777, true);
            file_put_contents(storage_path('app/testing/public-workflow.html'), $html);
        }
        $this->get(route('public.roundrobin.show', $draw))->assertOk()->assertSee('Published synthetic venue')->assertDontSee('PRIVATE WORKING VENUE')->assertDontSee('Provisional synthetic venue');
    }

    public function test_summary_rejects_foreign_fixture_assignments_and_draft_publication(): void
    {
        $event = Event::factory()->create(['eventType' => 6]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true, 'oop_published' => true]);
        $other = Draw::factory()->create();
        $foreign = \App\Models\Fixture::factory()->create(['draw_id' => $other->id]);
        \Illuminate\Support\Facades\DB::table('published_schedule_assignments')->insert([
            'event_id' => $event->id, 'draw_id' => $draw->id, 'fixture_kind' => 'individual', 'fixture_id' => $foreign->id,
            'scheduled_at' => '2026-11-14 08:00:00', 'court' => 'FOREIGN COURT', 'duration' => 30, 'published_at' => now(),
        ]);
        $summary = app(\App\Services\PublicDrawPresentation::class)->summary($draw);
        $this->assertFalse($summary['available']);
        $this->assertNull($summary['first_time']);
        $draw->update(['published' => false]);
        $this->get(route('public.roundrobin.show', $draw))->assertForbidden();
    }

    public function test_first_match_only_summary_does_not_reveal_later_venues_or_dates(): void
    {
        $event = Event::factory()->create(['eventType' => 6]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true, 'oop_published' => true]);
        $draw->settings()->create(['workflow' => 'round_robin', 'schedule_visibility' => \App\Models\DrawSetting::SCHEDULE_VISIBILITY_FIRST_MATCH]);
        $players = \App\Models\Registration::factory()->count(2)->create();
        foreach (['Visible opening venue', 'HIDDEN LATER VENUE'] as $index => $name) {
            $venue = new Venue; $venue->name = $name; $venue->save();
            $fixture = \App\Models\Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'RR', 'registration1_id' => $players[0]->id, 'registration2_id' => $players[1]->id]);
            OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $fixture->id, 'venue_id' => $venue->id, 'time' => $index ? '2026-11-15 13:00:00' : '2026-11-14 08:00:00']);
        }
        app(\App\Services\Scheduling\SchedulePublicationService::class)->publish($event, ['draw_id' => $draw->id]);
        $summary = app(\App\Services\PublicDrawPresentation::class)->summary($draw);
        $this->assertSame(['Visible opening venue'], $summary['venues']->all());
        $this->assertSame('2026-11-14 08:00:00', $summary['first_time']);
        $this->get(route('events.show', $event))->assertOk()->assertSee('Visible opening venue')->assertDontSee('HIDDEN LATER VENUE');
    }

    public function test_legacy_draw_url_does_not_bypass_draw_publication(): void
    {
        $event = Event::factory()->create();
        $draft = Draw::factory()->create([
            'event_id' => $event->id,
            'drawName' => 'Private under 12 draw',
            'published' => false,
        ]);

        $this->get(route('frontend.showDraw', $draft->id))
            ->assertForbidden()
            ->assertDontSee('Private under 12 draw');

        foreach (['frontend.fixtures.show', 'frontend.fixtures.index', 'frontend.bracket.fixtures'] as $route) {
            $this->get(route($route, $draft->id))
                ->assertForbidden()
                ->assertDontSee('Private under 12 draw');
        }
    }

    public function test_parent_event_page_hides_draft_draw_names_and_explains_release_state(): void
    {
        $event = Event::factory()->create(['eventType' => 6]);
        Draw::factory()->create([
            'event_id' => $event->id,
            'drawName' => 'Secret draft division',
            'published' => false,
        ]);

        $this->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('href="#event-draws-match-times"', false)
            ->assertSee('View draws &amp; match times', false)
            ->assertSee('id="event-draws-match-times"', false)
            ->assertSee('The draws are being finalised.')
            ->assertDontSee('Secret draft division');
    }

    public function test_parent_event_page_opens_draw_and_schedule_as_separate_tabs(): void
    {
        $event = Event::factory()->create(['eventType' => 6]);
        $draw = Draw::factory()->create([
            'event_id' => $event->id,
            'drawName' => 'Under 14 Girls',
            'published' => true,
            'oop_published' => true,
        ]);
        $draw->settings()->create(['workflow' => 'round_robin']);
        $fixture = \App\Models\Fixture::factory()->create(['draw_id' => $draw->id]);
        $venue = new Venue();
        $venue->name = 'Published round robin court';
        $venue->save();
        OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $fixture->id, 'venue_id' => $venue->id, 'time' => '2026-09-06 09:00:00']);
        app(\App\Services\Scheduling\SchedulePublicationService::class)->publish($event, ['draw_id' => $draw->id]);

        $this->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('href="'.route('public.roundrobin.show', $draw).'#draw"', false)
            ->assertSee('href="'.route('public.roundrobin.show', $draw).'#schedule"', false)
            ->assertSee('View draw')
            ->assertSee('View schedule');
    }

    public function test_public_document_route_enforces_event_publication_and_ownership(): void
    {
        $event = Event::factory()->create();
        $otherEvent = Event::factory()->create();
        $draftEvent = Event::factory()->unpublished()->create();
        $file = new File();
        $file->event_id = $event->id;
        $file->name = 'Tournament information.pdf';
        $file->path = 'public/files/not-needed-for-ownership-check.pdf';
        $file->save();

        $this->get(route('events.documents.show', [$otherEvent, $file]))->assertNotFound();
        $this->get(route('events.documents.show', [$draftEvent, $file]))->assertNotFound();

        $file->path = 'public/files/public-workflow-test.txt';
        $file->save();
        Storage::disk('local')->put($file->path, 'Public tournament document');

        try {
            $this->get(route('events.documents.show', [$event, $file]))
                ->assertOk()
                ->assertHeader('content-disposition', 'inline; filename="public-workflow-test.txt"');
        } finally {
            Storage::disk('local')->delete($file->path);
        }
    }

    public function test_public_venue_routes_only_show_published_draw_schedules(): void
    {
        $event = Event::factory()->create();
        $venue = new Venue();
        $venue->name = 'Public centre court';
        $venue->save();
        $event->venues()->attach($venue->id, ['num_courts' => 2]);

        $visible = Draw::factory()->create([
            'event_id' => $event->id,
            'drawName' => 'Published schedule',
            'published' => true,
            'oop_published' => true,
        ]);
        $private = Draw::factory()->create([
            'event_id' => $event->id,
            'drawName' => 'Private schedule',
            'published' => true,
            'oop_published' => false,
        ]);

        TeamFixture::create([
            'draw_id' => $visible->id,
            'match_nr' => 1,
            'scheduled' => true,
            'scheduled_at' => '2026-09-06 09:00:00',
            'venue_id' => $venue->id,
            'court_label' => 'PUBLIC-COURT-SENTINEL',
        ]);
        TeamFixture::create([
            'draw_id' => $private->id,
            'match_nr' => 2,
            'scheduled' => true,
            'scheduled_at' => '2026-09-06 10:00:00',
            'venue_id' => $venue->id,
        ]);
        app(\App\Services\Scheduling\SchedulePublicationService::class)->publish($event, ['draw_id' => $visible->id]);
        $this->assertDatabaseCount('published_schedule_assignments', 1);

        $this->get(route('fixtures.venue', [$event->id, $venue->id]))
            ->assertOk()
            ->assertSee('Published schedule')
            ->assertSee('Public centre court')
            ->assertDontSee('PUBLIC-COURT-SENTINEL')
            ->assertDontSee('Private schedule');

        $this->get(route('fixtures.order', [$event->id, $venue->id, '2026-09-06']))
            ->assertOk()
            ->assertSee('Published schedule')
            ->assertSee('Public centre court')
            ->assertDontSee('PUBLIC-COURT-SENTINEL')
            ->assertDontSee('Private schedule');
    }

    public function test_public_fixture_page_explains_and_hides_an_unpublished_schedule(): void
    {
        $event = Event::factory()->create(['eventType' => 3]);
        $venue = new Venue();
        $venue->name = 'Private fixture venue';
        $venue->save();
        $draw = Draw::factory()->create([
            'event_id' => $event->id,
            'drawName' => 'Public team draw',
            'published' => true,
            'oop_published' => false,
        ]);
        TeamFixture::create([
            'draw_id' => $draw->id,
            'match_nr' => 1,
            'scheduled' => true,
            'scheduled_at' => '2026-09-06 09:00:00',
            'venue_id' => $venue->id,
        ]);

        $this->get(route('frontend.fixtures.index', $draw))
            ->assertOk()
            ->assertSee('Match times to follow')
            ->assertSee('match times and venues have not been published yet')
            ->assertDontSee('Private fixture venue')
            ->assertDontSee('2026-09-06 09:00');
    }

    public function test_schedule_can_be_published_for_authorized_preview_before_draw_is_public(): void
    {
        Role::findOrCreate('admin');
        $admin = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create();
        EventAdmin::create(['event_id' => $event->id, 'user_id' => $admin->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => false]);

        $this->actingAs($admin)->postJson(route('draw.toggle.publish.schedule', $draw))
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Add at least one match time before publishing the schedule.');

        $fixture = \App\Models\Fixture::factory()->create(['draw_id' => $draw->id]);
        $venue = new Venue();
        $venue->name = 'Private preview court';
        $venue->save();
        OrderOfPlay::create([
            'draw_id' => $draw->id,
            'fixture_id' => $fixture->id,
            'venue_id' => $venue->id,
            'time' => '2026-09-06 09:00:00',
        ]);

        $this->postJson(route('draw.toggle.publish.schedule', $draw))
            ->assertOk()
            ->assertJsonPath('oop_published', true)
            ->assertJsonPath('preview_only', true);

        $draw->refresh();
        $this->assertFalse((bool) $draw->published);
        $this->assertTrue((bool) $draw->oop_published);
        $this->assertDatabaseHas('published_schedule_assignments', ['draw_id' => $draw->id, 'fixture_id' => $fixture->id]);
    }

    public function test_unpublishing_a_draw_retains_its_schedule_for_authorized_preview(): void
    {
        Role::findOrCreate('admin');
        $admin = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create();
        EventAdmin::create(['event_id' => $event->id, 'user_id' => $admin->id]);
        $draw = Draw::factory()->create([
            'event_id' => $event->id,
            'published' => true,
            'oop_published' => true,
            'locked' => false,
        ]);

        $this->actingAs($admin)
            ->postJson(route('draw.toggle.publish', $draw))
            ->assertOk()
            ->assertJsonPath('published', false);

        $draw->refresh();
        $this->assertFalse((bool) $draw->published);
        $this->assertTrue((bool) $draw->oop_published);
    }
}
