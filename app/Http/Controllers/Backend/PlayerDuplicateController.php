<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\Player;
use App\Services\PlayerDuplicateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlayerDuplicateController extends Controller
{
    public function index(PlayerDuplicateService $duplicates): View
    {
        return view('backend.superadmin.player-duplicates', [
            'candidateGroups' => $duplicates->candidates(),
        ]);
    }

    public function merge(Request $request, PlayerDuplicateService $duplicates): RedirectResponse
    {
        $validated = $request->validate([
            'keep_player_id' => ['required', 'integer', 'different:remove_player_id', 'exists:players,id'],
            'remove_player_id' => ['required', 'integer', 'exists:players,id'],
            'confirmation' => ['required', 'in:MERGE'],
        ]);

        $keep = Player::findOrFail($validated['keep_player_id']);
        $remove = Player::findOrFail($validated['remove_player_id']);
        $duplicates->merge($keep, $remove, $request->user());

        return redirect()->route('superadmin.player-duplicates.index')
            ->with('success', "Profile #{$remove->id} was merged into profile #{$keep->id}.");
    }
}
