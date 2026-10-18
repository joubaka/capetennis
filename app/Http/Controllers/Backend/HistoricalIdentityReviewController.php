<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\{Event, Player};
use App\Services\Performance\HistoricalIdentityReviewService;
use Illuminate\Http\Request;

class HistoricalIdentityReviewController extends Controller
{
    public function index(Request $request, Event $event, HistoricalIdentityReviewService $service)
    {
        abort_unless($request->user()?->hasRole('super-user'), 403);
        $input = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'matches_page' => ['nullable', 'integer', 'min:1'], 'proposals_page' => ['nullable', 'integer', 'min:1']]);
        $search = trim($input['search'] ?? '');
        $candidates = collect();
        if (mb_strlen($search) >= 2) {
            $query = Player::query()->select(['id', 'name', 'surname', 'dateOfBirth', 'gender']);
            foreach (preg_split('/\s+/u', $search, -1, PREG_SPLIT_NO_EMPTY) as $token) {
                $query->where(fn ($q) => $q->where('name', 'like', '%'.addcslashes($token, '%_\\').'%')->orWhere('surname', 'like', '%'.addcslashes($token, '%_\\').'%'));
            }
            $candidates = $query->orderBy('surname')->orderBy('id')->limit(20)->get();
        }
        return response()->view('backend.player-performance.identity-review', compact('event', 'search', 'candidates') + $service->listings($event))
            ->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request, Event $event, HistoricalIdentityReviewService $service)
    {
        abort_unless($request->user()?->hasRole('super-user'), 403);
        $input = $request->validate([
            'fixture_player_id' => ['required', 'integer', 'min:1'], 'side' => ['required', 'integer', 'in:1,2'],
            'source_team_id' => ['required', 'integer', 'min:1'], 'suggested_player_id' => ['required', 'integer', 'min:1'],
            'source_fingerprint' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'evidence' => ['required', 'string', 'min:30', 'max:10000'], 'reference' => ['required', 'string', 'min:5', 'max:2000'],
            'reason' => ['required', 'string', 'min:15', 'max:3000'],
        ]);
        $service->propose($event, $input);
        return redirect()->route('backend.player-performance.identity-review', $event)->with('status', 'Evidence proposal saved. Sources and ratings are unchanged.')->header('Cache-Control', 'no-store, private');
    }

    public function preview(Request $request, Event $event, HistoricalIdentityReviewService $service)
    {
        abort_unless($request->user()?->hasRole('super-user'), 403);
        $input = $request->validate(['proposals' => ['required', 'array', 'min:1', 'max:40'], 'proposals.*' => ['required', 'integer', 'distinct', 'min:1']]);
        $preview = $service->preview($event, $input['proposals']);
        return response()->view('backend.player-performance.identity-preview', compact('event', 'preview'))->header('Cache-Control', 'no-store, private');
    }
}
