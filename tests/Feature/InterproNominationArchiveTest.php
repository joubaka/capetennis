<?php

namespace Tests\Feature;

use App\Domain\Entries\Services\EntryService;
use App\Domain\Payments\Services\RegistrationPaymentService;
use App\Models\{CategoryEvent, CategoryEventRegistration, Event, EventNomination, EventType, InterprovincialTrialInvitation, InterprovincialTrialInvitationBatch, Player, Registration, RegistrationOrder, RegistrationOrderItems, Transaction, User};
use App\Services\InterprovincialTrials\{InvitationService, NominationArchiveService};
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\{DB, Mail};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InterproNominationArchiveTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        $this->assertTrue((DB::connection()->getDriverName() === 'sqlite' && DB::connection()->getDatabaseName() === ':memory:')
            || (DB::connection()->getDriverName() === 'mysql' && DB::connection()->getDatabaseName() === 'ct_testing'));
        if (DB::connection()->getDriverName() === 'sqlite') {
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
            \Illuminate\Foundation\Testing\RefreshDatabaseState::$inMemoryConnections = [];
        }
    }

    protected function tearDown(): void
    {
        $inMemory = $this->app && $this->app->bound('db') && DB::connection()->getDriverName() === 'sqlite'
            && DB::connection()->getDatabaseName() === ':memory:';
        try {
            parent::tearDown();
        } finally {
            // The next RefreshDatabase test owns a new application/connection.
            if ($inMemory) {
                \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
                \Illuminate\Foundation\Testing\RefreshDatabaseState::$inMemoryConnections = [];
            }
        }
    }

    public function test_archive_hides_only_current_nomination_and_preserves_every_financial_and_invitation_row(): void
    {
        [$actor, $payer, $event, $category, $nomination, $invitation, $entry] = $this->scenario();
        $before = $this->history();
        $this->get(route('events.show', $event))->assertOk()->assertSee('Archived Candidate');
        $preview = $this->runArchive($actor, $nomination);
        $this->assertFalse($preview['applied']);
        $this->assertNull($nomination->fresh()->archived_at);
        $this->assertArrayNotHasKey('entries', $preview['preview']);
        $this->runArchive($actor, $nomination, $preview['confirmation']);
        $this->assertSame($before, $this->history());
        $this->assertNotNull($nomination->fresh()->archived_at);
        $this->assertEquals($actor->id, $nomination->fresh()->archived_by);
        $this->assertTrue($this->runArchive($actor, $nomination, $preview['confirmation'])['already_in_state']);
        $this->get(route('events.show', $event))->assertOk()->assertDontSee('Archived Candidate')->assertSee('Current Nominee');
        $backend = $this->actingAs($actor)->get(route('backend.interprovincial-trials.invitations.index', $event))->assertOk()
            ->assertSee('Archived nominations')->assertSee('Archived Candidate');
        $this->assertSame(1, $backend->viewData('readiness')['nomination_count']);
        $this->assertSame('withdrawn', $entry->fresh()->status);
        $this->assertSame('withdrawn', $invitation->fresh()->status);
        Mail::assertNothingSent(); Mail::assertNothingQueued();
    }

    public function test_archived_nominee_cannot_use_open_or_old_invitation_or_admin_paid_registration(): void
    {
        [$actor, $payer, , $category, $nomination, $invitation] = $this->scenario();
        $preview = $this->runArchive($actor, $nomination);
        $this->runArchive($actor, $nomination, $preview['confirmation']);
        $before = $this->history();
        foreach ([fn () => app(InvitationService::class)->acceptPublishedNomination($nomination->event, $category, $nomination->fresh(), $payer),
            fn () => app(InvitationService::class)->accept($invitation, $payer)] as $attempt) {
            try { $attempt(); $this->fail('Archived registration accepted'); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(404, $e->getStatusCode()); }
        }
        try { app(EntryService::class)->addPlayerAsAdmin($category, $nomination->player_id, $actor, 'paid_privately'); $this->fail('Admin reentry accepted'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('archived', $e->getMessage()); }
        $this->assertSame($before, $this->history());
    }

    public function test_explicit_restore_changes_visibility_without_restoring_withdrawal_or_payment(): void
    {
        [$actor, , $event, , $nomination, $invitation, $entry] = $this->scenario();
        $preview = $this->runArchive($actor, $nomination);
        $this->runArchive($actor, $nomination, $preview['confirmation']);
        $before = $this->history();
        $restore = $this->runArchive($actor, $nomination, null, 'restore');
        $this->runArchive($actor, $nomination, $restore['confirmation'], 'restore');
        $this->assertNull($nomination->fresh()->archived_at);
        $this->assertSame($before, $this->history());
        $this->get(route('events.show', $event))->assertOk()->assertSee('Archived Candidate');
        $this->assertSame('withdrawn', $entry->fresh()->status);
        $this->assertSame('withdrawn', $invitation->fresh()->status);
    }

    public function test_role_event_tuple_and_stale_confirmation_are_rejected(): void
    {
        [$actor, $payer, $event, $category, $nomination] = $this->scenario();
        try { $this->runArchive($payer, $nomination); $this->fail('Role accepted'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        try { app(NominationArchiveService::class)->run($actor, $event->id + 1, $category->id, $nomination->id, 'archive', 'Remove withdrawn nominee'); $this->fail('Foreign event accepted'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(404, $e->getStatusCode()); }
        $preview = $this->runArchive($actor, $nomination);
        $nomination->player->update(['name' => 'Changed']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->runArchive($actor, $nomination, $preview['confirmation']);
    }

    public function test_active_participation_pending_proof_and_archived_hard_delete_are_blocked(): void
    {
        [$actor, , $event, $category, $nomination, $invitation, $entry] = $this->scenario(false);
        try { $this->runArchive($actor, $nomination); $this->fail('Active participation archived'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertNull($nomination->fresh()->archived_at); }
        app(EntryService::class)->withdrawEntryAsAdmin($entry, $actor);
        app(InvitationService::class)->handlePaidWithdrawal($entry->registration_id, $actor);
        $preview = $this->runArchive($actor, $nomination);
        $this->runArchive($actor, $nomination, $preview['confirmation']);
        $this->actingAs($actor)->delete(route('backend.interprovincial-trials.nominations.destroy', [$event, $category, $nomination]))->assertStatus(422);
        $this->assertNotNull($nomination->fresh()->archived_at);
        $restore = $this->runArchive($actor, $nomination, null, 'restore');
        $this->runArchive($actor, $nomination, $restore['confirmation'], 'restore');
        \App\Models\TrialPaymentProof::create(['order_id' => $invitation->order_id, 'event_id' => $event->id, 'payer_id' => $entry->user_id,
            'path' => 'fictional.pdf', 'mime_type' => 'application/pdf', 'size' => 100, 'status' => 'pending']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->runArchive($actor, $nomination);
    }

    public function test_foreign_invitation_checkout_and_unresolved_event_order_prevent_archive(): void
    {
        [$actor, , , $category, $nomination, $invitation] = $this->scenario();
        $sourceOrder = $invitation->order_id;
        $invitation->update(['order_id' => RegistrationOrder::whereKeyNot($sourceOrder)->sole()->id]);
        try { $this->runArchive($actor, $nomination); $this->fail('Foreign invitation order accepted'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertNull($nomination->fresh()->archived_at); }
        $invitation->update(['order_id' => $sourceOrder]);
        $pending = RegistrationOrder::create(['user_id' => $actor->id, 'status' => 'pending', 'total_fee' => 450, 'pay_status' => false]);
        RegistrationOrderItems::forceCreate(['order_id' => $pending->id, 'category_event_id' => $category->id, 'player_id' => $nomination->player_id,
            'registration_id' => Registration::create()->id, 'user_id' => $actor->id, 'item_price' => 450]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->runArchive($actor, $nomination);
    }

    public function test_archived_nomination_blocks_withdrawal_restoration_without_altering_history(): void
    {
        [$actor, , , , $nomination, , $entry] = $this->scenario();
        $preview = $this->runArchive($actor, $nomination);
        $this->runArchive($actor, $nomination, $preview['confirmation']);
        $before = $this->history();
        try {
            DB::transaction(function () use ($entry, $actor) {
                $entry->update(['status' => 'active']);
                app(InvitationService::class)->restorePaidWithdrawal($entry->fresh(), $actor);
            });
            $this->fail('Archived withdrawal restored');
        } catch (\Illuminate\Validation\ValidationException $e) { $this->assertSame($before, $this->history()); }
    }

    public function test_old_invitation_actions_and_current_reminder_audience_exclude_archived_nominee(): void
    {
        [$actor, $payer, $event, , $nomination, $invitation] = $this->scenario();
        $nomination->player->update(['email' => 'archived@example.test']);
        $preview = $this->runArchive($actor, $nomination);
        $this->runArchive($actor, $nomination, $preview['confirmation']);
        $this->withoutMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class, \App\Http\Middleware\EnsurePlayerProfileUpdated::class]);
        $this->actingAs($payer)->get(\Illuminate\Support\Facades\URL::temporarySignedRoute('interprovincial-trials.invitations.show', now()->addHour(), $invitation))->assertNotFound();
        try { app(InvitationService::class)->decline($invitation, $payer); $this->fail('Archived invitation decline accepted'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(404, $e->getStatusCode()); }
        $mailPreview = app(\App\Services\InterprovincialTrials\TrialCommunicationService::class)->preview($event, $actor,
            ['audience' => 'nominations', 'filter' => 'all'], 'Nomination reminder', 'Hello player');
        $this->assertStringNotContainsString('archived@example.test', json_encode($mailPreview->recipients));
        $this->assertStringNotContainsString('Archived Candidate', json_encode($mailPreview->recipients));
        Mail::assertNothingSent(); Mail::assertNothingQueued();
    }

    private function runArchive($actor, $nomination, $confirmation = null, $action = 'archive'): array
    {
        return app(NominationArchiveService::class)->run($actor, $nomination->event_id, $nomination->category_event_id,
            $nomination->id, $action, 'Remove withdrawn nominee', $confirmation);
    }

    private function history(): array
    {
        return collect(['registrations', 'player_registrations', 'category_event_registrations', 'registration_orders', 'registration_order_items',
            'transactions_pf', 'wallet_transactions', 'interprovincial_trial_invitations', 'user_players'])
            ->mapWithKeys(fn ($table) => [$table => DB::table($table)->orderBy('id')->get()->toJson()])->all();
    }

    private function scenario(bool $withdraw = true): array
    {
        Mail::fake(); config(['services.payment_failure_alert.enabled' => false]);
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $actor = User::factory()->create()->assignRole('super-user'); $payer = User::factory()->create(); $payer->wallet()->create();
        $type = EventType::forceCreate(['name' => 'Archive Interpro', 'type' => EventType::INDIVIDUAL, 'code' => EventType::INTERPROVINCIAL_TRIALS_CODE]);
        $event = Event::factory()->create(['eventType' => $type->id, 'entryFee' => 450, 'published' => true, 'signUp' => true,
            'status' => 'active', 'start_date' => now()->addDays(20)->toDateString(), 'deadline' => 2]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id, 'nominations_published' => true, 'entry_fee' => 450]);
        $player = Player::factory()->create(['name' => 'Archived', 'surname' => 'Candidate']);
        $nomination = EventNomination::create(['event_id' => $event->id, 'category_event_id' => $category->id, 'player_id' => $player->id]);
        EventNomination::create(['event_id' => $event->id, 'category_event_id' => $category->id,
            'player_id' => Player::factory()->create(['name' => 'Current', 'surname' => 'Nominee'])->id]);
        $batch = InterprovincialTrialInvitationBatch::create(['event_id' => $event->id, 'status' => 'draft', 'snapshot_hash' => str_repeat('a', 64), 'created_by_user_id' => $actor->id]);
        $invitation = InterprovincialTrialInvitation::create(['batch_id' => $batch->id, 'event_id' => $event->id, 'category_event_id' => $category->id,
            'nomination_id' => $nomination->id, 'player_id' => $player->id, 'status' => 'sent']);
        $order = app(InvitationService::class)->accept($invitation, $payer);
        $order = app(RegistrationPaymentService::class)->preparePayfastHandoff($order, $payer, 0, 450);
        $order = app(RegistrationPaymentService::class)->finalizePayfastPayment($order, 450, ['pf_payment_id' => 'PF-ARCHIVE', 'payment_method' => 'payfast']);
        $entry = CategoryEventRegistration::where('registration_id', $order->items()->sole()->registration_id)->sole();
        Transaction::forceCreate(['pf_payment_id' => 'PF-ARCHIVE', 'custom_int5' => $order->id, 'custom_int4' => $payer->id,
            'custom_int1' => $category->id, 'custom_int2' => $player->id, 'custom_int3' => $event->id, 'amount_gross' => 450, 'amount_fee' => 19.10, 'amount_net' => 430.90]);
        if ($withdraw) {
            app(EntryService::class)->withdrawEntryAsAdmin($entry, $actor);
            app(InvitationService::class)->handlePaidWithdrawal($entry->registration_id, $actor);
        }
        // A distinct Cavaliers-like event retains the same player's independent paid registration.
        $otherCategory = CategoryEvent::factory()->create(['event_id' => Event::factory()->create()->id]);
        $otherRegistration = Registration::create(); $otherRegistration->players()->attach($player->id);
        $otherEntry = CategoryEventRegistration::create(['category_event_id' => $otherCategory->id, 'registration_id' => $otherRegistration->id,
            'user_id' => $payer->id, 'status' => 'active', 'payment_status_id' => 1, 'payment_method' => 'payfast', 'pf_transaction_id' => 'PF-CAVALIERS']);
        $otherOrder = RegistrationOrder::create(['user_id' => $payer->id, 'total_fee' => 450, 'pay_status' => 1, 'payfast_paid' => true,
            'payfast_amount_due' => 450, 'status' => 'completed', 'payment_method' => 'payfast', 'payfast_pf_payment_id' => 'PF-CAVALIERS']);
        RegistrationOrderItems::forceCreate(['order_id' => $otherOrder->id, 'registration_id' => $otherRegistration->id,
            'category_event_id' => $otherCategory->id, 'player_id' => $player->id, 'user_id' => $payer->id, 'item_price' => 450]);
        Transaction::forceCreate(['pf_payment_id' => 'PF-CAVALIERS', 'custom_int5' => $otherOrder->id, 'custom_int4' => $payer->id,
            'amount_gross' => 450, 'amount_fee' => 19.10, 'amount_net' => 430.90]);
        return [$actor, $payer, $event, $category, $nomination, $invitation->fresh(), $entry->fresh()];
    }
}
