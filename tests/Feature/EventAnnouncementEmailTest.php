<?php

namespace Tests\Feature;

use App\Jobs\SendBulkEmailJob;
use App\Models\Announcement;
use App\Models\BulkEmailLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Log, Queue};
use Tests\TestCase;

class EventAnnouncementEmailTest extends TestCase
{
    use RefreshDatabase;
    public function test_successful_job_updates_log_to_sent()
    {
        $log = BulkEmailLog::create([
            'mail_type' => 'event_announcement',
            'recipient_email' => 'test@example.com',
            'status' => 'queued',
            'payload' => [
                'event_name' => 'Test Event',
                'title' => 'Test',
                'message' => 'Test message',
            ],
            'queued_at' => now(),
        ]);

        $log->markAsSent();

        $this->assertEquals('sent', $log->fresh()->status);
        $this->assertNotNull($log->fresh()->sent_at);
    }
    public function test_failed_job_updates_log_to_failed()
    {
        $log = BulkEmailLog::create([
            'mail_type' => 'event_announcement',
            'recipient_email' => 'test@example.com',
            'status' => 'queued',
            'payload' => [
                'event_name' => 'Test Event',
                'title' => 'Test',
                'message' => 'Test message',
            ],
            'queued_at' => now(),
        ]);

        $log->markAsFailed('SMTP connection failed');

        $this->assertEquals('failed', $log->fresh()->status);
        $this->assertNotNull($log->fresh()->failed_at);
        $this->assertEquals('SMTP connection failed', $log->fresh()->error_message);
    }
    public function test_log_can_be_marked_as_skipped()
    {
        $log = BulkEmailLog::create([
            'mail_type' => 'event_announcement',
            'recipient_email' => 'test@example.com',
            'status' => 'queued',
            'payload' => [],
            'queued_at' => now(),
        ]);

        $log->markAsSkipped('Duplicate email');

        $this->assertEquals('skipped', $log->fresh()->status);
        $this->assertNotNull($log->fresh()->skipped_at);
        $this->assertEquals('Duplicate email', $log->fresh()->error_message);
    }
    public function test_bulk_email_log_scopes_work_correctly()
    {
        BulkEmailLog::create(['mail_type' => 'test', 'recipient_email' => 'queued@test.com', 'status' => 'queued']);
        BulkEmailLog::create(['mail_type' => 'test', 'recipient_email' => 'sent@test.com', 'status' => 'sent', 'sent_at' => now()]);
        BulkEmailLog::create(['mail_type' => 'test', 'recipient_email' => 'failed@test.com', 'status' => 'failed', 'failed_at' => now()]);
        BulkEmailLog::create(['mail_type' => 'test', 'recipient_email' => 'skipped@test.com', 'status' => 'skipped', 'skipped_at' => now()]);

        $this->assertEquals(1, BulkEmailLog::queued()->count());
        $this->assertEquals(1, BulkEmailLog::sent()->count());
        $this->assertEquals(1, BulkEmailLog::failed()->count());
        $this->assertEquals(1, BulkEmailLog::skipped()->count());
    }
    public function test_bulk_email_job_logs_only_a_keyed_recipient_reference(): void
    {
        $email = 'private-recipient@example.test';
        $log = BulkEmailLog::create(['mail_type'=>'event_announcement','recipient_email'=>$email,'status'=>'sent','sent_at'=>now()]);
        Log::spy();

        (new SendBulkEmailJob($log->id))->handle();

        Log::shouldHaveReceived('info')->once()->withArgs(function (string $message, array $context) use ($email): bool {
            return str_contains($message,'already processed')
                && ! array_key_exists('recipient',$context)
                && ! str_contains(json_encode($context),$email)
                && ($context['recipient_ref'] ?? '') !== substr(hash('sha256',$email),0,12)
                && preg_match('/^[a-f0-9]{12}$/',$context['recipient_ref'] ?? '') === 1;
        });
    }
    public function test_bulk_email_job_redacts_recipient_from_transport_failures(): void
    {
        $email = 'private-failure@example.test';
        $log = BulkEmailLog::create(['mail_type'=>'event_announcement','recipient_email'=>$email,'status'=>'queued','queued_at'=>now()]);
        $job = new class($log->id, true) extends SendBulkEmailJob {
            protected function buildMailable(BulkEmailLog $log, string $fromAddress)
            {
                throw new \RuntimeException('SMTP refused '.$log->recipient_email);
            }
        };
        Log::spy();

        $job->handle();

        $this->assertSame('SMTP refused [REDACTED_RECIPIENT]',$log->fresh()->error_message);
        Log::shouldHaveReceived('error')->once()->withArgs(fn(string $message,array $context):bool=>!str_contains(json_encode($context),$email));
    }
}
