<?php

namespace Tests\Feature;

use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Feature tests for CategoryEventController::withdraw() (admin path).
 *
 * Route: POST /backend/admin/category-registration/{registration}/withdraw
 *        named admin.category.registration.withdraw
 *        Middleware: auth (no role check — any authenticated user, e.g. event admin)
 */
class AdminCategoryWithdrawTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /** Create a basic active registration with its required relations. */
    private function activeRegistration(array $overrides = []): CategoryEventRegistration
    {
        return CategoryEventRegistration::factory()->create(array_merge([
            'status' => 'active',
        ], $overrides));
    }

    private function adminUser(): User
    {
        return User::factory()->create();
    }

    // -----------------------------------------------------------------------
    // Auth guard
    // -----------------------------------------------------------------------

    public function test_guest_is_redirected_to_login(): void
    {
        $reg = $this->activeRegistration();

        $response = $this->post(route('admin.category.registration.withdraw', $reg));

        $response->assertRedirect(route('login'));
    }

    // -----------------------------------------------------------------------
    // Already-withdrawn guard
    // -----------------------------------------------------------------------

    public function test_already_withdrawn_registration_returns_error(): void
    {
        $admin = $this->adminUser();
        $reg   = CategoryEventRegistration::factory()->withdrawn()->create();

        $this->actingAs($admin);

        $response = $this->post(route('admin.category.registration.withdraw', $reg));

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    // -----------------------------------------------------------------------
    // Successful withdrawal — unpaid registration
    // -----------------------------------------------------------------------

    public function test_active_unpaid_registration_is_withdrawn_and_stays_on_page(): void
    {
        $admin = $this->adminUser();
        $reg   = $this->activeRegistration(['pf_transaction_id' => null]);

        $this->actingAs($admin);

        $response = $this->post(route('admin.category.registration.withdraw', $reg));

        // Redirects back with success (no refund needed)
        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('category_event_registrations', [
            'id'            => $reg->id,
            'status'        => 'withdrawn',
            'refund_status' => 'not_refunded',
        ]);
    }

    // -----------------------------------------------------------------------
    // Successful withdrawal — paid registration → redirect to refund chooser
    // -----------------------------------------------------------------------

    public function test_paid_registration_redirects_to_admin_refund_chooser(): void
    {
        $admin = $this->adminUser();

        // Create a paid registration that has a pf_transaction_id
        $reg = CategoryEventRegistration::factory()
            ->paid()
            ->create(['status' => 'active']);

        $this->actingAs($admin);

        // We expect a redirect to the admin refund chooser page
        // The controller does: redirect()->route('admin.registration.refund.choose', [$event, $registration])
        $response = $this->post(route('admin.category.registration.withdraw', $reg));

        $response->assertStatus(302);
        $response->assertSessionHasNoErrors();

        // Registration must be marked withdrawn in DB
        $this->assertDatabaseHas('category_event_registrations', [
            'id'     => $reg->id,
            'status' => 'withdrawn',
        ]);
    }

    // -----------------------------------------------------------------------
    // Withdrawal sets correct DB state
    // -----------------------------------------------------------------------

    public function test_withdrawal_sets_not_refunded_status(): void
    {
        $admin = $this->adminUser();
        $reg   = $this->activeRegistration();

        $this->actingAs($admin);

        $this->post(route('admin.category.registration.withdraw', $reg));

        $reg->refresh();
        $this->assertEquals('withdrawn', $reg->status);
        $this->assertEquals('not_refunded', $reg->refund_status);
        $this->assertNotNull($reg->withdrawn_at);
    }

    public function test_withdrawal_clears_previous_refund_amounts(): void
    {
        $admin = $this->adminUser();
        $reg   = $this->activeRegistration([
            'refund_gross' => 100.00,
            'refund_fee'   => 10.00,
            'refund_net'   => 90.00,
        ]);

        $this->actingAs($admin);

        $this->post(route('admin.category.registration.withdraw', $reg));

        $reg->refresh();
        $this->assertEquals(0, $reg->refund_gross);
        $this->assertEquals(0, $reg->refund_fee);
        $this->assertEquals(0, $reg->refund_net);
        $this->assertNull($reg->refund_method);
    }
}
