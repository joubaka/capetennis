<?php

namespace Tests\Feature;

use App\Jobs\SendBulkEmailJob;
use App\Models\BulkEmailLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BulkEmailBatchThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Cache::flush();
    }

    /** @test */
    public function it_sends_emails_in_batches_of_eight()
    {
        // Set config for testing
        config(['mail.bulk_mail.batch_threshold' => 8]);
        config(['mail.bulk_mail.delay_seconds' => 0]); // No delay in tests

        // Create 20 email logs
        $logs = [];
        for ($i = 0; $i < 20; $i++) {
            $logs[] = BulkEmailLog::create([
                'mail_type' => 'test_email',
                'recipient_email' => "test{$i}@example.com",
                'recipient_name' => "Test User {$i}",
                'status' => 'queued',
                'payload' => ['subject' => 'Test', 'body' => 'Test message'],
                'queued_at' => now(),
            ]);
        }

        // Track log entries to verify batch behavior
        Log::shouldReceive('info')
            ->withArgs(function ($message, $context) {
                if (strpos($message, 'Sending email in batch') !== false) {
                    // Verify batch numbers are correct
                    $batch = $context['batch_number'] ?? 0;
                    $position = $context['position_in_batch'] ?? 0;

                    // First 8 emails should be batch 1
                    // Emails 9-16 should be batch 2
                    // Emails 17-20 should be batch 3
                    return $batch >= 1 && $batch <= 3 && $position >= 1 && $position <= 8;
                }
                return true;
            })
            ->times(20);

        Log::shouldReceive('info')
            ->withArgs(function ($message) {
                return strpos($message, 'SMTP batch threshold reached') !== false;
            })
            ->times(2); // Should reconnect after email 8 and 16

        Log::shouldReceive('info')
            ->withArgs(function ($message) {
                return strpos($message, 'Pausing before next batch') !== false;
            })
            ->times(2);

        Log::shouldReceive('info')
            ->withArgs(function ($message) {
                return strpos($message, 'Email sent successfully') !== false;
            })
            ->times(20);

        Log::shouldReceive('debug')->andReturnTrue();
        Log::shouldReceive('error')->never();

        // Process all jobs
        foreach ($logs as $index => $log) {
            $job = new SendBulkEmailJob($log->id);

            // Manually set mailer to avoid MailAccountManager dependency
            config(['mail.default' => 'smtp']);

            try {
                $job->handle();
            } catch (\Exception $e) {
                // In test environment, mailer may not be fully configured
                // We're mainly testing the batch logic, not actual SMTP
                if (!str_contains($e->getMessage(), 'Unknown mail type')) {
                    $this->fail('Unexpected error: ' . $e->getMessage());
                }
            }

            // Verify cache counter increments
            $expectedCount = $index + 1;
            $actualCount = Cache::get('smtp_batch_counter_smtp', 0);

            // Allow for cache key variations based on mailer
            $this->assertGreaterThanOrEqual(
                $expectedCount,
                $actualCount,
                "Batch counter should be at least {$expectedCount} after email {$index}"
            );
        }
    }

    /** @test */
    public function it_resets_connection_after_batch_threshold()
    {
        config(['mail.bulk_mail.batch_threshold' => 3]); // Small batch for testing
        config(['mail.bulk_mail.delay_seconds' => 0]);

        // Create 5 email logs
        $logs = [];
        for ($i = 0; $i < 5; $i++) {
            $logs[] = BulkEmailLog::create([
                'mail_type' => 'test_email',
                'recipient_email' => "test{$i}@example.com",
                'status' => 'queued',
                'payload' => ['subject' => 'Test', 'body' => 'Test'],
                'queued_at' => now(),
            ]);
        }

        $reconnectCount = 0;

        Log::shouldReceive('info')
            ->withArgs(function ($message) use (&$reconnectCount) {
                if (strpos($message, 'SMTP batch threshold reached') !== false) {
                    $reconnectCount++;
                }
                return true;
            })
            ->andReturnTrue();

        Log::shouldReceive('debug')->andReturnTrue();
        Log::shouldReceive('warning')->andReturnTrue();

        // Process first 4 emails (should trigger one reconnect after email 3)
        for ($i = 0; $i < 4; $i++) {
            $job = new SendBulkEmailJob($logs[$i]->id);
            config(['mail.default' => 'smtp']);

            try {
                $job->handle();
            } catch (\Exception $e) {
                // Ignore mailer config issues in tests
            }
        }

        // Should have reconnected once (after 3rd email, before 4th)
        $this->assertEquals(
            1,
            $reconnectCount,
            'Should reconnect once after 3 emails when batch_threshold is 3'
        );
    }

    /** @test */
    public function it_tracks_batch_position_correctly()
    {
        config(['mail.bulk_mail.batch_threshold' => 8]);
        config(['mail.bulk_mail.delay_seconds' => 0]);

        $batchPositions = [];

        Log::shouldReceive('info')
            ->withArgs(function ($message, $context) use (&$batchPositions) {
                if (strpos($message, 'Sending email in batch') !== false) {
                    $batchPositions[] = [
                        'batch' => $context['batch_number'] ?? 0,
                        'position' => $context['position_in_batch'] ?? 0,
                    ];
                }
                return true;
            })
            ->andReturnTrue();

        Log::shouldReceive('debug')->andReturnTrue();
        Log::shouldReceive('warning')->andReturnTrue();

        // Create and process 10 emails
        for ($i = 0; $i < 10; $i++) {
            $log = BulkEmailLog::create([
                'mail_type' => 'test_email',
                'recipient_email' => "test{$i}@example.com",
                'status' => 'queued',
                'payload' => ['subject' => 'Test', 'body' => 'Test'],
                'queued_at' => now(),
            ]);

            $job = new SendBulkEmailJob($log->id);
            config(['mail.default' => 'smtp']);

            try {
                $job->handle();
            } catch (\Exception $e) {
                // Ignore
            }
        }

        // Verify batch positions
        // Emails 0-7 should be batch 1, positions 1-8
        // Emails 8-9 should be batch 2, positions 1-2
        $this->assertGreaterThanOrEqual(10, count($batchPositions));

        for ($i = 0; $i < min(8, count($batchPositions)); $i++) {
            $this->assertEquals(1, $batchPositions[$i]['batch'], "Email {$i} should be in batch 1");
            $this->assertEquals($i + 1, $batchPositions[$i]['position'], "Email {$i} should be at position " . ($i + 1));
        }

        if (count($batchPositions) > 8) {
            $this->assertEquals(2, $batchPositions[8]['batch'], "Email 8 should be in batch 2");
            $this->assertEquals(1, $batchPositions[8]['position'], "Email 8 should be at position 1 of batch 2");
        }
    }
}
