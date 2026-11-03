<?php

namespace Tests\Feature;

use App\Http\Controllers\Frontend\PlayerPlusController;
use App\Models\{AuditEvent, Player, PlayerPlusMembership, PlayerPlusOrder, SiteSetting, User};
use App\Services\Performance\{PlayerPlusService, PlayerRatingAccess};
use App\Support\Audit\AuditWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, DB, Http, Mail};
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerPlusDirectCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake(); Bus::fake(); Http::preventStrayRequests();
        SiteSetting::set(PlayerRatingAccess::SETTING, '1');
        SiteSetting::set(PlayerPlusService::PRICE_SETTING, '69900');
        config(['services.payfast.sandbox' => true, 'services.payfast.sandbox_id' => 'synthetic-plus-merchant',
            'services.payfast.sandbox_key' => 'synthetic-plus-key', 'services.payfast.passphrase_sandbox' => 'synthetic-plus-passphrase']);
        $this->withoutMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class, \App\Http\Middleware\EnsurePlayerProfileUpdated::class]);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-02 12:00:00', 'Africa/Johannesburg'));
    }

    private function data(int $price = 69900): array
    {
        return ['request_token' => (string) Str::uuid(), 'confirm' => '1', 'recurring_consent' => '1',
            'terms_version' => PlayerPlusController::TERMS_VERSION, 'plan' => 'annual_recurring', 'quoted_price_cents' => $price];
    }

    private function start(?Player $player = null, ?User $payer = null): PlayerPlusOrder
    {
        return app(PlayerPlusService::class)->start($player ?? Player::factory()->create(), $payer ?? User::factory()->create(),
            (string) Str::uuid(), 69900, 'annual_recurring', true);
    }

    private function activate(PlayerPlusOrder $order): PlayerPlusOrder
    {
        $service = app(PlayerPlusService::class);
        if (!$order->payfast_handed_off_at) $order = $service->handoff($order, $order->user);
        $service->receive(['custom_str5' => 'PlayerPlus', 'custom_int2' => $order->player_id, 'custom_int4' => $order->user_id,
            'custom_int5' => $order->id, 'payment_status' => 'COMPLETE', 'amount_gross' => '699.00',
            'pf_payment_id' => 'synthetic-direct-'.$order->id, 'token' => 'synthetic-subscription-'.$order->id]);
        return $order->fresh();
    }

    public function test_two_consents_start_one_exact_signed_handoff_and_replay_never_emits_another_form(): void
    {
        $payer = User::factory()->create(); $player = Player::factory()->create();
        $this->actingAs($payer);
        $url = route('player-plus.store', $player); $data = $this->data();
        foreach (['confirm', 'recurring_consent'] as $missing) {
            $this->postJson($url, array_diff_key($data, [$missing => true]))->assertUnprocessable()->assertJsonValidationErrors($missing);
        }
        $this->assertDatabaseCount('player_plus_orders', 0);
        $response = $this->post($url, $data)->assertOk()->assertViewIs('frontend.player-plus.launch')
            ->assertHeader('Cache-Control', 'no-store, private')->assertSee('data-plus-payfast-launch', false)
            ->assertSee('player-plus-checkout.js')->assertSee('name="amount" value="699.00"', false);
        $order = PlayerPlusOrder::sole();
        $response->assertSee(e(route('player-plus.orders.return', $order)), false)->assertSee(e(route('player-plus.orders.show', $order)), false);
        $this->assertNotNull($order->payfast_handed_off_at);
        $this->assertSame(300, (int) $order->payfast_handed_off_at->diffInSeconds($order->expires_at));
        $this->assertSame(69900, $order->price_cents);
        $this->assertSame('annual_recurring', $order->plan);
        $this->assertNotNull($order->recurring_consented_at);
        $before = $order->getAttributes();
        $this->post($url, $data)->assertRedirect(route('player-plus.orders.show', $order));
        $this->post($url, $this->data())->assertRedirect(route('player-plus.orders.show', $order));
        $this->assertSame($before, $order->fresh()->getAttributes());
        $this->assertDatabaseCount('player_plus_orders', 1);
        $this->assertSame(1, AuditEvent::where('action', 'player-plus.purchase-details-accepted')->count());
        $this->assertDatabaseCount('player_plus_memberships', 0);
        $this->assertDatabaseCount('transactions_pf', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_reused_saved_price_and_old_terms_require_review_without_false_acceptance_or_repricing(): void
    {
        $order = $this->start(); $this->actingAs($order->user);
        SiteSetting::set(PlayerPlusService::PRICE_SETTING, '79900');
        $this->post(route('player-plus.store', $order->player), $this->data(79900))
            ->assertRedirect(route('player-plus.orders.show', $order))->assertSessionHas('plus_checkout_review');
        $this->assertSame(69900, $order->fresh()->price_cents);
        $this->assertNull($order->fresh()->payfast_handed_off_at);
        $this->assertSame(0, AuditEvent::where('action', 'player-plus.purchase-details-accepted')->count());
        app(AuditWriter::class)->record(['category' => 'business', 'action' => 'player-plus.purchase-details-accepted',
            'actor' => $order->user, 'subject' => $order,
            'metadata' => ['terms_version' => 'previous-terms', 'price_cents' => 69900, 'plan' => 'annual_recurring', 'recurring_consent' => true]], true);
        $this->post(route('player-plus.store', $order->player), $this->data())
            ->assertRedirect(route('player-plus.orders.show', $order));
        $this->assertNull($order->fresh()->payfast_handed_off_at);
        $this->assertSame('previous-terms', AuditEvent::where('action', 'player-plus.purchase-details-accepted')->sole()->metadata['terms_version']);
        $this->assertDatabaseCount('player_plus_orders', 1);
    }

    public function test_stale_new_quote_and_foreign_payer_requests_cannot_create_or_handoff_orders(): void
    {
        $payer = User::factory()->create(); $player = Player::factory()->create();
        $this->actingAs($payer)->postJson(route('player-plus.store', $player), $this->data(6900))->assertUnprocessable();
        $this->assertDatabaseCount('player_plus_orders', 0);
        $order = $this->start($player, $payer); $before = $order->fresh()->getAttributes();
        $this->actingAs(User::factory()->create());
        $this->postJson(route('player-plus.store', $player), array_replace($this->data(), ['request_token' => $order->request_token]))->assertConflict();
        $this->postJson(route('player-plus.orders.pay', $order))->assertForbidden();
        $this->getJson(route('player-plus.orders.payment-status', $order))->assertForbidden();
        $this->get(route('player-plus.orders.return', $order))->assertForbidden();
        $this->assertSame($before, $order->fresh()->getAttributes());
        $this->assertDatabaseCount('player_plus_orders', 1);
        $this->assertDatabaseCount('player_plus_memberships', 0);
    }

    public function test_provider_return_waits_for_canonical_verification_then_opens_the_linked_players_rating_tab(): void
    {
        $payer = User::factory()->create(); $player = Player::factory()->create(['userId' => $payer->id]);
        $order = $this->start($player, $payer); $order = app(PlayerPlusService::class)->handoff($order, $payer);
        $this->actingAs($payer);
        $return = route('player-plus.orders.return', $order);
        $status = route('player-plus.orders.payment-status', $order);
        $this->get($return.'?payment_status=COMPLETE&paid=1')->assertOk()->assertSee('data-plus-payment-return', false);
        $this->getJson($status)->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertExactJson(['state' => 'pending', 'destination' => null]);
        $this->assertDatabaseCount('player_plus_memberships', 0);
        $this->assertFalse($order->fresh()->payfast_paid);
        $order = $this->activate($order);
        $destination = route('my.tennis', ['player' => $player->id]).'#rating';
        $this->getJson($status)->assertOk()->assertJsonPath('state', 'verified')->assertJsonPath('destination', $destination);
        $this->get($return)->assertRedirect($destination);
        $player->update(['userId' => null]);
        $payer->players()->attach($player);
        $this->get($return)->assertRedirect($destination);
        $this->get(route('player-plus.orders.show', $order))->assertOk()->assertSee('Payment verified');
        $this->assertDatabaseCount('player_plus_memberships', 1);
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_sponsoring_administrator_keeps_receipt_without_private_rating_destination_or_new_link(): void
    {
        Role::findOrCreate('super-user', 'web');
        $sponsor = User::factory()->create()->assignRole('super-user');
        $order = $this->activate($this->start(payer: $sponsor));
        $links = DB::table('user_players')->count();
        $this->actingAs($sponsor)->get(route('player-plus.orders.return', $order))->assertRedirect(route('player-plus.orders.show', $order));
        $this->getJson(route('player-plus.orders.payment-status', $order))->assertExactJson([
            'state' => 'receipt', 'destination' => route('player-plus.orders.show', $order)]);
        $this->assertSame($links, DB::table('user_players')->count());
    }

    public function test_expiry_reconciliation_and_inactive_entitlements_never_redirect_to_ratings(): void
    {
        $payer = User::factory()->create(); $player = Player::factory()->create(['userId' => $payer->id]);
        $order = $this->start($player, $payer); $order = app(PlayerPlusService::class)->handoff($order, $payer);
        $this->actingAs($payer);
        $this->travelTo($order->expires_at);
        $receipt = route('player-plus.orders.show', $order);
        $this->get(route('player-plus.orders.return', $order))->assertRedirect($receipt);
        $order = $this->activate($order);
        $this->assertSame('reconciliation_required', $order->status);
        $this->get(route('player-plus.orders.return', $order))->assertRedirect($receipt);
        $this->assertDatabaseCount('player_plus_memberships', 0);
    }

    public function test_verified_but_suspended_or_expired_access_and_additional_payment_review_stay_on_receipt(): void
    {
        $payer = User::factory()->create(); $player = Player::factory()->create(['userId' => $payer->id]);
        $order = $this->activate($this->start($player, $payer));
        $receipt = route('player-plus.orders.show', $order);
        $this->actingAs($payer);
        $player->plusOverride()->create(['mode' => 'suspended']);
        $this->get(route('player-plus.orders.return', $order))->assertRedirect($receipt);
        $player->plusOverride()->delete();
        $order->update(['reconciliation_reason' => 'Synthetic additional receipt']);
        $this->get(route('player-plus.orders.return', $order))->assertRedirect($receipt);
        $order->update(['reconciliation_reason' => null]);
        $this->travelTo($order->term_ends_at);
        $this->get(route('player-plus.orders.return', $order))->assertRedirect($receipt);
    }

    public function test_terminal_or_expired_token_replays_never_handoff_or_add_a_later_acceptance(): void
    {
        foreach (['expired', 'cancelled', 'reconciliation_required', 'completed'] as $state) {
            $order = $this->start();
            if ($state === 'expired') $order->update(['expires_at' => now()]);
            else $order->update(['status' => $state]);
            $before = $order->fresh()->getAttributes();
            $this->actingAs($order->user)->post(route('player-plus.store', $order->player),
                array_replace($this->data(), ['request_token' => $order->request_token]))->assertRedirect(route('player-plus.orders.show', $order));
            $this->assertSame($before, $order->fresh()->getAttributes());
        }
        $this->assertDatabaseCount('player_plus_orders', 4);
        $this->assertSame(0, AuditEvent::where('action', 'player-plus.purchase-details-accepted')->count());
        $this->assertDatabaseCount('player_plus_memberships', 0);
    }

    public function test_failed_handoff_configuration_rolls_back_new_order_and_acceptance(): void
    {
        config(['services.payfast.passphrase_sandbox' => '', 'services.payfast.passphrase' => '']);
        $this->actingAs(User::factory()->create())->postJson(route('player-plus.store', Player::factory()->create()), $this->data())
            ->assertUnprocessable()->assertJsonValidationErrors('membership');
        $this->assertDatabaseCount('player_plus_orders', 0);
        $this->assertSame(0, AuditEvent::where('action', 'player-plus.purchase-details-accepted')->count());
        $this->assertDatabaseCount('player_plus_memberships', 0);
    }
}
