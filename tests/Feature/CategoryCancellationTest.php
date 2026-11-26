<?php

namespace Tests\Feature;

use App\Domain\Entries\Services\{CategoryCancellationService, EntryService};
use App\Domain\Payments\Services\RegistrationPaymentService;
use App\Models\{CategoryEvent, CategoryEventRegistration, Event, Player, RegistrationOrder, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, DB, Mail};
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CategoryCancellationTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(int $count = 2): array
    {
        Bus::fake();
        Mail::fake();
        Role::findOrCreate('super-user', 'web');
        Role::findOrCreate('admin', 'web');
        DB::table('eventtypes')->updateOrInsert(['id' => 1], ['name' => 'Individual', 'type' => 1]);
        $actor = User::factory()->create()->assignRole('super-user');
        $category = CategoryEvent::factory()->create(['entry_fee' => 285]);
        $event = $category->event;
        $entries = collect();
        foreach (range(1, $count) as $index) {
            $payer = User::factory()->create();
            $entry = CategoryEventRegistration::factory()->paid()->create(['category_event_id' => $category->id, 'payment_method' => 'payfast']);
            $player = Player::factory()->create(['email' => 'shared@example.test', 'email_2' => 'extra'.$index.'@example.test']);
            $entry->registration->players()->attach($player);
            $order = RegistrationOrder::create(['user_id' => $payer->id, 'total_fee' => 285, 'pay_status' => true,
                'payfast_paid' => true, 'payfast_amount_due' => 285, 'payfast_pf_payment_id' => $entry->pf_transaction_id]);
            DB::table('registration_order_items')->insert(['order_id' => $order->id, 'registration_id' => $entry->registration_id,
                'category_event_id' => $category->id, 'player_id' => $player->id, 'user_id' => $payer->id, 'item_price' => 285]);
            $entries->push(compact('entry', 'payer', 'order', 'player'));
        }
        $this->actingAs($actor);
        return [$event, $category, $actor, $entries];
    }

    private function preview($event, $category, string $reason = 'Not enough entries for this category.')
    {
        return $this->post(route('admin.category.cancel.preview', [$event, $category]), ['reason' => $reason]);
    }

    private function apply($event, $category, array $preview, array $extra = [])
    {
        return $this->postJson(route('admin.category.cancel.store', [$event, $category]), $extra + ['preview_token' => $preview['token'], 'confirm' => 1]);
    }

    private function uniformMixedBasket(): array
    {
        [$event, $category, $actor, $entries] = $this->scenario(1);
        $row = $entries[0];
        $category->update(['entry_fee' => 250]);
        $row['entry']->update(['user_id' => $row['payer']->id]);
        DB::table('registration_order_items')->where('order_id', $row['order']->id)->update(['item_price' => 250]);
        $siblingCategory = CategoryEvent::factory()->create(['event_id' => $event->id, 'entry_fee' => 250]);
        $sibling = CategoryEventRegistration::factory()->paid()->create(['category_event_id' => $siblingCategory->id,
            'user_id' => $row['payer']->id, 'payment_method' => 'payfast', 'pf_transaction_id' => $row['order']->payfast_pf_payment_id]);
        $player = Player::factory()->create();
        $sibling->registration->players()->attach($player);
        DB::table('registration_order_items')->insert(['order_id' => $row['order']->id, 'registration_id' => $sibling->registration_id,
            'category_event_id' => $siblingCategory->id, 'player_id' => $player->id, 'user_id' => $row['payer']->id, 'item_price' => 250]);
        $row['order']->update(['total_fee' => 500, 'payfast_amount_due' => 500]);
        $receiptId = DB::table('transactions_pf')->insertGetId(['pf_payment_id' => $row['order']->payfast_pf_payment_id,
            'amount_gross' => 500, 'amount_net' => 480, 'amount_fee' => 20, 'event_id' => $event->id,
            'custom_int5' => $row['order']->id, 'created_at' => now(), 'updated_at' => now()]);
        return [$event, $category, $actor, $row, $siblingCategory, $sibling, $receiptId];
    }

    public function test_preview_then_confirmation_refunds_original_payers_and_queues_private_notices_once(): void
    {
        [$event, $category, $actor, $entries] = $this->scenario();
        $this->get(route('admin.category.cancel.show', [$event, $category]))->assertOk()->assertSee('Review cancellation');
        $response = $this->preview($event, $category)->assertOk()->assertSee('R570.00');
        $preview = $response->viewData('preview');
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $this->assertFalse($category->fresh()->isCancelled());
        $this->apply($event, $category, $preview, ['amount' => 99999, 'payer_id' => $actor->id])->assertRedirect();
        $this->assertTrue($category->fresh()->isCancelled());
        $this->assertArrayNotHasKey('cancellation', $category->fresh()->toArray());
        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertDatabaseCount('bulk_email_logs', 5); // Three deduplicated contacts and two payer-only receipts.
        $this->assertDatabaseCount('event_communication_batches', 1);
        $this->assertCount(2, collect(\App\Models\BulkEmailLog::where('payload->recipient_kind', 'players')->get())
            ->flatMap(fn ($log) => $log->payload['player_keys'])->unique());
        foreach ($entries as $row) {
            $this->assertDatabaseHas('category_event_registrations', ['id' => $row['entry']->id, 'status' => 'withdrawn',
                'refund_status' => 'completed', 'refund_gross' => 285, 'refund_fee' => 0, 'refund_net' => 285, 'payment_status_id' => 1]);
            $this->assertEquals(285, $row['payer']->fresh()->wallet->transactions()->sum('amount'));
            $this->assertNull($row['entry']->user->fresh()->wallet);
            $receipt = \App\Models\BulkEmailLog::where('recipient_email', $row['payer']->email)->firstOrFail();
            $this->assertSame('payer', $receipt->payload['recipient_kind']);
            $this->assertStringContainsString('285.00', $receipt->payload['body']);
        }
        $this->apply($event, $category, $preview)->assertRedirect();
        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertDatabaseCount('bulk_email_logs', 5);
        $this->assertDatabaseCount('event_communication_batches', 1);
        Mail::assertNothingSent();
    }

    public function test_only_assigned_event_admin_or_super_user_can_cancel_and_other_categories_are_untouched(): void
    {
        [$event, $category] = $this->scenario(1);
        $other = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $admin = User::factory()->create()->assignRole('admin');
        $this->actingAs($admin);
        $this->preview($event, $category)->assertForbidden();
        $event->admins()->attach($admin);
        $preview = $this->preview($event, $category)->assertOk()->viewData('preview');
        $this->apply($event, $other, $preview)->assertUnprocessable();
        $this->preview(Event::factory()->create(), $category)->assertNotFound();
        $this->apply($event, $category, $preview)->assertRedirect();
        $this->assertFalse($other->fresh()->isCancelled());
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_changed_contacts_payment_or_entries_requires_fresh_review(): void
    {
        [$event, $category, , $entries] = $this->scenario(1);
        $preview = $this->preview($event, $category)->assertOk()->viewData('preview');
        $entries[0]['player']->update(['email_2' => 'changed@example.test']);
        $this->apply($event, $category, $preview)->assertUnprocessable();
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $this->assertSame('active', $entries[0]['entry']->fresh()->status);
    }

    public function test_preview_is_actor_bound_expires_and_requires_confirmation(): void
    {
        [$event, $category] = $this->scenario(1);
        $preview = $this->preview($event, $category)->assertOk()->viewData('preview');
        $this->apply($event, $category, $preview, ['confirm' => 0])->assertUnprocessable();
        $other = User::factory()->create()->assignRole('super-user');
        $this->actingAs($other);
        $this->apply($event, $category, $preview)->assertUnprocessable();
        $preview = $this->preview($event, $category)->assertOk()->viewData('preview');
        $this->travel(16)->minutes();
        $this->apply($event, $category, $preview)->assertUnprocessable();
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertFalse($category->fresh()->isCancelled());
    }

    public function test_pending_refund_and_unresolved_checkout_block_before_any_partial_mutation(): void
    {
        [$event, $category, , $entries] = $this->scenario();
        $entries[1]['entry']->update(['refund_status' => 'pending', 'refund_method' => 'bank']);
        $this->preview($event, $category)->assertSessionHasErrors('cancellation');
        $entries[1]['entry']->update(['refund_status' => 'not_refunded', 'refund_method' => null]);
        $entries[1]['order']->update(['pay_status' => false]);
        $this->preview($event, $category)->assertSessionHasErrors('cancellation');
        $this->assertSame('active', $entries[0]['entry']->fresh()->status);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('bulk_email_logs', 0);
    }

    public function test_failed_second_credit_rolls_back_all_refunds_withdrawals_and_cancellation(): void
    {
        [$event, $category] = $this->scenario();
        $preview = $this->preview($event, $category)->assertOk()->viewData('preview');
        $real = app(\App\Domain\Refunds\Services\RefundExecutionService::class);
        $calls = 0;
        $this->partialMock(\App\Domain\Refunds\Services\RefundExecutionService::class, function ($mock) use ($real, &$calls) {
            $mock->shouldReceive('executeWalletRefund')->andReturnUsing(function (...$args) use ($real, &$calls) {
                if (++$calls === 2) throw new \RuntimeException('Second credit unavailable');
                return $real->executeWalletRefund(...$args);
            });
        });
        $this->apply($event, $category, $preview)->assertServerError();
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $this->assertDatabaseCount('event_communication_batches', 0);
        $this->assertFalse($category->fresh()->isCancelled());
        $this->assertSame(2, CategoryEventRegistration::where('category_event_id', $category->id)->where('status', 'active')->count());
    }

    public function test_already_withdrawn_entry_receives_full_refund_and_paid_callback_cannot_restore_it(): void
    {
        [$event, $category, $actor, $entries] = $this->scenario(1);
        app(EntryService::class)->withdrawEntryAsAdmin($entries[0]['entry'], $actor);
        $preview = $this->preview($event, $category)->assertOk()->viewData('preview');
        $this->apply($event, $category, $preview)->assertRedirect();
        app(RegistrationPaymentService::class)->markOrderRegistrationsPaid($entries[0]['order'], 'replayed', $actor->id);
        $this->assertSame('withdrawn', $entries[0]['entry']->fresh()->status);
        $this->assertSame('completed', $entries[0]['entry']->fresh()->refund_status);
        $this->expectException(ValidationException::class);
        app(EntryService::class)->unlockCategory($category->fresh(), $actor);
    }

    public function test_multiple_item_orders_and_missing_contacts_require_reconciliation(): void
    {
        [$event, $category, , $entries] = $this->scenario(1);
        DB::table('registration_order_items')->insert(['order_id' => $entries[0]['order']->id, 'item_price' => 100]);
        $this->preview($event, $category)->assertSessionHasErrors('refund');
        $this->assertFalse($category->fresh()->isCancelled());
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_mixed_category_basket_requires_review_and_preserves_remaining_entitlements(): void
    {
        [$event, $category, , $entries] = $this->scenario(1);
        $otherCategory = CategoryEvent::factory()->create(['event_id' => $event->id, 'entry_fee' => 100]);
        $otherEntry = CategoryEventRegistration::factory()->paid()->create(['category_event_id' => $otherCategory->id,
            'payment_method' => 'payfast', 'pf_transaction_id' => $entries[0]['order']->payfast_pf_payment_id]);
        DB::table('registration_order_items')->insert(['order_id' => $entries[0]['order']->id, 'registration_id' => $otherEntry->registration_id,
            'category_event_id' => $otherCategory->id, 'item_price' => 100, 'user_id' => $entries[0]['payer']->id]);
        $entries[0]['order']->update(['total_fee' => 385, 'payfast_amount_due' => 385]);
        $this->preview($event, $category)->assertSessionHasErrors('refund');
        $this->assertSame('active', $otherEntry->fresh()->status);
        $this->assertSame('not_refunded', $otherEntry->fresh()->refund_status);
        $this->assertFalse($otherCategory->fresh()->isCancelled());
        $this->assertDatabaseCount('wallet_transactions', 0);
        $otherEntry->update(['status' => 'withdrawn', 'refund_status' => 'completed', 'refund_gross' => 192.5, 'refund_net' => 192.5]);
        $this->preview($event, $category)->assertSessionHasErrors('refund');
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_two_differently_priced_items_in_same_category_are_refunded_once_to_shared_payer(): void
    {
        [$event, $category, , $entries] = $this->scenario(1);
        $other = CategoryEventRegistration::factory()->paid()->create(['category_event_id' => $category->id,
            'payment_method' => 'payfast', 'pf_transaction_id' => $entries[0]['order']->payfast_pf_payment_id]);
        $player = Player::factory()->create(['email' => 'shared@example.test']);
        $other->registration->players()->attach($player);
        DB::table('registration_order_items')->insert(['order_id' => $entries[0]['order']->id, 'registration_id' => $other->registration_id,
            'category_event_id' => $category->id, 'player_id' => $player->id, 'item_price' => 100, 'user_id' => $entries[0]['payer']->id]);
        $entries[0]['order']->update(['total_fee' => 385, 'payfast_amount_due' => 385]);
        $preview = $this->preview($event, $category)->assertOk()->viewData('preview');
        $this->assertEquals(385, $preview['total']);
        $this->apply($event, $category, $preview)->assertRedirect();
        $this->assertDatabaseHas('category_event_registrations', ['id' => $other->id, 'refund_net' => 100]);
        $this->assertDatabaseHas('category_event_registrations', ['id' => $entries[0]['entry']->id, 'refund_net' => 285]);
        $this->assertEquals(385, $entries[0]['payer']->fresh()->wallet->transactions()->sum('amount'));
        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertDatabaseCount('bulk_email_logs', 4); // Two notification contacts and separate private per-entry receipts.
    }

    public function test_existing_draw_even_without_fixtures_blocks_cancellation(): void
    {
        [$event, $category] = $this->scenario(1);
        \App\Models\Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id]);
        $this->preview($event, $category)->assertSessionHasErrors('cancellation');
        $this->assertFalse($category->fresh()->isCancelled());
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_genuinely_free_original_item_is_withdrawn_without_credit_after_repricing(): void
    {
        [$event, $category, , $entries] = $this->scenario(1);
        DB::table('registration_order_items')->where('order_id', $entries[0]['order']->id)->update(['item_price' => 0]);
        $entries[0]['order']->update(['total_fee' => 0, 'payfast_amount_due' => 0, 'payfast_paid' => false, 'payment_method' => 'free']);
        $entries[0]['entry']->update(['payment_method' => 'free', 'pf_transaction_id' => null]);
        $preview = $this->preview($event, $category)->assertOk()->viewData('preview');
        $this->assertEquals(0, $preview['total']);
        $this->apply($event, $category, $preview)->assertRedirect();
        $this->assertSame('withdrawn', $entries[0]['entry']->fresh()->status);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('bulk_email_logs', 2);
    }

    public function test_real_database_outbox_jobs_commit_with_refunds_and_never_send_during_confirmation(): void
    {
        $realBus = Bus::getFacadeRoot();
        [$event, $category] = $this->scenario(1);
        Bus::swap($realBus);
        $preview = $this->preview($event, $category)->assertOk()->viewData('preview');
        $this->apply($event, $category, $preview)->assertRedirect();
        $this->assertDatabaseCount('jobs', 3);
        $this->assertDatabaseCount('bulk_email_logs', 3);
        $this->assertDatabaseCount('wallet_transactions', 1);
        Mail::assertNothingSent();
    }

    public function test_outbox_failure_rolls_back_real_jobs_and_refunds_together(): void
    {
        $realBus = Bus::getFacadeRoot();
        [$event, $category] = $this->scenario(1);
        Bus::swap($realBus);
        $preview = $this->preview($event, $category)->assertOk()->viewData('preview');
        $real = app(\App\Services\BulkMailDispatcher::class);
        $calls = 0;
        $this->partialMock(\App\Services\BulkMailDispatcher::class, function ($mock) use ($real, &$calls) {
            $mock->shouldReceive('dispatch')->andReturnUsing(function (...$args) use ($real, &$calls) {
                if (++$calls === 2) throw new \RuntimeException('Outbox unavailable');
                return $real->dispatch(...$args);
            });
        });
        $this->apply($event, $category, $preview)->assertServerError();
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $this->assertFalse($category->fresh()->isCancelled());
    }

    public function test_cancelled_category_rejects_new_draws_and_admission(): void
    {
        [$event, $category] = $this->scenario(1);
        $preview = $this->preview($event, $category)->assertOk()->viewData('preview');
        $this->apply($event, $category, $preview)->assertRedirect();
        try {
            app(\App\Services\DivisionCapacityService::class)->assertAvailable($category->fresh());
            $this->fail('Cancelled category admitted a player.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('division', $exception->errors());
        }
        $this->expectException(ValidationException::class);
        \App\Models\Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id]);
    }

    public function test_wallet_and_hybrid_original_funding_are_refunded_in_full_to_original_payer(): void
    {
        [$event, $category, , $entries] = $this->scenario(2);
        foreach ($entries as $index => $row) {
            $wallet = $row['payer']->wallet()->create([]);
            $ledger = app(\App\Domain\Payments\Services\LedgerService::class);
            $ledger->appendWalletCredit($wallet, 285, 'test_funding', $row['order']->id);
            $walletAmount = $index === 0 ? 285 : 85;
            $debit = $ledger->appendWalletDebit($wallet, $walletAmount, 'registration', $row['order']->id);
            $row['order']->update(['wallet_reserved' => $walletAmount, 'wallet_debited' => true, 'wallet_transaction_id' => $debit->id,
                'payfast_amount_due' => 285 - $walletAmount, 'payfast_paid' => $index !== 0]);
            $row['entry']->update(['payment_method' => $index === 0 ? 'wallet' : 'hybrid', 'wallet_transaction_id' => $debit->id]);
        }
        $preview = $this->preview($event, $category)->assertOk()->viewData('preview');
        $this->assertEquals(570, $preview['total']);
        $this->apply($event, $category, $preview)->assertRedirect();
        $credits = \App\Models\WalletTransaction::where('source_type', 'category_cancellation_refund')->get();
        $this->assertCount(2, $credits);
        $this->assertEquals(570, $credits->sum('amount'));
        foreach ($entries as $row) $this->assertDatabaseHas('category_event_registrations', ['id' => $row['entry']->id, 'refund_net' => 285, 'refund_fee' => 0]);
    }

    public function test_unpaid_admin_entry_is_withdrawn_without_inventing_wallet_funds(): void
    {
        [$event, $category, $actor] = $this->scenario(1);
        $player = Player::factory()->create();
        $entry = app(EntryService::class)->addPlayerAsAdmin($category, $player->id, $actor);
        $preview = $this->preview($event, $category)->assertOk()->viewData('preview');
        $this->assertEquals(285, $preview['total']);
        $this->apply($event, $category, $preview)->assertRedirect();
        $this->assertSame('withdrawn', $entry->fresh()->status);
        $this->assertSame('not_refunded', $entry->fresh()->refund_status);
        $this->assertDatabaseCount('wallet_transactions', 1);
    }

    public function test_browser_validation_returns_to_the_get_form_instead_of_the_post_preview(): void
    {
        [$event, $category] = $this->scenario(1);
        $preview = $this->preview($event, $category)->assertOk()->viewData('preview');
        $this->travel(16)->minutes();
        $this->from(route('admin.category.cancel.preview', [$event, $category]))
            ->post(route('admin.category.cancel.store', [$event, $category]), ['preview_token' => $preview['token'], 'confirm' => 1])
            ->assertRedirect(route('admin.category.cancel.show', [$event, $category]))->assertSessionHasErrors('cancellation');
        $this->get(route('admin.category.cancel.show', [$event, $category]))->assertOk();
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_no_email_confirmation_refunds_once_without_any_batch_log_or_job(): void
    {
        $realBus = Bus::getFacadeRoot();
        [$event, $category] = $this->scenario();
        Bus::swap($realBus);
        $preview = $this->post(route('admin.category.cancel.preview', [$event, $category]), [
            'reason' => 'Already notified; insufficient entries.', 'send_emails' => 0,
        ])->assertOk()->assertSee('No emails will be sent or queued.')->viewData('preview');
        $this->assertFalse($preview['send_emails']);
        // Posted settings cannot override the immutable reviewed choice.
        $this->apply($event, $category, $preview, ['send_emails' => 1])->assertRedirect();
        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertDatabaseCount('event_communication_batches', 0);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertTrue($category->fresh()->cancellation['emails_suppressed']);
        $this->apply($event, $category, $preview)->assertRedirect();
        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_no_email_cancellation_does_not_require_notification_contacts_or_sender_email(): void
    {
        [$event, $category, $actor, $entries] = $this->scenario(1);
        $entries[0]['player']->update(['email' => null, 'email_2' => null, 'email_3' => null]);
        $entries[0]['payer']->update(['email' => 'invalid']);
        $actor->update(['email' => 'invalid-actor']);
        $event->update(['email' => 'invalid-event', 'organizer' => '']);
        $preview = $this->post(route('admin.category.cancel.preview', [$event, $category]), ['reason' => 'Already notified.', 'send_emails' => 0])
            ->assertOk()->viewData('preview');
        $this->apply($event, $category, $preview)->assertRedirect();
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $this->assertDatabaseCount('event_communication_batches', 0);
    }

    public function test_posted_no_email_flag_cannot_suppress_a_review_which_approved_emails(): void
    {
        [$event, $category] = $this->scenario(1);
        $preview = $this->preview($event, $category)->assertOk()->viewData('preview');
        $this->assertTrue($preview['send_emails']);
        $this->apply($event, $category, $preview, ['send_emails' => 0])->assertRedirect();
        $this->assertDatabaseCount('bulk_email_logs', 3);
        $this->assertFalse($category->fresh()->cancellation['emails_suppressed']);
    }

    public function test_uniform_payfast_only_mixed_basket_preserves_exact_future_sibling_refund(): void
    {
        [$event, $category, $actor, $row, $siblingCategory, $sibling] = $this->uniformMixedBasket();
        $preview = $this->post(route('admin.category.cancel.preview', [$event, $category]), ['reason' => 'Already notified.', 'send_emails' => 0])
            ->assertOk()->viewData('preview');
        $this->assertEquals(250, $preview['total']);
        $this->assertCount(2, $preview['rows'][0]['funding']['mixed_basket_proof']);
        $this->apply($event, $category, $preview)->assertRedirect();
        $this->assertSame('active', $sibling->fresh()->status);
        $this->assertSame('not_refunded', $sibling->fresh()->refund_status);
        $this->assertFalse($siblingCategory->fresh()->isCancelled());
        $this->assertEquals(250, $sibling->fresh()->paymentInfo()['gross']);
        $this->assertEquals(250, $sibling->fresh()->maxRefundableAmount());
        app(EntryService::class)->withdrawEntryAsAdmin($sibling, $actor);
        $this->actingAs($row['payer']);
        $this->post(route('registrations.refund.request', $sibling), ['method' => 'wallet'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('category_event_registrations', ['id' => $sibling->id, 'refund_gross' => 250, 'refund_fee' => 25, 'refund_net' => 225]);
        $this->assertEquals(475, $row['payer']->fresh()->wallet->transactions()->where('type', 'credit')->sum('amount'));
        $this->actingAs($actor);
        $this->apply($event, $category, $preview)->assertRedirect();
        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertEquals(475, $row['payer']->fresh()->wallet->transactions()->where('type', 'credit')->sum('amount'));
    }

    public function test_uniform_mixed_basket_rejects_missing_receipt_pending_claim_and_identity_drift(): void
    {
        [$event, $category, , $row, , $sibling, $receiptId] = $this->uniformMixedBasket();
        $sibling->update(['refund_status' => 'pending', 'refund_method' => 'bank', 'refund_gross' => 250, 'refund_fee' => 25, 'refund_net' => 225]);
        $this->preview($event, $category)->assertSessionHasErrors('refund');
        $sibling->update(['refund_status' => 'not_refunded', 'refund_method' => null, 'refund_gross' => 0, 'refund_fee' => 0, 'refund_net' => 0]);
        $sibling->update(['user_id' => User::factory()->create()->id]);
        $this->preview($event, $category)->assertSessionHasErrors('refund');
        $sibling->update(['user_id' => $row['payer']->id]);
        $sibling->update(['status' => 'withdrawn', 'refund_status' => 'completed', 'refund_gross' => 300, 'refund_net' => 300]);
        $this->preview($event, $category)->assertSessionHasErrors('refund');
        $sibling->update(['status' => 'active', 'refund_status' => 'not_refunded', 'refund_gross' => 0, 'refund_net' => 0]);
        $row['order']->update(['wallet_reserved' => 10, 'wallet_debited' => true, 'payfast_amount_due' => 490]);
        $this->preview($event, $category)->assertSessionHasErrors('refund');
        $row['order']->update(['wallet_reserved' => 0, 'wallet_debited' => false, 'payfast_amount_due' => 500]);
        DB::table('transactions_pf')->where('id', $receiptId)->delete();
        $this->preview($event, $category)->assertSessionHasErrors('refund'); // Legacy fallback would incorrectly quote 125 per item.
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertFalse($category->fresh()->isCancelled());
        $this->assertSame('active', $sibling->fresh()->status);
    }
}
