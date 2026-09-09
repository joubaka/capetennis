<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegion;
use App\Models\EventRegionRankingSource;
use App\Models\Series;
use App\Models\SeriesRanking;
use App\Models\Team;
use App\Models\TeamSelectionImport;
use App\Services\TeamSelection\TeamRankingImportService;
use App\Services\TeamSelection\TeamSelectionInvitationService;
use Illuminate\Http\Request;

class TeamSelectionInvitationController extends Controller
{
    public function index(Event $event, TeamRankingImportService $service)
    {
        $this->authorizeEvent($event);
        $eventRegions = EventRegion::with(['region', 'rankingSource.series', 'rankingSource.imports.invitations.player.user', 'rankingSource.imports.invitations.player.users', 'rankingSource.imports.invitations.emailLogs'])
            ->where('event_id', $event->id)->orderBy('ordering')->get();
        $eventYear = (int) ($event->start_date?->format('Y') ?: date('Y'));
        $series = Series::query()->where('year', $eventYear)->orderBy('name')->get();
        $readySeriesIds = $series->filter(function (Series $item): bool {
            $latest = SeriesRanking::query()->where('series_id', $item->id)
                ->orderByDesc('created_at')->orderByDesc('id')->first(['status', 'run_id']);

            return $latest?->status === 'published' && filled($latest->run_id);
        })->pluck('id');
        $teams = $service->teamsForEvent($event, $eventRegions->pluck('region_id')->map(fn ($id) => (int) $id)->all())
            ->loadCount(['team_players', 'team_players_no_profile'])->groupBy('region_id');

        return view('backend.team-selection.index', compact('event', 'eventRegions', 'series', 'readySeriesIds', 'teams'));
    }

    public function link(Request $request, Event $event, EventRegion $eventRegion, TeamRankingImportService $service)
    {
        $this->authorizeEvent($event);
        $data = $request->validate([
            'series_id' => ['required', 'integer', 'exists:series,id'],
            'reserve_count' => ['required', 'integer', 'min:0', 'max:20'],
        ]);
        $service->link($event, $eventRegion, Series::findOrFail($data['series_id']), (int) $data['reserve_count'], $request->user());

        return back()->with('success', 'The region is linked to its ranking series.');
    }

    public function preview(Event $event, EventRegionRankingSource $source, TeamRankingImportService $service)
    {
        $this->authorizeSource($event, $source);
        $preview = $service->preview($source);

        return view('backend.team-selection.preview', compact('event', 'source', 'preview'));
    }

    public function import(Request $request, Event $event, EventRegionRankingSource $source, TeamRankingImportService $service)
    {
        $this->authorizeSource($event, $source);
        $selectionImport = $service->import($source, $request->user());

        return redirect()->route('backend.team-selection.index', $event)
            ->with('success', "Imported {$selectionImport->invitations->where('status', 'invited')->count()} selected players and {$selectionImport->invitations->where('status', 'reserve')->count()} reserves.");
    }

    public function send(Request $request, Event $event, TeamSelectionImport $selectionImport, TeamSelectionInvitationService $service)
    {
        abort_unless((int) $selectionImport->event_id === (int) $event->id, 404);
        $this->authorizeEvent($event);
        $data = $request->validate([
            'response_deadline' => ['required', 'date'],
            'payment_deadline' => ['required', 'date', 'after_or_equal:response_deadline'],
        ]);
        $stats = $service->send($selectionImport, $data, $request->user());

        return back()->with('success', "Queued {$stats['queued']} invitations. {$stats['missing_email']} selected players need an email address.");
    }

    public function restart(Request $request, Event $event, TeamSelectionImport $selectionImport, TeamRankingImportService $service)
    {
        abort_unless((int) $selectionImport->event_id === (int) $event->id, 404);
        $this->authorizeEvent($event);
        $service->restartDraft($selectionImport, $request->user());

        return back()->with('success', 'The draft import was removed. Team quantities and the ranking link can now be corrected.');
    }

    public function extendDeadlines(Request $request, Event $event, TeamSelectionImport $selectionImport, TeamSelectionInvitationService $service)
    {
        abort_unless((int) $selectionImport->event_id === (int) $event->id, 404);
        $this->authorizeEvent($event);
        $data = $request->validate([
            'response_deadline' => ['required', 'date'],
            'payment_deadline' => ['required', 'date', 'after_or_equal:response_deadline'],
        ]);
        $service->extendDeadlines($selectionImport, $data, $request->user());

        return back()->with('success', 'Invitation deadlines were extended.');
    }

    public function retryFailed(Request $request, Event $event, TeamSelectionImport $selectionImport, TeamSelectionInvitationService $service)
    {
        abort_unless((int) $selectionImport->event_id === (int) $event->id, 404);
        $this->authorizeEvent($event);
        $queued = $service->retryFailedEmails($selectionImport, $request->user());

        return back()->with('success', "Queued {$queued} failed invitation email(s) for retry.");
    }

    private function authorizeSource(Event $event, EventRegionRankingSource $source): void
    {
        abort_unless((int) $source->event_id === (int) $event->id, 404);
        $this->authorizeEvent($event);
    }

    private function authorizeEvent(Event $event): void
    {
        abort_unless($event->isTeam(), 404);
        $this->authorize('event-draw.view', $event);
    }
}
