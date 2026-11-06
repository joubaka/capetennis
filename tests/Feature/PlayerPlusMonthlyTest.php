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
        // Keep legacy-price fixtures explicit; new offers default to R699.
        SiteSetting::set(PlayerPlusService::PRICE_SETTING, '49900');
        Mail::fake(); Bus::fake(); Http::preventStrayRequests();
        SiteSetting::set(PlayerRatingAccess::SETTING, '1');
        SiteSetting::set(PlayerPlusService::MONTHLY_ENABLED_SETTING, '1');
        config(['services.payfast.sandbox' => true, 'services.payfast.sandbox_id' => 'synthetic-plus-merchant',
            'services.payfast.sandbox_key' => 'synthetic-plus-key', 'services.payfast.passphrase_sandbox' => 'synthetic-plus-passphrase']);
        $this->withoutMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class, \App\Http\Middleware\EnsurePlayerProfileUpdated::class]);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-01-31 12:00:00', 'Africa/Johannesburg'));
    }

    private function order(string $plan = 'monthly'): PlayerPlusOrder
    {
        $order = app(PlayerPlusService::class)->start(Player::factory()->create(), User::factory()->create(), (string) Str::uuid(),
            $plan === 'monthly' ? 6900 : PlayerPlusService::priceCents(), $plan, true);
        return app(PlayerPlusService::class)->handoff($order, $order->user);
    }

    private function itn(PlayerPlusOrder $order, string $id = 'synthetic-monthly-1'): array
    {
        return ['custom_str5' => 'PlayerPlus', 'custom_int2' => $order->player_id, 'custom_int4' => $order->user_id,
            'custom_int5' => $order->id, 'payment_status' => 'COMPLETE', 'amount_gross' => PlayerPlusService::amount($order->price_cents),
            'pf_payment_id' => $id, 'token' => 'synthetic-subscription-token-'.$order->id];
    }

    public function test_monthly_handoff_and_consent_are_explicit_server_priced_and_signed(): void
    {
        $player = Player::factory()->create(); $payer = User::factory()->create();
        $data = ['request_token' => (string) Str::uuid(), 'confirm' => 1, 'terms_version' => PlayerPlusController::TERMS_VERSION,
            'quoted_price_cents' => 6900, 'plan' => 'monthly'];
        $this->actingAs($payer)->postJson(route('player-plus.store', $player), $data)->assertUnprocessable()->assertJsonValidationErrors('recurring_consent');
        $this->assertDatabaseCount('player_plus_orders', 0);
        $response = $this->post(route('player-plus.store', $player), $data + ['recurring_consent' => 1])->assertOk();
        $order = PlayerPlusOrder::sole();
        $this->assertSame(6900, $order->price_cents);
        $this->assertSame('monthly', $order->plan);
        $this->assertNotNull($order->recurring_consented_at);
        $this->assertTrue((bool) AuditEvent::where('action', 'player-plus.purchase-details-accepted')->sole()->metadata['recurring_consent']);
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
        $this->assertSame('2026-03-28', $renewal->term_ends_at->toDateString());
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
        $cancelConfirmed = false;
        Http::fake(function () use (&$cancelConfirmed) {
            return $cancelConfirmed ? Http::response(['code' => 200, 'status' => 'success', 'data' => ['response' => true]])
                : Http::response(['code' => 500, 'status' => 'failed'], 500);
        });
        $this->actingAs($root->user)->postJson($url)->assertUnprocessable();
        $this->assertSame('cancel_pending', PlayerPlusSubscription::sole()->status);
        $this->assertNull(PlayerPlusSubscription::sole()->cancelled_at);
        $this->assertEquals($ends, PlayerPlusMembership::sole()->ends_at);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-02-28 03:00:00', 'Africa/Johannesburg'));
        $service->receive($this->itn($root, 'charge-during-cancellation'));
        $this->assertEquals($ends, PlayerPlusMembership::sole()->ends_at);
        $cancelConfirmed = true;
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
        $payer = User::factory()->create();
        $pending = app(PlayerPlusService::class)->start(Player::factory()->create(), $payer, (string) Str::uuid(), 6900, 'monthly', true);
        $this->actingAs($admin)->postJson($url, ['player_plus_monthly_price' => '4.99', 'player_plus_monthly_enabled' => true])->assertUnprocessable();
        $this->postJson($url, ['player_plus_monthly_price' => '79.95', 'player_plus_monthly_enabled' => false])->assertOk();
        $this->assertSame(7995, PlayerPlusService::monthlyPriceCents());
        $this->assertFalse(PlayerPlusService::monthlyEnabled());
        $this->assertSame(6900, $root->fresh()->price_cents);
        $this->actingAs($payer)->get(route('player-plus.orders.show', $pending))->assertOk()
            ->assertSee('Close checkout')->assertDontSee(route('player-plus.orders.pay', $pending), false);
    }

    public function test_cancel_gateway_recovers_lost_response_only_from_matching_explicit_cancelled_status(): void
    {
        $remoteToken = 'test-token';
        Http::fake(function ($request) use (&$remoteToken) {
            return $request->method() === 'PUT' ? Http::response(['code' => 400, 'status' => 'failed'], 400)
                : Http::response(['code' => 200, 'status' => 'success', 'data' => ['response' => ['token' => $remoteToken, 'status_text' => 'CANCELLED']]]);
        });
        $this->assertTrue(app(PlayerPlusSubscriptionGateway::class)->cancel('test-token'));
        Http::assertSent(function ($request) {
            if ($request->method() !== 'PUT') return false;
            $signed = ['merchant-id' => 'synthetic-plus-merchant', 'version' => 'v1', 'timestamp' => $request->header('timestamp')[0], 'passphrase' => 'synthetic-plus-passphrase'];
            ksort($signed);
            return $request->header('signature')[0] === md5(http_build_query($signed)) && str_ends_with($request->url(), '/cancel?testing=true');
        });
        $remoteToken = 'wrong-token';
        $this->assertFalse(app(PlayerPlusSubscriptionGateway::class)->cancel('test-token'));
    }

    public function test_monthly_handoff_requires_effective_provider_credentials_without_changing_the_order(): void
    {
        $order = app(PlayerPlusService::class)->start(Player::factory()->create(), User::factory()->create(), (string) Str::uuid(), 6900, 'monthly', true);
        config(['services.payfast.passphrase_sandbox' => null, 'services.payfast.passphrase' => null]);
        try { app(PlayerPlusService::class)->handoff($order, $order->user); $this->fail('Missing monthly passphrase was accepted'); }
        catch (ValidationException) {}
        $this->assertNull($order->fresh()->payfast_handed_off_at);
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertDatabaseCount('player_plus_subscriptions', 0);
    }

    public function test_pending_annual_handoff_is_blocked_when_a_late_monthly_mandate_is_discovered(): void
    {
        $monthly = $this->order();
        $this->travel(6)->minutes();
        $annual = app(PlayerPlusService::class)->start($monthly->player, $monthly->user, (string) Str::uuid());
        app(PlayerPlusService::class)->receive($this->itn($monthly));
        try { app(PlayerPlusService::class)->handoff($annual, $annual->user); $this->fail('Overlapping mandate was accepted'); }
        catch (ValidationException) {}
        $this->assertNull($annual->fresh()->payfast_handed_off_at);
        $this->assertDatabaseCount('player_plus_memberships', 0);
    }

    public function test_monthly_player_order_and_settings_pages_render_with_clear_plan_and_cancellation_controls(): void
    {
        $root = $this->order();
        $this->actingAs($root->user);
        $player = $this->get(route('player-plus.player', $root->player))->assertOk()->assertSee('R69.00')
            ->assertSee('name="recurring_consent"', false)->assertSee('automatic monthly payments');
        app(PlayerPlusService::class)->receive($this->itn($root));
        $order = $this->get(route('player-plus.orders.show', $root))->assertOk()->assertSee('Stop automatic renewal');
        Role::findOrCreate('super-user', 'web');
        $admin = User::factory()->create()->assignRole('super-user');
        $settings = $this->actingAs($admin)->get(route('settings.index'))->assertOk()->assertSee('Monthly price per player (R)')
            ->assertSee('Offer monthly automatic renewal');
        if (getenv('CT_PLUS_QA_HTML') === '1') {
            \Illuminate\Support\Facades\File::ensureDirectoryExists(storage_path('framework/testing'));
            foreach (['player' => $player, 'order' => $order, 'settings' => $settings] as $name => $response) {
                file_put_contents(storage_path('framework/testing/player-plus-monthly-'.$name.'.html'), $response->getContent());
            }
        }
    }

    public function test_new_annual_subscription_has_explicit_consent_frequency_six_and_verified_yearly_renewal(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2028-02-29 12:00:00', 'Africa/Johannesburg'));
        $root = $this->order('annual_recurring'); $service = app(PlayerPlusService::class);
        $form = $service->form($root);
        $this->assertStringContainsString('name="frequency" value="6"', $form);
        $this->assertStringContainsString('name="billing_date" value="2029-02-28"', $form);
        $this->assertStringContainsString('name="recurring_amount" value="499.00"', $form);
        $service->receive($this->itn($root));
        $first = $root->fresh();
        $this->assertSame('2029-02-28', $first->term_ends_at->toDateString());
        $this->travelTo(\Illuminate\Support\Carbon::parse('2029-02-28 03:00:00', 'Africa/Johannesburg'));
        $service->receive($this->itn($root, 'annual-second'));
        $service->receive($this->itn($root, 'annual-second'));
        $renewal = PlayerPlusOrder::where('subscription_order_id', $root->id)->sole();
        $this->assertSame('annual_recurring', $renewal->plan);
        $this->assertSame('2030-02-28', $renewal->term_ends_at->toDateString());
        $this->assertSame('2029-02-28', PlayerPlusSubscription::sole()->billing_anchor_at->toDateString());
        $this->assertEquals($first->term_ends_at, $root->fresh()->term_ends_at);
        $this->assertDatabaseCount('transactions_pf', 2);
        $this->assertDatabaseCount('player_plus_subscription_charges', 2);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame(['queued' => 0], app(PlayerPlusNotifications::class)->record($renewal, 'renewal'));
        $page = $this->actingAs($root->user)->get(route('player-plus.orders.show', $root))->assertOk()->assertSee('Annual automatic renewal');
        if (getenv('CT_PLUS_QA_HTML') === '1') {
            \Illuminate\Support\Facades\File::ensureDirectoryExists(storage_path('framework/testing'));
            file_put_contents(storage_path('framework/testing/player-plus-annual-order.html'), $page->getContent());
        }
    }

    public function test_storefront_rejects_new_once_off_annual_but_existing_once_off_checkout_is_unchanged(): void
    {
        $player = Player::factory()->create(); $payer = User::factory()->create();
        $data = ['request_token' => (string) Str::uuid(), 'confirm' => 1, 'terms_version' => PlayerPlusController::TERMS_VERSION,
            'quoted_price_cents' => 49900, 'recurring_consent' => 1];
        $this->actingAs($payer)->postJson(route('player-plus.store', $player), $data + ['plan' => 'annual'])->assertUnprocessable()->assertJsonValidationErrors('plan');
        $this->postJson(route('player-plus.store', $player), array_replace($data, ['plan' => 'annual_recurring', 'recurring_consent' => 0]))
            ->assertUnprocessable()->assertJsonValidationErrors('recurring_consent');
        $this->assertDatabaseCount('player_plus_orders', 0);
        $this->post(route('player-plus.store', $player), $data + ['plan' => 'annual_recurring'])->assertOk()->assertViewIs('frontend.player-plus.launch');
        // The legacy internal start represents an already existing annual checkout; paying never converts its plan.
        $other = Player::factory()->create();
        $old = app(PlayerPlusService::class)->start($other, $payer, (string) Str::uuid());
        $old = app(PlayerPlusService::class)->handoff($old, $payer);
        $this->assertStringNotContainsString('name="subscription_type"', app(PlayerPlusService::class)->form($old));
        app(PlayerPlusService::class)->receive(array_diff_key($this->itn($old, 'old-once-off'), ['token' => true]));
        $this->assertSame('annual', $old->fresh()->plan);
        $this->assertNull($old->fresh()->recurring_consented_at);
        $this->assertNull($old->fresh()->subscription);
        $this->assertSame('2027-01-31 12:00:00', $old->fresh()->term_ends_at->timezone('Africa/Johannesburg')->format('Y-m-d H:i:s'));
    }

    public function test_invalid_initial_mandate_queues_provider_stop_and_audits_a_system_actor_without_access(): void
    {
        $root = $this->order(); $service = app(PlayerPlusService::class);
        $this->travel(6)->minutes();
        $service->receive($this->itn($root));
        Bus::assertDispatched(\App\Jobs\StopPlayerPlusSubscription::class);
        $subscription = PlayerPlusSubscription::sole();
        $requestAudit = AuditEvent::where('action', 'player-plus.subscription-automatic-cancellation-requested')->sole();
        $this->assertSame('system', $requestAudit->actor_type);
        $this->assertNull($requestAudit->actor_id);
        Http::fake(['*' => Http::response(['code' => 200, 'status' => 'success', 'data' => ['response' => true]])]);
        (new \App\Jobs\StopPlayerPlusSubscription($subscription->id))->handle($service);
        $this->assertSame('cancelled', $subscription->fresh()->status);
        $this->assertDatabaseCount('player_plus_memberships', 0);
        $this->assertSame('reconciliation_required', $root->fresh()->status);
        $this->assertDatabaseCount('transactions_pf', 1);
    }

    public function test_signed_recurring_http_callback_and_cross_order_receipt_token_binding_are_required(): void
    {
        $root = $this->order();
        $data = $this->itn($root) + ['merchant_id' => 'synthetic-plus-merchant'];
        $this->post('/notify', $data + ['signature' => 'invalid'])->assertStatus(400);
        $data['signature'] = md5(http_build_query($data).'&passphrase=synthetic-plus-passphrase');
        $this->post('/notify', $data)->assertOk();
        $other = $this->order();
        try {
            app(PlayerPlusService::class)->receive(array_replace($this->itn($other, 'other-token-attempt'), ['token' => $this->itn($root)['token']]));
            $this->fail('Subscription token reused by another payer/order');
        } catch (ValidationException) {}
        try {
            app(PlayerPlusService::class)->receive($this->itn($other));
            $this->fail('Provider receipt reused by another payer/order');
        } catch (ValidationException $exception) {
            $this->assertSame(['Provider receipt belongs to another subscription.'], $exception->errors()['membership']);
        }
        $this->assertFalse($other->fresh()->payfast_paid);
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseCount('player_plus_memberships', 1);
        $this->assertDatabaseCount('player_plus_subscriptions', 1);
    }

    public function test_payer_can_review_and_stop_renewal_with_stale_profile_and_unsigned_agreement_but_cannot_start_new_payment(): void
    {
        $root = $this->order();
        app(PlayerPlusService::class)->receive($this->itn($root));
        $root->player->update(['profile_updated_at' => now()->subYears(2)]);
        $root->user->players()->attach($root->player_id);
        \App\Models\Agreement::create(['title' => 'Synthetic current agreement', 'version' => 'synthetic-current', 'content' => 'Synthetic agreement text', 'is_active' => true]);
        SiteSetting::set('require_code_of_conduct', '1');
        SiteSetting::set('require_profile_update', '1');
        $this->withMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class, \App\Http\Middleware\EnsurePlayerProfileUpdated::class]);
        $this->actingAs($root->user)->get(route('player-plus.orders.index'))->assertOk();
        $this->get(route('player-plus.orders.show', $root))->assertOk();
        $this->postJson(route('player-plus.store', Player::factory()->create()))->assertForbidden();
        $this->postJson(route('player-plus.orders.pay', $root))->assertForbidden();
        SiteSetting::set('require_code_of_conduct', '0');
        $this->postJson(route('player-plus.orders.pay', $root))->assertForbidden()->assertJsonPath('redirect', route('player.profiles.pending'));
        Http::fake(['*' => Http::response(['code' => 200, 'status' => 'success', 'data' => ['response' => true]])]);
        $this->post(route('player-plus.orders.stop-renewal', $root))->assertRedirect();
        $this->assertNotNull($root->fresh()->subscription->cancelled_at);
        $sent = Http::recorded()->count();
        $this->actingAs(User::factory()->create())->get(route('player-plus.orders.show', $root))->assertForbidden();
        $this->post(route('player-plus.orders.stop-renewal', $root))->assertForbidden();
        $this->assertCount($sent, Http::recorded());
    }
}
