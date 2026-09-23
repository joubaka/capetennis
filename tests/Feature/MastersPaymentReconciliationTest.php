<?php

namespace Tests\Feature;

use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\MastersInvitation;
use App\Models\MastersInvitationBatch;
use App\Models\Player;
use App\Models\Registration;
use App\Models\RegistrationOrder;
use App\Models\RegistrationOrderItems;
use App\Models\User;
use App\Services\Masters\MastersInvitationService;
use App\Domain\Entries\Events\EntryWithdrawn;
use App\Domain\Entries\Services\EntryService;
use App\Listeners\SyncMastersInvitationWithdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MastersPaymentReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_starts_masters_payment_without_a_separate_acceptance(): void
    {
        [$invitation] = $this->invitationScenario();
        $otherUser = User::factory()->create();

        $this->get(route('masters.invitations.show', $invitation))
            ->assertRedirect();
        $this->actingAs($otherUser)
            ->get(route('masters.invitations.show', $invitation))
            ->assertOk()
            ->assertSee('Register and pay with PayFast')
            ->assertDontSee('accept the invitation', false);
        $response = $this->actingAs($otherUser)
            ->post(route('masters.invitations.accept', $invitation))
            ->assertRedirect();

        $this->assertStringContainsString('/registration/checkout/', $response->headers->get('Location'));
        $this->assertSame(MastersInvitation::ACCEPTED_PENDING_PAYMENT, $invitation->fresh()->status);
        $this->assertDatabaseHas('registration_orders', [
            'id' => $invitation->fresh()->order_id,
            'user_id' => $otherUser->id,
        ]);
    }

    public function test_any_authenticated_account_can_decline_an_invitation(): void
    {
        Queue::fake();
        [$invitation] = $this->invitationScenario();
        $otherUser = User::factory()->create();

        $this->actingAs($otherUser)
            ->post(route('masters.invitations.decline', $invitation), ['reason' => 'Unavailable'])
            ->assertRedirect();

        $this->assertSame(MastersInvitation::DECLINED, $invitation->fresh()->status);
        $this->assertSame($otherUser->id, $invitation->fresh()->declined_by_user_id);
    }

    public function test_accepting_an_invitation_does_not_create_an_active_entry_before_payment(): void
    {
        [$invitation, $user] = $this->invitationScenario();

        $order = app(MastersInvitationService::class)->accept($invitation, $user);
        $invitation->refresh();

        $this->assertSame(MastersInvitation::ACCEPTED_PENDING_PAYMENT, $invitation->status);
        $this->assertSame($order->id, $invitation->order_id);
        $this->assertDatabaseHas('registration_order_items', [
            'order_id' => $order->id,
            'registration_id' => $invitation->registration_id,
            'player_id' => $invitation->player_id,
            'category_event_id' => $invitation->category_event_id,
        ]);
        $this->assertDatabaseMissing('category_event_registrations', [
            'registration_id' => $invitation->registration_id,
            'category_event_id' => $invitation->category_event_id,
            'deleted_at' => null,
        ]);
    }

    public function test_stale_unpaid_checkout_returns_to_register_and_hides_legacy_draft_entry(): void
    {
        [$invitation, $user] = $this->invitationScenario();
        $service = app(MastersInvitationService::class);
        $order = $service->accept($invitation, $user);
        $invitation->refresh()->update(['accepted_at' => now()->subMinutes(61)]);

        $entry = CategoryEventRegistration::create([
            'registration_id' => $invitation->registration_id,
            'category_event_id' => $invitation->category_event_id,
            'user_id' => $user->id,
            'status' => 'active',
            'payment_status_id' => 0,
        ]);

        $rows = $service->reconcilePaymentStates($invitation->event_id, 60, true);

        $this->assertSame('return_to_register', $rows[0]['action']);
        $this->assertDatabaseHas('masters_invitations', [
            'id' => $invitation->id,
            'status' => MastersInvitation::INVITED,
            'order_id' => null,
            'registration_id' => null,
            'accepted_at' => null,
        ]);
        $this->assertSoftDeleted('category_event_registrations', ['id' => $entry->id]);
        $this->assertSame(0.0, (float) $order->fresh()->payfast_amount_due);
        $this->assertFalse((bool) $order->fresh()->pay_status);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->actingAs($user)
            ->get(route('registration.checkout', $order))
            ->assertRedirect()
            ->assertSessionHasErrors();
    }

    public function test_existing_paid_entry_is_linked_and_unpaid_duplicate_is_retired(): void
    {
        [$invitation, $user] = $this->invitationScenario();

        [$paidOrder, $paidRegistration] = $this->orderFor(
            $invitation,
            $user,
            paid: true,
        );
        [$duplicateOrder, $duplicateRegistration] = $this->orderFor(
            $invitation,
            $user,
            paid: false,
        );

        $rows = app(MastersInvitationService::class)
            ->reconcilePaymentStates($invitation->event_id, 60, true);

        $this->assertSame('link_paid_registration', $rows[0]['action']);
        $this->assertDatabaseHas('masters_invitations', [
            'id' => $invitation->id,
            'status' => MastersInvitation::PAID_CONFIRMED,
            'order_id' => $paidOrder->id,
            'registration_id' => $paidRegistration->id,
        ]);
        $this->assertDatabaseHas('category_event_registrations', [
            'registration_id' => $paidRegistration->id,
            'category_event_id' => $invitation->category_event_id,
            'payment_status_id' => 1,
            'deleted_at' => null,
        ]);
        $this->assertSoftDeleted('category_event_registrations', [
            'registration_id' => $duplicateRegistration->id,
            'category_event_id' => $invitation->category_event_id,
        ]);
        $this->assertSame(0.0, (float) $duplicateOrder->fresh()->payfast_amount_due);
    }

    public function test_canonical_withdrawal_event_synchronizes_paid_masters_invitation(): void
    {
        Queue::fake();
        [$invitation, $user] = $this->invitationScenario();
        [$order, $registration] = $this->orderFor($invitation, $user, paid: true);
        $entry = CategoryEventRegistration::query()
            ->where('registration_id', $registration->id)
            ->where('category_event_id', $invitation->category_event_id)
            ->firstOrFail();
        $entry->update(['status' => 'withdrawn', 'withdrawn_at' => now()->subHour()]);
        $invitation->update([
            'status' => MastersInvitation::PAID_CONFIRMED,
            'registration_id' => $registration->id,
            'order_id' => $order->id,
        ]);

        app(SyncMastersInvitationWithdrawal::class)->handle(new EntryWithdrawn($entry->fresh(), $user, 'admin'));

        $invitation->refresh();
        $this->assertSame(MastersInvitation::WITHDRAWN, $invitation->status);
        $this->assertTrue($invitation->withdrawn_at->equalTo($entry->fresh()->withdrawn_at));
    }

    public function test_masters_invitation_cannot_be_withdrawn_before_entry_transition(): void
    {
        [$invitation, $user] = $this->invitationScenario();
        [$order, $registration] = $this->orderFor($invitation, $user, paid: true);
        $invitation->update([
            'status' => MastersInvitation::PAID_CONFIRMED,
            'registration_id' => $registration->id,
            'order_id' => $order->id,
        ]);

        $this->expectException(\RuntimeException::class);
        app(MastersInvitationService::class)->handlePaidWithdrawal($registration->id, $user, false);
    }

    public function test_admin_can_mark_a_pending_masters_checkout_as_paid_privately_once(): void
    {
        [$invitation, $payer] = $this->invitationScenario();
        $admin = User::factory()->create();
        $order = app(MastersInvitationService::class)->accept($invitation, $payer);
        $order->update(['wallet_reserved' => 75]);
        $pendingRegistrationId = $invitation->fresh()->registration_id;
        $walletTransactionCount = DB::table('wallet_transactions')->count();

        $paid = app(MastersInvitationService::class)->markPaidByAdmin($invitation, $admin);

        $this->assertSame(MastersInvitation::PAID_CONFIRMED, $paid->status);
        $this->assertNull($paid->order_id);
        $this->assertNotSame($pendingRegistrationId, $paid->registration_id);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0.0, (float) $order->fresh()->payfast_amount_due);
        $this->assertSame(0.0, (float) $order->fresh()->wallet_reserved);
        $this->assertFalse((bool) $order->fresh()->wallet_debited);
        $this->assertSame($walletTransactionCount, DB::table('wallet_transactions')->count());
        $this->assertDatabaseHas('category_event_registrations', [
            'registration_id' => $paid->registration_id,
            'category_event_id' => $invitation->category_event_id,
            'status' => 'active',
            'payment_status_id' => 1,
            'admin_payment_status' => 'paid',
        ]);
        $this->assertSame(1, CategoryEventRegistration::query()
            ->where('category_event_id', $invitation->category_event_id)
            ->where('status', 'active')
            ->where('payment_status_id', 1)
            ->count());
        $this->assertSame(1, DB::table('transactions_pf')
            ->where('category_event_id', $invitation->category_event_id)
            ->where('player_id', $invitation->player_id)
            ->where('amount_gross', 0)
            ->count());
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'masters',
            'description' => 'Masters invitation marked paid by admin (private collection not reconciled)',
            'subject_id' => $invitation->id,
            'causer_id' => $admin->id,
        ]);

        $registrationId = $paid->registration_id;
        $transactionCount = DB::table('transactions_pf')
            ->where('category_event_id', $invitation->category_event_id)
            ->where('player_id', $invitation->player_id)
            ->count();

        $repeated = app(MastersInvitationService::class)->markPaidByAdmin($paid, $admin);

        $this->assertSame($registrationId, $repeated->registration_id);
        $this->assertSame($transactionCount, DB::table('transactions_pf')
            ->where('category_event_id', $invitation->category_event_id)
            ->where('player_id', $invitation->player_id)
            ->count());
    }

    public function test_manual_payment_links_one_existing_unpaid_admin_entry_without_creating_duplicates(): void
    {
        [$invitation, $payer] = $this->invitationScenario();
        $admin = User::factory()->create();
        $existingEntry = app(EntryService::class)->addPlayerAsAdmin(
            $invitation->categoryEvent,
            $invitation->player_id,
            $admin,
            'unpaid',
        );
        $order = app(MastersInvitationService::class)->accept($invitation, $payer);
        $order->update(['wallet_reserved' => 75]);
        $pendingRegistrationId = $invitation->fresh()->registration_id;
        $transactionCount = DB::table('transactions_pf')->count();

        $paid = app(MastersInvitationService::class)->markPaidByAdmin($invitation, $admin);

        $this->assertSame(MastersInvitation::PAID_CONFIRMED, $paid->status);
        $this->assertSame($existingEntry->registration_id, $paid->registration_id);
        $this->assertNull($paid->order_id);
        $this->assertSame('paid', $existingEntry->fresh()->admin_payment_status);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0.0, (float) $order->fresh()->wallet_reserved);
        $this->assertSame(0.0, (float) $order->fresh()->payfast_amount_due);
        $this->assertSame($transactionCount, DB::table('transactions_pf')->count());
        $this->assertSame(1, CategoryEventRegistration::query()
            ->where('category_event_id', $invitation->category_event_id)
            ->where('status', 'active')
            ->where('payment_status_id', 1)
            ->count());
        $this->assertDatabaseMissing('category_event_registrations', [
            'registration_id' => $pendingRegistrationId,
            'category_event_id' => $invitation->category_event_id,
            'deleted_at' => null,
        ]);

        $repeated = app(MastersInvitationService::class)->markPaidByAdmin($paid, $admin);
        $this->assertSame($existingEntry->registration_id, $repeated->registration_id);
        $this->assertSame($transactionCount, DB::table('transactions_pf')->count());
    }

    public function test_manual_payment_links_one_existing_paid_admin_entry_idempotently(): void
    {
        [$invitation, $payer] = $this->invitationScenario();
        $admin = User::factory()->create();
        $existingEntry = app(EntryService::class)->addPlayerAsAdmin(
            $invitation->categoryEvent,
            $invitation->player_id,
            $admin,
            'paid_privately',
        );
        $order = app(MastersInvitationService::class)->accept($invitation, $payer);
        $transactionCount = DB::table('transactions_pf')->count();

        $paid = app(MastersInvitationService::class)->markPaidByAdmin($invitation, $admin);

        $this->assertSame($existingEntry->registration_id, $paid->registration_id);
        $this->assertSame('paid', $existingEntry->fresh()->admin_payment_status);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame($transactionCount, DB::table('transactions_pf')->count());
        $this->assertSame($existingEntry->registration_id,
            app(MastersInvitationService::class)->markPaidByAdmin($paid, $admin)->registration_id);
        $this->assertSame($transactionCount, DB::table('transactions_pf')->count());
    }

    public function test_manual_payment_rejects_existing_gateway_paid_entry_without_mutation(): void
    {
        [$invitation, $payer] = $this->invitationScenario();
        $admin = User::factory()->create();
        $pendingOrder = app(MastersInvitationService::class)->accept($invitation, $payer);
        [$paidOrder] = $this->orderFor($invitation, $payer, paid: true);
        $before = $invitation->fresh()->only(['status', 'order_id', 'registration_id', 'paid_at']);

        try {
            app(MastersInvitationService::class)->markPaidByAdmin($invitation, $admin);
            $this->fail('Expected the gateway-paid entry to block a private payment.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('gateway-paid', $exception->getMessage());
        }

        $this->assertSame($before, $invitation->fresh()->only(['status', 'order_id', 'registration_id', 'paid_at']));
        $this->assertSame('pending', $pendingOrder->fresh()->status);
        $this->assertSame('completed', $paidOrder->fresh()->status);
    }

    public function test_manual_payment_rejects_multiple_active_paid_entries_without_mutation(): void
    {
        [$invitation, $payer] = $this->invitationScenario();
        $admin = User::factory()->create();
        app(EntryService::class)->addPlayerAsAdmin($invitation->categoryEvent, $invitation->player_id, $admin, 'paid_privately');
        $secondRegistration = Registration::create([]);
        $secondRegistration->players()->sync([$invitation->player_id]);
        CategoryEventRegistration::create([
            'registration_id' => $secondRegistration->id,
            'category_event_id' => $invitation->category_event_id,
            'user_id' => $admin->id,
            'status' => 'active',
            'payment_status_id' => 1,
            'admin_payment_status' => 'paid',
        ]);
        $pendingOrder = app(MastersInvitationService::class)->accept($invitation, $payer);
        $before = $invitation->fresh()->only(['status', 'order_id', 'registration_id', 'paid_at']);

        try {
            app(MastersInvitationService::class)->markPaidByAdmin($invitation, $admin);
            $this->fail('Expected ambiguous entries to block a private payment.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Multiple active paid entries', $exception->getMessage());
        }

        $this->assertSame($before, $invitation->fresh()->only(['status', 'order_id', 'registration_id', 'paid_at']));
        $this->assertSame('pending', $pendingOrder->fresh()->status);
    }

    public function test_manual_payment_rejects_ambiguous_admin_transaction_history_without_mutation(): void
    {
        [$invitation, $payer] = $this->invitationScenario();
        $admin = User::factory()->create();
        app(EntryService::class)->addPlayerAsAdmin($invitation->categoryEvent, $invitation->player_id, $admin, 'paid_privately');
        $transaction = (array) DB::table('transactions_pf')
            ->where('category_event_id', $invitation->category_event_id)
            ->where('player_id', $invitation->player_id)
            ->first();
        unset($transaction['id']);
        DB::table('transactions_pf')->insert($transaction);
        $pendingOrder = app(MastersInvitationService::class)->accept($invitation, $payer);
        $before = $invitation->fresh()->only(['status', 'order_id', 'registration_id', 'paid_at']);

        $this->expectException(ValidationException::class);
        try {
            app(MastersInvitationService::class)->markPaidByAdmin($invitation, $admin);
        } finally {
            $this->assertSame($before, $invitation->fresh()->only(['status', 'order_id', 'registration_id', 'paid_at']));
            $this->assertSame('pending', $pendingOrder->fresh()->status);
        }
    }

    public function test_manual_payment_rejects_paid_invitation_linked_to_non_admin_entry_without_mutation(): void
    {
        [$invitation, $payer] = $this->invitationScenario();
        $admin = User::factory()->create();
        [$order, $registration] = $this->orderFor($invitation, $payer, paid: true);
        $invitation->update([
            'status' => MastersInvitation::PAID_CONFIRMED,
            'registration_id' => $registration->id,
            'order_id' => $order->id,
            'paid_at' => now(),
        ]);
        $before = $invitation->fresh()->only(['status', 'order_id', 'registration_id', 'paid_at']);

        try {
            app(MastersInvitationService::class)->markPaidByAdmin($invitation, $admin);
            $this->fail('Expected paid invitation drift to reject private payment.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('another payment path', $exception->getMessage());
        }

        $this->assertEquals($before, $invitation->fresh()->only(['status', 'order_id', 'registration_id', 'paid_at']));
    }

    public function test_mark_paid_endpoint_is_visible_to_super_user_and_rejects_an_unrelated_event_admin(): void
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        [$invitation, $payer] = $this->invitationScenario();
        $mastersTypeId = DB::table('eventtypes')->where('code', 'masters')->value('id');
        $invitation->batch->event->update(['eventType' => $mastersTypeId]);
        $invitation->update(['ranking_list_id' => 1]);
        app(MastersInvitationService::class)->accept($invitation, $payer);

        $otherEvent = Event::factory()->create(['eventType' => $mastersTypeId]);
        $otherAdmin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert([
            'event_id' => $otherEvent->id,
            'user_id' => $otherAdmin->id,
        ]);

        $this->actingAs($otherAdmin)
            ->postJson(route('backend.masters.invitation.mark-paid', $invitation))
            ->assertForbidden();

        $eventAdmin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert([
            'event_id' => $invitation->event_id,
            'user_id' => $eventAdmin->id,
        ]);
        $this->actingAs($eventAdmin)
            ->get(route('backend.masters.show', $invitation->batch))
            ->assertOk()
            ->assertSee('Mark paid privately (not reconciled)');
        $this->actingAs($eventAdmin)
            ->postJson(route('backend.masters.invitation.mark-paid', $invitation))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $superUser = User::factory()->create()->assignRole('super-user');
        $this->actingAs($superUser)
            ->postJson(route('backend.masters.invitation.mark-paid', $invitation))
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    private function invitationScenario(): array
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();
        $categoryEvent = CategoryEvent::factory()->create([
            'event_id' => $event->id,
            'entry_fee' => 285,
        ]);
        $player = Player::factory()->create(['userId' => $user->id]);
        $batch = MastersInvitationBatch::create([
            'event_id' => $event->id,
            'series_id' => 1,
            'ranking_run_id' => 'test-run',
            'top_x' => 8,
            'status' => 'sent',
            'registration_open' => true,
            'response_deadline' => now()->addDay(),
            'payment_deadline' => now()->addWeek(),
            'replacement_payment_deadline' => now()->addWeek(),
        ]);
        $invitation = MastersInvitation::create([
            'batch_id' => $batch->id,
            'event_id' => $event->id,
            'category_event_id' => $categoryEvent->id,
            'player_id' => $player->id,
            'ranking_position' => 1,
            'queue_position' => 1,
            'status' => MastersInvitation::INVITED,
            'invited_at' => now(),
        ]);

        return [$invitation, $user];
    }

    private function orderFor(
        MastersInvitation $invitation,
        User $user,
        bool $paid,
    ): array {
        $registration = Registration::create([]);
        $registration->players()->sync([$invitation->player_id]);
        $order = RegistrationOrder::create([
            'user_id' => $user->id,
            'payfast_amount_due' => 285,
            'total_fee' => 285,
            'wallet_reserved' => 0,
            'wallet_debited' => false,
            'payfast_paid' => $paid,
            'pay_status' => $paid,
            'payment_method' => 'payfast',
            'status' => $paid ? 'completed' : 'pending',
        ]);
        $item = new RegistrationOrderItems();
        $item->order_id = $order->id;
        $item->category_event_id = $invitation->category_event_id;
        $item->registration_id = $registration->id;
        $item->player_id = $invitation->player_id;
        $item->user_id = $user->id;
        $item->item_price = 285;
        $item->save();
        CategoryEventRegistration::create([
            'registration_id' => $registration->id,
            'category_event_id' => $invitation->category_event_id,
            'user_id' => $user->id,
            'status' => 'active',
            'payment_status_id' => $paid ? 1 : 0,
            'pf_transaction_id' => $paid ? 'PF-PAID' : null,
        ]);

        return [$order, $registration];
    }
}
