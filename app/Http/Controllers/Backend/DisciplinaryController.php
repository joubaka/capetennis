<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Player;
use App\Models\PlayerSuspension;
use App\Models\PlayerViolation;
use App\Models\ViolationType;
use App\Services\DisciplinaryService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DisciplinaryController extends Controller
{
    public function __construct(protected DisciplinaryService $service) {}

    /**
     * Paginated log of all violations with filters.
     */
    public function index(Request $request)
    {
        $query = PlayerViolation::with(['player', 'violationType', 'recorder'])
            ->withTrashed(false)
            ->orderByDesc('violation_date');

        if ($request->filled('player_id')) {
            $query->where('player_id', $request->player_id);
        }
        if ($request->filled('violation_type_id')) {
            $query->where('violation_type_id', $request->violation_type_id);
        }
        if ($request->filled('date_from')) {
            $query->where('violation_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('violation_date', '<=', $request->date_to);
        }

        $violations     = $query->paginate(25)->withQueryString();
        $violationTypes = ViolationType::active()->orderBy('name')->get();
        $players        = Player::orderBy('surname')->orderBy('name')->get();

        return view('backend.disciplinary.index', compact('violations', 'violationTypes', 'players'));
    }

    /**
     * Full disciplinary profile for a single player.
     */
    public function playerProfile(int $playerId)
    {
        $player     = Player::findOrFail($playerId);
        $status     = $this->service->getPlayerStatus($player);
        $violations = $player->violations()
            ->with('violationType')
            ->orderByDesc('violation_date')
            ->get();
        $suspensions = $player->suspensions()->orderByDesc('triggered_at')->get();
        $pps         = $this->service->getPpsConsequence($player);

        return view('backend.disciplinary.player', compact('player', 'status', 'violations', 'suspensions', 'pps'));
    }

    /**
     * Show form to record a new violation (optionally pre-selected player).
     */
    public function create(Request $request)
    {
        $players        = Player::orderBy('surname')->orderBy('name')->get();
        $violationTypes = ViolationType::active()->orderBy('name')->get();
        $events         = Event::orderByDesc('id')->take(50)->get();
        $selectedPlayer = $request->player_id ? Player::find($request->player_id) : null;

        return view('backend.disciplinary.create', compact('players', 'violationTypes', 'events', 'selectedPlayer'));
    }

    /**
     * Store a new violation.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'player_id'         => 'required|exists:players,id',
            'violation_type_id' => 'required|exists:violation_types,id',
            'violation_date'    => 'required|date|before_or_equal:today',
            'penalty_type'      => 'nullable|in:warning,point,game,default',
            'points_assigned'   => 'required|integer|min:0|max:100',
            'notes'             => 'nullable|string|max:1000',
            'event_id'          => 'nullable|exists:events,id',
        ]);

        $violation = $this->service->recordViolation($validated);

        return redirect()
            ->route('backend.disciplinary.player', $violation->player_id)
            ->with('success', 'Violation recorded successfully.');
    }

    /**
     * Show form to edit an existing violation.
     */
    public function editViolation(int $id)
    {
        $violation      = PlayerViolation::with('violationType')->findOrFail($id);
        $violationTypes = ViolationType::active()->orderBy('name')->get();
        $events         = Event::orderByDesc('id')->take(50)->get();

        return view('backend.disciplinary.edit', compact('violation', 'violationTypes', 'events'));
    }

    /**
     * Update an existing violation.
     */
    public function updateViolation(Request $request, int $id)
    {
        $violation = PlayerViolation::findOrFail($id);

        $validated = $request->validate([
            'violation_type_id' => 'required|exists:violation_types,id',
            'violation_date'    => 'required|date|before_or_equal:today',
            'penalty_type'      => 'nullable|in:warning,point,game,default',
            'points_assigned'   => 'required|integer|min:0|max:100',
            'notes'             => 'nullable|string|max:1000',
            'event_id'          => 'nullable|exists:events,id',
        ]);

        $violation->update($validated);

        // Re-evaluate suspension status after edit
        $this->service->checkAndTriggerSuspension($violation->player);

        return redirect()
            ->route('backend.disciplinary.player', $violation->player_id)
            ->with('success', 'Violation updated successfully.');
    }

    /**
     * Soft-delete a violation.
     */
    public function destroyViolation(int $id)
    {
        $violation = PlayerViolation::findOrFail($id);
        $playerId  = $violation->player_id;
        $violation->delete();

        return redirect()
            ->route('backend.disciplinary.player', $playerId)
            ->with('success', 'Violation removed.');
    }

    /**
     * Manually lift an active suspension.
     */
    public function liftSuspension(Request $request, int $suspensionId)
    {
        $suspension = PlayerSuspension::findOrFail($suspensionId);

        $suspension->update([
            'lifted_by' => auth()->id(),
            'lifted_at' => Carbon::now(),
            'notes'     => $suspension->notes
                ? $suspension->notes . "\n[Lifted] " . ($request->reason ?? '')
                : '[Lifted] ' . ($request->reason ?? ''),
        ]);

        return redirect()
            ->route('backend.disciplinary.player', $suspension->player_id)
            ->with('success', 'Suspension lifted.');
    }
}
