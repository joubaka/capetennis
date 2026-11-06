<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventAnnouncementPresentationTest extends TestCase
{
    public function test_global_inbox_requires_sign_in_and_only_returns_public_visible_posts(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('published');
        });
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->string('title');
            $table->text('message');
            $table->timestamps();
            $table->softDeletes();
        });
        DB::table('events')->insert([
            ['id' => 1, 'name' => 'Public event', 'published' => true],
            ['id' => 2, 'name' => 'Draft event', 'published' => false],
        ]);
        foreach ([[1, 1, null], [2, 2, null], [3, 1, now()], [4, 3, null]] as [$id, $eventId, $deletedAt]) {
            DB::table('announcements')->insert([
                'id' => $id,
                'event_id' => $eventId,
                'title' => 'Notice '.$id,
                'message' => 'Body stays on the event page',
                'created_at' => now(),
                'deleted_at' => $deletedAt,
            ]);
        }

        $this->getJson(route('announcement-inbox'))->assertUnauthorized();

        $actor = new User;
        $actor->id = 7;
        $revision = Announcement::findOrFail(1)->readRevision();
        $this->actingAs($actor)->getJson(route('announcement-inbox'))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.eventName', 'Public event')
            ->assertJsonPath('0.revision', $revision)
            ->assertJsonPath('0.url', route('events.show', 1).'#event-announcement-1')
            ->assertJsonMissingPath('0.message')
            ->assertDontSee('Body stays on the event page');
    }

    public function test_announcements_are_collapsed_and_keep_sanitized_content_and_revision_tracking(): void
    {
        $event = new Event(['name' => 'Example tennis event']);
        $event->id = 987654;
        $announcement = new Announcement(['title' => 'Starting times', 'message' => '<p>Play starts at 09:00. Please arrive 15 minutes early.</p><script>alert(1)</script>']);
        $announcement->id = 123;
        $announcement->created_at = now();
        $event->setRelation('announcements', collect([$announcement]));
        $html = view('frontend.event.partials.event-announcements', compact('event'))->render();

        $this->assertStringContainsString('View 1 new announcement', $html);
        $this->assertStringContainsString('ct:announcements:987654:guest', $html);
        $this->assertStringContainsString('id="event-announcements"', $html);
        $this->assertStringContainsString('id="event-announcement-123"', $html);
        $this->assertStringContainsString('data-posted-at="'.$announcement->created_at->getTimestampMs().'"', $html);
        $this->assertStringContainsString('Mark as read &amp; close', $html);
        $this->assertStringContainsString('Mark all as read', $html);
        $this->assertStringContainsString('<p>Play starts at 09:00.', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertStringNotContainsString('<details open', $html);
        preg_match('/data-revision="([^"]+)"/', $html, $revision);
        $announcement->message = '<p>Updated: play starts at 10:00.</p>';
        $updated = view('frontend.event.partials.event-announcements', compact('event'))->render();
        $this->assertStringNotContainsString($revision[0], $updated);

        if (getenv('ANNOUNCEMENT_QA_EXPORT')) {
            $html = str_replace(asset('assets/js/event-announcements.js'), 'assets/js/event-announcements.js', $html);
            file_put_contents(public_path('announcement-qa.html'), '<!doctype html><html><head><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="assets/vendor/css/core.css"><link rel="stylesheet" href="assets/vendor/css/theme-default.css"><link rel="stylesheet" href="css/frontend-workspace.css"></head><body class="ct-frontend"><main class="container py-4" style="max-width:900px"><h2>Example tennis event</h2>'.$html.'<section class="card p-4"><h3>Teams and registration</h3><p>The next event section remains easy to reach.</p></section></main></body></html>');
        }
    }

    public function test_no_announcement_card_is_rendered_for_an_empty_event(): void
    {
        $event = new Event;
        $event->setRelation('announcements', collect());
        $this->assertSame('', trim(view('frontend.event.partials.event-announcements', compact('event'))->render()));
    }
}
