<?php

namespace App\Services;

use App\Mail\BulkEventMail;
use App\Models\{BulkEmailLog, Event, User};
use Illuminate\Support\Facades\DB;

class EventFinanceReportDelivery
{
    public function queue(Event $event, User $actor, string $recipient, string $requestKey, callable $pdf, string $filename): array
    {
        abort_unless($actor->can('event-finance.view', $event), 403);
        $log = DB::transaction(function () use ($event, $actor, $recipient, $requestKey, $pdf, $filename) {
            Event::whereKey($event->id)->lockForUpdate()->firstOrFail();
            $campaign = 'finance-report:'.$requestKey;
            $existing = BulkEmailLog::where('mail_type', 'event_finance_report')->where('related_type', Event::class)
                ->where('related_id', $event->id)->where('payload->created_by', $actor->id)->where('payload->campaign_key', $campaign)->first();
            if ($existing) {
                abort_unless($existing->recipient_email === $recipient, 409, 'This request was already used for another recipient.');
                return $existing;
            }
            $payload = ['event_id' => $event->id, 'created_by' => $actor->id, 'campaign_key' => $campaign, 'financial_metadata_only' => true,
                'subject' => 'Income and expense breakdown: '.$event->name,
                'body' => '<p>The income and expense breakdown for '.e($event->name).' is attached as a PDF.</p><p>This report contains private event financial information.</p>',
                'filename' => $filename, 'pdf' => base64_encode($pdf())];
            $payload['report_signature'] = $this->signature($payload, $recipient);
            app(BulkMailDispatcher::class)->dispatch('event_finance_report', $event, [$recipient], $payload);
            return BulkEmailLog::where('mail_type', 'event_finance_report')->where('related_id', $event->id)
                ->where('payload->campaign_key', $campaign)->where('payload->created_by', $actor->id)->firstOrFail();
        });
        $log->refresh();
        $failed = in_array($log->status, ['failed', 'skipped', 'acceptance_unknown'], true);
        return ['failed' => $failed, 'message' => $failed ? 'The report could not be sent. Check the mail history before retrying.' : 'Report queued for '.$recipient.'.'];
    }

    public function authorizedMailable(BulkEmailLog $log): ?BulkEventMail
    {
        $payload = $log->payload;
        $event = Event::find($payload['event_id'] ?? 0);
        $actor = User::find($payload['created_by'] ?? 0);
        if ($log->mail_type !== 'event_finance_report' || !$event || !$actor || !$actor->can('event-finance.view', $event)
            || $log->related_type !== Event::class || (int) $log->related_id !== (int) $event->id
            || !hash_equals($this->signature($payload, $log->recipient_email), $payload['report_signature'] ?? '')) return null;
        $pdf = base64_decode($payload['pdf'], true);
        if ($pdf === false || !str_starts_with($pdf, '%PDF-')) return null;
        return (new BulkEventMail($payload['subject'], $payload['body'], config('mail.from.name', 'Cape Tennis'), config('mail.from.address')))
            ->attachData($pdf, $payload['filename'], ['mime' => 'application/pdf'])
            ->with('finance_report_log_id', $log->id);
    }

    private function signature(array $payload, string $recipient): string
    {
        return hash_hmac('sha256', json_encode([$payload['event_id'] ?? null, $payload['created_by'] ?? null, $recipient,
            $payload['campaign_key'] ?? null, $payload['subject'] ?? null, $payload['body'] ?? null,
            $payload['filename'] ?? null, $payload['pdf'] ?? null], JSON_THROW_ON_ERROR), config('app.key'));
    }
}
