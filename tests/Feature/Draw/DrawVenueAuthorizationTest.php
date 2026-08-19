<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DrawVenueAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Draw $draw;
    private int $venueId;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $event = Event::factory()->create();
        $this->draw = Draw::factory()->create([
            'event_id' => $event->id,
            'locked' => false,
            'published' => false,
        ]);
        $this->venueId = DB::table('venues')->insertGetId([
            'name' => 'Audit Court',
            'event_id' => $event->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert([
            'event_id' => $event->id,
            'user_id' => $this->admin->id,
        ]);
    }

    public function test_guest_and_ordinary_user_cannot_sync_draw_venues(): void
    {
        $payload = ['draw' => $this->draw->id, 'venues' => [$this->venueId]];

        $this->post(route('save.draw.venues'), $payload)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())
            ->post(route('save.draw.venues'), $payload)
            ->assertForbidden();

        $this->assertDatabaseMissing('draw_venues', ['draw_id' => $this->draw->id]);
    }

    public function test_event_admin_can_sync_valid_draw_venues(): void
    {
        $this->actingAs($this->admin)
            ->post(route('save.draw.venues'), [
                'draw' => $this->draw->id,
                'venues' => [$this->venueId],
            ])->assertRedirect();

        $this->assertDatabaseHas('draw_venues', [
            'draw_id' => $this->draw->id,
            'venue_id' => $this->venueId,
        ]);
    }

    public function test_locked_draw_rejects_venue_changes(): void
    {
        $this->draw->update(['locked' => true]);

        $this->actingAs($this->admin)
            ->post(route('save.draw.venues'), [
                'draw' => $this->draw->id,
                'venues' => [$this->venueId],
            ])->assertForbidden();
    }
}
