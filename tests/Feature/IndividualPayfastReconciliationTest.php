<?php

namespace Tests\Feature;

use App\Domain\Payments\Services\{IndividualPayfastReconciliationService, PaymentTransactionService, RegistrationPaymentService, UnpaidCheckoutExpiryService};
use App\Models\{AuditEvent, CategoryEvent, CategoryEventRegistration, Event, EventNomination, EventType, InterprovincialTrialInvitation, InterprovincialTrialInvitationBatch, Player, RegistrationOrder, RegistrationOrderItems, Transaction, User};
use App\Services\InterprovincialTrials\InvitationService;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\{DB, Mail, Schema};
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class IndividualPayfastReconciliationTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        $this->assertTrue($this->usesIsolatedPaymentDatabase(), 'Payment regressions require isolated ct_testing MySQL or in-memory SQLite.');
        // Each test application owns a fresh in-memory SQLite connection.
        if (DB::connection()->getDriverName() === 'sqlite' && DB::connection()->getDatabaseName() === ':memory:') {
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
        }
    }

    protected function tearDown(): void
    {
        try {
            // Failed setup against an unsafe connection must never truncate it.
            if ($this->app && $this->app->bound('db') && $this->usesIsolatedPaymentDatabase()) {
                try {
                    $this->truncateTablesForAllConnections();
                } finally {
                    if (DB::connection()->getDriverName() === 'sqlite') \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
                }
            }
        } finally {
            parent::tearDown();
        }
    }

    private function usesIsolatedPaymentDatabase(): bool
    {
        $connection = DB::connection();
        return ($connection->getDriverName() === 'mysql' && $connection->getDatabaseName() === 'ct_testing')
            || ($connection->getDriverName() === 'sqlite' && $connection->getDatabaseName() === ':memory:');
    }

    public function test_preview_and_apply_restore_original_records_with_live_receipt_schema_and_replay_once(): void
    {
        [$actor, $order, $entry, $invitation, $evidence] = $this->scenario();
        Schema::table('transactions_pf', fn ($table) => $table->dropColumn(['registration_id', 'payment_status']));
        try {
        $before = $this->counts();
        $preview = $this->runRecovery($order, $actor, $evidence);
        $this->assertSame($before, $this->counts());
        $this->assertFalse($preview['applied']);
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertTrue($entry->fresh()->trashed());
        $result = $this->runRecovery($order, $actor, $evidence, $preview['confirmation']);
        $this->assertTrue($result['applied']);
        $this->assertTrue($order->fresh()->pay_status);
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('active', $entry->fresh()->status);
        $this->assertFalse($entry->fresh()->trashed());
        $this->assertSame($order->id, $invitation->fresh()->order_id);
        $this->assertSame(InterprovincialTrialInvitation::PAID_CONFIRMED, $invitation->fresh()->status);
        $this->assertSame($evidence['paid_at'], $invitation->fresh()->paid_at->toIso8601String());
        $receipt = Transaction::sole();
        $this->assertEquals(450, $receipt->amount_gross);
        $this->assertEquals(18.86, $receipt->amount_fee);
        $this->assertEquals(431.14, $receipt->amount_net);
        $this->assertSame($order->id, (int) $receipt->custom_int5);
        $this->assertSame($entry->registration_id, $order->items()->sole()->registration_id);
        $this->assertSame($entry->registration_id, (int) \Spatie\Activitylog\Models\Activity::where('description', IndividualPayfastReconciliationService::AUDIT)->sole()->properties['registration_id']);
        $after = $this->counts();
        $this->assertSame($before['orders'], $after['orders']);
        $this->assertSame($before['items'], $after['items']);
        $this->assertSame($before['entries'], $after['entries']);
        $this->assertSame($before['wallet'], $after['wallet']);
        $this->assertSame($before['mail'], $after['mail']);
        $this->assertTrue($this->runRecovery($order, $actor, $evidence, $preview['confirmation'])['already_reconciled']);
        $this->assertSame($after, $this->counts());
        $payload = ['merchant_id' => 'test-merchant', 'payment_status' => 'COMPLETE', 'pf_payment_id' => $evidence['provider_id'],
            'amount_gross' => $evidence['gross'], 'custom_int1' => $evidence['category_event_id'], 'custom_int2' => $evidence['player_id'],
            'custom_int3' => $evidence['event_id'], 'custom_int4' => $evidence['payer_id'], 'custom_int5' => $order->id];
        config(['services.payfast.sandbox' => false, 'services.payfast.merchant_id' => 'test-merchant', 'services.payfast.passphrase_live' => 'test-passphrase']);
        $payload['signature'] = md5(http_build_query($payload).'&passphrase=test-passphrase');
        $this->call('POST', route('notify'), $payload, [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], http_build_query($payload))->assertOk();
        $this->assertSame($after, $this->counts());
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        } finally {
            Schema::table('transactions_pf', function ($table) {
                $table->unsignedBigInteger('registration_id')->nullable();
                $table->string('payment_status')->nullable();
            });
        }
    }

    public function test_foreign_actor_cannot_preview_or_apply_or_reuse_another_actors_token(): void
    {
        [$actor, $order, , , $evidence] = $this->scenario();
        $preview = $this->runRecovery($order, $actor, $evidence);
        try {
            $this->runRecovery($order, User::factory()->create(), $evidence);
            $this->fail('Non-super-user must be rejected.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $other = User::factory()->create()->assignRole('super-user');
        $this->blocked(fn () => $this->runRecovery($order, $other, array_replace($evidence, ['verified_by' => $other->id]), $preview['confirmation']));
        $actor->removeRole('super-user');
        try {
            $this->runRecovery($order, $actor, $evidence, $preview['confirmation']);
            $this->fail('A revoked role must invalidate apply authority.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertFalse($order->fresh()->pay_status);
    }

    public function test_evidence_must_match_all_identifiers_exact_money_and_on_time_provider_settlement(): void
    {
        [$actor, $order, , , $evidence] = $this->scenario();
        foreach (['order_id', 'payer_id', 'event_id', 'category_event_id', 'player_id'] as $field) {
            $this->blocked(fn () => $this->runRecovery($order, $actor, array_replace($evidence, [$field => $evidence[$field] + 1])));
        }
        foreach ([['gross' => '450.00junk'], ['fee' => '18.85'], ['gross' => '451.00', 'net' => '432.14'],
            ['provider_status' => 'pending'], ['currency' => 'USD'], ['source' => 'screenshot'], ['attested' => false],
            ['paid_at' => now()->toIso8601String()], ['paid_at' => now()->subMinutes(\App\Domain\Payments\Services\CheckoutExpiryPolicy::MINUTES + 3)->toIso8601String()],
            ['paid_at' => now()->subMinutes(2)->toIso8601String()],
            ['verified_at' => now()->subHour()->toIso8601String()]] as $bad) {
            $this->blocked(fn () => $this->runRecovery($order, $actor, array_replace($evidence, $bad)));
        }
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_historical_five_minute_expiry_is_not_reinterpreted_as_thirty_minutes(): void
    {
        [$actor, $order, , , $evidence] = $this->scenario();
        $expiry = \Spatie\Activitylog\Models\Activity::where('subject_type', $order->getMorphClass())
            ->where('subject_id', $order->id)->where('description', 'Unpaid registration checkout expired; reservation released')->sole();
        $expiry->properties = $expiry->properties->put('timeout_minutes', 5);
        $expiry->save();
        $handoff = \Spatie\Activitylog\Models\Activity::where('subject_type', $order->getMorphClass())
            ->where('subject_id', $order->id)->where('description', 'Expired unresolved PayFast handoff retained for reconciliation')->sole();
        $late = array_replace($evidence, ['paid_at' => \Carbon\CarbonImmutable::parse($handoff->properties['handed_off_at'])->addMinutes(20)->toIso8601String()]);
        $this->blocked(fn () => $this->runRecovery($order, $actor, $late));
        $this->assertFalse($this->runRecovery($order, $actor, $evidence)['applied']);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_stale_token_expired_token_and_current_invitation_changes_are_rejected(): void
    {
        [$actor, $order, , $invitation, $evidence] = $this->scenario();
        $preview = $this->runRecovery($order, $actor, $evidence);
        $invitation->update(['recipient_name' => 'Changed recipient']);
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence, $preview['confirmation']));
        $preview = $this->runRecovery($order, $actor, $evidence);
        $this->travel(11)->minutes();
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence, $preview['confirmation']));
        $this->travelBack();
        $invitation->update(['status' => 'declined']);
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        $this->assertFalse($order->fresh()->pay_status);
    }

    public function test_wallet_refund_successor_and_duplicate_receipt_history_block_recovery(): void
    {
        [$actor, $order, $entry, , $evidence] = $this->scenario();
        $order->update(['wallet_reserved' => 1]);
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        $order->update(['wallet_reserved' => 0]);
        $entry->update(['refund_status' => 'pending']);
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        $entry->update(['refund_status' => 'not_refunded']);
        $item = $order->items->sole();
        $successor = RegistrationOrder::create(['user_id' => $order->user_id, 'status' => 'pending']);
        $copy = $item->replicate();
        $copy->order_id = $successor->id;
        $copy->save();
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        $copy->delete();
        $receipt = new Transaction();
        $receipt->pf_payment_id = $evidence['provider_id'];
        $receipt->custom_int5 = $successor->id;
        $receipt->save();
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        $receipt->forceFill(['pf_payment_id' => 'PF-OTHER-REFERENCE', 'custom_int5' => $order->id, 'custom_str5' => ''])->save();
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        $this->assertFalse($order->fresh()->pay_status);
    }

    public function test_closed_registration_locked_category_and_draw_history_block_recovery(): void
    {
        [$actor, $order, , , $evidence] = $this->scenario();
        $category = CategoryEvent::findOrFail($evidence['category_event_id']);
        $category->update(['entry_limit' => 0]);
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        $category->update(['entry_limit' => null]);
        $category->update(['locked_at' => now()]);
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        $category->update(['locked_at' => null]);
        $category->event->update(['signUp' => false]);
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        $category->event->update(['signUp' => true]);
        \App\Models\Draw::factory()->create(['event_id' => $category->event_id, 'category_event_id' => $category->id]);
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        $this->assertFalse($order->fresh()->pay_status);
    }

    public function test_receipt_failure_rolls_back_entry_order_invitation_and_audit(): void
    {
        [$actor, $order, $entry, $invitation, $evidence] = $this->scenario();
        $preview = $this->runRecovery($order, $actor, $evidence);
        $before = $this->counts();
        $this->app->instance(PaymentTransactionService::class, new class extends PaymentTransactionService {
            public function recordAttestedRegistrationReceipt(RegistrationOrder $order, array $evidence, \Carbon\CarbonInterface $paidAt): Transaction
            {
                parent::recordAttestedRegistrationReceipt($order, $evidence, $paidAt);
                throw new \RuntimeException('Receipt storage failed');
            }
        });
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence, $preview['confirmation']));
        $this->assertSame($before, $this->counts());
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertTrue($entry->fresh()->trashed());
        $this->assertNull($invitation->fresh()->order_id);
        $this->assertDatabaseMissing('activity_log', ['description' => IndividualPayfastReconciliationService::AUDIT]);
        Mail::assertNothingSent();
    }

    public function test_reset_audit_tampering_cannot_supply_original_invitation_provenance(): void
    {
        [$actor, $order, , $invitation, $evidence] = $this->scenario();
        $audit = AuditEvent::where('subject_type', $invitation->getMorphClass())->where('subject_id', $invitation->id)
            ->where('action', 'interprovincial-trial-invitation.updated')->latest('id')->firstOrFail();
        DB::table('audit_events')->where('id', $audit->id)->update(['integrity_hash' => str_repeat('0', 64)]);
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        $this->assertFalse($order->fresh()->pay_status);
    }

    public function test_status_only_withdrawal_and_changed_deletion_state_cannot_be_restored(): void
    {
        [$actor, $order, $entry, , $evidence] = $this->scenario();
        $entry->update(['status' => 'withdrawn']);
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        $entry->update(['status' => 'pending_checkout']);
        $entry->forceFill(['updated_at' => now()->addSecond()])->save();
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        $this->assertTrue($entry->fresh()->trashed());
        $this->assertFalse($order->fresh()->pay_status);
    }

    public function test_equivalent_utc_provider_timestamp_is_persisted_in_application_timezone(): void
    {
        [$actor, $order, , $invitation, $evidence] = $this->scenario();
        $expected = \Carbon\CarbonImmutable::parse($evidence['paid_at']);
        $evidence['paid_at'] = $expected->utc()->toIso8601String();
        $preview = $this->runRecovery($order, $actor, $evidence);
        $this->runRecovery($order, $actor, $evidence, $preview['confirmation']);
        $this->assertTrue($invitation->fresh()->paid_at->equalTo($expected));
        $this->assertTrue(Transaction::sole()->created_at->equalTo($expected));
        $this->assertTrue($this->runRecovery($order, $actor, $evidence)['already_reconciled']);
    }

    public function test_missing_original_expiry_provenance_and_missing_entry_are_not_reconstructed(): void
    {
        foreach (['handoff', 'expiry', 'entry'] as $missing) {
            [$actor, $order, $entry, , $evidence] = $this->scenario();
            if ($missing === 'entry') $entry->forceDelete();
            else \Spatie\Activitylog\Models\Activity::where('subject_type', $order->getMorphClass())->where('subject_id', $order->id)
                ->where('description', $missing === 'handoff' ? 'Expired unresolved PayFast handoff retained for reconciliation' : 'Unpaid registration checkout expired; reservation released')->delete();
            $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
            $this->assertFalse($order->fresh()->pay_status);
        }
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_command_defaults_to_preview_and_requires_a_current_confirmation_to_apply(): void
    {
        [$actor, $order, , , $evidence] = $this->scenario();
        $path = tempnam(sys_get_temp_dir(), 'ct-reconcile-test-');
        try {
            file_put_contents($path, json_encode($evidence, JSON_THROW_ON_ERROR));
            $options = ['order' => $order->id, '--actor' => $actor->id, '--evidence' => $path, '--reason' => 'Reviewed dashboard'];
            $this->artisan('payments:reconcile-individual', $options)->assertSuccessful();
            $this->assertFalse($order->fresh()->pay_status);
            $this->artisan('payments:reconcile-individual', array_merge($options, ['--confirmation' => 'invalid']))->assertFailed();
            $this->assertFalse($order->fresh()->pay_status);
            $this->assertDatabaseCount('transactions_pf', 0);
        } finally { unlink($path); }
    }

    public function test_same_day_reconfirmation_discloses_historical_observation_and_applies_once(): void
    {
        $this->travelTo(now()->setTime(0, 3));
        [$actor, $order, , , $evidence] = $this->scenario();
        $evidence = $this->reconfirmedEvidence($evidence);
        // The UTC calendar date differs; the policy follows the application date.
        $evidence['paid_at'] = \Carbon\CarbonImmutable::parse($evidence['paid_at'])->utc()->toIso8601String();
        $before = $this->counts();
        $preview = $this->runReconfirmed($order, $actor, $evidence);
        $this->assertSame($before, $this->counts());
        $this->assertFalse($preview['provider_freshly_rechecked']);
        $this->assertStringContainsString('NOT freshly rechecked', $preview['evidence_notice']);
        $this->assertSame($evidence['observed_on'], $preview['observed_on']);
        $this->assertSame($evidence['operator_reviewed_at'], $preview['operator_reviewed_at']);
        $this->assertArrayNotHasKey('verified_at', $preview);
        $this->assertTrue($this->runReconfirmed($order, $actor, $evidence, $preview['confirmation'])['applied']);
        $audit = \Spatie\Activitylog\Models\Activity::where('description', IndividualPayfastReconciliationService::AUDIT)->sole();
        $this->assertSame(IndividualPayfastReconciliationService::RECONFIRMED_SOURCE, $audit->properties['evidence_source']);
        $this->assertFalse($audit->properties['provider_freshly_rechecked']);
        $this->assertSame($evidence['observed_on'], $audit->properties['observed_on']);
        $this->assertSame($evidence['operator_reviewed_at'], $audit->properties['operator_reviewed_at']);
        $this->assertArrayNotHasKey('verified_at', $audit->properties['provider_evidence']);
        $after = $this->counts();
        $this->assertTrue($this->runReconfirmed($order, $actor, $evidence, $preview['confirmation'])['already_reconciled']);
        $this->assertSame($after, $this->counts());
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_reconfirmation_rejects_stale_future_wrong_day_and_missing_owner_confirmation(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        [$actor, $order, , , $evidence] = $this->scenario();
        $evidence = $this->reconfirmedEvidence($evidence);
        foreach ([['operator_reviewed_at' => now()->subMinutes(11)->toIso8601String()],
            ['operator_reviewed_at' => now()->addSecond()->toIso8601String()],
            ['observed_on' => now()->subDay()->toDateString()],
            ['paid_at' => now()->subDay()->toIso8601String()], ['unchanged_payment_confirmed' => false],
            ['attested' => false], ['owner_confirmation_reference' => ''], ['verified_by' => $actor->id + 1]] as $bad) {
            $this->blocked(fn () => $this->runReconfirmed($order, $actor, array_replace($evidence, $bad)));
        }
        foreach (['operator_reviewed_at', 'observed_on', 'unchanged_payment_confirmed', 'owner_confirmation_reference'] as $missing) {
            $incomplete = $evidence;
            unset($incomplete[$missing]);
            $this->blocked(fn () => $this->runReconfirmed($order, $actor, $incomplete));
        }
        $this->blocked(fn () => $this->runReconfirmed($order, $actor, $evidence, null, 'No owner confirmation referenced'));
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_reconfirmation_review_cannot_precede_payment_or_cross_application_date(): void
    {
        $this->travelTo(now()->setTime(0, 0));
        [$actor, $order, , , $evidence] = $this->scenario();
        $evidence = $this->reconfirmedEvidence($evidence);
        foreach ([\Carbon\CarbonImmutable::parse($evidence['paid_at'])->subMinute(), now()->startOfDay()->subSecond()] as $invalidReview) {
            $this->blocked(fn () => $this->runReconfirmed($order, $actor,
                array_replace($evidence, ['operator_reviewed_at' => $invalidReview->toIso8601String()])));
        }
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_fresh_and_historical_evidence_fields_cannot_be_mixed_or_relabelled(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        [$actor, $order, , , $fresh] = $this->scenario();
        $historical = $this->reconfirmedEvidence($fresh);
        foreach ([$fresh['verified_at'], null] as $verifiedAt) {
            $this->blocked(fn () => $this->runReconfirmed($order, $actor, array_replace($historical, ['verified_at' => $verifiedAt])));
        }
        foreach (['observed_on', 'operator_reviewed_at', 'unchanged_payment_confirmed', 'owner_confirmation_reference'] as $field) {
            $this->blocked(fn () => $this->runRecovery($order, $actor, array_replace($fresh, [$field => $historical[$field]])));
            $this->blocked(fn () => $this->runRecovery($order, $actor, array_replace($fresh, [$field => null])));
        }
        $this->blocked(fn () => $this->runReconfirmed($order, $actor, array_replace($historical, ['source' => 'historical_screenshot'])));
        $this->blocked(fn () => $this->runReconfirmed($order, $actor, array_replace($historical, ['source' => 'authenticated_payfast_dashboard'])));
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_reconfirmation_token_binds_review_time_owner_confirmation_and_original_payment_guards(): void
    {
        $this->travelTo(now()->setTime(12, 0));
        [$actor, $order, , , $evidence] = $this->scenario();
        $evidence = $this->reconfirmedEvidence($evidence);
        $preview = $this->runReconfirmed($order, $actor, $evidence);
        $this->blocked(fn () => $this->runReconfirmed($order, $actor,
            array_replace($evidence, ['operator_reviewed_at' => now()->subSecond()->toIso8601String()]), $preview['confirmation']));
        $this->blocked(fn () => $this->runReconfirmed($order, $actor,
            array_replace($evidence, ['owner_confirmation_reference' => 'Owner reconfirmed unchanged payment in another review']), $preview['confirmation']));
        $this->blocked(fn () => $this->runReconfirmed($order, $actor,
            array_replace($evidence, ['gross' => '451.00', 'net' => '432.14'])));
        $this->blocked(fn () => $this->runReconfirmed($order, $actor,
            array_replace($evidence, ['event_id' => $evidence['event_id'] + 1])));
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_later_never_handed_off_expiry_is_disclosed_and_preserved_through_original_recovery_and_replay(): void
    {
        [$actor, $order, , , $evidence] = $this->scenario();
        [$later, $laterEntry] = $this->laterExpiredAttempt($order);
        $evidence['verified_at'] = now()->toIso8601String();
        $laterBefore = [$later->getRawOriginal(), $laterEntry->getRawOriginal(), $later->items->sole()->getRawOriginal()];
        $before = $this->counts();
        $preview = $this->runRecovery($order, $actor, $evidence);
        $this->assertSame([$later->id], $preview['preserved_never_handed_off_order_ids']);
        $this->assertTrue($preview['other_attempts_unchanged']);
        $this->assertSame($before, $this->counts());
        $this->assertTrue($this->runRecovery($order, $actor, $evidence, $preview['confirmation'])['applied']);
        $this->assertTrue($order->fresh()->pay_status);
        $this->assertSame($laterBefore, [$later->fresh()->getRawOriginal(), $laterEntry->fresh()->getRawOriginal(), $later->items()->sole()->getRawOriginal()]);
        $this->assertSame('cancelled', $later->fresh()->status);
        $this->assertFalse($later->fresh()->pay_status);
        $this->assertTrue($laterEntry->fresh()->trashed());
        $after = $this->counts();
        $this->assertSame($before['orders'], $after['orders']);
        $this->assertSame($before['items'], $after['items']);
        $this->assertSame($before['entries'], $after['entries']);
        $this->assertSame($before['ownership_links'], $after['ownership_links']);
        $this->assertSame($before['wallet'], $after['wallet']);
        $audit = \Spatie\Activitylog\Models\Activity::where('description', IndividualPayfastReconciliationService::AUDIT)->sole();
        $this->assertSame($later->id, $audit->properties['preserved_expired_attempts'][0]['order_id']);
        $this->assertTrue($this->runRecovery($order, $actor, $evidence, $preview['confirmation'])['already_reconciled']);
        $this->assertSame($after, $this->counts());
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_later_handed_off_or_active_attempt_still_blocks_original_recovery(): void
    {
        [$actor, $order, , , $evidence] = $this->scenario();
        [$later, $entry] = $this->laterExpiredAttempt($order, true);
        $evidence['verified_at'] = now()->toIso8601String();
        $this->assertNull($later->payfast_handed_off_at);
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertTrue($entry->fresh()->trashed());
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_later_attempt_preview_detects_reactivation_and_other_attempt_state_changes(): void
    {
        [$actor, $order, , , $evidence] = $this->scenario();
        [$later, $entry] = $this->laterExpiredAttempt($order);
        $evidence['verified_at'] = now()->toIso8601String();
        $preview = $this->runRecovery($order, $actor, $evidence);
        $later->update(['status' => 'pending']);
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence, $preview['confirmation']));
        $later->update(['status' => 'cancelled']);
        $preview = $this->runRecovery($order, $actor, $evidence);
        $later->update(['total_fee' => 451]);
        $later->items()->sole()->forceFill(['item_price' => 451])->save();
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence, $preview['confirmation']));
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertTrue($entry->fresh()->trashed());
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_later_attempt_other_payer_refund_or_missing_canonical_expiry_cannot_be_ignored(): void
    {
        [$actor, $order, , , $evidence] = $this->scenario();
        [$later, $entry] = $this->laterExpiredAttempt($order);
        $evidence['verified_at'] = now()->toIso8601String();
        $payer = $later->user_id;
        $later->update(['user_id' => User::factory()->create()->id]);
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        $later->update(['user_id' => $payer]);
        $entry->update(['refund_status' => 'pending']);
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        $entry->update(['refund_status' => 'not_refunded']);
        \Spatie\Activitylog\Models\Activity::where('subject_type', $later->getMorphClass())->where('subject_id', $later->id)->delete();
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_later_attempt_receipt_and_historical_handoff_evidence_block_recovery(): void
    {
        [$actor, $order, , , $evidence] = $this->scenario();
        [$later] = $this->laterExpiredAttempt($order);
        $evidence['verified_at'] = now()->toIso8601String();
        $receipt = new Transaction();
        $receipt->pf_payment_id = 'PF-LATER-CONFLICT';
        $receipt->custom_int5 = $later->id;
        $receipt->custom_str5 = '';
        $receipt->save();
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        $receipt->delete();
        app(\App\Support\Audit\AuditWriter::class)->record(['category' => 'data', 'action' => 'registration-order.updated',
            'subject' => $later, 'before' => ['payfast_handed_off_at' => now()->subMinutes(8)->toIso8601String()],
            'after' => ['payfast_handed_off_at' => null]], true);
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    public function test_later_attempt_with_manual_receipt_cannot_be_preserved_as_an_unused_checkout(): void
    {
        [$actor, $order, , , $evidence] = $this->scenario();
        [$later] = $this->laterExpiredAttempt($order);
        $evidence['verified_at'] = now()->toIso8601String();
        \App\Models\RegistrationManualReceipt::create(['order_id' => $later->id, 'event_id' => $evidence['event_id'],
            'amount' => 450, 'method' => 'eft', 'reference' => 'REVIEWED-LATER-RECEIPT',
            'verified_by_user_id' => $actor->id, 'paid_at' => now()]);
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertSame('cancelled', $later->fresh()->status);
        $this->assertDatabaseCount('transactions_pf', 0);
        $this->assertDatabaseCount('registration_manual_receipts', 1);
    }

    public function test_later_attempt_tampered_order_history_and_missing_history_are_rejected(): void
    {
        [$actor, $order, , , $evidence] = $this->scenario();
        [$later] = $this->laterExpiredAttempt($order);
        $evidence['verified_at'] = now()->toIso8601String();
        $history = AuditEvent::where('subject_type', $later->getMorphClass())->where('subject_id', $later->id)->get();
        $this->assertNotEmpty($history);
        DB::table('audit_events')->where('id', $history->first()->id)->update(['integrity_hash' => str_repeat('0', 64)]);
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        DB::table('audit_events')->whereIn('id', $history->pluck('id'))->delete();
        $this->blocked(fn () => $this->runRecovery($order, $actor, $evidence));
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertDatabaseCount('transactions_pf', 0);
    }

    private function laterExpiredAttempt(RegistrationOrder $original, bool $handoff = false): array
    {
        $this->travel(1)->minutes();
        $item = $original->items->sole();
        $invitation = InterprovincialTrialInvitation::where('player_id', $item->player_id)
            ->where('category_event_id', $item->category_event_id)->latest('id')->firstOrFail();
        $later = app(InvitationService::class)->accept($invitation, $original->user);
        if ($handoff) $later = app(RegistrationPaymentService::class)->preparePayfastHandoff($later, $original->user, 0, (float) $later->total_fee);
        $entry = CategoryEventRegistration::where('registration_id', $later->items->sole()->registration_id)->sole();
        $this->travel(\App\Domain\Payments\Services\CheckoutExpiryPolicy::MINUTES + 2)->minutes();
        $this->assertTrue(app(UnpaidCheckoutExpiryService::class)->expire($later->fresh()));
        return [$later->fresh('items'), $entry->fresh()];
    }

    private function reconfirmedEvidence(array $fresh): array
    {
        unset($fresh['verified_at']);
        return array_replace($fresh, ['source' => IndividualPayfastReconciliationService::RECONFIRMED_SOURCE,
            'observed_on' => now()->toDateString(), 'operator_reviewed_at' => now()->toIso8601String(),
            'unchanged_payment_confirmed' => true,
            'owner_confirmation_reference' => 'Owner confirmed PayFast unchanged in this support request']);
    }

    private function runReconfirmed(RegistrationOrder $order, User $actor, array $evidence, ?string $confirmation = null, ?string $reason = null): array
    {
        $reason ??= 'Operator reviewed prior authenticated dashboard evidence; '.($evidence['owner_confirmation_reference'] ?? 'owner confirmation missing');
        return app(IndividualPayfastReconciliationService::class)->run($order->id, $actor, $evidence, $reason, $confirmation);
    }

    private function runRecovery(RegistrationOrder $order, User $actor, array $evidence, ?string $confirmation = null): array
    {
        return app(IndividualPayfastReconciliationService::class)->run($order->id, $actor, $evidence, 'Reviewed authenticated dashboard settlement', $confirmation);
    }

    private function blocked(callable $action): void
    {
        try { $action(); }
        catch (ValidationException|\RuntimeException $exception) {
            $this->assertNotSame('', $exception->getMessage());
            return;
        }
        $this->fail('Unsafe reconciliation should be rejected.');
    }

    private function counts(): array
    {
        return ['orders' => RegistrationOrder::count(), 'items' => RegistrationOrderItems::count(),
            'entries' => CategoryEventRegistration::withTrashed()->count(), 'receipts' => Transaction::count(),
            'wallet' => DB::table('wallet_transactions')->count(), 'mail' => DB::table('bulk_email_logs')->count(),
            'registrations' => DB::table('registrations')->count(), 'ownership_links' => DB::table('user_players')->count()];
    }

    private function scenario(): array
    {
        $connection = DB::connection();
        $this->assertTrue(
            ($connection->getDriverName() === 'mysql' && $connection->getDatabaseName() === 'ct_testing')
            || ($connection->getDriverName() === 'sqlite' && $connection->getDatabaseName() === ':memory:'),
            'Payment regressions require isolated ct_testing MySQL or in-memory SQLite.'
        );
        Mail::fake();
        \App\Models\SiteSetting::set('player_email_on_registration', '1');
        \App\Models\SiteSetting::set('player_email_on_admin_entry', '1');
        config(['services.payment_failure_alert.enabled' => false]);
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('super-user');
        $this->travelTo(now()->startOfSecond());
        $type = EventType::query()->forceCreate(['name' => 'Reviewed Interpro', 'type' => EventType::INDIVIDUAL, 'code' => EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $event = Event::factory()->create(['eventType' => $type->id, 'entryFee' => 450, 'cape_tennis_fee' => 10,
            'published' => true, 'status' => 'active', 'signUp' => true, 'start_date' => now()->addDays(20)->toDateString(), 'deadline' => 2]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id, 'nominations_published' => true, 'entry_fee' => 450]);
        $payer = User::factory()->create();
        $payer->wallet()->create();
        $player = Player::factory()->create(['email_2' => 'additional@example.test']);
        $nomination = EventNomination::create(['event_id' => $event->id, 'category_event_id' => $category->id, 'player_id' => $player->id]);
        $batch = InterprovincialTrialInvitationBatch::create(['event_id' => $event->id, 'status' => 'draft', 'snapshot_hash' => str_repeat('a', 64), 'created_by_user_id' => $actor->id]);
        $invitation = InterprovincialTrialInvitation::create(['batch_id' => $batch->id, 'event_id' => $event->id,
            'category_event_id' => $category->id, 'nomination_id' => $nomination->id, 'player_id' => $player->id, 'status' => 'sent']);
        $order = app(InvitationService::class)->accept($invitation, $payer);
        $order = app(RegistrationPaymentService::class)->preparePayfastHandoff($order, $payer, 0, 450);
        $entry = CategoryEventRegistration::where('registration_id', $order->items->sole()->registration_id)->sole();
        $paidAt = now()->addMinutes(2)->toIso8601String();
        $this->travel(\App\Domain\Payments\Services\CheckoutExpiryPolicy::MINUTES + 2)->minutes();
        $this->assertTrue(app(UnpaidCheckoutExpiryService::class)->expire($order->fresh()));
        $evidence = ['source' => 'authenticated_payfast_dashboard', 'attested' => true, 'verified_by' => $actor->id,
            'verified_at' => now()->toIso8601String(), 'provider_status' => 'successful', 'currency' => 'ZAR',
            'provider_id' => 'PF-RECONCILE-ORIGINAL', 'order_id' => $order->id, 'payer_id' => $payer->id,
            'event_id' => $event->id, 'category_event_id' => $category->id, 'player_id' => $player->id,
            'paid_at' => $paidAt, 'gross' => '450.00', 'fee' => '18.86', 'net' => '431.14'];
        return [$actor, $order->fresh(), $entry->fresh(), $invitation->fresh(), $evidence];
    }
}
