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
            ->postJson(route('backend.event-draws.bulk-publication', $this->event), [
                'operation' => 'schedule_visibility',
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

    public function test_event_admin_can_apply_scoring_format_to_every_event_draw_without_creating_a_second_settings_record(): void
    {
        $existing = Draw::factory()->create(['event_id' => $this->event->id]);
        $existing->settings()->create([
            'num_sets' => 1,
            'schedule_visibility' => DrawSetting::SCHEDULE_VISIBILITY_FULL,
        ]);
        $withoutSettings = Draw::factory()->create(['event_id' => $this->event->id]);
        $foreign = Draw::factory()->create(['event_id' => Event::factory()->create()->id]);
        $foreign->settings()->create(['num_sets' => 1]);

        $this->actingAs($this->admin)
            ->postJson(route('backend.events.schedule-visibility', $this->event), [
                'schedule_visibility' => DrawSetting::SCHEDULE_VISIBILITY_FIRST_MATCH,
                'num_sets' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('num_sets', 3)
            ->assertJsonPath('updated_draws', 2);

        $this->assertSame(3, $existing->fresh()->settings->num_sets);
        $this->assertSame(3, $withoutSettings->fresh()->settings->num_sets);
        $this->assertSame(1, $foreign->fresh()->settings->num_sets);
        $this->assertSame(1, $existing->settings()->count());
        $this->assertSame(1, $withoutSettings->settings()->count());
    }

    public function test_event_scoring_format_update_is_atomic_when_a_draw_is_locked(): void
    {
        $editable = Draw::factory()->create(['event_id' => $this->event->id]);
        $editable->settings()->create(['num_sets' => 1]);
        $locked = Draw::factory()->create(['event_id' => $this->event->id, 'locked' => true]);
        $locked->settings()->create(['num_sets' => 1]);

        $this->actingAs($this->admin)
            ->postJson(route('backend.events.schedule-visibility', $this->event), [
                'schedule_visibility' => DrawSetting::SCHEDULE_VISIBILITY_FULL,
                'num_sets' => 3,
            ])
            ->assertForbidden();

        $this->assertSame(1, $editable->fresh()->settings->num_sets);
        $this->assertSame(1, $locked->fresh()->settings->num_sets);
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
