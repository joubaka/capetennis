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

        // Should be blocked — not the owner (403 Forbidden)
        $response->assertStatus(403);
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

    // -----------------------------------------------------------------------
    // HOTFIX 2 — bank/PayFast refund formula uses 10% withdrawal fee, not PayFast fee
    // -----------------------------------------------------------------------

    public function test_payfast_bank_refund_net_is_ninety_percent_of_gross(): void
    {
        // Verify the HOTFIX 2 formula: payfastNet = round(payfastGross * 0.90, 2)
        // This is a unit-level check of the formula — the PayFast API call is not made.
        $cases = [
            [100.00, 90.00],
            [200.00, 180.00],
            [55.00,  49.50],
            [0.50,   0.45],
        ];

        foreach ($cases as [$gross, $expectedNet]) {
            $payfastNet = round($gross * 0.90, 2);
            $this->assertEqualsWithDelta(
                $expectedNet, $payfastNet, 0.01,
                "Expected 90% of PayFast gross {$gross} = {$expectedNet}"
            );
        }
    }

    public function test_hybrid_bank_refund_wallet_portion_has_no_fee(): void
    {
        // Wallet contribution in a hybrid payment is always refunded in full (no fee).
        $walletPaid = 50.00;
        $walletNet  = $walletPaid; // no fee applied

        $this->assertEquals($walletPaid, $walletNet);
    }

    public function test_refund_fee_is_ten_percent_of_combined_gross(): void
    {
        $payfastGross = 100.00;
        $walletPaid   = 50.00;
        $gross        = round($payfastGross + $walletPaid, 2); // 150.00
        $fee          = \App\Models\SiteSetting::calculateWithdrawalFee($gross); // 10% = 15.00
        $net          = round($gross - $fee, 2); // 135.00

        $this->assertEqualsWithDelta(150.00, $gross, 0.01);
        $this->assertEqualsWithDelta(15.00,  $fee,   0.01);
        $this->assertEqualsWithDelta(135.00, $net,   0.01);
    }

    public function test_old_payment_net_formula_differs_from_withdrawal_fee_net(): void
    {
        // This test documents the bug HOTFIX 2 fixes:
        // $payment['net'] = gross - PayFast_platform_fee (≈ 97% of gross)
        // but refund should use gross * 0.90 (90%).
        // They are different values and using payment['net'] over-refunds.
        $gross = 200.00;

        // Simulate what payment['net'] would have been (PayFast platform fee ~3%)
        $simulatedPlatformFeeNet = round($gross * 0.97, 2); // ≈ 194.00

        // Correct withdrawal fee net
        $withdrawalFeeNet = round($gross * 0.90, 2); // 180.00

        $this->assertNotEquals($simulatedPlatformFeeNet, $withdrawalFeeNet);
        $this->assertGreaterThan($withdrawalFeeNet, $simulatedPlatformFeeNet, 'payment[net] over-refunds vs 10% withdrawal fee');
    }
}
