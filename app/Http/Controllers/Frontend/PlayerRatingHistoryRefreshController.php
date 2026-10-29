<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\Performance\PlayerRatingHistoryRefresh;
use Illuminate\Http\Request;

class PlayerRatingHistoryRefreshController extends Controller
{
    public function store(Request $request, PlayerRatingHistoryRefresh $refresh)
    {
        abort_unless($request->user()?->hasRole('super-user'), 403);
        $status = $refresh->request();
        return response()->json($status, $status['state'] === 'failed' ? 503 : 202)->header('Cache-Control', 'private, no-store');
    }

    public function status(Request $request, PlayerRatingHistoryRefresh $refresh)
    {
        abort_unless($request->user()?->hasRole('super-user'), 403);
        return response()->json($refresh->status())->header('Cache-Control', 'private, no-store');
    }
}
