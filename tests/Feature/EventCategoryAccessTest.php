<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventCategoryAccessTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);

        DB::table('eventtypes')->insert([
            'id' => 1,
            'name' => 'Individual',
            'type' => EventType::INDIVIDUAL,
        ]);

        $this->event = Event::factory()->create(['eventType' => 1]);
    }

    public function test_ordinary_user_cannot_access_category_setup(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.events.categories', $this->event))
            ->assertForbidden();
    }

    public function test_event_admin_can_access_category_setup(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert([
            'event_id' => $this->event->id,
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.events.categories', $this->event))
            ->assertOk();
    }

    public function test_super_user_sees_one_canonical_category_link_on_overview(): void
    {
        $superUser = User::factory()->create()->assignRole('super-user');

        $this->actingAs($superUser)
            ->get(route('admin.events.overview', $this->event))
            ->assertOk()
            ->assertSee(route('admin.events.categories', $this->event), false)
            ->assertSee('Categories')
            ->assertDontSee('Category setup: event admins and super users only');
    }

    public function test_event_admin_sees_one_canonical_category_link_on_overview(): void
    {
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert([
            'event_id' => $this->event->id,
            'user_id' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.events.overview', $this->event))
            ->assertOk()
            ->assertSee(route('admin.events.categories', $this->event), false)
            ->assertSee('Categories')
            ->assertDontSee('Category setup: event admins and super users only');
    }
}
