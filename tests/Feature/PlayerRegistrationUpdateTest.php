<?php

namespace Tests\Feature;

use App\Models\{BulkEmailLog, CategoryEventRegistration, Player, RegistrationOrder, RegistrationOrderItems, SiteSetting, User};
use App\Services\PlayerRegistrationUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Queue};
use Tests\TestCase;

class PlayerRegistrationUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function entry(): array
    {
        Queue::fake();
        $payer = User::factory()->create(['email' => 'payer@example.test']);
        $entry = CategoryEventRegistration::factory()->paid()->create(['user_id' => $payer->id]);
        $player = Player::factory()->create(['email' => $payer->email, 'email_2' => 'second@example.test', 'email_3' => 'third@example.test']);
        $entry->players()->attach($player);
        return [$entry, $player, $payer];
    }

    public function test_registration_update_excludes_payer_and_has_no_financial_or_access_links(): void
    {
        [$entry, $player] = $this->entry();
        $service = app(PlayerRegistrationUpdateService::class);
        $service->record($entry);
        $this->assertEqualsCanonicalizing(['second@example.test', 'third@example.test'], BulkEmailLog::pluck('recipient_email')->all());
        $this->assertDatabaseCount('user_players', 0);
        $this->assertDatabaseCount('wallet_transactions', 0);
        foreach (BulkEmailLog::all() as $log) {
            $mail = $service->authorizedMailable($log);
            $this->assertNotNull($mail);
            $this->assertStringContainsString(e($player->full_name), $mail->body);
            $this->assertStringContainsString(route('events.show', $entry->categoryEvent->event), $mail->body);
            foreach (['order_id', 'checkout', 'wallet', 'PayFast', 'token=', 'signature='] as $secret) {
                $this->assertStringNotContainsString($secret, $mail->body);
            }
        }
        $service->record($entry);
        $this->assertSame(2, BulkEmailLog::where('status', 'queued')->count());
    }

    public function test_removed_contacts_and_withdrawn_entries_are_suppressed_before_delivery(): void
    {
        [$entry, $player] = $this->entry();
        $service = app(PlayerRegistrationUpdateService::class);
        $service->record($entry);
        $second = BulkEmailLog::where('recipient_email', 'second@example.test')->sole();
        $third = BulkEmailLog::where('recipient_email', 'third@example.test')->sole();
        $player->update(['email_2' => null]);
        $this->assertNull($service->authorizedMailable($second));
        $this->assertNotNull($service->authorizedMailable($third));
        $entry->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        $this->assertNull($service->authorizedMailable($third));
        $service->record($entry, 'withdrawal');
        $withdrawal = BulkEmailLog::where('payload->action', 'withdrawal')->sole();
        $this->assertSame('third@example.test', $withdrawal->recipient_email);
        $this->assertNotNull($service->authorizedMailable($withdrawal));
    }

    public function test_rollback_and_unpaid_entries_do_not_queue_updates_and_cross_event_payload_is_rejected(): void
    {
        [$entry] = $this->entry();
        $service = app(PlayerRegistrationUpdateService::class);
        DB::beginTransaction();
        $service->record($entry);
        DB::rollBack();
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $entry->update(['payment_status_id' => null]);
        $service->record($entry);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $entry->update(['payment_status_id' => 1]);
        $service->record($entry);
        $log = BulkEmailLog::firstOrFail();
        $log->payload = [...$log->payload, 'event_id' => 999999];
        $this->assertNull($service->authorizedMailable($log));
    }

    public function test_free_registration_service_queues_participant_updates_without_changing_order_amounts(): void
    {
        [$entry, $player, $payer] = $this->entry();
        $entry->update(['payment_status_id' => null]);
        $order = RegistrationOrder::create(['user_id' => $payer->id, 'total_fee' => 0, 'pay_status' => false]);
        (new RegistrationOrderItems)->forceFill(['order_id' => $order->id, 'registration_id' => $entry->registration_id,
            'category_event_id' => $entry->category_event_id, 'player_id' => $player->id, 'user_id' => $payer->id, 'item_price' => 0])->save();
        app(\App\Domain\Payments\Services\RegistrationPaymentService::class)->markFreeOrderPaid($order);
        $this->assertSame(2, BulkEmailLog::where('mail_type', 'player_registration_update')->count());
        $this->assertSame(0.0, $order->fresh()->total_fee);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_transport_rejects_tampered_recipient_and_content_even_with_reviewed_flag(): void
    {
        [$entry] = $this->entry();
        $service = app(PlayerRegistrationUpdateService::class);
        $service->record($entry);
        $log = BulkEmailLog::firstOrFail();
        $log->update(['status' => 'sending']);
        $canonical = $service->authorizedMailable($log);
        $listener = app(\App\Listeners\RequireEventEmailReview::class);
        $html = (new \ReflectionMethod($listener, 'previewHtml'))->invoke($listener, $canonical->body);
        $message = (new \Symfony\Component\Mime\Email)->from(new \Symfony\Component\Mime\Address(config('mail.from.address'), $canonical->fromName))
            ->replyTo($canonical->replyToAddress)->to($log->recipient_email)->subject($canonical->subjectLine)->html($html);
        $data = ['player_registration_update_log_id' => $log->id, 'event_mail_reviewed' => true];
        $this->assertNull($listener->handle(new \Illuminate\Mail\Events\MessageSending($message, $data)));
        foreach (['to', 'cc', 'bcc', 'from', 'replyTo', 'subject', 'html', 'text', 'attach'] as $field) {
            $changed = clone $message;
            $changed->{$field}($field === 'html' ? '<p>Altered</p>' : (in_array($field, ['subject', 'text', 'attach'], true) ? 'Altered' : 'other@example.test'));
            try {
                $listener->handle(new \Illuminate\Mail\Events\MessageSending($changed, $data));
                $this->fail('Expected rejection for '.$field);
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(409, $exception->getStatusCode());
            }
        }
    }

    public function test_reinstatement_notifies_optional_contacts_once_without_sending_payment_receipts(): void
    {
        [$entry] = $this->entry();
        \Illuminate\Support\Facades\Mail::fake();
        \Spatie\Permission\Models\Role::findOrCreate('super-user', 'web');
        $actor = User::factory()->create()->assignRole('super-user');
        $entry->update(['status' => 'withdrawn', 'withdrawn_at' => now()]);
        $event = $entry->categoryEvent->event;
        $this->actingAs($actor)->delete(route('admin.registration.refund.cancel', [$event, $entry]))->assertRedirect();
        foreach (['payer@example.test', 'second@example.test', 'third@example.test'] as $email) {
            \Illuminate\Support\Facades\Mail::assertQueued(\App\Mail\RegistrationReinstatedMail::class, fn ($mail) => $mail->hasTo($email));
        }
        \Illuminate\Support\Facades\Mail::assertQueuedCount(3);
        $this->delete(route('admin.registration.refund.cancel', [$event, $entry]))->assertRedirect();
        \Illuminate\Support\Facades\Mail::assertQueuedCount(3);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }
}
