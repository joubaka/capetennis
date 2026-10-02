<?php

namespace Tests\Feature;

use App\Mail\WalletRefundConfirmationMail;
use App\Models\CategoryEventRegistration;
use App\Models\Event;
use App\Models\RegistrationOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminWalletRefundConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        Mail::fake();
        Role::findOrCreate('super-user', 'web');
        $admin = User::factory()->create()->assignRole('super-user');
        $payer = User::factory()->create(['name' => 'Payer Example']);
        $entry = CategoryEventRegistration::factory()->paid()->create(['payment_method' => 'payfast']);
        $event = $entry->categoryEvent->event;
        $contact = User::factory()->create(['email' => 'event-admin@example.test']);
        $event->admins()->attach($contact);
        $order = RegistrationOrder::create(['user_id' => $payer->id, 'total_fee' => 285, 'pay_status' => true,
            'payfast_paid' => true, 'payfast_amount_due' => 285, 'payfast_pf_payment_id' => $entry->pf_transaction_id]);
        DB::table('registration_order_items')->insert(['order_id' => $order->id, 'registration_id' => $entry->registration_id,
            'category_event_id' => $entry->category_event_id, 'item_price' => 285]);
        $this->actingAs($admin);
        return [$entry, $event, $payer, $contact, $order];
    }

    private function preview($entry, $event, string $reason = 'The general signup option was mistakenly available.'): array
    {
        return $this->postJson(route('superadmin.finances.full-refund.registration.preview', [$event, $entry]), ['reason' => $reason])
            ->assertOk()->json();
    }

    private function refund($entry, $event, array $preview, array $overrides = [])
    {
        return $this->postJson(route('superadmin.finances.full-refund.registration', [$event, $entry]), $overrides + [
            'method' => 'wallet', 'percentage' => 0, 'reason' => $preview['reason'], 'preview_token' => $preview['token'],
        ]);
    }

    public function test_full_refund_credits_original_payer_and_queues_reviewed_email_once(): void
    {
        [$entry, $event, $payer, $contact] = $this->scenario();
        $preview = $this->preview($entry, $event);
        $this->assertSame($payer->email, $preview['to']);
        $this->assertSame([$contact->email], $preview['cc']);
        $this->assertSame([$contact->email], $preview['reply_to']);
        $this->assertStringContainsString($preview['reason'], $preview['html']);
        $this->assertStringContainsString('Apply Wallet Balance', $preview['html']);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame('active', $entry->fresh()->status);
        $this->refund($entry, $event, $preview, ['amount' => 9999, 'user_id' => $entry->user_id])->assertRedirect();
        $this->assertDatabaseHas('category_event_registrations', ['id' => $entry->id, 'refund_net' => 285,
            'refund_fee' => 0, 'refund_status' => 'completed', 'status' => 'withdrawn', 'payment_status_id' => 1]);
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertEquals(285, $payer->fresh()->wallet->transactions()->sum('amount'));
        $this->assertNull($entry->user->fresh()->wallet);
        Mail::assertQueued(WalletRefundConfirmationMail::class, fn ($mail) => $mail->hasTo($payer->email)
            && $mail->envelope()->cc[0]->address === $contact->email
            && $mail->envelope()->replyTo[0]->address === $contact->email
            && $mail->refundReason === $preview['reason']);
        $this->refund($entry, $event, $preview)->assertUnprocessable();
        $this->assertDatabaseCount('wallet_transactions', 1);
        Mail::assertQueuedCount(1);
    }

    public function test_wrong_event_and_non_super_user_cannot_preview_or_refund(): void
    {
        [$entry, $event] = $this->scenario();
        $other = Event::factory()->create();
        $this->postJson(route('superadmin.finances.full-refund.registration.preview', [$other, $entry]), ['reason' => 'Wrong'])->assertNotFound();
        $this->postJson(route('superadmin.finances.full-refund.registration', [$other, $entry]), ['method' => 'wallet'])->assertNotFound();
        $this->actingAs(User::factory()->create());
        $this->postJson(route('superadmin.finances.full-refund.registration.preview', [$event, $entry]), ['reason' => 'Wrong'])->assertForbidden();
        $this->postJson(route('superadmin.finances.full-refund.registration', [$event, $entry]), ['method' => 'wallet'])->assertForbidden();
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_changed_reason_or_recipients_requires_fresh_preview(): void
    {
        [$entry, $event, , $contact] = $this->scenario();
        $preview = $this->preview($entry, $event);
        $this->refund($entry, $event, $preview, ['reason' => 'Different reason'])->assertUnprocessable();
        $contact->update(['email' => 'changed@example.test']);
        $this->refund($entry, $event, $preview)->assertUnprocessable();
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame('active', $entry->fresh()->status);
        Mail::assertNothingQueued();
    }

    public function test_pending_bank_refund_or_unfinalized_payment_is_rejected(): void
    {
        [$entry, $event, , , $order] = $this->scenario();
        $preview = $this->preview($entry, $event);
        $order->update(['payfast_paid' => false]);
        $this->refund($entry, $event, $preview)->assertUnprocessable();
        $order->update(['payfast_paid' => true]);
        $entry->update(['refund_status' => 'pending', 'refund_method' => 'bank']);
        $this->refund($entry, $event, $preview)->assertUnprocessable();
        $this->postJson(route('superadmin.finances.full-refund.registration', [$event, $entry]), ['method' => 'bank'])->assertUnprocessable();
        $this->assertDatabaseCount('wallet_transactions', 0);
        Mail::assertNothingQueued();
    }

    public function test_mail_queue_failure_keeps_successful_credit_and_reports_gap(): void
    {
        [$entry, $event] = $this->scenario();
        $preview = $this->preview($entry, $event);
        Mail::shouldReceive('to')->once()->andThrow(new \RuntimeException('queue unavailable'));
        $this->refund($entry, $event, $preview)->assertRedirect()->assertSessionHas('success', fn ($message) => str_contains($message, 'could not be queued'));
        $this->assertDatabaseCount('wallet_transactions', 1);
        $this->assertSame('completed', $entry->fresh()->refund_status);
    }

    public function test_tampered_expired_and_missing_preview_cannot_change_financial_state(): void
    {
        [$entry, $event] = $this->scenario();
        $preview = $this->preview($entry, $event);
        $this->refund($entry, $event, $preview, ['preview_token' => 'tampered'])->assertUnprocessable();
        $this->refund($entry, $event, $preview, ['preview_token' => ''])->assertUnprocessable();
        $this->travel(16)->minutes();
        $this->refund($entry, $event, $preview)->assertUnprocessable();
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame('active', $entry->fresh()->status);
        Mail::assertNothingQueued();
    }

    public function test_disabled_mail_missing_admin_or_changed_amount_prevents_refund(): void
    {
        [$entry, $event, , $contact, $order] = $this->scenario();
        $preview = $this->preview($entry, $event);
        $event->admins()->detach($contact);
        $this->refund($entry, $event, $preview)->assertUnprocessable();
        $event->admins()->attach($contact);
        \App\Models\SiteSetting::set('player_email_on_wallet_refund', '0');
        $this->refund($entry, $event, $preview)->assertUnprocessable();
        \App\Models\SiteSetting::set('player_email_on_wallet_refund', '1');
        $order->update(['payfast_amount_due' => 300]);
        $this->refund($entry, $event, $preview)->assertUnprocessable();
        $this->assertDatabaseCount('wallet_transactions', 0);
        Mail::assertNothingQueued();
    }

    public function test_wallet_reservation_is_not_a_finalized_payment(): void
    {
        [$entry, $event, , , $order] = $this->scenario();
        $order->update(['wallet_reserved' => 285, 'wallet_debited' => false, 'payfast_amount_due' => 0, 'payfast_paid' => false]);
        $entry->update(['payment_method' => 'wallet']);
        $this->postJson(route('superadmin.finances.full-refund.registration.preview', [$event, $entry]), ['reason' => 'Cancelled'])
            ->assertUnprocessable();
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertSame('active', $entry->fresh()->status);
    }

    public function test_ambiguous_multiple_item_order_is_rejected(): void
    {
        [$entry, $event, , , $order] = $this->scenario();
        DB::table('registration_order_items')->insert(['order_id' => $order->id, 'item_price' => 100]);
        $this->postJson(route('superadmin.finances.full-refund.registration.preview', [$event, $entry]), ['reason' => 'Cancelled'])
            ->assertUnprocessable();
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_failed_wallet_credit_rolls_back_withdrawal_and_wallet_creation(): void
    {
        [$entry, $event, $payer] = $this->scenario();
        $preview = $this->preview($entry, $event);
        $this->partialMock(\App\Domain\Refunds\Services\RefundExecutionService::class, fn ($mock) =>
            $mock->shouldReceive('executeWalletRefund')->once()->andThrow(new \RuntimeException('credit failed')));
        $this->refund($entry, $event, $preview)->assertServerError();
        $this->assertSame('active', $entry->fresh()->status);
        $this->assertSame('not_refunded', $entry->fresh()->refund_status);
        $this->assertNull($payer->fresh()->wallet);
        $this->assertDatabaseCount('wallet_transactions', 0);
        Mail::assertNothingQueued();
    }

    public function test_deduction_is_exact_and_email_uses_frozen_names(): void
    {
        [$entry, $event, $payer] = $this->scenario();
        $preview = $this->postJson(route('superadmin.finances.full-refund.registration.preview', [$event, $entry]), [
            'reason' => 'Cancelled', 'percentage' => 10,
        ])->assertOk()->json();
        $this->refund($entry, $event, $preview, ['percentage' => 10])->assertRedirect();
        $this->assertDatabaseHas('category_event_registrations', ['id' => $entry->id, 'refund_gross' => 285, 'refund_fee' => 28.5, 'refund_net' => 256.5]);
        $this->assertEquals(256.5, $payer->fresh()->wallet->transactions()->sum('amount'));
        Mail::assertQueued(WalletRefundConfirmationMail::class, function ($mail) use ($preview, $event) {
            $event->update(['name' => 'Changed after refund']);
            $mail->registration->unsetRelations();
            return $mail->emailSnapshot['event_name'] === $preview['event_name']
                && !str_contains($mail->render(), 'Changed after refund');
        });
    }
}
