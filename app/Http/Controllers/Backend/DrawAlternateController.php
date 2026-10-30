<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{Draw, Player};
use App\Services\Draw\AlternateReplacementService;
use Illuminate\Http\Request;

class DrawAlternateController extends Controller
{
    public function show(Request $request, Draw $draw)
    {
        $this->authorize('replaceAlternate', $draw);
        $request->validate(['q' => 'nullable|string|max:100']);
        $query = trim((string) $request->query('q', ''));
        $players = collect();
        if (mb_strlen($query) >= 2) {
            $players = Player::query()->where(function ($q) use ($query) {
                foreach (preg_split('/\s+/u', $query) as $word) {
                    $q->where(fn ($part) => $part->where('name', 'like', '%'.$word.'%')->orWhere('surname', 'like', '%'.$word.'%'));
                }
            })->orderBy('surname')->orderBy('name')->limit(30)->get();
        }
        $registrations = $draw->registrations()->with('players')->get();
        $fixtures = $draw->drawFixtures()->with('fixtureResults')->get();
        $starts = \App\Models\DrawAuditLog::where('draw_id', $draw->id)->where('action', 'match_started')->pluck('fixture_id');
        $service = app(AlternateReplacementService::class);
        $unavailableIds = $registrations->filter(fn ($registration) => $service->hasStarted($draw, $registration->id, $fixtures, false, $starts))->pluck('id');
        return view('backend.draw.alternate', compact('draw', 'query', 'players', 'registrations', 'unavailableIds'));
    }

    public function store(Request $request, Draw $draw, AlternateReplacementService $service)
    {
        $this->authorize('replaceAlternate', $draw);
        $data = $request->validate(['outgoing_registration_id' => 'required|integer', 'player_id' => 'required|integer|exists:players,id', 'show_alternate' => 'nullable|boolean', 'q' => 'nullable|string|max:100']);
        try {
            $service->replace($draw, $data['outgoing_registration_id'], $data['player_id'], $request->boolean('show_alternate'), $request->user()->id);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            if ($request->expectsJson() || !in_array($exception->getStatusCode(), [409, 422], true)) {
                throw $exception;
            }

            return redirect()->route('draw.alternate.show', ['draw' => $draw, 'q' => $data['q'] ?? null])
                ->withErrors(['replacement' => $exception->getMessage()])
                ->withInput($request->only(['outgoing_registration_id', 'player_id', 'show_alternate']));
        }
        return redirect()->route('draw.alternate.show', $draw)->with('success', 'Alternate saved. The player’s positions now use the alternate, with the same match times and courts.');
    }
}
