<?php

namespace Tests\Unit;

use App\Domain\Payments\Services\PaymentTransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTransactionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_payfast_receipt_is_written_once_for_repeated_itn(): void
    {
        $service = app(PaymentTransactionService::class);
        $data = [
            'pf_payment_id' => 'PF-REGRESSION-001',
            'amount_gross' => 285.00,
            'custom_int1' => 12,
            'custom_int2' => 34,
            'custom_int3' => 56,
            'custom_int4' => 78,
            'custom_int5' => 90,
            'item_name' => 'Regression event',
        ];

        $service->record($data, null);
        $service->record(array_merge($data, ['amount_gross' => 285.00]), null);

        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseHas('transactions_pf', [
            'pf_payment_id' => 'PF-REGRESSION-001',
            'transaction_type' => 'Registration',
            'amount_gross' => 285.00,
            'event_id' => 56,
            'custom_int5' => 90,
        ]);
    }
}
