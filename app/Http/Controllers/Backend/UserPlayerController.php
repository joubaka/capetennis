<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Player;
use Illuminate\Http\Request;

class UserPlayerController extends Controller
{
  public function store(Request $request, User $user)
  {
    $data = $request->validate([
      'player_id' => ['required', 'exists:players,id'],
    ]);

    if ($user->players()->where('player_id', $data['player_id'])->exists()) {
      return response()->json([
        'message' => 'Player already linked',
      ], 422);
    }

    $user->players()->attach($data['player_id']);

    return response()->json([
      'message' => 'Player linked successfully',
    ]);
  }

  // app/Http/Controllers/Backend/UserPlayerController.php

  public function destroy(User $user, Player $player)
  {
    if (!$user->players()->where('player_id', $player->id)->exists()) {
      return response()->json([
        'message' => 'Player not linked to this user',
      ], 404);
    }

    $user->players()->detach($player->id);

    return response()->json([
      'message' => 'Player unlinked successfully',
    ]);
  }

}
