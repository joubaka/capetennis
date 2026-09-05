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
        ]);
        TeamFixture::create([
            'draw_id' => $private->id,
            'match_nr' => 2,
            'scheduled' => true,
            'scheduled_at' => '2026-09-06 10:00:00',
            'venue_id' => $venue->id,
        ]);

        $this->get(route('fixtures.venue', [$event->id, $venue->id]))
            ->assertOk()
            ->assertSee('Published schedule')
            ->assertDontSee('Private schedule');

        $this->get(route('fixtures.order', [$event->id, $venue->id, '2026-09-06']))
            ->assertOk()
            ->assertSee('Published schedule')
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

        OrderOfPlay::create([
            'draw_id' => $draw->id,
            'fixture_id' => 1,
            'venue_id' => 1,
            'time' => '2026-09-06 09:00:00',
        ]);

        $this->postJson(route('draw.toggle.publish.schedule', $draw))
            ->assertOk()
            ->assertJsonPath('oop_published', true)
            ->assertJsonPath('preview_only', true);

        $draw->refresh();
        $this->assertFalse((bool) $draw->published);
        $this->assertTrue((bool) $draw->oop_published);
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
