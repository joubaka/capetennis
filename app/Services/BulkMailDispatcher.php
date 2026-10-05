<?php

namespace App\Services;

use App\Jobs\SendBulkEmailJob;
use App\Models\BulkEmailLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class BulkMailDispatcher
{
    /**
     * Dispatch bulk emails with throttling and deduplication.
     *
     * @param string $mailType The type of mail (e.g., 'tournament_announcement')
     * @param mixed $related The related model (e.g., Announcement instance)
     * @param Collection|array $recipients Collection of recipients with email/name
     * @param array $payload Additional data needed to rebuild the email
     * @param bool $allowDuplicates Whether to allow duplicate sends (default: false)
     * @return array Statistics about the dispatch
     */
    public function dispatch(
        string $mailType,
        $related = null,
        $recipients = [],
        array $payload = [],
        bool $allowDuplicates = false
    ): array {
        $recipients = $this->normalizeRecipients($recipients);
        $stats = ['total' => $recipients->count(), 'queued' => 0, 'skipped' => 0, 'invalid' => 0, 'duplicate' => 0, 'failed' => 0];
        $seen = [];
        foreach ($recipients as $recipient) {
            $attributes = ['mail_type' => $mailType, 'related_type' => $related ? get_class($related) : null,
                'related_id' => $related?->id, 'recipient_email' => $recipient['email'], 'recipient_name' => $recipient['name'], 'payload' => $payload];
            if (! filter_var($recipient['email'], FILTER_VALIDATE_EMAIL)) {
                BulkEmailLog::create([...$attributes, 'status' => 'skipped', 'skipped_at' => now(), 'error_message' => 'Missing or invalid email address']);
                $stats['invalid']++;
                $stats['skipped']++;
                continue;
            }
            $identity = [$mailType, $attributes['related_type'], $attributes['related_id'], $recipient['email'], $payload['campaign_key'] ?? null, ! empty($payload['campaign_key']) ? ($payload['created_by'] ?? null) : null, ! empty($payload['campaign_key']) ? ($payload['recipient_kind'] ?? null) : null, ! empty($payload['campaign_key']) && $mailType !== 'series_email' ? ($payload['event_id'] ?? null) : null];
            $key = $allowDuplicates ? null : hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
            $legacyDuplicate = ! $allowDuplicates && empty($payload['campaign_key']) && BulkEmailLog::where('mail_type', $mailType)
                ->where('related_type', $attributes['related_type'])->where('related_id', $attributes['related_id'])
                ->where('recipient_email', $recipient['email'])->whereIn('status', ['queued', 'sending', 'sent', 'acceptance_unknown'])->exists();
            if (isset($seen[$recipient['email']]) || $legacyDuplicate || ($key && BulkEmailLog::where('deduplication_key', $key)->exists())) {
                BulkEmailLog::create([...$attributes, 'status' => 'skipped', 'skipped_at' => now(), 'error_message' => 'Duplicate email suppressed for this campaign']);
                $stats['duplicate']++;
                $stats['skipped']++;
                continue;
            }
            $seen[$recipient['email']] = true;
            try {
                // The unique reservation closes the check/create race between concurrent requests.
                $log = BulkEmailLog::create([...$attributes, 'deduplication_key' => $key, 'status' => 'queued', 'queued_at' => now()]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException $exception) {
                if (! $key || ! BulkEmailLog::where('deduplication_key', $key)->exists()) throw $exception;
                BulkEmailLog::create([...$attributes, 'status' => 'skipped', 'skipped_at' => now(), 'error_message' => 'Duplicate email suppressed for this campaign']);
                $stats['duplicate']++;
                $stats['skipped']++;
                continue;
            }
            try {
                SendBulkEmailJob::dispatch($log->id, (bool) ($payload['manual_retry_only'] ?? false))->afterCommit();
                $stats['queued']++;
            } catch (\Throwable $exception) {
                $log->markAsFailed('Email could not be queued. Please review the email log before retrying.');
                $stats['failed']++;
            }
        }
        return $stats;
    }

    /**
     * Normalize recipients into a consistent format.
     */
    protected function normalizeRecipients($recipients): Collection
    {
        return collect($recipients)->map(function ($recipient) {
            // Handle different input formats
            if (is_string($recipient)) {
                return [
                    'email' => strtolower(trim($recipient)),
                    'name' => null,
                ];
            }

            if (is_array($recipient)) {
                return [
                    'email' => strtolower(trim($recipient['email'] ?? '')),
                    'name' => $recipient['name'] ?? null,
                ];
            }

            // Handle objects (models)
            if (is_object($recipient)) {
                return [
                    'email' => strtolower(trim($recipient->email ?? '')),
                    'name' => $recipient->name ?? $recipient->full_name ?? null,
                ];
            }

            return ['email' => '', 'name' => null];
        })
            ->values();
    }



    /**
     * Resend failed emails for a specific mail type and related record.
     */
    public function resendFailed(string $mailType, $related = null): array
    {
        $query = BulkEmailLog::failed()
            ->where('mail_type', $mailType);

        if ($related) {
            $query->where('related_type', get_class($related))
                ->where('related_id', $related->id);
        }

        $failedLogs = $query->whereNull('sent_at')->whereNull('accepted_at')->get();

        if ($failedLogs->isEmpty()) {
            Log::info('[BulkMailDispatcher] No failed emails to resend', [
                'mail_type' => $mailType,
                'related_type' => $related ? get_class($related) : null,
                'related_id' => $related?->id ?? null,
            ]);

            return [
                'total' => 0,
                'queued' => 0,
            ];
        }

        $delaySeconds = config('mail.bulk_mail.delay_seconds', 10);
        $currentDelay = 0;
        $queued = 0;

        foreach ($failedLogs as $log) {
            // Reset status to queued
            $claimed = \Illuminate\Support\Facades\DB::transaction(function () use ($log): bool {
                $current = BulkEmailLog::whereKey($log->id)->lockForUpdate()->firstOrFail();
                if ($current->status !== 'failed' || $current->sent_at || $current->accepted_at) return false;
                $current->update(['status' => 'queued', 'queued_at' => now(), 'failed_at' => null,
                    'error_message' => null, 'retry_actor_id' => auth()->id()]);
                return true;
            });
            if (! $claimed) continue;

            // Dispatch job with delay
            SendBulkEmailJob::dispatch($log->id, (bool) data_get($log->payload, 'manual_retry_only', false))
                ->delay(now()->addSeconds($currentDelay));

            $queued++;
            $currentDelay += $delaySeconds;
        }

        Log::info('[BulkMailDispatcher] Failed emails re-queued', [
            'mail_type' => $mailType,
            'total' => $failedLogs->count(),
            'queued' => $queued,
        ]);

        return [
            'total' => $failedLogs->count(),
            'queued' => $queued,
        ];
    }
}
