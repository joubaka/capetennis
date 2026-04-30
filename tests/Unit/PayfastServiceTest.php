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
            'api.payfast.co.za/refunds' => Http::response(['success' => true], 200),
        ]);

        $payfast = $this->makePayfast();
        $result  = $payfast->refund('PF-REFUND-99', 150.00, 'Test refund');

        $this->assertTrue($result['success']);
        $this->assertNull($result['error']);
    }

    public function test_refund_sends_required_headers_after_refactor(): void
    {
        Http::fake([
            'api.payfast.co.za/refunds' => Http::response(['success' => true], 200),
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
}
