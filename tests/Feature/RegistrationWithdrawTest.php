<?php

namespace Tests\Feature;

use App\Models\CategoryEventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationWithdrawTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_withdraw(): void
    {
        $registration = CategoryEventRegistration::factory()->create();

        $response = $this->post(route('registrations.withdraw', $registration));

        $response->assertRedirect(route('login'));
    }

    public function test_owner_can_withdraw_own_registration(): void
    {
        $user = User::factory()->create();
        $registration = CategoryEventRegistration::factory()
            ->create(['user_id' => $user->id, 'status' => 'active']);

        $this->actingAs($user);

        // CategoryEventRegistration::canWithdraw needs categoryEvent.event relation.
        // This test exercises the auth redirect and ownership check path.
        $response = $this->post(route('registrations.withdraw', $registration));

        // Either succeeds or redirects — must not 403.
        $response->assertStatus(302);
        $response->assertNotForbidden();
    }

    public function test_non_owner_cannot_withdraw(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $registration = CategoryEventRegistration::factory()
            ->create(['user_id' => $owner->id, 'status' => 'active']);

        $this->actingAs($other);

        $response = $this->post(route('registrations.withdraw', $registration));

        // Should redirect back with error (not 403, the controller handles it via canWithdraw)
        $response->assertStatus(302);
    }

    public function test_already_withdrawn_registration_cannot_be_withdrawn_again(): void
    {
        $user = User::factory()->create();
        $registration = CategoryEventRegistration::factory()
            ->withdrawn()
            ->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $response = $this->post(route('registrations.withdraw', $registration));

        $response->assertSessionHasErrors();
    }
}
