<?php

declare(strict_types=1);

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventConvenor;
use App\Models\NoProfileTeamPlayer;
use App\Models\Player;
use App\Models\Team;
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
        abort_unless(app(RegionManagerAccessService::class)->isEventManager($request->user(), $event), 403);
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

    public function index(Request $request, Event $event, Team $team, ImportedRosterProfileMatchService $matches)
    {
        $this->authorizeTeam($request, $event, $team);
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
        $preview = $source ? $matches->preview($team, $source, $page) : null;
        $fingerprint = $preview ? $matches->fingerprint($preview, $event, $source, $team, $request->user()->id, $page) : null;

        return response()->view('backend.team-selection.profile-matches', compact('event', 'team', 'source', 'events', 'preview', 'fingerprint', 'page'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, Event $event, Team $team, ImportedRosterProfileMatchService $matches, ImportedTeamRosterService $rosters)
    {
        $this->authorizeTeam($request, $event, $team);
        $data = $request->validate([
            'source_event_id' => ['required', 'integer', 'min:1'], 'roster_page' => ['required', 'integer', 'min:1'],
            'fingerprint' => ['required', 'string', 'size:64'], 'confirm_links' => ['accepted'],
            'links' => ['required', 'array', 'max:25'], 'links.*' => ['nullable', 'integer', 'min:1', 'distinct'],
        ]);
        $source = $this->source($request, $event);
        $count = DB::transaction(function () use ($request, $event, $team, $source, $matches, $rosters, $data): int {
            foreach (collect([$event->id, $source->id])->sort()->all() as $eventId) {
                app(\App\Services\TeamDrawAdaptationService::class)->lockEvent($eventId);
            }
            $lockedTeam = Team::query()->lockForUpdate()->findOrFail($team->id);
            $this->authorizeTeam($request, $event, $lockedTeam);
            NoProfileTeamPlayer::query()->where('team_id', $team->id)->orderBy('rank')->orderBy('id')
                ->offset(((int) $data['roster_page'] - 1) * 25)->limit(25)->lockForUpdate()->get();
            $preview = $matches->preview($team, $source, (int) $data['roster_page']);
            $previewCandidates = $preview['rows']->flatMap(fn ($row) => $row['candidates'])->keyBy('id');
            $candidateIds = $previewCandidates->keys()->sort()->values();
            $lockedCandidates = Player::query()->whereIn('id', $candidateIds)->orderBy('id')->lockForUpdate()->get();
            $identityFields = ['id', 'name', 'surname', 'dateOfBirth', 'email', 'gender'];
            if ($lockedCandidates->count() !== $previewCandidates->count()
                || $lockedCandidates->contains(fn ($player) => $player->only($identityFields) !== $previewCandidates[$player->id]->only($identityFields))) {
                throw ValidationException::withMessages(['links' => 'A matching profile changed. Check the event again before confirming.']);
            }
            $preview = $matches->preview($team, $source, (int) $data['roster_page']);
            if (! hash_equals($matches->fingerprint($preview, $event, $source, $team, $request->user()->id, (int) $data['roster_page']), $data['fingerprint'])) {
                throw ValidationException::withMessages(['links' => 'The roster or matching profiles changed. Check the event again before confirming.']);
            }
            $count = 0;
            foreach (array_filter($data['links']) as $slotId => $playerId) {
                $row = $preview['rows']->first(fn ($row) => (int) $row['slot']->id === (int) $slotId);
                if (! $row || $row['slot']->player_profile || $row['overflow'] || ! $row['candidates']->contains('id', (int) $playerId)
                    || ! $matches->participants($source)->whereKey($playerId)->exists()) {
                    throw ValidationException::withMessages(['links' => 'Choose a current matching participant for an unlinked roster player.']);
                }
                $rosters->relink($event, $row['slot'], (int) $playerId, 0, (int) $row['slot']->rank, $request->user());
                $count++;
            }
            if (! $count) throw ValidationException::withMessages(['links' => 'Choose at least one profile to link.']);
            activity('team-roster')->performedOn($team)->causedBy($request->user())->withProperties([
                'event_id' => $event->id, 'source_event_id' => $source->id, 'confirmed_links' => array_filter($data['links']),
            ])->log('administrator confirmed imported roster profiles from previous event');

            return $count;
        });

        return redirect()->route('backend.team-selection.profile-matches.index', [$event, $team, 'source_event_id' => $source->id, 'roster_page' => $data['roster_page']])
            ->with('success', "Linked {$count} player profile(s). Imported order and account ownership were preserved.");
    }
}
