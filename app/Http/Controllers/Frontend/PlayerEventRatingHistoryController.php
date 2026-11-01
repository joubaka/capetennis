<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Services\Performance\{PlayerEventRatingHistory, PlayerEventRatingHistoryRefresh, PlayerRatingAccess};
use Illuminate\Http\Request;

class PlayerEventRatingHistoryController extends Controller
{
    public function show(Request $request, Player $player, PlayerRatingAccess $access, PlayerEventRatingHistory $history, PlayerEventRatingHistoryRefresh $refresh)
    {
        abort_unless($access->allows($request->user(), $player), 403);
        $request->validate(['page' => 'nullable|integer|min:1|max:20']);
        return response()->view('frontend.my-tennis.event-rating-history', ['ratingPlayer' => $player,
            'timeline' => $history->forPlayer($player, $request->integer('page', 1)), 'refreshStatus' => $refresh->status((int) $player->id)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function refresh(Request $request, Player $player, PlayerRatingAccess $access, PlayerEventRatingHistoryRefresh $refresh)
    {
        abort_unless($access->allows($request->user(), $player), 403);
        $status = $refresh->request((int) $player->id);
        return redirect()->route('my.tennis.event-history', $player)->with('event_history_status', $status['message']);
    }
}
