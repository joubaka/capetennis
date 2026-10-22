<?php

namespace Tests\Feature\Authorization;

use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\EventConvenor;
use App\Models\EventExpense;
use App\Models\EventIncomeItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventFinanceControllerAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $adminB;
    private User $user;
    private Event $eventA;
    private Event $eventB;
    private EventExpense $expenseA;
    private EventIncomeItem $incomeA;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
        $this->adminB = User::factory()->create();
        $this->adminB->assignRole('admin');
        $this->user = User::factory()->create();
        $this->eventA = Event::factory()->create();
        $this->eventB = Event::factory()->create();
        EventAdmin::create(['user_id' => $this->admin->id, 'event_id' => $this->eventA->id]);
        EventAdmin::create(['user_id' => $this->adminB->id, 'event_id' => $this->eventB->id]);
        $this->expenseA = EventExpense::create(['event_id' => $this->eventA->id, 'expense_type' => 'Equipment', 'amount' => 100]);
        $this->incomeA = EventIncomeItem::create(['event_id' => $this->eventA->id, 'label' => 'Sponsorship', 'quantity' => 1, 'unit_price' => 500]);
    }

    public function test_view_guest_redirect() { $this->get(route('admin.events.finances', $this->eventA->id))->assertRedirect('login'); }

    public function test_view_admin_success() { $this->actingAs($this->admin)->get(route('admin.events.finances', $this->eventA->id))->assertSuccessful(); }

    public function test_convenor_payout_breakdown_is_visible_after_summary_before_received_transactions(): void
    {
        $convenor = EventConvenor::create(['event_id' => $this->eventA->id, 'user_id' => $this->admin->id, 'role' => 'hoof', 'profit_share_pct' => 50]);
        $this->expenseA->update(['paid_by_convenor_id' => $convenor->id]);
        $response = $this->actingAs($this->admin)->get(route('admin.events.finances', $this->eventA));
        $response->assertSuccessful()->assertSeeInOrder(['Registration net income', 'id="finance-convenor-payouts"', 'id="finance-receipts"'], false);
        $row = $response->viewData('recon')->first();
        $this->assertSame(100.0, $row['total_paid']);
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML($response->getContent());
            $xpath = new \DOMXPath($document);
            $section = $xpath->query('//*[@id="finance-convenor-payouts"]')->item(0);
            $this->assertNotNull($section);
            $this->assertStringContainsString('Convenor Payout Breakdown', $section->textContent);
            $this->assertStringContainsString(number_format($row['final_payout'], 2), $section->textContent);
            $this->assertStringContainsString(number_format($row['total_paid'], 2), $section->textContent);
            $this->assertSame(0, $xpath->query('//*[@id="finance-convenor-payouts"]/ancestor::*[self::details or contains(concat(" ", normalize-space(@class), " "), " collapse ") or contains(concat(" ", normalize-space(@class), " "), " tab-pane ")]')->length);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function test_team_payment_breakdown_uses_correct_event_order_and_totals_despite_id_collision(): void
    {
        $this->eventA->forceFill(['cape_tennis_fee' => 15])->save();
        $player = \App\Models\Player::factory()->create(['name' => 'Correct', 'surname' => 'Receipt Player']);
        $category = \App\Models\CategoryEvent::factory()->create([
            'event_id' => $this->eventA->id,
            'category_id' => \App\Models\Category::factory()->create(['name' => 'Correct receipt category'])->id,
        ]);
        $team = \App\Models\Team::factory()->create(['category_event_id' => $category->id]);
        $order = \App\Models\TeamPaymentOrder::create([
            'event_id' => $this->eventA->id, 'user_id' => $this->user->id, 'player_id' => $player->id,
            'team_id' => $team->id, 'total_amount' => 350,
        ]);
        $foreignPlayer = \App\Models\Player::factory()->create(['name' => 'Foreign', 'surname' => 'Receipt Player']);
        $foreignCategory = \App\Models\CategoryEvent::factory()->create(['event_id' => $this->eventB->id]);
        DB::table('registration_orders')->insert(['id' => $order->id, 'user_id' => $this->user->id]);
        DB::table('registration_order_items')->insert([
            'order_id' => $order->id, 'category_event_id' => $foreignCategory->id,
            'player_id' => $foreignPlayer->id, 'item_price' => 240,
        ]);
        DB::table('transactions_pf')->insert([
            'pf_payment_id' => 'PF-COLLIDING-ORDER', 'event_id' => $this->eventA->id,
            'transaction_type' => 'Registration', 'custom_str5' => 'TeamOrder',
            'custom_int5' => $order->id, 'custom_int4' => $this->user->id,
            'amount_gross' => 350, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $fee = \App\Models\SiteSetting::calculatePayfastFee(350);
        $response = $this->actingAs($this->admin)->get(route('admin.events.finances', $this->eventA->id));
        $response->assertSuccessful()->assertSee('Correct Receipt Player')->assertSee('Correct receipt category')
            ->assertDontSee('Foreign Receipt Player')->assertViewHas('totalGross', 350.0)
            ->assertViewHas('totalEntries', 1)->assertViewHas('netRegistrationIncome', round(350 - $fee - 15, 2))
            ->assertViewHas('eventTransactions', fn ($rows) => $rows->count() === 1
                && $rows->first()->registrationDetails->sole()['price'] === 350.0);
    }

    public function test_view_uses_canonical_ledger_and_lists_user_transactions(): void
    {
        $payer = User::factory()->create(['name' => 'Finance Payer']);

        DB::table('transactions_pf')->insert([
            'pf_payment_id' => 'PF-ADMIN-VIEW-1',
            'event_id' => $this->eventA->id,
            'transaction_type' => 'Registration',
            'amount_gross' => 300.00,
            'custom_int4' => $payer->id,
            'is_test' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.events.finances', $this->eventA->id));

        $response
            ->assertSuccessful()
            ->assertViewHas('totalGross', 300.00)
            ->assertViewHas('eventTransactions', fn ($rows) => $rows->count() === 1
                && $rows->first()->user_name === 'Finance Payer')
            ->assertSee('Received Transactions')
            ->assertSee('Open to see registration and clothing receipts, payment method, fees and refunds.')
            ->assertSee('registration-transactions-action-label', false)
            ->assertSee('Finance Payer')
            ->assertSee('PF-ADMIN-VIEW-1')
            ->assertSee('Registered player')
            ->assertSee('Category')
            ->assertSee('Entry amount');
        $response
            ->assertSee('Setup tools', false)
            ->assertSee('finance-reconciliation', false)
            ->assertSee('data-finance-details', false)
            ->assertSee('beforeprint', false)
            ->assertSee('afterprint', false);
        if (getenv('CT_BATCHES91011_QA')) {
            $directory = storage_path('app/batches91011-qa');
            if (!is_dir($directory)) { mkdir($directory, 0755, true); }
            file_put_contents($directory.'/finances.html', $response->getContent());
        }
    }

    public function test_paid_clothing_breakdown_is_event_scoped_and_reconciles_once(): void
    {
        $this->eventA->update(['cape_tennis_fee' => 0]);
        // Registration ledger fees use the configured calculator, not amount_fee.
        \App\Models\SiteSetting::set('payfast_fee_percentage', 4);
        \App\Models\SiteSetting::set('payfast_fee_flat', 0);
        \App\Models\SiteSetting::set('payfast_vat_rate', 0);
        $category = \App\Models\CategoryEvent::factory()->create(['event_id' => $this->eventA->id]);
        $north = \App\Models\TeamRegion::create(['region_name' => 'North clothing region']);
        $south = \App\Models\TeamRegion::create(['region_name' => 'South clothing region']);
        $teams = collect([$north, $south])->map(fn ($region) => \App\Models\Team::factory()->create([
            'category_event_id' => $category->id, 'region_id' => $region->id,
        ]));
        foreach ([[$teams[0]->id, 101.01, 3.03], [$teams[0]->id, 50.50, 1.50], [$teams[1]->id, 200, 6], [null, 25, 0]] as [$teamId, $gross, $fee]) {
            \App\Models\ClothingOrder::create([
                'event_id' => $this->eventA->id, 'team_id' => $teamId, 'user_id' => $this->user->id,
                'pay_status' => 1, 'status' => 'completed', 'total' => 999, 'amount_paid' => $gross, 'payfast_fee' => $fee,
            ]);
        }
        foreach ([[$this->eventA->id, 0, 'pending'], [$this->eventB->id, 1, 'completed']] as [$eventId, $paid, $status]) {
            \App\Models\ClothingOrder::create([
                'event_id' => $eventId, 'user_id' => $this->user->id, 'pay_status' => $paid, 'status' => $status, 'total' => 999,
            ]);
        }
        DB::table('transactions_pf')->insert([
            'event_id' => $this->eventA->id, 'transaction_type' => 'Registration', 'amount_gross' => 100,
            'amount_fee' => -4, 'is_test' => false, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $response = $this->actingAs($this->admin)->get(route('admin.events.finances', $this->eventA));
        $response->assertOk()->assertDontSee('Show income by')->assertSee('Clothing funds by region')
            ->assertSee('Recorded PayFast fee')->assertSee('Region not recorded')
            ->assertViewHas('netRegistrationIncome', 96.00)->assertViewHas('clothingNet', 365.98)
            ->assertViewHas('grandTotalIncome', 96.00)
            ->assertViewHas('clothingReceipts', function ($report) {
                $this->assertSame(4, $report['count']);
                $this->assertEquals(376.51, $report['totals']['gross']);
                $this->assertEquals(-10.53, $report['totals']['fees']);
                $this->assertEquals(146.98, $report['groups']['North clothing region']['totals']['net']);
                $this->assertEquals(194, $report['groups']['South clothing region']['totals']['net']);
                return true;
            });
        $this->actingAs($this->admin)->get(route('admin.events.finances', $this->eventA))->assertOk()
            ->assertViewHas('grandTotalIncome', 96.00);
        $this->assertDatabaseCount('clothing_orders', 6);
        $this->assertDatabaseCount('transactions_pf', 1);
    }

    public function test_registration_profit_fees_and_refunds_exclude_regional_funds_and_other_income(): void
    {
        $this->eventA->update(['cape_tennis_fee' => 7.50]);
        $payer = $this->user;
        $category = \App\Models\CategoryEvent::factory()->create(['event_id' => $this->eventA->id]);
        $orders = collect(['North', 'South'])->map(function ($name) use ($category, $payer) {
            $region = \App\Models\TeamRegion::create(['region_name' => $name]);
            $team = \App\Models\Team::factory()->create(['category_event_id' => $category->id, 'region_id' => $region->id]);
            return \App\Models\ClothingOrder::create(['event_id' => $this->eventA->id, 'user_id' => $payer->id, 'team_id' => $team->id, 'total' => 100, 'pay_status' => 1]);
        });
        $row = fn ($values) => (object) array_merge(['type' => 'payment', 'source_tx_id' => null, 'gross' => 0, 'fee' => 0, 'capeFee' => 0, 'net' => 0, 'created_at' => now(), 'registrationDetails' => [], 'user_name' => 'Example payer', 'payment_method' => 'PayFast', 'status_label' => 'Paid', 'status_colour' => 'success'], $values);
        $payments = collect([
            $row(['gross' => 1000, 'fee' => -40, 'capeFee' => -15, 'net' => 945, 'entryCount' => 2]),
            $row(['type' => 'clothing_payment', 'order' => $orders[0], 'gross' => 100, 'fee' => -4, 'net' => 96]),
            $row(['type' => 'clothing_payment', 'order' => $orders[1], 'gross' => 100, 'fee' => -4, 'net' => 96]),
        ]);
        $refunds = collect([
            $row(['type' => 'refund', 'refund_status' => 'completed', 'refund_gross' => 100, 'net' => -81]),
            $row(['type' => 'refund', 'refund_status' => 'pending', 'refund_gross' => 50, 'net' => -40]),
            $row(['type' => 'refund', 'subtype' => 'clothing_refund', 'order' => $orders[0], 'refund_status' => 'completed', 'refund_gross' => 50, 'net' => -48]),
            $row(['type' => 'refund', 'subtype' => 'clothing_refund', 'order' => $orders[1], 'refund_status' => 'pending', 'refund_gross' => 30, 'net' => -28.80]),
        ]);
        $ledger = app(\App\Domain\Finance\Services\FinancialLedgerService::class);
        $totals = $ledger->buildTotals($payments, $refunds, collect());
        $this->mock(\App\Domain\Finance\Services\FinancialLedgerService::class, function ($mock) use ($payments, $refunds, $totals) {
            $mock->shouldReceive('buildForEvent')->twice()->withArgs(fn ($event) => $event->id === $this->eventA->id)->andReturn(['paymentRows' => $payments, 'refundRows' => $refunds, 'totals' => $totals]);
        });
        EventConvenor::create(['event_id' => $this->eventA->id, 'user_id' => $this->admin->id, 'role' => 'hoof', 'profit_share_pct' => 50]);
        $staleFee = EventExpense::create(['event_id' => $this->eventA->id, 'expense_type' => 'payfast', 'amount' => 48]);
        for ($i = 0; $i < 2; $i++) {
            $response = $this->actingAs($this->admin)->get(route('admin.events.finances', $this->eventA));
            $response->assertOk()->assertViewHas('totalGross', 1000)->assertViewHas('totalEntries', 2)
                ->assertViewHas('totalPayfastFees', 40)->assertViewHas('totalSystemFees', 55)
                ->assertViewHas('grandTotalIncome', 864)->assertViewHas('netProfit', 764)
                ->assertViewHas('totalIncomeItems', 500)->assertViewHas('clothingNet', 144)
                ->assertViewHas('recon', fn ($rows) => $rows->first()['profit_share_amount'] == 382)
                ->assertSee('View transactions')->assertSee('Clothing item')->assertSee('Item amount')
                ->assertSee('Completed registration refunds (net paid back)')
                ->assertDontSee('Net Registration and Clothing Income')->assertDontSee('registrations and clothing');
            $this->assertEquals(40, $staleFee->refresh()->amount);
            $this->assertDatabaseCount('event_expenses', 3);
            $this->assertDatabaseCount('clothing_orders', 2);
        }
        if (getenv('CT_FINANCE_SEPARATION_QA')) {
            $directory = storage_path('app/finance-separation-qa');
            if (!is_dir($directory)) { mkdir($directory, 0755, true); }
            file_put_contents($directory.'/finances.html', $response->getContent());
        }
    }

    public function test_view_cross_event_forbidden() { $this->actingAs($this->admin)->get(route('admin.events.finances', $this->eventB->id))->assertStatus(403); }

    public function test_view_user_forbidden() { $this->actingAs($this->user)->get(route('admin.events.finances', $this->eventA->id))->assertStatus(403); }

    public function test_expense_store_admin() { $this->actingAs($this->admin)->post(route('admin.events.finances.expense.store', $this->eventA->id), ['expense_type' => 'Equipment', 'amount' => 100])->assertRedirect(); $this->assertCount(2, EventExpense::where('event_id', $this->eventA->id)->get()); }

    public function test_expense_store_cross_event() { $this->actingAs($this->admin)->post(route('admin.events.finances.expense.store', $this->eventB->id), ['expense_type' => 'Equipment', 'amount' => 100])->assertStatus(403); }

    public function test_expense_update_admin() { $this->actingAs($this->admin)->patch(route('admin.events.finances.expense.update', $this->expenseA->id), ['expense_type' => 'Equipment', 'amount' => 250])->assertRedirect(); $this->assertEquals(250, $this->expenseA->refresh()->amount); }

    public function test_expense_update_cross_event() { $expenseB = EventExpense::create(['event_id' => $this->eventB->id, 'expense_type' => 'Equipment', 'amount' => 100]); $this->actingAs($this->admin)->patch(route('admin.events.finances.expense.update', $expenseB->id), ['expense_type' => 'Equipment', 'amount' => 250])->assertStatus(403); }

    public function test_expense_destroy_admin() { $this->actingAs($this->admin)->delete(route('admin.events.finances.expense.destroy', $this->expenseA->id))->assertRedirect(); $this->assertNull(EventExpense::find($this->expenseA->id)); }

    public function test_expense_destroy_cross_event() { $expenseB = EventExpense::create(['event_id' => $this->eventB->id, 'expense_type' => 'Equipment', 'amount' => 100]); $this->actingAs($this->admin)->delete(route('admin.events.finances.expense.destroy', $expenseB->id))->assertStatus(403); $this->assertNotNull(EventExpense::find($expenseB->id)); }

    public function test_expense_approve_admin() { $this->actingAs($this->admin)->post(route('admin.events.finances.expense.approve', $this->expenseA->id))->assertRedirect(); }

    public function test_expense_approve_cross_event() { $expenseB = EventExpense::create(['event_id' => $this->eventB->id, 'expense_type' => 'Equipment', 'amount' => 100]); $this->actingAs($this->admin)->post(route('admin.events.finances.expense.approve', $expenseB->id))->assertStatus(403); }

    public function test_expense_reimburse_admin() { $this->actingAs($this->admin)->post(route('admin.events.finances.expense.reimburse', $this->expenseA->id))->assertRedirect(); }

    public function test_expense_reimburse_cross_event() { $expenseB = EventExpense::create(['event_id' => $this->eventB->id, 'expense_type' => 'Equipment', 'amount' => 100]); $this->actingAs($this->admin)->post(route('admin.events.finances.expense.reimburse', $expenseB->id))->assertStatus(403); }

    public function test_income_store_admin() { $this->actingAs($this->admin)->post(route('admin.events.finances.income.store', $this->eventA->id), ['label' => 'S', 'quantity' => 1, 'unit_price' => 500])->assertRedirect(); $this->assertCount(2, EventIncomeItem::where('event_id', $this->eventA->id)->get()); }

    public function test_income_store_cross_event() { $this->actingAs($this->admin)->post(route('admin.events.finances.income.store', $this->eventB->id), ['label' => 'S', 'quantity' => 1, 'unit_price' => 500])->assertStatus(403); }

    public function test_income_update_admin() { $this->actingAs($this->admin)->patch(route('admin.events.finances.income.update', $this->incomeA->id), ['unit_price' => 750])->assertRedirect(); }

    public function test_income_update_cross_event() { $incomeB = EventIncomeItem::create(['event_id' => $this->eventB->id, 'label' => 'S', 'quantity' => 1, 'unit_price' => 500]); $this->actingAs($this->admin)->patch(route('admin.events.finances.income.update', $incomeB->id), ['unit_price' => 750])->assertStatus(403); }

    public function test_income_destroy_admin() { $this->actingAs($this->admin)->delete(route('admin.events.finances.income.destroy', $this->incomeA->id))->assertRedirect(); $this->assertNull(EventIncomeItem::find($this->incomeA->id)); }

    public function test_income_destroy_cross_event() { $incomeB = EventIncomeItem::create(['event_id' => $this->eventB->id, 'label' => 'S', 'quantity' => 1, 'unit_price' => 500]); $this->actingAs($this->admin)->delete(route('admin.events.finances.income.destroy', $incomeB->id))->assertStatus(403); $this->assertNotNull(EventIncomeItem::find($incomeB->id)); }

    public function test_convenor_store_admin() { $user = User::factory()->create(); $this->actingAs($this->admin)->post(route('admin.events.finances.convenor.store', $this->eventA->id), ['user_ids' => [$user->id]])->assertRedirect(); $this->assertTrue(EventConvenor::where('user_id', $user->id)->where('event_id', $this->eventA->id)->exists()); }

    public function test_convenor_store_cross_event() { $user = User::factory()->create(); $this->actingAs($this->admin)->post(route('admin.events.finances.convenor.store', $this->eventB->id), ['user_ids' => [$user->id]])->assertStatus(403); }

    public function test_convenor_update_admin() { $convenor = EventConvenor::create(['event_id' => $this->eventA->id, 'user_id' => User::factory()->create()->id]); $this->actingAs($this->admin)->patch(route('admin.events.finances.convenor.update', $convenor->id), ['role' => 'hulp'])->assertRedirect(); }

    public function test_convenor_update_cross_event() { $convenor = EventConvenor::create(['event_id' => $this->eventB->id, 'user_id' => User::factory()->create()->id]); $this->actingAs($this->admin)->patch(route('admin.events.finances.convenor.update', $convenor->id), ['role' => 'hulp'])->assertStatus(403); }

    public function test_convenor_destroy_admin() { $convenor = EventConvenor::create(['event_id' => $this->eventA->id, 'user_id' => User::factory()->create()->id]); $this->actingAs($this->admin)->delete(route('admin.events.finances.convenor.destroy', $convenor->id))->assertRedirect(); $this->assertNull(EventConvenor::find($convenor->id)); }

    public function test_convenor_destroy_cross_event() { $convenor = EventConvenor::create(['event_id' => $this->eventB->id, 'user_id' => User::factory()->create()->id]); $this->actingAs($this->admin)->delete(route('admin.events.finances.convenor.destroy', $convenor->id))->assertStatus(403); $this->assertNotNull(EventConvenor::find($convenor->id)); }
}
