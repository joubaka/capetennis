<?php

namespace Tests\Feature;

use App\Models\CategoryEventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature tests for BankRefundController::queryPayfast().
 *
 * Route: GET /backend/refunds/{registration}/payfast-query
 *        named admin.refunds.bank.payfast-query
 *        Middleware: auth + role:super-user
 */
class BankRefundPayfastQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Spatie roles must exist in the DB for assignRole() to work
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',      'guard_name' => 'web']);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function superUser(): User
    {
        $user = User::factory()->create();
        $user->assignRole('super-user');
        return $user;
    }

    private function regularUser(): User
    {
        return User::factory()->create();
    }

    // -----------------------------------------------------------------------
    // Auth guard
    // -----------------------------------------------------------------------

    public function test_guest_is_redirected_to_login(): void
    {
        $reg = CategoryEventRegistration::factory()->create();

        $response = $this->get(route('admin.refunds.bank.payfast-query', $reg));

        $response->assertRedirect(route('login'));
    }

    // -----------------------------------------------------------------------
    // Role guard
    // -----------------------------------------------------------------------

    public function test_non_super_user_is_forbidden(): void
    {
        $user = $this->regularUser();
        $reg  = CategoryEventRegistration::factory()->create();

        $this->actingAs($user);

        $response = $this->get(route('admin.refunds.bank.payfast-query', $reg));

        // Spatie role middleware returns 403
        $response->assertStatus(403);
    }

    // -----------------------------------------------------------------------
    // No pf_payment_id → error flash
    // -----------------------------------------------------------------------

    public function test_query_fails_gracefully_when_no_pf_payment_id(): void
    {
        $user = $this->superUser();
        // No pf_transaction_id → paymentInfo returns [] → pf_payment_id is null
        $reg = CategoryEventRegistration::factory()->create([
            'pf_transaction_id' => null,
        ]);

        $this->actingAs($user);

        $response = $this->get(route('admin.refunds.bank.payfast-query', $reg));

        $response->assertStatus(302);
        $response->assertSessionHasErrors();
    }

    // -----------------------------------------------------------------------
    // queryPayfast — always redirects back (302)
    // -----------------------------------------------------------------------

    public function test_query_always_redirects_back(): void
    {
        $user = $this->superUser();
        $reg  = CategoryEventRegistration::factory()->create([
            'pf_transaction_id' => null,
        ]);

        Http::fake([
            'api.payfast.co.za/refunds/query/*' => Http::response(['status' => 'complete'], 200),
        ]);

        $this->actingAs($user);

        $response = $this->get(route('admin.refunds.bank.payfast-query', $reg));

        // Should be a redirect (302) regardless of PayFast response
        $response->assertStatus(302);
    }
}
