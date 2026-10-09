<?php

namespace Tests\Feature;

use App\Models\CategoryEvent;
use App\Models\Draw;
use App\Models\Event;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminWorkflowSimplificationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(Event $event): User
    {
        Role::findOrCreate('admin', 'web');
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);

        return $admin;
    }

    public function test_category_creation_posts_exact_category_and_creates_unpublished_draw(): void
    {
        $event = Event::factory()->create(['name' => 'Example tournament']);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $this->admin($event);
        $type = DB::table('draw_types')->insertGetId(['drawTypeName' => 'Round robin', 'btn_color' => 'primary']);
        $page = $this->get(route('category.manage', $category))->assertOk();
        $page->assertSee('Create unpublished draw')->assertSee('name="category_event_id" value="'.$category->id.'"', false);
        $this->fixture('category', $page->getContent());
        $this->post(route('draws.generate', $category), ['category_event_id' => $category->id, 'draw_name' => 'Example draw', 'draw_type' => $type])->assertRedirect();
        $this->assertDatabaseHas('draws', ['category_event_id' => $category->id, 'event_id' => $event->id, 'drawName' => 'Example draw', 'published' => 0, 'oop_published' => 0]);
        $this->assertDatabaseCount('draws', 1);
    }

    public function test_category_creation_keeps_cross_event_authorization_and_no_mutation(): void
    {
        $event = Event::factory()->create();
        $other = Event::factory()->create();
        $category = CategoryEvent::factory()->create(['event_id' => $other->id]);
        $this->admin($event);
        $type = DB::table('draw_types')->insertGetId(['drawTypeName' => 'Round robin', 'btn_color' => 'primary']);
        $this->post(route('draws.generate', $category), ['category_event_id' => $category->id, 'draw_name' => 'Forbidden draw', 'draw_type' => $type])->assertForbidden();
        $this->assertDatabaseCount('draws', 0);
    }

    public function test_venue_page_distinguishes_general_assignments_and_disables_published_draw(): void
    {
        $event = Event::factory()->create(['name' => 'Example tournament']);
        $this->admin($event);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'drawName' => 'Under 12 draw', 'published' => true]);
        $venue = DB::table('venues')->insertGetId(['name' => 'Example courts', 'event_id' => $event->id]);
        $draw->venues()->attach($venue);
        $html = view('backend.venue.venue-show', ['errors' => new \Illuminate\Support\ViewErrorBag(), 'event' => $event, 'draw' => $draw, 'venues' => $draw->venues, 'selectedVenues' => [$venue], 'drawTypes' => collect()])->render();
        $this->assertStringContainsString('Current venues for Under 12 draw', $html);
        $this->assertStringContainsString('Round-specific venues and match times', $html);
        $this->assertMatchesRegularExpression('/id="apply-venue-button"[^>]*disabled/', $html);
        $this->assertDatabaseCount('draw_venues', 1);
        $this->fixture('venues', $html);
    }

    public function test_series_events_show_dates_and_separate_attach_from_creation_without_mutating(): void
    {
        $series = Series::create(['name' => 'Example series', 'year' => 2026]);
        $event = Event::factory()->create(['name' => 'Example event', 'series_id' => $series->id, 'start_date' => '2026-10-09', 'published' => false, 'signUp' => false]);
        $this->admin($event);
        $available = Event::factory()->create(['name' => 'Another example event']);
        $html = view('backend.series.events', ['errors' => new \Illuminate\Support\ViewErrorBag(), 'series' => $series, 'seriesEvents' => collect([$event]), 'availableEvents' => collect([$available])])->render();
        $this->assertStringContainsString('09 Oct 2026', $html);
        $this->assertStringContainsString('Unpublished', $html);
        $this->assertStringContainsString('Selecting an event from another series moves its series association', $html);
        $this->assertLessThan(strpos($html, 'Add Existing Event'), strpos($html, 'Events in this Series'));
        $this->assertStringContainsString('Contact, information and logo', $html);
        $this->assertDatabaseCount('events', 2);
        $this->fixture('series', $html);
    }

    private function fixture(string $name, string $html): void
    {
        if (getenv('CT_BATCHES2731_QA') === '1') {
            file_put_contents(storage_path('app/batches2731-qa/'.$name.'.html'), $html);
        }
    }
}
