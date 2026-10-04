<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{BulkEmailLog, Event, EventCommunicationBatch, Team};
use App\Services\EventCommunicationService;
use Illuminate\Http\Request;

class EventCommunicationController extends Controller
{
    public function index(Request $request, Event $event, EventCommunicationService $service)
    {
        $regions = $service->regions($event, $request->user());
        $rankingAudience = app(\App\Services\RegionalRankingMailAudience::class);
        $canRankingMail = $rankingAudience->canManage($event, $request->user());
        $rankingLists = $canRankingMail ? $rankingAudience->lists($event) : collect();
        $teams = Team::withoutGlobalScopes()->whereHas('category', fn ($q) => $q->where('event_id', $event->id))->whereIn('region_id', $regions->pluck('region_id'))->when($request->filled('team_search'), fn ($q) => $q->where('name', 'like', '%'.mb_substr($request->query('team_search'), 0, 100).'%'))->orderBy('name')->limit(500)->get();
        $search = trim((string) $request->query('search', ''));
        $individuals = $service->individualOptions($event, $request->user(), $search);
        $batches = EventCommunicationBatch::where('event_id', $event->id)->where('created_by', $request->user()->id)->when(! $canRankingMail, fn ($q) => $q->where(fn ($q) => $q->whereNull('options->scope')->orWhere('options->scope', '!=', 'rankings'))->whereNull('options->ranking_origin_id'))->latest()->paginate(15);
        $batch = $request->filled('batch') ? EventCommunicationBatch::where('event_id', $event->id)->where('created_by', $request->user()->id)->findOrFail($request->query('batch')) : $batches->first();
        if ($batch) $service->authorizeRankingBatch($batch, $request->user());
        $batchLogs = $batch && ($batch->options['source'] ?? null) === 'invitation_retry'
            ? $service->invitationLogs($event, $request->user())->whereKey($batch->options['log_id']) : $batch?->logs();
        $summary = $batchLogs ? BulkEmailLog::deliverySummary($batchLogs) : null;
        $logs = $batchLogs ? (clone $batchLogs)->latest()->paginate(25, ['*'], 'recipients_page')->withQueryString() : null;
        $invitationQuery = $service->invitationLogs($event, $request->user());
        $invitationSummary = BulkEmailLog::deliverySummary($invitationQuery);
        $invitationLogs = $invitationQuery->latest()->paginate(25, ['*'], 'invitation_page')->withQueryString();
        $drafts = $service->managesWholeEvent($event, $request->user())
            ? EventCommunicationBatch::where('event_id', $event->id)->where('status', 'draft')->latest()->paginate(10, ['*'], 'drafts_page') : collect();

        return view('backend.event.communications.index', compact('event', 'regions', 'teams', 'individuals', 'batches', 'batch', 'summary', 'logs', 'search', 'drafts', 'invitationLogs', 'invitationSummary', 'canRankingMail', 'rankingLists'));
    }

    public function reviewDraft(Request $request, Event $event, EventCommunicationBatch $draft, EventCommunicationService $service)
    {
        abort_unless((int) $draft->event_id === (int) $event->id && $draft->status === 'draft', 404);
        $batch = $service->previewDraft($draft, $request->user());

        $rankingReview = ($batch->options['scope'] ?? null) === 'rankings' ? app(\App\Services\RegionalRankingMailAudience::class)->resolve($event, $request->user(), $batch->options) : null;
        return view('backend.event.communications.preview', compact('event', 'batch', 'rankingReview'));
    }

    public function retryPreview(Request $request, Event $event, BulkEmailLog $log, EventCommunicationService $service)
    {
        $batch = $service->previewRetry($event, $log, $request->user());

        $rankingReview = ($batch->options['scope'] ?? null) === 'rankings' ? app(\App\Services\RegionalRankingMailAudience::class)->resolve($event, $request->user(), $batch->options) : null;
        return view('backend.event.communications.preview', compact('event', 'batch', 'rankingReview'));
    }

    public function preview(Request $request, Event $event, EventCommunicationService $service)
    {
        $data = $request->validate([
            'scope' => 'required|in:all,nominations,region,team,individual,rankings',
            'ranking_region_ids' => 'nullable|array|max:500',
            'ranking_region_ids.*' => 'integer',
            'ranking_list_ids' => 'nullable|array|max:500',
            'ranking_list_ids.*' => 'integer',
            'rank_numbers' => 'nullable|string|max:1000',
            'excluded_player_ids' => 'nullable|array|max:5000',
            'excluded_player_ids.*' => 'integer',
            'exclude_team_listed' => 'nullable|boolean',
            'exclude_declined' => 'nullable|boolean',
            'exclude_reserves' => 'nullable|boolean',
            'exclude_withdrawn' => 'nullable|boolean',
            'region_id' => 'nullable|required_if:scope,region|integer',
            'team_id' => 'nullable|required_if:scope,team|integer',
            'individual_key' => 'nullable|required_if:scope,individual|string|max:80',
            'filter' => 'required|in:all,not_registered,payment_pending,paid,declined,withdrawn,reserves',
            'recipients' => 'required|in:players,managers,both',
            'subject' => 'required|string|max:200',
            'body' => 'required|string|max:30000',
        ]);
        $options = array_intersect_key($data, array_flip(['scope', 'region_id', 'team_id', 'individual_key', 'filter', 'recipients']));
        if ($data['scope'] === 'rankings') {
            $options = array_intersect_key($data, array_flip(['scope', 'ranking_region_ids', 'ranking_list_ids', 'rank_numbers', 'excluded_player_ids', 'exclude_team_listed', 'exclude_declined', 'exclude_reserves', 'exclude_withdrawn']));
            $options += ['filter' => 'all', 'recipients' => 'players'];
        }
        $batch = $service->preview($event, $request->user(), $options, $data['subject'], $data['body']);

        $rankingReview = ($batch->options['scope'] ?? null) === 'rankings' ? app(\App\Services\RegionalRankingMailAudience::class)->resolve($event, $request->user(), $batch->options) : null;
        return view('backend.event.communications.preview', compact('event', 'batch', 'rankingReview'));
    }

    public function send(Request $request, Event $event, EventCommunicationService $service)
    {
        $request->validate(['token' => 'required|uuid', 'confirm_send' => 'accepted', 'acknowledge_missing' => 'nullable|boolean']);
        $batch = EventCommunicationBatch::where('event_id', $event->id)->where('token', $request->token)->firstOrFail();
        $stats = $service->approve($batch, $request->user(), $request->boolean('acknowledge_missing'));

        return redirect()->route('backend.event-communications.index', ['event' => $event, 'batch' => $batch->id])->with('success', $stats['duplicate'] ? 'This approved batch was already queued. No duplicate emails were created.' : "{$stats['queued']} emails queued. Check the send report below for mail-server acceptance.");
    }
}
