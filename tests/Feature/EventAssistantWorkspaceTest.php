<?php

namespace Tests\Feature;

use App\Models\{Event, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventAssistantWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dedicated_workspace_is_lightweight_and_super_user_only(): void
    {
        config(['event-assistant.enabled' => true]);
        $event = Event::factory()->create();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('super-user');
        $this->actingAs($actor)->get(route('backend.event-assistant.workspace', $event))
            ->assertOk()->assertSee('Ask assistant')->assertSee('Test your tournament plan')
            ->assertSee('event-assistant-panel')->assertDontSee('Detailed finances');

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $actor->syncRoles(['admin']);
        $this->actingAs($actor)->get(route('backend.event-assistant.workspace', $event))->assertForbidden();
    }
}
