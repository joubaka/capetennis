<?php

namespace Tests\Feature;

use App\Mail\AdminEntryCreatedMail;
use App\Models\CategoryEvent;
use App\Models\Player;
use App\Models\User;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminRegistrationCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_registration_is_created_as_paid_through_canonical_entry_service(): void
    {
        Mail::fake();
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

        Mail::assertQueued(AdminEntryCreatedMail::class, fn ($mail) => $mail->hasTo($player->email));
    }

    public function test_admin_registration_confirmation_can_be_disabled(): void
    {
        Mail::fake();
        SiteSetting::set('player_email_on_admin_entry', '0', SiteSetting::GROUP_EMAIL);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $player = Player::factory()->create();
        $categoryEvent = CategoryEvent::factory()->create();

        $this->actingAs($admin)->post(route('register.admin'), [
            'player_id' => $player->id,
            'categoryEvent' => $categoryEvent->id,
        ])->assertOk();

        Mail::assertNothingQueued();
    }
}
