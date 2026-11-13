<?php

namespace Tests\Feature;

use App\Domain\Entries\Services\EntryService;
use App\Domain\Entries\Services\EntryEligibilityService;
use App\Domain\Finance\Services\FinancialLedgerService;
use App\Domain\Payments\Services\RegistrationPaymentService;
use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\Player;
use App\Models\Registration;
use App\Models\RegistrationOrder;
use App\Models\RegistrationOrderItems;
use App\Models\SiteSetting;
use App\Models\User;
use App\Http\Middleware\EnsureAgreementAccepted;
use App\Http\Middleware\EnsurePlayerProfileUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FreeRegistrationFeesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        DB::table('eventtypes')->insertOrIgnore(['id' => 1, 'name' => 'Individual', 'type' => 1]);
    }

    public function test_free_admin_entry_has_zero_fees_and_replay_creates_no_extra_records(): void
    {
        $event = Event::factory()->unpublished()->closedSignUp()->create(['entryFee' => 0, 'cape_tennis_fee' => 15]);
        $category = CategoryEvent::factory()->for($event)->create(['entry_fee' => null]);
        $actor = User::factory()->create();
        $player = Player::factory()->create();
        $entry = app(EntryService::class)->addPlayerAsAdmin($category, $player->id, $actor);

        $this->assertSame('active', $entry->status);
        $this->assertDatabaseHas('transactions_pf', ['category_event_id' => $category->id,
            'cape_tennis_fee' => 0, 'amount_gross' => 0, 'amount_fee' => 0, 'amount_net' => 0, 'pf_payment_id' => null]);
        try {
            app(EntryService::class)->addPlayerAsAdmin($category, $player->id, $actor);
            $this->fail('Duplicate entry should be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('already registered', $exception->getMessage());
        }
        $this->assertDatabaseCount('category_event_registrations', 1);
        $this->assertDatabaseCount('registrations', 1);
        $this->assertDatabaseCount('player_registrations', 1);
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $ledger = app(FinancialLedgerService::class);
        $built = $ledger->buildForEvent($event);
        $this->assertEquals(0, $built['totals']['cape_fees']);
        $this->assertEquals(0, $built['totals']['pf_fees']);
        $this->assertEquals(0, $built['totals']['registration_net']);
        $summary = $ledger->buildFySummaryRow($event);
        $this->assertSame(1, $summary['total_entries']);
        $this->assertEquals(0, $summary['registration_net']);
        $event->update(['entryFee' => 150]);
        $this->assertEquals(0, $ledger->buildForEvent($event->fresh())['totals']['cape_fees']);
        $this->assertFalse((bool) $event->fresh()->published);
        $this->assertFalse((bool) $event->fresh()->signUp);
    }

    public function test_category_overrides_determine_whether_admin_fee_is_due(): void
    {
        $actor = User::factory()->create();
        $freeEvent = Event::factory()->create(['entryFee' => 0, 'cape_tennis_fee' => 17.25]);
        $paidCategory = CategoryEvent::factory()->for($freeEvent)->create(['entry_fee' => 125]);
        $paidEvent = Event::factory()->create(['entryFee' => 125, 'cape_tennis_fee' => 19.50]);
        $freeCategory = CategoryEvent::factory()->for($paidEvent)->create(['entry_fee' => 0]);
        foreach ([$paidCategory, $freeCategory] as $category) {
            app(EntryService::class)->addPlayerAsAdmin($category, Player::factory()->create()->id, $actor);
        }
        $this->assertDatabaseHas('transactions_pf', ['category_event_id' => $paidCategory->id, 'cape_tennis_fee' => 17.25]);
        $this->assertDatabaseHas('transactions_pf', ['category_event_id' => $freeCategory->id, 'cape_tennis_fee' => 0]);
        $ledger = app(FinancialLedgerService::class);
        $this->assertEquals(-17.25, $ledger->buildForEvent($freeEvent)['totals']['cape_fees']);
        $this->assertEquals(0, $ledger->buildForEvent($paidEvent)['totals']['cape_fees']);
        $this->assertDatabaseCount('transactions_pf', 2);
    }

    public function test_captured_admin_fee_survives_later_zero_pricing(): void
    {
        $event = Event::factory()->create(['entryFee' => 150, 'cape_tennis_fee' => 15]);
        $category = CategoryEvent::factory()->for($event)->create(['entry_fee' => null]);
        app(EntryService::class)->addPlayerAsAdmin($category, Player::factory()->create()->id, User::factory()->create(), 'paid_privately');
        $event->update(['entryFee' => 0]);
        $category->update(['entry_fee' => 0]);
        $built = app(FinancialLedgerService::class)->buildForEvent($event->fresh());
        $this->assertEquals(-15, $built['totals']['cape_fees']);
        $this->assertDatabaseHas('transactions_pf', ['category_event_id' => $category->id, 'cape_tennis_fee' => 15]);
    }

    public function test_legacy_null_admin_snapshot_uses_same_event_free_price_without_rewriting_receipt(): void
    {
        $event = Event::factory()->create(['entryFee' => 0, 'cape_tennis_fee' => 15]);
        $category = CategoryEvent::factory()->for($event)->create(['entry_fee' => null]);
        $this->adminReceipt($event, $category);
        $ledger = app(FinancialLedgerService::class);
        $this->assertEquals(0, $ledger->buildForEvent($event)['totals']['cape_fees']);
        $this->assertEquals(0, $ledger->buildFySummaryRow($event)['registration_net']);
        $this->assertDatabaseHas('transactions_pf', ['category_event_id' => $category->id, 'cape_tennis_fee' => null]);
    }

    public function test_foreign_free_category_cannot_waive_legacy_fee_in_another_event(): void
    {
        $event = Event::factory()->create(['entryFee' => 100, 'cape_tennis_fee' => 15]);
        $other = Event::factory()->create(['entryFee' => 0, 'cape_tennis_fee' => 99]);
        $category = CategoryEvent::factory()->for($other)->create(['entry_fee' => 0]);
        $this->adminReceipt($event, $category);
        $ledger = app(FinancialLedgerService::class);
        $this->assertEquals(-15, $ledger->buildForEvent($event)['totals']['cape_fees']);
        $this->assertEquals(-15, $ledger->buildFySummaryRow($event)['registration_net']);
        $this->assertCount(0, $ledger->buildForEvent($other)['paymentRows']);
    }

    public function test_free_entry_http_route_still_denies_unassigned_actor_without_writes(): void
    {
        Role::findOrCreate('admin', 'web');
        $actor = User::factory()->create()->assignRole('admin');
        $category = CategoryEvent::factory()->for(Event::factory()->create(['entryFee' => 0]))->create(['entry_fee' => null]);
        $this->actingAs($actor)->postJson(route('admin.category.addPlayer', $category), [
            'registration_id' => Player::factory()->create()->id,
        ])->assertForbidden();
        $this->assertDatabaseCount('category_event_registrations', 0);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_legacy_null_admin_snapshot_without_category_is_not_inferred_free(): void
    {
        $event = Event::factory()->create(['entryFee' => 0, 'cape_tennis_fee' => 15]);
        DB::table('transactions_pf')->insert(['event_id' => $event->id, 'transaction_type' => 'Registration',
            'amount_gross' => 0, 'cape_tennis_fee' => null, 'item_name' => 'Admin Entry', 'is_test' => false,
            'created_at' => now(), 'updated_at' => now()]);
        $this->assertEquals(-15, app(FinancialLedgerService::class)->buildForEvent($event)['totals']['cape_fees']);
    }

    public function test_public_free_finalization_is_idempotent_and_adds_no_financial_records(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['entryFee' => 0, 'cape_tennis_fee' => 15]);
        $category = CategoryEvent::factory()->for($event)->create(['entry_fee' => 0]);
        $registration = Registration::create([]);
        $player = Player::factory()->create();
        $registration->players()->attach($player);
        $registration->categoryEvents()->attach($category, ['user_id' => $user->id, 'payment_status_id' => 0]);
        $order = RegistrationOrder::create(['user_id' => $user->id, 'total_fee' => 0]);
        RegistrationOrderItems::forceCreate(['order_id' => $order->id, 'registration_id' => $registration->id,
            'category_event_id' => $category->id, 'player_id' => $player->id, 'user_id' => $user->id, 'item_price' => 0]);
        $service = app(RegistrationPaymentService::class);
        $service->markFreeOrderPaid($order);
        $service->markFreeOrderPaid($order->fresh());
        $this->assertSame('free', $order->fresh()->payment_method);
        $this->assertFalse((bool) $order->fresh()->payfast_paid);
        $this->assertFalse((bool) $order->fresh()->wallet_debited);
        $this->assertEquals(0, $order->fresh()->payfast_amount_due);
        $this->assertEquals(1, CategoryEventRegistration::sole()->payment_status_id);
        $this->assertDatabaseCount('registration_orders', 1);
        $this->assertDatabaseCount('category_event_registrations', 1);
        $this->assertDatabaseCount('transactions_pf', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertEquals(0, app(FinancialLedgerService::class)->buildForEvent($event)['totals']['cape_fees']);
    }

    public function test_payfast_estimates_charge_nothing_for_zero_and_preserve_positive_formula(): void
    {
        SiteSetting::set('payfast_fee_percentage', '3.2');
        SiteSetting::set('payfast_fee_flat', '2.00');
        SiteSetting::set('payfast_vat_rate', '15');
        $this->assertSame(0.0, SiteSetting::calculatePayfastFee(0));
        $this->assertSame(0.0, SiteSetting::calculatePayfastFeeWithRates(0, 3.2, 2, 15));
        $this->assertSame(0.0, SiteSetting::calculatePayfastFeeWithRates(-1, 3.2, 2, 15));
        $this->assertSame(5.98, SiteSetting::calculatePayfastFee(100));
        $this->assertSame(5.98, SiteSetting::calculatePayfastFeeWithRates(100, 3.2, 2, 15));
    }

    public function test_removed_admin_entry_can_register_again_through_free_public_checkout_without_erasing_history(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['entryFee' => 0, 'cape_tennis_fee' => 15, 'deadline' => 0, 'status' => 'open']);
        $category = CategoryEvent::factory()->for($event)->create(['entry_fee' => null]);
        $player = Player::factory()->create();
        $service = app(EntryService::class);
        $oldEntry = $service->addPlayerAsAdmin($category, $player->id, $user);
        // Preserve a pre-fix fee snapshot exactly as it was captured.
        DB::table('transactions_pf')->where('registration_id', $oldEntry->registration_id)->update(['cape_tennis_fee' => 15]);
        $service->withdrawEntryAsAdmin($oldEntry, $user);

        $this->withoutMiddleware([EnsureAgreementAccepted::class, EnsurePlayerProfileUpdated::class])
            ->actingAs($user)->post(route('pay.now.payfast'), [
                'terms_accepted' => '1', 'player' => [$player->id], 'category' => [$category->id],
            ])->assertRedirect(route('frontend.registration.success', RegistrationOrder::query()->sole()));

        $order = RegistrationOrder::query()->sole();
        $this->assertSame('free', $order->payment_method);
        $this->assertEquals(0, $order->total_fee);
        $this->assertDatabaseHas('category_event_registrations', ['id' => $oldEntry->id,
            'status' => 'withdrawn', 'payment_status_id' => 1]);
        $this->assertNotNull($oldEntry->fresh()->withdrawn_at);
        $this->assertDatabaseCount('category_event_registrations', 2);
        $this->assertDatabaseCount('registrations', 2);
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseHas('transactions_pf', ['registration_id' => $oldEntry->registration_id, 'cape_tennis_fee' => 15]);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame(1, $category->categoryEventRegistrations()->whereNull('withdrawn_at')->where('payment_status_id', 1)->count());
    }

    public function test_registration_eligibility_blocks_live_paid_entries_but_ignores_inactive_or_unpaid_history(): void
    {
        $category = CategoryEvent::factory()->create();
        $player = Player::factory()->create();
        $registration = Registration::create([]);
        $registration->players()->attach($player);
        $registration->categoryEvents()->attach($category, ['payment_status_id' => 1, 'status' => 'active']);
        $entry = CategoryEventRegistration::sole();
        $eligibility = app(EntryEligibilityService::class);
        $statusColumn = collect(\Illuminate\Support\Facades\Schema::getColumns('category_event_registrations'))->firstWhere('name', 'status');
        // Exercise legacy NULL states only when supported by the installed schema;
        // never ALTER a MySQL table inside a RefreshDatabase transaction.
        $liveStatuses = $statusColumn['nullable'] ? ['active', null] : ['active'];
        foreach ($liveStatuses as $status) {
            DB::table('category_event_registrations')->where('id', $entry->id)->update(['status' => $status]);
            try {
                $eligibility->assertCanRegister($category, $player->id);
                $this->fail('Live paid duplicate should remain blocked.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('already registered', $exception->getMessage());
            }
        }
        foreach (['withdrawn', 'withdrawn_pending_refund', 'withdrawn_refunded', 'cancelled'] as $status) {
            DB::table('category_event_registrations')->where('id', $entry->id)->update(['status' => $status]);
            $eligibility->assertCanRegister($category, $player->id);
        }
        DB::table('category_event_registrations')->where('id', $entry->id)->update(['status' => 'active', 'payment_status_id' => 0]);
        $eligibility->assertCanRegister($category, $player->id);
        $this->assertDatabaseCount('category_event_registrations', 1);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    private function adminReceipt(Event $event, CategoryEvent $category): void
    {
        DB::table('transactions_pf')->insert(['event_id' => $event->id, 'category_event_id' => $category->id,
            'transaction_type' => 'Registration', 'amount_gross' => 0, 'cape_tennis_fee' => null,
            'item_name' => 'Admin Entry', 'is_test' => false, 'created_at' => now(), 'updated_at' => now()]);
    }
}
