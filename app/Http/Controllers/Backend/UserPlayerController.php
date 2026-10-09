<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Player;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserPlayerController extends Controller
{
  public function store(Request $request, User $user)
  {
    $this->authorizeLinkManagement($user);

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
    $this->authorizeLinkManagement($user);

    $removed = DB::transaction(function () use ($user, $player): bool {
      $player = Player::whereKey($player->id)->lockForUpdate()->firstOrFail();
      $pivotLinked = $user->players()->where('player_id', $player->id)->exists();
      $legacyLinked = (int) $player->userId === (int) $user->id;
      if (!$pivotLinked && !$legacyLinked) {
        return false;
      }
      if ($pivotLinked) {
        $user->players()->detach($player->id);
      }
      // Keep historical player records; zero supports legacy NOT NULL schemas.
      if ($legacyLinked) {
        $player->forceFill(['userId' => 0])->save();
      }
      return true;
    });

    if (!$removed) {
      return response()->json([
        'message' => 'Player not linked to this user',
      ], 404);
    }

    return response()->json([
      'message' => 'Player unlinked successfully',
    ]);
  }

  public function bulkDestroy(Request $request, User $user)
  {
    $this->authorizeLinkManagement($user);

    $data = $request->validate([
      'player_ids' => ['required', 'array', 'min:1', 'max:200'],
      'player_ids.*' => ['integer', 'distinct', 'exists:players,id'],
    ]);

    $playerIds = array_map('intval', $data['player_ids']);
    $removed = 0;

    DB::transaction(function () use ($user, $playerIds, &$removed): void {
      $players = Player::query()->whereKey($playerIds)->lockForUpdate()->get();
      foreach ($players as $player) {
        $pivotLinked = $user->players()->whereKey($player->id)->exists();
        $legacyLinked = (int) $player->userId === (int) $user->id;
        if (!$pivotLinked && !$legacyLinked) {
          continue;
        }

        if ($pivotLinked) {
          $user->players()->detach($player->id);
        }
        if ($legacyLinked) {
          $player->forceFill(['userId' => 0])->save();
        }
        $removed++;
      }
    });

    return response()->json([
      'message' => $removed.' player link'.($removed === 1 ? '' : 's').' removed successfully',
      'removed' => $removed,
    ]);
  }

  private function authorizeLinkManagement(User $targetUser): void
  {
    $actor = auth()->user();
    $isPrivileged = $actor && method_exists($actor, 'hasAnyRole')
      && $actor->hasAnyRole(['super-user', 'admin']);

    abort_unless($actor && ((int) $actor->id === (int) $targetUser->id || $isPrivileged), 403);
  }

}
