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
        $data['canPreviewNonPlus'] = $request->user()->hasRole('super-user');
        $data['previewNonPlus'] = $data['canPreviewNonPlus'] && $request->boolean('preview_non_plus');
        $data['canViewRating'] = !$data['previewNonPlus'] && $ratingPlayer && app(\App\Services\Performance\PlayerRatingAccess::class)->allows($request->user(), $ratingPlayer);
        $data['overviewBadge'] = null;
        $data['overviewPathway'] = null;
        if ($data['selectedPlayer'] && $data['canViewRating']) {
            $history = app(\App\Services\Performance\PlayerRatingHistoryService::class)->forPlayer($data['selectedPlayer']);
            $data['overviewPathway'] = app(\App\Services\Performance\PlayerPathwayService::class)->summary($data['selectedPlayer'], $history);
            $data['overviewBadge'] = $data['overviewPathway']['badge'];
        }
        return response()->view('frontend.my-tennis.index', $data)->header('Cache-Control', 'private, no-store');
    }

    public function rating(Request $request, \App\Models\Player $player)
    {
        abort_unless(app(\App\Services\Performance\PlayerRatingAccess::class)->allows($request->user(), $player), 403);
        $filters = $request->validate(['cohort' => 'nullable|string|max:255', 'month' => 'nullable|integer|min:0|max:11',
            'scope' => 'nullable|in:new,all', 'page' => 'nullable|integer|min:1|max:2500', 'scale' => 'nullable|in:focused,full',
            'rating_age' => 'nullable|in:u7,u8,u9,u10,u11,u12,u13,u18,other,unknown',
            'rating_gender' => 'nullable|in:boys,girls,unknown', 'rating_level' => 'nullable|in:red,orange,overall,standard',
            'rating_page' => 'nullable|integer|min:1|max:200']);
        $history = app(\App\Services\Performance\PlayerRatingHistoryService::class)->forPlayer($player, $filters);
        return response()->view('frontend.my-tennis.rating', [
            'ratingPlayer' => $player,
            'ratingHistory' => $history,
            'ratingPathway' => app(\App\Services\Performance\PlayerPathwayService::class)->summary($player, $history),
            'ratingTopTen' => app(\App\Services\Performance\PlayerRatingTopTen::class)->build($request->user(), $player,
                array_map(fn ($value) => $value ?? '', array_intersect_key($filters, array_flip(['rating_age', 'rating_gender', 'rating_level', 'rating_page'])))),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function pathway(Request $request, \App\Models\Player $player)
    {
        abort_unless(app(\App\Services\Performance\PlayerRatingAccess::class)->allows($request->user(), $player), 403);
        $history = app(\App\Services\Performance\PlayerRatingHistoryService::class)->forPlayer($player);
        return response()->view('frontend.my-tennis.pathway-page', [
            'ratingPlayer' => $player,
            'pathway' => app(\App\Services\Performance\PlayerPathwayService::class)->summary($player, $history),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function pathwayInfo(Request $request, \App\Models\Player $player, string $stage)
    {
        abort_unless(app(\App\Services\Performance\PlayerRatingAccess::class)->allows($request->user(), $player), 403);
        abort_unless(in_array($stage, ['red', 'orange', 'wilson-b', 'wilson-a', 'tsa'], true), 404);
        $history = app(\App\Services\Performance\PlayerRatingHistoryService::class)->forPlayer($player);
        return response()->view('frontend.my-tennis.pathway-info', [
            'ratingPlayer' => $player, 'infoStage' => $stage,
            'pathway' => app(\App\Services\Performance\PlayerPathwayService::class)->summary($player, $history),
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
