<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Services\Performance\{PlayerAbilitySnapshotStore, TeamSheetTennisEvidence, TeamSheetTennisImportService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamSheetTennisController extends Controller
{
    public function index(Request $request, TeamSheetTennisImportService $import, TeamSheetTennisEvidence $evidence)
    {
        $request->validate(['page' => 'nullable|integer|min:1', 'links_page' => 'nullable|integer|min:1']);
        $ready = $import->ready(); $fresh = $ready && $import->fresh(); $configured = $import->origin() !== null;
        $matches = $ready ? DB::table('teamsheet_tennis_matches')->orderByDesc('id')->paginate(20) : null;
        $links = $ready ? DB::table('teamsheet_tennis_player_links as l')->leftJoin('players as p', 'p.id', '=', 'l.player_id')
            ->select(['l.*', 'p.name', 'p.surname'])->orderByDesc('l.id')->paginate(20, ['*'], 'links_page') : null;
        $identities = $ready ? $evidence->links() : [];
        $states = [];
        $snapshot = $ready ? app(PlayerAbilitySnapshotStore::class)->current() : [];
        foreach ($matches ?? [] as $match) {
            $states[$match->id] = $evidence->status($match, $identities);
            if (!($snapshot['reason'] ?? null) && isset($snapshot['teamsheet_held']['teamsheet:'.$match->source_match_id])) $states[$match->id]['reason'] = $snapshot['teamsheet_held']['teamsheet:'.$match->source_match_id];
        }
        $feed = $ready ? DB::table('teamsheet_tennis_feeds')->where('id', 1)->first() : null;
        return response()->view('backend.integrations.teamsheet-tennis', compact('ready', 'fresh', 'configured', 'matches', 'links', 'states', 'feed'))->header('Cache-Control', 'private, no-store');
    }

    public function sync(Request $request, TeamSheetTennisImportService $import)
    {
        try { $count = $import->sync($request->user()); }
        catch (ValidationException $error) { throw $error; }
        catch (\Throwable) { throw ValidationException::withMessages(['teamsheet' => 'TeamSheet sync failed. Earlier results were retained. Try again later.']); }
        return back()->with('success', "Synced {$count} verified singles results. Player and date reviews apply before the next rating update.");
    }

    public function link(Request $request, TeamSheetTennisImportService $import)
    {
        $data = $request->validate(['teamsheet_player_id' => 'required|integer|min:1', 'player_id' => 'required|integer|min:1|exists:players,id', 'reason' => 'required|string|min:10|max:1000']);
        $import->link($request->user(), (int) $data['teamsheet_player_id'], (int) $data['player_id'], $data['reason']);
        return back()->with('success', 'Player identity connected. The next saved rating update will apply this connection.');
    }

    public function unlink(Request $request, int $id, TeamSheetTennisImportService $import)
    {
        $data = $request->validate(['reason' => 'required|string|min:10|max:1000']);
        $import->unlink($request->user(), $id, $data['reason']);
        return back()->with('success', 'Player connection removed. Saved estimates using this connection are withheld until refreshed.');
    }

    public function date(Request $request, int $id, TeamSheetTennisImportService $import)
    {
        $data = $request->validate(['source_version' => 'required|string|size:64', 'played_on' => 'required|date_format:Y-m-d|before_or_equal:today', 'reason' => 'required|string|min:10|max:1000']);
        $import->reviewDate($request->user(), $id, $data['source_version'], $data['played_on'], $data['reason']);
        return back()->with('success', 'Actual match date reviewed for this source revision.');
    }
}
