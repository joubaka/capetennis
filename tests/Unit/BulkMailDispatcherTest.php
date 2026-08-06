<?php

namespace Tests\Unit;

use App\Jobs\SendBulkEmailJob;
use App\Models\Announcement;
use App\Models\BulkEmailLog;
use App\Services\BulkMailDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BulkMailDispatcherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }
    public function test_it_dispatches_emails_to_valid_recipients()
    {
        $dispatcher = new BulkMailDispatcher();

        $recipients = [
            'user1@example.com',
            'user2@example.com',
            'user3@example.com',
        ];

        $stats = $dispatcher->dispatch(
            mailType: 'test_email',
            related: null,
            recipients: $recipients,
            payload: ['message' => 'test']
        );

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(3, $stats['queued']);
        $this->assertEquals(0, $stats['skipped']);
        $this->assertEquals(0, $stats['invalid']);

        // Assert jobs were dispatched
        Queue::assertPushed(SendBulkEmailJob::class, 3);

        // Assert log records were created
        $this->assertDatabaseCount('bulk_email_logs', 3);
        $this->assertDatabaseHas('bulk_email_logs', [
            'recipient_email' => 'user1@example.com',
            'mail_type' => 'test_email',
            'status' => 'queued',
        ]);
    }
    public function test_it_deduplicates_recipients_within_same_dispatch()
    {
        $dispatcher = new BulkMailDispatcher();

        $recipients = [
            'user1@example.com',
            'USER1@example.com', // Duplicate (different case)
            'user2@example.com',
            'user2@example.com', // Duplicate (exact)
        ];

        $stats = $dispatcher->dispatch(
            mailType: 'test_email',
            related: null,
            recipients: $recipients,
            payload: []
        );

        $this->assertEquals(2, $stats['total']); // Only unique emails
        $this->assertEquals(2, $stats['queued']);
        Queue::assertPushed(SendBulkEmailJob::class, 2);
    }
    public function test_it_skips_invalid_email_addresses()
    {
        $dispatcher = new BulkMailDispatcher();

        $recipients = [
            'valid@example.com',
            '', // Empty - will be filtered
            null, // Null - will be filtered
            'another@example.com',
        ];

        $stats = $dispatcher->dispatch(
            mailType: 'test_email',
            related: null,
            recipients: $recipients,
            payload: []
        );

        // After normalization: valid@example.com, another@example.com
        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(2, $stats['queued']);
        Queue::assertPushed(SendBulkEmailJob::class, 2);
    }
    public function test_it_prevents_duplicate_sends_for_same_mail_type_and_related_record()
    {
        $announcement = Announcement::create([
            'title' => 'Test',
            'message' => 'Test message',
            'event_id' => 1,
        ]);
        $dispatcher = new BulkMailDispatcher();

        // First dispatch
        $stats1 = $dispatcher->dispatch(
            mailType: 'event_announcement',
            related: $announcement,
            recipients: ['user@example.com'],
            payload: [],
            allowDuplicates: false
        );

        $this->assertEquals(1, $stats1['queued']);

        // Second dispatch - should be skipped
        $stats2 = $dispatcher->dispatch(
            mailType: 'event_announcement',
            related: $announcement,
            recipients: ['user@example.com'],
            payload: [],
            allowDuplicates: false
        );

        $this->assertEquals(0, $stats2['queued']);
        $this->assertEquals(1, $stats2['duplicate']);
        $this->assertEquals(1, $stats2['skipped']);

        // Only one job should be dispatched total
        Queue::assertPushed(SendBulkEmailJob::class, 1);

        // Should have 2 log records (1 queued, 1 skipped)
        $this->assertDatabaseCount('bulk_email_logs', 2);
        $this->assertDatabaseHas('bulk_email_logs', [
            'recipient_email' => 'user@example.com',
            'status' => 'queued',
        ]);
        $this->assertDatabaseHas('bulk_email_logs', [
            'recipient_email' => 'user@example.com',
            'status' => 'skipped',
        ]);
    }
    public function test_it_allows_duplicates_when_flag_is_true()
    {
        $dispatcher = new BulkMailDispatcher();

        // First dispatch
        $dispatcher->dispatch(
            mailType: 'test_email',
            related: null,
            recipients: ['user@example.com'],
            payload: [],
            allowDuplicates: false
        );

        // Second dispatch with allowDuplicates = true
        $stats = $dispatcher->dispatch(
            mailType: 'test_email',
            related: null,
            recipients: ['user@example.com'],
            payload: [],
            allowDuplicates: true
        );

        $this->assertEquals(1, $stats['queued']);
        $this->assertEquals(0, $stats['duplicate']);

        Queue::assertPushed(SendBulkEmailJob::class, 2);
    }
    public function test_it_handles_different_recipient_formats()
    {
        $dispatcher = new BulkMailDispatcher();

        $recipients = [
            'string@example.com', // String
            ['email' => 'array@example.com', 'name' => 'Array User'], // Array
            (object)['email' => 'object@example.com', 'name' => 'Object User'], // Object
        ];

        $stats = $dispatcher->dispatch(
            mailType: 'test_email',
            related: null,
            recipients: $recipients,
            payload: []
        );

        $this->assertEquals(3, $stats['queued']);
        $this->assertDatabaseHas('bulk_email_logs', ['recipient_email' => 'string@example.com']);
        $this->assertDatabaseHas('bulk_email_logs', ['recipient_email' => 'array@example.com', 'recipient_name' => 'Array User']);
        $this->assertDatabaseHas('bulk_email_logs', ['recipient_email' => 'object@example.com', 'recipient_name' => 'Object User']);
    }
    public function test_it_resends_only_failed_emails()
    {
        $dispatcher = new BulkMailDispatcher();

        // Create some log records manually
        $failed1 = BulkEmailLog::create([
            'mail_type' => 'test_email',
            'recipient_email' => 'failed1@example.com',
            'status' => 'failed',
            'payload' => ['test' => 'data'],
            'failed_at' => now(),
        ]);

        $failed2 = BulkEmailLog::create([
            'mail_type' => 'test_email',
            'recipient_email' => 'failed2@example.com',
            'status' => 'failed',
            'payload' => ['test' => 'data'],
            'failed_at' => now(),
        ]);

        $sent = BulkEmailLog::create([
            'mail_type' => 'test_email',
            'recipient_email' => 'sent@example.com',
            'status' => 'sent',
            'payload' => ['test' => 'data'],
            'sent_at' => now(),
        ]);

        // Resend failed
        $stats = $dispatcher->resendFailed('test_email');

        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(2, $stats['queued']);

        // Failed records should be reset to queued
        $this->assertEquals('queued', $failed1->fresh()->status);
        $this->assertEquals('queued', $failed2->fresh()->status);
        $this->assertEquals('sent', $sent->fresh()->status); // Should remain sent

        Queue::assertPushed(SendBulkEmailJob::class, 2);
    }
}
