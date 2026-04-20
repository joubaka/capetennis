<?php

namespace Tests\Feature;

use App\Models\CategoryEventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationRefundFlowTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // choose — GET /registrations/{registration}/refund/choose
    // -----------------------------------------------------------------------

    public function test_guest_cannot_access_choose_refund_page(): void
    {
        $reg = CategoryEventRegistration::factory()->withdrawn()->create();

        $response = $this->get(route('registrations.refund.choose', $reg));

        $response->assertRedirect(route('login'));
    }

    public function test_non_owner_cannot_access_choose_refund_page(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $reg = CategoryEventRegistration::factory()
            ->withdrawn()
            ->create(['user_id' => $owner->id]);

        $this->actingAs($other);

        $response = $this->get(route('registrations.refund.choose', $reg));

        $response->assertForbidden();
    }

    public function test_owner_cannot_choose_refund_when_not_withdrawn(): void
    {
        $user = User::factory()->create();
        $reg = CategoryEventRegistration::factory()
            ->create(['user_id' => $user->id, 'status' => 'active']);

        $this->actingAs($user);

        $response = $this->get(route('registrations.refund.choose', $reg));

        // Must redirect with error — not withdrawn yet
        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    // -----------------------------------------------------------------------
    // store — POST /registrations/{registration}/refund/request
    // -----------------------------------------------------------------------

    public function test_guest_cannot_submit_refund_request(): void
    {
        $reg = CategoryEventRegistration::factory()->withdrawn()->create();

        $response = $this->post(route('registrations.refund.request', $reg), [
            'method' => 'wallet',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_non_owner_cannot_submit_refund_request(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $reg = CategoryEventRegistration::factory()
            ->withdrawn()
            ->create(['user_id' => $owner->id]);

        $this->actingAs($other);

        $response = $this->post(route('registrations.refund.request', $reg), [
            'method' => 'wallet',
        ]);

        // Should be blocked — not the owner
        $response->assertStatus(302);
    }

    public function test_invalid_refund_method_fails_validation(): void
    {
        $user = User::factory()->create();
        $reg = CategoryEventRegistration::factory()
            ->withdrawn()
            ->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $response = $this->post(route('registrations.refund.request', $reg), [
            'method' => 'cash', // invalid
        ]);

        $response->assertSessionHasErrors(['method']);
    }

    public function test_bank_refund_requires_bank_fields(): void
    {
        $user = User::factory()->create();
        $reg = CategoryEventRegistration::factory()
            ->withdrawn()
            ->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $response = $this->post(route('registrations.refund.request', $reg), [
            'method' => 'bank',
            // all bank fields missing
        ]);

        $response->assertSessionHasErrors(['account_name', 'bank_name', 'account_number', 'branch_code']);
    }

    public function test_missing_method_field_fails_validation(): void
    {
        $user = User::factory()->create();
        $reg = CategoryEventRegistration::factory()
            ->withdrawn()
            ->create(['user_id' => $user->id]);

        $this->actingAs($user);

        $response = $this->post(route('registrations.refund.request', $reg), []);

        $response->assertSessionHasErrors(['method']);
    }
}
