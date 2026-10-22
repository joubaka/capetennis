<?php

namespace Tests\Feature;

use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Feature tests for the lightweight Super Admin landing page and its
 * bank-refund attention summary.
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

        $response->assertOk()
            ->assertSee('Needs attention')
            ->assertSee('Platform pulse')
            ->assertSee('Workspaces');
    }

    public function test_event_quick_actions_link_to_the_dashboard_event_list(): void
    {
        $response = $this->actingAs($this->superUser())
            ->get(route('backend.superadmin.index'));

        $response->assertOk()
            ->assertSee('href="'.route('backend.dashboard').'#tab-events"', false)
            ->assertDontSee('href="'.url('backend/event').'"', false);
    }

    public function test_landing_page_does_not_build_event_ledgers(): void
    {
        Event::factory()->create(['start_date' => now()->startOfYear()->toDateString()]);
        $this->mock(FinancialLedgerService::class)->shouldNotReceive('buildFySummaryRow');

        $this->actingAs($this->superUser())->get(route('backend.superadmin.index'))
            ->assertOk()
            ->assertSee('data-balance-value', false)
            ->assertSee('Retry balance')
            ->assertSee('View balance in Finance');
    }

    public function test_balance_endpoint_requires_super_user(): void
    {
        $this->getJson(route('backend.superadmin.balance'))->assertUnauthorized();
        $this->actingAs(User::factory()->create())
            ->getJson(route('backend.superadmin.balance'))->assertForbidden();
    }

    public function test_balance_uses_canonical_current_year_summaries_and_is_not_cached(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 15));
        $first = Event::factory()->create(['start_date' => '2026-01-01']);
        $last = Event::factory()->create(['start_date' => '2026-12-31']);
        $otherEvents = Event::factory()->count(24)->create(['start_date' => '2026-06-01']);
        Event::factory()->create(['start_date' => '2025-12-31']);
        Event::factory()->create(['start_date' => '2027-01-01']);
        $ledger = $this->mock(FinancialLedgerService::class);
        $ledger->shouldReceive('buildFySummaryRow')->once()->withArgs(fn (Event $event) => $event->is($first))->andReturn(['balance' => 123.45]);
        $ledger->shouldReceive('buildFySummaryRow')->once()->withArgs(fn (Event $event) => $event->is($last))->andReturn(['balance' => -200.55]);
        $ledger->shouldReceive('buildFySummaryRow')->times(24)->withArgs(fn (Event $event) => $otherEvents->contains('id', $event->id))->andReturn(['balance' => 0]);

        $response = $this->actingAs($this->superUser())->getJson(route('backend.superadmin.balance'));
        $response->assertOk()->assertExactJson(['year' => 2026, 'balance' => '-77.10', 'formatted' => 'R -77.10']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    }

    public function test_ordinary_user_cannot_change_platform_settings(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('settings.store.single'), [
            'key' => 'registration_open',
            'value' => '0',
        ])->assertForbidden();
    }

    public function test_payfast_diagnostic_redacts_credentials_outside_production(): void
    {
        config()->set('services.payfast.sandbox', true);
        config()->set('services.payfast.passphrase_sandbox', 'DO_NOT_EXPOSE_THIS_SECRET');
        config()->set('services.payfast.merchant_key_sandbox', 'DO_NOT_EXPOSE_THIS_KEY');

        $response = $this->actingAs($this->superUser())
            ->get(route('backend.superadmin.payfast-signature-check'));

        $response->assertOk()
            ->assertDontSee('DO_NOT_EXPOSE_THIS_SECRET')
            ->assertDontSee('DO_NOT_EXPOSE_THIS_KEY')
            ->assertSee('[REDACTED]');
    }

    // -----------------------------------------------------------------------
    // The attention queue is quiet when no supported operational issue exists
    // -----------------------------------------------------------------------

    public function test_dashboard_shows_no_urgent_items_when_attention_queues_are_clear(): void
    {
        $this->actingAs($this->superUser());

        $response = $this->get(route('backend.superadmin.index'));

        $response->assertOk();
        $response->assertSee('No urgent operational items');
    }

    // -----------------------------------------------------------------------
    // Pending bank refunds are summarized without exposing bank details
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

        $response->assertOk()
            ->assertSee('1 bank refund requires processing')
            ->assertSee(route('admin.registration.refunds.bank.index'), false)
            ->assertDontSee('Test Owner')
            ->assertDontSee('FNB')
            ->assertDontSee('1234567890');
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
        $response->assertSee('No urgent operational items');
    }

    // -----------------------------------------------------------------------
    // Provider controls stay in the specialist refund workspace
    // -----------------------------------------------------------------------

    public function test_paid_refund_is_summarized_without_provider_controls_on_landing_page(): void
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

        $response->assertOk()
            ->assertSee('1 bank refund requires processing')
            ->assertDontSee('PF Status');
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
    // Finance workspace reflects the canonical pending count
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

        $response->assertOk()
            ->assertSee('Bank refunds')
            ->assertSee('1 bank refund requires processing');
    }

    public function test_legacy_workspace_remains_super_user_only(): void
    {
        $this->get(route('backend.superadmin.workspace'))->assertRedirect(route('login'));

        $ordinaryUser = User::factory()->create();
        $this->actingAs($ordinaryUser)
            ->get(route('backend.superadmin.workspace'))
            ->assertForbidden();
    }
}
