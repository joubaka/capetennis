<?php

namespace Tests\Feature\Draw;

use App\Models\{CategoryEvent, Draw, Event, Fixture, OrderOfPlay, Registration, User, Venue};
use App\Services\Draw\DrawSetupGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DrawSetupGuideTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(bool $category = true): array
    {
        $event = Event::factory()->create();
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category ? CategoryEvent::factory()->create(['event_id' => $event->id])->id : null]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $user->id]);
        $this->actingAs($user);
        return [$event, $draw, $user];
    }

    public function test_resume_is_read_only_and_category_selection_is_event_scoped(): void
    {
        [$event, $draw] = $this->scenario(false);
        $url = route('draw.guide.show', [$event, $draw]);
        $page = $this->get($url)->assertOk()->assertSee('Step 2 of 6');
        if (getenv('DRAW_GUIDE_SNAPSHOT_DIR')) file_put_contents(getenv('DRAW_GUIDE_SNAPSHOT_DIR').'/individual-guide.html', $page->getContent());
        $this->assertSame(0, Fixture::count());
        $this->assertNull($draw->fresh()->settings);
        $foreign = CategoryEvent::factory()->create();
        $categoryUrl = route('draw.guide.category', [$event, $draw]);
        $this->postJson($categoryUrl, ['category_event_id' => $foreign->id])->assertUnprocessable();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $this->post($categoryUrl, ['category_event_id' => $category->id])->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('Step 3 of 6');
        $this->assertSame($category->id, $draw->fresh()->category_event_id);
        $this->assertSame(0, Fixture::count());
        $this->assertFalse((bool) $draw->published);
        $this->assertFalse((bool) $draw->oop_published);
        $draw->update(['locked' => true]);
        $this->post($categoryUrl, ['category_event_id' => $category->id])->assertForbidden();
    }

    public function test_event_overview_handles_legacy_venue_text_and_keeps_saved_category_draws_visible(): void
    {
        [$event, $draw] = $this->scenario();
        // A legacy attribute must not replace the event_venues relationship.
        $event->setAttribute('venues', 'Legacy venue directions');
        $event->load('venues', 'categoryEvents.category', 'draws.settings');
        foreach ($event->categoryEvents as $category) $category->eligible_draw_entries_count = 0;
        $event->draws->each(fn ($item) => $item->setRelation('event', $event));
        $html = view('backend.headOffice.individual-event-show', [
            'event' => $event, 'eventVenues' => $event->getRelation('venues'),
        ])->render();
        if (getenv('DRAW_GUIDE_SNAPSHOT_DIR')) file_put_contents(getenv('DRAW_GUIDE_SNAPSHOT_DIR').'/individual-overview.html', $html);
        $this->assertStringContainsString('Categories and saved draws', $html);
        $this->assertStringContainsString('Continue setup: '.$draw->drawName, $html);
        $this->assertStringContainsString('id="venue-print-venue"', $html);
        $this->assertSame(1, Draw::count());
        $this->assertSame(0, Fixture::count());
        $this->assertFalse((bool) $draw->fresh()->published);
    }

    public function test_ajax_guide_returns_private_fragment_and_category_save_returns_resume_url(): void
    {
        [$event, $draw] = $this->scenario(false);
        $url = route('draw.guide.show', [$event, $draw]);
        $response = $this->getJson($url)->assertOk()->assertJsonPath('guide_url', $url);
        $this->assertStringContainsString('data-draw-guide', $response->json('html'));
        $this->assertStringNotContainsString('<html', $response->json('html'));
        $this->assertStringNotContainsString('<script', $response->json('html'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame(0, Fixture::count());
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $this->postJson(route('draw.guide.category', [$event, $draw]), ['category_event_id' => $category->id])
            ->assertOk()->assertJsonPath('guide_url', $url);
        $this->getJson($url)->assertOk()->assertSee('Step 3 of 6');
        $this->assertFalse((bool) $draw->fresh()->published);
    }

    public function test_unrelated_admin_and_foreign_event_cannot_resume_or_edit(): void
    {
        [$event, $draw] = $this->scenario();
        $other = Event::factory()->create();
        $this->get(route('draw.guide.show', [$other, $draw]))->assertNotFound();
        $user = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $other->id, 'user_id' => $user->id]);
        $this->actingAs($user)->get(route('draw.guide.show', [$event, $draw]))->assertForbidden();
        $this->postJson(route('draw.guide.category', [$event, $draw]), ['category_event_id' => $draw->category_event_id])->assertForbidden();
    }

    public function test_combined_creation_and_legacy_formats_resume_without_forcing_a_category_or_reset(): void
    {
        [$event, $draw] = $this->scenario(false);
        DB::table('draw_types')->insert(['drawTypeName' => 'Singles', 'btn_color' => 'primary', 'type' => 'individual']);
        $response = $this->postJson(route('headoffice.createSingleDraw', $event), ['drawName' => 'Combined trial', 'category_scope' => 'event'])->assertOk();
        $created = Draw::findOrFail($response->json('draw.id'));
        $this->assertNull($created->category_event_id);
        $response->assertJsonPath('setup_url', route('draw.setup.show', $created))->assertJsonPath('guide_url', null);
        $this->assertSame(0, Fixture::count());
        $created->settings()->create(['workflow' => 'round_robin']);
        $state = app(DrawSetupGuide::class)->state($created->fresh());
        $this->assertTrue($state['steps'][1]['complete']);
        $this->assertSame(3, $state['next']);
        $draw->update(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $event->id])->id]);
        Fixture::factory()->create(['draw_id' => $draw->id, 'winner_registration' => null]);
        $state = app(DrawSetupGuide::class)->state($draw->fresh());
        $this->assertTrue($state['steps'][2]['complete']);
        $this->assertSame(4, $state['next']);
    }

    public function test_partial_schedule_stays_incomplete_and_publication_is_a_separate_step(): void
    {
        [$event, $draw] = $this->scenario();
        $draw->settings()->create(['workflow' => 'round_robin']);
        $fixtures = Fixture::factory()->count(2)->create(['draw_id' => $draw->id, 'winner_registration' => null]);
        $service = app(DrawSetupGuide::class);
        $this->assertSame(4, $service->state($draw->fresh())['next']);
        $savedFixtures = $fixtures->map(fn ($fixture) => $fixture->getAttributes())->all();
        $resumeUrl = route('draw.guide.show', [$event, $draw]);
        $this->get($resumeUrl)->assertOk()->assertSee('Step 4 of 6');
        $this->get($resumeUrl)->assertOk()->assertSee('Step 4 of 6');
        $this->assertSame($savedFixtures, $fixtures->map(fn ($fixture) => $fixture->fresh()->getAttributes())->all());
        $this->assertSame(1, Draw::count());
        $this->assertSame(2, Fixture::count());
        $venue = Venue::forceCreate(['name' => 'Synthetic court']);
        foreach ($fixtures as $index => $fixture) {
            OrderOfPlay::create(['fixture_id' => $fixture->id, 'draw_id' => $draw->id, 'time' => '2026-10-30 09:00:00', 'venue_id' => $venue->id, 'court' => (string) ($index + 1)]);
            $state = $service->state($draw->fresh());
            $this->assertSame($index === 0 ? 4 : 5, $state['next']);
        }
        $this->assertFalse($state['steps'][5]['complete']);
        $draw->update(['published' => true]);
        $this->assertFalse($service->state($draw->fresh())['steps'][5]['complete']);
        app(\App\Services\Scheduling\SchedulePublicationService::class)->publish($event, ['draw_id' => $draw->id]);
        $draw->refresh();
        $this->assertTrue($service->state($draw->fresh())['steps'][5]['complete']);
        OrderOfPlay::where('fixture_id', $fixtures->first()->id)->update(['time' => '2026-10-30 10:00:00']);
        $this->assertFalse($service->state($draw->fresh())['steps'][5]['complete']);
    }
}
