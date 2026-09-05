<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\DrawSetting;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventScheduleVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->event = Event::factory()->create(['eventType' => 6]);
        $this->admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $this->event->id, 'user_id' => $this->admin->id]);
    }

    public function test_event_admin_can_apply_first_match_visibility_to_every_event_draw(): void
    {
        $existing = Draw::factory()->create(['event_id' => $this->event->id]);
        $existing->settings()->create([
            'workflow' => 'round_robin',
            'schedule_visibility' => DrawSetting::SCHEDULE_VISIBILITY_FULL,
        ]);
        $withoutSettings = Draw::factory()->create(['event_id' => $this->event->id]);
        $foreign = Draw::factory()->create(['event_id' => Event::factory()->create()->id]);
        $foreign->settings()->create(['schedule_visibility' => DrawSetting::SCHEDULE_VISIBILITY_FULL]);

        $this->actingAs($this->admin)
            ->postJson(route('backend.events.schedule-visibility', $this->event), [
                'schedule_visibility' => DrawSetting::SCHEDULE_VISIBILITY_FIRST_MATCH,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('updated_draws', 2);

        $this->assertSame(
            DrawSetting::SCHEDULE_VISIBILITY_FIRST_MATCH,
            $existing->fresh()->settings->schedule_visibility,
        );
        $this->assertSame(
            DrawSetting::SCHEDULE_VISIBILITY_FIRST_MATCH,
            $withoutSettings->fresh()->settings->schedule_visibility,
        );
        $this->assertSame(
            DrawSetting::SCHEDULE_VISIBILITY_FULL,
            $foreign->fresh()->settings->schedule_visibility,
        );
    }

    public function test_unauthorized_user_cannot_change_event_schedule_visibility(): void
    {
        $draw = Draw::factory()->create(['event_id' => $this->event->id]);
        $draw->settings()->create(['schedule_visibility' => DrawSetting::SCHEDULE_VISIBILITY_FULL]);

        $this->actingAs(User::factory()->create())
            ->postJson(route('backend.events.schedule-visibility', $this->event), [
                'schedule_visibility' => DrawSetting::SCHEDULE_VISIBILITY_FIRST_MATCH,
            ])
            ->assertForbidden();

        $this->assertSame(DrawSetting::SCHEDULE_VISIBILITY_FULL, $draw->fresh()->settings->schedule_visibility);
    }

    public function test_event_schedule_visibility_rejects_unknown_modes(): void
    {
        $this->actingAs($this->admin)
            ->postJson(route('backend.events.schedule-visibility', $this->event), [
                'schedule_visibility' => 'some_matches',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('schedule_visibility');
    }
}
