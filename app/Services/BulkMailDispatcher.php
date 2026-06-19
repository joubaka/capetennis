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
        $recipients = collect($recipients);
        $stats = [
            'total' => 0,
            'queued' => 0,
            'skipped' => 0,
            'invalid' => 0,
            'duplicate' => 0,
        ];

        Log::info('[BulkMailDispatcher] Starting bulk email dispatch', [
            'mail_type' => $mailType,
            'related_type' => $related ? get_class($related) : null,
            'related_id' => $related?->id ?? null,
            'recipient_count' => $recipients->count(),
            'allow_duplicates' => $allowDuplicates,
        ]);

        // Extract and normalize recipient emails
        $normalizedRecipients = $this->normalizeRecipients($recipients);

        $stats['total'] = $normalizedRecipients->count();

        // Get delay configuration
        $delaySeconds = config('mail.bulk_mail.delay_seconds', 10);
        $currentDelay = 0;

        foreach ($normalizedRecipients as $recipient) {
            // Validate email
            if (empty($recipient['email']) || !filter_var($recipient['email'], FILTER_VALIDATE_EMAIL)) {
                $stats['invalid']++;
                Log::warning('[BulkMailDispatcher] Invalid email skipped', [
                    'email' => $recipient['email'] ?? 'empty',
                ]);
                continue;
            }

            // Check for duplicates (unless explicitly allowed)
            if (!$allowDuplicates && $this->isDuplicate($mailType, $related, $recipient['email'])) {
                $stats['duplicate']++;
                $stats['skipped']++;

                // Log as skipped
                BulkEmailLog::create([
                    'mail_type' => $mailType,
                    'related_type' => $related ? get_class($related) : null,
                    'related_id' => $related?->id ?? null,
                    'recipient_email' => $recipient['email'],
                    'recipient_name' => $recipient['name'] ?? null,
                    'status' => 'skipped',
                    'error_message' => 'Duplicate email for this mail type and related record',
                    'payload' => $payload,
                    'skipped_at' => now(),
                ]);

                Log::info('[BulkMailDispatcher] Duplicate email skipped', [
                    'email' => $recipient['email'],
                    'mail_type' => $mailType,
                ]);

                continue;
            }

            // Create log entry
            $log = BulkEmailLog::create([
                'mail_type' => $mailType,
                'related_type' => $related ? get_class($related) : null,
                'related_id' => $related?->id ?? null,
                'recipient_email' => $recipient['email'],
                'recipient_name' => $recipient['name'] ?? null,
                'status' => 'queued',
                'payload' => $payload,
                'queued_at' => now(),
            ]);

            // Dispatch job with progressive delay
            SendBulkEmailJob::dispatch($log->id)
                ->delay(now()->addSeconds($currentDelay));

            $stats['queued']++;
            $currentDelay += $delaySeconds;

            Log::debug('[BulkMailDispatcher] Email queued', [
                'log_id' => $log->id,
                'email' => $recipient['email'],
                'delay_seconds' => $currentDelay - $delaySeconds,
            ]);
        }

        Log::info('[BulkMailDispatcher] Bulk email dispatch completed', [
            'mail_type' => $mailType,
            'stats' => $stats,
            'total_delay' => $currentDelay,
        ]);

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
            ->filter(fn($r) => !empty($r['email'])) // Remove empty emails
            ->unique('email') // Remove duplicate emails
            ->values();
    }

    /**
     * Check if this email was already sent for this mail type and related record.
     */
    protected function isDuplicate(string $mailType, $related, string $email): bool
    {
        $query = BulkEmailLog::where('mail_type', $mailType)
            ->where('recipient_email', strtolower(trim($email)))
            ->whereIn('status', ['queued', 'sent']); // Don't count failed/skipped as duplicates

        if ($related) {
            $query->where('related_type', get_class($related))
                ->where('related_id', $related->id);
        } else {
            $query->whereNull('related_type')
                ->whereNull('related_id');
        }

        return $query->exists();
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

        $failedLogs = $query->get();

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
            $log->update([
                'status' => 'queued',
                'queued_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ]);

            // Dispatch job with delay
            SendBulkEmailJob::dispatch($log->id)
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
