<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventCopyTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_user_can_save_event_copy_without_uploading_a_new_logo(): void
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $superUser = User::factory()->create()->assignRole('super-user');
        $eventAdmin = User::factory()->create();

        DB::table('eventtypes')->insert([
            'id' => 1,
            'name' => 'Individual',
            'type' => 'individual',
        ]);

        $source = Event::factory()->create([
            'eventType' => 1,
            'logo' => 'source-logo.png',
        ]);
        $source->admins()->attach($eventAdmin->id);

        $category = Category::factory()->create();
        CategoryEvent::factory()->create([
            'event_id' => $source->id,
            'category_id' => $category->id,
            'entry_fee' => 175,
        ]);

        $response = $this->actingAs($superUser)->post(route('backend.events.store'), [
            'source_event_id' => $source->id,
            'name' => 'Copied Event',
            'eventType' => 1,
        ]);

        $copy = Event::where('name', 'Copied Event')->firstOrFail();

        $response->assertRedirect(route('admin.events.overview', $copy));
        $this->assertSame('source-logo.png', $copy->logo);
        $this->assertDatabaseHas('category_events', [
            'event_id' => $copy->id,
            'category_id' => $category->id,
            'entry_fee' => 175,
        ]);
        $this->assertDatabaseHas('event_admins', [
            'event_id' => $copy->id,
            'user_id' => $eventAdmin->id,
        ]);
    }
}
