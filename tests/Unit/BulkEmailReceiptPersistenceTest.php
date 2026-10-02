<?php

namespace Tests\Unit;

use App\Jobs\SendBulkEmailJob;
use App\Models\BulkEmailLog;
use App\Services\MailAccountManager;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Mail\SentMessage;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class BulkEmailReceiptPersistenceTest extends TestCase
{
    public function test_receipt_storage_error_after_transport_success_never_authorizes_a_resend(): void
    {
        config(['database.default' => 'evidence', 'database.connections.evidence' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        Schema::create('bulk_email_logs', function (Blueprint $table) {
            $table->id();
            foreach (['mail_type', 'recipient_email', 'status'] as $name) $table->string($name);
            foreach (['transport_message_id', 'transport_name', 'evidence_status', 'error_message'] as $name) $table->string($name)->nullable();
            foreach (['accepted_at', 'sent_at', 'failed_at', 'skipped_at'] as $name) $table->timestamp($name)->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
        $log = BulkEmailLog::create(['mail_type' => 'team_email', 'recipient_email' => 'parent@example.test', 'status' => 'queued', 'payload' => ['subject' => 'Approved', 'body' => 'Approved body']]);
        $this->mock(MailAccountManager::class)->shouldReceive('getMailer')->once()->andReturn('array');
        $email = (new Email)->from('sender@example.test')->to('parent@example.test')->text('Approved body');
        $sent = new SentMessage(new SymfonySentMessage($email, Envelope::create($email)));
        Mail::shouldReceive('mailer')->once()->with('array')->andReturnSelf();
        Mail::shouldReceive('to')->once()->with('parent@example.test')->andReturnSelf();
        Mail::shouldReceive('sendNow')->once()->andReturn($sent);
        Mail::shouldReceive('getSymfonyTransport')->once()->andReturn(new ArrayTransport);
        DB::listen(function ($query) {
            if (str_starts_with($query->sql, 'update') && str_contains($query->sql, 'evidence_status')) {
                throw new \RuntimeException('Injected receipt persistence failure');
            }
        });
        $job = new SendBulkEmailJob($log->id, true);
        $job->handle();

        $this->assertSame('acceptance_unknown', $log->fresh()->status);
        $this->assertNotNull($log->fresh()->sent_at);
        $job->handle();
        $job->failed(new \RuntimeException('Worker callback'));
        $this->assertSame('acceptance_unknown', $log->fresh()->status);
    }
}
