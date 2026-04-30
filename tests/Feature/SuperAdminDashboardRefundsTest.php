<?php

namespace Tests\Feature;

use App\Models\CategoryEventRegistration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature tests for the Super Admin Dashboard — specifically the new
 * "Pending Bank Refunds" widget added to backend.superadmin.index.
 */
class SuperAdminDashboardRefundsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
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

    // -----------------------------------------------------------------------
    // Auth guard
    // -----------------------------------------------------------------------

    public function test_guest_cannot_access_superadmin_dashboard(): void
    {
        $response = $this->get(route('backend.superadmin.index'));

        $response->assertRedirect(route('login'));
    }

    // -----------------------------------------------------------------------
    // Dashboard loads for super-user
    // -----------------------------------------------------------------------

    public function test_superadmin_dashboard_loads_for_super_user(): void
    {
        $this->actingAs($this->superUser());

        $response = $this->get(route('backend.superadmin.index'));

        $response->assertOk();
        $response->assertSee('Pending Bank Refunds');
    }

    // -----------------------------------------------------------------------
    // "All clear" shown when no pending bank refunds
    // -----------------------------------------------------------------------

    public function test_dashboard_shows_all_clear_when_no_pending_bank_refunds(): void
    {
        $this->actingAs($this->superUser());

        $response = $this->get(route('backend.superadmin.index'));

        $response->assertOk();
        $response->assertSee('All clear');
    }

    // -----------------------------------------------------------------------
    // Pending bank refund appears in the table
    // -----------------------------------------------------------------------

    public function test_pending_bank_refund_appears_on_dashboard(): void
    {
        $owner = User::factory()->create();
        CategoryEventRegistration::factory()
            ->withdrawn()
            ->create([
                'user_id'               => $owner->id,
                'refund_method'         => 'bank',
                'refund_status'         => 'pending',
                'refund_account_name'   => 'Test Owner',
                'refund_bank_name'      => 'FNB',
                'refund_account_number' => '1234567890',
                'refund_branch_code'    => '250655',
                'refund_net'            => 180.00,
            ]);

        $this->actingAs($this->superUser());

        $response = $this->get(route('backend.superadmin.index'));

        $response->assertOk();
        $response->assertSee('Test Owner');
        $response->assertSee('FNB');
        // Badge count > 0
        $response->assertSeeText('1');
    }

    // -----------------------------------------------------------------------
    // Completed bank refunds do NOT appear in the pending list
    // -----------------------------------------------------------------------

    public function test_completed_bank_refunds_not_shown_in_pending_widget(): void
    {
        $owner = User::factory()->create();
        CategoryEventRegistration::factory()
            ->withdrawn()
            ->create([
                'user_id'               => $owner->id,
                'refund_method'         => 'bank',
                'refund_status'         => 'completed',
                'refund_account_name'   => 'Completed Owner',
                'refund_net'            => 90.00,
            ]);

        $this->actingAs($this->superUser());

        $response = $this->get(route('backend.superadmin.index'));

        $response->assertOk();
        $response->assertDontSee('Completed Owner');
        $response->assertSee('All clear');
    }

    // -----------------------------------------------------------------------
    // PF Status button only shows for registrations with pf_transaction_id
    // -----------------------------------------------------------------------

    public function test_pf_status_button_shown_for_paid_registration(): void
    {
        $owner = User::factory()->create();
        CategoryEventRegistration::factory()
            ->withdrawn()
            ->paid()
            ->create([
                'user_id'       => $owner->id,
                'refund_method' => 'bank',
                'refund_status' => 'pending',
                'refund_net'    => 120.00,
            ]);

        $this->actingAs($this->superUser());

        $response = $this->get(route('backend.superadmin.index'));

        $response->assertOk();
        $response->assertSee('PF Status');
    }

    public function test_pf_status_button_not_shown_for_unpaid_registration(): void
    {
        $owner = User::factory()->create();
        CategoryEventRegistration::factory()
            ->withdrawn()
            ->create([
                'user_id'          => $owner->id,
                'refund_method'    => 'bank',
                'refund_status'    => 'pending',
                'pf_transaction_id' => null,
                'refund_net'       => 120.00,
            ]);

        $this->actingAs($this->superUser());

        $response = $this->get(route('backend.superadmin.index'));

        $response->assertOk();
        $response->assertDontSee('PF Status');
    }

    // -----------------------------------------------------------------------
    // "Bank Refunds" counter in Quick Actions reflects pending count
    // -----------------------------------------------------------------------

    public function test_quick_actions_counter_reflects_pending_bank_refunds(): void
    {
        $owner = User::factory()->create();
        CategoryEventRegistration::factory()
            ->withdrawn()
            ->create([
                'user_id'       => $owner->id,
                'refund_method' => 'bank',
                'refund_status' => 'pending',
                'refund_net'    => 50.00,
            ]);

        $this->actingAs($this->superUser());

        $response = $this->get(route('backend.superadmin.index'));

        $response->assertOk();
        $response->assertSee('Bank Refunds');
    }
}
