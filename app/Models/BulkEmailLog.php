<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Mail\SentMessage;
use Illuminate\Mail\Transport\SesTransport;
use Illuminate\Mail\Transport\SesV2Transport;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;

class BulkEmailLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'mail_type',
        'attempt_number',
        'retry_actor_id',
        'deduplication_key',
        'related_type',
        'related_id',
        'recipient_email',
        'recipient_name',
        'status',
        'error_message',
        'payload',
        'queued_at',
        'sent_at',
        'failed_at',
        'skipped_at',
        'accepted_at',
        'transport_message_id',
        'transport_name',
        'evidence_status',
    ];

    protected $casts = [
        'payload' => 'array',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'skipped_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (self $log): void {
            if ($log->isDirty('status') && $log->status === 'queued' && $log->getOriginal('status') === 'failed') {
                app(\App\Services\EventMailAttemptRecorder::class)->recordOriginal($log);
                $log->attempt_number = max(1, (int) $log->getOriginal('attempt_number')) + 1;
            }
        });
        static::saved(function (self $log): void {
            if ($log->wasRecentlyCreated || $log->wasChanged(['status', 'payload', 'accepted_at'])) {
                app(\App\Services\EventMailAttemptRecorder::class)->record($log);
            }
            if (($log->wasRecentlyCreated || $log->wasChanged('status')) && in_array($log->status, ['failed', 'skipped', 'acceptance_unknown'], true)) {
                app(\App\Services\EventMailLogService::class)->recordIssue($log);
            }
        });
    }

    /**
     * Scope to get only queued emails.
     */
    public function scopeQueued($query)
    {
        return $query->where('status', 'queued');
    }

    /**
     * Scope to get only sent emails.
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope to get only failed emails.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to get only skipped emails.
     */
    public function scopeSkipped($query)
    {
        return $query->where('status', 'skipped');
    }

    /**
     * Mark as sent.
     */
    public function markAsSent(): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    /** Record only evidence returned by the actual transport, never delivery or reading. */
    public function recordTransportResult(?SentMessage $sent, object $transport, string $mailer): void
    {
        if ($sent === null) {
            throw new \RuntimeException('Email was not accepted by the mail transport.');
        }

        $serverTransport = $transport instanceof SmtpTransport
            || $transport instanceof SesTransport
            || $transport instanceof SesV2Transport;
        $sandbox = $transport instanceof SmtpTransport
            && str_ends_with(strtolower($transport->getStream()->getHost()), '.mailtrap.io');
        $messageId = $sent->getOriginalMessage()->getHeaders()->get('X-SES-Message-ID')?->getBodyAsString()
            ?? $sent->getMessageId();

        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'accepted_at' => $serverTransport ? now() : null,
            'transport_message_id' => mb_substr($messageId, 0, 255),
            'transport_name' => mb_substr($mailer, 0, 100),
            'evidence_status' => $sandbox ? 'sandbox_accepted' : ($serverTransport ? 'server_accepted' : 'unverified'),
            'failed_at' => null,
            'error_message' => null,
        ]);
    }

    public function getDeliveryStatusLabelAttribute(): string
    {
        return match (true) {
            $this->status === 'sent' && $this->evidence_status === 'server_accepted' && $this->accepted_at !== null => 'Mail server accepted',
            $this->status === 'sent' && $this->evidence_status === 'sandbox_accepted' => 'Sandbox accepted',
            $this->status === 'sent' => 'Sent — acceptance unverified',
            $this->status === 'sending' => 'Sending',
            $this->status === 'queued' && data_get($this->payload, 'queue_state') === 'prepared' => 'Queue submission unverified',
            $this->status === 'acceptance_unknown' => 'Acceptance unverified — check mail server',
            $this->status === 'failed' => 'Failed',
            $this->status === 'skipped' => 'Skipped',
            default => 'Queued',
        };
    }

    /** Aggregate a caller's authorised campaign query without loading recipient records. */
    public static function deliverySummary(Builder $query): array
    {
        $counts = ['total' => 0, 'queued' => 0, 'sending' => 0, 'server_accepted' => 0, 'sandbox_accepted' => 0, 'unverified' => 0, 'failed' => 0, 'skipped' => 0];
        $groups = (clone $query)->reorder()->selectRaw('status, evidence_status, accepted_at IS NOT NULL AS has_acceptance, COUNT(*) AS aggregate')
            ->groupBy('status', 'evidence_status')->groupByRaw('accepted_at IS NOT NULL')->get();
        foreach ($groups as $group) {
            $key = $group->status === 'sent'
                ? (in_array($group->evidence_status, ['server_accepted', 'sandbox_accepted'], true) && $group->has_acceptance ? $group->evidence_status : 'unverified')
                : (array_key_exists($group->status, $counts) ? $group->status : 'unverified');
            $counts[$key] += (int) $group->aggregate;
            $counts['total'] += (int) $group->aggregate;
        }
        $counts['all_server_accepted'] = $counts['total'] > 0 && $counts['server_accepted'] === $counts['total'];

        return $counts;
    }

    /**
     * Mark as failed.
     */
    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'failed_at' => now(),
            'error_message' => $error,
        ]);
    }

    /**
     * Mark as skipped.
     */
    public function markAsSkipped(string $reason): void
    {
        $this->update([
            'status' => 'skipped',
            'skipped_at' => now(),
            'error_message' => $reason,
        ]);
    }

    /**
     * Get the related model (polymorphic).
     */
    public function related()
    {
        return $this->morphTo('related');
    }
}
