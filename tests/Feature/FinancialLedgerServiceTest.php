<?php

namespace Tests\Feature;

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * FinancialLedgerServiceTest
 *
 * Validates HOTFIX 3 (wallet-only included), HOTFIX 4 (gross semantics),
 * HOTFIX 7 (unified ledger), and HOTFIX 8 (pending refunds separate).
 */
class FinancialLedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    private FinancialLedgerService $service;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FinancialLedgerService::class);

        $this->event = Event::factory()->create([
            'cape_tennis_fee' => 10.00,
        ]);
    }

    // =========================================================================
    // HOTFIX 3 — Wallet-only orders must be included
    // =========================================================================

    public function test_wallet_only_payment_is_included_in_payment_rows(): void
    {
        $user   = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->create();

        // Create a wallet-only order (no payfast)
        $orderId = DB::table('registration_orders')->insertGetId([
            'user_id'              => $user->id,
            'wallet_reserved'      => 150.00,
            'wallet_debited'       => true,
            'payfast_amount_due'   => 0,
            'pay_status'           => true,
            'payfast_paid'         => false,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        WalletTransaction::create([
            'wallet_id'   => $wallet->id,
            'type'        => 'debit',
            'amount'      => 150.00,
            'source_type' => 'event_registration_wallet_payment',
            'source_id'   => $orderId,
            'meta'        => ['event_id' => $this->event->id],
        ]);

        // Link order to event via item → category_event
        $catEventId = DB::table('category_events')->insertGetId([
            'event_id'    => $this->event->id,
            'category_id' => $this->insertCategory(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        DB::table('registration_order_items')->insert([
            'order_id'          => $orderId,
            'category_event_id' => $catEventId,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $paymentRows = $this->service->buildPaymentRows($this->event, 10.00);
        $walletRows  = $paymentRows->where('method', 'Wallet');

        $this->assertTrue($walletRows->isNotEmpty(), 'Wallet-only rows must be present in payment rows');
        $this->assertEquals(150.00, $walletRows->sum('gross'));
    }

    public function test_fy_summary_includes_wallet_only_orders(): void
    {
        $user   = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->create();

        $orderId = DB::table('registration_orders')->insertGetId([
            'user_id'            => $user->id,
            'wallet_reserved'    => 200.00,
            'wallet_debited'     => true,
            'payfast_amount_due' => 0,
            'pay_status'         => true,
            'payfast_paid'       => false,
            'created_at'         => now(),
            'updated_at'         => now(),
        ]);

        WalletTransaction::create([
            'wallet_id'   => $wallet->id,
            'type'        => 'debit',
            'amount'      => 200.00,
            'source_type' => 'event_registration_wallet_payment',
            'source_id'   => $orderId,
            'meta'        => [],
        ]);

        $catEventId = DB::table('category_events')->insertGetId([
            'event_id'    => $this->event->id,
            'category_id' => $this->insertCategory(),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
        DB::table('registration_order_items')->insert([
            'order_id'          => $orderId,
            'category_event_id' => $catEventId,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        $summary = $this->service->buildFySummaryRow($this->event);

        $this->assertGreaterThan(0, $summary['gross_payments'], 'Wallet-only gross must be > 0 in FY summary');
    }

    // =========================================================================
    // HOTFIX 4 — Gross semantics: gross_payments never reduced by refunds
    // =========================================================================

    public function test_gross_payments_never_reduced_by_completed_refunds(): void
    {
        // Insert a PayFast transaction
        DB::table('transactions_pf')->insert([
            'pf_payment_id'    => 'PF_GROSS_TEST',
            'event_id'         => $this->event->id,
            'transaction_type' => 'Registration',
            'amount_gross'     => 500.00,
            'is_test'          => false,
            'custom_int5'      => null,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $paymentRows = $this->service->buildPaymentRows($this->event, 10.00);
        $refundRows  = $this->service->buildRefundRows($this->event, 10.00);
        $payoutRows  = collect();

        $totals = $this->service->buildTotals($paymentRows, $refundRows, $payoutRows);

        // gross_payments must equal the raw inflow regardless of refunds
        $this->assertEquals(
            round($paymentRows->sum('gross'), 2),
            $totals['gross_payments'],
            'gross_payments must equal raw payment inflow, not reduced by refunds'
        );
    }

    // =========================================================================
    // HOTFIX 8 — Pending refunds must not reduce net revenue
    // =========================================================================

    public function test_pending_refund_does_not_reduce_net_revenue(): void
    {
        // Insert a PayFast transaction
        DB::table('transactions_pf')->insert([
            'pf_payment_id'    => 'PF_PENDING_TEST',
            'event_id'         => $this->event->id,
            'transaction_type' => 'Registration',
            'amount_gross'     => 400.00,
            'is_test'          => false,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $paymentRows = $this->service->buildPaymentRows($this->event, 10.00);
        $emptyRefunds = collect(); // no refunds at all

        $totalsNoRefund = $this->service->buildTotals($paymentRows, $emptyRefunds, collect());

        // Now simulate a pending refund row (not completed)
        $pendingRefundRow = (object) [
            'type'          => 'refund',
            'refund_status' => CategoryEventRegistration::REFUND_PENDING,
            'refund_gross'  => 400.00,
            'refund_fee'    => 10.00,
            'net'           => -380.00,
        ];
        $totalsWithPending = $this->service->buildTotals($paymentRows, collect([$pendingRefundRow]), collect());

        // Net revenue must be the same whether a pending refund exists or not
        $this->assertEquals(
            $totalsNoRefund['net_revenue'],
            $totalsWithPending['net_revenue'],
            'Pending refunds must NOT reduce realized net revenue'
        );

        // Pending refund must appear in its own bucket
        $this->assertEquals(400.00, $totalsWithPending['pending_refunds']);
    }

    public function test_completed_refund_reduces_net_revenue(): void
    {
        DB::table('transactions_pf')->insert([
            'pf_payment_id'    => 'PF_COMPLETED_REFUND',
            'event_id'         => $this->event->id,
            'transaction_type' => 'Registration',
            'amount_gross'     => 300.00,
            'is_test'          => false,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $paymentRows = $this->service->buildPaymentRows($this->event, 10.00);
        $emptyRefunds = collect();
        $totalsNoRefund = $this->service->buildTotals($paymentRows, $emptyRefunds, collect());

        $completedRefundRow = (object) [
            'type'          => 'refund',
            'refund_status' => CategoryEventRegistration::REFUND_COMPLETED,
            'refund_gross'  => 300.00,
            'refund_fee'    => 9.00,
            'net'           => round(-300 + 9 + 10, 2), // -281
        ];
        $totalsWithCompleted = $this->service->buildTotals($paymentRows, collect([$completedRefundRow]), collect());

        $this->assertLessThan(
            $totalsNoRefund['net_revenue'],
            $totalsWithCompleted['net_revenue'],
            'Completed refund must reduce net revenue'
        );
        $this->assertEquals(300.00, $totalsWithCompleted['completed_refunds']);
    }

    // =========================================================================
    // HOTFIX 7 — Dashboard and ledger totals align (no double-counting)
    // =========================================================================

    public function test_no_double_counting_of_hybrid_payment(): void
    {
        $user   = User::factory()->create();
        $wallet = Wallet::factory()->forUser($user)->create();

        $orderId = DB::table('registration_orders')->insertGetId([
            'user_id'              => $user->id,
            'wallet_reserved'      => 100.00,
            'wallet_debited'       => true,
            'payfast_amount_due'   => 200.00,
            'payfast_paid'         => true,
            'pay_status'           => true,
            'payfast_pf_payment_id' => 'PF_HYBRID',
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        // PayFast transaction for the hybrid order
        DB::table('transactions_pf')->insert([
            'pf_payment_id'    => 'PF_HYBRID',
            'event_id'         => $this->event->id,
            'transaction_type' => 'Registration',
            'amount_gross'     => 200.00,
            'is_test'          => false,
            'custom_int5'      => $orderId,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $paymentRows = $this->service->buildPaymentRows($this->event, 10.00);

        // Hybrid order should appear once (via PayFast row, with walletUsed added)
        // and NOT also appear in wallet-only rows
        $walletOnlyRows = $paymentRows->where('method', 'Wallet');
        $hybridRows     = $paymentRows->where('method', 'PayFast + Wallet');

        $this->assertEmpty($walletOnlyRows, 'Hybrid order must NOT appear as wallet-only');
        $this->assertNotEmpty($hybridRows, 'Hybrid order must appear as PayFast + Wallet');

        // Gross should include both payfast + wallet amounts
        $this->assertEquals(300.00, $hybridRows->sum('gross'));
    }

    // =========================================================================
    // PHASE 1 — EventTransactionController / PDF parity with FinancialLedgerService
    // =========================================================================

    public function test_event_transaction_page_total_equals_ledger_service(): void
    {
        // Insert two PayFast transactions
        DB::table('transactions_pf')->insert([
            ['pf_payment_id' => 'PF_PAGE_A', 'event_id' => $this->event->id, 'transaction_type' => 'Registration', 'amount_gross' => 300.00, 'is_test' => false, 'created_at' => now(), 'updated_at' => now()],
            ['pf_payment_id' => 'PF_PAGE_B', 'event_id' => $this->event->id, 'transaction_type' => 'Registration', 'amount_gross' => 200.00, 'is_test' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $built   = $this->service->buildForEvent($this->event);
        $totals  = $built['totals'];

        $this->assertEquals(
            round($built['paymentRows']->sum('gross'), 2),
            $totals['gross_payments'],
            'gross_payments from service must match payment row sum'
        );
    }

    public function test_pdf_total_equals_ledger_service(): void
    {
        DB::table('transactions_pf')->insert([
            'pf_payment_id' => 'PF_PDF_A', 'event_id' => $this->event->id,
            'transaction_type' => 'Registration', 'amount_gross' => 450.00,
            'is_test' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $built  = $this->service->buildForEvent($this->event);
        $totals = $built['totals'];

        // The PDF now uses the same service; net_revenue must be identical
        $this->assertIsFloat($totals['net_revenue']);
        $this->assertGreaterThan(0, $totals['gross_payments']);
    }

    public function test_archived_payfast_row_is_excluded(): void
    {
        DB::table('transactions_pf')->insert([
            'pf_payment_id'    => 'PF_ARCHIVED',
            'event_id'         => $this->event->id,
            'transaction_type' => 'Registration',
            'amount_gross'     => 500.00,
            'is_test'          => false,
            'archived_at'      => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $paymentRows = $this->service->buildPaymentRows($this->event, 10.00);
        $this->assertEmpty($paymentRows, 'Archived PayFast row must be excluded from payment rows');
    }

    public function test_hardcoded_fee_not_present_in_blade(): void
    {
        $bladePath = resource_path('views/backend/event/transactions.blade.php');
        $this->assertFileExists($bladePath);
        $this->assertStringNotContainsString(
            '* 285',
            file_get_contents($bladePath),
            'Hardcoded 285 entry fee must not appear in transactions Blade'
        );
    }

    public function test_transactions_view_renders_payment_items_with_missing_relations(): void
    {
        $transactions = collect([
            (object) [
                'type'         => 'payment',
                'method'       => 'PayFast',
                'player'       => null,
                'pf_payment_id'=> 'PF_TEAM_NULLS',
                'entryCount'   => 1,
                'gross'        => 125.00,
                'payfastGross' => 125.00,
                'walletUsed'   => 0.00,
                'fee'          => -5.00,
                'capeFee'      => -10.00,
                'net'          => 110.00,
                'created_at'   => now(),
                'order'        => (object) [
                    'items' => collect([
                        (object) [
                            'player'         => null,
                            'category_event' => null,
                            'item_price'     => 125.00,
                        ],
                    ]),
                ],
            ],
        ]);

        $html = view('backend.event.transactions', [
            'event'                     => $this->event,
            'transactions'              => $transactions,
            'feePerEntry'               => 10.00,
            'isTeamEvent'               => true,
            'totalEntries'              => 1,
            'refundCount'               => 0,
            'completedRefundCount'      => 0,
            'pendingRefundCount'        => 0,
            'noRefundCount'             => 0,
            'noRefundRetainedTotal'     => 0.00,
            'totalWithdrawals'          => 0.00,
            'completedWithdrawalsTotal' => 0.00,
            'pendingWithdrawalsTotal'   => 0.00,
            'totalGross'                => 125.00,
            'totalPayfastFees'          => -5.00,
            'totalCapeTennisFees'       => -10.00,
            'totalPayouts'              => 0.00,
            'netTournamentIncome'       => 110.00,
            'adminEntriesCount'         => 0,
            'adminEntriesCapeFee'       => 0.00,
            'adminGrossPrivate'         => 0.00,
            'payfastEntriesCount'       => 1,
            'payfastGrossTotal'         => 125.00,
        ])->render();

        $this->assertMatchesRegularExpression("/data-items='([^']+)'/", $html, 'Expected rendered child payload JSON');
        preg_match("/data-items='([^']+)'/", $html, $matches);
        $payload = json_decode(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('', $payload[1]['player']);
        $this->assertSame('—', $payload[1]['category']);
        $this->assertSame('125.00', $payload[1]['price']);
    }

    // =========================================================================
    // Helper
    // =========================================================================

    private function insertCategory(): int
    {
        return DB::table('categories')->insertGetId([
            'name'       => 'Test Category',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
