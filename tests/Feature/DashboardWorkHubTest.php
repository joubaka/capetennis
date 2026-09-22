<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\Paginator;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DashboardWorkHubTest extends TestCase
{
    use RefreshDatabase;

    public function test_bare_event_admin_sees_only_assigned_events_and_admin_identity(): void
    {
        $admin = User::factory()->create();
        $assigned = Event::factory()->unpublished()->create(['name' => 'Assigned Private Event']);
        $other = Event::factory()->create(['name' => 'Someone Else Event']);
        EventAdmin::create(['user_id' => $admin->id, 'event_id' => $assigned->id]);

        $this->actingAs($admin)
            ->get(route('backend.dashboard'))
            ->assertOk()
            ->assertSee('Event administrator')
            ->assertSee('Assigned Private Event')
            ->assertSee('Not published')
            ->assertDontSee('Someone Else Event')
            ->assertSee(route('admin.events.overview', $assigned), false)
            ->assertDontSee(route('admin.events.copy', $assigned), false)
            ->assertViewHas('managedEventCount', 1);

        $this->assertFalse($admin->hasRole('admin'));
        $this->assertFalse($admin->hasRole('super-user'));
    }

    public function test_managed_events_are_bounded_and_current_events_precede_completed_events(): void
    {
        $admin = User::factory()->create();
        $completed = Event::factory()->create([
            'name' => 'Old Completed Event',
            'start_date' => today()->subMonth(),
            'end_date' => today()->subWeeks(3),
        ]);
        $upcoming = Event::factory()->create([
            'name' => 'Next Upcoming Event',
            'start_date' => today()->addDay(),
            'end_date' => today()->addDays(2),
        ]);
        $extraEvents = Event::factory()->count(11)->create();

        foreach ($extraEvents->prepend($upcoming)->prepend($completed) as $event) {
            EventAdmin::create(['user_id' => $admin->id, 'event_id' => $event->id]);
        }

        $this->actingAs($admin)
            ->get(route('backend.dashboard'))
            ->assertOk()
            ->assertViewHas('managedEvents', function ($events) use ($upcoming): bool {
                return $events instanceof Paginator
                    && $events->count() === 12
                    && $events->first()->is($upcoming)
                    && $events->hasMorePages();
            })
            ->assertViewHas('managedEventCount', 13);
    }

    public function test_empty_hub_is_useful_and_old_arbitrary_user_feed_is_retired(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('backend.dashboard'))
            ->assertOk()
            ->assertSee('No events to manage yet');

        $this->get('/events/ajax/userEvents/'.$user->id)->assertNotFound();
    }

    public function test_super_user_event_list_is_all_events_but_still_bounded_and_copy_is_available(): void
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $superUser = User::factory()->create()->assignRole('super-user');
        $events = Event::factory()->count(13)->create();

        $this->actingAs($superUser)
            ->get(route('backend.dashboard'))
            ->assertOk()
            ->assertViewHas('managedEvents', fn ($managedEvents): bool =>
                $managedEvents->count() === 12 && $managedEvents->hasMorePages()
            )
            ->assertViewHas('managedEventCount', 13)
            ->assertSee('Copy event');
    }
}
