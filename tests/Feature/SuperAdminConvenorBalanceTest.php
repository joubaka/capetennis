<?php

namespace Tests\Feature;

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\Event;
use App\Models\EventPayout;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminConvenorBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_convenor_amount_deducts_fees_refunds_and_event_payouts_once_until_fully_paid(): void
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('super-user');
        $event = Event::factory()->create(['start_date' => '2026-06-01']);
        $otherEvent = Event::factory()->create(['start_date' => '2025-06-01']);

        // Exercise real summary/totals with canonical, already-normalized ledger rows.
        $ledger = $this->partialMock(FinancialLedgerService::class);
        $ledger->shouldReceive('buildPaymentRows')->andReturn(collect([
            (object) ['type' => 'payment', 'gross' => 1000, 'fee' => -30, 'capeFee' => -20, 'net' => 950, 'entryCount' => 1],
            (object) ['type' => 'clothing_payment', 'gross' => 500, 'fee' => -15, 'capeFee' => 0, 'net' => 485, 'entryCount' => 0],
            (object) ['type' => 'payment', 'subtype' => 'superseded_payfast_receipt', 'gross' => 300, 'fee' => -9, 'capeFee' => 0, 'net' => 291, 'entryCount' => 0],
        ]));
        $ledger->shouldReceive('buildRefundRows')->andReturn(collect([
            (object) ['type' => 'refund', 'subtype' => 'registration_refund', 'refund_status' => 'completed', 'refund_gross' => 100, 'net' => -90],
            (object) ['type' => 'refund', 'subtype' => 'registration_refund', 'refund_status' => 'pending', 'refund_gross' => 40, 'net' => -40],
        ]));

        EventPayout::create(['event_id' => $otherEvent->id, 'amount' => 10000, 'paid_by' => $admin->id, 'paid_at' => now()]);
        EventPayout::create(['event_id' => $event->id, 'amount' => 200, 'paid_by' => $admin->id, 'paid_at' => now()]);
        $this->actingAs($admin)->get(route('superadmin.finances', ['fy' => '2026']))
            ->assertOk()
            ->assertSee('Available to convenors')
            ->assertSee('Owed to convenors')
            ->assertSee('PayFast fees, Cape Tennis fees, completed refunds and recorded payouts')
            ->assertSee('Zero means fully paid')
            ->assertViewHas('financeSummary', fn ($summary) =>
                $summary['registration_received'] === 1300.0
                && $summary['registration_net'] === 860.0
                && $summary['registration_paid_out'] === 200.0
                && $summary['registration_balance'] === 660.0
                && $summary['clothing_balance'] === 485.0);

        EventPayout::create(['event_id' => $event->id, 'amount' => 660, 'paid_by' => $admin->id, 'paid_at' => now()]);
        $this->get(route('superadmin.finances', ['fy' => '2026']))->assertOk()
            ->assertViewHas('financeSummary', fn ($summary) => $summary['registration_balance'] === 0.0);

        EventPayout::create(['event_id' => $event->id, 'amount' => 10, 'paid_by' => $admin->id, 'paid_at' => now()]);
        $this->get(route('superadmin.finances', ['fy' => '2026']))->assertOk()
            ->assertViewHas('financeSummary', fn ($summary) => $summary['registration_balance'] === -10.0);
        $this->assertDatabaseCount('event_payouts', 4);
    }
}
