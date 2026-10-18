<?php

namespace Tests\Feature;

use App\Domain\Payments\Services\{CheckoutExpiryPolicy, RegistrationPaymentService, TeamPaymentService, UnpaidCheckoutExpiryService};
use App\Models\{CategoryEvent, CategoryEventRegistration, Event, EventNomination, InterprovincialTrialInvitation, InterprovincialTrialInvitationBatch, Player, Registration, RegistrationOrder, RegistrationOrderItems, Team, TeamPaymentOrder, TeamPlayer, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UnpaidCheckoutExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_five_minute_boundary_releases_only_draft_and_retains_amounts_and_audit(): void
    {
        $this->travelTo(Carbon::parse('2026-10-14 12:00:00'));
        [$order, $entry] = $this->registration();
        $order->forceFill(['created_at' => now()->subMinutes(4)->subSeconds(59)])->save();
        $service = app(UnpaidCheckoutExpiryService::class);
        $this->assertFalse($service->expire($order));
        $this->travel(1)->seconds();
        $this->assertTrue($service->expire($order));
        $this->assertFalse($service->expire($order));
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0.0, $order->fresh()->wallet_reserved);
        $this->assertSame(0.0, $order->fresh()->payfast_amount_due);
        $this->assertSame(100.0, $order->fresh()->total_fee);
        $this->assertEquals(100, $order->items()->sole()->item_price);
        $this->assertSoftDeleted('category_event_registrations', ['id' => $entry->id]);
        $this->assertDatabaseCount('registration_orders', 1);
        $this->assertDatabaseCount('registrations', 1);
        $this->assertDatabaseCount('registration_order_items', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_paid_debited_handed_off_and_submitted_eft_evidence_are_preserved(): void
    {
        foreach (['pay_status', 'payfast_paid', 'wallet_debited', 'handoff', 'provider_id', 'entry_paid', 'proof', 'receipt'] as $evidence) {
            [$order, $entry, $event] = $this->registration();
            $order->forceFill(['created_at' => now()->subMinutes(10), 'payfast_handed_off_at' => now()->subMinutes(9)])->save();
            if (in_array($evidence, ['pay_status', 'payfast_paid', 'wallet_debited'], true)) $order->forceFill([$evidence => true])->save();
            if ($evidence === 'handoff') $order->forceFill(['payfast_handed_off_at' => now()->subMinutes(4)])->save();
            if ($evidence === 'provider_id') $order->forceFill(['payfast_pf_payment_id' => 'PF-EXACT'])->save();
            if ($evidence === 'entry_paid') $entry->forceFill(['payment_status_id' => 1])->save();
            if ($evidence === 'proof') \App\Models\TrialPaymentProof::create(['order_id' => $order->id, 'event_id' => $event->id, 'payer_id' => $order->user_id, 'path' => 'private-proof.pdf', 'mime_type' => 'application/pdf', 'size' => 10]);
            if ($evidence === 'receipt') \App\Models\RegistrationManualReceipt::create(['order_id' => $order->id, 'event_id' => $event->id, 'amount' => 100, 'method' => 'manual', 'reference' => 'BANK-EXACT', 'verified_by_user_id' => $order->user_id, 'paid_at' => now()]);
            $before = $order->fresh()->getAttributes();
            $this->assertFalse(app(UnpaidCheckoutExpiryService::class)->expire($order), $evidence);
            $this->assertSame($before, $order->fresh()->getAttributes(), $evidence);
            $this->assertNull($entry->fresh()->deleted_at, $evidence);
        }
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_checkout_request_enforces_owner_before_lazy_expiry(): void
    {
        [$order, $entry] = $this->registration();
        $order->forceFill(['created_at' => now()->subMinutes(5)])->save();
        $this->withoutMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class, \App\Http\Middleware\EnsurePlayerProfileUpdated::class]);
        $this->actingAs(User::factory()->create())->get(route('registration.checkout', $order))->assertForbidden();
        $this->assertSame(25.0, $order->fresh()->wallet_reserved);
        $this->assertNull($entry->fresh()->deleted_at);
        $this->actingAs($order->user)->get(route('registration.checkout', $order))->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSoftDeleted('category_event_registrations', ['id' => $entry->id]);
    }

    public function test_interpro_expiry_returns_exact_nominee_to_registration_without_deleting_nomination(): void
    {
        [$order, $entry, $event, $category, $player] = $this->registration();
        $nomination = EventNomination::create(['event_id' => $event->id, 'category_event_id' => $category->id, 'player_id' => $player->id]);
        $batch = InterprovincialTrialInvitationBatch::create(['event_id' => $event->id, 'status' => 'draft', 'snapshot_hash' => str_repeat('a', 64), 'created_by_user_id' => $order->user_id]);
        $invitation = InterprovincialTrialInvitation::create(['batch_id' => $batch->id, 'event_id' => $event->id, 'category_event_id' => $category->id, 'player_id' => $player->id, 'nomination_id' => $nomination->id, 'order_id' => $order->id, 'registration_id' => $entry->registration_id, 'status' => 'accepted_pending_payment', 'accepted_at' => now()->subMinutes(5)]);
        $order->forceFill(['created_at' => now()->subMinutes(5)])->save();
        $this->assertTrue(app(UnpaidCheckoutExpiryService::class)->expire($order));
        $this->assertSame('sent', $invitation->fresh()->status);
        $this->assertNull($invitation->fresh()->order_id);
        $this->assertNull($invitation->fresh()->registration_id);
        $this->assertDatabaseHas('event_nominations', ['id' => $nomination->id]);
    }

    public function test_team_expiry_retains_unpaid_roster_slot_and_allows_fresh_exact_order(): void
    {
        $event = Event::factory()->create();
        $team = Team::factory()->create(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $event->id])->id]);
        $player = Player::factory()->create();
        $payer = User::factory()->create();
        $slot = TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 0]);
        $order = TeamPaymentOrder::create(['user_id' => $payer->id, 'team_id' => $team->id, 'event_id' => $event->id, 'player_id' => $player->id, 'total_amount' => 123.45, 'wallet_reserved' => 23.45, 'payfast_amount_due' => 100, 'pay_status' => false, 'wallet_debited' => false, 'payfast_paid' => false]);
        $order->forceFill(['created_at' => now()->subMinutes(5)])->save();
        $this->assertTrue(app(UnpaidCheckoutExpiryService::class)->expire($order));
        $this->assertFalse(app(UnpaidCheckoutExpiryService::class)->expire($order));
        $this->assertNotNull($order->fresh()->withdrawn_at);
        $this->assertSame(0.0, $order->fresh()->wallet_reserved);
        $this->assertSame(123.45, $order->fresh()->total_amount);
        $this->assertSame($player->id, $slot->fresh()->player_id);
        $replacement = app(TeamPaymentService::class)->ensureOrder($payer, $team, $player, $event, 123.45);
        $this->assertNotSame($order->id, $replacement->id);
        $this->assertSame($payer->id, $replacement->user_id);
        $this->assertDatabaseCount('team_payment_orders', 2);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_command_is_preview_only_by_default_and_shared_policy_is_five_minutes(): void
    {
        [$order] = $this->registration();
        $order->forceFill(['created_at' => now()->subMinutes(5)])->save();
        $this->artisan('registrations:expire-checkouts')->assertSuccessful();
        $this->assertSame(25.0, $order->fresh()->wallet_reserved);
        $this->artisan('registrations:expire-checkouts', ['--apply' => true])->assertSuccessful();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(5, CheckoutExpiryPolicy::MINUTES);
        $this->assertSame(5, config('masters.pending_payment_minutes'));
    }

    public function test_tampered_master_link_and_wrong_payer_cannot_release_checkout(): void
    {
        [$order, $entry, $event, $category, $player] = $this->registration();
        $order->forceFill(['created_at' => now()->subMinutes(5)])->save();
        $otherEvent = Event::factory()->create();
        $otherCategory = CategoryEvent::factory()->create(['event_id' => $otherEvent->id]);
        $batch = \App\Models\MastersInvitationBatch::create(['event_id' => $otherEvent->id, 'series_id' => 1, 'ranking_run_id' => 'expiry-test', 'top_x' => 8, 'status' => 'sent']);
        \App\Models\MastersInvitation::create(['batch_id' => $batch->id, 'event_id' => $otherEvent->id, 'category_event_id' => $otherCategory->id, 'player_id' => $player->id, 'registration_id' => $entry->registration_id, 'order_id' => $order->id, 'status' => 'accepted_pending_payment', 'ranking_position' => 1, 'queue_position' => 1]);
        $this->assertFalse(app(UnpaidCheckoutExpiryService::class)->expire($order));
        $this->assertSame(25.0, $order->fresh()->wallet_reserved);
        try {
            app(RegistrationPaymentService::class)->preparePayfastHandoff($order, User::factory()->create(), 25, 75);
            $this->fail('A foreign payer submitted the checkout.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey('payment', $exception->errors());
        }
        $this->assertSame(25.0, $order->fresh()->wallet_reserved);
        $this->assertNull($entry->fresh()->deleted_at);
    }

    public function test_cancelled_draft_cannot_be_reserved_handed_off_or_paid_from_stale_models(): void
    {
        [$order] = $this->registration();
        $order->forceFill(['created_at' => now()->subMinutes(5)])->save();
        $this->assertTrue(app(UnpaidCheckoutExpiryService::class)->expire($order));
        $payments = app(RegistrationPaymentService::class);
        foreach ([fn () => $payments->reservePayment($order, 100, 0), fn () => $payments->finalizeWalletPayment($order), fn () => $payments->preparePayfastHandoff($order, $order->user, 25, 75)] as $action) {
            try { $action(); $this->fail('An expired draft was paid or resurrected.'); }
            catch (\Illuminate\Validation\ValidationException $exception) { $this->assertArrayHasKey('payment', $exception->errors()); }
        }
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_generic_retry_resumes_fresh_draft_then_releases_expired_reservation_before_replacement(): void
    {
        [$order, $entry, $event, $category, $player] = $this->registration();
        $category->update(['entry_fee' => 100]);
        $this->withoutMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class, \App\Http\Middleware\EnsurePlayerProfileUpdated::class]);
        $this->actingAs($order->user)->post(route('pay.now.payfast'), ['player' => [$player->id], 'category' => [$category->id], 'terms_accepted' => '1'])
            ->assertRedirect(route('registration.checkout', $order));
        $this->assertDatabaseCount('registration_orders', 1);
        $order->forceFill(['created_at' => now()->subMinutes(5)])->save();
        config(['services.payfast.merchant_id' => 'test-merchant', 'services.payfast.merchant_key' => 'test-key']);
        $this->post(route('pay.now.payfast'), ['player' => [$player->id], 'category' => [$category->id], 'terms_accepted' => '1'])->assertSuccessful();
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0.0, $order->fresh()->wallet_reserved);
        $this->assertSoftDeleted('category_event_registrations', ['id' => $entry->id]);
        $this->assertDatabaseCount('registration_orders', 2);
        $this->assertSame(1, CategoryEventRegistration::count());
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_scheduler_prioritises_new_drafts_and_advances_over_inconsistent_legacy_backlog(): void
    {
        $payer = User::factory()->create();
        $records = array_fill(0, 501, ['user_id' => $payer->id, 'created_at' => now()->subHour(), 'updated_at' => now()->subHour(), 'pay_status' => 0, 'payfast_paid' => 0, 'wallet_debited' => 0, 'wallet_reserved' => 0, 'payfast_amount_due' => 0, 'total_fee' => 0, 'status' => 'pending']);
        RegistrationOrder::insert($records);
        $firstBacklogId = (int) RegistrationOrder::min('id');
        \Illuminate\Support\Facades\Cache::forget('checkout-expiry:registration:backlog-cursor');
        [$recent] = $this->registration();
        $recent->forceFill(['created_at' => now()->subMinutes(5)])->save();
        $service = app(UnpaidCheckoutExpiryService::class);
        $first = $service->expireAll();
        $this->assertSame(1, $first['registration']);
        $this->assertSame(250, $first['skipped']);
        $this->assertSame($firstBacklogId + 249, \Illuminate\Support\Facades\Cache::get('checkout-expiry:registration:backlog-cursor'));
        $second = $service->expireAll();
        $this->assertSame(250, $second['skipped']);
        $this->assertSame($firstBacklogId + 499, \Illuminate\Support\Facades\Cache::get('checkout-expiry:registration:backlog-cursor'));
        $third = $service->expireAll();
        $this->assertSame(1, $third['skipped']);
        $this->assertSame($firstBacklogId + 500, \Illuminate\Support\Facades\Cache::get('checkout-expiry:registration:backlog-cursor'));
        $this->assertDatabaseCount('registration_orders', 502);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_expired_handoff_releases_entry_and_quarantines_exact_delayed_receipt_idempotently(): void
    {
        [$order, $entry, $event, $category, $player] = $this->registration();
        $order->forceFill(['created_at' => now()->subDay(), 'payfast_handed_off_at' => now()->subMinutes(4)])->save();
        $service = app(UnpaidCheckoutExpiryService::class);
        $this->assertFalse($service->expire($order));
        $this->travel(1)->minutes();
        $this->assertSame(1, $service->candidateCounts()['registration']);
        $this->assertSame(1, $service->expireAll()['registration']);
        $this->assertFalse($service->expire($order));
        $this->assertSoftDeleted('category_event_registrations', ['id' => $entry->id]);
        $data = ['payment_status' => 'COMPLETE', 'pf_payment_id' => 'EXPIRED-PF-123',
            'amount_gross' => '75.00', 'custom_int5' => $order->id, 'custom_int4' => $order->user_id,
            'custom_int1' => $category->id, 'custom_int2' => $player->id, 'custom_int3' => $event->id];
        $receipts = app(\App\Domain\Payments\Services\ExpiredPayfastHandoffService::class);
        foreach ([['amount_gross' => '100.00'], ['custom_int4' => User::factory()->create()->id],
            ['custom_int3' => Event::factory()->create()->id], ['payment_status' => 'PENDING']] as $tamper) {
            try { $receipts->recordReceipt($order, array_replace($data, $tamper)); $this->fail('Invalid delayed receipt accepted.'); }
            catch (\RuntimeException $exception) { $this->assertStringContainsString('original handoff', $exception->getMessage()); }
        }
        $this->sendExpiredCallback('notify', $data)->assertOk();
        $this->sendExpiredCallback('notify', $data)->assertOk();
        $this->post(route('notify'), $data + ['signature' => 'invalid'])->assertStatus(400);
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertSame(0.0, $order->fresh()->wallet_reserved);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseCount('registration_orders', 1);
        $this->assertSame(0, CategoryEventRegistration::count());
        $row = app(\App\Domain\Finance\Services\FinancialLedgerService::class)->buildPaymentRows($event, 10)->sole();
        $this->assertSame('Late payment — reconciliation required', $row->status_label);
        $this->assertSame(0, $row->entryCount);
        $this->assertSame(0.0, $row->walletUsed);
        $this->assertSame(0.0, $row->capeFee);
    }

    public function test_team_handoff_expiry_releases_reservation_and_retains_roster_without_settling_late_charge(): void
    {
        $event = Event::factory()->create();
        $team = Team::factory()->create(['category_event_id' => CategoryEvent::factory()->create(['event_id' => $event->id])->id]);
        $player = Player::factory()->create();
        $payer = User::factory()->create();
        $slot = TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 0]);
        $order = TeamPaymentOrder::create(['user_id' => $payer->id, 'team_id' => $team->id, 'event_id' => $event->id,
            'player_id' => $player->id, 'total_amount' => 450, 'wallet_reserved' => 50, 'payfast_amount_due' => 400,
            'pay_status' => false, 'wallet_debited' => false, 'payfast_paid' => false]);
        $order->forceFill(['created_at' => now()->subDay(), 'payfast_handed_off_at' => now()->subMinutes(5)])->save();
        $this->assertSame(1, app(UnpaidCheckoutExpiryService::class)->expireAll()['team']);
        $data = ['payment_status' => 'COMPLETE', 'pf_payment_id' => 'EXPIRED-TEAM-123', 'amount_gross' => '400.00',
            'custom_str5' => 'TeamOrder', 'custom_int5' => $order->id, 'custom_int4' => $payer->id,
            'custom_int3' => $event->id, 'custom_int2' => $player->id];
        $this->sendExpiredCallback('notify.team', $data)->assertOk();
        $this->sendExpiredCallback('notify.team', $data)->assertOk();
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertFalse((bool) $slot->fresh()->pay_status);
        $this->assertSame('reconciliation_required', $order->fresh()->collection_status);
        $this->assertSame(0.0, $order->fresh()->wallet_reserved);
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    private function sendExpiredCallback(string $route, array $payload): \Illuminate\Testing\TestResponse
    {
        config(['services.payfast.sandbox' => false, 'services.payfast.merchant_id' => 'test-merchant',
            'services.payfast.passphrase_live' => 'test-passphrase']);
        $payload['merchant_id'] = 'test-merchant';
        $payload['signature'] = md5(http_build_query($payload).'&passphrase=test-passphrase');
        return $this->call('POST', route($route), $payload, [], [],
            ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], http_build_query($payload));
    }

    private function registration(): array
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['published' => true, 'signUp' => true, 'start_date' => now()->addMonth(), 'deadline' => 1]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $player = Player::factory()->create();
        $registration = Registration::create([]);
        $registration->players()->sync([$player->id]);
        $entry = CategoryEventRegistration::create(['registration_id' => $registration->id, 'category_event_id' => $category->id, 'user_id' => $user->id, 'payment_status_id' => 0, 'status' => 'active']);
        $order = RegistrationOrder::create(['user_id' => $user->id, 'total_fee' => 100, 'wallet_reserved' => 25, 'payfast_amount_due' => 75, 'wallet_debited' => false, 'payfast_paid' => false, 'pay_status' => false, 'status' => 'pending']);
        (new RegistrationOrderItems())->forceFill(['order_id' => $order->id, 'registration_id' => $registration->id, 'category_event_id' => $category->id, 'player_id' => $player->id, 'user_id' => $user->id, 'item_price' => 100])->save();
        return [$order, $entry, $event, $category, $player];
    }
}
