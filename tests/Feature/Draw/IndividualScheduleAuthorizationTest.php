<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IndividualScheduleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'convenor', 'guard_name' => 'web']);
    }

    public function test_schedule_data_is_scoped_to_the_managed_event(): void
    {
        $event = Event::factory()->create();
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $otherEvent = Event::factory()->create();
        $otherAdmin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $otherEvent->id, 'user_id' => $otherAdmin->id]);

        $this->actingAs($otherAdmin)
            ->getJson(route('backend.individual-schedule.data', $draw))
            ->assertForbidden();
    }

    public function test_event_admin_can_read_schedule_data(): void
    {
        $event = Event::factory()->create();
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);

        $this->actingAs($admin)
            ->getJson(route('backend.individual-schedule.data', $draw))
            ->assertOk();
    }

    public function test_locked_draw_schedule_cannot_be_mutated(): void
    {
        $event = Event::factory()->create();
        $draw = Draw::factory()->create(['event_id' => $event->id, 'locked' => 1]);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);

        $this->actingAs($admin)
            ->postJson(route('backend.individual-schedule.clear', $draw))
            ->assertForbidden();
    }
}
