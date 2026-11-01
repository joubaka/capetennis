<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Services\Performance\TeamSheetProfileLinkService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TeamSheetProfileLinkController extends Controller
{
    public function start(Request $request, Player $player, TeamSheetProfileLinkService $links)
    {
        return redirect()->away($links->start($request, $player))->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function callback(Request $request, TeamSheetProfileLinkService $links)
    {
        try {
            $data = $request->validate(['state' => 'required|string|size:64', 'code' => 'required|string|size:64']);
            $player = $links->complete($request, $data['state'], $data['code']);
        }
        catch (ValidationException $error) { return redirect()->route('my.tennis')->withErrors($error->errors())->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer'); }
        catch (\Throwable) { return redirect()->route('my.tennis')->withErrors(['teamsheet' => 'The TeamSheet connection could not be completed. Start again from My Tennis.'])->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer'); }
        return redirect()->route('my.tennis', ['player' => $player])->with('teamsheet_success', 'TeamSheet player profile connected. Verified match results can contribute after the normal date and rating checks.')
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }

    public function unlink(Request $request, Player $player, TeamSheetProfileLinkService $links)
    {
        $links->unlink($request->user(), $player);
        return redirect()->route('my.tennis', ['player' => $player->id])->with('teamsheet_success', 'TeamSheet profile disconnected.');
    }
}
