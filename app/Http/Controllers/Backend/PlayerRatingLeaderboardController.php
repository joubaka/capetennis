<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Services\Performance\PlayerRatingLeaderboardService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class PlayerRatingLeaderboardController extends Controller
{
    public function refresh(Request $request, \App\Services\Performance\PlayerRatingRefreshRequest $refresh)
    {
        abort_unless($request->user()?->hasRole('super-user'), 403);
        return $this->refreshResponse($refresh, true);
    }

    public function status(Request $request, \App\Services\Performance\PlayerRatingRefreshRequest $refresh)
    {
        abort_unless($request->user()?->hasRole('super-user'), 403);
        return $this->refreshResponse($refresh, false);
    }

    private function refreshResponse(\App\Services\Performance\PlayerRatingRefreshRequest $refresh, bool $start)
    {
        $diagnostics = \App\Services\Performance\PlayerRatingRefreshDiagnostics::class;
        $reference = $diagnostics::reference();
        try {
            $status = app(\App\Services\Performance\PlayerAbilityRefreshState::class)->status();
            if ($start) {
                if ($status['pending'] || $status['failed']) $refresh->request($reference);
                else $diagnostics::write($reference, 'request', 'current');
            }
            return response()->json(['status' => $status, 'refreshing' => $refresh->running(),
                'reference_id' => $reference, 'launch_failure' => $refresh->launchFailure()])
                ->header('Cache-Control', 'no-store, private');
        } catch (\Throwable $error) {
            $diagnostics::write($reference, $start ? 'request' : 'status', 'unavailable', $error);
            return response()->json(['error' => ['reason' => 'refresh_unavailable', 'reference_id' => $reference]], 503)
                ->header('Cache-Control', 'no-store, private');
        }
    }

    public function site(Request $request, PlayerRatingLeaderboardService $service)
    {
        return $this->show($request, $service);
    }

    public function event(Request $request, Event $event, PlayerRatingLeaderboardService $service)
    {
        return $this->show($request, $service, $event);
    }

    private function show(Request $request, PlayerRatingLeaderboardService $service, ?Event $event = null)
    {
        abort_unless($request->user()?->hasRole('super-user'), 403);
        $settings = $request->validate(['cohort' => ['nullable', 'string', 'max:150'], 'search' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1']]);
        $selected = $settings['cohort'] ?? '';
        $search = trim($settings['search'] ?? '');
        $data = $service->build($event, $selected, $search);
        $refreshStatus = app(\App\Services\Performance\PlayerAbilityRefreshState::class)->status();
        if ($selected !== '' && !in_array($selected, $data['cohorts'], true)) {
            throw \Illuminate\Validation\ValidationException::withMessages(['cohort' => 'Choose an available exact cohort.']);
        }
        $players = new LengthAwarePaginator($data['rows']->forPage($request->integer('page', 1), 25)->values(), $data['rows']->count(), 25,
            $request->integer('page', 1), ['path' => $request->url(), 'query' => $request->query()]);
        return response()->view('backend.player-performance.leaderboard', compact('event', 'selected', 'search', 'players', 'refreshStatus') + $data)
            ->header('Cache-Control', 'no-store, private');
    }
}
