<?php

namespace Tests\Feature;

use App\Models\CategoryEvent;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\EventNomination;
use App\Models\EventType;
use App\Models\Draw;
use App\Models\InterprovincialTrialInvitation;
use App\Models\InterprovincialTrialInvitationBatch;
use App\Models\Player;
use App\Models\RegistrationOrder;
use App\Models\User;
use App\Services\InterprovincialTrials\InvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InterprovincialTrialPaymentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->admin = User::factory()->create()->assignRole('super-user');
    }

    public function test_paid_confirmation_requires_the_exact_locked_invitation_tuple_and_is_idempotent(): void
    {
        foreach (['event', 'category', 'player', 'order', 'user'] as $tamper) {
            [$invitation, $order, $entry] = $this->paidFixture();
            $invitation->update([
                'status' => InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT,
                'paid_at' => null,
            ]);

            $otherEvent = Event::factory()->create();
            $otherCategory = CategoryEvent::factory()->create(['event_id' => $otherEvent->id]);
            $otherPlayer = Player::factory()->create();
            $otherUser = User::factory()->create();
            $otherOrder = RegistrationOrder::create([
                'user_id' => $otherUser->id,
                'total_fee' => 0,
                'payfast_amount_due' => 0,
                'pay_status' => true,
                'status' => 'completed',
            ]);

            match ($tamper) {
                'event' => $invitation->update(['event_id' => $otherEvent->id]),
                'category' => $invitation->update(['category_event_id' => $otherCategory->id]),
                'player' => $invitation->update(['player_id' => $otherPlayer->id]),
                'order' => DB::table('registration_order_items')
                    ->where('order_id', $order->id)
                    ->update(['order_id' => $otherOrder->id]),
                'user' => $order->update(['user_id' => $otherUser->id]),
            };

            try {
                app(InvitationService::class)->confirmPaidOrder($order->fresh());
                $this->fail("{$tamper} tampering should be rejected.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('payment', $exception->errors());
            }

            $this->assertSame(
                InterprovincialTrialInvitation::ACCEPTED_PENDING_PAYMENT,
                $invitation->fresh()->status,
            );
            $this->assertNull($invitation->fresh()->paid_at);

            $invitation->delete();
            $entry->delete();
        }

        [$invitation, $order] = $this->paidFixture();
        $paidAt = $invitation->paid_at;
        app(InvitationService::class)->confirmPaidOrder($order->fresh());
        app(InvitationService::class)->confirmPaidOrder($order->fresh());

        $this->assertSame(InterprovincialTrialInvitation::PAID_CONFIRMED, $invitation->fresh()->status);
        $this->assertTrue($paidAt->equalTo($invitation->fresh()->paid_at));
        $this->assertDatabaseCount('registration_orders', 11);
    }

    public function test_withdrawal_sync_requires_the_matching_entry_then_is_idempotent_and_preserves_payment_links(): void
    {
        [$invitation, $order, $entry] = $this->paidFixture();
        $paidAt = $invitation->paid_at;
        $this->assertNotSame((int) $invitation->player->userId, (int) $order->user_id);

        try {
            app(InvitationService::class)->handlePaidWithdrawal($entry->registration_id, $this->admin);
            $this->fail('An active entry must not synchronize as withdrawn.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('only be withdrawn after', $exception->getMessage());
        }

        $entry->update(['status' => 'withdrawn', 'withdrawn_at' => now(), 'withdrawn_by' => $this->admin->id]);
        app(InvitationService::class)->handlePaidWithdrawal($entry->registration_id, $this->admin);
        app(InvitationService::class)->handlePaidWithdrawal($entry->registration_id, $this->admin);

        $fresh = $invitation->fresh();
        $this->assertSame(InterprovincialTrialInvitation::WITHDRAWN, $fresh->status);
        $this->assertSame($order->id, $fresh->order_id);
        $this->assertSame($order->user_id, $fresh->order->user_id);
        $this->assertSame($entry->registration_id, $fresh->registration_id);
        $this->assertTrue($paidAt->equalTo($fresh->paid_at));
        $this->assertDatabaseCount('interprovincial_trial_invitations', 1);
        $this->assertDatabaseCount('registration_orders', 1);
        $this->assertDatabaseCount('registration_order_items', 1);
    }

    public function test_cancelling_withdrawal_is_event_scoped_and_restores_paid_invitation_once(): void
    {
        [$invitation, $order, $entry, $event] = $this->paidFixture();
        $paidAt = $invitation->paid_at;
        $entry->update(['status' => 'withdrawn', 'withdrawn_at' => now(), 'withdrawn_by' => $this->admin->id]);
        app(InvitationService::class)->handlePaidWithdrawal($entry->registration_id, $this->admin);

        $wrongEvent = Event::factory()->create();
        $this->actingAs($this->admin)
            ->delete(route('admin.registration.refund.cancel', [$wrongEvent, $entry]))
            ->assertNotFound();
        $this->assertSame('withdrawn', $entry->fresh()->status);
        $this->assertSame(InterprovincialTrialInvitation::WITHDRAWN, $invitation->fresh()->status);

        $this->actingAs($this->admin)
            ->delete(route('admin.registration.refund.cancel', [$event, $entry]))
            ->assertRedirect(route('admin.events.entries.new', $event));

        $fresh = $invitation->fresh();
        $this->assertSame('active', $entry->fresh()->status);
        $this->assertSame(InterprovincialTrialInvitation::PAID_CONFIRMED, $fresh->status);
        $this->assertSame($order->id, $fresh->order_id);
        $this->assertSame($entry->registration_id, $fresh->registration_id);
        $this->assertTrue($paidAt->equalTo($fresh->paid_at));

        $this->actingAs($this->admin)
            ->delete(route('admin.registration.refund.cancel', [$event, $entry]))
            ->assertRedirect(route('admin.events.entries.new', $event));
        $this->assertDatabaseCount('interprovincial_trial_invitations', 1);
        $this->assertDatabaseCount('registration_orders', 1);
    }

    public function test_cancelling_withdrawal_rejects_pending_refund_without_mutating_entry_invitation_or_audit(): void
    {
        [$invitation, $order, $entry, $event] = $this->paidFixture();
        $entry->update([
            'status' => 'withdrawn',
            'withdrawn_at' => now(),
            'withdrawn_by' => $this->admin->id,
            'refund_status' => CategoryEventRegistration::REFUND_PENDING,
        ]);
        app(InvitationService::class)->handlePaidWithdrawal($entry->registration_id, $this->admin);
        $entryBefore = $entry->fresh()->getRawOriginal();
        $invitationBefore = $invitation->fresh()->getRawOriginal();
        $auditCount = DB::table('activity_log')->count();
        $walletTransactionCount = DB::table('wallet_transactions')->count();

        $this->actingAs($this->admin)
            ->delete(route('admin.registration.refund.cancel', [$event, $entry]))
            ->assertSessionHasErrors('registration');

        $this->assertSame($entryBefore, $entry->fresh()->getRawOriginal());
        $this->assertSame($invitationBefore, $invitation->fresh()->getRawOriginal());
        $this->assertSame($auditCount, DB::table('activity_log')->count());
        $this->assertSame($walletTransactionCount, DB::table('wallet_transactions')->count());
        $this->assertDatabaseCount('registration_orders', 1);
        $this->assertSame($order->id, $invitation->fresh()->order_id);
    }

    public function test_cancelling_withdrawal_rejects_existing_category_draw_without_inventing_roster_restoration(): void
    {
        [$invitation, $order, $entry, $event] = $this->paidFixture();
        $entry->update([
            'status' => 'withdrawn',
            'withdrawn_at' => now(),
            'withdrawn_by' => $this->admin->id,
            'refund_status' => 'not_refunded',
        ]);
        app(InvitationService::class)->handlePaidWithdrawal($entry->registration_id, $this->admin);
        Draw::factory()->create([
            'event_id' => $event->id,
            'category_event_id' => $entry->category_event_id,
        ]);
        $entryBefore = $entry->fresh()->getRawOriginal();
        $invitationBefore = $invitation->fresh()->getRawOriginal();
        $auditCount = DB::table('activity_log')->count();
        $walletTransactionCount = DB::table('wallet_transactions')->count();

        $this->actingAs($this->admin)
            ->delete(route('admin.registration.refund.cancel', [$event, $entry]))
            ->assertSessionHasErrors('registration');

        $this->assertSame($entryBefore, $entry->fresh()->getRawOriginal());
        $this->assertSame($invitationBefore, $invitation->fresh()->getRawOriginal());
        $this->assertSame($auditCount, DB::table('activity_log')->count());
        $this->assertSame($walletTransactionCount, DB::table('wallet_transactions')->count());
        $this->assertDatabaseCount('draws', 1);
        $this->assertDatabaseCount('registration_orders', 1);
        $this->assertSame($order->id, $invitation->fresh()->order_id);
    }

    /** @return array{InterprovincialTrialInvitation, RegistrationOrder, CategoryEventRegistration, Event} */
    private function paidFixture(): array
    {
        $typeId = DB::table('eventtypes')->insertGetId([
            'name' => 'Interpro Trials '.uniqid(),
            'type' => EventType::INDIVIDUAL,
            'code' => EventType::INTERPROVINCIAL_TRIALS_CODE,
        ]);
        $event = Event::factory()->create([
            'eventType' => $typeId,
            'published' => true,
            'status' => 'active',
            'signUp' => true,
            'start_date' => now()->addDays(20)->toDateString(),
            'deadline' => 2,
        ]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id, 'entry_fee' => 0]);
        $owner = User::factory()->create();
        $payer = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        $nomination = EventNomination::create([
            'event_id' => $event->id,
            'category_event_id' => $category->id,
            'player_id' => $player->id,
        ]);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $this->admin->id]);

        $batch = InterprovincialTrialInvitationBatch::create([
            'event_id' => $event->id, 'status' => InterprovincialTrialInvitationBatch::DRAFT,
            'snapshot_hash' => str_repeat('e', 64), 'created_by_user_id' => $this->admin->id,
        ]);
        $invitation = InterprovincialTrialInvitation::create([
            'batch_id' => $batch->id, 'event_id' => $event->id, 'category_event_id' => $category->id,
            'nomination_id' => $nomination->id, 'player_id' => $player->id, 'status' => 'sent',
        ]);
        $order = app(InvitationService::class)->accept($invitation, $payer);
        $entry = CategoryEventRegistration::query()
            ->where('registration_id', $invitation->fresh()->registration_id)
            ->where('category_event_id', $category->id)
            ->sole();

        return [$invitation->fresh(), $order->fresh(), $entry, $event];
    }
}
