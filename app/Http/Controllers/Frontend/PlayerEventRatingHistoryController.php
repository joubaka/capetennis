<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Services\Performance\{PlayerEventRatingHistory, PlayerEventRatingHistoryRefresh, PlayerRatingAccess};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class PlayerEventRatingHistoryController extends Controller
{
    public function show(Request $request, Player $player, PlayerRatingAccess $access, PlayerEventRatingHistory $history, PlayerEventRatingHistoryRefresh $refresh)
    {
        abort_unless($access->allows($request->user(), $player), 403);
        $request->validate(['page' => 'nullable|integer|min:1|max:20']);
        return response()->view('frontend.my-tennis.event-rating-history', ['ratingPlayer' => $player,
            'workspace' => app(\App\Services\PlayerWorkspaceContext::class)->forPlayer($request->user(), $player, includeSnapshot: false),
            'timeline' => $history->forPlayer($player, $request->integer('page', 1)), 'refreshStatus' => $refresh->status((int) $player->id)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function refresh(Request $request, Player $player, PlayerRatingAccess $access, PlayerEventRatingHistoryRefresh $refresh)
    {
        abort_unless($access->allows($request->user(), $player), 403);
        // Keep the actor-wide limit separate from other numeric route throttles.
        // Authorize first so rejected requests cannot consume the actor's refresh allowance.
        $key = 'player-event-history:refresh:user:'.$request->user()->getAuthIdentifier();
        $status = RateLimiter::attempt($key, 2, fn () => $refresh->request((int) $player->id), 60);
        if ($status === false) {
            $retryAfter = max(1, RateLimiter::availableIn($key));
            $message = 'Please wait '.$retryAfter.' seconds before requesting another history update. Your saved history is unchanged.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'retry_after' => $retryAfter], 429)
                    ->header('Retry-After', $retryAfter)->header('Cache-Control', 'private, no-store');
            }
            return redirect()->route('my.tennis.event-history', $player)->with('event_history_status', $message)
                ->header('Retry-After', $retryAfter)->header('Cache-Control', 'private, no-store');
        }
        return redirect()->route('my.tennis.event-history', $player)->with('event_history_status', $status['message']);
    }
}
