<?php

namespace Tests\Unit;

use App\Jobs\SendBulkEmailJob;
use App\Jobs\SendMastersInvitationEmailJob;
use App\Mail\MastersInvitationMail;
use App\Models\BulkEmailLog;
use App\Models\MastersInvitation;
use App\Services\MailAccountManager;
use App\Services\Masters\RetryMastersInvitationEmails;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Queue\Jobs\FakeJob;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Worker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

class MastersInvitationEmailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Never migrate/clear the imported production snapshot or a shared
        // testing database. Each test has its own in-memory fixture schema.
        config([
            'database.default' => 'masters_mail_test',
            'database.connections.masters_mail_test' => ['driver' => 'sqlite', 'database' => ':memory:'],
            'mail.default' => 'array',
            'mail.mailers.array' => ['transport' => 'array'],
            'mail.from.address' => 'noreply@example.test',
            'cache.default' => 'array',
            'mail.bulk_mail.rate_per_second' => 14,
        ]);
        DB::purge('masters_mail_test');
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key');
            $table->text('value')->nullable();
        });
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('masters_invitation_batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->dateTime('response_deadline')->nullable();
            $table->dateTime('replacement_payment_deadline')->nullable();
            $table->timestamps();
        });
        Schema::create('masters_invitations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batch_id');
            $table->unsignedBigInteger('player_id')->nullable();
            $table->unsignedBigInteger('category_event_id')->nullable();
            $table->string('status');
            $table->dateTime('replacement_sent_at')->nullable();
            $table->timestamps();
        });
        Schema::create('bulk_email_logs', function (Blueprint $table) {
            $table->id();
            $table->string('mail_type');
            $table->string('related_type')->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('recipient_email');
            $table->string('status');
            $table->text('payload');
            $table->text('error_message')->nullable();
            foreach (['queued_at', 'sent_at', 'failed_at', 'skipped_at'] as $column) {
                $table->dateTime($column)->nullable();
            }
            $table->timestamps();
        });
        foreach ([1, 2] as $id) {
            DB::table('events')->insert(['id' => $id, 'name' => 'Masters '.$id]);
            DB::table('masters_invitation_batches')->insert([
                'id' => $id, 'event_id' => $id,
                'response_deadline' => now()->addDays(2),
                'replacement_payment_deadline' => now()->addDays(3),
            ]);
        }
        $this->mock(MailAccountManager::class)->shouldReceive('getMailer')->andReturn('array');
        Queue::fake();
    }

    protected function tearDown(): void
    {
        DB::purge('masters_mail_test');
        parent::tearDown();
    }

    private function email(int $eventId = 1, string $state = 'invited', string $kind = 'invitation'): BulkEmailLog
    {
        $invitation = MastersInvitation::create(['batch_id' => $eventId, 'status' => $state]);

        return BulkEmailLog::create([
            'mail_type' => 'masters_invitation', 'related_type' => MastersInvitation::class,
            'related_id' => $invitation->id, 'recipient_email' => 'player@example.test',
            'status' => 'queued', 'payload' => ['invitation_id' => $invitation->id, 'kind' => $kind],
            'queued_at' => now(),
        ]);
    }

    public function test_it_sends_once_inside_the_job_and_only_then_records_success(): void
    {
        $log = $this->email();
        $this->app['events']->listen(MessageSending::class, function () use ($log) {
            $this->assertSame('queued', $log->fresh()->status);
            $this->assertNull($log->fresh()->sent_at);
        });
        $job = new SendMastersInvitationEmailJob($log->id, 1);
        $job->handle();
        $job->handle();
        $job->failed(new RuntimeException('Late duplicate failure'));

        $this->assertCount(1, Mail::mailer('array')->getSymfonyTransport()->messages());
        Queue::assertNothingPushed();
        $this->assertSame('sent', $log->fresh()->status);
        $this->assertNotNull($log->fresh()->sent_at);
        $this->assertNull($log->fresh()->error_message);
    }

    public function test_legacy_masters_bulk_jobs_no_longer_queue_the_mailable_twice(): void
    {
        $log = $this->email();
        (new SendBulkEmailJob($log->id))->handle();

        $this->assertCount(1, Mail::mailer('array')->getSymfonyTransport()->messages());
        Queue::assertNothingPushed();
        $this->assertNotInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class,
            new MastersInvitationMail(MastersInvitation::find($log->related_id)));
    }

    public function test_actual_send_errors_are_retained_without_prematurely_marking_failed(): void
    {
        $log = $this->email();
        $this->app['events']->listen(MessageSending::class, fn () => throw new RuntimeException('Transport unavailable'));
        $job = new SendMastersInvitationEmailJob($log->id, 1);
        try {
            $job->handle();
            $this->fail('Sending should throw.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Transport unavailable', $exception->getMessage());
        }
        $this->assertSame('queued', $log->fresh()->status);
        $this->assertSame('Transport unavailable', $log->fresh()->error_message);
        $this->assertNull($log->fresh()->sent_at);
        $job->failed(new MaxAttemptsExceededException('Deadline exceeded'));
        $this->assertSame('failed', $log->fresh()->status);
        $this->assertSame('Transport unavailable', $log->fresh()->error_message);
        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_cancelled_sends_are_not_recorded_as_sent(): void
    {
        $log = $this->email();
        $this->app['events']->listen(MessageSending::class, fn () => false);
        try {
            (new SendMastersInvitationEmailJob($log->id, 1))->handle();
            $this->fail('A cancelled send must not succeed.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('cancelled', $exception->getMessage());
        }
        $this->assertSame('queued', $log->fresh()->status);
        $this->assertNull($log->fresh()->sent_at);
    }

    public function test_jobs_cannot_send_or_change_another_events_log_or_an_ordinary_email(): void
    {
        $other = $this->email(2);
        $ordinary = $this->email();
        $ordinary->update(['mail_type' => 'event_announcement']);
        foreach ([$other, $ordinary] as $log) {
            $job = new SendMastersInvitationEmailJob($log->id, 1);
            $job->handle();
            $job->failed(new RuntimeException('Wrong target'));
            $this->assertSame('queued', $log->fresh()->status);
            $this->assertNull($log->fresh()->error_message);
        }
        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_stale_invitation_states_and_expired_deadlines_are_skipped(): void
    {
        foreach (['accepted_pending_payment', 'paid_confirmed', 'declined', 'admin_removed', 'withdrawn'] as $state) {
            $log = $this->email(state: $state);
            (new SendMastersInvitationEmailJob($log->id, 1))->handle();
            $this->assertSame('skipped', $log->fresh()->status);
        }
        DB::table('masters_invitation_batches')->where('id', 1)->update(['response_deadline' => now()->subMinute()]);
        $log = $this->email();
        (new SendMastersInvitationEmailJob($log->id, 1))->handle();
        $this->assertSame('skipped', $log->fresh()->status);
        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_replacements_use_their_own_deadline_and_confirmations_still_send(): void
    {
        DB::table('masters_invitation_batches')->where('id', 1)->update(['response_deadline' => now()->subMinute()]);
        foreach ([$this->email(kind: 'replacement'), $this->email(state: 'paid_confirmed', kind: 'confirmed')] as $log) {
            (new SendMastersInvitationEmailJob($log->id, 1))->handle();
            $this->assertSame('sent', $log->fresh()->status);
        }
        $this->assertCount(2, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_payload_invitation_mismatch_is_not_sent(): void
    {
        $log = $this->email();
        $log->update(['payload' => ['invitation_id' => 999, 'kind' => 'invitation']]);
        (new SendMastersInvitationEmailJob($log->id, 1))->handle();
        $this->assertSame('skipped', $log->fresh()->status);
        $this->assertCount(0, Mail::mailer('array')->getSymfonyTransport()->messages());
    }

    public function test_rate_limited_job_survives_more_than_three_reservations_but_has_a_fixed_deadline(): void
    {
        $this->travelTo(now()->startOfSecond());
        $log = $this->email();
        $job = new SendMastersInvitationEmailJob($log->id, 1);
        $deadline = $job->retryUntil()->getTimestamp();
        $payload = $this->payload($job);
        $this->assertSame(0, $payload['maxTries']);
        $this->assertSame(3, $payload['maxExceptions']);
        $this->assertSame($deadline, $payload['retryUntil']);
        $this->assertTrue($job->afterCommit);

        $worker = (new ReflectionClass(Worker::class))->newInstanceWithoutConstructor();
        $guard = (new ReflectionClass(Worker::class))->getMethod('markJobAsFailedIfAlreadyExceedsMaxAttempts');
        $reservation = $this->reservation($payload);
        $limiter = app(\Illuminate\Cache\RateLimiter::class);
        $limiter->for('outbound-mail', fn () => \Illuminate\Cache\RateLimiting\Limit::perSecond(14));
        $middleware = collect($job->middleware())->first(fn ($m) => $m instanceof RateLimited);
        $this->assertNotNull($middleware);
        for ($round = 1; $round <= 4; $round++) {
            $this->travel(5)->seconds();
            for ($i = 0; $i < 14; $i++) {
                $middleware->handle(new FakeJob, fn () => true);
            }
            $reservation->attempts = $round;
            $guard->invoke($worker, 'database', $reservation, 3);
            $job->setJob($reservation);
            $middleware->handle($job, fn () => $this->fail('Throttled job must not send.'));
            $this->assertTrue($reservation->isReleased());
            $this->assertFalse($reservation->hasFailed());
        }
        $this->assertSame($deadline, unserialize($payload['data']['command'])->retryUntil()->getTimestamp());
        $this->assertSame('queued', $log->fresh()->status);
        $this->travel(5)->seconds();
        $middleware->handle($job, fn ($j) => $j->handle());
        $this->assertSame('sent', $log->fresh()->status);

        $this->travelTo(now()->setTimestamp($deadline + 1));
        $this->expectException(MaxAttemptsExceededException::class);
        $guard->invoke($worker, 'database', $reservation, 3);
    }

    public function test_three_real_exceptions_still_exhaust_the_exception_budget(): void
    {
        $job = new SendMastersInvitationEmailJob(1, 1);
        $payload = $this->payload($job);
        $payload['uuid'] = 'masters-exception-test';
        $reservation = $this->reservation($payload);
        $ref = new ReflectionClass(Worker::class);
        $worker = $ref->newInstanceWithoutConstructor();
        $worker->setCache(app('cache')->store('array'));
        $guard = $ref->getMethod('markJobAsFailedIfWillExceedMaxExceptions');
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $guard->invoke($worker, 'database', $reservation, new RuntimeException('Transport failed'));
            $this->assertSame($attempt === 3, $reservation->hasFailed());
        }
    }

    private function reservation(array $payload): FakeJob
    {
        return new class($payload) extends FakeJob {
            public function __construct(private array $body) {}
            public function payload() { return $this->body; }
            public function resolveName() { return SendMastersInvitationEmailJob::class; }
        };
    }

    private function payload(SendMastersInvitationEmailJob $job): array
    {
        $queue = new \Illuminate\Queue\SyncQueue;
        $queue->setContainer($this->app);

        $method = (new ReflectionClass($queue))->getMethod('createPayload');

        return json_decode($method->invoke($queue, $job, 'default'), true);
    }

    public function test_announcement_jobs_continue_sending_directly_with_the_existing_policy(): void
    {
        $log = $this->email();
        $log->update([
            'mail_type' => 'event_announcement',
            'payload' => ['event_name' => 'Announcement comparison', 'title' => 'Update', 'message' => 'Test message'],
        ]);
        $job = new SendBulkEmailJob($log->id);
        $job->handle();

        $this->assertSame(3, $job->tries);
        $this->assertSame('sent', $log->fresh()->status);
        $this->assertCount(1, Mail::mailer('array')->getSymfonyTransport()->messages());
        Queue::assertNothingPushed();
    }

    public function test_recovery_is_preview_first_event_scoped_and_idempotent(): void
    {
        $eligible = $this->email();
        $accepted = $this->email(state: 'accepted_pending_payment');
        $paid = $this->email(state: 'paid_confirmed');
        $other = $this->email(2);
        $ordinary = $this->email();
        $ordinary->update(['mail_type' => 'event_announcement']);
        foreach ([$eligible, $accepted, $paid, $other, $ordinary] as $log) {
            $log->markAsFailed('Historical error');
        }
        $service = app(RetryMastersInvitationEmails::class);
        $this->assertSame(['failed' => 3, 'eligible' => 1, 'queued' => 0, 'skipped' => 2], $service->run(1));
        Queue::assertNothingPushed();
        $this->assertSame('failed', $eligible->fresh()->status);

        $this->assertSame(['failed' => 3, 'eligible' => 1, 'queued' => 1, 'skipped' => 2], $service->run(1, true));
        $this->assertSame(0, $service->run(1, true)['queued']);
        Queue::assertPushed(SendMastersInvitationEmailJob::class, 1);
        Queue::assertPushed(SendMastersInvitationEmailJob::class,
            fn ($job) => $job->logId === $eligible->id && $job->eventId === 1 && $job->tries === 0);
        $this->assertSame('queued', $eligible->fresh()->status);
        foreach ([$accepted, $paid, $other, $ordinary] as $log) {
            $this->assertSame('failed', $log->fresh()->status);
        }
        $this->assertDatabaseCount('bulk_email_logs', 5);
    }

    public function test_recovery_suppresses_duplicate_logs_and_expired_invitations(): void
    {
        $log = $this->email();
        $log->markAsFailed('Historical error');
        $duplicate = $log->replicate();
        $duplicate->save();
        $service = app(RetryMastersInvitationEmails::class);
        $this->assertSame(1, $service->run(1)['eligible']);
        $this->assertSame(1, $service->run(1, true)['queued']);
        $this->assertSame(0, $service->run(1, true)['queued']);
        $expired = $this->email();
        $expired->markAsFailed('Historical error');
        DB::table('masters_invitation_batches')->where('id', 1)->update(['response_deadline' => now()->subMinute()]);
        $this->assertSame(0, $service->run(1)['eligible']);
        $this->assertSame('failed', $expired->fresh()->status);
        Queue::assertPushed(SendMastersInvitationEmailJob::class, 1);
    }

    public function test_recovery_command_does_not_queue_without_explicit_option(): void
    {
        $log = $this->email();
        $log->markAsFailed('Historical error');
        $this->artisan('masters:retry-failed-emails', ['event' => 1])->assertSuccessful();
        Queue::assertNothingPushed();
        $this->assertSame('failed', $log->fresh()->status);
        $this->artisan('masters:retry-failed-emails', ['event' => 999])->assertFailed();
    }

    public function test_masters_dispatch_waits_for_commit_and_rollback_leaves_no_job(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue');
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
        config([
            'queue.default' => 'database',
            'queue.connections.database.connection' => 'masters_mail_test',
        ]);
        $manager = new \Illuminate\Queue\QueueManager($this->app);
        $manager->addConnector('database', fn () => new \Illuminate\Queue\Connectors\DatabaseConnector($this->app['db']));
        Queue::swap($manager);
        $log = $this->email();
        DB::transaction(function () use ($log) {
            SendMastersInvitationEmailJob::dispatch($log->id, 1);
            $this->assertSame(0, DB::table('jobs')->count());
        });
        $this->assertSame(1, DB::table('jobs')->count());

        try {
            DB::transaction(function () use ($log) {
                SendMastersInvitationEmailJob::dispatch($log->id, 1);
                throw new RuntimeException('Rollback fixture');
            });
        } catch (RuntimeException $exception) {
            $this->assertSame('Rollback fixture', $exception->getMessage());
        }
        $this->assertSame(1, DB::table('jobs')->count());
        $payload = json_decode(DB::table('jobs')->value('payload'), true);
        $this->assertSame(SendMastersInvitationEmailJob::class, $payload['displayName']);
        $this->assertSame(3, $payload['maxExceptions']);
        $this->assertNotNull($payload['retryUntil']);
    }
}
