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
            ->assertOk()->assertSee('Step 5 of 6')
            ->assertSee(route('draw.guide.show', [$this->event, $draw]), false)
            ->assertSee('Review setup &amp; publication · Selected schedule draw', false)
            ->assertDontSee('Review setup &amp; publication · Other schedule draw', false)
            ->assertSee('draw_id='.$draw->id, false)
            ->assertDontSee('@if($guideSelection', false);
    }
}
