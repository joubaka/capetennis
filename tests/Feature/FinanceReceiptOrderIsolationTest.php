<?php

namespace Tests\Feature;

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\Player;
use App\Models\RegistrationOrder;
use App\Models\Team;
use App\Models\TeamPaymentOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinanceReceiptOrderIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_receipt_ignores_colliding_individual_order_and_uses_exact_team_details(): void
    {
        $event = Event::factory()->create(['cape_tennis_fee' => 15]);
        $payer = User::factory()->create();
        $player = Player::factory()->create(['name' => 'Correct', 'surname' => 'Player']);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => 'Correct category'])->id]);
        $team = Team::factory()->create(['category_event_id' => $category->id]);
        $teamOrder = TeamPaymentOrder::create(['event_id' => $event->id, 'user_id' => $payer->id, 'team_id' => $team->id, 'player_id' => $player->id, 'total_amount' => 350, 'wallet_reserved' => 50, 'wallet_debited' => true]);
        $foreign = CategoryEvent::factory()->create();
        DB::table('registration_orders')->insert(['id' => $teamOrder->id, 'user_id' => $payer->id, 'wallet_reserved' => 999, 'wallet_debited' => true]);
        DB::table('registration_order_items')->insert(['order_id' => $teamOrder->id, 'category_event_id' => $foreign->id, 'player_id' => Player::factory()->create()->id, 'item_price' => 240]);
        $this->receipt($event, $payer, $teamOrder->id, 'TeamOrder', 300);

        $ledger = app(FinancialLedgerService::class);
        $built = $ledger->buildForEvent($event);
        $row = $built['paymentRows']->sole();
        $this->assertInstanceOf(TeamPaymentOrder::class, $row->order);
        $this->assertSame(1, $row->entryCount);
        $this->assertSame(50.0, $row->walletUsed);
        $this->assertSame(350.0, $row->gross);
        $this->assertSame([['player' => 'Correct Player', 'category' => 'Correct category', 'price' => 350.0]], $row->registrationDetails->all());
        $this->assertSame(350.0, $built['totals']['gross_payments']);
        $this->assertSame(round(350 + $row->fee - 15, 2), $built['totals']['net_revenue']);
        $this->assertEquals($built['totals'], $ledger->buildForEvent($event)['totals']);
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseCount('registration_order_items', 1);
    }

    public function test_foreign_event_wrong_payer_and_unknown_order_types_cannot_supply_metadata_or_wallet_money(): void
    {
        $event = Event::factory()->create(['cape_tennis_fee' => 0]);
        $payer = User::factory()->create();
        $otherPayer = User::factory()->create();
        $foreignCategory = CategoryEvent::factory()->create();
        $localCategory = CategoryEvent::factory()->create(['event_id' => $event->id]);
        foreach ([[$foreignCategory, $payer], [$localCategory, $otherPayer]] as [$category, $owner]) {
            $order = RegistrationOrder::create(['user_id' => $owner->id, 'wallet_reserved' => 999, 'wallet_debited' => true]);
            DB::table('registration_order_items')->insert(['order_id' => $order->id, 'category_event_id' => $category->id, 'player_id' => Player::factory()->create()->id, 'item_price' => 999]);
            $this->receipt($event, $payer, $order->id, null, 100);
        }
        $teamOrder = TeamPaymentOrder::create(['event_id' => $foreignCategory->event_id, 'user_id' => $payer->id, 'total_amount' => 999, 'wallet_reserved' => 999, 'wallet_debited' => true]);
        $this->receipt($event, $payer, $teamOrder->id, 'TeamOrder', 100);
        $this->receipt($event, $payer, $teamOrder->id, 'ClothingOrder', 100);
        $built = app(FinancialLedgerService::class)->buildForEvent($event);
        $this->assertCount(4, $built['paymentRows']);
        foreach ($built['paymentRows'] as $row) {
            $this->assertNull($row->order);
            $this->assertSame(0.0, $row->walletUsed);
            $this->assertSame(0, $row->entryCount);
            $this->assertEmpty($row->registrationDetails);
        }
        $this->assertSame(400.0, $built['totals']['gross_payments']);
    }

    public function test_individual_hybrid_keeps_item_amounts_and_ignores_undebited_reservations(): void
    {
        $event = Event::factory()->create(['cape_tennis_fee' => 15]);
        $payer = User::factory()->create();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $order = RegistrationOrder::create(['user_id' => $payer->id, 'wallet_reserved' => 100, 'wallet_debited' => true]);
        foreach ([120.25, 229.75] as $amount) {
            DB::table('registration_order_items')->insert(['order_id' => $order->id, 'category_event_id' => $category->id, 'player_id' => Player::factory()->create()->id, 'item_price' => $amount]);
        }
        $this->receipt($event, $payer, $order->id, null, 250);
        $row = app(FinancialLedgerService::class)->buildPaymentRows($event, 15)->sole();
        $this->assertSame(350.0, $row->gross);
        $this->assertSame(350.0, $row->registrationDetails->sum('price'));
        $this->assertSame(2, $row->entryCount);
        $this->assertSame(-30.0, $row->capeFee);
        DB::table('registration_orders')->where('id', $order->id)->update(['wallet_debited' => false]);
        $row = app(FinancialLedgerService::class)->buildPaymentRows($event, 15)->sole();
        $this->assertSame(250.0, $row->gross);
        $this->assertSame(0.0, $row->walletUsed);
    }

    public function test_reconciliation_receipt_keeps_money_without_counting_an_entry_or_released_wallet(): void
    {
        $event = Event::factory()->create(['cape_tennis_fee' => 15]);
        $payer = User::factory()->create();
        $order = TeamPaymentOrder::create(['event_id' => $event->id, 'user_id' => $payer->id, 'total_amount' => 350, 'wallet_reserved' => 100, 'collection_status' => 'reconciliation_required']);
        $this->receipt($event, $payer, $order->id, 'TeamOrder', 350);
        $ledger = app(FinancialLedgerService::class);
        $row = $ledger->buildPaymentRows($event, 15)->sole();
        $this->assertSame('superseded_payfast_receipt', $row->subtype);
        $this->assertSame(0, $row->entryCount);
        $this->assertSame(0.0, $row->walletUsed);
        $this->assertSame(0.0, $row->capeFee);
        $this->assertSame(350.0, $row->gross);
        $this->assertEmpty($row->registrationDetails);
        $this->assertEquals(0, $ledger->buildFySummaryRow($event)['total_entries']);
        $summary = $ledger->buildFySummaryRow($event);
        $this->assertSame(350.0, $summary['registration_received']);
        $this->assertSame(0.0, $summary['registration_net']);
        $this->assertSame(0.0, $summary['registration_balance']);
        $this->assertGreaterThan(0, $summary['total_income']);
        $this->assertSame(350.0, $ledger->buildForEvent($event)['totals']['gross_payments']);
        $this->assertSame(0.0, $ledger->buildForEvent($event)['totals']['registration_balance']);
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseCount('event_payouts', 0);
    }

    private function receipt(Event $event, User $payer, int $orderId, ?string $type, float $gross): void
    {
        DB::table('transactions_pf')->insert(['event_id' => $event->id, 'custom_int4' => $payer->id, 'custom_int5' => $orderId, 'custom_str5' => $type, 'transaction_type' => 'Registration', 'pf_payment_id' => 'PF-'.uniqid(), 'amount_gross' => $gross, 'created_at' => now(), 'updated_at' => now()]);
    }
}
