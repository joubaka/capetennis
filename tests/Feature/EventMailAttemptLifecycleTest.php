<?php

namespace Tests\Feature;

use App\Models\{BulkEmailLog, Event, EventMailAttemptHistory, EventMailIssue, User};
use App\Services\MailFailureOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\{DB, Mail, Queue};
use Tests\TestCase;
class EventMailAttemptLifecycleTest extends TestCase
{
    use RefreshDatabase;
    private function queued(bool $broken = false, bool $plaintext = false): BulkEmailLog
    {
        config(['queue.default' => 'database', 'queue.connections.database.after_commit' => false, 'mail.default' => 'array']);
        $actor = User::factory()->create();
        $this->actingAs($actor);
        $event = Event::factory()->create();
        Mail::mailer('array')->to('recipient@example.test')->queue(new LifecycleMail($event, $broken, $plaintext));
        $log = BulkEmailLog::sole();
        $this->assertSame('enqueued', $log->payload['queue_state']);
        $payload = json_decode(DB::table('jobs')->sole()->payload, true);
        $this->assertSame([$log->id], $payload['outbound_mail_history_ids']);
        $command = unserialize($payload['data']['command']);
        $this->assertSame([$log->id], $command->mailable->viewData['outbound_mail_history_ids']);
        $this->assertSame($actor->id, $log->payload['created_by']);
        auth()->logout();
        return $log;
    }
    private function work(bool $expectedFailure = false): void
    {
        $job = Queue::connection('database')->pop();
        try {
            app('queue.worker')->process('database', $job, new \Illuminate\Queue\WorkerOptions(maxTries: 3, backoff: 0));
        } catch (\Throwable $e) {
            if (!$expectedFailure) {
                throw $e;
            }
        }
    }
    public function test_real_queue_roundtrip_preserves_event_actor_and_snapshot_without_worker_auth(): void
    {
        $log = $this->queued();
        $this->work();
        $this->assertSame('sent', $log->fresh()->status);
        $this->assertSame('Exact saved body', trim(strip_tags($log->fresh()->payload['rendered_html'])));
        $this->assertGreaterThanOrEqual(3, EventMailAttemptHistory::where('log_id', $log->id)->count());
    }
    public function test_pre_render_failure_and_stall_have_durable_initiator_alerts(): void
    {
        $log = $this->queued(true);
        $this->work(true);
        $this->assertSame('failed', $log->fresh()->status);
        $this->assertDatabaseHas('event_mail_issues', ['log_id' => $log->id, 'user_id' => $log->payload['created_by'], 'attempt_number' => 1, 'kind' => 'failed']);
        $log->refresh()->update(['status' => 'queued']);
        $log->forceFill(['updated_at' => now()->subHours(2)])->save();
        $this->artisan('mail:record-event-issues')->assertSuccessful();
        $this->assertDatabaseHas('event_mail_issues', ['log_id' => $log->id, 'attempt_number' => 2, 'kind' => 'stalled']);
    }
    public function test_retry_retains_failed_snapshot_and_second_failure_alerts_retry_actor(): void
    {
        $actor = User::factory()->create();
        $retry = User::factory()->create();
        $event = Event::factory()->create();
        $log = BulkEmailLog::create(['mail_type' => 'team_email', 'recipient_email' => 'person@example.test', 'status' => 'failed', 'failed_at' => now(), 'error_message' => 'First failure', 'payload' => ['event_id' => $event->id, 'created_by' => $actor->id, 'body' => 'Original exact body']]);
        EventMailIssue::where('log_id', $log->id)->update(['read_at' => now()]);
        $log->update(['status' => 'queued', 'failed_at' => null, 'error_message' => null, 'payload' => [...$log->payload, 'retry_actor_id' => $retry->id]]);
        $log->markAsFailed('Second failure');
        $this->assertSame(2, $log->attempt_number);
        $this->assertDatabaseHas('event_mail_issues', ['log_id' => $log->id, 'user_id' => $retry->id, 'attempt_number' => 2, 'read_at' => null]);
        $history = EventMailAttemptHistory::where('log_id', $log->id)->where('attempt_number', 1)->where('status', 'failed')->firstOrFail();
        $this->assertSame('Original exact body', $history->snapshot['payload']['body']);
        $this->assertNotNull($history->snapshot['failed_at']);
        $this->expectException(\LogicException::class);
        $history->update(['status' => 'sent']);
    }
    public function test_timeout_is_uncertain_but_explicit_smtp_rejection_is_failed(): void
    {
        $log = BulkEmailLog::create(['mail_type' => 'team_email', 'recipient_email' => 'person@example.test', 'status' => 'sending']);
        app(MailFailureOutcome::class)->record($log, new \RuntimeException('Disconnected after DATA'), true);
        $this->assertSame('acceptance_unknown', $log->status);
        app(MailFailureOutcome::class)->record($log, new \Symfony\Component\Mailer\Exception\UnexpectedResponseException('Rejected', 550), true);
        $this->assertSame('failed', $log->status);
    }
    public function test_pre_render_native_retry_keeps_first_failure_and_succeeds_on_second_attempt(): void
    {
        $log = $this->queued(true);
        $this->work(true);
        $row = DB::table('jobs')->sole();
        $payload = json_decode($row->payload, true);
        $command = unserialize($payload['data']['command']);
        $command->mailable->broken = false;
        $payload['data']['command'] = serialize($command);
        DB::table('jobs')->where('id', $row->id)->update(['payload' => json_encode($payload), 'available_at' => now()->timestamp]);
        $this->work();
        $this->assertSame('sent', $log->fresh()->status);
        $this->assertSame(2, (int) $log->fresh()->attempt_number);
        $this->assertTrue(EventMailAttemptHistory::where('log_id', $log->id)->where('attempt_number', 1)->where('status', 'failed')->exists());
    }
    public function test_queued_team_transaction_is_held_for_review_and_never_reported_sent(): void
    {
        $log = $this->queued();
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Teams', 'type' => 2, 'code' => 'lifecycle-team']);
        Event::findOrFail($log->payload['event_id'])->update(['eventType' => $type]);
        $this->work();
        $this->assertSame('skipped', $log->fresh()->status);
        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
        $this->assertDatabaseCount('event_communication_batches', 1);
    }
    public function test_actual_bulk_transport_disconnect_blocks_subsequent_send(): void
    {
        $log = BulkEmailLog::create(['mail_type' => 'team_email', 'recipient_email' => 'person@example.test', 'status' => 'queued', 'payload' => ['subject' => 'Approved', 'body' => 'Exact body']]);
        $this->mock(\App\Services\MailAccountManager::class)->shouldReceive('getMailer')->once()->andReturn('array');
        Mail::shouldReceive('mailer')->once()->with('array')->andReturnSelf();
        Mail::shouldReceive('to')->once()->with('person@example.test')->andReturnSelf();
        Mail::shouldReceive('sendNow')->once()->andThrow(new \RuntimeException('Connection lost after DATA'));
        $job = new \App\Jobs\SendBulkEmailJob($log->id, true);
        $job->handle();
        $this->assertSame('acceptance_unknown', $log->fresh()->status);
        $job->handle();
    }
    public function test_untracked_job_hooks_do_not_query_mail_tables(): void
    {
        $job = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
        $job->shouldReceive('payload')->andReturn([]);
        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });
        app(\App\Services\OutboundMailHistory::class)->processing(new \Illuminate\Queue\Events\JobProcessing('database', $job));
        app(\App\Services\OutboundMailHistory::class)->processed(new \Illuminate\Queue\Events\JobProcessed('database', $job));
        $this->assertSame([], $queries);
    }
    public function test_plaintext_queue_snapshot_is_visible_in_authorized_event_detail(): void
    {
        $log = $this->queued(false, true);
        $this->work();
        $this->assertSame('Exact queued plaintext', $log->fresh()->payload['rendered_text']);
        $actor = User::findOrFail($log->payload['created_by']);
        \Spatie\Permission\Models\Role::findOrCreate('admin', 'web');
        $actor->assignRole('admin');
        DB::table('event_admins')->insert(['event_id'=>$log->payload['event_id'],'user_id'=>$actor->id]);
        $this->actingAs($actor)->get(route('backend.event-mail-log.show',[$log->payload['event_id'],$log]))->assertOk()->assertSee('Exact queued plaintext');
    }

    public function test_attempt_snapshot_storage_failure_does_not_retry_a_successful_send(): void
    {
        $log = $this->queued();
        EventMailAttemptHistory::creating(fn () => throw new \RuntimeException('Snapshot storage unavailable'));
        $this->work();
        $this->assertSame('sent', $log->fresh()->status);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertCount(1, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_recovery_queue_attribution_is_metadata_only_and_rejects_mixed_events(): void
    {
        config(['queue.default'=>'database','queue.connections.database.after_commit'=>false]);
        $actor = User::factory()->create();
        $payer = User::factory()->create();
        $category = \App\Models\CategoryEvent::factory()->create();
        $order = \App\Models\RegistrationOrder::create(['user_id'=>$payer->id]);
        DB::table('registration_order_items')->insert(['order_id'=>$order->id,'category_event_id'=>$category->id]);
        $recovery = \App\Models\RegistrationPaymentRecovery::create([
            'registration_order_id'=>$order->id,'user_id'=>$payer->id,'amount_due'=>'100.00',
            'before_state'=>[],'before_state_checksum'=>str_repeat('a',64),'preview_state_hash'=>str_repeat('b',64),
            'mail_snapshot'=>['private_bank'=>'NEVER_LOG_THIS'],'mail_snapshot_checksum'=>str_repeat('c',64),
            'link_expires_at'=>now()->addDay(),'prepared_by'=>$actor->id,'prepared_at'=>now(),'mail_queued_by'=>$actor->id,
        ]);
        Queue::connection('database')->push(new \App\Jobs\SendRecoveryPaymentMailJob($recovery->id));
        $log = BulkEmailLog::sole();
        $this->assertSame((int)$category->event_id,$log->payload['event_id']);
        $this->assertSame($actor->id,$log->payload['created_by']);
        $this->assertTrue($log->payload['financial_metadata_only']);
        $this->assertStringNotContainsString('NEVER_LOG_THIS',json_encode(EventMailAttemptHistory::where('log_id',$log->id)->get()->toArray()));
        $otherCategory = \App\Models\CategoryEvent::factory()->create();
        DB::table('registration_order_items')->insert(['order_id'=>$order->id,'category_event_id'=>$otherCategory->id]);
        Queue::connection('database')->push(new \App\Jobs\SendRecoveryPaymentMailJob($recovery->id));
        $this->assertDatabaseCount('bulk_email_logs',1);
    }

    public function test_duplicate_worker_claim_blocks_a_second_execution(): void
    {
        $log = $this->queued();
        $log->update(['status' => 'sending']);
        $this->work();
        $this->assertSame('sending', $log->fresh()->status);
        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
    }
    public function test_middleware_release_restores_queue_without_new_attempt_then_sends(): void
    {
        $log = $this->queued();
        $row = DB::table('jobs')->sole();
        $payload = json_decode($row->payload, true);
        $command = unserialize($payload['data']['command']);
        $command->through([new ReleaseOnce()]);
        $payload['data']['command'] = serialize($command);
        DB::table('jobs')->where('id', $row->id)->update(['payload' => json_encode($payload)]);
        $this->work();
        $this->assertSame('queued', $log->fresh()->status);
        $this->assertSame(1, (int) $log->fresh()->attempt_number);
        $this->work();
        $this->assertSame('sent', $log->fresh()->status);
    }
    public function test_terminal_multi_recipient_queue_replay_never_sends_again(): void
    {
        $log = $this->queued();
        $log->update(['status' => 'acceptance_unknown']);
        $second = BulkEmailLog::create(['mail_type' => 'system_mail', 'recipient_email' => 'second@example.test', 'status' => 'sending', 'payload' => $log->payload]);
        $row = DB::table('jobs')->sole();
        $payload = json_decode($row->payload, true);
        $payload['outbound_mail_history_ids'][] = $second->id;
        DB::table('jobs')->where('id', $row->id)->update(['payload' => json_encode($payload)]);
        $this->work();
        $this->assertSame('acceptance_unknown', $second->fresh()->status);
        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
    }
}
class LifecycleMail extends Mailable
{
    use SerializesModels;
    public function __construct(public Event $event, public bool $broken = false, public bool $plaintext = false)
    {
    }
    public function build(): self
    {
        if ($this->broken) {
            throw new \RuntimeException('Render failed');
        }
        if ($this->plaintext) return $this->subject('Exact text subject')->view(['raw'=>'Exact queued plaintext']);
        return $this->subject('Exact subject')->html('Exact saved body');
    }
}
class ReleaseOnce
{
    public function handle($job, $next): void
    {
        if ($job->job->attempts() === 1) {
            $job->release(0);
            return;
        }
        $next($job);
    }
}
