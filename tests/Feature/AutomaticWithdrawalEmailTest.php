<?php

namespace Tests\Feature;

use App\Jobs\SendBulkEmailJob;
use App\Mail\{WithdrawalAdminMail, WithdrawalPlayerMail};
use App\Models\{BulkEmailLog, CategoryEvent, CategoryEventRegistration, Event, EventType, Player, SiteSetting, Team, TeamPaymentOrder, TeamPlayer, User};
use App\Services\{MailAccountManager, TeamWithdrawalNotificationService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Bus, DB, Mail};
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AutomaticWithdrawalEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
    }

    public function test_paid_request_waits_for_completion_then_managed_transport_sends_once_with_actual_facts(): void
    {
        [$actor, $event, $team, $player, $order] = $this->fixture();
        $event->update(['withdrawal_deadline' => now()->addDay()]);
        $this->actingAs($actor)->post(route('team.player.withdraw', [$team, $player, $event]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bulk_email_logs', 0);
        $this->assertDatabaseCount('event_communication_batches', 0);
        $requestedAt = $order->fresh()->withdrawn_at;
        $this->travel(2)->hours();
        $event->update(['withdrawal_deadline' => now()->subDay()]);
        $this->post(route('team.player.withdraw', [$team, $player, $event]))->assertSessionHasNoErrors();
        $logs = BulkEmailLog::where('mail_type', 'team_withdrawal_notification')->get();
        $this->assertCount(2, $logs);
        $this->assertDatabaseCount('event_communication_batches', 0);
        $activity = Activity::where('description', 'team participant withdrawal completed')->sole();
        $this->assertTrue(\Illuminate\Support\Carbon::parse($activity->properties['withdrawn_at'])->gt($requestedAt));
        app(TeamWithdrawalNotificationService::class)->record($activity);
        $this->assertDatabaseCount('bulk_email_logs', 2);
        $this->mock(MailAccountManager::class)->shouldReceive('getMailer')->andReturn('array');
        $transport = Mail::mailer('array')->getSymfonyTransport();
        $transport->flush();
        foreach ($logs as $log) {
            (new SendBulkEmailJob($log->id))->handle();
            (new SendBulkEmailJob($log->id))->handle();
            $this->assertSame('sent', $log->fresh()->status);
        }
        $this->assertCount(2, $transport->messages());
        foreach ($transport->messages() as $sent) {
            $message = $sent->getOriginalMessage();
            $this->assertStringContainsString(e($player->full_name), $message->getHtmlBody());
            $this->assertStringContainsString(e($actor->name), $message->getHtmlBody());
            $this->assertStringContainsString(now()->format('d M Y H:i:s'), $message->getHtmlBody());
            $this->assertStringContainsString(config('app.timezone'), $message->getHtmlBody());
            $this->assertSame([], $message->getCc());
            $this->assertSame([], $message->getBcc());
        }
        $this->post(route('team.player.withdraw', [$team, $player, $event]))->assertSessionHasErrors();
        $this->assertDatabaseCount('bulk_email_logs', 2);
        $this->assertSame(200.0, (float) $order->fresh()->total_amount);
        $this->assertTrue($order->fresh()->pay_status);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_no_order_withdrawal_notifies_after_commit_and_rollback_sends_nothing(): void
    {
        [$actor, $event, $team, $player, $order] = $this->fixture();
        $order->delete();
        TeamPlayer::where('team_id', $team->id)->update(['pay_status' => 0]);
        DB::beginTransaction();
        $this->actingAs($actor)->post(route('team.player.withdraw', [$team, $player, $event]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bulk_email_logs', 0);
        DB::rollBack();
        Bus::assertNothingDispatched();
        $this->assertSame($player->id, TeamPlayer::where('team_id', $team->id)->sole()->player_id);
        $this->post(route('team.player.withdraw', [$team, $player, $event]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('team_payment_orders', 0);
        $this->assertDatabaseCount('bulk_email_logs', 2);
        $this->assertDatabaseCount('event_communication_batches', 0);
        Bus::assertDispatched(SendBulkEmailJob::class, 2);
    }

    public function test_current_recipient_settings_and_source_scope_are_rechecked_before_transport(): void
    {
        [$actor, $event, $team, $player] = $this->fixture();
        $event->update(['withdrawal_deadline' => now()->subDay()]);
        $this->actingAs($actor)->post(route('team.player.withdraw', [$team, $player, $event]))->assertSessionHasNoErrors();
        $service = app(TeamWithdrawalNotificationService::class);
        $log = BulkEmailLog::where('recipient_email', $actor->email)->sole();
        $this->assertNotNull($service->authorizedMailable($log));
        foreach (['event_id' => Event::factory()->create()->id, 'body' => '<p>Changed</p>', 'subject' => 'Changed'] as $field => $value) {
            $changed = clone $log;
            $changed->payload = [...$log->payload, $field => $value];
            $this->assertNull($service->authorizedMailable($changed));
        }
        $changed = clone $log; $changed->recipient_email = 'unrelated@example.test';
        $this->assertNull($service->authorizedMailable($changed));
        SiteSetting::set('player_email_on_team_withdrawal', '0', SiteSetting::GROUP_EMAIL);
        $this->assertNull($service->authorizedMailable($log));
        $adminLog = BulkEmailLog::where('recipient_email', 'event-admin@example.test')->sole();
        $this->assertNotNull($service->authorizedMailable($adminLog));
        DB::table('event_admins')->where('event_id', $event->id)->delete();
        $this->assertNull($service->authorizedMailable($adminLog));
        $activity = Activity::find($log->related_id);
        $activity->update(['properties' => [...$activity->properties, 'event_id' => Event::factory()->create()->id]]);
        $this->assertNull($service->authorizedMailable($log));
    }

    public function test_individual_default_and_custom_templates_name_actual_account_and_recorded_time(): void
    {
        $actor = User::factory()->create(['name' => 'Parent Actor']);
        $entry = CategoryEventRegistration::factory()->withdrawn()->create(['withdrawn_by' => $actor->id, 'withdrawn_at' => '2026-11-22 14:35:12']);
        foreach ([new WithdrawalAdminMail($entry), new WithdrawalPlayerMail($entry)] as $mail) {
            $body = $mail->render();
            $this->assertStringContainsString('Parent Actor', $body);
            $this->assertStringContainsString('22 Nov 2026 14:35:12', $body);
            $this->assertStringContainsString(config('app.timezone'), $body);
        }
        SiteSetting::set('player_email_body_withdrawal', 'Custom withdrawal message without placeholders.', SiteSetting::GROUP_EMAIL);
        $body = (new WithdrawalPlayerMail($entry))->render();
        $this->assertStringContainsString('Custom withdrawal message', $body);
        $this->assertStringContainsString('Parent Actor', $body);
        $this->assertStringContainsString('22 Nov 2026 14:35:12', $body);
    }

    public function test_mail_preparation_failure_does_not_undo_completed_withdrawal(): void
    {
        [$actor, $event, $team, $player, $order] = $this->fixture();
        $event->update(['withdrawal_deadline' => now()->subDay()]);
        $this->mock(\App\Services\BulkMailDispatcher::class)->shouldReceive('dispatch')->andThrow(new \RuntimeException('Simulated local preparation failure'));
        $this->actingAs($actor)->post(route('team.player.withdraw', [$team, $player, $event]))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('team_players', ['team_id' => $team->id, 'player_id' => 0]);
        $this->assertNotNull($order->fresh()->withdrawn_at);
        $this->assertSame(1, Activity::where('description', 'team participant withdrawal completed')->count());
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    private function fixture(): array
    {
        $actor = User::factory()->create(['name' => 'Payer Parent', 'email' => 'payer@example.test']);
        $player = Player::factory()->create(['userId' => $actor->id, 'email' => $actor->email]);
        $actor->players()->attach($player);
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Team withdrawal test', 'type' => EventType::TEAM]);
        $event = Event::factory()->create(['eventType' => $type, 'start_date' => today()->addWeek()]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $team = Team::factory()->create(['category_event_id' => $category->id]);
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 1]);
        $admin = User::factory()->create(['email' => 'event-admin@example.test']);
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $order = TeamPaymentOrder::create(['user_id' => $actor->id, 'event_id' => $event->id, 'team_id' => $team->id,
            'player_id' => $player->id, 'total_amount' => 200, 'pay_status' => 1, 'payfast_paid' => true]);
        return [$actor, $event, $team, $player, $order];
    }
}
