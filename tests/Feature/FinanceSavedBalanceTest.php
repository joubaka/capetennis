<?php

namespace Tests\Feature;

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\{CategoryEvent, CategoryEventRegistration, ClothingOrder, Event, EventPayout, RegionalClothingSettlement, TeamRegion, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinanceSavedBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_refunds_and_clothing_settlements_are_read_exactly_without_writes(): void
    {
        $event = Event::factory()->create(['cape_tennis_fee' => 99]);
        $other = Event::factory()->create();
        $user = User::factory()->create();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        foreach ([['completed', 270, 30], ['completed', 0, 300], ['pending', 75, 25], ['not_refunded', 400, 0]] as [$status, $net, $fee]) {
            CategoryEventRegistration::factory()->create(['category_event_id' => $category->id,
                'status' => 'withdrawn', 'withdrawn_at' => now(), 'refund_status' => $status,
                'refund_method' => 'bank', 'refund_gross' => 300, 'refund_net' => $net, 'refund_fee' => $fee]);
        }
        CategoryEventRegistration::factory()->create(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $other->id])->id,
            'status' => 'withdrawn', 'refund_status' => 'completed', 'refund_gross' => 999, 'refund_net' => 999]);
        ClothingOrder::create(['event_id' => $event->id, 'user_id' => $user->id, 'pay_status' => 1,
            'status' => 'completed', 'amount_paid' => 500, 'total' => 500, 'payfast_fee' => 15]);
        EventPayout::create(['event_id' => $event->id, 'amount' => 20, 'paid_by' => $user->id, 'paid_at' => now()]);
        $region = TeamRegion::create(['region_name' => 'Synthetic']);
        $scope = DB::table('event_regions')->insertGetId(['event_id' => $event->id, 'region_id' => $region->id, 'ordering' => 1]);
        foreach ([$event->id => ['payout' => 600, 'recovery' => 10, 'offset_in' => 7, 'offset_out' => 2], $other->id => ['payout' => 999]] as $eventId => $kinds) {
            foreach ($kinds as $kind => $amount) {
                RegionalClothingSettlement::create(['event_id' => $eventId, 'event_region_id' => $scope,
                    'region_id' => $region->id, 'region_name' => 'Synthetic', 'kind' => $kind, 'amount' => $amount,
                    'recipient_name' => 'Synthetic', 'occurred_at' => now(), 'reference' => 'QA', 'evidence' => 'Synthetic',
                    'recorded_by' => $user->id, 'recorded_by_name' => 'Synthetic', 'request_token' => Str::uuid(), 'request_fingerprint' => str_repeat('a', 64)]);
            }
        }
        $tables = ['category_event_registration', 'event_payouts', 'regional_clothing_settlements', 'clothing_orders', 'wallet_transactions', 'transactions_pf'];
        // Capture full rows so repeated reporting must preserve values as well as counts.
        $tables[0] = (new CategoryEventRegistration)->getTable();
        $snapshot = fn () => collect($tables)->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
        $before = $snapshot();
        $ledger = app(FinancialLedgerService::class);
        for ($i = 0; $i < 2; $i++) {
            $built = $ledger->buildForEvent($event);
            $rows = $built['refundRows'];
            $this->assertCount(4, $rows);
            $saved = $rows->first(fn ($row) => $row->refund_net === 270.0);
            $this->assertSame(-270.0, $saved->net);
            $this->assertSame(-270.0, $saved->amount_net);
            $this->assertSame(30.0, $saved->fee);
            $this->assertSame(0.0, $saved->capeFee);
            $totals = $built['totals'];
            $this->assertSame(-270.0, $totals['completed_refund_adj']);
            $this->assertSame(-290.0, $totals['registration_balance']);
            $this->assertSame(-100.0, $totals['clothing_balance']);
            $this->assertSame(-390.0, $totals['balance']);
            $this->assertSame(20.0, $totals['total_paid_out']);
            $this->assertSame(620.0, $totals['combined_paid_out']);
            $summary = $ledger->buildFySummaryRow($event);
            foreach (['balance', 'registration_balance', 'clothing_balance', 'combined_paid_out'] as $key) {
                $this->assertSame($totals[$key], $summary[$key]);
            }
        }
        $this->assertSame($before, $snapshot());
    }
}
