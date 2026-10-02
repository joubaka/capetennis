<?php

namespace Tests\Unit;

use App\Models\BulkEmailLog;
use Illuminate\Mail\SentMessage;
use Illuminate\Mail\Transport\ArrayTransport;
use Illuminate\Mail\Transport\SesTransport;
use Aws\Ses\SesClient;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage as SymfonySentMessage;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Email;
use Tests\TestCase;

class BulkEmailDeliveryEvidenceTest extends TestCase
{
    private function log(): BulkEmailLog
    {
        return new class extends BulkEmailLog
        {
            public function update(array $attributes = [], array $options = [])
            {
                $this->fill($attributes);

                return true;
            }
        };
    }

    private function sent(): SentMessage
    {
        $email = (new Email)->from('sender@example.test')->to('recipient@example.test')->text('Approved message');

        return new SentMessage(new SymfonySentMessage($email, Envelope::create($email)));
    }

    public function test_smtp_acceptance_records_time_and_message_id_without_claiming_delivery(): void
    {
        $log = $this->log();
        $sent = $this->sent();
        $log->recordTransportResult($sent, new EsmtpTransport('mail.example.test'), 'smtp');

        $this->assertSame('sent', $log->status);
        $this->assertSame('server_accepted', $log->evidence_status);
        $this->assertSame($sent->getMessageId(), $log->transport_message_id);
        $this->assertNotNull($log->accepted_at);
        $this->assertSame('Mail server accepted', $log->delivery_status_label);
    }

    public function test_array_transport_cannot_claim_mail_server_acceptance(): void
    {
        $log = $this->log();
        $log->recordTransportResult($this->sent(), new ArrayTransport, 'array');

        $this->assertSame('unverified', $log->evidence_status);
        $this->assertNull($log->accepted_at);
        $this->assertSame('Sent — acceptance unverified', $log->delivery_status_label);
    }

    public function test_ses_provider_receipt_id_is_saved_instead_of_the_generated_mime_id(): void
    {
        $sent = $this->sent();
        $sent->getOriginalMessage()->getHeaders()->addTextHeader('X-SES-Message-ID', 'ses-provider-receipt');
        $log = $this->log();
        $log->recordTransportResult($sent, new SesTransport($this->createMock(SesClient::class)), 'ses');

        $this->assertSame('ses-provider-receipt', $log->transport_message_id);
        $this->assertSame('server_accepted', $log->evidence_status);
        $this->assertSame('Mail server accepted', $log->delivery_status_label);
    }

    public function test_mailtrap_acceptance_is_identified_as_sandbox_evidence(): void
    {
        $log = $this->log();
        $log->recordTransportResult($this->sent(), new EsmtpTransport('sandbox.smtp.mailtrap.io'), 'smtp');

        $this->assertSame('sandbox_accepted', $log->evidence_status);
        $this->assertSame('Sandbox accepted', $log->delivery_status_label);
    }

    public function test_suppressed_send_cannot_be_recorded_as_sent(): void
    {
        $log = $this->log();
        try {
            $log->recordTransportResult(null, new ArrayTransport, 'array');
            $this->fail('Suppressed sends must fail.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Email was not accepted by the mail transport.', $exception->getMessage());
            $this->assertNull($log->status);
            $this->assertNull($log->accepted_at);
        }
    }

    public function test_historical_sent_records_do_not_gain_acceptance_evidence(): void
    {
        $log = $this->log();
        $log->fill(['status' => 'sent', 'sent_at' => now()]);

        $this->assertSame('Sent — acceptance unverified', $log->delivery_status_label);
        $this->assertNull($log->accepted_at);
    }

    public function test_summary_is_scoped_and_requires_acceptance_for_every_recipient(): void
    {
        config(['database.connections.evidence' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        Schema::connection('evidence')->create('bulk_email_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('related_id');
            $table->string('status');
            $table->string('evidence_status')->nullable();
            $table->timestamp('accepted_at')->nullable();
        });
        $model = (new BulkEmailLog)->setConnection('evidence');
        $model->newQuery()->insert([
            ['related_id' => 1, 'status' => 'sent', 'evidence_status' => 'server_accepted', 'accepted_at' => now()],
            ['related_id' => 1, 'status' => 'sent', 'evidence_status' => null, 'accepted_at' => null],
            ['related_id' => 1, 'status' => 'queued', 'evidence_status' => null, 'accepted_at' => null],
            ['related_id' => 1, 'status' => 'failed', 'evidence_status' => null, 'accepted_at' => null],
            ['related_id' => 2, 'status' => 'sent', 'evidence_status' => 'server_accepted', 'accepted_at' => now()],
        ]);

        $summary = BulkEmailLog::deliverySummary($model->newQuery()->where('related_id', 1));
        $this->assertSame(4, $summary['total']);
        $this->assertSame(1, $summary['server_accepted']);
        $this->assertSame(1, $summary['unverified']);
        $this->assertSame(1, $summary['queued']);
        $this->assertSame(1, $summary['failed']);
        $this->assertFalse($summary['all_server_accepted']);
        $this->assertTrue(BulkEmailLog::deliverySummary($model->newQuery()->where('related_id', 2))['all_server_accepted']);
        $this->assertFalse(BulkEmailLog::deliverySummary($model->newQuery()->where('related_id', 3))['all_server_accepted']);
    }
}
