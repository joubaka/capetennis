<?php

namespace Tests\Feature;

use App\Http\Controllers\Backend\EventAdminController;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventOverviewClothingIncomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_overview_excludes_clothing_and_other_income_from_registration_profit(): void
    {
        $event = Event::factory()->create();
        \App\Models\EventIncomeItem::create(['event_id' => $event->id, 'label' => 'Sponsorship', 'total' => 500]);
        \App\Models\EventExpense::create(['event_id' => $event->id, 'expense_type' => 'Equipment', 'amount' => 100]);
        // A previously combined persisted fee must not inflate registration reconciliation.
        \App\Models\EventExpense::create(['event_id' => $event->id, 'expense_type' => 'payfast', 'amount' => 35]);
        $clothing = ['count' => 2, 'groups' => collect(), 'totals' => [
            'gross' => 150.00, 'fees' => -5.00, 'net' => 125.00,
            'completed_refund_net' => 20.00, 'pending_refund_net' => 10.00,
        ]];
        $method = new \ReflectionMethod(EventAdminController::class, 'buildFinanceData');
        $data = $method->invoke(app(EventAdminController::class), $event, [
            'gross_payments' => 1150.00,
            'registration_received' => 1000.00,
            'pf_fees' => -35.00,
            'cape_fees' => -15.00,
            'registration_net' => 855.00,
            'total_entries' => 1,
            'registration_pending_refund_net' => 25.00,
        ], $clothing);

        $this->assertEquals(1000.00, $data['totalGross']);
        $this->assertEquals(-30.00, $data['totalPayfastFees']);
        $this->assertEquals(855.00, $data['netRegistrationIncome']);
        $this->assertEquals(-100.00, $data['registrationRefundAdjustment']);
        $this->assertEquals(855.00, $data['grandTotalIncome']);
        $this->assertEquals(755.00, $data['netProfit']);
        $this->assertEquals(500.00, $data['totalIncomeItems']);
        $this->assertEquals(45.00, $data['totalSystemFees']);
        $this->assertSame($clothing, $data['clothingReceipts']);
        $this->assertDatabaseCount('event_expenses', 2);

        $html = view('backend.event.partials.finances', ['event' => $event] + $data)->render();
        $this->assertStringContainsString('Registration reconciliation', $html);
        $this->assertStringContainsString('Regional clothing income and reconciliation', $html);
        $this->assertStringContainsString('Pending registration refund liability: R 25.00', $html);
        $this->assertStringNotContainsString('Gross Clothing Income', $html);
    }
}
