<?php

namespace Tests\Unit;

use App\Services\Payfast;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Unit tests for Payfast::refundQuery() and the shared buildApiHeaders() helper.
 *
 * All outbound HTTP calls are mocked via Http::fake().
 */
class PayfastServiceTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makePayfast(): Payfast
    {
        $payfast = new Payfast();
        $payfast->setMode(1); // live
        return $payfast;
    }

    // -----------------------------------------------------------------------
    // refundQuery — success
    // -----------------------------------------------------------------------

    public function test_refund_query_returns_success_on_200_response(): void
    {
        Http::fake([
            'api.payfast.co.za/refunds/query/*' => Http::response(
                ['status' => 'complete', 'amount' => '100.00'],
                200
            ),
        ]);

        $payfast = $this->makePayfast();
        $result  = $payfast->refundQuery('PF-12345');

        $this->assertTrue($result['success']);
        $this->assertNull($result['error']);
        $this->assertIsArray($result['data']);
        $this->assertEquals('complete', $result['data']['status']);
    }

    // -----------------------------------------------------------------------
    // refundQuery — non-2xx
    // -----------------------------------------------------------------------

    public function test_refund_query_returns_failure_on_404_response(): void
    {
        Http::fake([
            'api.payfast.co.za/refunds/query/*' => Http::response(
                ['message' => 'Not found'],
                404
            ),
        ]);

        $payfast = $this->makePayfast();
        $result  = $payfast->refundQuery('UNKNOWN-ID');

        $this->assertFalse($result['success']);
        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('404', $result['error']);
    }

    public function test_refund_query_returns_failure_on_500_response(): void
    {
        Http::fake([
            'api.payfast.co.za/refunds/query/*' => Http::response('Server error', 500),
        ]);

        $payfast = $this->makePayfast();
        $result  = $payfast->refundQuery('PF-ABC');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('500', $result['error']);
    }

    // -----------------------------------------------------------------------
    // refundQuery — network exception
    // -----------------------------------------------------------------------

    public function test_refund_query_returns_failure_on_connection_exception(): void
    {
        Http::fake(function () {
            throw new \RuntimeException('Connection timed out');
        });

        Log::shouldReceive('error')->once();
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('debug')->zeroOrMoreTimes();

        $payfast = $this->makePayfast();
        $result  = $payfast->refundQuery('PF-TIMEOUT');

        $this->assertFalse($result['success']);
        $this->assertNull($result['data']);
        $this->assertStringContainsString('Connection timed out', $result['error']);
    }

    // -----------------------------------------------------------------------
    // refundQuery — correct URL constructed
    // -----------------------------------------------------------------------

    public function test_refund_query_hits_correct_endpoint(): void
    {
        Http::fake([
            'api.payfast.co.za/refunds/query/PF-XYZ' => Http::response(['status' => 'pending'], 200),
        ]);

        $payfast = $this->makePayfast();
        $result  = $payfast->refundQuery('PF-XYZ');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.payfast.co.za/refunds/query/PF-XYZ');
        });

        $this->assertTrue($result['success']);
    }

    // -----------------------------------------------------------------------
    // refundQuery — headers are present
    // -----------------------------------------------------------------------

    public function test_refund_query_sends_required_headers(): void
    {
        Http::fake([
            'api.payfast.co.za/refunds/query/*' => Http::response(['status' => 'complete'], 200),
        ]);

        $payfast = $this->makePayfast();
        $payfast->refundQuery('PF-HDR');

        Http::assertSent(function ($request) {
            return $request->hasHeader('merchant-id')
                && $request->hasHeader('signature')
                && $request->hasHeader('timestamp')
                && $request->hasHeader('version');
        });
    }

    // -----------------------------------------------------------------------
    // refundQuery — return shape
    // -----------------------------------------------------------------------

    public function test_refund_query_always_returns_three_keys(): void
    {
        Http::fake([
            'api.payfast.co.za/refunds/query/*' => Http::response([], 200),
        ]);

        $payfast = $this->makePayfast();
        $result  = $payfast->refundQuery('PF-SHAPE');

        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('error', $result);
    }

    // -----------------------------------------------------------------------
    // refund() — verify it still works (regression) after buildApiHeaders refactor
    // -----------------------------------------------------------------------

    public function test_refund_still_works_after_headers_refactor(): void
    {
        Http::fake([
            'api.payfast.co.za/refunds/PF-REFUND-99' => Http::response(['success' => true], 200),
        ]);

        $payfast = $this->makePayfast();
        $result  = $payfast->refund('PF-REFUND-99', 150.00, 'Test refund');

        $this->assertTrue($result['success']);
        $this->assertNull($result['error']);
    }

    public function test_refund_sends_required_headers_after_refactor(): void
    {
        Http::fake([
            'api.payfast.co.za/refunds/PF-REFUND-HDR' => Http::response(['success' => true], 200),
        ]);

        $payfast = $this->makePayfast();
        $payfast->refund('PF-REFUND-HDR', 50.00);

        Http::assertSent(function ($request) {
            return $request->hasHeader('merchant-id')
                && $request->hasHeader('signature')
                && $request->hasHeader('timestamp')
                && $request->hasHeader('version');
        });
    }

    public function test_partial_bank_payout_refund_includes_complete_bank_details(): void
    {
        Http::fake([
            'api.payfast.co.za/refunds/query/PF-BANK' => Http::response([
                'status' => 'REFUNDABLE',
                'amount_available_for_refund' => 28500,
                'refund_full' => ['method' => 'PAYMENT_SOURCE'],
                'refund_partial' => ['method' => 'BANK_PAYOUT'],
                'bank_names' => [
                    ['bank_name' => 'STANDARD', 'label' => 'Standard Bank'],
                ],
            ]),
            'api.payfast.co.za/refunds/PF-BANK' => Http::response(['status' => 'success']),
        ]);

        $result = $this->makePayfast()->refundUsingAvailableMethod(
            'PF-BANK',
            256.50,
            'Event withdrawal refund',
            [
                'account_holder' => 'Test Player',
                'bank_name' => 'Standard Bank',
                'branch_code' => '051001',
                'account_number' => '0123456789',
                'account_type' => 'current',
            ]
        );

        $this->assertTrue($result['success']);
        Http::assertSent(function ($request) {
            if (!str_contains($request->url(), '/refunds/PF-BANK')) {
                return false;
            }

            return $request['amount'] === 25650
                && $request['bank_account_holder'] === 'Test Player'
                && $request['bank_name'] === 'STANDARD'
                && $request['bank_branch_code'] === '051001'
                && $request['bank_account_number'] === '0123456789'
                && $request['bank_account_type'] === 'current';
        });
    }

    public function test_payment_source_refund_does_not_send_bank_details(): void
    {
        Http::fake([
            'api.payfast.co.za/refunds/query/PF-SOURCE' => Http::response([
                'status' => 'REFUNDABLE',
                'amount_available_for_refund' => 10000,
                'refund_full' => ['method' => 'PAYMENT_SOURCE'],
                'refund_partial' => ['method' => 'PAYMENT_SOURCE'],
                'bank_names' => [],
            ]),
            'api.payfast.co.za/refunds/PF-SOURCE' => Http::response(['status' => 'success']),
        ]);

        $result = $this->makePayfast()->refundUsingAvailableMethod('PF-SOURCE', 100.00);

        $this->assertTrue($result['success']);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/refunds/PF-SOURCE')
                && !isset($request['bank_account_number']);
        });
    }

    public function test_bank_payout_stops_before_refund_when_details_are_incomplete(): void
    {
        Http::fake([
            'api.payfast.co.za/refunds/query/PF-MISSING' => Http::response([
                'status' => 'REFUNDABLE',
                'amount_available_for_refund' => 10000,
                'refund_full' => ['method' => 'BANK_PAYOUT'],
                'refund_partial' => ['method' => 'BANK_PAYOUT'],
                'bank_names' => [['bank_name' => 'FNB', 'label' => 'FNB']],
            ]),
        ]);

        $result = $this->makePayfast()->refundUsingAvailableMethod('PF-MISSING', 100.00, bankDetails: [
            'bank_name' => 'FNB',
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('complete bank details', $result['error']);
        Http::assertSentCount(1);
    }

    public function test_refund_rejects_amount_above_payfast_available_balance(): void
    {
        Http::fake([
            'api.payfast.co.za/refunds/query/PF-LIMIT' => Http::response([
                'status' => 'REFUNDABLE',
                'amount_available_for_refund' => 5000,
                'refund_full' => ['method' => 'PAYMENT_SOURCE'],
                'refund_partial' => ['method' => 'PAYMENT_SOURCE'],
            ]),
        ]);

        $result = $this->makePayfast()->refundUsingAvailableMethod('PF-LIMIT', 50.01);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('exceeds', $result['error']);
        Http::assertSentCount(1);
    }
}
