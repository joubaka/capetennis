<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Services\MyTennisService;
use App\Services\PlayerRelationshipAccess;
use Illuminate\Http\Request;

class MyTennisController extends Controller
{
    public function __construct(private readonly MyTennisService $myTennis)
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $explicitPlayerId = $request->integer('player') ?: null;
        $playerId = $explicitPlayerId;
        $remembered = $request->session()->get('my_tennis.selected_player');
        if (!$playerId && $remembered) {
            $rememberedPlayer = Player::find($remembered);
            if ($rememberedPlayer && app(PlayerRelationshipAccess::class)->related($user, $rememberedPlayer)) {
                $playerId = $rememberedPlayer->id;
            }
        }
        $tab = $request->query('tab');
        if (!in_array($tab, ['profile', 'activity', 'manage', 'rating'], true)) {
            $tab = match (true) {
                $request->hasAny(['matches_page', 'entries_page']) => 'activity',
                $request->hasAny(['feature', 'preview_non_plus']) => 'rating',
                default => 'profile',
            };
        }
        $data = $this->myTennis->dashboard(
            $user,
            $playerId,
            includeActivity: $tab === 'activity',
            selectUpcoming: false,
        );
        $data['workspaceTab'] = $tab;
        if ($data['selectedPlayer'] && !$request->prefetch()) {
            $request->session()->put('my_tennis.selected_player', $data['selectedPlayer']->id);
        }
        // A super admin may preview another player's rating without granting ownership.
        if (!$data['selectedPlayer'] && $user->hasRole('super-user') && $explicitPlayerId) {
            $data['ratingPreviewPlayer'] = Player::findOrFail($explicitPlayerId);
        }
        $ratingPlayer = $data['ratingPreviewPlayer'] ?? $data['selectedPlayer'];
        $data['ratingPlayer'] = $ratingPlayer;
        $data['canPreviewNonPlus'] = $request->user()->hasRole('super-user');
        $data['previewNonPlus'] = $data['canPreviewNonPlus'] && $request->boolean('preview_non_plus');
        $data['canViewRating'] = !$data['previewNonPlus'] && $ratingPlayer && app(\App\Services\Performance\PlayerRatingAccess::class)->allows($request->user(), $ratingPlayer);
        $data['overviewPathway'] = null;
        $data['ratingSnapshot'] = null;
        if ($ratingPlayer && $data['canViewRating']) {
            $history = app(\App\Services\Performance\PlayerRatingHistoryService::class)
                ->forPlayer($ratingPlayer, summaryOnly: !in_array($tab, ['activity', 'rating'], true));
            $data['ratingSnapshot'] = \App\Services\Performance\PlayerRatingHistoryDisplay::snapshot($history);
            if ($tab === 'rating') {
                $data += $this->ratingViewData($request, $ratingPlayer, $history, []);
            }
            if ($data['selectedPlayer'] && $tab === 'activity') {
                $data['overviewPathway'] = app(\App\Services\Performance\PlayerPathwayService::class)->summary($data['selectedPlayer'], $history);
            }
        }
        return response()->view('frontend.my-tennis.index', $data)->header('Cache-Control', 'private, no-store');
    }

    public function featurePreview(Request $request, \App\Models\Player $player, string $feature)
    {
        abort_unless(in_array($feature, ['record', 'opponents', 'pathway', 'events', 'rating'], true), 404);
        abort_unless($request->user()->hasRole('super-user') || app(\App\Services\PlayerRelationshipAccess::class)->related($request->user(), $player), 403);

        // Marketing only: do not load private rating, match or pathway evidence.
        return response()->view('frontend.my-tennis.feature-preview-page', [
            'ratingPlayer' => $player, 'previewFeature' => $feature,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function rating(Request $request, \App\Models\Player $player)
    {
        abort_unless(app(\App\Services\Performance\PlayerRatingAccess::class)->allows($request->user(), $player), 403);
        $filters = $request->validate(['cohort' => 'nullable|string|max:255', 'month' => 'nullable|integer|min:0|max:11',
            'scope' => 'nullable|in:new,all', 'page' => 'nullable|integer|min:1|max:2500', 'scale' => 'nullable|in:focused,full',
            'rating_age' => 'nullable|in:u7,u8,u9,u10,u11,u12,u13,u18,other,unknown',
            'rating_gender' => 'nullable|in:boys,girls,unknown', 'rating_level' => 'nullable|in:red,orange,overall,standard',
            'rating_page' => 'nullable|integer|min:1|max:200', 'rating_per_page' => 'nullable|integer|in:25,50,100,500']);
        $history = app(\App\Services\Performance\PlayerRatingHistoryService::class)->forPlayer($player, $filters);
        return response()->view('frontend.my-tennis.rating', $this->ratingViewData($request, $player, $history, $filters))
            ->header('Cache-Control', 'private, no-store');
    }

    private function ratingViewData(Request $request, Player $player, array $history, array $filters): array
    {
        return [
            'ratingPlayer' => $player,
            'ratingHistory' => $history,
            'ratingTopTen' => app(\App\Services\Performance\PlayerRatingTopTen::class)->build($request->user(), $player,
                array_map(fn ($value) => $value ?? '', array_intersect_key($filters, array_flip(['rating_age', 'rating_gender', 'rating_level', 'rating_page', 'rating_per_page'])))),
        ];
    }

    public function pathway(Request $request, \App\Models\Player $player)
    {
        abort_unless(app(\App\Services\Performance\PlayerRatingAccess::class)->allows($request->user(), $player), 403);
        $history = app(\App\Services\Performance\PlayerRatingHistoryService::class)->forPlayer($player);
        return response()->view('frontend.my-tennis.pathway-page', [
            'workspace' => app(\App\Services\PlayerWorkspaceContext::class)->forPlayer($request->user(), $player, $history),
            'ratingPlayer' => $player,
            'pathway' => app(\App\Services\Performance\PlayerPathwayService::class)->summary($player, $history),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function playingRecord(Request $request, \App\Models\Player $player, string $section = 'record')
    {
        abort_unless(app(\App\Services\Performance\PlayerRatingAccess::class)->allows($request->user(), $player), 403);
        $filters = $request->validate(['year' => 'nullable|integer|min:1900|max:2100', 'compare_first' => 'nullable|integer|min:1900|max:2100',
            'record_month' => 'nullable|date_format:Y-m',
            'compare_second' => 'nullable|integer|min:1900|max:2100', 'opponent' => 'nullable|integer|min:1',
            'page' => 'nullable|integer|min:1|max:2500', 'upcoming_page' => 'nullable|integer|min:1|max:2500', 'opponent_page' => 'nullable|integer|min:1|max:2500', 'meeting_page' => 'nullable|integer|min:1|max:2500']);
        $upcoming = $section === 'opponents' ? app(\App\Services\Performance\PlayerUpcomingMatchInsights::class)
            ->forPlayer($request->user(), $player, (int) ($filters['upcoming_page'] ?? 1)) : null;
        $record = app(\App\Services\Performance\PlayerPlusRecordService::class)->forPlayer($player, $filters, $request->user()->hasRole('super-user'));
        $liveResultsPrefetch = $section === 'record' && $request->prefetch();
        $liveResults = null;
        if ($section === 'record') {
            $liveResults = $liveResultsPrefetch ? collect()
                : app(\App\Services\Performance\PlayerLiveResults::class)->forPlayer($request->user(), $player);
        }
        $pastRatings = [];
        if ($section === 'opponents') {
            $opponentIds = collect($record['opponents']['rows'] ?? [])->pluck('id')
                ->push($record['opponent']['id'] ?? null)->filter()->unique()->values()->all();
            if ($opponentIds) {
                $pastRatings = app(\App\Services\Performance\PlayerRatingTopTen::class)
                    ->forMatchPlayers($request->user(), $player, $opponentIds);
            }
        }
        return response()->view('frontend.my-tennis.playing-record', ['recordPlayer' => $player, 'recordSection' => $section,
            'upcoming' => $upcoming, 'pastRatings' => $pastRatings, 'liveResults' => $liveResults,
            'liveResultsPrefetch' => $liveResultsPrefetch,
            'workspace' => app(\App\Services\PlayerWorkspaceContext::class)->forPlayer($request->user(), $player),
            'record' => $record])
            ->header('Cache-Control', 'private, no-store');
    }

    public function liveResults(Request $request, \App\Models\Player $player)
    {
        abort_unless(app(\App\Services\Performance\PlayerRatingAccess::class)->allows($request->user(), $player), 403);

        return response()->view('frontend.my-tennis.record-live-results', [
            'recordPlayer' => $player,
            'liveResults' => app(\App\Services\Performance\PlayerLiveResults::class)->forPlayer($request->user(), $player),
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
        $linkedIds = $request->user()->players()->whereIn('players.id', collect($page->items())->pluck('id'))
            ->pluck('players.id')->map(fn ($id) => (int) $id)->all();

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
