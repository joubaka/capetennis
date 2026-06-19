<?php

namespace Tests\Feature;

use App\Jobs\SendBulkEmailJob;
use App\Models\Announcement;
use App\Models\BulkEmailLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class EventAnnouncementEmailTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function successful_job_updates_log_to_sent()
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

    /** @test */
    public function failed_job_updates_log_to_failed()
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

    /** @test */
    public function log_can_be_marked_as_skipped()
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

    /** @test */
    public function bulk_email_log_scopes_work_correctly()
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
}
