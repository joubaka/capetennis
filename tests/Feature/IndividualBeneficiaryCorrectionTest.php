<?php

namespace Tests\Feature;

use App\Domain\Entries\Services\EntryService;
use App\Domain\Payments\Services\RegistrationPaymentService;
use App\Models\{CategoryEvent, CategoryEventRegistration, Event, EventNomination, EventType, IndividualBeneficiaryCorrection, InterprovincialTrialInvitation, InterprovincialTrialInvitationBatch, Player, RegistrationOrder, Transaction, User};
use App\Services\InterprovincialTrials\InvitationService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\{DB, Mail};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IndividualBeneficiaryCorrectionTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        $this->assertTrue((DB::connection()->getDriverName() === 'mysql' && DB::connection()->getDatabaseName() === 'ct_testing')
            || (DB::connection()->getDriverName() === 'sqlite' && DB::connection()->getDatabaseName() === ':memory:'));
        if (DB::connection()->getDriverName() === 'sqlite') \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
    }

    public function test_correction_preserves_money_original_evidence_counts_and_repeat_is_idempotent(): void
    {
        [$actor, $payer, $order, $entry, $source, $target] = $this->scenario();
        $before = [$order->getAttributes(), $order->items()->sole()->getAttributes(), Transaction::sole()->getAttributes(), $entry->paymentInfo()];
        $counts = $this->counts();
        $preview = $this->correct($actor, $order, $target);
        $this->assertFalse($preview['applied']);
        $this->assertSame($counts, $this->counts());
        $this->assertSame($source->player_id, $entry->registration->players()->sole()->id);
        $result = $this->correct($actor, $order, $target, $preview['confirmation']);
        $this->assertTrue($result['applied']);
        $this->assertSame($counts, $this->counts());
        $this->assertEquals($before, [$order->fresh()->getAttributes(), $order->items()->sole()->getAttributes(), Transaction::sole()->getAttributes(), $entry->fresh()->paymentInfo()]);
        $this->assertSame($target->player_id, $entry->fresh()->registration->players()->sole()->id);
        $this->assertNull($source->fresh()->order_id);
        $this->assertSame('sent', $source->fresh()->status);
        $this->assertSame('paid_confirmed', $target->fresh()->status);
        $this->assertSame($order->id, $target->fresh()->order_id);
        $this->assertSame($source->paid_at->toIso8601String(), $target->fresh()->paid_at->toIso8601String());
        $this->assertTrue($this->correct($actor, $order, $target, $preview['confirmation'])['already_applied']);
        $this->assertDatabaseCount('individual_beneficiary_corrections', 1);
        $this->assertSame($payer->id, $entry->fresh()->user_id);
        Mail::assertNothingSent(); Mail::assertNothingQueued();
    }

    public function test_signed_original_paid_replay_preserves_corrected_participation_and_rejects_changed_amount(): void
    {
        [$actor, , $order, $entry, , $target] = $this->scenario();
        $preview = $this->correct($actor, $order, $target);
        $this->correct($actor, $order, $target, $preview['confirmation']);
        $payload = ['merchant_id' => 'test-merchant', 'payment_status' => 'COMPLETE', 'pf_payment_id' => 'PF-CORRECTION',
            'amount_gross' => '450.00', 'custom_int1' => $entry->category_event_id, 'custom_int2' => $order->items()->sole()->player_id,
            'custom_int3' => $entry->categoryEvent->event_id, 'custom_int4' => $order->user_id, 'custom_int5' => $order->id];
        config(['services.payfast.sandbox' => false, 'services.payfast.merchant_id' => 'test-merchant', 'services.payfast.passphrase_live' => 'test-passphrase']);
        $send = function ($data) {
            $data['signature'] = md5(http_build_query($data).'&passphrase=test-passphrase');
            return $this->call('POST', route('notify'), $data, [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], http_build_query($data));
        };
        $counts = $this->counts();
        $send($payload)->assertOk();
        $send(array_replace($payload, ['amount_gross' => '451.00']))->assertStatus(400);
        app(RegistrationPaymentService::class)->markOrderRegistrationsPaid($order, 'PF-CORRECTION', $order->user_id);
        $this->assertSame($target->player_id, $entry->fresh()->registration->players()->sole()->id);
        $this->assertSame($counts, $this->counts());
        Mail::assertNothingSent(); Mail::assertNothingQueued();
    }

    public function test_stale_preview_and_non_superuser_cannot_apply(): void
    {
        [$actor, $payer, $order, , , $target] = $this->scenario();
        try { $this->correct($payer, $order, $target); $this->fail('Role required'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        $preview = $this->correct($actor, $order, $target);
        $target->update(['recipient_name' => 'Changed after preview']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->correct($actor, $order, $target, $preview['confirmation']);
    }

    public function test_cross_event_target_and_wrong_current_amount_are_rejected_without_writes(): void
    {
        [$actor, , $order, $entry, , $target] = $this->scenario();
        $target->update(['event_id' => Event::factory()->create()->id]);
        try { $this->correct($actor, $order, $target); $this->fail('Foreign target accepted'); }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) { $this->assertDatabaseCount('individual_beneficiary_corrections', 0); }
        $target->update(['event_id' => $entry->categoryEvent->event_id]);
        $entry->categoryEvent->update(['entry_fee' => 451]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->correct($actor, $order, $target);
    }

    public function test_original_nominee_can_register_but_target_cannot_pay_again(): void
    {
        [$actor, $payer, $order, , $source, $target] = $this->scenario();
        $preview = $this->correct($actor, $order, $target);
        $this->correct($actor, $order, $target, $preview['confirmation']);
        $new = app(InvitationService::class)->accept($source->fresh(), $payer);
        $this->assertNotEquals($order->id, $new->id);
        $this->assertFalse($new->pay_status);
        $counts = $this->counts();
        $existing = app(InvitationService::class)->accept($target->fresh(), $payer);
        $this->assertSame($order->id, $existing->id);
        $this->assertSame($counts, $this->counts());
    }

    public function test_closed_target_attempt_remains_immutable_and_changes_invalidate_preview(): void
    {
        [$actor, $payer, $order, , , $target] = $this->scenario();
        $attempt = app(InvitationService::class)->accept($target, $payer);
        $attempt = app(RegistrationPaymentService::class)->cancelPayment($attempt);
        app(InvitationService::class)->resetCancelledPayment($attempt, $payer);
        $closedEntry = CategoryEventRegistration::withTrashed()->where('registration_id', $attempt->items()->sole()->registration_id)->sole();
        $before = [$attempt->getAttributes(), $attempt->items()->sole()->getAttributes(), $closedEntry->getAttributes()];
        $preview = $this->correct($actor, $order, $target);
        $this->correct($actor, $order, $target, $preview['confirmation']);
        $this->assertSame($before, [$attempt->fresh()->getAttributes(), $attempt->items()->sole()->getAttributes(), $closedEntry->fresh()->getAttributes()]);
        $this->assertTrue($closedEntry->fresh()->trashed());
    }

    public function test_expired_confirmation_cannot_apply_and_corrupt_mapping_fails_closed(): void
    {
        [$actor, , $order, $entry, , $target] = $this->scenario();
        $preview = $this->correct($actor, $order, $target);
        $this->travel(16)->minutes();
        try { $this->correct($actor, $order, $target, $preview['confirmation']); $this->fail('Expired confirmation accepted'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertDatabaseCount('individual_beneficiary_corrections', 0); }
        $preview = $this->correct($actor, $order, $target);
        $this->correct($actor, $order, $target, $preview['confirmation']);
        DB::table('individual_beneficiary_corrections')->update(['target_player_id' => 999999]);
        try { app(RegistrationPaymentService::class)->markOrderRegistrationsPaid($order, 'PF-CORRECTION', $order->user_id); $this->fail('Corrupt mapping accepted'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertSame($target->player_id, $entry->fresh()->registration->players()->sole()->id); }
    }

    public function test_cancelled_target_with_pending_eft_proof_is_rejected(): void
    {
        [$actor, $payer, $order, , , $target] = $this->scenario();
        $attempt = app(InvitationService::class)->accept($target, $payer);
        $attempt = app(RegistrationPaymentService::class)->cancelPayment($attempt);
        app(InvitationService::class)->resetCancelledPayment($attempt, $payer);
        \App\Models\TrialPaymentProof::create(['order_id' => $attempt->id, 'event_id' => $target->event_id,
            'payer_id' => $payer->id, 'path' => 'fictional-proof.pdf', 'mime_type' => 'application/pdf', 'size' => 100, 'status' => 'pending']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->correct($actor, $order, $target);
    }

    public function test_bad_receipt_tuple_or_test_receipt_is_not_accepted(): void
    {
        [$actor, , $order, , , $target] = $this->scenario();
        foreach (['custom_int1', 'custom_int2', 'custom_int3', 'custom_int4', 'custom_int5', 'is_test'] as $field) {
            $tx = Transaction::sole();
            $original = $tx->{$field};
            $tx->forceFill([$field => $field === 'is_test' ? true : 999999])->save();
            try { $this->correct($actor, $order, $target); $this->fail('Mismatched receipt accepted: '.$field); }
            catch (\Illuminate\Validation\ValidationException $e) { $this->assertDatabaseCount('individual_beneficiary_corrections', 0); }
            $tx->forceFill([$field => $original])->save();
        }
    }

    public function test_corrected_participation_keeps_one_original_payer_refund_after_withdrawal(): void
    {
        [$actor, $payer, $order, $entry, $source, $target] = $this->scenario();
        $preview = $this->correct($actor, $order, $target);
        $this->correct($actor, $order, $target, $preview['confirmation']);
        $this->assertEquals(450, $entry->fresh()->maxRefundableAmount());
        $requests = app(\App\Domain\Finance\Services\RefundRequestService::class);
        $attributes = ['refund_status' => 'pending', 'refund_method' => 'wallet', 'refund_gross' => 450, 'refund_fee' => 0, 'refund_net' => 450];
        try { $requests->requestRegistrationRefund($entry, $attributes, $payer); $this->fail('Active entry refundable'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertNull($entry->fresh()->withdrawn_at); }
        app(EntryService::class)->withdrawEntryAsAdmin($entry, $actor);
        app(InvitationService::class)->handlePaidWithdrawal($entry->registration_id, $actor);
        $johnAccount = User::factory()->create();
        $johnAccount->players()->syncWithoutDetaching([$source->player_id]);
        try { $requests->requestRegistrationRefund($entry->fresh(), $attributes, $johnAccount); $this->fail('Linked nonpayer refundable'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertSame($payer->id, $entry->fresh()->user_id); }
        $pending = $requests->requestRegistrationRefund($entry->fresh(), $attributes, $payer);
        $execution = app(\App\Domain\Refunds\Services\RefundExecutionService::class);
        $execution->executeWalletRefund($pending, $payer->wallet, 450, 'individual_refund', $entry->id);
        $execution->executeWalletRefund($pending->fresh(), $payer->wallet, 450, 'individual_refund', $entry->id);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertSame('completed', $entry->fresh()->refund_status);
        $this->assertEquals(450, $payer->wallet->transactions()->sum('amount'));
        $this->assertTrue($this->correct($actor, $order, $target, $preview['confirmation'])['already_applied']);
        app(RegistrationPaymentService::class)->markOrderRegistrationsPaid($order, 'PF-CORRECTION', $payer->id);
        $this->assertSame('withdrawn', $entry->fresh()->status);
        $this->assertSame('completed', $entry->fresh()->refund_status);
        $this->assertSame('withdrawn', $target->fresh()->status);
    }

    public function test_corrected_entry_cannot_start_category_charge_or_send_direct_marker_notifications(): void
    {
        [$actor, , $order, $entry, , $target] = $this->scenario();
        Player::findOrFail($target->player_id)->update(['email_2' => 'additional@example.test']);
        \App\Models\SiteSetting::set('player_email_on_registration', '1');
        $preview = $this->correct($actor, $order, $target);
        $this->correct($actor, $order, $target, $preview['confirmation']);
        $counts = $this->counts();
        app(RegistrationPaymentService::class)->markOrderRegistrationsPaid($order, 'PF-CORRECTION', $order->user_id);
        $this->assertSame($counts, $this->counts());
        $destination = CategoryEvent::factory()->create(['event_id' => $entry->categoryEvent->event_id, 'entry_fee' => 500]);
        try { app(\App\Domain\Entries\Services\CategoryAdjustmentService::class)->quote($entry, $destination, $actor, true); $this->fail('Category charge allowed'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertDatabaseCount('category_adjustments', 0); }
        Mail::assertNothingSent(); Mail::assertNothingQueued();
    }

    private function correct($actor, $order, $target, $confirmation = null): array
    {
        return app(EntryService::class)->correctIndividualBeneficiary($actor, $order->id, $target->player_id, 'Correct mistakenly selected nominee', $confirmation);
    }

    private function counts(): array
    {
        return collect(['registration_orders', 'registration_order_items', 'registrations', 'category_event_registrations', 'player_registrations', 'transactions_pf', 'wallet_transactions', 'bulk_email_logs'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->count()])->all();
    }

    private function scenario(): array
    {
        Mail::fake();
        config(['services.payment_failure_alert.enabled' => false]);
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('super-user');
        $payer = User::factory()->create();
        $payer->wallet()->create();
        $type = EventType::forceCreate(['name' => 'Correction Interpro', 'type' => EventType::INDIVIDUAL, 'code' => EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $event = Event::factory()->create(['eventType' => $type->id, 'entryFee' => 450, 'cape_tennis_fee' => 10, 'published' => true,
            'status' => 'active', 'signUp' => true, 'start_date' => now()->addDays(20)->toDateString(), 'deadline' => 2]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id, 'nominations_published' => true, 'entry_fee' => 450]);
        $batch = InterprovincialTrialInvitationBatch::create(['event_id' => $event->id, 'status' => 'draft', 'snapshot_hash' => str_repeat('a', 64), 'created_by_user_id' => $actor->id]);
        $invitations = collect([Player::factory()->create(), Player::factory()->create()])->map(function ($player) use ($batch, $event, $category) {
            $nomination = EventNomination::create(['event_id' => $event->id, 'category_event_id' => $category->id, 'player_id' => $player->id]);
            return InterprovincialTrialInvitation::create(['batch_id' => $batch->id, 'event_id' => $event->id, 'category_event_id' => $category->id,
                'nomination_id' => $nomination->id, 'player_id' => $player->id, 'status' => 'sent']);
        });
        [$source, $target] = $invitations->all();
        $order = app(InvitationService::class)->accept($source, $payer);
        $order = app(RegistrationPaymentService::class)->preparePayfastHandoff($order, $payer, 0, 450);
        $order = app(RegistrationPaymentService::class)->finalizePayfastPayment($order, 450, ['pf_payment_id' => 'PF-CORRECTION', 'payment_method' => 'payfast']);
        $item = $order->items()->sole();
        Transaction::forceCreate(['pf_payment_id' => 'PF-CORRECTION', 'custom_int5' => $order->id, 'custom_int4' => $payer->id,
            'payment_status' => 'COMPLETE',
            'custom_int1' => $category->id, 'custom_int2' => $source->player_id, 'custom_int3' => $event->id,
            'amount_gross' => 450, 'amount_fee' => 19.10, 'amount_net' => 430.90]);
        return [$actor, $payer, $order->fresh(), CategoryEventRegistration::where('registration_id', $item->registration_id)->sole(), $source->fresh(), $target->fresh()];
    }
}
