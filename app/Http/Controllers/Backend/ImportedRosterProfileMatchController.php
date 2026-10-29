<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventConvenor;
use App\Models\NoProfileTeamPlayer;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamRegion;
use App\Services\TeamSelection\ImportedRosterProfileMatchService;
use App\Services\TeamSelection\ImportedTeamRosterService;
use App\Services\TeamSelection\RegionManagerAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ImportedRosterProfileMatchController extends Controller
{
    private function authorizeTeam(Request $request, Event $event, Team $team): void
    {
        abort_unless(app(RegionManagerAccessService::class)->canManageTeam($request->user(), $team, $event), 403);
        abort_unless($team->noProfile && $team->category()->where('event_id', $event->id)->exists(), 404);
    }

    private function source(Request $request, Event $event): ?Event
    {
        if (! $request->filled('source_event_id')) return null;
        $source = Event::findOrFail($request->integer('source_event_id'));
        abort_unless(app(RegionManagerAccessService::class)->isEventManager($request->user(), $source), 403);
        abort_unless($source->id !== $event->id && $source->start_date < $event->start_date, 422);

        return $source;
    }

    private function authorizeRegion(Request $request, Event $event, TeamRegion $region): void
    {
        abort_unless(app(RegionManagerAccessService::class)->managedRegionIds($request->user(), $event)->contains((int) $region->id), 403);
        abort_unless(DB::table('event_regions')->where('event_id', $event->id)->where('region_id', $region->id)->exists(), 404);
    }

    public function index(Request $request, Event $event, Team $team, ImportedRosterProfileMatchService $matches)
    {
        $this->authorizeTeam($request, $event, $team);
        return $this->render($request, $event, $team, $matches);
    }

    public function regionIndex(Request $request, Event $event, TeamRegion $region, ImportedRosterProfileMatchService $matches)
    {
        $this->authorizeRegion($request, $event, $region);
        return $this->render($request, $event, $region, $matches);
    }

    private function render(Request $request, Event $event, Team|TeamRegion $scope, ImportedRosterProfileMatchService $matches)
    {
        $request->validate(['source_event_id' => ['nullable', 'integer', 'min:1'], 'q' => ['nullable', 'string', 'max:100'], 'roster_page' => ['nullable', 'integer', 'min:1']]);
        $source = $this->source($request, $event);
        $events = Event::query()->whereKeyNot($event->id)->where('start_date', '<', $event->start_date)
            ->when(! $request->user()->hasRole('super-user'), function ($query) use ($request): void {
                $query->where(fn ($q) => $q->whereHas('admins', fn ($admin) => $admin->where('users.id', $request->user()->id))
                    ->orWhereIn('events.id', EventConvenor::query()->select('event_id')->where('user_id', $request->user()->id)->active()
                        ->whereNotIn('event_id', EventConvenor::query()->select('event_id')->where('user_id', $request->user()->id)->active()->where('role', 'score-keeper'))));
            })->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%'.$request->string('q').'%'))
            ->orderByDesc('start_date')->orderByDesc('id')->paginate(20)->withQueryString();
        $page = max(1, $request->integer('roster_page', 1));
        $preview = $source ? $matches->preview($scope, $source, $page, $event) : null;
        $fingerprint = $preview ? $matches->fingerprint($preview, $event, $source, $scope, $request->user()->id, $page) : null;
        $isRegion = $scope instanceof TeamRegion;
        $scopeName = $isRegion ? $scope->region_name.' · all imported teams' : $scope->name;
        $regionId = $isRegion ? $scope->id : $scope->region_id;
        $routePrefix = $isRegion ? 'backend.team-selection.region-profile-matches.' : 'backend.team-selection.profile-matches.';
        $storeUrl = route($routePrefix.'store', [$event, $scope]);

        return response()->view('backend.team-selection.profile-matches', compact('event', 'scope', 'scopeName', 'regionId', 'isRegion', 'storeUrl', 'source', 'events', 'preview', 'fingerprint', 'page'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, Event $event, Team $team, ImportedRosterProfileMatchService $matches, ImportedTeamRosterService $rosters)
    {
        $this->authorizeTeam($request, $event, $team);
        return $this->confirm($request, $event, $team, $matches, $rosters);
    }

    public function regionStore(Request $request, Event $event, TeamRegion $region, ImportedRosterProfileMatchService $matches, ImportedTeamRosterService $rosters)
    {
        $this->authorizeRegion($request, $event, $region);
        return $this->confirm($request, $event, $region, $matches, $rosters);
    }

    private function confirm(Request $request, Event $event, Team|TeamRegion $scope, ImportedRosterProfileMatchService $matches, ImportedTeamRosterService $rosters)
    {
        $data = $request->validate([
            'source_event_id' => ['required', 'integer', 'min:1'], 'roster_page' => ['required', 'integer', 'min:1'],
            'fingerprint' => ['required', 'string', 'size:64'], 'confirm_links' => ['accepted'],
            'links' => ['required', 'array', 'max:'.$matches->pageSize($scope)], 'links.*' => ['nullable', 'integer', 'min:1', 'distinct'],
        ]);
        $source = $this->source($request, $event);
        $count = DB::transaction(function () use ($request, $event, $scope, $source, $matches, $rosters, $data): int {
            foreach (collect([$event->id, $source->id])->sort()->all() as $eventId) {
                app(\App\Services\TeamDrawAdaptationService::class)->lockEvent($eventId);
            }
            $lockedTeams = Team::query()->without(['team_players', 'team_players_no_profile'])->where('noProfile', true)
                ->whereHas('category', fn ($q) => $q->where('event_id', $event->id))
                ->when($scope instanceof Team, fn ($q) => $q->whereKey($scope->id), fn ($q) => $q->where('region_id', $scope->id))
                ->orderBy('id')->lockForUpdate()->get();
            if ($scope instanceof Team) {
                abort_unless($lockedTeams->isNotEmpty(), 404);
                $this->authorizeTeam($request, $event, $lockedTeams->first());
            }
            else $this->authorizeRegion($request, $event, $scope);
            // All scoped identities inform off-page collision warnings and are locked before the snapshot.
            $matches->rosterQuery($scope, $event)->select('no_profile_team_players.id')->orderBy('id')->lockForUpdate()->get();
            $preview = $matches->preview($scope, $source, (int) $data['roster_page'], $event);
            $previewCandidates = $preview['rows']->flatMap(fn ($row) => $row['candidates'])->keyBy('id');
            $candidateIds = $previewCandidates->keys()->sort()->values();
            $lockedCandidates = Player::query()->whereIn('id', $candidateIds)->orderBy('id')->lockForUpdate()->get();
            $identityFields = ['id', 'name', 'surname', 'dateOfBirth', 'email', 'gender'];
            if ($lockedCandidates->count() !== $previewCandidates->count()
                || $lockedCandidates->contains(fn ($player) => $player->only($identityFields) !== $previewCandidates[$player->id]->only($identityFields))) {
                throw ValidationException::withMessages(['links' => 'A matching profile changed. Check the event again before confirming.']);
            }
            $preview = $matches->preview($scope, $source, (int) $data['roster_page'], $event);
            if (! hash_equals($matches->fingerprint($preview, $event, $source, $scope, $request->user()->id, (int) $data['roster_page']), $data['fingerprint'])) {
                throw ValidationException::withMessages(['links' => 'The roster or matching profiles changed. Check the event again before confirming.']);
            }
            $count = 0;
            foreach (array_filter($data['links']) as $slotId => $playerId) {
                $row = $preview['rows']->first(fn ($row) => (int) $row['slot']->id === (int) $slotId);
                $candidate = $row ? $row['candidates']->firstWhere('id', (int) $playerId) : null;
                if (! $row || $row['slot']->player_profile || $row['overflow'] || !$candidate || $candidate->link_problems
                    || ! $matches->participants($source)->whereKey($playerId)->exists()) {
                    throw ValidationException::withMessages(['links' => 'Choose a current matching participant for an unlinked roster player.']);
                }
                $rosters->relink($event, $row['slot'], (int) $playerId, 0, (int) $row['slot']->rank, $request->user());
                $count++;
            }
            if (! $count) throw ValidationException::withMessages(['links' => 'Choose at least one profile to link.']);
            activity('team-roster')->performedOn($scope)->causedBy($request->user())->withProperties([
                'event_id' => $event->id, 'source_event_id' => $source->id, 'scope_type' => $scope::class, 'scope_id' => $scope->id, 'confirmed_links' => array_filter($data['links']),
            ])->log('administrator confirmed imported roster profiles from previous event');

            return $count;
        });

        $route = $scope instanceof TeamRegion ? 'backend.team-selection.region-profile-matches.index' : 'backend.team-selection.profile-matches.index';
        return redirect()->route($route, [$event, $scope, 'source_event_id' => $source->id, 'roster_page' => $data['roster_page']])
            ->with('success', "Linked {$count} player profile(s). Imported order and account ownership were preserved.");
    }
}
