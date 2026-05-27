<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tests for HOTFIX 1 — finance:dedupe-payfast-transactions
 * and HOTFIX 2 — finance:repair-wallet-reservations
 *
 * Note: We do NOT use RefreshDatabase here because the dedupe tests need to
 * execute DDL (ALTER TABLE DROP/ADD INDEX) which triggers an implicit commit
 * in MySQL and breaks RefreshDatabase's transaction-based rollback strategy.
 * Instead we truncate affected tables explicitly in tearDown.
 */
class FinancialDashboardIntegrityTest extends TestCase
{
    /** Tables that need truncation after each test. */
    private array $truncateTables = [
        'transactions_pf',
        'registration_orders',
        'wallet_transactions',
        'wallets',
        'users',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->restoreUniquePfIndex();
        $this->truncateTestTables();
    }

    protected function tearDown(): void
    {
        $this->truncateTestTables();
        $this->restoreUniquePfIndex();
        parent::tearDown();
    }

    private function truncateTestTables(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($this->truncateTables as $table) {
            try {
                DB::table($table)->truncate();
            } catch (\Exception $e) {
                // table may not exist in this environment
            }
        }
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function dropUniquePfIndex(): void
    {
        try {
            DB::statement('ALTER TABLE transactions_pf DROP INDEX transactions_pf_pf_payment_id_unique');
        } catch (\Exception $e) {
            // already absent
        }
    }

    private function restoreUniquePfIndex(): void
    {
        try {
            DB::statement('ALTER TABLE transactions_pf ADD UNIQUE INDEX transactions_pf_pf_payment_id_unique (pf_payment_id)');
        } catch (\Exception $e) {
            // duplicates still present; command should have cleaned them already
        }
    }

    // =========================================================================
    // HOTFIX 1 — Dedupe PayFast Transactions
    // =========================================================================

    public function test_dedupe_command_reports_all_clear_when_no_duplicates(): void
    {
        DB::table('transactions_pf')->insert([
            'pf_payment_id' => 'UNIQUE123',
            'amount_gross'  => 500.00,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->artisan('finance:dedupe-payfast-transactions --dry-run')
            ->assertExitCode(0)
            ->expectsOutput('✅ No duplicate pf_payment_id values found. Table is clean.');
    }

    public function test_dedupe_dry_run_detects_duplicates_without_mutation(): void
    {
        $this->dropUniquePfIndex();
        DB::table('transactions_pf')->insert([
            ['pf_payment_id' => 'DUPE_001', 'amount_gross' => 100, 'created_at' => now()->subMinutes(5), 'updated_at' => now()],
            ['pf_payment_id' => 'DUPE_001', 'amount_gross' => 100, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->artisan('finance:dedupe-payfast-transactions --dry-run')
            ->assertExitCode(0)
            ->expectsOutputToContain('DUPE_001');

        // Dry run must not mutate
        $this->assertEquals(2, DB::table('transactions_pf')->where('pf_payment_id', 'DUPE_001')->count());
    }

    public function test_dedupe_confirm_keeps_oldest_row_and_archives_duplicates(): void
    {
        $this->dropUniquePfIndex();
        DB::table('transactions_pf')->insert([
            ['pf_payment_id' => 'DUPE_002', 'amount_gross' => 200, 'created_at' => now()->subMinutes(10), 'updated_at' => now()],
            ['pf_payment_id' => 'DUPE_002', 'amount_gross' => 200, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->artisan('finance:dedupe-payfast-transactions --confirm')
            ->assertExitCode(0);

        // Only one row should still have the payment ID (the oldest, kept row)
        $this->assertEquals(1, DB::table('transactions_pf')
            ->where('pf_payment_id', 'DUPE_002')
            ->count());

        // One row should be archived (pf_payment_id nulled)
        $this->assertEquals(1, DB::table('transactions_pf')
            ->whereNull('pf_payment_id')
            ->whereNotNull('archived_at')
            ->count());
    }

    public function test_dedupe_confirm_keeps_the_row_with_the_lowest_id(): void
    {
        $this->dropUniquePfIndex();
        $oldId = DB::table('transactions_pf')->insertGetId([
            'pf_payment_id' => 'KEEP_ME',
            'amount_gross'  => 300,
            'created_at'    => now()->subMinutes(20),
            'updated_at'    => now(),
        ]);
        DB::table('transactions_pf')->insert([
            'pf_payment_id' => 'KEEP_ME',
            'amount_gross'  => 300,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $this->artisan('finance:dedupe-payfast-transactions --confirm')->assertExitCode(0);

        $survivor = DB::table('transactions_pf')->where('pf_payment_id', 'KEEP_ME')->first();
        $this->assertNotNull($survivor);
        $this->assertEquals($oldId, $survivor->id);
    }

    public function test_dedupe_requires_dry_run_or_confirm_flag(): void
    {
        $this->artisan('finance:dedupe-payfast-transactions')
            ->assertExitCode(1);
    }

    // =========================================================================
    // HOTFIX 2 — Wallet Reservation Repair
    // =========================================================================

    public function test_wallet_repair_reports_all_clear_when_no_corruption(): void
    {
        $this->artisan('finance:repair-wallet-reservations --dry-run')
            ->assertExitCode(0)
            ->expectsOutput('✅ No wallet reservation corruption found.');
    }

    public function test_wallet_repair_dry_run_detects_corrupted_order_without_mutation(): void
    {
        $user   = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->create();
        WalletTransaction::create([
            'wallet_id'   => $wallet->id,
            'type'        => 'credit',
            'amount'      => 500,
            'source_type' => 'test',
            'source_id'   => 999,
            'meta'        => [],
        ]);

        $orderId = DB::table('registration_orders')->insertGetId([
            'user_id'         => $user->id,
            'payfast_paid'    => true,
            'wallet_reserved' => 100.00,
            'wallet_debited'  => false,
            'pay_status'      => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->artisan('finance:repair-wallet-reservations --dry-run')
            ->assertExitCode(0);

        // Dry run must not mutate
        $order = DB::table('registration_orders')->find($orderId);
        $this->assertEquals(0, $order->wallet_debited);
    }

    public function test_wallet_repair_debits_wallet_when_balance_sufficient(): void
    {
        $user   = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->create();
        WalletTransaction::create([
            'wallet_id'   => $wallet->id,
            'type'        => 'credit',
            'amount'      => 500,
            'source_type' => 'test',
            'source_id'   => 998,
            'meta'        => [],
        ]);

        $orderId = DB::table('registration_orders')->insertGetId([
            'user_id'         => $user->id,
            'payfast_paid'    => true,
            'wallet_reserved' => 100.00,
            'wallet_debited'  => false,
            'pay_status'      => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->artisan('finance:repair-wallet-reservations --confirm')
            ->assertExitCode(0);

        $order = DB::table('registration_orders')->find($orderId);
        $this->assertEquals(1, $order->wallet_debited);

        // Wallet should now have a debit transaction
        $this->assertDatabaseHas('wallet_transactions', [
            'wallet_id'   => $wallet->id,
            'type'        => 'debit',
            'source_type' => 'event_registration_wallet_payment',
            'source_id'   => $orderId,
        ]);
    }

    public function test_wallet_repair_zeros_reservation_when_insufficient_balance(): void
    {
        $user   = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->create();
        // No credit → balance is 0, insufficient for 100

        $orderId = DB::table('registration_orders')->insertGetId([
            'user_id'         => $user->id,
            'payfast_paid'    => true,
            'wallet_reserved' => 100.00,
            'wallet_debited'  => false,
            'pay_status'      => true,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $this->artisan('finance:repair-wallet-reservations --confirm')
            ->assertExitCode(0);

        $order = DB::table('registration_orders')->find($orderId);
        $this->assertEquals(0, $order->wallet_reserved);
    }

    public function test_wallet_repair_requires_dry_run_or_confirm_flag(): void
    {
        $this->artisan('finance:repair-wallet-reservations')
            ->assertExitCode(1);
    }
}
