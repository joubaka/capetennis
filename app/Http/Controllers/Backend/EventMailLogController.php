<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{BulkEmailLog, Event, EventMailIssue};
use App\Services\{EventMailLogService, SuperAdminMailHistory};
use Illuminate\Http\Request;
class EventMailLogController extends Controller
{
    private const OUTCOMES = [
        'pending' => 'Waiting / sending', 'sent_complete' => 'Sent (excluding sandbox)', 'accepted' => 'Server accepted',
        'failed' => 'Failed', 'skipped' => 'Excluded / duplicate',
        'unverified' => 'Completed, evidence unverified', 'uncertain' => 'Acceptance uncertain',
        'sandbox' => 'Sandbox accepted', 'queued' => 'Waiting in queue',
        'sending' => 'Sending', 'sent' => 'Completed transport (including sandbox)',
    ];

    public function index(Request $request, Event $event, EventMailLogService $service)
    {
        $filters = $request->validate([
            'status' => 'nullable|in:'.implode(',', SuperAdminMailHistory::STATUSES),
            'outcome' => 'nullable|in:'.implode(',', array_keys(self::OUTCOMES)),
            'search' => 'nullable|string|max:200', 'campaign' => 'nullable|string|max:100',
            'mail_type' => 'nullable|string|max:100', 'audience' => 'nullable|in:all,recipients,copies',
            'from' => 'nullable|date_format:Y-m-d',
            'until' => array_filter(['nullable', 'date_format:Y-m-d', $request->filled('from') ? 'after_or_equal:from' : null]),
        ]);
        $authorized = $service->query($event, $request->user());
        $types = (clone $authorized)->select('mail_type')->whereNotNull('mail_type')->where('mail_type', '!=', '')->distinct()->orderBy('mail_type')->limit(101)->pluck('mail_type');
        $typesLimited = $types->count() > 100;
        $types = $types->take(100)->mapWithKeys(fn ($type) => [$type => SuperAdminMailHistory::typeLabel($type)]);
        if (! empty($filters['mail_type']) && ! $types->has($filters['mail_type'])) {
            $selectedExists = (clone $authorized)->where('mail_type', $filters['mail_type'])->exists();
            $types->put($filters['mail_type'], $selectedExists
                ? SuperAdminMailHistory::typeLabel($filters['mail_type'])
                : 'Selected type (no permitted records): '.$filters['mail_type']);
        }
        $query = clone $authorized;
        if (! empty($filters['campaign'])) $query->where('payload->campaign_key', $filters['campaign']);
        if (! empty($filters['mail_type'])) $query->where('mail_type', $filters['mail_type']);
        if (! empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $term = '%'.$filters['search'].'%';
                $q->where('recipient_name', 'like', $term)->orWhere('recipient_email', 'like', $term)
                    ->orWhere('payload->subject', 'like', $term)->orWhere('payload->rendered_subject', 'like', $term)->orWhere('payload->title', 'like', $term);
            });
        }
        if (! empty($filters['from'])) $query->where('created_at', '>=', $filters['from'].' 00:00:00');
        if (! empty($filters['until'])) $query->where('created_at', '<', \Carbon\Carbon::parse($filters['until'])->addDay()->toDateString());
        $summary = BulkEmailLog::deliverySummary($this->audienceQuery(clone $query, 'recipients'));
        $copySummary = BulkEmailLog::deliverySummary($this->audienceQuery(clone $query, 'copies'));
        $query = $this->audienceQuery($query, $filters['audience'] ?? 'all');
        $facets = BulkEmailLog::deliverySummary(clone $query);
        $facets['sent_complete'] = (clone $query)->where('status', 'sent')->where(fn ($q) => $q->whereNull('evidence_status')->orWhere('evidence_status', '!=', 'sandbox_accepted'))->count();
        $facets['uncertain'] = (clone $query)->where('status', 'acceptance_unknown')->count();
        $facets['unverified'] -= $facets['uncertain'];
        $outcome = $filters['outcome'] ?? match ($filters['status'] ?? null) {
            'acceptance_unknown' => 'uncertain', default => $filters['status'] ?? null,
        };
        if ($outcome) $this->outcomeQuery($query, $outcome);
        $logs = $query->latest('id')->paginate(25)->withQueryString();
        $logs->each(fn ($log) => $service->recordIssue($log));
        $outcomes = self::OUTCOMES;
        return view('backend.event.mail-log.index', compact('event', 'logs', 'summary', 'copySummary', 'facets', 'filters', 'types', 'typesLimited', 'outcomes', 'outcome'));
    }

    private function audienceQuery(\Illuminate\Database\Eloquent\Builder $query, string $audience): \Illuminate\Database\Eloquent\Builder
    {
        if ($audience === 'copies') $query->whereIn('payload->recipient_kind', ['admin_copy', 'sender_copy']);
        if ($audience === 'recipients') $query->where(fn ($q) => $q->whereNull('payload->recipient_kind')->orWhereNotIn('payload->recipient_kind', ['admin_copy', 'sender_copy']));
        return $query;
    }

    private function outcomeQuery(\Illuminate\Database\Eloquent\Builder $query, string $outcome): void
    {
        match ($outcome) {
            'sent_complete' => $query->where('status', 'sent')->where(fn ($q) => $q->whereNull('evidence_status')->orWhere('evidence_status', '!=', 'sandbox_accepted')),
            'pending' => $query->whereIn('status', ['queued', 'sending']),
            'accepted' => $query->where('status', 'sent')->where('evidence_status', 'server_accepted')->whereNotNull('accepted_at'),
            'sandbox' => $query->where('status', 'sent')->where('evidence_status', 'sandbox_accepted')->whereNotNull('accepted_at'),
            'unverified' => $query->where('status', 'sent')->where(fn ($q) => $q->whereNull('accepted_at')->orWhereNull('evidence_status')->orWhereNotIn('evidence_status', ['server_accepted', 'sandbox_accepted'])),
            'uncertain' => $query->where('status', 'acceptance_unknown'),
            default => $query->where('status', $outcome),
        };
    }

    public function show(Request $request, Event $event, BulkEmailLog $log, EventMailLogService $service)
    {
        $log = $service->query($event, $request->user())->whereKey($log->id)->firstOrFail();
        $service->recordIssue($log);
        $sender = $service->sender($log);
        $history = \App\Models\EventMailAttemptHistory::where('log_id', $log->id)->latest('id')->paginate(20, ['*'], 'history_page');
        return view('backend.event.mail-log.show', compact('event', 'log', 'sender', 'history'));
    }
    public function issues(Request $request, EventMailLogService $service)
    {
        abort_unless(\Illuminate\Support\Facades\Schema::hasTable('event_mail_issues'), 503, 'Email issues are awaiting the required database migration.');
        $issues = EventMailIssue::where('user_id', $request->user()->id)->latest()->paginate(25);
        $visible = collect();
        foreach ($issues as $issue) {
            $event = Event::find($issue->event_id);
            if (!$event) {
                continue;
            }
            try {
                $log = $service->query($event, $request->user())->whereKey($issue->log_id)->first();
                $visible->push(['issue' => $issue, 'event' => $log ? $event : null, 'log' => $log]);
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
                $visible->push(['issue' => $issue, 'event' => null, 'log' => null]);
                // Own generic outcome remains visible without private event or message data.
            }
        }
        return view('backend.event.mail-log.issues', compact('issues', 'visible'));
    }
    public function acknowledge(Request $request, Event $event, EventMailIssue $issue, EventMailLogService $service)
    {
        abort_unless((int) $issue->user_id === (int) $request->user()->id && (int) $issue->event_id === (int) $event->id, 404);
        $issue->update(['read_at' => now()]);
        return back()->with('success', 'Email issue acknowledged. The send record is retained.');
    }
    public function retryPreview(Request $request, Event $event, BulkEmailLog $log, EventMailLogService $service)
    {
        $log = $service->query($event, $request->user())->whereKey($log->id)->firstOrFail();
        abort_unless(EventMailLogService::canRetry($log), 422);
        $request->session()->put('event_mail_retry.' . $log->id, ['event_id' => $event->id, 'actor_id' => $request->user()->id, 'expires' => now()->addMinutes(15)->timestamp, 'fingerprint' => hash('sha256', json_encode([$log->payload, $log->recipient_email, $log->updated_at?->toISOString()]))]);
        $retryPreview = true;
        $sender = $service->sender($log);
        $history = \App\Models\EventMailAttemptHistory::where('log_id', $log->id)->latest('id')->paginate(20, ['*'], 'history_page');
        return view('backend.event.mail-log.show', compact('event', 'log', 'retryPreview', 'sender', 'history'));
    }
    public function retry(Request $request, Event $event, BulkEmailLog $log, EventMailLogService $service)
    {
        $request->validate(['confirmed' => 'accepted']);
        $review = $request->session()->get('event_mail_retry.' . $log->id);
        abort_unless($review && $review['actor_id'] === $request->user()->id && $review['event_id'] === $event->id && $review['expires'] >= now()->timestamp, 422);
        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $event, $log, $service, $review) {
            $log = $service->query($event, $request->user())->whereKey($log->id)->lockForUpdate()->firstOrFail();
            abort_unless(EventMailLogService::canRetry($log) && hash_equals($review['fingerprint'], hash('sha256', json_encode([$log->payload, $log->recipient_email, $log->updated_at?->toISOString()]))), 422);
            $batch = \App\Models\EventCommunicationBatch::create(['event_id' => $event->id, 'created_by' => $request->user()->id, 'token' => (string) \Illuminate\Support\Str::uuid(), 'subject' => data_get($log->payload, 'subject', 'Reviewed retry'), 'body' => data_get($log->payload, 'body', data_get($log->payload, 'message')), 'options' => ['source' => 'email_log_retry', 'log_id' => $log->id], 'recipients' => [['email' => $log->recipient_email]], 'issues' => [], 'fingerprint' => $review['fingerprint'], 'status' => 'approved', 'approved_at' => now()]);
            $log->update(['status' => 'queued', 'retry_actor_id' => $request->user()->id, 'queued_at' => now(), 'failed_at' => null, 'error_message' => null, 'payload' => [...$log->payload, 'event_id' => $event->id, 'retry_actor_id' => $request->user()->id, 'event_communication_batch_id' => $batch->id, 'manual_retry_only' => true]]);
            \App\Jobs\SendBulkEmailJob::dispatch($log->id, true)->afterCommit();
        });
        $request->session()->forget('event_mail_retry.' . $log->id);
        return redirect()->route('backend.event-mail-log.show', [$event, $log])->with('success', 'One failed email queued after review. Check the report for mail-server acceptance.');
    }
}
