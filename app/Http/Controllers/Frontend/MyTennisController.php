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
        return view('frontend.my-tennis.index', $this->myTennis->dashboard(
            $request->user(),
            $request->integer('player') ?: null,
        ));
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
