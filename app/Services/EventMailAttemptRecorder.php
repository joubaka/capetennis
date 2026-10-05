<?php

namespace App\Services;

use App\Models\BulkEmailLog;
use App\Models\EventMailAttemptHistory;
use Illuminate\Support\Facades\Schema;
class EventMailAttemptRecorder
{
    public function recordOriginal(BulkEmailLog $log): void
    {
        $original = new BulkEmailLog();
        $original->setRawAttributes($log->getRawOriginal(), true);
        $this->write($original);
    }
    public function record(BulkEmailLog $log): void
    {
        try {
            $this->write($log);
        } catch (\Throwable) {
            \Illuminate\Support\Facades\Log::warning('Mail attempt snapshot could not be saved.', ['log_id' => $log->id]);
        }
    }
    private function write(BulkEmailLog $log): void
    {
        if (!Schema::hasTable('event_mail_attempt_history')) {
            return;
        }
        $payload = $log->payload ?? [];
        $financial = ($log->mail_type === 'system_mail' && data_get($payload, 'financial_metadata_only') !== false)
            || data_get($payload, 'financial_metadata_only') === true || in_array($log->mail_type, ['bank_refund_reminder', 'registration_confirmation', 'recovery_payment', 'team_payment_confirmation'], true);
        if ($financial) {
            $payload = array_intersect_key($payload, array_flip(['event_id', 'created_by', 'retry_actor_id', 'queue_uuid', 'queue_state', 'financial_metadata_only', 'rendered_subject', 'system_initiated']));
        }
        EventMailAttemptHistory::create([
            'log_id' => $log->id,
            'attempt_number' => max(1, (int) $log->attempt_number),
            'status' => $log->status,
            'actor_id' => $log->retry_actor_id ?? data_get($payload, 'retry_actor_id', data_get($payload, 'created_by')),
            'snapshot' => [
                'payload' => $payload,
                'recipient_email' => $log->recipient_email,
                'recipient_name' => $log->recipient_name,
                'error_message' => $log->error_message
                    ? (EventMailLogService::explanation($log) ?? 'Review the recorded outcome before retrying.') : null,
                'queued_at' => $log->queued_at?->toISOString(),
                'failed_at' => $log->failed_at?->toISOString(),
                'sent_at' => $log->sent_at?->toISOString(),
                'accepted_at' => $log->accepted_at?->toISOString(),
                'evidence_status' => $log->evidence_status,
            ],
            'recorded_at' => now(),
        ]);
    }
}
