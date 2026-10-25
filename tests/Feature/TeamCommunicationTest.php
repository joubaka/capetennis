<?php

namespace Tests\Feature;

use App\Mail\TeamActionMail;
use App\Events\PaymentCompleted;
use App\Models\Event;
use App\Models\EventCommunicationBatch;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPaymentOrder;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\TeamCommunicationService;
use App\Listeners\SendTeamRegistrationConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Bus;
use App\Models\{BulkEmailLog, CategoryEvent, EventType, TeamPlayer};
use App\Jobs\SendBulkEmailJob;
use App\Services\TeamRegistrationConfirmationService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamCommunicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_confirmation_is_automatically_logged_once_for_payer(): void
    {
        Mail::fake();
        Bus::fake();
        $order = $this->order();

        app(SendTeamRegistrationConfirmation::class)->handle(new PaymentCompleted($order));
        app(SendTeamRegistrationConfirmation::class)->handle(new PaymentCompleted($order));

        Mail::assertNothingQueued();
        $this->assertDatabaseCount('event_communication_batches', 0);
        $log = BulkEmailLog::sole();
        $this->assertSame($order->user->email, $log->recipient_email);
        $this->assertSame('team_payment_confirmation', $log->mail_type);
        $this->assertSame($order->event_id, $log->payload['event_id']);
        Bus::assertDispatched(SendBulkEmailJob::class, 1);
    }

    public function test_canonical_payment_completion_waits_for_commit_and_is_idempotent(): void
    {
        Bus::fake(); Mail::fake();
        $order = $this->order();
        DB::table('team_payment_orders')->where('id', $order->id)->update(['pay_status' => 0, 'payfast_paid' => 0, 'payfast_amount_due' => 285]);
        DB::table('team_players')->where('team_id', $order->team_id)->update(['pay_status' => 0]);
        DB::beginTransaction();
        $service = app(\App\Domain\Payments\Services\TeamPaymentService::class);
        $service->finalizePayment($order->fresh(), ['payment_method' => 'payfast', 'payfast_amount_received' => 285, 'pf_payment_id' => 'confirmation-test']);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Bus::assertNothingDispatched();
        DB::commit();
        $service->finalizePayment($order->fresh(), ['payment_method' => 'payfast', 'payfast_amount_received' => 285, 'pf_payment_id' => 'confirmation-test']);
        $this->assertDatabaseCount('bulk_email_logs', 1);
        $this->assertDatabaseCount('event_communication_batches', 0);
        Bus::assertDispatched(SendBulkEmailJob::class, 1);
    }

    public function test_wallet_completion_and_transaction_rollback_obey_confirmation_boundary(): void
    {
        Bus::fake(); Mail::fake();
        $order = $this->order();
        $wallet = \App\Models\Wallet::factory()->forUser($order->user)->create();
        app(\App\Domain\Payments\Services\LedgerService::class)->appendWalletCredit($wallet, 285, 'confirmation-test-credit', $order->id);
        DB::table('team_payment_orders')->where('id', $order->id)->update(['pay_status' => 0, 'payfast_paid' => 0, 'wallet_reserved' => 285, 'payfast_amount_due' => 0]);
        DB::table('team_players')->where('team_id', $order->team_id)->update(['pay_status' => 0]);
        $service = app(\App\Domain\Payments\Services\TeamPaymentService::class);
        DB::beginTransaction();
        $service->finalizeWalletPayment($order->fresh());
        DB::rollBack();
        $this->assertFalse($order->fresh()->pay_status);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Bus::assertNothingDispatched();
        $service->finalizeWalletPayment($order->fresh());
        $service->finalizeWalletPayment($order->fresh());
        $this->assertDatabaseCount('bulk_email_logs', 1);
        $this->assertSame(1, \App\Models\WalletTransaction::where('source_type', 'team_registration_wallet_payment')->where('source_id', $order->id)->count());
        $this->assertSame(285.0, (float) \App\Models\WalletTransaction::where('source_type', 'team_registration_wallet_payment')->where('source_id', $order->id)->sole()->amount);
        Bus::assertDispatched(SendBulkEmailJob::class, 1);
    }

    public function test_automatic_exception_rejects_changed_envelope_or_content_even_with_reviewed_flag(): void
    {
        Bus::fake(); Mail::fake();
        $order = $this->order();
        app(SendTeamRegistrationConfirmation::class)->handle(new PaymentCompleted($order));
        $log = BulkEmailLog::sole(); $log->update(['status' => 'sending']);
        $canonical = app(TeamRegistrationConfirmationService::class)->authorizedMailable($log);
        $listener = app(\App\Listeners\RequireEventEmailReview::class);
        $html = (new \ReflectionMethod($listener, 'previewHtml'))->invoke($listener, $canonical->body);
        $message = (new \Symfony\Component\Mime\Email)->from(new \Symfony\Component\Mime\Address(config('mail.from.address'), $canonical->fromName))
            ->replyTo($canonical->replyToAddress)->to($log->recipient_email)->subject($canonical->subjectLine)->html($html);
        $data = ['team_confirmation_log_id' => $log->id, 'event_mail_reviewed' => true];
        $this->assertNull($listener->handle(new \Illuminate\Mail\Events\MessageSending($message, $data)));
        foreach (['to', 'cc', 'bcc', 'from', 'replyTo', 'subject', 'html', 'text', 'attach'] as $field) {
            $changed = clone $message;
            $changed->{$field}($field === 'html' ? '<p>Altered content</p>' : ($field === 'subject' || $field === 'text' || $field === 'attach' ? 'Altered content' : 'other@example.test'));
            try {
                $listener->handle(new \Illuminate\Mail\Events\MessageSending($changed, $data));
                $this->fail('Expected rejection for '.$field);
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
                $this->assertSame(409, $exception->getStatusCode(), $field);
            }
        }
        $log->update(['status' => 'queued']);
        try {
            $listener->handle(new \Illuminate\Mail\Events\MessageSending($message, $data));
            $this->fail('An unclaimed log cannot authorize transport.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
    }

    public function test_automatic_confirmation_uses_real_managed_job_and_keeps_authored_mail_reviewed(): void
    {
        Bus::fake();
        $order = $this->order();
        $this->mock(\App\Services\MailAccountManager::class)->shouldReceive('getMailer')->andReturn('array');
        $mailer = Mail::mailer('array'); $mailer->getSymfonyTransport()->flush();
        app(SendTeamRegistrationConfirmation::class)->handle(new PaymentCompleted($order));
        $log = BulkEmailLog::sole();
        (new SendBulkEmailJob($log->id))->handle();
        (new SendBulkEmailJob($log->id))->handle();
        $sent = $mailer->getSymfonyTransport()->messages();
        $this->assertCount(1, $sent);
        $message = $sent->first()->getOriginalMessage();
        $this->assertSame($order->user->email, $message->getTo()[0]->getAddress());
        $this->assertSame([], $message->getCc()); $this->assertSame([], $message->getBcc());
        $this->assertStringContainsString('Team Registration Confirmed', $message->getSubject());
        $this->assertStringContainsString(e($order->effectivePlayer->full_name), $message->getHtmlBody());
        $this->assertSame('sent', $log->fresh()->status);
        $this->assertDatabaseCount('event_communication_batches', 0);

        // An ordinary authored team message still becomes a reviewed draft.
        Mail::mailer('array')->to($order->user->email)->sendNow(new TeamActionMail($order, 'withdrawal'));
        $this->assertCount(1, $mailer->getSymfonyTransport()->messages());
        $this->assertDatabaseCount('event_communication_batches', 1);
    }

    public function test_unpaid_foreign_withdrawn_or_refunded_orders_cannot_queue_automatic_confirmation(): void
    {
        Bus::fake(); Mail::fake();
        foreach ([['pay_status' => 0], ['pay_status' => 2], ['event_id' => Event::factory()->create()->id], ['withdrawn_at' => now()], ['refund_status' => 'completed']] as $changes) {
            $order = $this->order();
            DB::table('team_payment_orders')->where('id', $order->id)->update($changes);
            app(SendTeamRegistrationConfirmation::class)->handle(new PaymentCompleted($order));
        }
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $this->assertDatabaseCount('event_communication_batches', 0);
        Bus::assertNothingDispatched();
    }

    public function test_existing_registration_draft_remains_unsent_on_replayed_event(): void
    {
        Bus::fake(); Mail::fake();
        $order = $this->order();
        $mail = new TeamActionMail($order, 'registration');
        app(\App\Services\EventCommunicationService::class)->draftFixed($order->event,
            'team-order:'.$order->id.':registration:'.hash('sha256', strtolower($order->user->email)),
            [['email' => $order->user->email, 'name' => $order->user->name, 'kind' => 'players', 'subject' => $mail->envelope()->subject, 'html' => $mail->render()]], $mail->envelope()->subject);
        $order->user->update(['email' => 'new-contact@example.test']);
        app(SendTeamRegistrationConfirmation::class)->handle(new PaymentCompleted($order));
        $this->assertDatabaseCount('event_communication_batches', 1);
        $this->assertSame('draft', EventCommunicationBatch::sole()->status);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Bus::assertNothingDispatched();
    }

    public function test_trials_participation_orders_keep_the_existing_review_workflow(): void
    {
        Bus::fake(); Mail::fake();
        $order = $this->order();
        DB::table('eventtypes')->where('id', $order->event->eventType)->update(['code' => EventType::INTERPROVINCIAL_TRIALS_CODE]);
        DB::table('team_payment_orders')->where('id', $order->id)->update(['team_id' => null]);
        app(SendTeamRegistrationConfirmation::class)->handle(new PaymentCompleted($order->fresh()));
        $this->assertDatabaseCount('event_communication_batches', 1);
        $this->assertDatabaseCount('bulk_email_logs', 0);
        Bus::assertNothingDispatched();
    }

    public function test_changed_recipient_content_or_paid_state_invalidates_queued_confirmation(): void
    {
        Bus::fake(); Mail::fake();
        foreach (['payer', 'body', 'event', 'withdrawn', 'beneficiary', 'amount'] as $change) {
            $order = $this->order();
            app(SendTeamRegistrationConfirmation::class)->handle(new PaymentCompleted($order));
            $log = BulkEmailLog::where('related_id', $order->id)->sole();
            $this->assertNotNull(app(TeamRegistrationConfirmationService::class)->authorizedMailable($log));
            match ($change) {
                'payer' => $order->user->update(['email' => 'changed@example.test']),
                'body' => $log->update(['payload' => [...$log->payload, 'body' => 'Unapproved text']]),
                'event' => DB::table('team_payment_orders')->where('id', $order->id)->update(['event_id' => Event::factory()->create()->id]),
                'withdrawn' => DB::table('team_payment_orders')->where('id', $order->id)->update(['withdrawn_at' => now()]),
                'beneficiary' => DB::table('team_payment_orders')->where('id', $order->id)->update(['beneficiary_player_id' => Player::factory()->create()->id]),
                'amount' => DB::table('team_payment_orders')->where('id', $order->id)->update(['total_amount' => 286]),
            };
            $this->assertNull(app(TeamRegistrationConfirmationService::class)->authorizedMailable($log->fresh()));
        }
    }

    public function test_withdrawal_notifies_payer_super_users_and_event_admins_once(): void
    {
        Mail::fake();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $order = $this->order();
        $super = User::factory()->create(['email' => 'super@example.test'])->assignRole('super-user');
        $admin = User::factory()->create(['email' => 'admin@example.test']);
        DB::table('event_admins')->insert(['event_id' => $order->event_id, 'user_id' => $admin->id]);

        app(TeamCommunicationService::class)->withdrawal($order->fresh(), ['refund_available' => true]);

        Mail::assertNothingQueued();
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $this->assertDatabaseCount('event_communication_batches', 3);
        foreach ([$order->user->email, $super->email, $admin->email] as $email) {
            $this->assertContains($email, EventCommunicationBatch::all()->flatMap(fn ($batch) => array_column($batch->recipients, 'email'))->all());
        }
    }

    public function test_disabled_team_registration_email_is_not_queued(): void
    {
        Mail::fake();
        SiteSetting::set('player_email_on_team_registration', '0', SiteSetting::GROUP_EMAIL);
        $order = $this->order();

        app(SendTeamRegistrationConfirmation::class)->handle(new PaymentCompleted($order));

        Mail::assertNothingQueued();
    }

    public function test_player_and_admin_team_withdrawal_switches_apply_independently(): void
    {
        Mail::fake();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $order = $this->order();
        $super = User::factory()->create(['email' => 'super@example.test'])->assignRole('super-user');
        SiteSetting::set('player_email_on_team_withdrawal', '0', SiteSetting::GROUP_EMAIL);
        SiteSetting::set('email_on_team_withdrawal', '1', SiteSetting::GROUP_EMAIL);

        app(TeamCommunicationService::class)->withdrawal($order->fresh());

        Mail::assertNothingQueued();
        $this->assertSame($super->email, EventCommunicationBatch::sole()->recipients[0]['email']);
    }

    public function test_settled_team_order_keeps_receipt_with_payer_and_sends_safe_contact_updates_once(): void
    {
        Bus::fake(); Mail::fake();
        $order = $this->order();
        $order->effectivePlayer->update(['email_2' => 'parent2@example.test', 'email_3' => 'parent3@example.test']);
        DB::table('team_payment_orders')->where('id', $order->id)->update(['pay_status' => 0, 'payfast_paid' => 0, 'payfast_amount_due' => 285]);
        DB::table('team_players')->where('team_id', $order->team_id)->update(['pay_status' => 0]);
        $service = app(\App\Domain\Payments\Services\TeamPaymentService::class);
        $context = ['payment_method' => 'payfast', 'payfast_amount_received' => 285, 'pf_payment_id' => 'extra-contact-confirmation'];
        $service->finalizePayment($order->fresh(), $context);
        $service->finalizePayment($order->fresh(), $context);
        $receipt = BulkEmailLog::where('mail_type', 'team_payment_confirmation')->sole();
        $this->assertSame($order->user->email, $receipt->recipient_email);
        $this->assertNotNull(app(TeamRegistrationConfirmationService::class)->authorizedMailable($receipt));
        $updates = BulkEmailLog::where('mail_type', 'player_registration_update')->get();
        $this->assertEqualsCanonicalizing(['parent2@example.test', 'parent3@example.test'], $updates->pluck('recipient_email')->all());
        foreach ($updates as $log) {
            $mail = app(\App\Services\PlayerRegistrationUpdateService::class)->authorizedMailable($log);
            $this->assertNotNull($mail);
            $this->assertStringNotContainsString('285', $mail->body);
            $this->assertStringNotContainsString('checkout', $mail->body);
        }
        $this->assertSame(285.0, $order->fresh()->total_amount);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('user_players', 0);
        $this->assertDatabaseCount('bulk_email_logs', 3);
    }

    private function order(): TeamPaymentOrder
    {
        $user = User::factory()->create();
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Team confirmation test', 'type' => EventType::TEAM]);
        $event = Event::factory()->create(['eventType' => $type]);
        $player = Player::factory()->create(['userId' => $user->id]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $team = Team::factory()->create(['category_event_id' => $category->id]);
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 1]);

        return TeamPaymentOrder::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'player_id' => $player->id,
            'team_id' => $team->id,
            'total_amount' => 285,
            'pay_status' => 1,
        ]);
    }
}
