<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\MyTennisService;
use Illuminate\Http\Request;

class MyTennisController extends Controller
{
    public function __construct(private readonly MyTennisService $myTennis)
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $data = $this->myTennis->dashboard(
            $request->user(),
            $request->integer('player') ?: null,
        );
        // A super admin may preview another player's rating without granting ownership.
        if (!$data['selectedPlayer'] && $request->user()->hasRole('super-user') && $request->integer('player')) {
            $data['ratingPreviewPlayer'] = \App\Models\Player::findOrFail($request->integer('player'));
        }
        $ratingPlayer = $data['ratingPreviewPlayer'] ?? $data['selectedPlayer'];
        $data['ratingPlayer'] = $ratingPlayer;
        $data['canViewRating'] = $ratingPlayer && app(\App\Services\Performance\PlayerRatingAccess::class)->allows($request->user(), $ratingPlayer);
        return response()->view('frontend.my-tennis.index', $data)->header('Cache-Control', 'private, no-store');
    }

    public function rating(Request $request, \App\Models\Player $player)
    {
        abort_unless(app(\App\Services\Performance\PlayerRatingAccess::class)->allows($request->user(), $player), 403);
        $filters = $request->validate(['cohort' => 'nullable|string|max:255', 'month' => 'nullable|integer|min:0|max:11',
            'scope' => 'nullable|in:new,all', 'page' => 'nullable|integer|min:1|max:2500', 'scale' => 'nullable|in:focused,full']);
        $history = app(\App\Services\Performance\PlayerRatingHistoryService::class)->forPlayer($player, $filters);
        return response()->view('frontend.my-tennis.rating', [
            'ratingPlayer' => $player,
            'ratingHistory' => $history,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function players(Request $request)
    {
        $page = $this->myTennis->playerPage($request->user(), $request->integer('page', 1));
        $linkedIds = $request->user()->players()->pluck('players.id')->map(fn ($id) => (int) $id)->all();

        return response()->json([
            'data' => collect($page->items())->map(fn ($player) => [
                'id' => $player->id,
                'name' => $player->full_name,
                'legacy' => (int) $player->userId === (int) $request->user()->id,
                'linked' => in_array((int) $player->id, $linkedIds, true),
            ])->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ]);
    }
}
