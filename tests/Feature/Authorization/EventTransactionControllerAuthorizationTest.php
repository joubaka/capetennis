<?php

namespace Tests\Feature\Authorization;

use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventTransactionControllerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $eventAdmin;
    private Event $event;
    private Event $otherEvent;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        $this->eventAdmin = User::factory()->create()->assignRole('admin');
        $this->event = Event::factory()->create();
        $this->otherEvent = Event::factory()->create();

        EventAdmin::create([
            'user_id' => $this->eventAdmin->id,
            'event_id' => $this->event->id,
        ]);
    }

    public function test_event_admin_can_view_own_events_transactions(): void
    {
        $this->actingAs($this->eventAdmin)
            ->get(route('admin.events.transactions', $this->event))
            ->assertSuccessful()
            ->assertViewIs('backend.event.transactions')
            ->assertViewHas('event', fn (Event $event): bool => $event->is($this->event));
    }

    public function test_event_admin_cannot_view_another_events_transactions(): void
    {
        $this->actingAs($this->eventAdmin)
            ->get(route('admin.events.transactions', $this->otherEvent))
            ->assertForbidden();
    }
}
