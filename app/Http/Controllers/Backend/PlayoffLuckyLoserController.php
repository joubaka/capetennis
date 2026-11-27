<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{Draw, Fixture};
use App\Services\Draw\PlayoffLuckyLoserService;
use Illuminate\Http\Request;

class PlayoffLuckyLoserController extends Controller
{
    public function show(Request $request, Draw $draw, PlayoffLuckyLoserService $service)
    {
        $this->authorize('replacePlayoffLuckyLoser', $draw);
        $query = trim((string) ($request->validate(['q' => 'nullable|string|max:100'])['q'] ?? ''));
        $candidates = $service->candidates($draw)->with(['registration.players', 'categoryEvent.category'])
            ->when($query !== '', fn ($entries) => $entries->whereHas('registration.players', fn ($players) => $players
                ->where(fn ($name) => $name->where('name', 'like', '%'.$query.'%')->orWhere('surname', 'like', '%'.$query.'%'))))
            ->orderBy('id')->paginate(30)->withQueryString();
        if ($request->expectsJson()) return response()->json(['candidates' => $candidates->map(fn ($entry) => [
            'id' => $entry->id, 'name' => $entry->registration->display_name, 'division' => $entry->categoryEvent->category?->name,
        ]), 'next_page_url' => $candidates->nextPageUrl()]);
        $fixtures = $draw->drawFixtures()->whereNotIn('stage', ['RR', 'FM'])->where('match_status', 0)->whereNull('winner_registration')
            ->whereDoesntHave('fixtureResults')->with(['registration1.players', 'registration2.players'])->orderBy('match_nr')->get();
        return view('backend.draw.playoff-lucky-loser', ['draw' => $draw, 'query' => $query, 'candidates' => $candidates,
            'fixtures' => $fixtures, 'revision' => $service->revision($draw)]);
    }

    public function store(Request $request, Draw $draw, PlayoffLuckyLoserService $service)
    {
        $this->authorize('replacePlayoffLuckyLoser', $draw);
        $data = $request->validate(['target' => ['required', 'regex:/^\d+:[12]:\d+$/'], 'source_entry_id' => 'required|integer',
            'revision' => 'required|string|size:64', 'confirm_withdrawal' => 'required|accepted']);
        [$fixtureId, $slot, $outgoing] = array_map('intval', explode(':', $data['target']));
        $fixture = Fixture::findOrFail($fixtureId);
        abort_unless((int) $fixture->draw_id === (int) $draw->id, 422, 'Choose a playoff place in this draw.');
        try {
            $incoming = $service->replace($draw, $fixture, $slot, (int) $data['source_entry_id'], $outgoing, $data['revision']);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
            if ($request->expectsJson() || !in_array($exception->getStatusCode(), [409, 422], true)) throw $exception;
            return back()->withErrors(['replacement' => $exception->getMessage()])->withInput();
        }
        if ($request->expectsJson()) return response()->json(['success' => true, 'registration_id' => $incoming->id]);
        return redirect()->route('draw.playoff-lucky-loser.show', $draw)->with('success', 'Playoff withdrawal confirmed. The replacement is labelled LL; round-robin results and entries are unchanged.');
    }
}
