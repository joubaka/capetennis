<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Player;
use Illuminate\Http\Request;
use Carbon\Carbon;

class PlayerProfileController extends Controller
{
    /**
     * Show all pending player profiles that need updating.
     */
    public function pending()
    {
        $user = auth()->user();
        $players = $user->players;

        // Get players needing update with their status
        $playersData = $players->map(function ($player) {
            return [
                'id' => $player->id,
                'name' => $player->name,
                'surname' => $player->surname,
                'full_name' => $player->name . ' ' . $player->surname,
                'email' => $player->email,
                'cellNr' => $player->cellNr,
                'dateOfBirth' => $player->dateOfBirth ? Carbon::parse($player->dateOfBirth)->format('Y-m-d') : '',
                'gender' => $player->gender,
                'age' => $player->dateOfBirth ? Carbon::parse($player->dateOfBirth)->age : null,
                'is_minor' => $player->isMinor(),
                'needs_update' => $player->needsProfileUpdate() || !$player->isProfileComplete(),
                'status' => $player->getProfileStatus(),
                'profile_updated_at' => $player->profile_updated_at?->format('d M Y'),
            ];
        });

        $pendingCount = $playersData->where('needs_update', true)->count();

        // Get intended URL but DON'T remove it from session yet
        $intendedUrl = session('url.intended', route('home'));

        return view('frontend.player.profiles-pending', compact('playersData', 'pendingCount', 'intendedUrl'));
    }

    /**
     * Show the player profile edit form.
     */
    public function edit(Player $player)
    {
        // Ensure user owns this player
        $user = auth()->user();
        if (!$user->players->contains($player->id)) {
            abort(403, 'You do not have permission to edit this player.');
        }

        $profileStatus = $player->getProfileStatus();

        return view('frontend.player.profile-update', compact('player', 'profileStatus'));
    }

    /**
     * Update the player profile.
     */
    public function update(Request $request, Player $player)
    {
        // Ensure user owns this player
        $user = auth()->user();
        if (!$user->players->contains($player->id)) {
            abort(403, 'You do not have permission to edit this player.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'surname' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'cellNr' => 'required|string|max:50',
            'dateOfBirth' => 'required|date|before:today',
            'gender' => 'required|in:Male,Female',
        ], [
            'dateOfBirth.required' => 'Date of birth is required.',
            'dateOfBirth.date' => 'Please enter a valid date of birth.',
            'dateOfBirth.before' => 'Date of birth must be before today.',
            'name.required' => 'First name is required.',
            'surname.required' => 'Surname is required.',
            'cellNr.required' => 'Cell number is required.',
            'gender.required' => 'Please select a gender.',
            'gender.in' => 'Gender must be Male or Female.',
        ]);

        $player->update($validated);
        $player->markProfileUpdated();

        if ($request->expectsJson()) {
            $status = $player->getProfileStatus();
            return response()->json([
                'success' => true,
                'message' => "Profile for \"{$player->name} {$player->surname}\" updated successfully.",
                'player' => [
                    'id' => $player->id,
                    'name' => $player->name,
                    'surname' => $player->surname,
                    'full_name' => $player->name . ' ' . $player->surname,
                    'status' => $status,
                    'needs_update' => false,
                ],
            ]);
        }

        // Redirect to intended URL or home
        $intended = session()->pull('url.intended', route('home'));

        return redirect($intended)
            ->with('success', 'Player profile for "' . $player->full_name . '" has been updated successfully.');
    }

    /**
     * Check profile status for a player (AJAX).
     */
    public function status(Player $player)
    {
        $user = auth()->user();
        if (!$user->players->contains($player->id)) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'player_id' => $player->id,
            'needs_update' => $player->needsProfileUpdate(),
            'is_complete' => $player->isProfileComplete(),
            'status' => $player->getProfileStatus(),
            'last_updated' => $player->profile_updated_at?->format('d M Y H:i'),
        ]);
    }

    /**
     * Confirm profile is current without changes (just mark as reviewed).
     */
    public function confirm(Request $request, Player $player)
    {
        $user = auth()->user();
        if (!$user->players->contains($player->id)) {
            abort(403);
        }

        // Only allow confirm if profile is complete
        if (!$player->isProfileComplete()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profile is incomplete. Please fill in all required fields.',
                ], 422);
            }
            return redirect()->route('player.profile.edit', $player)
                ->with('error', 'Profile is incomplete. Please fill in all required fields.');
        }

        $player->markProfileUpdated();

        if ($request->expectsJson()) {
            $status = $player->getProfileStatus();
            return response()->json([
                'success' => true,
                'message' => "Profile for \"{$player->name} {$player->surname}\" confirmed as current.",
                'player' => [
                    'id' => $player->id,
                    'name' => $player->name,
                    'surname' => $player->surname,
                    'full_name' => $player->name . ' ' . $player->surname,
                    'status' => $status,
                    'needs_update' => false,
                ],
            ]);
        }

        $intended = session()->pull('url.intended', route('home'));

        return redirect($intended)
            ->with('success', 'Profile confirmed as current.');
    }

    /**
     * Remove player from user's account.
     */
    public function remove(Request $request, Player $player)
    {
        $user = auth()->user();

        // Ensure user owns this player
        if (!$user->players->contains($player->id)) {
            abort(403, 'You do not have permission to remove this player.');
        }

        $playerName = $player->name . ' ' . $player->surname;

        // Detach player from user (doesn't delete the player record)
        $user->players()->detach($player->id);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Player \"{$playerName}\" has been removed from your account.",
            ]);
        }

        return redirect()->route('home')
            ->with('success', "Player \"{$playerName}\" has been removed from your account. If this was a mistake, please contact support@capetennis.co.za");
    }
}
