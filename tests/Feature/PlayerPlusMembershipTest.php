<?php

namespace Tests\Feature;

use App\Models\{Player, PlayerPlusMembership, PlayerPlusOrder, SiteSetting, Transaction, User};
use App\Services\Performance\{PlayerPlusService, PlayerRatingAccess};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Mail};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PlayerPlusMembershipTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Keep legacy-price fixtures explicit; new offers default to R699.
        SiteSetting::set(PlayerPlusService::PRICE_SETTING, '49900');
        Mail::fake();
        SiteSetting::set(PlayerRatingAccess::SETTING, '1');
    }

    private function order(?Player $player = null, ?User $payer = null): PlayerPlusOrder
    {
        $player ??= Player::factory()->create();
        $payer ??= User::factory()->create();
        return app(PlayerPlusService::class)->start($player, $payer, (string) Str::uuid());
    }

    private function itn(PlayerPlusOrder $order, string $id = 'plus-payment-1'): array
    {
        return ['custom_str5' => 'PlayerPlus', 'custom_int2' => $order->player_id, 'custom_int4' => $order->user_id,
            'custom_int5' => $order->id, 'payment_status' => 'COMPLETE', 'amount_gross' => '499.00', 'pf_payment_id' => $id,
            'item_name' => 'Player Plus - 12 months'];
    }

    private function activate(PlayerPlusOrder $order): PlayerPlusOrder
    {
        $service = app(PlayerPlusService::class);
        $service->handoff($order, $order->user);
        $service->receive($this->itn($order));
        return $order->fresh();
    }

    public function test_verified_purchase_is_per_player_and_shared_only_with_existing_links(): void
    {
        $playerAccount = User::factory()->create();
        $parent = User::factory()->create();
        $secondParent = User::factory()->create();
        $sponsor = User::factory()->create();
        $outsider = User::factory()->create();
        $player = Player::factory()->create(['userId' => $playerAccount->id]);
        $parent->players()->attach($player);
        $secondParent->players()->attach($player);
        $other = Player::factory()->create(['userId' => $parent->id]);
        $links = DB::table('user_players')->count();
        $order = $this->activate($this->order($player, $sponsor));
        $this->assertTrue($order->pay_status);
        $this->assertSame(49900, $order->price_cents);
        $this->assertEquals(0, $order->wallet_reserved);
        $this->assertFalse($order->wallet_debited);
        $this->assertSame($links, DB::table('user_players')->count());
        $access = app(PlayerRatingAccess::class);
        foreach ([$playerAccount, $parent, $secondParent] as $user) {
            $this->assertTrue($access->allows($user, $player));
            $this->assertTrue($access->allowsDiscovery($user));
        }
        foreach ([$sponsor, $outsider] as $user) {
            $this->assertFalse($access->allows($user, $player));
            $this->assertFalse($access->allowsDiscovery($user));
        }
        $this->assertFalse($access->allows($parent, $other));
        $this->assertDatabaseCount('player_plus_memberships', 1);
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $receipt = Transaction::sole();
        $this->assertSame('Player Plus', $receipt->transaction_type);
        $this->assertNull($receipt->event_id);
        $this->assertNull($receipt->category_event_id);
        $this->assertEquals($sponsor->id, $receipt->custom_int4);
        $parent->players()->detach($player);
        $this->assertFalse($access->allows($parent, $player));
        $this->assertFalse($access->allowsDiscovery($parent));
        SiteSetting::set(PlayerRatingAccess::SETTING, '0');
        $this->assertTrue($access->allows($secondParent, $player));
        $this->assertTrue($access->allowsDiscovery($secondParent));
    }

    public function test_provider_receipt_cannot_be_reused_by_another_order(): void
    {
        $original = $this->activate($this->order());
        $other = $this->order();
        app(PlayerPlusService::class)->handoff($other, $other->user);
        try {
            app(PlayerPlusService::class)->receive($this->itn($other));
            $this->fail('Provider receipt was reused.');
        } catch (\RuntimeException) {}
        $this->assertTrue($original->fresh()->payfast_paid);
        $this->assertFalse($other->fresh()->payfast_paid);
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseCount('player_plus_memberships', 1);
    }

    public function test_exact_five_minute_expiry_withholds_activation(): void
    {
        $order = $this->order();
        $order = app(PlayerPlusService::class)->handoff($order, $order->user);
        $this->travelTo($order->expires_at);
        app(PlayerPlusService::class)->receive($this->itn($order));
        $this->assertSame('reconciliation_required', $order->fresh()->status);
        $this->assertDatabaseCount('player_plus_memberships', 0);
        $this->assertDatabaseCount('transactions_pf', 1);
    }

    public function test_repeated_callback_and_start_are_idempotent_and_additional_money_does_not_extend_term(): void
    {
        $order = $this->order();
        $again = app(PlayerPlusService::class)->start($order->player, $order->user, $order->request_token);
        $this->assertSame($order->id, $again->id);
        $order = $this->activate($order);
        $ends = $order->term_ends_at->toISOString();
        app(PlayerPlusService::class)->receive($this->itn($order));
        app(PlayerPlusService::class)->receive($this->itn($order, 'additional-payment'));
        $this->assertSame($ends, $order->fresh()->term_ends_at->toISOString());
        $this->assertNotNull($order->fresh()->reconciliation_reason);
        $this->assertDatabaseCount('player_plus_orders', 1);
        $this->assertDatabaseCount('player_plus_memberships', 1);
        $this->assertDatabaseCount('transactions_pf', 2);
    }

    public function test_membership_receipt_is_on_original_payer_statement_only(): void
    {
        $order = $this->activate($this->order());
        $service = app(\App\Services\AccountStatementService::class);
        $statement = $service->build($order->user, now()->subDay()->toDateString(), now()->addDay()->toDateString());
        $this->assertSame(49900, $statement['paid']);
        $this->assertSame('Player Plus / PayFast', $statement['rows']->sole()['method']);
        $entryOnly = $service->build($order->user, now()->subDay()->toDateString(), now()->addDay()->toDateString(), '', 'entry');
        $this->assertCount(0, $entryOnly['rows']);
        $linkedParent = User::factory()->create();
        $linkedParent->players()->attach($order->player_id);
        $other = $service->build($linkedParent, now()->subDay()->toDateString(), now()->addDay()->toDateString());
        $this->assertCount(0, $other['rows']);
    }

    public function test_amount_and_identity_tampering_never_activates_or_records_a_receipt(): void
    {
        $order = $this->order();
        app(PlayerPlusService::class)->handoff($order, $order->user);
        foreach (['amount_gross' => '498.99', 'custom_int2' => $order->player_id + 1,
            'custom_int4' => $order->user_id + 1, 'custom_int3' => 1, 'custom_str5' => 'TeamOrder'] as $key => $value) {
            try {
                app(PlayerPlusService::class)->receive(array_replace($this->itn($order), [$key => $value]));
                $this->fail('Tampered callback accepted: '.$key);
            } catch (ValidationException) {}
        }
        app(PlayerPlusService::class)->receive(array_replace($this->itn($order), ['payment_status' => 'FAILED']));
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertDatabaseCount('player_plus_memberships', 0);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_only_payer_can_access_handoff_or_cancel_and_return_never_activates(): void
    {
        $order = $this->order();
        $other = User::factory()->create();
        $this->withoutMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class, \App\Http\Middleware\EnsurePlayerProfileUpdated::class]);
        $this->actingAs($other)->get(route('player-plus.orders.show', $order))->assertForbidden();
        $this->actingAs($other)->post(route('player-plus.orders.pay', $order))->assertForbidden();
        $this->actingAs($other)->post(route('player-plus.orders.cancel', $order))->assertForbidden();
        $this->actingAs($order->user)->get(route('player-plus.orders.show', $order))->assertOk()->assertSee('R499');
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertDatabaseCount('player_plus_memberships', 0);
        $this->actingAs($order->user)->post(route('player-plus.orders.pay', $order))->assertOk()
            ->assertSee('form="payfastForm"', false)->assertSee('name="amount" value="499.00"', false);
    }

    public function test_expired_or_cancelled_payment_retains_original_payer_receipt_without_access(): void
    {
        $order = $this->order();
        app(PlayerPlusService::class)->handoff($order, $order->user);
        $handoff = $order->fresh()->payfast_handed_off_at;
        $this->travel(6)->minutes();
        app(PlayerPlusService::class)->cancel($order, $order->user);
        app(PlayerPlusService::class)->receive($this->itn($order));
        $order->refresh();
        $this->assertSame('reconciliation_required', $order->status);
        $this->assertEquals($handoff, $order->payfast_handed_off_at);
        $this->assertTrue($order->payfast_paid);
        $this->assertNull($order->activated_at);
        $this->assertDatabaseCount('player_plus_memberships', 0);
        $this->assertEquals($order->user_id, Transaction::sole()->custom_int4);
    }

    public function test_payer_http_handoff_contains_fixed_signed_identity_and_does_not_activate_membership(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-01 12:00:00'));
        $order = $this->order();
        config(['services.payfast.sandbox' => true, 'services.payfast.sandbox_id' => 'plus-test-merchant',
            'services.payfast.sandbox_key' => 'plus-test-key', 'services.payfast.passphrase_sandbox' => 'plus-test-passphrase']);
        $this->withoutMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class, \App\Http\Middleware\EnsurePlayerProfileUpdated::class]);
        $response = $this->actingAs($order->user)->post(route('player-plus.orders.pay', $order), [
            'amount' => '1.00', 'player_id' => $order->player_id + 1, 'user_id' => $order->user_id + 1,
        ])->assertOk()->assertSee('form="payfastForm"', false)
            ->assertSee('action="https://sandbox.payfast.co.za/eng/process"', false);

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $fields = [];
        foreach ($xpath->query('//form[@id="payfastForm"]/input[@type="hidden"]') as $input) {
            $fields[$input->getAttribute('name')] = $input->getAttribute('value');
        }
        $this->assertSame('499.00', $fields['amount']);
        $this->assertSame('PlayerPlus', $fields['custom_str5']);
        $this->assertSame((string) $order->player_id, $fields['custom_int2']);
        $this->assertSame((string) $order->user_id, $fields['custom_int4']);
        $this->assertSame((string) $order->id, $fields['custom_int5']);
        $this->assertSame(route('player-plus.orders.show', $order), $fields['return_url']);
        $this->assertArrayNotHasKey('custom_int1', $fields);
        $this->assertArrayNotHasKey('custom_int3', $fields);
        $signature = $fields['signature'];
        unset($fields['signature']);
        $fields['passphrase'] = 'plus-test-passphrase';
        ksort($fields);
        $this->assertSame(md5(http_build_query($fields)), $signature);

        $order->refresh();
        $handoff = $order->payfast_handed_off_at;
        $expiry = $order->expires_at;
        $this->assertSame('2026-11-01 12:00:00', $handoff->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-01 12:05:00', $expiry->format('Y-m-d H:i:s'));
        $this->assertFalse($order->pay_status);
        $this->assertNull($order->activated_at);
        $this->assertDatabaseCount('player_plus_memberships', 0);
        $this->assertDatabaseCount('transactions_pf', 0);
        $this->travel(1)->minutes();
        $this->post(route('player-plus.orders.pay', $order))->assertRedirect()->assertSessionHasErrors('membership');
        $order->refresh();
        $this->assertEquals($handoff, $order->payfast_handed_off_at);
        $this->assertEquals($expiry, $order->expires_at);
        $this->assertFalse($order->pay_status);
    }

    public function test_manual_renewal_after_expiry_is_a_new_twelve_month_term_and_exact_boundary_revokes_access(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2028-02-29 12:00:00'));
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        $order = $this->activate($this->order($player, $owner));
        $this->assertSame('2029-02-28 12:00:00', $order->term_ends_at->format('Y-m-d H:i:s'));
        try { $this->order($player, $owner); $this->fail('Duplicate covered-player purchase accepted.'); }
        catch (ValidationException) {}
        $this->travelTo($order->term_ends_at);
        $this->assertFalse(app(PlayerRatingAccess::class)->allows($owner, $player));
        $renewal = $this->order($player, $owner);
        app(PlayerPlusService::class)->handoff($renewal, $owner);
        app(PlayerPlusService::class)->receive($this->itn($renewal, 'manual-renewal'));
        $this->assertSame('2030-02-28 12:00:00', $renewal->fresh()->term_ends_at->format('Y-m-d H:i:s'));
        $this->assertTrue(app(PlayerRatingAccess::class)->allows($owner, $player));
        $this->assertDatabaseCount('player_plus_orders', 2);
        $this->assertDatabaseCount('player_plus_memberships', 1);
    }

    public function test_pending_checkout_is_unique_per_player_and_other_payer_cannot_take_it_over(): void
    {
        $order = $this->order();
        $this->assertSame($order->id, $this->order($order->player, $order->user)->id);
        try { $this->order($order->player); $this->fail('Pending player checkout taken over.'); }
        catch (ValidationException) {}
        $this->assertDatabaseCount('player_plus_orders', 1);
        $this->travel(6)->minutes();
        $new = $this->order($order->player);
        $this->assertNotSame($order->id, $new->id);
        $this->assertSame('expired', $order->fresh()->status);
        $this->assertDatabaseCount('player_plus_orders', 2);
    }

    public function test_verified_signed_http_callback_is_required_and_switch_off_blocks_new_purchases(): void
    {
        $order = $this->order();
        app(PlayerPlusService::class)->handoff($order, $order->user);
        config(['services.payfast.sandbox' => true, 'services.payfast.sandbox_id' => 'test-merchant', 'services.payfast.passphrase_sandbox' => 'test-passphrase']);
        $data = $this->itn($order) + ['merchant_id' => 'test-merchant'];
        $this->post('/notify', $data + ['signature' => 'invalid'])->assertStatus(400);
        $this->assertDatabaseCount('player_plus_memberships', 0);
        SiteSetting::set(PlayerRatingAccess::SETTING, '0');
        $data['signature'] = md5(http_build_query($data).'&passphrase=test-passphrase');
        $this->post('/notify', $data)->assertOk();
        $this->assertDatabaseCount('player_plus_memberships', 1);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->order();
    }
}
