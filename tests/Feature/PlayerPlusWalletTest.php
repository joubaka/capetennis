<?php

namespace Tests\Feature;

use App\Domain\Payments\Services\LedgerService;
use App\Http\Controllers\Frontend\PlayerPlusController;
use App\Models\{AuditEvent, Player, PlayerPlusMembership, PlayerPlusOrder, RegistrationOrder, SiteSetting, User, WalletTransaction};
use App\Services\AccountStatementService;
use App\Services\Performance\{PlayerPlusEntitlement, PlayerPlusService, PlayerRatingAccess};
use App\Support\Audit\AuditWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, DB, Http, Mail};
use Illuminate\Support\Str;
use Tests\TestCase;

class PlayerPlusWalletTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake(); Bus::fake(); Http::preventStrayRequests();
        SiteSetting::set(PlayerRatingAccess::SETTING, '1');
        SiteSetting::set(PlayerPlusService::PRICE_SETTING, '69900');
        $this->withoutMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class, \App\Http\Middleware\EnsurePlayerProfileUpdated::class]);
    }

    private function funds(User $payer, float $amount = 1000): void
    {
        $wallet = $payer->wallet()->firstOrCreate();
        app(LedgerService::class)->appendWalletCredit($wallet, $amount, 'synthetic_plus_wallet_credit', $payer->id);
    }

    private function data(): array
    {
        return ['request_token' => (string) Str::uuid(), 'confirm' => '1', 'wallet_consent' => '1', 'plan' => 'annual',
            'payment_method' => 'wallet', 'terms_version' => PlayerPlusController::TERMS_VERSION, 'quoted_price_cents' => 69900];
    }

    public function test_wallet_purchase_is_exact_atomic_once_off_idempotent_and_payer_private(): void
    {
        $payer = User::factory()->create(); $player = Player::factory()->create(['userId' => $payer->id]);
        $this->funds($payer); $this->actingAs($payer); $data = $this->data();
        $url = route('player-plus.store', $player);
        $this->post($url, $data)->assertRedirect();
        $order = PlayerPlusOrder::sole(); $snapshot = $order->getAttributes();
        $this->assertSame('annual', $order->plan); $this->assertTrue($order->pay_status); $this->assertFalse($order->payfast_paid);
        $this->assertNull($order->payfast_handed_off_at); $this->assertNull($order->payfast_pf_payment_id); $this->assertNull($order->recurring_consented_at);
        $this->assertSame(301.0, $payer->wallet()->first()->balance);
        // MySQL returns DECIMAL as a string; SQLite returns this whole amount as an integer.
        $this->assertSame(699.0, (float) WalletTransaction::where('source_type', PlayerPlusOrder::WALLET_SOURCE)->sole()->getRawOriginal('amount'));
        $this->assertTrue($order->hasVerifiedSettlement());
        $this->assertTrue(app(PlayerPlusEntitlement::class)->forPlayer($payer, $player));
        $this->assertTrue($order->term_starts_at->addYearNoOverflow()->eq($order->term_ends_at));
        $this->post($url, $data)->assertRedirect(route('player-plus.orders.show', $order));
        $this->assertSame($snapshot, $order->fresh()->getAttributes());
        $this->assertDatabaseCount('player_plus_orders', 1); $this->assertDatabaseCount('player_plus_memberships', 1);
        $this->assertDatabaseCount('player_plus_subscriptions', 0); $this->assertDatabaseCount('transactions_pf', 0);
        $this->assertSame(1, WalletTransaction::where('source_type', PlayerPlusOrder::WALLET_SOURCE)->count());
        $this->assertSame(1, AuditEvent::where('action', 'player-plus.purchase-details-accepted')->count());
        $this->postJson($url, $this->data())->assertUnprocessable();
        $this->get(route('player-plus.orders.show', $order))->assertOk()->assertSee('Paid from your wallet')->assertDontSee('name="payfastForm"', false);
        $statement = app(AccountStatementService::class)->build($payer, now()->toDateString(), now()->toDateString());
        $this->assertSame('Player Plus / Wallet', $statement['rows']->sole()['method']);
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('player-plus.orders.show', $order))->assertForbidden();
        $this->postJson($url, $data)->assertStatus(409);
        $this->actingAs($payer)->postJson(route('player-plus.store', Player::factory()->create()), $data)->assertStatus(409);
    }

    public function test_sponsoring_does_not_link_player_or_grant_private_access(): void
    {
        $payer = User::factory()->create(); $player = Player::factory()->create(); $this->funds($payer);
        $this->actingAs($payer)->post(route('player-plus.store', $player), $this->data())->assertRedirect();
        $this->assertDatabaseCount('user_players', 0);
        $this->assertFalse(app(PlayerPlusEntitlement::class)->forPlayer($payer, $player));
    }

    public function test_validation_and_insufficient_funds_leave_no_financial_records(): void
    {
        $payer = User::factory()->create(); $player = Player::factory()->create(); $this->actingAs($payer);
        $url = route('player-plus.store', $player); $data = $this->data();
        foreach (['confirm', 'wallet_consent'] as $missing) $this->postJson($url, array_diff_key($data, [$missing => true]))->assertUnprocessable();
        foreach ([['plan' => 'monthly'], ['plan' => 'annual_recurring'], ['recurring_consent' => '1'], ['quoted_price_cents' => 69899]] as $change) {
            $this->postJson($url, array_replace($data, $change))->assertUnprocessable();
        }
        $this->postJson($url, $data)->assertUnprocessable();
        $this->funds($payer, 698.99); $this->postJson($url, $data)->assertUnprocessable();
        $this->assertDatabaseCount('player_plus_orders', 0); $this->assertDatabaseCount('player_plus_memberships', 0);
        $this->assertSame(0, AuditEvent::where('action', 'player-plus.purchase-details-accepted')->count());
        $this->assertSame(0, WalletTransaction::where('type', 'debit')->count());
    }

    public function test_existing_and_stale_reservations_remain_protected_across_events(): void
    {
        $payer = User::factory()->create(); $player = Player::factory()->create(); $this->funds($payer);
        $hold = RegistrationOrder::create(['user_id' => $payer->id, 'wallet_reserved' => 301.01, 'wallet_debited' => false, 'status' => 'pending']);
        DB::table('registration_orders')->where('id', $hold->id)->update(['created_at' => now()->subDays(2)]);
        $this->assertSame(69899, app(PlayerPlusService::class)->walletAvailableCents($payer));
        $this->actingAs($payer)->postJson(route('player-plus.store', $player), $this->data())->assertUnprocessable();
        $this->assertDatabaseCount('player_plus_orders', 0);
        $this->assertSame(1000.0, $payer->wallet()->first()->balance);
        $this->assertSame(301.01, $hold->fresh()->wallet_reserved);
        $hold->delete();
        $teamHold = \App\Models\TeamPaymentOrder::create(['user_id' => $payer->id, 'wallet_reserved' => 301.01, 'wallet_debited' => false]);
        $this->assertSame(69899, app(PlayerPlusService::class)->walletAvailableCents($payer));
        $this->postJson(route('player-plus.store', $player), $this->data())->assertUnprocessable();
        $this->assertSame(301.01, $teamHold->fresh()->wallet_reserved);
        $this->assertDatabaseCount('player_plus_orders', 0);
    }

    public function test_wallet_never_converts_an_inflight_payfast_checkout_or_bypasses_sales_pause(): void
    {
        $payer = User::factory()->create(); $player = Player::factory()->create(); $this->funds($payer);
        $order = app(PlayerPlusService::class)->start($player, $payer, (string) Str::uuid());
        $order = app(PlayerPlusService::class)->handoff($order, $payer); $snapshot = $order->getAttributes();
        $this->actingAs($payer)->postJson(route('player-plus.store', $player), $this->data())->assertUnprocessable();
        $this->assertSame($snapshot, $order->fresh()->getAttributes());
        SiteSetting::set(PlayerRatingAccess::SETTING, '0');
        $this->postJson(route('player-plus.store', Player::factory()->create()), $this->data())->assertNotFound();
        $this->assertSame(0, WalletTransaction::where('type', 'debit')->count());
    }

    public function test_forged_ledger_evidence_and_payfast_callback_cannot_settle_wallet_order(): void
    {
        $payer = User::factory()->create(); $player = Player::factory()->create(['userId' => $payer->id]); $this->funds($payer);
        $this->actingAs($payer)->post(route('player-plus.store', $player), $this->data())->assertRedirect();
        $order = PlayerPlusOrder::sole(); $snapshot = $order->getAttributes(); $service = app(PlayerPlusService::class);
        try {
            $service->receive(['payment_status' => 'COMPLETE', 'custom_str5' => 'PlayerPlus', 'custom_int2' => $player->id,
                'custom_int4' => $payer->id, 'custom_int5' => $order->id, 'amount_gross' => '699.00', 'pf_payment_id' => 'forged-wallet-provider']);
            $this->fail('Wallet checkout accepted a provider callback.');
        } catch (\Illuminate\Validation\ValidationException) {}
        $this->assertDatabaseCount('transactions_pf', 0); $this->assertSame($snapshot, $order->fresh()->getAttributes());
        $debit = WalletTransaction::where('source_type', PlayerPlusOrder::WALLET_SOURCE)->sole();
        foreach ([['amount' => 698.99], ['source_type' => 'event_registration_wallet_payment'], ['type' => 'credit']] as $change) {
            $original = $debit->getAttributes(); DB::table('wallet_transactions')->where('id', $debit->id)->update($change);
            $this->assertFalse($order->hasVerifiedSettlement());
            $this->assertFalse(app(PlayerPlusEntitlement::class)->forPlayer($payer, $player));
            DB::table('wallet_transactions')->where('id', $debit->id)->update($original);
        }
        $other = User::factory()->create(); $otherWallet = $other->wallet()->create();
        $duplicate = $debit->getAttributes(); unset($duplicate['id']); $duplicate['wallet_id'] = $otherWallet->id;
        $duplicateId = DB::table('wallet_transactions')->insertGetId($duplicate);
        $this->assertFalse($order->hasVerifiedSettlement());
        DB::table('wallet_transactions')->where('id', $duplicateId)->delete();
        DB::table('wallet_transactions')->where('id', $debit->id)->update(['wallet_id' => $otherWallet->id]);
        $this->assertFalse($order->hasVerifiedSettlement());
    }

    public function test_audit_failure_rolls_back_wallet_purchase_and_membership(): void
    {
        $payer = User::factory()->create(); $player = Player::factory()->create(); $this->funds($payer);
        $mock = \Mockery::mock(AuditWriter::class); $mock->shouldReceive('record')->andReturn(false); $this->app->instance(AuditWriter::class, $mock);
        $this->actingAs($payer)->postJson(route('player-plus.store', $player), $this->data())->assertServerError();
        $this->assertDatabaseCount('player_plus_orders', 0); $this->assertDatabaseCount('player_plus_memberships', 0);
        $this->assertSame(1000.0, $payer->wallet()->first()->balance); $this->assertSame(0, WalletTransaction::where('type', 'debit')->count());
    }

    public function test_review_shows_own_wallet_only_and_does_not_require_recurring_consent(): void
    {
        $payer = User::factory()->create(); $player = Player::factory()->create();
        $empty = $this->actingAs($payer)->get(route('player-plus.player', $player))->assertOk()->assertSee('Your available wallet funds do not cover this purchase.');
        if (getenv('CT_PLUS_QA_HTML') === '1') {
            \Illuminate\Support\Facades\File::ensureDirectoryExists(storage_path('framework/testing'));
            file_put_contents(storage_path('framework/testing/player-plus-wallet-insufficient.html'), $empty->getContent());
        }
        $this->funds($payer);
        $response = $this->actingAs($payer)->get(route('player-plus.player', $player))->assertOk()->assertSee('R1000.00')->assertSee('Pay from wallet');
        $document = new \DOMDocument; $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING); $xpath = new \DOMXPath($document);
        $this->assertSame(0, $xpath->query('//form[@data-plus-plan="annual"]//input[@name="recurring_consent"]')->length);
        $this->assertSame(2, $xpath->query('//form[@data-plus-plan="annual"]//input[@type="checkbox" and @required and not(@checked)]')->length);
        if (getenv('CT_PLUS_QA_HTML') === '1') {
            \Illuminate\Support\Facades\File::ensureDirectoryExists(storage_path('framework/testing'));
            file_put_contents(storage_path('framework/testing/player-plus-wallet.html'), $response->getContent());
        }
        $this->assertDatabaseCount('player_plus_orders', 0);
    }

    public function test_failure_after_debit_rolls_back_ledger_membership_order_and_audit(): void
    {
        $payer = User::factory()->create(); $player = Player::factory()->create(); $this->funds($payer);
        $mock = \Mockery::mock(\App\Services\Performance\PlayerPlusNotifications::class);
        $mock->shouldReceive('record')->once()->andThrow(new \RuntimeException('Synthetic post-debit failure'));
        $this->app->instance(\App\Services\Performance\PlayerPlusNotifications::class, $mock);
        $this->actingAs($payer)->postJson(route('player-plus.store', $player), $this->data())->assertServerError();
        $this->assertDatabaseCount('player_plus_orders', 0); $this->assertDatabaseCount('player_plus_memberships', 0);
        $this->assertSame(0, AuditEvent::where('action', 'player-plus.purchase-details-accepted')->count());
        $this->assertSame(0, WalletTransaction::where('type', 'debit')->count());
        $this->assertSame(1000.0, $payer->wallet()->first()->balance);
    }

    public function test_suspended_and_admin_granted_access_block_wallet_purchase(): void
    {
        $payer = User::factory()->create(); $player = Player::factory()->create(); $this->funds($payer);
        $override = \App\Models\PlayerPlusAccessOverride::create(['player_id' => $player->id, 'mode' => 'suspended', 'version' => 1]);
        $this->actingAs($payer)->postJson(route('player-plus.store', $player), $this->data())->assertUnprocessable();
        $override->update(['mode' => 'grant', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $this->postJson(route('player-plus.store', $player), $this->data())->assertUnprocessable();
        $this->assertDatabaseCount('player_plus_orders', 0); $this->assertSame(0, WalletTransaction::where('type', 'debit')->count());
    }
}
