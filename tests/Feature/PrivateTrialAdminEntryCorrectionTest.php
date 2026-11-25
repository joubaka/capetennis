<?php

namespace Tests\Feature;

use App\Domain\Entries\Services\EntryService;
use App\Domain\Finance\Services\FinancialLedgerService;
use App\Domain\Payments\Services\RegistrationPaymentService;
use App\Models\{CategoryEvent, CategoryEventRegistration, Event, EventNomination, EventType, InterprovincialTrialInvitation, Player, RegistrationOrder, Transaction, User};
use App\Services\InterprovincialTrials\{InvitationService, NominationAdministrationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PrivateTrialAdminEntryCorrectionTest extends TestCase
{
    use RefreshDatabase;

    protected function beforeRefreshingDatabase(): void
    {
        $this->assertTrue((DB::getDriverName() === 'sqlite' && DB::connection()->getDatabaseName() === ':memory:')
            || (DB::getDriverName() === 'mysql' && DB::connection()->getDatabaseName() === 'ct_testing'));
    }

    public function test_exact_mistaken_entry_can_be_corrected_and_player_can_register_pay_and_restart(): void
    {
        $this->exerciseCorrection(false);
    }

    public function test_legacy_receipt_correction_preserves_original_history_and_allows_normal_checkout(): void
    {
        if (DB::getDriverName() !== 'sqlite' || DB::connection()->getDatabaseName() !== ':memory:') $this->markTestSkipped('Legacy DDL requires isolated in-memory SQLite.');
        Schema::table('transactions_pf', fn ($table) => $table->dropColumn('registration_id'));
        $this->exerciseCorrection(true);
    }

    private function exerciseCorrection(bool $legacy): void
    {
        [$actor, $event, $category, $nomination, $entry, $receipt, $ids] = $this->scenario();
        $service = app(EntryService::class);
        $reason = 'Administrator entry added in error; no private funds collected; player must register normally.';
        $originalEntry = $entry->getAttributes();
        $originalReceipt = $receipt->getAttributes();
        $oldMarker = $nomination->actionableInvitation()->first();
        $oldMarkerAttributes = $oldMarker->getAttributes();
        $ledger = app(FinancialLedgerService::class);
        $this->assertEquals(-15, $ledger->buildForEvent($event)['totals']['totalCapeTennisFees']);
        $before = $this->snapshot();
        $preview = $service->correctPrivateTrialAdminEntry($actor, $ids, $reason, true);
        $this->assertTrue($preview['preview']);
        $this->assertSame($before, $this->snapshot());
        $this->assertEquals(15, $preview['cape_fee_liability_to_archive']);
        $result = $service->correctPrivateTrialAdminEntry($actor, $ids, $reason, true, $preview['confirmation']);
        $this->assertFalse($result['preview']);
        $retired = CategoryEventRegistration::withTrashed()->findOrFail($entry->id);
        $this->assertTrue($retired->trashed());
        $this->assertSame('cancelled_admin_error', $retired->status);
        $this->assertSame('paid', $retired->admin_payment_status);
        $this->assertEquals(1, $retired->payment_status_id);
        $this->assertNull($retired->withdrawn_at);
        $this->assertNull($retired->refunded_at);
        $this->assertSame(array_diff_key($originalEntry, array_flip(['status', 'deleted_at', 'updated_at'])), array_diff_key($retired->getAttributes(), array_flip(['status', 'deleted_at', 'updated_at'])));
        $this->assertNotNull($receipt->fresh()->archived_at);
        $this->assertSame(array_diff_key($originalReceipt, ['archived_at' => true]), array_diff_key($receipt->fresh()->getAttributes(), ['archived_at' => true]));
        $this->assertSame($oldMarkerAttributes, $oldMarker->fresh()->getAttributes());
        $this->assertEquals(0, $ledger->buildForEvent($event)['totals']['totalCapeTennisFees']);
        $marker = $nomination->actionableInvitation()->first();
        $this->assertSame('open_registration', $marker->status);
        $this->assertNull($marker->registration_id);
        $this->assertTrue($service->correctPrivateTrialAdminEntry($actor, $ids, $reason, true, $preview['confirmation'])['already_corrected']);
        $page = $this->get(route('events.show', $event))->assertOk()->assertSee('Not registered');
        $document = new \DOMDocument();
        @$document->loadHTML($page->getContent());
        $row = (new \DOMXPath($document))->query('//*[@id="trial-nomination-'.$nomination->id.'"]')->item(0);
        $this->assertNotNull($row);
        $this->assertStringContainsString('Register', $row->textContent);
        $this->assertStringNotContainsString('Registered', $row->textContent);
        $payer = User::factory()->create();
        try { app(InvitationService::class)->accept($oldMarker, $payer); $this->fail('Historical private marker was accepted.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(404, $e->getStatusCode()); }
        $invitations = app(InvitationService::class);
        $order = $invitations->acceptPublishedNomination($event, $category, $nomination, $payer);
        $this->assertSame($payer->id, $order->user_id);
        $this->assertFalse($order->pay_status);
        $this->assertEquals(450, $order->total_fee);
        app(RegistrationPaymentService::class)->cancelPayment($order);
        $invitations->resetCancelledPayment($order->fresh(), $payer);
        $newPayer = User::factory()->create();
        $order = $invitations->acceptPublishedNomination($event, $category, $nomination, $newPayer);
        $this->assertSame($newPayer->id, $order->user_id);
        app(RegistrationPaymentService::class)->finalizePayfastPayment($order, 450, ['pf_payment_id' => 'PF-CORRECTED', 'payment_method' => 'payfast']);
        $item = $order->items()->sole();
        $transaction = ['event_id' => $event->id, 'category_event_id' => $category->id, 'player_id' => $nomination->player_id,
            'transaction_type' => 'Registration', 'item_name' => 'Normal entry', 'amount_gross' => 450, 'amount_fee' => 10, 'amount_net' => 440,
            'cape_tennis_fee' => 15, 'is_test' => false, 'pf_payment_id' => 'PF-CORRECTED', 'custom_int3' => $event->id,
            'custom_int4' => $newPayer->id, 'custom_int5' => $order->id];
        if (!$legacy) $transaction['registration_id'] = $item->registration_id;
        Transaction::forceCreate($transaction);
        $this->assertEquals(-15, $ledger->buildForEvent($event)['totals']['totalCapeTennisFees']);
        $this->assertSame(1, CategoryEventRegistration::where('category_event_id', $category->id)->count());
        $this->assertDatabaseCount('user_players', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('interprovincial_trial_mail_dispatches', 0);
    }

    public function test_confirmation_stale_state_wrong_scope_and_settlement_evidence_are_rejected_without_writes(): void
    {
        [$actor, $event, $category, $nomination, $entry, $receipt, $ids] = $this->scenario();
        $service = app(EntryService::class);
        $reason = 'Administrator entry was mistaken and no private money was collected.';
        $this->rejects(fn () => $service->correctPrivateTrialAdminEntry($actor, $ids, $reason, false));
        $preview = $service->correctPrivateTrialAdminEntry($actor, $ids, $reason, true);
        DB::table('transactions_pf')->where('id', $receipt->id)->update(['cape_tennis_fee' => 16]);
        $before = $this->snapshot();
        $this->rejects(fn () => $service->correctPrivateTrialAdminEntry($actor, $ids, $reason, true, $preview['confirmation']));
        $this->assertSame($before, $this->snapshot());
        DB::table('transactions_pf')->where('id', $receipt->id)->update(['cape_tennis_fee' => 15, 'pf_payment_id' => 'REAL-PROVIDER']);
        $before = $this->snapshot();
        $this->rejects(fn () => $service->correctPrivateTrialAdminEntry($actor, $ids, $reason, true));
        $this->assertSame($before, $this->snapshot());
        try { $service->correctPrivateTrialAdminEntry(User::factory()->create(), $ids, $reason, true); $this->fail('Unauthorized actor accepted.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        $foreign = CategoryEvent::factory()->create();
        try { $service->correctPrivateTrialAdminEntry($actor, array_replace($ids, ['category_event_id' => $foreign->id]), $reason, true); $this->fail('Foreign category accepted.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(404, $e->getStatusCode()); }
    }

    public function test_newer_sent_marker_cannot_bypass_a_genuine_pending_or_paid_checkout(): void
    {
        [$actor, $event, $category, $nomination] = $this->scenario(false);
        $payer = User::factory()->create();
        $service = app(InvitationService::class);
        $order = $service->acceptPublishedNomination($event, $category, $nomination, $payer);
        $source = $nomination->actionableInvitation()->first();
        $laterBatch = $source->batch->replicate();
        $laterBatch->save();
        InterprovincialTrialInvitation::create(['event_id' => $event->id, 'category_event_id' => $category->id,
            'nomination_id' => $nomination->id, 'player_id' => $nomination->player_id, 'batch_id' => $laterBatch->id, 'status' => 'sent']);
        foreach (['accepted_pending_payment', 'paid_confirmed'] as $status) {
            DB::table('interprovincial_trial_invitations')->where('id', $source->id)->update(['status' => $status]);
            $before = RegistrationOrder::count();
            try { $service->acceptPublishedNomination($event, $category, $nomination, User::factory()->create()); $this->fail('Historical checkout was bypassed.'); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(404, $e->getStatusCode()); }
            $this->assertSame($before, RegistrationOrder::count());
        }
    }

    public function test_competition_dependencies_block_correction_without_writes(): void
    {
        [$actor, $event, $category, $nomination, $entry, $receipt, $ids] = $this->scenario();
        $draw = \App\Models\Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id]);
        DB::table('draw_registrations')->insert(['draw_id' => $draw->id, 'registration_id' => $entry->registration_id]);
        $before = $this->snapshot();
        $this->rejects(fn () => app(EntryService::class)->correctPrivateTrialAdminEntry($actor, $ids, 'Erroneous private registration has no collected private funds.', true));
        $this->assertSame($before, $this->snapshot());
        $this->assertDatabaseHas('draw_registrations', ['draw_id' => $draw->id, 'registration_id' => $entry->registration_id]);
    }

    public function test_ledger_failure_rolls_back_entry_retirement_and_nomination_lifecycle_atomically(): void
    {
        [$actor, $event, $category, $nomination, $entry, $receipt, $ids] = $this->scenario();
        $reason = 'Erroneous private registration has no collected private funds.';
        $preview = app(EntryService::class)->correctPrivateTrialAdminEntry($actor, $ids, $reason, true);
        $before = $this->snapshot();
        $this->mock(FinancialLedgerService::class)->shouldReceive('archiveErroneousPrivateAdminReceipt')->once()->andThrow(new \RuntimeException('Injected archive failure'));
        try { app(EntryService::class)->correctPrivateTrialAdminEntry($actor, $ids, $reason, true, $preview['confirmation']); $this->fail('Injected failure was hidden.'); }
        catch (\RuntimeException $exception) { $this->assertSame('Injected archive failure', $exception->getMessage()); }
        $this->assertSame($before, $this->snapshot());
        $this->assertSame('active', $entry->fresh()->status);
        $this->assertNull($receipt->fresh()->archived_at);
    }
    private function scenario(bool $private = true): array
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('super-user');
        $this->actingAs($actor);
        $type = EventType::forceCreate(['name' => 'Correction trials', 'type' => EventType::INDIVIDUAL, 'code' => EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $event = Event::factory()->create(['eventType' => $type->id, 'entryFee' => 450, 'cape_tennis_fee' => 15, 'published' => true, 'signUp' => true,
            'status' => 'scheduled', 'start_date' => now()->addMonth(), 'deadline' => 1]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id, 'nominations_published' => true]);
        $player = Player::factory()->create();
        $nomination = EventNomination::create(['event_id' => $event->id, 'category_event_id' => $category->id, 'player_id' => $player->id]);
        if (!$private) return [$actor, $event, $category, $nomination];
        app(NominationAdministrationService::class)->act($event, $category, $nomination, $actor, 'paid_privately');
        $entry = CategoryEventRegistration::where('category_event_id', $category->id)->sole();
        $receipt = Transaction::where('event_id', $event->id)->sole();
        $ids = ['event_id' => $event->id, 'category_event_id' => $category->id, 'player_id' => $player->id,
            'nomination_id' => $nomination->id, 'entry_id' => $entry->id, 'receipt_id' => $receipt->id];
        return [$actor, $event, $category, $nomination, $entry, $receipt, $ids];
    }

    private function snapshot(): array
    {
        $result = [];
        foreach (['category_event_registrations', 'transactions_pf', 'registration_orders', 'registration_order_items', 'player_registrations', 'interprovincial_trial_invitations', 'interprovincial_trial_invitation_batches', 'wallet_transactions'] as $table) {
            $result[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }
        return $result;
    }

    private function rejects(callable $operation): void
    {
        try { $operation(); $this->fail('Unsafe correction accepted.'); }
        catch (ValidationException $exception) { $this->assertNotEmpty($exception->errors()); }
    }
}