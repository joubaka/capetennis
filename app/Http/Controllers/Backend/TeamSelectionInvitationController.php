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
        $eventRegions = EventRegion::with(['region.clothingItems.sizes', 'rankingSource.series', 'rankingSource.imports.invitations.player.user', 'rankingSource.imports.invitations.player.users', 'rankingSource.imports.invitations.emailLogs'])
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
        $categorySetups = $eventRegions->filter(fn (EventRegion $eventRegion) => $eventRegion->rankingSource)
            ->mapWithKeys(fn (EventRegion $eventRegion) => [
                $eventRegion->rankingSource->id => $service->categorySetup($eventRegion->rankingSource),
            ]);

        return view('backend.team-selection.index', compact('event', 'eventRegions', 'series', 'readySeriesIds', 'teams', 'categorySetups'));
    }

    public function link(Request $request, Event $event, EventRegion $eventRegion, TeamRankingImportService $service)
    {
        $this->authorizeEvent($event);
        $data = $request->validate([
            'series_id' => ['required', 'integer', 'exists:series,id'],
            'reserve_count' => ['required', 'integer', 'min:0', 'max:20'],
        ]);
        $service->link($event, $eventRegion, Series::findOrFail($data['series_id']), (int) $data['reserve_count'], $request->user());

        $source = $eventRegion->fresh('rankingSource')->rankingSource;

        return redirect()->route('backend.team-selection.index', $event)
            ->with('success', 'The region is linked. Select the ranking categories that must become event teams.')
            ->with('open_team_setup_source', $source?->id);
    }

    public function createTeams(Request $request, Event $event, EventRegionRankingSource $source, TeamRankingImportService $service)
    {
        $this->authorizeSource($event, $source);
        $data = $request->validate([
            'categories' => ['required', 'array', 'min:1'],
            'categories.*.selected' => ['nullable', 'boolean'],
            'categories.*.ranking_list_id' => ['required', 'integer', 'distinct'],
            'categories.*.team_name' => ['nullable', 'string', 'max:255'],
            'categories.*.num_players' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $result = $service->createTeamsFromRankingCategories($source, $data['categories'], $request->user());

        if ($service->hasPublishedRanking($source->series_id)) {
            return redirect()->route('backend.team-selection.preview', [$event, $source])
                ->with('success', "Created {$result['created']} teams and linked {$result['linked']} existing teams. Review the ranked players before importing.");
        }

        return redirect()->route('backend.team-selection.index', $event)
            ->with('success', "Created {$result['created']} teams and linked {$result['linked']} existing teams. Publish the latest ranking before importing players.");
    }

    public function unlink(Request $request, Event $event, EventRegionRankingSource $source, TeamRankingImportService $service)
    {
        $this->authorizeSource($event, $source);
        $service->unlink($source, $request->user());

        return redirect()->route('backend.team-selection.index', $event)
            ->with('success', 'The ranking series was unlinked. Existing event categories and teams were preserved.');
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
        $data = $request->validate([
            'confirm_incomplete_rosters' => ['nullable', 'accepted'],
        ]);
        $selectionImport = $service->import(
            $source,
            $request->user(),
            array_key_exists('confirm_incomplete_rosters', $data)
        );

        return redirect()->route('backend.team-selection.index', $event)
            ->with('success', "Imported {$selectionImport->invitations->where('status', 'invited')->count()} selected players and {$selectionImport->invitations->where('status', 'reserve')->count()} reserves.");
    }

    public function send(Request $request, Event $event, TeamSelectionImport $selectionImport, TeamSelectionInvitationService $service)
    {
        abort_unless((int) $selectionImport->event_id === (int) $event->id, 404);
        $this->authorizeEvent($event);
        $data = $this->communicationData($request);
        $stats = $service->send($selectionImport, $data, $request->user());

        return back()->with('success', "Queued {$stats['queued']} invitations. {$stats['missing_email']} selected players need an email address.");
    }

    public function previewEmail(Request $request, Event $event, TeamSelectionImport $selectionImport, TeamSelectionInvitationService $service)
    {
        abort_unless((int) $selectionImport->event_id === (int) $event->id, 404);
        $this->authorizeEvent($event);
        $data = $this->communicationData($request);
        $invitation = $selectionImport->invitations()
            ->with(['selectionImport.event', 'region', 'team', 'player'])
            ->where('status', 'invited')
            ->orderBy('queue_position')
            ->firstOrFail();
        $campaign = $service->previewCampaign($selectionImport, $data);
        $kind = 'invitation';

        return view('emails.team-selection.invitation', compact('invitation', 'campaign', 'kind'));
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

    private function communicationData(Request $request): array
    {
        return $request->validate([
            'response_deadline' => ['required', 'date'],
            'payment_deadline' => ['required', 'date', 'after_or_equal:response_deadline'],
            'email_subject' => ['required', 'string', 'max:180'],
            'email_message' => ['required', 'string', 'max:10000'],
            'event_information' => ['nullable', 'string', 'max:20000'],
            'reply_to' => ['nullable', 'email:rfc', 'max:255'],
            'include_clothing' => ['nullable', 'boolean'],
        ]);
    }
}
