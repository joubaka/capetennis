<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegion;
use App\Models\EventRegionRankingSource;
use App\Models\EventRegionManager;
use App\Models\NoProfileTeamPlayer;
use App\Models\Player;
use App\Models\Series;
use App\Models\SeriesRanking;
use App\Models\Team;
use App\Models\TeamSelectionImport;
use App\Models\TeamSelectionInvitation;
use App\Models\TeamSelectionRegionAnnouncement;
use App\Models\User;
use App\Services\BulkMailDispatcher;
use App\Services\TeamSelection\RegionManagerAccessService;
use App\Services\TeamSelection\ImportedTeamRosterService;
use App\Services\TeamSelection\ImportedRosterContactEnrichmentService;
use App\Imports\ExternalTeamWorkbookParser;
use App\Services\TeamSelection\TeamRankingImportService;
use App\Services\TeamSelection\TeamSelectionInvitationService;
use App\Services\TeamSelection\TeamSelectionContactService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamSelectionInvitationController extends Controller
{
    public function __construct(private TeamSelectionContactService $contacts) {}

    public function index(Event $event, TeamRankingImportService $service, RegionManagerAccessService $access)
    {
        abort_unless($event->isTeam(), 404);
        $isEventManager = $access->isEventManager(request()->user(), $event);
        $eventRegions = EventRegion::with(['events', 'managerAssignment.user', 'announcements.creator', 'announcements.emailLogs', 'region.clothingItems.sizes', 'rankingSource.series', 'rankingSource.imports.invitations.team', 'rankingSource.imports.invitations.player.user', 'rankingSource.imports.invitations.player.users', 'rankingSource.imports.invitations.emailLogs'])
            ->where('event_id', $event->id)->orderBy('ordering')->get();
        if (! $isEventManager) {
            $eventRegions = $eventRegions->filter(fn (EventRegion $item) => $access->canManage(request()->user(), $item))->values();
            abort_if($eventRegions->isEmpty(), 403);
        }
        $regionManagers = $eventRegions->mapWithKeys(fn (EventRegion $item) => [$item->id => $access->manager($item)]);
        $defaultRegionManagers = $isEventManager
            ? $eventRegions->mapWithKeys(fn (EventRegion $item) => [$item->id => $access->defaultManager($item)])
            : collect();
        $defaultRegionManagerCandidates = $isEventManager
            ? $eventRegions->mapWithKeys(fn (EventRegion $item) => [$item->id => $access->defaultManagerCandidates($item)])
            : collect();
        $announcementRecipients = $eventRegions->mapWithKeys(fn (EventRegion $item) => [
            $item->id => $this->announcementRecipients($event, $item),
        ]);
        $regionRosterRecipients = $eventRegions->mapWithKeys(fn (EventRegion $item) => [
            $item->id => $this->rosterRecipients($event, $item),
        ]);
        $pendingImportedRecipients = $eventRegions->mapWithKeys(fn (EventRegion $item) => [
            $item->id => $this->pendingImportedRecipients($event, $item),
        ]);
        $eventYear = (int) ($event->start_date?->format('Y') ?: date('Y'));
        $series = Series::query()->where('year', $eventYear)->orderBy('name')->get();
        $readySeriesIds = $series->filter(function (Series $item): bool {
            $latest = SeriesRanking::query()->where('series_id', $item->id)
                ->orderByDesc('created_at')->orderByDesc('id')->first(['status', 'run_id']);

            return $latest?->status === 'published' && filled($latest->run_id);
        })->pluck('id');
        $teams = $service->teamsForEvent($event, $eventRegions->pluck('region_id')->map(fn ($id) => (int) $id)->all())
            ->load(['team_players_no_profile.profile'])
            ->loadCount(['team_players', 'team_players_no_profile'])->groupBy('region_id');
        $categorySetups = $eventRegions->filter(fn (EventRegion $eventRegion) => $eventRegion->rankingSource)
            ->mapWithKeys(fn (EventRegion $eventRegion) => [
                $eventRegion->rankingSource->id => $service->categorySetup($eventRegion->rankingSource),
            ]);

        $teamSelectionContacts = $this->contacts;

        return view('backend.team-selection.index', compact('event', 'eventRegions', 'series', 'readySeriesIds', 'teams', 'categorySetups', 'isEventManager', 'regionManagers', 'defaultRegionManagers', 'defaultRegionManagerCandidates', 'announcementRecipients', 'regionRosterRecipients', 'pendingImportedRecipients', 'teamSelectionContacts'));
    }

    public function link(Request $request, Event $event, EventRegion $eventRegion, TeamRankingImportService $service)
    {
        abort_unless((int) $eventRegion->event_id === (int) $event->id, 404);
        $this->authorizeRegion($event, $eventRegion, $request->user());
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
        $this->authorizeImport($event, $selectionImport, $request->user());
        $data = $this->communicationData($request);
        $campaign = $service->previewCampaign($selectionImport, $data);
        $previewHash = $request->session()->get('team_selection_email_previews.'.$selectionImport->id);
        if (! is_string($previewHash) || ! hash_equals($campaign['hash'], $previewHash)) {
            throw ValidationException::withMessages([
                'email_preview' => 'Preview this exact email before sending. If you change any message, deadline, event detail, or clothing price, preview it again.',
            ]);
        }
        $stats = $service->send($selectionImport, $data, $request->user());
        $request->session()->forget('team_selection_email_previews.'.$selectionImport->id);

        return back()->with('success', "Queued {$stats['queued']} invitations. {$stats['missing_email']} selected players need an email address.");
    }

    public function previewEmail(Request $request, Event $event, TeamSelectionImport $selectionImport, TeamSelectionInvitationService $service)
    {
        abort_unless((int) $selectionImport->event_id === (int) $event->id, 404);
        $this->authorizeImport($event, $selectionImport, $request->user());
        $data = $this->communicationData($request);
        $invitation = $selectionImport->invitations()
            ->with(['selectionImport.event', 'region', 'team', 'player'])
            ->where('status', 'invited')
            ->orderBy('queue_position')
            ->firstOrFail();
        $campaign = $service->previewCampaign($selectionImport, $data);
        $kind = 'invitation';
        $request->session()->put('team_selection_email_previews.'.$selectionImport->id, $campaign['hash']);
        $subject = $campaign['subject'];

        return view('backend.team-selection.email-preview', compact('invitation', 'campaign', 'kind', 'subject'));
    }

    public function restart(Request $request, Event $event, TeamSelectionImport $selectionImport, TeamRankingImportService $service)
    {
        abort_unless((int) $selectionImport->event_id === (int) $event->id, 404);
        $this->authorizeImport($event, $selectionImport, $request->user());
        $service->restartDraft($selectionImport, $request->user());

        return back()->with('success', 'The draft import was removed. Team quantities and the ranking link can now be corrected.');
    }

    public function extendDeadlines(Request $request, Event $event, TeamSelectionImport $selectionImport, TeamSelectionInvitationService $service)
    {
        abort_unless((int) $selectionImport->event_id === (int) $event->id, 404);
        $this->authorizeImport($event, $selectionImport, $request->user());
        $data = $request->validate([
            'response_deadline' => ['required', 'date'],
            'payment_deadline' => ['required', 'date', 'after_or_equal:response_deadline'],
            'replacement_payment_deadline' => ['nullable', 'date', 'after_or_equal:payment_deadline'],
        ]);
        $service->extendDeadlines($selectionImport, $data, $request->user());

        return back()->with('success', 'Invitation deadlines were extended.');
    }

    public function updateReplacementMode(Request $request, Event $event, TeamSelectionImport $selectionImport, TeamSelectionInvitationService $service)
    {
        abort_unless((int) $selectionImport->event_id === (int) $event->id, 404);
        $this->authorizeImport($event, $selectionImport, $request->user());
        $data = $request->validate(['replacement_mode' => ['required', 'in:automatic,manual']]);
        $automatic = $data['replacement_mode'] === 'automatic';
        $service->updateReplacementMode($selectionImport, $automatic, $request->user());

        return back()->with('success', $automatic
            ? 'Automatic reserve promotion is enabled. Each replacement receives at least 24 hours where the event date allows it.'
            : 'Manual reserve promotion is enabled. Future vacancies will wait for regional approval.');
    }

    public function retryFailed(Request $request, Event $event, TeamSelectionImport $selectionImport, TeamSelectionInvitationService $service)
    {
        abort_unless((int) $selectionImport->event_id === (int) $event->id, 404);
        $this->authorizeImport($event, $selectionImport, $request->user());
        $queued = $service->retryFailedEmails($selectionImport, $request->user());

        return back()->with('success', "Queued {$queued} failed invitation email(s) for retry.");
    }

    private function authorizeSource(Event $event, EventRegionRankingSource $source): void
    {
        abort_unless((int) $source->event_id === (int) $event->id, 404);
        $this->authorizeRegion($event, $source->eventRegion()->with('events')->firstOrFail(), request()->user());
    }

    public function assignManager(Request $request, Event $event, EventRegion $eventRegion, RegionManagerAccessService $access)
    {
        abort_unless((int) $eventRegion->event_id === (int) $event->id && $event->isTeam(), 404);
        abort_unless($access->isEventManager($request->user(), $event), 403);
        $data = $request->validate([
            'use_default' => ['nullable', 'boolean'],
            'manager_user_id' => ['nullable', 'integer', 'exists:users,id'],
            // Retained for compatibility with forms submitted before the searchable selector was added.
            'manager_email' => ['nullable', 'email:rfc', 'max:255'],
        ]);
        if ((bool) ($data['use_default'] ?? false)) {
            EventRegionManager::query()->where('event_region_id', $eventRegion->id)->delete();
            return back()->with('success', 'This region now uses the default series event organizer.');
        }
        $user = isset($data['manager_user_id'])
            ? User::query()->find($data['manager_user_id'])
            : User::query()->whereRaw('LOWER(email) = ?', [mb_strtolower(trim($data['manager_email'] ?? ''))])->first();
        if (! $user) {
            throw ValidationException::withMessages(['manager_user_id' => 'Select a system user for this region. The user does not need a player profile.']);
        }
        EventRegionManager::updateOrCreate(
            ['event_region_id' => $eventRegion->id],
            ['event_id' => $event->id, 'region_id' => $eventRegion->region_id, 'user_id' => $user->id, 'assigned_by' => $request->user()->id]
        );
        activity('team-selection')->performedOn($eventRegion)->causedBy($request->user())
            ->withProperties(['manager_user_id' => $user->id, 'region_id' => $eventRegion->region_id])
            ->log('assigned regional team manager');

        $regionCount = EventRegionManager::query()->where('event_id', $event->id)->where('user_id', $user->id)->count();
        $scope = $access->isEventManager($user, $event)
            ? 'This account already has event-wide organizer access.'
            : ($regionCount === 1 ? 'Access is limited to this region.' : "This account is now assigned to {$regionCount} regions in this event.");

        return back()->with('success', "{$user->email} was assigned. {$scope}");
    }

    public function searchUsers(Request $request, Event $event, RegionManagerAccessService $access)
    {
        abort_unless($event->isTeam(), 404);
        abort_unless($access->isEventManager($request->user(), $event), 403);
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $query = trim($data['q']);
        if (mb_strlen($query) < 2) {
            throw ValidationException::withMessages(['q' => 'Enter at least two characters to search users.']);
        }

        $users = User::query()
            ->where(function ($userQuery) use ($query): void {
                $userQuery->where('name', 'like', "%{$query}%")
                    ->orWhere('email', 'like', "%{$query}%");
            })
            ->orderBy('name')
            ->orderBy('email')
            ->limit(20)
            ->get(['id', 'name', 'email']);

        return response()->json([
            'results' => $users->map(function (User $user): array {
                $name = trim($user->name ?? '');

                return ['id' => $user->id, 'text' => ($name !== '' ? "{$name} · " : '').$user->email];
            })->values(),
        ]);
    }

    public function replace(Request $request, Event $event, TeamSelectionImport $selectionImport, TeamSelectionInvitation $invitation, TeamSelectionInvitationService $service)
    {
        abort_unless((int) $invitation->import_id === (int) $selectionImport->id, 404);
        $this->authorizeImport($event, $selectionImport, $request->user());
        $data = $request->validate([
            'replacement_mode' => ['nullable', 'in:next_reserve,custom_profile'],
            'replacement_player_id' => ['nullable', 'required_if:replacement_mode,custom_profile', 'integer', 'exists:players,id'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $mode = $data['replacement_mode'] ?? 'next_reserve';
        $replacement = $mode === 'custom_profile'
            ? $service->replaceWithSystemPlayer(
                $invitation,
                Player::query()->findOrFail((int) $data['replacement_player_id']),
                $request->user(),
                trim($data['reason'])
            )
            : $service->replaceWithNextReserve($invitation, $request->user(), trim($data['reason']));

        $delivery = $selectionImport->status === 'sent' ? ' and queued for a replacement invitation' : '';

        return back()->with('success', ($replacement->player?->full_name ?? 'The replacement player').' was selected'.$delivery.'.');
    }

    public function moveRosterRank(Request $request, Event $event, TeamSelectionImport $selectionImport, TeamSelectionInvitation $invitation, TeamSelectionInvitationService $service)
    {
        abort_unless((int) $invitation->import_id === (int) $selectionImport->id, 404);
        $this->authorizeImport($event, $selectionImport, $request->user());
        $data = $request->validate(['direction' => ['required', 'in:up,down']]);
        $service->moveRosterRank($invitation, $request->user(), $data['direction']);

        return back()->with('success', 'Regional roster order updated.');
    }

    public function promoteReserveManually(Request $request, Event $event, TeamSelectionImport $selectionImport, TeamSelectionInvitation $invitation, TeamSelectionInvitationService $service)
    {
        abort_unless((int) $invitation->import_id === (int) $selectionImport->id, 404);
        $this->authorizeImport($event, $selectionImport, $request->user());
        $replacement = $service->promoteNextReserveManually($invitation, $request->user());

        return back()->with('success', ($replacement->player?->full_name ?? 'The next reserve').' was promoted and the replacement invitation was queued.');
    }

    public function activateReserve(Request $request, Event $event, TeamSelectionImport $selectionImport, TeamSelectionInvitation $invitation, TeamSelectionInvitationService $service)
    {
        abort_unless((int) $invitation->import_id === (int) $selectionImport->id, 404);
        $this->authorizeImport($event, $selectionImport, $request->user());
        $activated = $service->activateReserveInOpenPlace($invitation, $request->user());
        $delivery = $selectionImport->status === 'sent' ? ' The invitation email was queued.' : '';

        return back()->with('success', ($activated->player?->full_name ?? 'The reserve')
            .' is now active at Rank '.$activated->roster_rank.'.'.$delivery);
    }

    public function searchPlayers(Request $request, Event $event, TeamSelectionImport $selectionImport, Team $team)
    {
        $this->authorizeTeamImport($event, $selectionImport, $team, $request->user());
        $data = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $query = trim($data['q']);

        $existingPlayerIds = $selectionImport->invitations()
            ->where('team_id', $team->id)
            ->pluck('player_id');
        $searchTerms = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [$query];
        $players = Player::query()->with(['user:id,email', 'users:id,email'])
            ->whereNotIn('id', $existingPlayerIds)
            ->where(function ($playerQuery) use ($searchTerms): void {
                foreach ($searchTerms as $term) {
                    $playerQuery->where(function ($termQuery) use ($term): void {
                        $termQuery->where('name', 'like', "%{$term}%")
                            ->orWhere('surname', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%")
                            ->orWhere('cellNr', 'like', "%{$term}%")
                            ->orWhereHas('user', fn ($userQuery) => $userQuery->where('email', 'like', "%{$term}%"))
                            ->orWhereHas('users', fn ($userQuery) => $userQuery->where('email', 'like', "%{$term}%"));
                    });
                }
            })
            ->orderBy('surname')->orderBy('name')->limit(40)->get()
            ->map(function (Player $player): array {
                $email = $this->contacts->primaryEmail($player);
                $contact = $email ?: (filled($player->cellNr) ? $player->cellNr : 'No email or cell');

                return [
                    'id' => $player->id,
                    'text' => trim($player->full_name).' · '.$contact,
                ];
            })->take(20)->values();

        return response()->json(['results' => $players]);
    }

    public function addPlayer(Request $request, Event $event, TeamSelectionImport $selectionImport, Team $team, TeamSelectionInvitationService $service)
    {
        $this->authorizeTeamImport($event, $selectionImport, $team, $request->user());
        $data = $request->validate([
            'player_id' => ['required', 'integer', 'exists:players,id'],
            'reason' => ['required', 'string', 'max:1000'],
            'add_team_id' => ['nullable', 'integer'],
        ]);
        $player = Player::query()->findOrFail($data['player_id']);
        $invitation = $service->addSystemPlayerAsReserve(
            $selectionImport,
            $team,
            $player,
            $request->user(),
            trim($data['reason'])
        );

        return back()->with('success', $invitation->player->full_name.' was added as the next reserve. The published ranking snapshot and active roster were not changed.');
    }

    public function viewSentInvitation(Request $request, Event $event, TeamSelectionImport $selectionImport, TeamSelectionInvitation $invitation, TeamSelectionInvitationService $service)
    {
        abort_unless((int) $invitation->import_id === (int) $selectionImport->id, 404);
        $this->authorizeImport($event, $selectionImport, $request->user());
        abort_unless($selectionImport->status === 'sent', 404);
        $invitation->loadMissing(['selectionImport.event', 'region', 'team', 'player']);
        $campaign = $service->savedCampaign($selectionImport);
        $kind = $invitation->promoted_from_id ? 'replacement' : 'invitation';
        $subject = $campaign['subject'] ?? $selectionImport->email_subject;
        $previewOnly = false;

        return view('backend.team-selection.email-preview', compact('invitation', 'campaign', 'kind', 'subject', 'previewOnly'));
    }

    public function resendInvitation(Request $request, Event $event, TeamSelectionImport $selectionImport, TeamSelectionInvitation $invitation, TeamSelectionInvitationService $service)
    {
        abort_unless((int) $invitation->import_id === (int) $selectionImport->id, 404);
        $this->authorizeImport($event, $selectionImport, $request->user());
        $email = $service->resendInvitation($invitation, $request->user());

        return back()->with('success', "Invitation re-queued for {$email} using the saved campaign message.");
    }

    public function sendRosterMessage(Request $request, Event $event, EventRegion $eventRegion, BulkMailDispatcher $mailer)
    {
        $this->authorizeRegion($event, $eventRegion, $request->user());
        $data = $request->validate([
            'target_type' => ['required', 'in:region,team,player,pending_imported'],
            'team_id' => ['nullable', 'required_if:target_type,team,player', 'integer', 'exists:teams,id'],
            'invitation_id' => ['nullable', 'integer', 'exists:team_selection_invitations,id'],
            'subject' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string', 'max:20000'],
            'confirm_recipients' => ['accepted'],
            'recipient_hash' => ['nullable', 'string', 'size:64'],
        ]);

        $team = null;
        if (in_array($data['target_type'], ['team', 'player'], true)) {
            $team = Team::query()->withoutGlobalScopes()->with('category.event')->findOrFail($data['team_id']);
            abort_unless((int) $team->region_id === (int) $eventRegion->region_id
                && (int) $team->category?->event_id === (int) $event->id, 404);
        }

        $invitationId = $data['target_type'] === 'player' ? (int) ($data['invitation_id'] ?? 0) : null;
        $recipients = $data['target_type'] === 'pending_imported'
            ? $this->pendingImportedRecipients($event, $eventRegion)
            : $this->rosterRecipients($event, $eventRegion, $team?->id, $invitationId);
        if ($recipients->isEmpty()) {
            throw ValidationException::withMessages(['message' => 'No matching recipient has a valid email address.']);
        }
        if (in_array($data['target_type'], ['region', 'pending_imported'], true)) {
            $currentHash = hash('sha256', $recipients->pluck('email')->toJson());
            if (! hash_equals($currentHash, (string) ($data['recipient_hash'] ?? ''))) {
                throw ValidationException::withMessages(['confirm_recipients' => 'The regional recipient list changed. Review the current list and confirm again.']);
            }
        }

        $related = in_array($data['target_type'], ['region', 'pending_imported'], true)
            ? $eventRegion
            : ($data['target_type'] === 'player'
                ? TeamSelectionInvitation::query()->findOrFail($invitationId)
                : $team);
        $mailType = in_array($data['target_type'], ['region', 'pending_imported'], true) ? 'region_email' : 'team_email';
        $stats = $mailer->dispatch($mailType, $related, $recipients, [
            'subject' => trim($data['subject']),
            'message' => $data['message'],
            'from_name' => $request->user()->name ?: 'Regional team manager',
            'reply_to' => $request->user()->email,
        ], true);
        activity('team-selection')->performedOn($related)->causedBy($request->user())
            ->withProperties(['target_type' => $data['target_type'], 'queued' => $stats['queued'], 'region_id' => $eventRegion->region_id])
            ->log(match ($data['target_type']) {
                'pending_imported' => 'regional manager emailed imported players still needing account or payment completion',
                'region' => 'regional manager emailed all active selected players in region',
                default => 'regional manager emailed selected team roster',
            });

        return back()->with('success', "Queued {$stats['queued']} email(s) for the reviewed recipient list.");
    }

    public function storeAnnouncement(Request $request, Event $event, EventRegion $eventRegion, BulkMailDispatcher $mailer)
    {
        $this->authorizeRegion($event, $eventRegion, $request->user());
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:20000'],
            'send_email' => ['nullable', 'boolean'],
            'confirm_recipients' => ['nullable', 'accepted_if:send_email,1'],
            'recipient_hash' => ['nullable', 'string', 'size:64'],
        ]);
        if (trim(html_entity_decode(strip_tags($data['message']), ENT_QUOTES | ENT_HTML5, 'UTF-8')) === '') {
            throw ValidationException::withMessages(['message' => 'Enter an announcement message.']);
        }
        $recipients = $this->announcementRecipients($event, $eventRegion);
        if ((bool) ($data['send_email'] ?? false)) {
            if ($recipients->isEmpty()) {
                throw ValidationException::withMessages(['send_email' => 'There are no active selected-player email addresses in this region.']);
            }
            $currentHash = hash('sha256', $recipients->toJson());
            if (! hash_equals($currentHash, (string) ($data['recipient_hash'] ?? ''))) {
                throw ValidationException::withMessages(['send_email' => 'The recipient list changed. Review the current recipients and confirm again.']);
            }
        }
        $announcement = TeamSelectionRegionAnnouncement::create([
            'event_id' => $event->id, 'event_region_id' => $eventRegion->id,
            'region_id' => $eventRegion->region_id, 'created_by' => $request->user()->id,
            'title' => $data['title'], 'message' => $data['message'],
        ]);
        $queued = 0;
        if ((bool) ($data['send_email'] ?? false)) {
            $stats = $mailer->dispatch('event_announcement', $announcement, $recipients, [
                'event_name' => $event->name.' · '.$eventRegion->region?->region_name,
                'title' => $data['title'], 'message' => $announcement->message,
            ]);
            $queued = (int) $stats['queued'];
            if ($queued > 0) $announcement->update(['emailed_at' => now()]);
        }
        activity('team-selection')->performedOn($announcement)->causedBy($request->user())
            ->withProperties(['queued' => $queued, 'region_id' => $eventRegion->region_id])->log('published regional team announcement');

        return back()->with('success', "Regional announcement published; {$queued} email(s) queued.");
    }

    public function retryAnnouncement(Request $request, Event $event, EventRegion $eventRegion, TeamSelectionRegionAnnouncement $announcement, BulkMailDispatcher $mailer)
    {
        abort_unless((int) $announcement->event_region_id === (int) $eventRegion->id, 404);
        $this->authorizeRegion($event, $eventRegion, $request->user());
        $currentRecipients = $this->announcementRecipients($event, $eventRegion);
        $failedRecipients = $announcement->emailLogs()->where('status', 'failed')
            ->pluck('recipient_email')->map(fn ($email) => mb_strtolower(trim((string) $email)))->unique();
        $recipients = $currentRecipients->intersect($failedRecipients)->values();
        $stats = $mailer->dispatch('event_announcement', $announcement, $recipients, [
            'event_name' => $event->name.' · '.$eventRegion->region?->region_name,
            'title' => $announcement->title, 'message' => $announcement->message,
        ]);

        return back()->with('success', "Re-queued {$stats['queued']} failed email(s) for currently active recipients.");
    }

    public function updateTeam(
        Request $request,
        Event $event,
        EventRegion $eventRegion,
        Team $team,
        TeamSelectionInvitationService $service,
    )
    {
        $this->authorizeRegion($event, $eventRegion, $request->user());
        $team->loadMissing('category.event');
        abort_unless((int) $team->region_id === (int) $eventRegion->region_id
            && (int) $team->category?->event_id === (int) $event->id, 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'num_team_members' => ['required', 'integer', 'min:1', 'max:50'],
            'published' => ['required', 'boolean'],
        ]);

        $result = $service->updateTeamSettings(
            $team,
            $event,
            (int) $eventRegion->region_id,
            [
                'name' => trim($data['name']),
                'num_team_members' => (int) $data['num_team_members'],
                'published' => (bool) $data['published'],
            ],
            $request->user(),
        );

        $reserveMessage = match ($result['moved_to_reserves']) {
            0 => '',
            1 => ' 1 player was moved to the reserve queue.',
            default => " {$result['moved_to_reserves']} players were moved to the reserve queue.",
        };

        return back()->with('success', "Regional team details updated to {$data['num_team_members']} player places.{$reserveMessage}");
    }

    public function enrichImportedContacts(
        Request $request,
        Event $event,
        EventRegion $eventRegion,
        ExternalTeamWorkbookParser $parser,
        ImportedRosterContactEnrichmentService $contacts,
    ) {
        $this->authorizeRegion($event, $eventRegion, $request->user());
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xls,xlsx,csv', 'max:5120'],
            'expected_players' => ['required', 'integer', 'min:1', 'max:50'],
            'confirmed' => ['nullable', 'boolean'],
            'preview_fingerprint' => ['nullable', 'string', 'size:64'],
            'confirm_anomalies' => ['sometimes', 'boolean'],
        ]);
        $parsed = $parser->parse($request->file('file')->getRealPath(), (int) $data['expected_players']);
        if ($parsed['errors'] !== []) {
            throw ValidationException::withMessages(['file' => $parsed['errors']]);
        }
        $preview = $contacts->preview($event, $eventRegion, $parsed['teams']);

        if (! $request->boolean('confirmed')) {
            return response()->json([
                'requires_confirmation' => true,
                'message' => 'Review the email-only changes before applying them.',
                'updates' => $preview['updates'],
                'issues' => $preview['issues'],
                'unchanged_count' => $preview['unchanged'],
                'without_email_count' => $preview['withoutEmail'],
                'preview_fingerprint' => $preview['fingerprint'],
            ]);
        }
        if ($preview['issues'] !== [] && ! $request->boolean('confirm_anomalies')) {
            throw ValidationException::withMessages(['confirm_anomalies' => 'Review and acknowledge the flagged rows. They will be skipped, not changed.']);
        }

        $updated = $contacts->apply($event, $eventRegion, $preview, (string) ($data['preview_fingerprint'] ?? ''), $request->user());

        return response()->json(['requires_confirmation' => false, 'message' => "Added {$updated} missing roster email(s). No other roster data was changed."]);
    }

    public function updateImportedPlayer(
        Request $request,
        Event $event,
        EventRegion $eventRegion,
        Team $team,
        NoProfileTeamPlayer $noProfileTeamPlayer,
        ImportedTeamRosterService $rosters,
    ) {
        $this->authorizeImportedRosterSlot($event, $eventRegion, $team, $noProfileTeamPlayer, $request->user());
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'surname' => ['required', 'string', 'max:100'],
        ]);
        $rosters->rename($event, $noProfileTeamPlayer, $data['name'], $data['surname'], $request->user());

        $message = 'The imported roster name was updated. Its profile-link status was not changed.';
        return $request->expectsJson()
            ? response()->json(['message' => $message, 'player' => ['id' => $noProfileTeamPlayer->id, 'name' => trim($data['name']), 'surname' => trim($data['surname'])]])
            : back()->with('success', $message);
    }

    public function moveImportedPlayer(
        Request $request,
        Event $event,
        EventRegion $eventRegion,
        Team $team,
        NoProfileTeamPlayer $noProfileTeamPlayer,
        ImportedTeamRosterService $rosters,
    ) {
        $this->authorizeImportedRosterSlot($event, $eventRegion, $team, $noProfileTeamPlayer, $request->user());
        $data = $request->validate(['direction' => ['required', 'in:up,down']]);
        $rosters->move($event, $noProfileTeamPlayer, $data['direction'], $request->user());

        return back()->with('success', 'The imported roster order was updated.');
    }

    public function reorderImportedPlayers(
        Request $request,
        Event $event,
        EventRegion $eventRegion,
        Team $team,
        ImportedTeamRosterService $rosters,
    ) {
        $firstSlot = $team->team_players_no_profile()->orderBy('rank')->firstOrFail();
        $this->authorizeImportedRosterSlot($event, $eventRegion, $team, $firstSlot, $request->user());
        $data = $request->validate([
            'slot_ids' => ['required', 'array', 'min:1', 'max:50'],
            'slot_ids.*' => ['required', 'integer'],
        ]);
        $rosters->reorder($event, $team->id, $data['slot_ids'], $request->user());

        return response()->json(['message' => 'The imported roster order was updated.']);
    }

    public function destroyAnnouncement(Request $request, Event $event, EventRegion $eventRegion, TeamSelectionRegionAnnouncement $announcement)
    {
        abort_unless((int) $announcement->event_region_id === (int) $eventRegion->id, 404);
        $this->authorizeRegion($event, $eventRegion, $request->user());
        $announcement->delete();
        activity('team-selection')->performedOn($announcement)->causedBy($request->user())
            ->withProperties(['region_id' => $eventRegion->region_id])->log('hid regional team announcement');

        return back()->with('success', 'The regional announcement was hidden. Previously sent email is unchanged.');
    }

    private function authorizeImport(Event $event, TeamSelectionImport $selectionImport, User $user): void
    {
        abort_unless((int) $selectionImport->event_id === (int) $event->id, 404);
        $eventRegion = EventRegion::query()->with('events')->where('event_id', $event->id)
            ->where('region_id', $selectionImport->region_id)->firstOrFail();
        $this->authorizeRegion($event, $eventRegion, $user);
    }

    private function authorizeTeamImport(Event $event, TeamSelectionImport $selectionImport, Team $team, User $user): void
    {
        $this->authorizeImport($event, $selectionImport, $user);
        abort_unless((int) $team->region_id === (int) $selectionImport->region_id
            && $selectionImport->invitations()->where('team_id', $team->id)->exists(), 404);
    }

    private function authorizeRegion(Event $event, EventRegion $eventRegion, User $user): void
    {
        abort_unless((int) $eventRegion->event_id === (int) $event->id && $event->isTeam(), 404);
        abort_unless(app(RegionManagerAccessService::class)->canManage($user, $eventRegion), 403);
    }

    /** @return \Illuminate\Support\Collection<int, string> */
    private function announcementRecipients(Event $event, EventRegion $eventRegion)
    {
        return TeamSelectionInvitation::query()->with(['player.user', 'player.users'])
            ->where('event_id', $event->id)->where('region_id', $eventRegion->region_id)
            ->whereIn('status', [TeamSelectionInvitation::INVITED, TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, TeamSelectionInvitation::PAID_CONFIRMED])
            ->whereHas('selectionImport', fn ($query) => $query->where('status', 'sent'))
            ->get()
            ->map(fn (TeamSelectionInvitation $item) => $this->contacts->primaryEmail($item->player))
            ->filter()
            ->unique()->sort()->values();
    }

    private function authorizeImportedRosterSlot(
        Event $event,
        EventRegion $eventRegion,
        Team $team,
        NoProfileTeamPlayer $slot,
        User $user,
    ): void {
        $this->authorizeRegion($event, $eventRegion, $user);
        $team->loadMissing('category.event');
        abort_unless((int) $team->region_id === (int) $eventRegion->region_id
            && (int) $team->category?->event_id === (int) $event->id
            && (int) $slot->team_id === (int) $team->id, 404);
        app(TeamSelectionInvitationService::class)->assertRosterEditable($team);
    }

    private function rosterRecipients(Event $event, EventRegion $eventRegion, ?int $teamId = null, ?int $invitationId = null)
    {
        return TeamSelectionInvitation::query()->with(['player.user', 'player.users'])
            ->where('event_id', $event->id)
            ->where('region_id', $eventRegion->region_id)
            ->whereIn('status', [
                TeamSelectionInvitation::INVITED,
                TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT,
                TeamSelectionInvitation::PAID_CONFIRMED,
            ])
            ->when($teamId, fn ($query) => $query->where('team_id', $teamId))
            ->when($invitationId, fn ($query) => $query->whereKey($invitationId))
            ->get()
            ->map(function (TeamSelectionInvitation $invitation): array {
                $email = $this->contacts->primaryEmail($invitation->player);

                return ['email' => $email, 'name' => $invitation->player?->full_name];
            })
            ->filter(fn (array $recipient) => filled($recipient['email']))
            ->unique('email')
            ->sortBy('email')
            ->values();
    }

    private function pendingImportedRecipients(Event $event, EventRegion $eventRegion)
    {
        return NoProfileTeamPlayer::query()->with('team.category')
            ->whereHas('team', fn ($query) => $query->where('region_id', $eventRegion->region_id)
                ->whereHas('category', fn ($category) => $category->where('event_id', $event->id)))
            ->where(function ($query): void {
                $query->whereNull('player_profile')->orWhereNotExists(function ($payment): void {
                    $payment->select(DB::raw(1))->from('team_players')
                        ->whereColumn('team_players.team_id', 'no_profile_team_players.team_id')
                        ->whereColumn('team_players.rank', 'no_profile_team_players.rank')
                        ->where('team_players.pay_status', 1);
                });
            })
            ->get()
            ->map(fn (NoProfileTeamPlayer $slot): array => [
                'email' => mb_strtolower(trim((string) $slot->email)),
                'name' => trim($slot->name.' '.$slot->surname),
            ])
            ->filter(fn (array $recipient) => filter_var($recipient['email'], FILTER_VALIDATE_EMAIL))
            ->unique('email')->sortBy('email')->values();
    }

    private function communicationData(Request $request): array
    {
        return $request->validate([
            'response_deadline' => ['required', 'date'],
            'payment_deadline' => ['required', 'date', 'after_or_equal:response_deadline'],
            'replacement_payment_deadline' => ['nullable', 'date', 'after_or_equal:payment_deadline'],
            'email_subject' => ['required', 'string', 'max:180'],
            'email_message' => ['required', 'string', 'max:10000'],
            'event_information' => ['nullable', 'string', 'max:20000'],
            'reply_to' => ['nullable', 'email:rfc', 'max:255'],
            'include_clothing' => ['nullable', 'boolean'],
        ]);
    }
}
