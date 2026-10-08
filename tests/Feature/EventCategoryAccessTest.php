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

    public function test_category_list_precedes_add_tools_and_create_reopens_after_validation(): void
    {
        $actor = User::factory()->create()->assignRole('super-user');
        $category = \App\Models\CategoryEvent::factory()->create(['event_id' => $this->event->id, 'entry_fee' => 125]);
        $url = route('admin.events.categories', $this->event);
        $page = $this->actingAs($actor)->get($url)->assertOk()
            ->assertSeeInOrder(['Categories in this event', 'Add Existing Category', 'Create New Category', 'Maintenance'])
            ->assertSee('Save fee')->assertSee('value="125"', false);
        $this->patchJson(route('admin.events.category-fee.update', $category), ['entry_fee' => 150])->assertOk();
        $this->assertSame(150, (int) $category->fresh()->entry_fee);
        $this->from($url)->post(route('admin.categories.create', $this->event), ['name' => $category->category->name])->assertSessionHasErrors('name');
        $error = $this->get($url)->assertOk()->assertSee('value="'.e($category->category->name).'"', false);
        $this->assertMatchesRegularExpression('/<details class="card mb-4"\s+open\s*>/', $error->getContent());
        if (getenv('CT_BATCHES678_QA') === '1') {
            \Illuminate\Support\Facades\File::ensureDirectoryExists(storage_path('app/batches678-qa'));
            file_put_contents(storage_path('app/batches678-qa/categories.html'), str_replace('http://localhost', 'http://127.0.0.1:8776/ct/public', $page->getContent()));
            file_put_contents(storage_path('app/batches678-qa/categories-validation.html'), str_replace('http://localhost', 'http://127.0.0.1:8776/ct/public', $error->getContent()));
        }
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

    public function test_masters_overview_exposes_the_event_setup_shortcuts(): void
    {
        $superUser = User::factory()->create()->assignRole('super-user');
        $mastersTypeId = DB::table('eventtypes')->where('code', 'masters')->value('id');
        $event = Event::factory()->create(['eventType' => $mastersTypeId]);

        $this->actingAs($superUser)
            ->get(route('admin.events.overview', $event))
            ->assertOk()
            ->assertSee('Event setup')
            ->assertSee('Event settings')
            ->assertSee('Event categories &amp; fees', false)
            ->assertSee('Invitation setup')
            ->assertSee(route('admin.events.settings', $event), false)
            ->assertSee(route('admin.events.categories', $event), false)
            ->assertSee(route('backend.masters.setup', $event), false)
            ->assertDontSee('Masters selection setup')
            ->assertDontSee('Configure Masters categories');
    }
}
