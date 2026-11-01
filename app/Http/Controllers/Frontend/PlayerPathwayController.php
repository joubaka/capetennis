<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Services\Performance\{PlayerPathwayService, PlayerRatingAccess};
use Illuminate\Http\Request;

class PlayerPathwayController extends Controller
{
    public function stage(Request $request, Player $player, PlayerPathwayService $pathways)
    {
        $this->authorizeActor($request, $player);
        $pathways->recordStage($player, $request->user(), $request->only(['stage', 'wilson_division']));

        return $this->saved($request, $player, 'Playing stage saved.');
    }

    public function milestones(Request $request, Player $player, PlayerPathwayService $pathways)
    {
        $this->authorizeActor($request, $player);
        $pathways->verifyMilestones($player, $request->user(), $request->only(['stage', 'milestones']));
    }

    private function authorizeActor(Request $request, Player $player): void
    {
        abort_unless($request->user()?->hasRole('super-user') && app(PlayerRatingAccess::class)->allows($request->user(), $player), 403);
    }

    private function saved(Request $request, Player $player, string $message)
    {
        if ($request->expectsJson()) return response()->json(['message' => $message])->header('Cache-Control', 'private, no-store');

        return redirect(route('my.tennis', ['player' => $player->id]).'#rating')->with('status', $message);
    }
}
