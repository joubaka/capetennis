<?php

namespace App\Services\Masters;

use App\Jobs\SendMastersInvitationEmailJob;
use App\Models\BulkEmailLog;
use App\Models\MastersInvitation;
use Illuminate\Support\Facades\DB;

class RetryMastersInvitationEmails
{
    public function run(int $eventId, bool $queue = false): array
    {
        $report = ['failed' => 0, 'eligible' => 0, 'queued' => 0, 'skipped' => 0];
        $seen = [];
        BulkEmailLog::query()
            ->where('mail_type', 'masters_invitation')
            ->where('related_type', MastersInvitation::class)
            ->where('status', 'failed')
            ->whereIn('payload->kind', ['invitation', 'replacement'])
            ->whereIn('related_id', MastersInvitation::query()
                ->select('masters_invitations.id')
                ->join('masters_invitation_batches as batch', 'batch.id', '=', 'masters_invitations.batch_id')
                ->where('batch.event_id', $eventId))
            ->chunkById(100, function ($logs) use ($eventId, $queue, &$report, &$seen) {
                foreach ($logs as $candidate) {
                    $report['failed']++;
                    $key = $candidate->related_id.':'.$candidate->payload['kind'];
                    $result = !isset($seen[$key]) && ($queue
                        ? DB::transaction(fn () => $this->inspect($candidate->id, $eventId, true))
                        : $this->inspect($candidate->id, $eventId, false));
                    $report[$result ? 'eligible' : 'skipped']++;
                    if ($result) {
                        $seen[$key] = true;
                    }
                    if ($queue && $result) {
                        $report['queued']++;
                    }
                }
            });

        return $report;
    }

    private function inspect(int $logId, int $eventId, bool $queue): bool
    {
        // Serialize recovery per invitation, including duplicate failed logs.
        $candidate = BulkEmailLog::find($logId);
        if (!$candidate) {
            return false;
        }
        $query = MastersInvitation::with('batch.event')->whereKey($candidate->related_id);
        $invitation = ($queue ? $query->lockForUpdate() : $query)->first();
        $logQuery = BulkEmailLog::whereKey($logId);
        $log = ($queue ? $logQuery->lockForUpdate() : $logQuery)->first();
        if (!$log || $log->status !== 'failed' || $log->sent_at
            || !in_array($log->payload['kind'] ?? null, ['invitation', 'replacement'], true)
            || app(MastersInvitationMailEligibility::class)->reason($log, $invitation, $eventId)) {
            return false;
        }

        $scheduledQuery = BulkEmailLog::query()
            ->where('mail_type', 'masters_invitation')
            ->where('related_type', MastersInvitation::class)
            ->where('related_id', $invitation->id)
            ->where('payload->kind', $log->payload['kind'])
            ->where('id', '!=', $log->id)
            ->whereIn('status', ['queued', 'sent']);
        // Use a current locking read during recovery, not a repeatable-read
        // snapshot created before another retry transaction committed.
        $alreadyScheduled = $queue
            ? $scheduledQuery->lockForUpdate()->first(['id']) !== null
            : $scheduledQuery->exists();
        if ($alreadyScheduled) {
            return false;
        }

        if ($queue) {
            $log->update([
                'status' => 'queued', 'queued_at' => now(),
                'failed_at' => null, 'error_message' => null,
            ]);
            // Fresh payload: old failed_jobs entries retain their three-try
            // policy. Keep those failure records as history; do not retry them.
            SendMastersInvitationEmailJob::dispatch($log->id, $eventId);
        }

        return true;
    }
}
