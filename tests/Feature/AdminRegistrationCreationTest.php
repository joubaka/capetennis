<?php

namespace Tests\Feature;

use App\Models\CategoryEvent;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminRegistrationCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_registration_is_created_as_paid_through_canonical_entry_service(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $player = Player::factory()->create();
        $categoryEvent = CategoryEvent::factory()->create();

        $this->actingAs($admin)->post(route('register.admin'), [
            'player_id' => $player->id,
            'categoryEvent' => $categoryEvent->id,
        ])->assertOk()->assertSee('success');

        $this->assertDatabaseHas('category_event_registrations', [
            'category_event_id' => $categoryEvent->id,
            'user_id' => $admin->id,
            'status' => 'active',
            'payment_status_id' => 1,
        ]);
    }
}
