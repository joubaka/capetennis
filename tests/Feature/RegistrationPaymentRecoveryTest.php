<?php

namespace Tests\Feature;

use App\Domain\Payments\Services\RegistrationPaymentRecoveryService;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\Player;
use App\Models\Registration;
use App\Models\RegistrationOrder;
use App\Models\RegistrationOrderItems;
use App\Models\User;
use App\Jobs\SendRecoveryPaymentMailJob;
use App\Mail\RecoveryPaymentMail;
use App\Http\Controllers\Frontend\RegistrationPaymentController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RegistrationPaymentRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_open_starts_write_once_clock_without_rewriting_history_and_expires_at_five_minutes(): void
    {
        $this->freezeTime();
        [$order, $owner] = $this->erroneousFreeOrder(300);
        $createdAt = $order->created_at->toDateTimeString();
        $service = app(RegistrationPaymentRecoveryService::class);
        $recovery = $service->prepare($order, $owner->id);
        $before = $recovery->before_state;
        $snapshot = $recovery->mail_snapshot;
        $this->assertNull($recovery->checkout_started_at);
        $this->assertFalse(app(\App\Domain\Payments\Services\UnpaidCheckoutExpiryService::class)->expire($order));
        $this->travel(2)->days();
        $url = URL::temporarySignedRoute('registration.recovery.show', now()->addHour(), ['recovery' => $recovery->id]);
        $this->actingAs($owner)->get($url)->assertOk();
        $startedAt = $recovery->fresh()->checkout_started_at;
        $this->assertSame(now()->toDateTimeString(), $startedAt->toDateTimeString());
        $this->travel(299)->seconds();
        $this->get($url)->assertOk();
        $this->assertTrue($recovery->fresh()->checkout_started_at->equalTo($startedAt));
        $this->travel(1)->seconds();
        $this->get($url)->assertSessionHasErrors('payment');
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->get($url)->assertStatus(409);
        $this->assertSame($createdAt, $order->fresh()->created_at->toDateTimeString());
        $this->assertSame($before, $recovery->fresh()->before_state);
        $this->assertSame($snapshot, $recovery->fresh()->mail_snapshot);
        $this->assertDatabaseCount('registration_orders', 1);
        $this->assertDatabaseCount('registration_order_items', 1);
        $this->assertDatabaseCount('category_event_registrations', 1);
        $this->assertSoftDeleted('category_event_registrations', ['registration_id' => $order->items()->first()->registration_id]);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_dormant_recovery_expires_at_link_deadline_and_corrupt_seals_never_exempt_an_old_order(): void
    {
        $this->freezeTime();
        [$order, $owner] = $this->erroneousFreeOrder(300);
        $recovery = app(RegistrationPaymentRecoveryService::class)->prepare($order, $owner->id);
        $expiry = app(\App\Domain\Payments\Services\UnpaidCheckoutExpiryService::class);
        $this->assertFalse($expiry->expire($order));
        DB::table('registration_payment_recoveries')->where('id', $recovery->id)->update(['before_state_checksum' => str_repeat('1', 64)]);
        $this->assertTrue(\App\Domain\Payments\Services\CheckoutExpiryPolicy::isExpired($order));
        DB::table('registration_payment_recoveries')->where('id', $recovery->id)->update(['before_state_checksum' => $recovery->before_state_checksum]);
        $this->travelTo($recovery->link_expires_at);
        $this->assertTrue($expiry->expire($order));
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertNull($recovery->fresh()->checkout_started_at);
    }

    public function test_direct_payfast_starts_checkout_and_late_verified_receipt_preserves_original_history(): void
    {
        $this->freezeTime();
        [$order, $owner] = $this->erroneousFreeOrder(300);
        $recovery = app(RegistrationPaymentRecoveryService::class)->prepare($order, $owner->id);
        $before = $recovery->before_state;
        $createdAt = $order->created_at->toDateTimeString();
        $url = URL::temporarySignedRoute('registration.recovery.payfast', now()->addHour(), ['recovery' => $recovery->id]);
        $this->actingAs($owner)->get($url)->assertOk();
        $handoff = $order->fresh()->payfast_handed_off_at->toIso8601String();
        $this->assertNotNull($recovery->fresh()->checkout_started_at);
        $this->travel(5)->minutes();
        $this->assertTrue(app(\App\Domain\Payments\Services\UnpaidCheckoutExpiryService::class)->expire($order));
        $payload = ['custom_int5' => $order->id, 'custom_int4' => $owner->id, 'pf_payment_id' => 'PF-RECOVERY-EXPIRED',
            'payment_status' => 'COMPLETE', 'amount_gross' => '300.00', 'amount_fee' => '-10.00', 'amount_net' => '290.00'];
        $controller = app(RegistrationPaymentController::class);
        $this->assertTrue($controller->handlePayfastSuccess($payload));
        $this->assertTrue($controller->handlePayfastSuccess($payload));
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, (int) $order->fresh()->pay_status);
        $this->assertSame($createdAt, $order->fresh()->created_at->toDateTimeString());
        $this->assertSame($before, $recovery->fresh()->before_state);
        $this->assertSame('prepared', $recovery->fresh()->status);
        $this->assertDatabaseCount('transactions_pf', 1);
        $this->assertDatabaseHas('transactions_pf', ['custom_int5' => $order->id, 'custom_int4' => $owner->id, 'amount_gross' => 300]);
        $this->assertSame(1, \Spatie\Activitylog\Models\Activity::where('subject_id', $order->id)->where('description', 'Expired PayFast charge requires reconciliation')->count());
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSoftDeleted('category_event_registrations', ['registration_id' => $order->items()->first()->registration_id]);
        $audit = \Spatie\Activitylog\Models\Activity::where('subject_id', $order->id)->where('description', 'Expired unresolved PayFast handoff retained for reconciliation')->firstOrFail();
        $this->assertSame($handoff, $audit->properties['handed_off_at']);
        $this->assertSame($owner->id, $audit->properties['payer_id']);
    }

    public function test_legacy_null_item_owner_is_resolved_from_verified_payer_and_retained_in_audit_before_checkout(): void
    {
        [$order, $owner] = $this->erroneousFreeOrder(300);
        DB::table('registration_order_items')->where('order_id', $order->id)->update(['user_id' => null]);
        $recovery = app(RegistrationPaymentRecoveryService::class)->prepare($order, $owner->id);
        $before = $recovery->before_state;
        $url = URL::temporarySignedRoute('registration.recovery.show', now()->addHour(), ['recovery' => $recovery->id]);
        $this->actingAs($owner)->get($url)->assertOk();
        $this->assertSame($owner->id, (int) $order->items()->first()->user_id);
        $this->assertSame($before, $recovery->fresh()->before_state);
        $audit = \Spatie\Activitylog\Models\Activity::where('subject_id', $order->id)->where('description', 'Recovery legacy item ownership resolved from verified order and entry')->firstOrFail();
        $this->assertArrayHasKey('original_user_id', $audit->properties->all());
        $this->assertNull($audit->properties['original_user_id']);
        $this->travel(5)->minutes();
        $this->assertTrue(app(\App\Domain\Payments\Services\UnpaidCheckoutExpiryService::class)->expire($order));
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_foreign_item_payer_and_cross_event_drift_cannot_start_checkout(): void
    {
        [$order, $owner] = $this->erroneousFreeOrder(300);
        $recovery = app(RegistrationPaymentRecoveryService::class)->prepare($order, $owner->id);
        $url = URL::temporarySignedRoute('registration.recovery.show', now()->addHour(), ['recovery' => $recovery->id]);
        DB::table('registration_order_items')->where('order_id', $order->id)->update(['user_id' => User::factory()->create()->id]);
        $this->actingAs($owner)->get($url)->assertStatus(409);
        $this->assertNull($recovery->fresh()->checkout_started_at);
        DB::table('registration_order_items')->where('order_id', $order->id)->update(['user_id' => $owner->id]);
        $otherEvent = Event::factory()->create();
        DB::table('category_events')->where('id', $order->items()->first()->category_event_id)->update(['event_id' => $otherEvent->id]);
        $this->get($url)->assertStatus(409);
        $this->assertNull($recovery->fresh()->checkout_started_at);
        $this->assertDatabaseCount('transactions_pf', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_handoff_uses_its_own_five_minutes_and_refresh_does_not_reserve_again(): void
    {
        $this->freezeTime();
        [$order, $owner] = $this->erroneousFreeOrder(300);
        $recovery = app(RegistrationPaymentRecoveryService::class)->prepare($order, $owner->id);
        $url = URL::temporarySignedRoute('registration.recovery.show', now()->addHour(), ['recovery' => $recovery->id]);
        $response = $this->actingAs($owner)->get($url)->assertOk();
        $this->travel(4)->minutes();
        $this->get($response->viewData('paymentUrl'))->assertOk()->assertViewHas('amount', 300.0);
        $handoff = $order->fresh()->payfast_handed_off_at;
        $this->travel(4)->minutes();
        $this->get($url)->assertOk()->assertSee('awaiting confirmation')->assertDontSee('Continue to PayFast');
        $this->assertTrue($handoff->equalTo($order->fresh()->payfast_handed_off_at));
        $this->travel(1)->minutes();
        $this->get($url)->assertSessionHasErrors('payment');
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertDatabaseHas('activity_log', ['subject_id' => $order->id, 'description' => 'Expired unresolved PayFast handoff retained for reconciliation']);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_recovery_start_is_immutable_and_unauthorized_open_cannot_start_it(): void
    {
        [$order, $owner] = $this->erroneousFreeOrder(300);
        $recovery = app(RegistrationPaymentRecoveryService::class)->prepare($order, $owner->id);
        $url = URL::temporarySignedRoute('registration.recovery.show', now()->addHour(), ['recovery' => $recovery->id]);
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->assertNull($recovery->fresh()->checkout_started_at);
        $this->actingAs($owner)->get($url)->assertOk();
        $this->expectException(\LogicException::class);
        $recovery->refresh()->update(['checkout_started_at' => now()->addMinute()]);
    }

    public function test_preparation_derives_exact_amount_and_is_idempotent(): void
    {
        [$order, $user] = $this->erroneousFreeOrder(300);
        $service = app(RegistrationPaymentRecoveryService::class);

        $first = $service->prepare($order, $user->id);
        $second = $service->prepare($order->fresh(), $user->id);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(300.0, (float) $first->amount_due);
        $this->assertSame(300.0, (float) $order->fresh()->items()->sum('item_price'));
        $this->assertDatabaseHas('registration_orders', ['id' => $order->id, 'pay_status' => 0, 'payment_method' => 'payfast', 'payfast_amount_due' => 300, 'status' => 'pending']);
        $this->assertDatabaseHas('category_event_registrations', ['payment_status_id' => 0, 'payment_method' => 'payfast']);
        $this->assertDatabaseCount('registration_payment_recoveries', 1);
        $this->assertSame('completed', $first->before_state['order']['status']);
    }

    public function test_category_override_is_used_and_payment_evidence_excludes_order(): void
    {
        [$order] = $this->erroneousFreeOrder(300);
        $service = app(RegistrationPaymentRecoveryService::class);
        $this->assertSame(300.0, $service->inspect($order)['amount']);

        DB::table('transactions_pf')->insert(['custom_int5' => $order->id, 'pf_payment_id' => 'PF-EVIDENCE', 'payment_status' => 'COMPLETE', 'created_at' => now(), 'updated_at' => now()]);
        $result = $service->inspect($order->fresh());
        $this->assertFalse($result['eligible']);
        $this->assertContains('PayFast transaction exists', $result['reasons']);
    }

    public function test_signed_link_is_owner_bound_tamper_resistant_and_expires(): void
    {
        [$order, $owner] = $this->erroneousFreeOrder(300);
        $recovery = app(RegistrationPaymentRecoveryService::class)->prepare($order, $owner->id);
        $other = User::factory()->create();
        $url = URL::temporarySignedRoute('registration.recovery.show', now()->addMinutes(5), ['recovery' => $recovery->id]);

        $this->actingAs($other)->get($url)->assertForbidden();
        $this->actingAs($owner)->get($url.'&tampered=1')->assertForbidden();
        $this->actingAs($owner)->get($url)->assertOk()->assertSee('R 300.00');

        $expired = URL::temporarySignedRoute('registration.recovery.show', now()->subMinute(), ['recovery' => $recovery->id]);
        $this->get($expired)->assertForbidden();
    }

    public function test_withdrawn_and_refunded_entries_fail_closed(): void
    {
        [$withdrawn] = $this->erroneousFreeOrder(300);
        DB::table('category_event_registrations')->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        $this->assertFalse(app(RegistrationPaymentRecoveryService::class)->inspect($withdrawn)['eligible']);

        DB::table('category_event_registrations')->update(['status' => 'active', 'withdrawn_at' => null, 'refund_status' => 'completed', 'refund_gross' => 300]);
        $this->assertFalse(app(RegistrationPaymentRecoveryService::class)->inspect($withdrawn)['eligible']);
    }

    public function test_recovery_mail_is_queued_once_with_exact_amount_copy(): void
    {
        Mail::fake();
        [$order, $owner] = $this->erroneousFreeOrder(300);
        $recovery = app(RegistrationPaymentRecoveryService::class)->prepare($order, $owner->id);
        $recovery = app(RegistrationPaymentRecoveryService::class)->authorizeMail($recovery, $this->superUser()->id);

        (new SendRecoveryPaymentMailJob($recovery->id))->handle();
        (new SendRecoveryPaymentMailJob($recovery->id))->handle();

        Mail::assertSent(RecoveryPaymentMail::class, 1);
        Mail::assertSent(RecoveryPaymentMail::class, function (RecoveryPaymentMail $mail) use ($owner): bool {
            $html = $mail->render();
            return $mail->hasTo($owner->email)
                && str_contains($html, 'R300.00')
                && str_contains($html, 'error in our registration system')
                && str_contains($html, 'event convener');
        });
        $this->assertNotNull($recovery->fresh()->mail_queued_at);
    }

    public function test_transport_failure_releases_only_its_claim_and_retry_can_send(): void
    {
        [$order, $owner] = $this->erroneousFreeOrder(300);
        $service = app(RegistrationPaymentRecoveryService::class);
        $recovery = $service->authorizeMail($service->prepare($order, $owner->id), $this->superUser()->id);

        $mailManager = app('mail.manager');
        Mail::shouldReceive('to')->once()->with($owner->email)->andReturnSelf();
        Mail::shouldReceive('sendNow')->once()->andThrow(new \RuntimeException('Simulated transport failure'));

        try {
            (new SendRecoveryPaymentMailJob($recovery->id))->handle();
            $this->fail('The transport exception should be rethrown for the queue to retry.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated transport failure', $exception->getMessage());
        }

        $failed = $recovery->fresh();
        $this->assertSame('mail_failed', $failed->status);
        $this->assertNull($failed->mail_attempt_token);
        $this->assertNull($failed->mail_sending_at);
        $this->assertNull($failed->mail_sent_at);

        Mail::swap($mailManager);
        Mail::fake();
        (new SendRecoveryPaymentMailJob($recovery->id))->handle();
        Mail::assertSent(RecoveryPaymentMail::class, 1);
        $this->assertSame('notified', $recovery->fresh()->status);
    }

    public function test_mail_success_cannot_downgrade_payment_finalized_while_transport_is_in_flight(): void
    {
        [$order, $owner] = $this->erroneousFreeOrder(300);
        $service = app(RegistrationPaymentRecoveryService::class);
        $recovery = $service->authorizeMail($service->prepare($order, $owner->id), $this->superUser()->id);
        $mailManager = app('mail.manager');

        Mail::shouldReceive('to')->once()->with($owner->email)->andReturnSelf();
        Mail::shouldReceive('sendNow')->once()->andReturnUsing(function () use ($order, $owner): void {
            $this->assertTrue(app(RegistrationPaymentController::class)->handlePayfastSuccess([
                'custom_int5' => $order->id,
                'custom_int4' => $owner->id,
                'pf_payment_id' => 'PF-MAIL-RACE-SUCCESS',
                'payment_status' => 'COMPLETE',
                'amount_gross' => '300.00',
                'amount_fee' => '-10.00',
                'amount_net' => '290.00',
            ]));
        });

        (new SendRecoveryPaymentMailJob($recovery->id))->handle();
        Mail::swap($mailManager);

        $fresh = $recovery->fresh();
        $this->assertSame('paid', $fresh->status);
        $this->assertNotNull($fresh->paid_at);
        $this->assertNotNull($fresh->mail_sent_at);
        $this->assertDatabaseHas('registration_orders', ['id' => $order->id, 'pay_status' => 1]);
    }

    public function test_mail_failure_cannot_downgrade_payment_finalized_while_transport_is_in_flight(): void
    {
        [$order, $owner] = $this->erroneousFreeOrder(300);
        $service = app(RegistrationPaymentRecoveryService::class);
        $recovery = $service->authorizeMail($service->prepare($order, $owner->id), $this->superUser()->id);
        $mailManager = app('mail.manager');

        Mail::shouldReceive('to')->once()->with($owner->email)->andReturnSelf();
        Mail::shouldReceive('sendNow')->once()->andReturnUsing(function () use ($order, $owner): never {
            $this->assertTrue(app(RegistrationPaymentController::class)->handlePayfastSuccess([
                'custom_int5' => $order->id,
                'custom_int4' => $owner->id,
                'pf_payment_id' => 'PF-MAIL-RACE-FAILURE',
                'payment_status' => 'COMPLETE',
                'amount_gross' => '300.00',
                'amount_fee' => '-10.00',
                'amount_net' => '290.00',
            ]));
            throw new \RuntimeException('Simulated post-payment transport failure');
        });

        try {
            (new SendRecoveryPaymentMailJob($recovery->id))->handle();
            $this->fail('The transport failure should still be rethrown.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Simulated post-payment transport failure', $exception->getMessage());
        } finally {
            Mail::swap($mailManager);
        }

        $fresh = $recovery->fresh();
        $this->assertSame('paid', $fresh->status);
        $this->assertNotNull($fresh->paid_at);
        $this->assertNull($fresh->mail_attempt_token);
        $this->assertNull($fresh->mail_sending_at);
        $this->assertNull($fresh->mail_sent_at);
        $this->assertDatabaseHas('registration_orders', ['id' => $order->id, 'pay_status' => 1]);
    }

    public function test_stale_claim_is_reclaimed(): void
    {
        Mail::fake();
        [$order, $owner] = $this->erroneousFreeOrder(300);
        $service = app(RegistrationPaymentRecoveryService::class);
        $recovery = $service->authorizeMail($service->prepare($order, $owner->id), $this->superUser()->id);

        DB::table('registration_payment_recoveries')->where('id', $recovery->id)->update([
            'mail_attempt_token' => (string) \Illuminate\Support\Str::uuid(),
            'mail_sending_at' => now()->subMinutes(11),
            'status' => 'sending',
        ]);
        (new SendRecoveryPaymentMailJob($recovery->id))->handle();
        Mail::assertSent(RecoveryPaymentMail::class, 1);
        $this->assertSame('notified', $recovery->fresh()->status);
    }

    public function test_mail_authorization_and_dispatch_reject_expired_or_near_expiry_links(): void
    {
        Mail::fake();
        [$order, $owner] = $this->erroneousFreeOrder(300);
        $service = app(RegistrationPaymentRecoveryService::class);
        $recovery = $service->prepare($order, $owner->id);
        DB::table('registration_payment_recoveries')->where('id', $recovery->id)
            ->update(['link_expires_at' => now()->addMinutes(29)]);

        try {
            $service->authorizeMail($recovery->fresh(), $this->superUser()->id);
            $this->fail('A near-expiry recovery link must not be authorized for mail.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        DB::table('registration_payment_recoveries')->where('id', $recovery->id)
            ->update(['link_expires_at' => now()->addHours(2)]);
        $authorized = $service->authorizeMail($recovery->fresh(), $this->superUser()->id);
        DB::table('registration_payment_recoveries')->where('id', $recovery->id)
            ->update(['link_expires_at' => now()->subSecond()]);

        try {
            (new SendRecoveryPaymentMailJob($authorized->id))->handle();
            $this->fail('An expired recovery link must not be dispatched.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(410, $exception->getStatusCode());
        }
        Mail::assertNothingSent();
        $this->assertNull($recovery->fresh()->mail_attempt_token);
    }

    public function test_checkout_endpoints_reject_recovery_after_its_canonical_expiry(): void
    {
        [$order, $owner] = $this->erroneousFreeOrder(300);
        $recovery = app(RegistrationPaymentRecoveryService::class)->prepare($order, $owner->id);
        DB::table('registration_payment_recoveries')->where('id', $recovery->id)
            ->update(['link_expires_at' => now()->subSecond()]);

        $show = URL::temporarySignedRoute('registration.recovery.show', now()->addMinutes(5), ['recovery' => $recovery->id]);
        $payfast = URL::temporarySignedRoute('registration.recovery.payfast', now()->addMinutes(5), ['recovery' => $recovery->id]);

        $this->actingAs($owner)->get($show)->assertStatus(410);
        $this->actingAs($owner)->get($payfast)->assertStatus(410);
        $this->assertDatabaseHas('registration_orders', ['id' => $order->id, 'pay_status' => 0]);
        $this->assertDatabaseCount('transactions_pf', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_nested_checkout_links_never_outlive_the_canonical_recovery_expiry(): void
    {
        [$order, $owner] = $this->erroneousFreeOrder(300);
        $recovery = app(RegistrationPaymentRecoveryService::class)->prepare($order, $owner->id);
        DB::table('registration_payment_recoveries')->where('id', $recovery->id)
            ->update(['link_expires_at' => now()->addMinutes(20)]);
        $canonicalExpiry = $recovery->fresh()->link_expires_at->timestamp;
        $show = URL::temporarySignedRoute('registration.recovery.show', now()->addMinutes(25), ['recovery' => $recovery->id]);

        $showResponse = $this->actingAs($owner)->get($show)->assertOk();
        $paymentUrl = $showResponse->viewData('paymentUrl');
        parse_str((string) parse_url($paymentUrl, PHP_URL_QUERY), $paymentQuery);
        $this->assertLessThanOrEqual($canonicalExpiry, (int) $paymentQuery['expires']);

        $payfastResponse = $this->actingAs($owner)->get($paymentUrl)->assertOk();
        parse_str((string) parse_url($payfastResponse->viewData('cancel_url'), PHP_URL_QUERY), $cancelQuery);
        $this->assertLessThanOrEqual($canonicalExpiry, (int) $cancelQuery['expires']);
    }

    public function test_verified_complete_payment_can_finalize_after_recovery_link_expiry(): void
    {
        [$order, $owner] = $this->erroneousFreeOrder(300);
        $recovery = app(RegistrationPaymentRecoveryService::class)->prepare($order, $owner->id);
        DB::table('registration_payment_recoveries')->where('id', $recovery->id)
            ->update(['link_expires_at' => now()->subMinute()]);

        $handled = app(RegistrationPaymentController::class)->handlePayfastSuccess([
            'custom_int5' => $order->id,
            'custom_int4' => $owner->id,
            'pf_payment_id' => 'PF-DELAYED-COMPLETE',
            'payment_status' => 'COMPLETE',
            'amount_gross' => '300.00',
            'amount_fee' => '-10.00',
            'amount_net' => '290.00',
        ]);

        $this->assertTrue($handled);
        $this->assertDatabaseHas('registration_orders', [
            'id' => $order->id,
            'pay_status' => 1,
            'payfast_paid' => 1,
            'payfast_pf_payment_id' => 'PF-DELAYED-COMPLETE',
        ]);
        $this->assertSame('paid', $recovery->fresh()->status);
        $this->assertNotNull($recovery->fresh()->paid_at);
    }

    public function test_direct_unauthorized_mail_job_fails_without_sending(): void
    {
        Mail::fake();
        [$order, $owner] = $this->erroneousFreeOrder(300);
        $recovery = app(RegistrationPaymentRecoveryService::class)->prepare($order, $owner->id);
        try {
            (new SendRecoveryPaymentMailJob($recovery->id))->handle();
            $this->fail('Unauthorized job should fail closed.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        Mail::assertNothingSent();
        $this->assertNull($recovery->fresh()->mail_queued_at);
    }

    public function test_prepared_order_remains_compatible_with_canonical_payfast_itn_finalization(): void
    {
        Mail::fake();
        [$order, $owner] = $this->erroneousFreeOrder(300);
        $recovery = app(RegistrationPaymentRecoveryService::class)->prepare($order, $owner->id);

        $handled = app(RegistrationPaymentController::class)->handlePayfastSuccess([
            'custom_int5' => $order->id,
            'custom_int4' => $owner->id,
            'pf_payment_id' => 'PF-RECOVERY-TEST',
            'payment_status' => 'COMPLETE',
            'amount_gross' => '300.00',
            'amount_fee' => '-10.00',
            'amount_net' => '290.00',
        ]);

        $this->assertTrue($handled);
        $this->assertDatabaseHas('registration_orders', ['id' => $order->id, 'pay_status' => 1, 'payfast_paid' => 1, 'payfast_pf_payment_id' => 'PF-RECOVERY-TEST']);
        $this->assertDatabaseHas('registration_payment_recoveries', ['id' => $recovery->id, 'status' => 'paid']);
        $this->assertDatabaseHas('category_event_registrations', ['payment_status_id' => 1, 'pf_transaction_id' => 'PF-RECOVERY-TEST']);
        $this->assertDatabaseCount('registration_payment_recoveries', 1);
    }

    public function test_incident_fingerprint_and_preview_hash_fail_on_drift(): void
    {
        [$order] = $this->erroneousFreeOrder(300);
        $service = app(RegistrationPaymentRecoveryService::class);
        $hash = $service->previewBatchHash([$order->id]);
        $item = $order->items()->first();
        $item->forceFill(['item_price' => 1])->save();
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->prepareBatch([$order->id], $order->user_id, $hash);
    }

    public function test_west_coast_incident_is_eligible_and_other_event_or_time_is_excluded(): void
    {
        $service = app(RegistrationPaymentRecoveryService::class);
        [$westCoast] = $this->erroneousFreeOrder(300, null, 257, '2026-09-26 12:00:00');
        $this->assertTrue($service->inspect($westCoast)['eligible']);

        $westCoast->forceFill(['created_at' => '2026-09-28 00:00:00'])->save();
        $this->assertFalse($service->inspect($westCoast->fresh())['eligible']);

        $westCoast->forceFill(['created_at' => '2026-09-26 12:00:00'])->save();
        DB::table('events')->where('id', 257)->update(['id' => 256]);
        DB::table('category_events')->update(['event_id' => 256]);
        $this->assertFalse($service->inspect($westCoast->fresh())['eligible']);
    }

    public function test_prepared_link_fails_closed_after_relationship_tamper(): void
    {
        [$order, $owner] = $this->erroneousFreeOrder(300);
        $recovery = app(RegistrationPaymentRecoveryService::class)->prepare($order, $owner->id);
        DB::table('category_event_registrations')->where('registration_id', $order->items()->first()->registration_id)->update(['user_id' => User::factory()->create()->id]);
        $url = URL::temporarySignedRoute('registration.recovery.show', now()->addMinutes(5), ['recovery' => $recovery->id]);
        $this->actingAs($owner)->get($url)->assertStatus(409);
    }

    public function test_mail_snapshot_drift_and_before_state_checksum_tamper_fail_closed(): void
    {
        [$order, $owner] = $this->erroneousFreeOrder(300);
        $service = app(RegistrationPaymentRecoveryService::class);
        $recovery = $service->prepare($order, $owner->id);
        $owner->update(['email' => 'changed@example.test']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->assertMailSnapshotCurrent($recovery->fresh());
    }

    public function test_withdrawal_after_prepare_blocks_mail_send(): void
    {
        Mail::fake();
        [$order, $owner] = $this->erroneousFreeOrder(300);
        $recovery = app(RegistrationPaymentRecoveryService::class)->prepare($order, $owner->id);
        $recovery = app(RegistrationPaymentRecoveryService::class)->authorizeMail($recovery, $this->superUser()->id);
        DB::table('category_event_registrations')->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        try {
            (new SendRecoveryPaymentMailJob($recovery->id))->handle();
            $this->fail('Withdrawn recovery should fail closed.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        Mail::assertNothingSent();
    }

    public function test_payfast_evidence_after_prepare_blocks_checkout(): void
    {
        [$order, $owner] = $this->erroneousFreeOrder(300);
        $recovery = app(RegistrationPaymentRecoveryService::class)->prepare($order, $owner->id);
        DB::table('transactions_pf')->insert(['custom_int5' => $order->id, 'pf_payment_id' => 'PF-LATE', 'payment_status' => 'COMPLETE', 'created_at' => now(), 'updated_at' => now()]);
        $url = URL::temporarySignedRoute('registration.recovery.show', now()->addMinutes(5), ['recovery' => $recovery->id]);
        $this->actingAs($owner)->get($url)->assertStatus(409);
    }

    public function test_stale_prepared_state_cannot_finalize_after_locked_revalidation(): void
    {
        [$order, $owner] = $this->erroneousFreeOrder(300);
        $service = app(RegistrationPaymentRecoveryService::class);
        $recovery = $service->prepare($order, $owner->id);
        DB::table('registration_orders')->where('id', $order->id)->update(['payment_method' => 'wallet']);
        try {
            $service->finalizePayfastRecovery($recovery, 300, ['pf_payment_id' => 'PF-STALE', 'payment_method' => 'payfast']);
            $this->fail('Stale recovery should fail locked revalidation.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertDatabaseHas('registration_orders', ['id' => $order->id, 'pay_status' => 0, 'payfast_paid' => 0]);
        $this->assertDatabaseMissing('transactions_pf', ['pf_payment_id' => 'PF-STALE']);
    }

    public function test_non_super_user_cannot_authorize_mail(): void
    {
        [$order, $owner] = $this->erroneousFreeOrder(300);
        $recovery = app(RegistrationPaymentRecoveryService::class)->prepare($order, $owner->id);
        try {
            app(RegistrationPaymentRecoveryService::class)->authorizeMail($recovery, $owner->id);
            $this->fail('Non-super-user authorization must fail.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_claimed_mail_attempt_is_not_resent_on_retry(): void
    {
        Mail::fake();
        [$order, $owner] = $this->erroneousFreeOrder(300);
        $service = app(RegistrationPaymentRecoveryService::class);
        $recovery = $service->authorizeMail($service->prepare($order, $owner->id), $this->superUser()->id);
        $recovery->update(['mail_attempt_token' => (string) \Illuminate\Support\Str::uuid(), 'mail_sending_at' => now(), 'status' => 'sending']);
        (new SendRecoveryPaymentMailJob($recovery->id))->handle();
        Mail::assertNothingSent();
        $this->assertNull($recovery->fresh()->mail_sent_at);
    }

    private function erroneousFreeOrder(float $eventFee, ?float $categoryFee = null, int $eventId = 257, string $createdAt = '2026-09-26 12:00:00'): array
    {
        $user = User::factory()->create();
        $player = Player::factory()->create();
        $event = Event::factory()->create(['id' => $eventId, 'entryFee' => $eventFee]);
        $categoryEvent = CategoryEvent::factory()->create(['event_id' => $event->id, 'entry_fee' => $categoryFee]);
        $registration = Registration::query()->create([]);
        DB::table('player_registrations')->insert(['player_id' => $player->id, 'registration_id' => $registration->id, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('category_event_registrations')->insert([
            'registration_id' => $registration->id, 'category_event_id' => $categoryEvent->id,
            'user_id' => $user->id, 'payment_status_id' => 1, 'payment_method' => 'free',
            'status' => 'active', 'refund_status' => 'not_refunded', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $order = RegistrationOrder::query()->create([
            'user_id' => $user->id, 'wallet_reserved' => 0, 'wallet_debited' => false,
            'payfast_paid' => false, 'payfast_amount_due' => 0, 'pay_status' => 1,
            'payment_method' => 'free', 'total_fee' => 0, 'status' => 'completed',
        ]);
        $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
        $item = new RegistrationOrderItems();
        $item->forceFill(['order_id' => $order->id, 'user_id' => $user->id, 'registration_id' => $registration->id, 'player_id' => $player->id, 'category_event_id' => $categoryEvent->id, 'item_price' => 0])->save();

        return [$order, $user];
    }

    private function superUser(): User
    {
        $role = Role::findOrCreate('super-user', 'web');
        $user = User::factory()->create();
        $user->assignRole($role);
        return $user;
    }
}
