<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\DrawSetting;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DrawManagementRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_split_box_preview_does_not_persist_assignments_or_settings(): void
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $user = User::factory()->create()->assignRole('super-user');
        $event = Event::factory()->create();
        $draw = Draw::factory()->create([
            'event_id' => $event->id,
            'locked' => true,
            'published' => true,
        ]);
        DrawSetting::create(['draw_id' => $draw->id, 'boxes' => 2]);
        $registration = Registration::factory()->create();
        DB::table('draw_registrations')->insert([
            'draw_id' => $draw->id,
            'registration_id' => $registration->id,
            'seed' => 1,
            'box_number' => 2,
        ]);

        $this->actingAs($user)
            ->get(route('admin.draws.split-boxes', ['id' => $draw->id, 'boxes' => 4]))
            ->assertOk();

        $this->assertDatabaseHas('draw_registrations', [
            'draw_id' => $draw->id,
            'registration_id' => $registration->id,
            'box_number' => 2,
        ]);
        $this->assertDatabaseHas('draw_settings', ['draw_id' => $draw->id, 'boxes' => 2]);
    }

    public function test_obsolete_schedule_routes_are_not_registered(): void
    {
        $this->assertFalse(Route::has('schedule.create'));
        $this->assertFalse(Route::has('schedule.save'));
        $this->assertFalse(Route::has('schedule.update.time'));
    }

    public function test_draw_mutation_routes_do_not_use_get(): void
    {
        $this->assertSame(['POST'], Route::getRoutes()->getByName('add.venue.draw')->methods());
        $this->assertSame(['DELETE'], Route::getRoutes()->getByName('remove.venue.draw')->methods());
        $this->assertSame(['DELETE'], Route::getRoutes()->getByName('draws.clear-players')->methods());
        $this->assertSame(['POST'], Route::getRoutes()->getByName('draw.insert.result')->methods());
        $this->assertSame(['POST'], Route::getRoutes()->getByName('draw.update.result')->methods());
        $this->assertSame(['POST'], Route::getRoutes()->getByName('fixture.update.players')->methods());
        $this->assertSame(['POST'], Route::getRoutes()->getByName('save.draw.venues')->methods());
    }
}
