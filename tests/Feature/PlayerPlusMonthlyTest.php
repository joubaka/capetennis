<?php

namespace Tests\Feature;

use App\Http\Controllers\Frontend\PlayerPlusController;
use App\Models\{AuditEvent, Player, PlayerPlusMembership, PlayerPlusOrder, PlayerPlusSubscription, SiteSetting, Transaction, User};
use App\Services\Performance\{PlayerPlusNotifications, PlayerPlusReconciliation, PlayerPlusService, PlayerPlusSubscriptionGateway, PlayerRatingAccess};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, DB, Http, Mail};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerPlusMonthlyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake(); Bus::fake(); Http::preventStrayRequests();
        SiteSetting::set(PlayerRatingAccess::SETTING, '1');
        SiteSetting::set(PlayerPlusService::MONTHLY_ENABLED_SETTING, '1');
        config(['services.payfast.sandbox' => true, 'services.payfast.sandbox_id' => 'synthetic-plus-merchant',
            'services.payfast.sandbox_key' => 'synthetic-plus-key', 'services.payfast.passphrase_sandbox' => 'synthetic-plus-passphrase']);
        $this->withoutMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class, \App\Http\Middleware\EnsurePlayerProfileUpdated::class]);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-01-31 12:00:00', 'Africa/Johannesburg'));
    }

    private function order(): PlayerPlusOrder
    {
        $order = app(PlayerPlusService::class)->start(Player::factory()->create(), User::factory()->create(), (string) Str::uuid(), 6900, 'monthly', true);
        return app(PlayerPlusService::class)->handoff($order, $order->user);
    }

    private function itn(PlayerPlusOrder $order, string $id = 'synthetic-monthly-1'): array
    {
        return ['custom_str5' => 'PlayerPlus', 'custom_int2' => $order->player_id, 'custom_int4' => $order->user_id,
            'custom_int5' => $order->id, 'payment_status' => 'COMPLETE', 'amount_gross' => '69.00',
            'pf_payment_id' => $id, 'token' => 'synthetic-subscription-token-'.$order->id];
    }

    public function test_monthly_handoff_and_consent_are_explicit_server_priced_and_signed(): void
    {
        $player = Player::factory()->create(); $payer = User::factory()->create();
        $data = ['request_token' => (string) Str::uuid(), 'confirm' => 1, 'terms_version' => PlayerPlusController::TERMS_VERSION,
            'quoted_price_cents' => 6900, 'plan' => 'monthly'];
        $this->actingAs($payer)->postJson(route('player-plus.store', $player), $data)->assertUnprocessable()->assertJsonValidationErrors('recurring_consent');
        $this->assertDatabaseCount('player_plus_orders', 0);
        $this->post(route('player-plus.store', $player), $data + ['recurring_consent' => 1])->assertRedirect();
        $order = PlayerPlusOrder::sole();
        $this->assertSame(6900, $order->price_cents);
        $this->assertSame('monthly', $order->plan);
        $this->assertNotNull($order->recurring_consented_at);
        $this->assertTrue((bool) AuditEvent::where('action', 'player-plus.purchase-details-accepted')->sole()->metadata['recurring_consent']);
        $response = $this->post(route('player-plus.orders.pay', $order), ['amount' => 1])->assertOk();
        foreach (['amount' => '69.00', 'subscription_type' => '1', 'billing_date' => '2026-02-28', 'recurring_amount' => '69.00', 'frequency' => '3', 'cycles' => '0'] as $name => $value) {
            $response->assertSee('name="'.$name.'" value="'.$value.'"', false);
        }
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document); $fields = [];
        foreach ($xpath->query('//form[@id="payfastForm"]/input') as $input) $fields[$input->getAttribute('name')] = $input->getAttribute('value');
        $signature = $fields['signature']; unset($fields['signature']);
        $fields['passphrase'] = 'synthetic-plus-passphrase'; ksort($fields);
        $this->assertSame(md5(http_build_query($fields)), $signature);
        $this->assertDatabaseCount('player_plus_memberships', 0);
    }

    public function test_verified_cycles_are_exact_idempotent_anchored_and_preserve_original_receipts(): void
    {
        $root = $this->order(); $service = app(PlayerPlusService::class);
        $links = DB::table('user_players')->count();
        $service->receive($this->itn($root));
        $service->receive($this->itn($root));
        $first = $root->fresh();
        $this->assertSame('2026-02-28', $first->term_ends_at->toDateString());
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseCount('player_plus_subscription_charges', 1);
        $subscription = PlayerPlusSubscription::sole();
        $this->assertStringNotContainsString('synthetic-subscription-token', $subscription->toJson());
        $this->assertNotSame($this->itn($root)['token'], DB::table('player_plus_subscriptions')->value('provider_token'));
        SiteSetting::set(PlayerPlusService::MONTHLY_PRICE_SETTING, '7900');
        SiteSetting::set(PlayerRatingAccess::SETTING, '0');
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-02-28 03:00:00', 'Africa/Johannesburg'));
        $service->receive($this->itn($root, 'synthetic-monthly-2'));
        $service->receive($this->itn($root, 'synthetic-monthly-2'));
        $renewal = PlayerPlusOrder::where('subscription_order_id', $root->id)->sole();
        $this->assertSame(6900, $renewal->price_cents);
        $this->assertSame('2026-02-28', $renewal->term_starts_at->toDateString());
        $this->assertSame('2026-03-31', $renewal->term_ends_at->toDateString());
        $this->assertEquals($first->term_ends_at, $root->fresh()->term_ends_at);
        $this->assertSame($renewal->id, PlayerPlusMembership::sole()->order_id);
        $this->assertDatabaseCount('transactions_pf', 2);
        $this->assertDatabaseCount('player_plus_orders', 2);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame($links, DB::table('user_players')->count());
        $this->assertSame([$root->id], Transaction::pluck('custom_int5')->unique()->map(fn ($id) => (int) $id)->all());
        $this->assertSame(1, app(PlayerPlusReconciliation::class)->receipts($renewal)->count());
        $statement = app(\App\Services\AccountStatementService::class)->build($root->user, '2026-01-01', '2026-03-01');
        $this->assertSame(13800, $statement['paid']);
        $this->assertCount(2, collect($statement['rows'])->where('method', 'Player Plus / PayFast'));
        $this->assertSame(['queued' => 0], app(PlayerPlusNotifications::class)->record($renewal, 'renewal'));
    }

    public function test_unexpected_extra_charge_and_invalid_identity_never_extend_access(): void
    {
        $root = $this->order(); $service = app(PlayerPlusService::class); $service->receive($this->itn($root));
        $ends = PlayerPlusMembership::sole()->ends_at;
        foreach (['amount_gross' => '68.99', 'token' => 'foreign-token', 'custom_int4' => $root->user_id + 1] as $field => $value) {
            try { $service->receive(array_replace($this->itn($root, 'bad-'.$field), [$field => $value])); $this->fail('Invalid monthly callback accepted'); }
            catch (ValidationException) {}
        }
        $service->receive($this->itn($root, 'unexpected-extra'));
        $service->receive($this->itn($root, 'unexpected-extra'));
        $this->assertEquals($ends, PlayerPlusMembership::sole()->ends_at);
        $this->assertSame('reconciliation_required', PlayerPlusOrder::where('subscription_order_id', $root->id)->sole()->status);
        $this->assertDatabaseCount('transactions_pf', 2);
        $this->assertDatabaseCount('player_plus_orders', 2);
    }

    public function test_cancellation_is_payer_only_and_requires_provider_confirmation_without_revoking_paid_access(): void
    {
        $root = $this->order(); $service = app(PlayerPlusService::class); $service->receive($this->itn($root));
        $ends = PlayerPlusMembership::sole()->ends_at;
        $url = route('player-plus.orders.stop-renewal', $root);
        $this->actingAs(User::factory()->create())->post($url)->assertForbidden();
        Http::assertNothingSent();
        Http::fake(['*' => Http::response(['code' => 500, 'status' => 'failed'], 500)]);
        $this->actingAs($root->user)->postJson($url)->assertUnprocessable();
        $this->assertSame('cancel_pending', PlayerPlusSubscription::sole()->status);
        $this->assertNull(PlayerPlusSubscription::sole()->cancelled_at);
        $this->assertEquals($ends, PlayerPlusMembership::sole()->ends_at);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-02-28 03:00:00', 'Africa/Johannesburg'));
        $service->receive($this->itn($root, 'charge-during-cancellation'));
        $this->assertEquals($ends, PlayerPlusMembership::sole()->ends_at);
        Http::fake(['*' => Http::response(['code' => 200, 'status' => 'success', 'data' => ['response' => true]])]);
        $this->post($url)->assertRedirect();
        $this->assertSame('cancelled', PlayerPlusSubscription::sole()->status);
        $this->assertNotNull(PlayerPlusSubscription::sole()->cancelled_at);
        $this->assertEquals($ends, PlayerPlusMembership::sole()->ends_at);
        $count = Http::recorded()->count();
        $this->post($url)->assertRedirect();
        $this->assertCount($count, Http::recorded());
        $this->assertSame(2, AuditEvent::where('action', 'like', 'player-plus.subscription-%')->count());
    }

    public function test_late_initial_and_missed_cycle_payments_are_reconciled_without_free_access(): void
    {
        $root = $this->order(); $service = app(PlayerPlusService::class);
        $this->travel(6)->minutes();
        $service->receive($this->itn($root));
        $service->receive($this->itn($root, 'second-late-initial'));
        $this->assertDatabaseCount('player_plus_memberships', 0);
        $this->assertDatabaseCount('transactions_pf', 2);
        $this->assertSame('cancel_pending', PlayerPlusSubscription::sole()->status);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-01-31 12:00:00', 'Africa/Johannesburg'));
        $other = $this->order(); $service->receive($this->itn($other, 'other-first'));
        $ends = $other->fresh()->term_ends_at;
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-04-02 12:00:00', 'Africa/Johannesburg'));
        $service->receive($this->itn($other, 'missed-cycle-charge'));
        $this->assertEquals($ends, PlayerPlusMembership::sole()->ends_at);
        $this->assertSame('cancel_pending', $other->fresh()->subscription->status);
    }

    public function test_monthly_setting_is_super_user_only_and_does_not_change_existing_prices(): void
    {
        $url = route('settings.player-plus-monthly');
        $this->actingAs(User::factory()->create())->postJson($url, ['player_plus_monthly_price' => '79', 'player_plus_monthly_enabled' => true])->assertForbidden();
        Role::findOrCreate('super-user', 'web');
        $admin = User::factory()->create()->assignRole('super-user');
        $root = $this->order();
        $this->actingAs($admin)->postJson($url, ['player_plus_monthly_price' => '4.99', 'player_plus_monthly_enabled' => true])->assertUnprocessable();
        $this->postJson($url, ['player_plus_monthly_price' => '79.95', 'player_plus_monthly_enabled' => false])->assertOk();
        $this->assertSame(7995, PlayerPlusService::monthlyPriceCents());
        $this->assertFalse(PlayerPlusService::monthlyEnabled());
        $this->assertSame(6900, $root->fresh()->price_cents);
    }

    public function test_cancel_gateway_recovers_lost_response_only_from_matching_explicit_cancelled_status(): void
    {
        Http::fake(['*/cancel*' => Http::response(['code' => 400, 'status' => 'failed'], 400),
            '*/fetch*' => Http::response(['code' => 200, 'status' => 'success', 'data' => ['response' => ['token' => 'test-token', 'status_text' => 'CANCELLED']]])]);
        $this->assertTrue(app(PlayerPlusSubscriptionGateway::class)->cancel('test-token'));
        Http::assertSent(function ($request) {
            if ($request->method() !== 'PUT') return false;
            $signed = ['merchant-id' => 'synthetic-plus-merchant', 'version' => 'v1', 'timestamp' => $request->header('timestamp')[0], 'passphrase' => 'synthetic-plus-passphrase'];
            ksort($signed);
            return $request->header('signature')[0] === md5(http_build_query($signed)) && str_ends_with($request->url(), '/cancel?testing=true');
        });
        Http::fake(['*/cancel*' => Http::response([], 400), '*/fetch*' => Http::response(['code' => 200, 'status' => 'success', 'data' => ['response' => ['token' => 'wrong-token', 'status_text' => 'CANCELLED']]])]);
        $this->assertFalse(app(PlayerPlusSubscriptionGateway::class)->cancel('test-token'));
    }
}
