<?php

namespace Tests\Feature\Draw;

use App\Models\{CategoryEvent, Draw, Event, OrderOfPlay, Registration, User, Venue};
use App\Services\Draw\FlexibleMonradService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DrawPublicationToolbarTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create(['name' => 'U/19 Interpro test', 'eventType' => 5]);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $actor->id]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'drawName' => 'U/19 Boys']);
        $draw->settings()->create(['workflow' => 'feed_in_monrad']);
        $slots = [];
        foreach (['aa', 'ab', 'ba', 'bb'] as $path) {
            $registration = Registration::factory()->create();
            $registration->categoryEvents()->attach($category->id, ['status' => 'registered', 'payment_status_id' => 1]);
            $slots[$path] = ['type' => 'player', 'id' => $registration->id];
        }
        app(FlexibleMonradService::class)->save($draw, ['size' => 4, 'slots' => $slots], 0);
        app(FlexibleMonradService::class)->generate($draw, 1);
        $this->actingAs($actor);
        return [$event, $draw->fresh(), $actor];
    }

    public function test_feed_in_toolbar_has_visible_independent_toggles_and_same_event_draw_navigation(): void
    {
        [$event, $draw] = $this->scenario();
        $next = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'U/19 Girls']);
        $previous = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'U/17 Boys']);
        Draw::factory()->create(['drawName' => 'PRIVATE FOREIGN DRAW']);
        $event = $event->fresh(); $draw->setRelation('event', $event);
        $html = view('backend.draw.partials.workspace-header', compact('draw'))->render();
        $this->assertStringContainsString('data-workspace-publish-draw-proxy', $html);
        $this->assertStringContainsString('data-workspace-publish-schedule', $html);
        $this->assertLessThan(strpos($html, 'data-individual-draw-tools'), strpos($html, 'data-draw-publication'));
        $this->assertStringContainsString('Draw 2 of 3', $html);
        foreach ([$previous, $next] as $other) $this->assertStringContainsString(route('backend.draw.roundrobin.show', $other), $html);
        $this->assertStringNotContainsString('PRIVATE FOREIGN DRAW', $html);
        $scheduler = view('backend.schedule.partials.draw-controls', ['event' => $event, 'guideSelection' => collect([$draw]), 'planningDate' => '2026-10-30'])->render();
        $this->assertStringContainsString(route('flexible-monrad.publish', $draw), $scheduler);
        $this->assertStringContainsString('data-flexible-revision="2"', $scheduler);
        $this->assertStringContainsString('date=2026-10-30', $scheduler);
        $this->assertStringNotContainsString('PRIVATE FOREIGN DRAW', $scheduler);
        $this->assertFalse((bool) $draw->fresh()->published);
        $this->assertFalse((bool) $draw->fresh()->oop_published);
        if (getenv('CT_DRAW_PUBLICATION_QA') === '1') {
            if (! is_dir(storage_path('app/testing'))) mkdir(storage_path('app/testing'), 0777, true);
            file_put_contents(storage_path('app/testing/draw-publication-header.html'), $html);
            file_put_contents(storage_path('app/testing/draw-publication-scheduler.html'), $scheduler);
        }
    }

    public function test_first_and_last_draw_navigation_have_disabled_boundaries_and_unauthorized_users_have_no_publish_controls(): void
    {
        [$event, $first] = $this->scenario();
        $first->update(['drawName' => 'A draw']);
        $last = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'Z draw']);
        foreach ([$first, $last] as $draw) {
            $draw->setRelation('event', $event->fresh());
            $html = view('backend.draw.partials.workspace-header', compact('draw'))->render();
            $this->assertStringContainsString('aria-disabled="true"', $html);
            $this->assertStringContainsString($draw->id === $first->id ? 'Draw 1 of 2' : 'Draw 2 of 2', $html);
        }
        $this->actingAs(User::factory()->create()->assignRole('admin'));
        $html = view('backend.draw.partials.publication-controls', ['publicationDraw' => $first])->render();
        $this->assertStringNotContainsString('data-workspace-publish', $html);
        $this->postJson(route('flexible-monrad.publish', $first), ['revision' => 2, 'published' => true])->assertForbidden();
        $this->postJson(route('draw.toggle.publish.schedule', $first))->assertForbidden();
    }

    public function test_canonical_flexible_publication_remains_revision_guarded_and_independent_of_saved_schedule(): void
    {
        [, $draw] = $this->scenario();
        $publish = route('flexible-monrad.publish', $draw);
        $this->postJson($publish, ['revision' => 1, 'published' => true])->assertConflict();
        $this->assertFalse((bool) $draw->fresh()->published);
        $this->postJson($publish, ['revision' => 2, 'published' => true])->assertOk()->assertJsonPath('published', true);
        $this->assertFalse((bool) $draw->fresh()->oop_published);
        $venue = Venue::forceCreate(['name' => 'Publication test courts']);
        OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $draw->drawFixtures()->first()->id, 'venue_id' => $venue->id,
            'court' => '1', 'time' => '2026-10-30 09:00:00', 'duration_minutes' => 60]);
        $this->postJson(route('draw.toggle.publish.schedule', $draw))->assertOk();
        $this->assertTrue((bool) $draw->fresh()->oop_published);
        $this->assertDatabaseCount('published_schedule_assignments', 1);
        $revision = $draw->fresh()->flexibleMonrad->revision;
        $this->postJson($publish, ['revision' => $revision, 'published' => false])->assertOk();
        $this->assertTrue((bool) $draw->fresh()->oop_published);
        $this->assertDatabaseCount('published_schedule_assignments', 1);
    }
}
