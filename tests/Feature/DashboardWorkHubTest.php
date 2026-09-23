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
        $other = Event::factory()->unpublished()->create(['name' => 'Someone Else Event']);
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

    public function test_ordinary_profile_does_not_present_an_active_empty_management_hub(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('backend.dashboard'))
            ->assertOk()
            ->assertDontSee('My events')
            ->assertDontSee('No events to manage yet')
            ->assertSee('Players Linked');

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
            ->assertSee('Copy event')
            ->assertSee('Platform administrator')
            ->assertDontSee('Event administrator');
    }

    public function test_global_admin_without_event_assignment_gets_player_tools_not_empty_events(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');

        $this->actingAs($admin)
            ->get(route('backend.dashboard'))
            ->assertOk()
            ->assertDontSee('My Events')
            ->assertSee('data-bs-target="#tab-players"', false)
            ->assertSee('Players');
    }

    public function test_upcoming_events_use_published_canonical_date_rules_for_every_role(): void
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $eventAdmin = User::factory()->create();
        $superUser = User::factory()->create()->assignRole('super-user');

        $future = Event::factory()->create([
            'name' => 'Published Future Event',
            'start_date' => today()->addDays(5),
            'end_date' => today()->addDays(7),
            'published' => true,
        ]);
        $ongoing = Event::factory()->create([
            'name' => 'Published Ongoing Event',
            'start_date' => today()->subDay(),
            'end_date' => today(),
            'published' => true,
        ]);
        $nullEndFuture = Event::factory()->create([
            'name' => 'Published Future Without End',
            'start_date' => today()->addDays(2),
            'end_date' => null,
            'published' => true,
        ]);
        $ended = Event::factory()->create([
            'name' => 'Ended Published Event',
            'start_date' => today()->subWeek(),
            'end_date' => today()->subDay(),
            'published' => true,
        ]);
        $unpublished = Event::factory()->unpublished()->create([
            'name' => 'Assigned Unpublished Future',
            'start_date' => today()->addDay(),
            'end_date' => today()->addDays(2),
        ]);
        EventAdmin::create(['user_id' => $eventAdmin->id, 'event_id' => $unpublished->id]);

        foreach ([$eventAdmin, $superUser] as $user) {
            $this->actingAs($user)
                ->get(route('backend.dashboard'))
                ->assertOk()
                ->assertViewHas('upcomingEvents', function ($events) use ($future, $ongoing, $nullEndFuture, $ended, $unpublished): bool {
                    $ids = $events->modelKeys();

                    return in_array($future->id, $ids, true)
                        && in_array($ongoing->id, $ids, true)
                        && in_array($nullEndFuture->id, $ids, true)
                        && ! in_array($ended->id, $ids, true)
                        && ! in_array($unpublished->id, $ids, true);
                });
        }
    }

    public function test_upcoming_events_are_bounded_ordered_and_link_to_public_event_pages(): void
    {
        $user = User::factory()->create();
        $events = collect(range(1, 7))->map(fn (int $days) => Event::factory()->create([
            'name' => 'Upcoming Event '.$days,
            'start_date' => today()->addDays($days),
            'end_date' => today()->addDays($days + 1),
            'published' => true,
        ]));

        $this->actingAs($user)
            ->get(route('backend.dashboard'))
            ->assertOk()
            ->assertViewHas('upcomingEvents', fn ($upcoming): bool =>
                $upcoming->count() === 6
                && $upcoming->first()->is($events->first())
                && ! $upcoming->contains($events->last())
            )
            ->assertSee(route('events.show', $events->first()), false)
            ->assertSee(route('events.index'), false)
            ->assertSee('View event')
            ->assertSee('View all events');
    }
}
