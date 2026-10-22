<?php

namespace Tests\Feature\Clothing;

use App\Domain\Finance\Services\RefundRequestService;
use App\Models\CategoryEvent;
use App\Models\ClothingItemType;
use App\Models\ClothingOrder;
use App\Models\ClothingRefund;
use App\Models\ClothingSize;
use App\Models\Event;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamRegion;
use App\Models\User;
use App\Services\Clothing\ClothingPayfastRefundRecoveryService;
use App\Services\Clothing\ClothingPayfastRefundService;
use App\Services\Payfast;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClothingRefundReconcileHttpTest extends TestCase
{
    private static bool $schemaReady = false;
    private array $providerTransactionLevels = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertMatchesRegularExpression('/^ct_testing(?:_[a-z0-9_]+)?$/', DB::connection()->getDatabaseName());
        if (! self::$schemaReady) {
            $command = DB::connection()->getDatabaseName() === 'ct_testing_clothing_reconcile_ui' ? 'migrate' : 'migrate:fresh';
            $this->artisan($command, ['--force' => true])->assertExitCode(0);
            self::$schemaReady = true;
        }
        Http::preventStrayRequests();
        $this->app->singleton(Payfast::class, function () { $gateway = new Payfast; $gateway->mode = 'live'; return $gateway; });
    }

    protected function tearDown(): void
    {
        \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
        parent::tearDown();
    }

    private function capture(string $name, $response): void
    {
        if (getenv('CT_QA_CAPTURE_HTML') === '1') {
            $folder = storage_path('app/clothing-reconcile-qa');
            if (! is_dir($folder)) mkdir($folder, 0777, true);
            file_put_contents($folder.'/'.$name.'.html', $response->getContent());
        }
    }

    public function test_check_then_separate_wallet_review_and_confirmation_preserve_attempt_and_credit_once(): void
    {
        [$actor, $payer, $order, $refund] = $this->fixture();
        $event = $order->event;
        $this->fakeProvider();
        $show = route('backend.clothing.refunds.show', [$event, $order]);
        $before = $this->actingAs($actor)->get($show)->assertOk()->assertSee('Check PayFast')->assertDontSee('Refund to wallet instead');
        $this->capture('uncertain-history', $before);
        $attempt = DB::table('clothing_provider_refund_attempts')->where('clothing_refund_id', $refund->id)->first();
        $this->post(route('backend.clothing.refunds.check-payfast', [$event, $order, $refund]))->assertRedirect($show)->assertSessionHasNoErrors();
        $this->assertSame(0, $payer->wallet()->count());
        $this->assertSame('pending', $refund->fresh()->refund_status);
        $this->assertDatabaseHas('clothing_provider_refund_attempts', ['id' => $attempt->id, 'status' => 'reconciled_ready', 'amount' => '395.00']);
        $checked = $this->get($show)->assertOk()->assertSee('No money moved')->assertSee('Refund to wallet instead');
        $this->capture('verified-history', $checked);
        $review = $this->post(route('backend.clothing.refunds.wallet.review', [$event, $order, $refund]))->assertOk()->assertSee($payer->name)->assertSee('395.00')->assertSee('Refund shirt');
        $this->capture('wallet-review', $review);
        $this->assertSame(0, $payer->wallet()->count());
        $url = route('backend.clothing.refunds.wallet.confirm', [$event, $order, $refund]);
        $input = ['review_token' => $review->viewData('reviewToken'), 'confirmed' => 1];
        $this->post($url, $input)->assertSessionHasNoErrors();
        $this->post($url, $input)->assertSessionHasNoErrors();
        $this->assertDatabaseHas('wallet_transactions', ['wallet_id' => $payer->fresh()->wallet->id, 'source_type' => 'clothing_refund', 'source_id' => $refund->id, 'amount' => '395.00']);
        $this->assertSame(1, DB::table('wallet_transactions')->where('source_type', 'clothing_refund')->where('source_id', $refund->id)->count());
        $this->assertDatabaseHas('clothing_provider_refund_attempts', ['id' => $attempt->id, 'status' => 'reconciled_ready', 'created_at' => $attempt->created_at, 'confirmed_at' => null]);
        Http::assertSentCount(8);
        $this->assertSame([0], array_values(array_unique($this->providerTransactionLevels)));
        Http::assertSent(fn ($request) => $request->hasHeader('merchant-id') && $request->hasHeader('signature') && $request->hasHeader('timestamp'));
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public function test_paid_pending_unknown_or_error_provider_history_never_unlocks_or_credits(): void
    {
        [$actor, $payer, $order, $refund] = $this->fixture();
        $url = route('backend.clothing.refunds.check-payfast', [$order->event, $order, $refund]);
        $this->actingAs($payer)->post($url)->assertForbidden();
        $foreign = Event::factory()->create();
        $this->actingAs($actor)->post(route('backend.clothing.refunds.check-payfast', [$foreign, $order, $refund]))->assertNotFound();
        foreach (['Refund', 'Refund Pending', 'Unknown transaction'] as $type) {
            $history = $this->history();
            $history['data']['response']['transactions'][] = ['type' => $type, 'amount' => 39500, 'date' => '2026-09-20 12:00:00'];
            $this->fakeProvider($history);
            $this->post($url)->assertSessionHasErrors('refund')->assertRedirect(route('backend.clothing.refunds.show', [$order->event, $order]));
        }
        $this->fakeProvider(historyStatus: 500);
        $this->post($url)->assertSessionHasErrors('refund');
        $this->assertDatabaseHas('clothing_provider_refund_attempts', ['clothing_refund_id' => $refund->id, 'status' => 'uncertain']);
        $this->assertSame(0, $payer->wallet()->count());
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public function test_new_provider_refund_or_internal_retry_after_review_blocks_wallet_confirmation(): void
    {
        [$actor, $payer, $order, $refund] = $this->fixture();
        $this->fakeProvider();
        $this->actingAs($actor)->post(route('backend.clothing.refunds.check-payfast', [$order->event, $order, $refund]))->assertSessionHasNoErrors();
        $token = $this->post(route('backend.clothing.refunds.wallet.review', [$order->event, $order, $refund]))->assertOk()->viewData('reviewToken');
        $history = $this->history();
        $history['data']['response']['available_balance'] = 61500;
        $this->fakeProvider($history);
        $url = route('backend.clothing.refunds.wallet.confirm', [$order->event, $order, $refund]);
        $this->post($url, ['review_token' => $token, 'confirmed' => 1])->assertSessionHasErrors('refund');
        $this->fakeProvider();
        DB::table('clothing_provider_refund_attempts')->where('clothing_refund_id', $refund->id)->update(['status' => 'dispatching']);
        $this->post($url, ['review_token' => $token, 'confirmed' => 1])->assertSessionHasErrors('refund');
        $this->assertSame(0, $payer->wallet()->count());
        $this->assertSame('pending', $refund->fresh()->refund_status);
        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    public function test_only_a_proven_completed_other_order_wallet_credit_is_unrelated(): void
    {
        [$actor, $payer, $order, $refund] = $this->fixture();
        $this->fakeProvider();
        $wallet = $payer->wallet()->create();
        $unknown = DB::table('wallet_transactions')->insertGetId(['wallet_id' => $wallet->id, 'type' => 'credit', 'amount' => '10.00', 'source_type' => 'manual', 'source_id' => 123, 'created_at' => now(), 'updated_at' => now()]);
        $url = route('backend.clothing.refunds.check-payfast', [$order->event, $order, $refund]);
        $this->actingAs($actor)->post($url)->assertSessionHasErrors('refund');
        DB::table('wallet_transactions')->where('id', $unknown)->delete();
        [$otherActor, , $otherOrder, $otherRefund] = $this->fixture();
        $otherOrder->update(['user_id' => $payer->id]);
        \App\Support\FinanceMutationScope::run('refund_state_write', fn () => $otherRefund->update(['payer_id' => $payer->id]));
        $otherAttempt = DB::table('clothing_provider_refund_attempts')->where('clothing_refund_id', $otherRefund->id)->first();
        DB::table('clothing_provider_refund_attempts')->where('id', $otherAttempt->id)->update(['status' => 'not_dispatched']);
        $fallback = app(\App\Services\Clothing\ClothingRefundWalletFallbackService::class);
        $token = $fallback->review($otherOrder->event, $otherOrder, $otherRefund, $otherActor, null);
        $fallback->confirm($otherOrder->event, $otherOrder, $otherRefund, $otherActor, $token);
        $this->post($url)->assertSessionHasNoErrors();
        $this->assertSame('pending', $refund->fresh()->refund_status);
        $this->assertSame(1, DB::table('wallet_transactions')->where('wallet_id', $wallet->id)->count());
        $existing = DB::table('wallet_transactions')->where('source_type', 'clothing_refund')->where('source_id', $otherRefund->id)->first();
        $foreignWallet = User::factory()->create()->wallet()->create();
        DB::table('wallet_transactions')->insert(['wallet_id' => $foreignWallet->id, 'type' => 'credit', 'amount' => $existing->amount, 'source_type' => 'clothing_refund', 'source_id' => $otherRefund->id, 'created_at' => now(), 'updated_at' => now()]);
        $this->post(route('backend.clothing.refunds.wallet.review', [$order->event, $order, $refund]))->assertSessionHasErrors('refund');

        Http::assertNotSent(fn ($request) => $request->method() !== 'GET');
    }

    private function fakeProvider(?array $history = null, ?array $query = null, int $historyStatus = 200): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake(function ($request) use ($history, $query, $historyStatus) {
            $this->providerTransactionLevels[] = DB::transactionLevel();
            return $request->method() === 'POST'
                ? Http::response(['code' => 200, 'status' => 'success', 'data' => ['response' => true]])
                : (str_contains($request->url(), '/query/') ? Http::response($query ?? $this->providerQuery()) : Http::response($history ?? $this->history(), $historyStatus));
        });
    }

    private function history(): array
    {
        return ['code' => 200, 'status' => 'success', 'data' => ['response' => ['available_balance' => 101000,
            'transactions' => [['amount' => 101000, 'date' => '2026-09-18 10:04:58', 'type' => 'Funds Received']]]]];
    }

    private function providerQuery(): array
    {
        return ['status' => 'REFUNDABLE', 'amount_original' => 101000, 'amount_available_for_refund' => 101000,
            'refund_full' => ['method' => 'BANK_PAYOUT'], 'refund_partial' => ['method' => 'BANK_PAYOUT'],
            'bank_names' => [['bank_name' => 'FNB', 'label' => 'FNB']]];
    }

    private function bank(): array
    {
        return ['account_holder' => 'Original Payer', 'bank_name' => 'FNB', 'branch_code' => '250655',
            'account_number' => '0123456789', 'account_type' => 'current'];
    }

    private function fixture(): array
    {
        $actor = User::factory()->create()->assignRole(Role::findOrCreate('super-user', 'web'));
        $payer = User::factory()->create();
        $event = Event::factory()->create();
        $region = TeamRegion::create(['region_name' => 'Recovery test region']);
        DB::table('event_regions')->insert(['event_id' => $event->id, 'region_id' => $region->id, 'ordering' => 1]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $team = Team::factory()->create(['region_id' => $region->id, 'category_event_id' => $category->id]);
        $catalogue = ClothingItemType::create(['region_id' => $region->id, 'item_type_name' => 'Refund shirt', 'price' => 1010]);
        $size = ClothingSize::create(['item_type' => $catalogue->id, 'size' => 'Medium']);
        $order = ClothingOrder::create(['event_id' => $event->id, 'team_id' => $team->id, 'player_id' => Player::factory()->create()->id,
            'user_id' => $payer->id, 'pay_status' => 1, 'status' => 'completed', 'subtotal' => 1010, 'payfast_fee' => 0,
            'total' => 1010, 'amount_paid' => 1010, 'payfast_paid' => true, 'payfast_pf_payment_id' => 'RECOVERY-'.$payer->id,
            'payfast_amount_due' => 1010, 'wallet_reserved' => 0, 'wallet_debited' => false, 'paid_at' => now()->subMonth(), 'request_token' => (string) str()->uuid()]);
        $item = $order->items()->create(['clothing_order_item_id' => $catalogue->id, 'clothing_item_size' => $size->id,
            'qty' => 1, 'price' => 1010, 'line_total' => 1010, 'item_name' => 'Refund shirt', 'size_name' => 'Medium']);
        $refund = app(RefundRequestService::class)->requestClothingRefund($event, $order, $actor, ['request_token' => (string) str()->uuid(),
            'mode' => 'amount', 'item_id' => $item->id, 'amount' => '395.00', 'method' => 'payfast', 'reason' => 'Asked by admin']);
        DB::table('clothing_provider_refund_attempts')->insert(['clothing_refund_id' => $refund->id, 'clothing_order_id' => $order->id,
            'pf_payment_id' => $order->payfast_pf_payment_id, 'amount' => '395.00', 'requested_by' => $actor->id,
            'status' => 'uncertain', 'created_at' => now()->subHour(), 'updated_at' => now()->subHour(), 'confirmed_at' => null]);
        return [$actor, $payer, $order, $refund];
    }
}
