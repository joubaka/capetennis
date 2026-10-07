<?php

namespace Tests\Feature;

use App\Models\{Event, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventResultControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_switches_are_independent_event_scoped_and_preserved_on_partial_updates(): void
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('super-user');
        $event = Event::factory()->create();
        $other = Event::factory()->create();
        $this->assertFalse($event->fresh()->result_notifications_enabled);
        $this->assertTrue($event->fresh()->result_auto_refresh_enabled);
        $this->actingAs($admin)->get(route('admin.events.settings', $event))->assertOk()
            ->assertSee('Send result emails for this event')->assertSee('Automatically refresh results and standings pages');
        $this->patchJson(route('admin.events.settings.update', $event), ['result_notifications_enabled' => true])->assertOk();
        $this->assertTrue($event->fresh()->result_notifications_enabled);
        $this->assertTrue($event->fresh()->result_auto_refresh_enabled);
        $this->patchJson(route('admin.events.settings.update', $event), ['result_auto_refresh_enabled' => false])->assertOk();
        $this->patchJson(route('admin.events.settings.update', $event), ['name' => 'Updated event'])->assertOk();
        $this->assertTrue($event->fresh()->result_notifications_enabled);
        $this->assertFalse($event->fresh()->result_auto_refresh_enabled);
        $this->assertFalse($other->fresh()->result_notifications_enabled);
        $this->assertTrue($other->fresh()->result_auto_refresh_enabled);
        $this->patchJson(route('admin.events.settings.update', $event), ['result_notifications_enabled' => false])->assertOk();
        $this->assertFalse($event->fresh()->result_notifications_enabled);
        $this->assertFalse($event->fresh()->result_auto_refresh_enabled);
        $this->patchJson(route('admin.events.settings.update', $event), ['result_auto_refresh_enabled' => 'invalid'])->assertUnprocessable();
    }

    public function test_unassigned_administrator_cannot_change_switches(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        $event = Event::factory()->create();
        $this->actingAs($admin)->patchJson(route('admin.events.settings.update', $event), [
            'result_notifications_enabled' => true, 'result_auto_refresh_enabled' => false,
        ])->assertForbidden();
        $this->assertFalse($event->fresh()->result_notifications_enabled);
        $this->assertTrue($event->fresh()->result_auto_refresh_enabled);
    }
}
