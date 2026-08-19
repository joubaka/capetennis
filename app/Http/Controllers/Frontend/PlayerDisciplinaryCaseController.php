<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\DisciplinaryCase;
use App\Services\DisciplinaryCaseService;
use Illuminate\Http\Request;

class PlayerDisciplinaryCaseController extends Controller
{
    public function __construct(private DisciplinaryCaseService $service) {}

    public function index(Request $request)
    {
        $playerIds = $request->user()->ownedPlayerIds();
        $cases = DisciplinaryCase::with(['event', 'player', 'sanctions'])->whereIn('player_id', $playerIds)
            ->whereNotIn('status', [DisciplinaryCase::STATUS_SUBMITTED, DisciplinaryCase::STATUS_TRIAGE])
            ->latest('incident_at')->paginate(20);
        return view('frontend.disciplinary.index', compact('cases'));
    }

    public function show(DisciplinaryCase $case)
    {
        $this->authorize('respond', $case);
        $case->load(['event', 'player', 'charges', 'decisions.sanctions', 'appeals']);
        return view('frontend.disciplinary.show', compact('case'));
    }

    public function respond(Request $request, DisciplinaryCase $case)
    {
        $this->authorize('respond', $case);
        $data = $request->validate(['response' => ['required', 'string', 'min:10', 'max:20000']]);
        $this->service->submitResponse($case, $request->user(), $data['response']);
        return back()->with('success', 'Your response has been securely submitted to the panel.');
    }

    public function appeal(Request $request, DisciplinaryCase $case)
    {
        $this->authorize('respond', $case);
        $data = $request->validate(['grounds' => ['required', 'string', 'min:20', 'max:20000']]);
        $this->service->appeal($case, $request->user(), $data['grounds']);
        return back()->with('success', 'Your appeal has been submitted.');
    }
}
