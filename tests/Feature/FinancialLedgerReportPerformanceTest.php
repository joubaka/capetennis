<?php

namespace Tests\Feature;

use App\Domain\Finance\Services\FinancialLedgerService;
use App\Models\{CategoryEvent, Event, Player, RegistrationOrder, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, DB};
use Tests\TestCase;

class FinancialLedgerReportPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_detailed_receipt_queries_are_bounded_and_preserve_exact_money_and_baskets(): void
    {
        $event = Event::factory()->create(['cape_tennis_fee' => 15]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $payer = User::factory()->create();
        $player = Player::factory()->create();
        $add = function () use ($event, $category, $payer, $player) {
            $order = RegistrationOrder::create(['user_id' => $payer->id, 'wallet_reserved' => 40.10,
                'wallet_debited' => true, 'payfast_amount_due' => 150.25, 'pay_status' => true, 'status' => 'completed']);
            foreach ([90, 100.35] as $price) {
                DB::table('registration_order_items')->insert(['order_id' => $order->id,
                    'category_event_id' => $category->id, 'player_id' => $player->id, 'item_price' => $price]);
            }
            DB::table('transactions_pf')->insert(['event_id' => $event->id, 'custom_int4' => $payer->id,
                'custom_int5' => $order->id, 'transaction_type' => 'Registration', 'pf_payment_id' => 'QA-'.$order->id,
                'amount_gross' => 150.25, 'created_at' => now(), 'updated_at' => now()]);
        };
        $read = function () use ($event) {
            Cache::flush();
            DB::flushQueryLog();
            DB::enableQueryLog();
            try {
                $result = app(FinancialLedgerService::class)->buildForEvent($event);
                return [$result, count(DB::getQueryLog())];
            } finally {
                DB::disableQueryLog();
                DB::flushQueryLog();
            }
        };
        $add();
        [, $smallQueries] = $read();
        for ($i = 0; $i < 11; $i++) $add();
        [$large, $largeQueries] = $read();
        $this->assertSame($smallQueries, $largeQueries);
        $this->assertCount(12, $large['paymentRows']);
        $this->assertSame(24, $large['paymentRows']->sum('entryCount'));
        $this->assertSame(2284.2, $large['totals']['registration_received']);
        foreach ($large['paymentRows'] as $row) {
            $this->assertSame(190.35, $row->gross);
            $this->assertSame(40.1, $row->walletUsed);
            $this->assertSame(-30.0, $row->capeFee);
            $this->assertSame([90.0, 100.35], $row->registrationDetails->pluck('price')->all());
        }
        $this->assertDatabaseCount('registration_orders', 12);
        $this->assertDatabaseCount('registration_order_items', 24);
        $this->assertDatabaseCount('transactions_pf', 12);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_empty_or_mixed_and_orphan_baskets_never_supply_receipt_metadata(): void
    {
        $event = Event::factory()->create(['cape_tennis_fee' => 0]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $foreign = CategoryEvent::factory()->create();
        $payer = User::factory()->create();
        foreach ([[], [$category->id, $foreign->id], [$category->id, null]] as $categories) {
            $order = RegistrationOrder::create(['user_id' => $payer->id, 'wallet_reserved' => 99,
                'wallet_debited' => true, 'payfast_amount_due' => 100]);
            foreach ($categories as $categoryId) {
                DB::table('registration_order_items')->insert(['order_id' => $order->id, 'category_event_id' => $categoryId]);
            }
            DB::table('transactions_pf')->insert(['event_id' => $event->id, 'custom_int4' => $payer->id,
                'custom_int5' => $order->id, 'transaction_type' => 'Registration', 'pf_payment_id' => 'QA-'.$order->id,
                'amount_gross' => 100, 'created_at' => now(), 'updated_at' => now()]);
        }
        $ledger = app(FinancialLedgerService::class);
        foreach ([false, true] as $summary) {
            $rows = $ledger->buildPaymentRows($event, 0, $summary);
            $this->assertCount(3, $rows);
            foreach ($rows as $row) {
                $this->assertNull($row->order);
                $this->assertSame(0, $row->entryCount);
                $this->assertSame(0.0, $row->walletUsed);
                $this->assertSame(100.0, $row->gross);
                $this->assertEmpty($row->registrationDetails);
            }
        }
    }
}
