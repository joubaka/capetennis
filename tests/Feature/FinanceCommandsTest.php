<?php

namespace Tests\Feature;

use App\Models\RegistrationOrder;
use App\Models\TeamPaymentOrder;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Finance Reconciliation Commands – Test Suite
 *
 * Verifies all four artisan commands introduced during the financial refactor:
 *   - finance:reconcile-wallets
 *   - finance:detect-duplicates
 *   - finance:detect-negative-balances
 *   - finance:audit-payments
 */
class FinanceCommandsTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // finance:reconcile-wallets
    // =========================================================================

    public function test_reconcile_wallets_returns_success_when_all_balanced(): void
    {
        // All wallets with clean ledgers → SUCCESS (0)
        $wallet = Wallet::factory()->create();
        WalletTransaction::create([
            'wallet_id'   => $wallet->id,
            'type'        => 'credit',
            'amount'      => 100,
            'source_type' => 'test',
            'source_id'   => 1,
            'meta'        => [],
        ]);

        // The accessor-based balance equals the ledger sum → no mismatch
        $this->artisan('finance:reconcile-wallets')->assertExitCode(0);
    }

    public function test_reconcile_wallets_returns_success_with_no_wallets(): void
    {
        $this->artisan('finance:reconcile-wallets')->assertExitCode(0);
    }

    // =========================================================================
    // finance:detect-duplicates
    // =========================================================================

    public function test_detect_duplicates_returns_success_with_no_records(): void
    {
        $this->artisan('finance:detect-duplicates')->assertExitCode(0);
    }

    public function test_detect_duplicates_returns_failure_when_wallet_transaction_duplicate_exists(): void
    {
        $wallet = Wallet::factory()->create();

        // Two transactions with the same (wallet_id, source_type, source_id)
        // which would indicate a duplicate ledger entry
        $sharedData = [
            'wallet_id'   => $wallet->id,
            'type'        => 'credit',
            'amount'      => 50,
            'source_type' => 'order',
            'source_id'   => 99,
            'meta'        => [],
        ];
        WalletTransaction::create($sharedData);
        WalletTransaction::create($sharedData);

        $this->artisan('finance:detect-duplicates')->assertExitCode(1);
    }

    public function test_detect_duplicates_returns_failure_when_pf_transaction_duplicate_exists(): void
    {
        $this->markTestSkipped('The database unique constraint now prevents duplicate PayFast IDs.');

        \DB::table('transactions_pf')->insert(['pf_payment_id' => 'PF-DUP-001', 'created_at' => now(), 'updated_at' => now()]);
        \DB::table('transactions_pf')->insert(['pf_payment_id' => 'PF-DUP-001', 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('finance:detect-duplicates')->assertExitCode(1);
    }

    public function test_detect_duplicates_returns_success_with_distinct_pf_ids(): void
    {
        \DB::table('transactions_pf')->insert(['pf_payment_id' => 'PF-001', 'created_at' => now(), 'updated_at' => now()]);
        \DB::table('transactions_pf')->insert(['pf_payment_id' => 'PF-002', 'created_at' => now(), 'updated_at' => now()]);

        $this->artisan('finance:detect-duplicates')->assertExitCode(0);
    }

    public function test_detect_duplicates_reports_team_payment_order_duplicates(): void
    {
        $user = User::factory()->create();

        // Two team payment orders for the same (team_id, player_id, event_id)
        TeamPaymentOrder::create([
            'user_id'            => $user->id,
            'team_id'            => 1,
            'player_id'          => 1,
            'event_id'           => 1,
            'total_amount'       => 100,
            'wallet_reserved'    => 0,
            'payfast_amount_due' => 100,
        ]);
        TeamPaymentOrder::create([
            'user_id'            => $user->id,
            'team_id'            => 1,
            'player_id'          => 1,
            'event_id'           => 1,
            'total_amount'       => 100,
            'wallet_reserved'    => 0,
            'payfast_amount_due' => 100,
        ]);

        $this->artisan('finance:detect-duplicates')->assertExitCode(1);
    }

    // =========================================================================
    // finance:detect-negative-balances
    // =========================================================================

    public function test_detect_negative_balances_returns_success_when_no_wallets(): void
    {
        $this->artisan('finance:detect-negative-balances')->assertExitCode(0);
    }

    public function test_detect_negative_balances_returns_success_when_all_positive(): void
    {
        $wallet = Wallet::factory()->create();
        WalletTransaction::create([
            'wallet_id'   => $wallet->id,
            'type'        => 'credit',
            'amount'      => 100,
            'source_type' => 'top_up',
            'source_id'   => 1,
            'meta'        => [],
        ]);

        $this->artisan('finance:detect-negative-balances')->assertExitCode(0);
    }

    public function test_detect_negative_balances_returns_failure_when_negative_balance_exists(): void
    {
        $wallet = Wallet::factory()->create();

        // Manually insert a debit exceeding credits to create a negative balance
        // (bypassing WalletService intentionally to test the command's detection)
        WalletTransaction::create([
            'wallet_id'   => $wallet->id,
            'type'        => 'credit',
            'amount'      => 10,
            'source_type' => 'top_up',
            'source_id'   => 1,
            'meta'        => [],
        ]);
        WalletTransaction::create([
            'wallet_id'   => $wallet->id,
            'type'        => 'debit',
            'amount'      => 50,   // 50 > 10 → negative balance
            'source_type' => 'order',
            'source_id'   => 2,
            'meta'        => [],
        ]);

        $this->artisan('finance:detect-negative-balances')->assertExitCode(1);
    }

    public function test_detect_negative_balances_returns_success_when_balance_is_exactly_zero(): void
    {
        $wallet = Wallet::factory()->create();

        WalletTransaction::create([
            'wallet_id'   => $wallet->id,
            'type'        => 'credit',
            'amount'      => 50,
            'source_type' => 'top_up',
            'source_id'   => 1,
            'meta'        => [],
        ]);
        WalletTransaction::create([
            'wallet_id'   => $wallet->id,
            'type'        => 'debit',
            'amount'      => 50,
            'source_type' => 'order',
            'source_id'   => 2,
            'meta'        => [],
        ]);

        // Balance is 0 — not negative → SUCCESS
        $this->artisan('finance:detect-negative-balances')->assertExitCode(0);
    }

    // =========================================================================
    // finance:audit-payments
    // =========================================================================

    public function test_audit_payments_returns_success_with_no_orders(): void
    {
        $this->artisan('finance:audit-payments')->assertExitCode(0);
    }

    public function test_audit_payments_returns_success_for_clean_orders(): void
    {
        // A cleanly-paid order: pay_status=1, payfast_paid=1, pf_payment_id set
        RegistrationOrder::create([
            'user_id'               => User::factory()->create()->id,
            'wallet_reserved'       => 0,
            'wallet_debited'        => false,
            'payfast_paid'          => true,
            'payfast_pf_payment_id' => 'PF-GOOD',
            'payfast_amount_due'    => 100,
            'pay_status'            => true,
        ]);

        $this->artisan('finance:audit-payments')->assertExitCode(0);
    }

    public function test_audit_payments_detects_paid_without_gateway_or_wallet(): void
    {
        // pay_status=1 but payfast_paid=0 AND wallet_reserved=0 → issue
        RegistrationOrder::create([
            'user_id'               => User::factory()->create()->id,
            'wallet_reserved'       => 0,
            'wallet_debited'        => false,
            'payfast_paid'          => false,
            'payfast_pf_payment_id' => null,
            'payfast_amount_due'    => 0,
            'pay_status'            => true, // paid but no gateway and no wallet → suspicious
        ]);

        $this->artisan('finance:audit-payments')->assertExitCode(1);
    }

    public function test_audit_payments_detects_paid_with_reserved_wallet_not_debited(): void
    {
        // wallet_reserved > 0, pay_status=1, but wallet_debited=0 → issue
        RegistrationOrder::create([
            'user_id'               => User::factory()->create()->id,
            'wallet_reserved'       => 50,
            'wallet_debited'        => false,  // should have been debited
            'payfast_paid'          => true,
            'payfast_pf_payment_id' => 'PF-X',
            'payfast_amount_due'    => 50,
            'pay_status'            => true,
        ]);

        $this->artisan('finance:audit-payments')->assertExitCode(1);
    }

    public function test_audit_payments_detects_payfast_paid_without_pf_reference(): void
    {
        // payfast_paid=1, payfast_amount_due > 0, but pf_payment_id is null → issue
        RegistrationOrder::create([
            'user_id'               => User::factory()->create()->id,
            'wallet_reserved'       => 0,
            'wallet_debited'        => false,
            'payfast_paid'          => true,
            'payfast_pf_payment_id' => null,   // missing PF reference
            'payfast_amount_due'    => 100,
            'pay_status'            => true,
        ]);

        $this->artisan('finance:audit-payments')->assertExitCode(1);
    }

    public function test_audit_payments_detects_team_order_completed_refund_with_zero_net(): void
    {
        TeamPaymentOrder::create([
            'user_id'            => User::factory()->create()->id,
            'total_amount'       => 100,
            'wallet_reserved'    => 0,
            'payfast_amount_due' => 100,
            'payfast_paid'       => true,
            'pay_status'         => true,
            'refund_status'      => 'completed',
            'refund_net'         => 0, // completed refund with zero net → suspicious
        ]);

        $this->artisan('finance:audit-payments')->assertExitCode(1);
    }
}
