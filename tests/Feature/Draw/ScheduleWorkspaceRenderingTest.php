<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ScheduleWorkspaceRenderingTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        $this->event = Event::factory()->create(['eventType' => 6]);
        DB::table('event_admins')->insert(['event_id' => $this->event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
    }

    public function test_manage_full_schedule_renders_empty_event_in_manual_mode(): void
    {
        $this->get(route('backend.event-venue-schedule.index', ['event' => $this->event, 'manual' => 1]))
            ->assertOk()->assertSee('Event schedule workspace')
            ->assertSee('const manualMode = true;', false)
            ->assertDontSee('@if($guideSelection', false)
            ->assertDontSee('Step 5 of 6');
    }

    public function test_manual_schedule_renders_selected_draw_guide_and_return_route(): void
    {
        $draw = Draw::factory()->create(['event_id' => $this->event->id, 'drawName' => 'Selected schedule draw']);
        Draw::factory()->create(['event_id' => $this->event->id, 'drawName' => 'Other schedule draw']);
        $this->get(route('backend.event-venue-schedule.index', ['event' => $this->event, 'manual' => 1, 'draw_ids' => [$draw->id]]))
            ->assertOk()->assertSee('Step 4 of 5 · Schedule')
            ->assertSee(route('draw.guide.show', [$this->event, $draw]), false)
            ->assertSee('Review setup &amp; publication · Selected schedule draw', false)
            ->assertDontSee('Review setup &amp; publication · Other schedule draw', false)
            ->assertSee('draw_id='.$draw->id, false)
            ->assertDontSee('@if($guideSelection', false);
    }

    public function test_planning_starts_before_secondary_tools_and_publication_stays_available_without_writes(): void
    {
        $draw = Draw::factory()->create(['event_id' => $this->event->id, 'drawName' => 'Simple planning draw']);
        $response = $this->get(route('backend.event-venue-schedule.index', ['event' => $this->event, 'draw_ids' => [$draw->id]]))->assertOk();
        $html = $response->getContent();
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $tools = $xpath->query('//details[@id="schedule-tools"]')->item(0);
        $this->assertNotNull($tools);
        $this->assertFalse($tools->hasAttribute('open'), 'Secondary tools should start collapsed.');
        $this->assertGreaterThan(strpos($html, 'Plan the schedule'), strpos($html, 'id="day-batch-workflow"'));
        $this->assertGreaterThan(strpos($html, 'id="day-batch-workflow"'), strpos($html, 'id="schedule-tools"'));
        $this->assertSame(1, $xpath->query('//a[@data-saved-schedule-link]')->length);
        $this->assertSame(0, $xpath->query('//a[@data-saved-schedule-link]/ancestor::details')->length);
        $this->assertSame(1, $xpath->query('.//*[@data-draw-publication="'.$draw->id.'"]', $tools)->length);
        $this->assertSame(1, $xpath->query('.//*[@id="open-venue-announcement"]', $tools)->length);
        $this->assertSame(1, $xpath->query('.//*[@id="toggle-advanced-scheduling"]', $tools)->length);
        $this->assertSame(1, $xpath->query('//details[contains(@class,"day-batch-order-options") and not(@open)]')->length);
        $this->assertFalse((bool) $draw->fresh()->published);
        $this->assertFalse((bool) $draw->fresh()->oop_published);
        $this->assertDatabaseCount('fixtures', 0);
        $this->assertDatabaseCount('order_of_plays', 0);
        $this->assertDatabaseCount('published_schedule_assignments', 0);
    }
}
