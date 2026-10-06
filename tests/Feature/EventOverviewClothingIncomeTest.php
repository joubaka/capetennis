<?php

namespace Tests\Feature;

use App\Http\Controllers\Backend\EventAdminController;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventOverviewClothingIncomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_keeps_clothing_separate_and_adds_net_income_once(): void
    {
        $event = Event::factory()->create();
        $clothing = ['count' => 2, 'groups' => collect(), 'totals' => ['gross' => 150.00, 'fees' => -5.00, 'net' => 145.00]];
        $method = new \ReflectionMethod(EventAdminController::class, 'buildFinanceData');
        $data = $method->invoke(app(EventAdminController::class), $event, [
            'gross_payments' => 1150.00,
            'registration_received' => 1000.00,
            'pf_fees' => -35.00,
            'cape_fees' => -15.00,
            'registration_net' => 855.00,
            'total_entries' => 1,
        ], $clothing);

        $this->assertEquals(1000.00, $data['totalGross']);
        $this->assertEquals(-30.00, $data['totalPayfastFees']);
        $this->assertEquals(855.00, $data['netRegistrationIncome']);
        $this->assertEquals(-100.00, $data['registrationRefundAdjustment']);
        $this->assertEquals(1000.00, $data['grandTotalIncome']);
        $this->assertEquals(1000.00, $data['netProfit']);
        $this->assertSame($clothing, $data['clothingReceipts']);
        $this->assertDatabaseCount('event_expenses', 0);
    }
}
