<?php

namespace Tests\Feature;

use App\Domain\Finance\Services\EventFinanceReceiptReport;
use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\CategoryEvent;
use App\Models\ClothingOrder;
use App\Models\Event;
use App\Models\RegistrationOrder;
use App\Models\Team;
use App\Models\TeamPaymentOrder;
use App\Models\TeamRegion;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventFinanceReceiptReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_payfast_wallet_and_clothing_receipts_are_separate_per_region_without_using_payer_region(): void
    {
        $event = Event::factory()->create();
        $payer = User::factory()->create();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $north = TeamRegion::create(['region_name' => 'North']);
        $south = TeamRegion::create(['region_name' => 'South']);
        $teamNorth = Team::factory()->create(['category_event_id' => $category->id, 'region_id' => $north->id]);
        $teamSouth = Team::factory()->create(['category_event_id' => $category->id, 'region_id' => $south->id]);
        $orderNorth = TeamPaymentOrder::create(['event_id' => $event->id, 'team_id' => $teamNorth->id, 'user_id' => $payer->id, 'total_amount' => 100]);
        $orderSouth = TeamPaymentOrder::create(['event_id' => $event->id, 'team_id' => $teamSouth->id, 'user_id' => $payer->id, 'total_amount' => 200, 'wallet_reserved' => 200, 'wallet_debited' => true, 'pay_status' => true, 'payfast_amount_due' => 0]);
        $wallet = Wallet::factory()->forUser($payer)->create();
        WalletTransaction::create(['wallet_id' => $wallet->id, 'type' => 'debit', 'amount' => 200, 'source_type' => 'team_registration_wallet_payment', 'source_id' => $orderSouth->id]);
        $walletRow = app(FinancialLedgerService::class)->buildPaymentRows($event, 0)->firstWhere('subtype', 'wallet_payment_team');
        $this->assertSame($orderSouth->id, $walletRow->team_order_id);
        $this->assertNull($walletRow->order);
        $txId = DB::table('transactions_pf')->insertGetId([
            'event_id' => $event->id, 'transaction_type' => 'Registration', 'custom_str5' => 'TeamOrder',
            'custom_int5' => $orderNorth->id, 'amount_gross' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);
        // The transaction relationship points at a different table with the same ID.
        $collidingOrder = new RegistrationOrder;
        $collidingOrder->id = $orderNorth->id;
        $clothing = ClothingOrder::create(['event_id' => $event->id, 'team_id' => $teamSouth->id, 'user_id' => $payer->id, 'total' => 50]);
        $rows = collect([
            $this->row(['source_tx_id' => $txId, 'order' => $collidingOrder, 'gross' => 100, 'fee' => -5, 'net' => 95]),
            $walletRow,
            $this->row(['type' => 'clothing_payment', 'order' => $clothing, 'gross' => 50, 'fee' => -2, 'net' => 48]),
            $this->row(['type' => 'refund', 'refund_status' => 'completed', 'source_tx_id' => $txId, 'gross' => -20, 'refund_gross' => 20, 'net' => -18]),
            $this->row(['type' => 'refund', 'refund_status' => 'pending', 'order' => $orderSouth, 'gross' => -30, 'refund_gross' => 30, 'net' => -30]),
            $this->row(['type' => 'withdrawal', 'order' => $orderSouth, 'original_gross' => 200, 'net' => 0]),
        ]);
        $report = app(EventFinanceReceiptReport::class)->build($event, $rows);
        $registrations = $report['registrations'];
        $this->assertSame(['North', 'South'], $registrations['groups']->keys()->all());
        $this->assertEquals(['gross' => 100, 'fees' => -5, 'completed_refunds' => 20, 'pending_refunds' => 0, 'net' => 77], $registrations['groups']['North']['totals']);
        $this->assertEquals(200, $registrations['groups']['South']['totals']['net']);
        $this->assertEquals(30, $registrations['groups']['South']['totals']['pending_refunds']);
        $this->assertSame(5, $registrations['count']);
        $this->assertSame(1, $report['clothing']['count']);
        $this->assertEquals(48, $report['clothing']['groups']['South']['totals']['net']);
        $ledger = app(FinancialLedgerService::class)->buildTotals($rows->whereIn('type', ['payment', 'clothing_payment']), $rows->whereIn('type', ['refund', 'withdrawal']), collect());
        $this->assertEquals($ledger['net_revenue'], $registrations['totals']['net'] + $report['clothing']['totals']['net']);
        $this->assertEquals($ledger['gross_payments'], $registrations['totals']['gross'] + $report['clothing']['totals']['gross']);
    }

    public function test_mixed_unknown_and_foreign_event_receipts_remain_explicit_and_are_not_duplicated(): void
    {
        $event = Event::factory()->create();
        $other = Event::factory()->create();
        $report = app(EventFinanceReceiptReport::class)->build($event, collect([
            $this->row(['gross' => 101.01, 'net' => 99.99, 'registrationDetails' => [['region' => 'North'], ['region' => 'South']]]),
            $this->row(['gross' => 2.02, 'net' => 2.02]),
            $this->row(['gross' => 3.03, 'net' => 3.03, 'registrationDetails' => [['region' => 'North'], ['player' => 'Unknown region']]]),
            $this->row(['event_id' => $other->id, 'gross' => 999, 'net' => 999]),
        ]));
        $groups = $report['registrations']['groups'];
        $this->assertSame(['Multiple regions: North, Region not recorded', 'Multiple regions: North, South', 'Region not recorded'], $groups->keys()->all());
        $this->assertSame(3, $report['registrations']['count']);
        $this->assertEquals(106.06, $report['registrations']['totals']['gross']);
        $this->assertEquals(105.04, round($groups->sum(fn ($group) => $group['totals']['net']), 2));
        $this->assertSame(0, $report['clothing']['count']);
    }

    public function test_foreign_team_and_foreign_team_order_do_not_supply_region_metadata(): void
    {
        $event = Event::factory()->create();
        $other = Event::factory()->create();
        $region = TeamRegion::create(['region_name' => 'Foreign region']);
        $team = Team::factory()->create(['region_id' => $region->id, 'category_event_id' => CategoryEvent::factory()->create(['event_id' => $other->id])->id]);
        $order = TeamPaymentOrder::create(['event_id' => $event->id, 'team_id' => $team->id, 'user_id' => User::factory()->create()->id, 'total_amount' => 10]);
        $foreignOrder = TeamPaymentOrder::create(['event_id' => $other->id, 'team_id' => $team->id, 'user_id' => $order->user_id, 'total_amount' => 20]);
        $report = app(EventFinanceReceiptReport::class)->build($event, collect([
            $this->row(['team_order_id' => $order->id, 'gross' => 10, 'net' => 10]),
            $this->row(['team_order_id' => $foreignOrder->id, 'gross' => 20, 'net' => 20]),
        ]));
        $this->assertSame(['Region not recorded'], $report['registrations']['groups']->keys()->all());
        $this->assertSame(2, $report['registrations']['count']);
    }

    private function row(array $values): object
    {
        return (object) array_merge(['type' => 'payment', 'source_tx_id' => null, 'gross' => 0, 'fee' => 0, 'capeFee' => 0, 'net' => 0], $values);
    }
}
