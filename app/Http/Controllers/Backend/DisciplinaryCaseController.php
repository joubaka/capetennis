<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\DisciplinaryCase;
use App\Models\DisciplinaryCaseAssignment;
use App\Models\Event;
use App\Models\Player;
use App\Models\TeamPlayer;
use App\Models\User;
use App\Models\ViolationType;
use App\Services\DisciplinaryCaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DisciplinaryCaseController extends Controller
{
    public function __construct(private DisciplinaryCaseService $service) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $query = DisciplinaryCase::with(['event', 'player', 'charges'])->latest('incident_at');
        if (! $user->hasRole('super-user')) {
            $query->where(function ($q) use ($user) {
                $q->whereHas('event.admins', fn ($admins) => $admins->where('users.id', $user->id))
                    ->orWhereHas('assignments', fn ($assignments) => $assignments->where('user_id', $user->id));
            });
        }
        if ($request->filled('event_id')) $query->where('event_id', $request->integer('event_id'));
        if ($request->filled('status')) $query->where('status', $request->string('status'));
        if ($request->filled('player_id')) $query->where('player_id', $request->integer('player_id'));

        $cases = $query->paginate(25)->withQueryString();
        $events = Event::visibleTo($user)->latest('id')->limit(100)->get();
        return view('backend.disciplinary.cases.index', compact('cases', 'events'));
    }

    public function eventIndex(Event $event)
    {
        $this->authorize('event.manage', $event);
        $cases = DisciplinaryCase::with(['player', 'charges'])->where('event_id', $event->id)
            ->latest('incident_at')->paginate(25);
        return view('backend.disciplinary.cases.index', compact('cases', 'event') + ['events' => collect([$event])]);
    }

    public function create(Event $event)
    {
        $this->authorize('event.manage', $event);
        $teamPlayerIds = TeamPlayer::whereHas('team.category', fn ($q) => $q->where('event_id', $event->id))
            ->pluck('player_id');
        $players = Player::where(function ($query) use ($event, $teamPlayerIds) {
            $query->whereHas('registrations.categoryEvents', fn ($q) => $q->where('category_events.event_id', $event->id))
                ->orWhereIn('id', $teamPlayerIds);
        })
            ->orderBy('surname')->orderBy('name')->get();
        $violationTypes = ViolationType::active()->orderBy('name')->get();
        $categories = $event->categories()->orderBy('name')->get();
        $fixtures = \App\Models\Fixture::whereHas('draw', fn ($q) => $q->where('event_id', $event->id))
            ->with(['registration1.players', 'registration2.players'])->latest('id')->limit(250)->get();
        return view('backend.disciplinary.cases.create', compact('event', 'players', 'violationTypes', 'categories', 'fixtures'));
    }

    public function store(Request $request, Event $event)
    {
        $this->authorize('event.manage', $event);
        $data = $request->validate([
            'player_id' => ['required', 'integer', 'exists:players,id'],
            'category_event_id' => ['nullable', 'integer', 'exists:category_events,id'],
            'fixture_id' => ['nullable', 'integer', 'exists:fixtures,id'],
            'violation_type_id' => ['nullable', 'integer', 'exists:violation_types,id'],
            'rule_title' => ['required_without:violation_type_id', 'nullable', 'string', 'max:255'],
            'rule_code' => ['nullable', 'string', 'max:100'],
            'severity' => ['required', Rule::in(['standard', 'serious', 'urgent'])],
            'incident_at' => ['required', 'date', 'before_or_equal:now'],
            'incident_location' => ['nullable', 'string', 'max:255'],
            'summary' => ['required', 'string', 'max:5000'],
            'allegation' => ['nullable', 'string', 'max:5000'],
            'statement' => ['nullable', 'string', 'max:10000'],
        ]);
        $case = $this->service->report($event, Player::findOrFail($data['player_id']), $request->user(), $data);
        return redirect()->route('backend.disciplinary.cases.show', $case)->with('success', 'Incident submitted for disciplinary triage.');
    }

    public function show(DisciplinaryCase $case)
    {
        $this->authorize('view', $case);
        $case->load(['event', 'player.users', 'reporter', 'charges.violationType', 'evidence.submitter',
            'assignments.user', 'decisions.sanctions', 'appeals', 'timeline.actor']);
        $panelCandidates = User::role(['admin', 'super-user'])->orderBy('name')->get();
        return view('backend.disciplinary.cases.show', compact('case', 'panelCandidates'));
    }

    public function triage(Request $request, DisciplinaryCase $case)
    {
        $this->authorize('manage', $case);
        $data = $request->validate(['action' => ['required', Rule::in(['proceed', 'dismiss'])], 'reason' => ['nullable', 'string', 'max:3000']]);
        $this->service->triage($case, $request->user(), $data['action'], $data['reason'] ?? null);
        return back()->with('success', $data['action'] === 'dismiss' ? 'Case dismissed with an audit record.' : 'Notice issued; player response is now due.');
    }

    public function appointPanel(Request $request, DisciplinaryCase $case)
    {
        $this->authorize('manage', $case);
        $data = $request->validate([
            'members' => ['required', 'array', 'min:3'],
            'members.*.user_id' => ['required', 'integer', 'exists:users,id'],
            'members.*.role' => ['required', Rule::in(['chair', 'member'])],
        ]);
        $this->service->appointPanel($case, $request->user(), $data['members']);
        return back()->with('success', 'Independent panel appointed.');
    }

    public function declareConflict(Request $request, DisciplinaryCase $case, DisciplinaryCaseAssignment $assignment)
    {
        abort_unless($assignment->disciplinary_case_id === $case->id && $assignment->user_id === $request->user()->id, 403);
        $data = $request->validate(['conflict' => ['required', 'boolean'], 'notes' => ['nullable', 'string', 'max:2000']]);
        $this->service->declareConflict($assignment, $request->user(), (bool) $data['conflict'], $data['notes'] ?? null);
        return back()->with('success', 'Conflict declaration recorded.');
    }

    public function decide(Request $request, DisciplinaryCase $case)
    {
        $this->authorize('decide', $case);
        $data = $request->validate([
            'outcome' => ['required', Rule::in(['dismissed', 'upheld', 'partially_upheld'])],
            'reasons' => ['required', 'string', 'min:20', 'max:20000'],
            'sanctions' => ['nullable', 'array'],
            'sanctions.*.type' => ['required', Rule::in(['warning', 'points', 'match_default', 'event_disqualification', 'suspension', 'interim_restriction'])],
            'sanctions.*.scope' => ['required', Rule::in(['match', 'event', 'series', 'global'])],
            'sanctions.*.starts_at' => ['nullable', 'date'],
            'sanctions.*.ends_at' => ['nullable', 'date', 'after_or_equal:sanctions.*.starts_at'],
            'sanctions.*.details' => ['nullable', 'string', 'max:3000'],
        ]);
        $this->service->finalizeDecision($case, $request->user(), $data);
        return back()->with('success', 'Panel decision finalized and sanctions activated.');
    }

    public function uploadEvidence(Request $request, DisciplinaryCase $case)
    {
        $this->authorize('view', $case);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'evidence_file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx,txt'],
        ]);
        $file = $data['evidence_file'];
        $path = $file->store('disciplinary/'.$case->case_number);
        $case->evidence()->create([
            'submitted_by' => $request->user()->id,
            'kind' => 'attachment', 'title' => $data['title'], 'file_path' => $path,
            'original_name' => $file->getClientOriginalName(), 'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(), 'visibility' => 'panel',
        ]);
        return back()->with('success', 'Evidence uploaded securely.');
    }

    public function downloadEvidence(DisciplinaryCase $case, \App\Models\DisciplinaryEvidence $evidence)
    {
        $this->authorize('view', $case);
        abort_unless($evidence->disciplinary_case_id === $case->id && $evidence->file_path, 404);
        return Storage::download($evidence->file_path, $evidence->original_name);
    }

    public function decideAppeal(Request $request, \App\Models\DisciplinaryAppeal $appeal)
    {
        abort_unless($request->user()->hasRole('super-user'), 403);
        $data = $request->validate([
            'outcome' => ['required', Rule::in(['confirmed', 'varied', 'overturned'])],
            'reasons' => ['required', 'string', 'min:20', 'max:20000'],
        ]);
        $this->service->decideAppeal($appeal, $request->user(), $data['outcome'], $data['reasons']);
        return back()->with('success', 'Appeal outcome recorded.');
    }
}
