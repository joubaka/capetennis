<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardEventPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_admin_can_publish_from_the_dashboard(): void
    {
        $event = Event::factory()->create(['published' => false, 'signUp' => false]);
        $otherEvent = Event::factory()->create(['published' => false]);
        $admin = User::factory()->create();
        $event->admins()->attach($admin);

        $dashboard = route('backend.dashboard');
        $this->actingAs($admin)->get($dashboard)
            ->assertOk()
            ->assertSee('Publish event');

        $this->from($dashboard)->post(route('admin.events.settings.update', $event), [
            '_method' => 'PATCH',
            'published' => '1',
        ])->assertRedirect($dashboard)->assertSessionHas('success');

        $this->assertDatabaseHas('events', ['id' => $event->id, 'published' => true, 'signUp' => false]);
        $this->get($dashboard)->assertOk()->assertDontSee('Publish event');

        $this->patch(route('admin.events.settings.update', $otherEvent), ['published' => '1'])
            ->assertForbidden();
        $this->assertDatabaseHas('events', ['id' => $otherEvent->id, 'published' => false]);
    }
}
