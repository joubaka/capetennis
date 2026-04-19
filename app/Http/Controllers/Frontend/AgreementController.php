<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Agreement;
use App\Models\PlayerAgreement;
use Illuminate\Http\Request;

class AgreementController extends Controller
{
    /**
     * Show the active Code of Conduct agreement.
     */
    public function show()
    {
        $agreement = Agreement::where('is_active', 1)->latest()->first();

        if (!$agreement) {
            return redirect()->route('home')
                ->with('error', 'No active agreement found.');
        }

        $user = auth()->user();
        $players = $user->players;

        return view('frontend.agreements.show', compact('agreement', 'players'));
    }

    /**
     * Accept the active agreement for a specific player.
     */
    public function accept(Request $request)
    {
        $request->validate([
            'player_id' => 'required|exists:players,id',
        ]);

        $player = \App\Models\Player::find($request->player_id);

        if (!$player) {
            return response()->json(['error' => 'Player not found.'], 404);
        }

        $agreement = Agreement::where('is_active', 1)->latest()->first();

        if (!$agreement) {
            return response()->json(['error' => 'No active agreement found.'], 404);
        }

        // Already accepted
        if (PlayerAgreement::where('player_id', $player->id)->where('agreement_id', $agreement->id)->exists()) {
            return response()->json(['success' => true, 'message' => 'Already accepted.']);
        }

        if ($player->isMinor()) {
            $request->validate([
                'guardian_name' => 'required',
                'guardian_email' => 'required|email',
                'guardian_relationship' => 'required',
            ]);
        }

        PlayerAgreement::create([
            'player_id' => $player->id,
            'agreement_id' => $agreement->id,
            'accepted_by_type' => $player->isMinor() ? 'guardian' : 'player',

            'guardian_name' => $request->guardian_name,
            'guardian_email' => $request->guardian_email,
            'guardian_phone' => $request->guardian_phone,
            'guardian_relationship' => $request->guardian_relationship,

            'accepted_at' => now(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'content_snapshot' => $agreement->content,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Check agreement status for a player (AJAX).
     */
    public function check(Request $request)
    {
        $request->validate([
            'player_id' => 'required|exists:players,id',
        ]);

        $player = \App\Models\Player::find($request->player_id);

        if (!$player) {
            return response()->json(['accepted' => false, 'error' => 'Player not found.']);
        }

        $agreement = Agreement::where('is_active', 1)->latest()->first();

        if (!$agreement) {
            return response()->json(['accepted' => true]); // No active agreement
        }

        $accepted = PlayerAgreement::where('player_id', $player->id)
            ->where('agreement_id', $agreement->id)
            ->exists();

        return response()->json([
            'accepted' => $accepted,
            'is_minor' => $player->isMinor(),
            'agreement_id' => $agreement->id,
        ]);
    }
}
