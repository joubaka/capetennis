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
      'date_of_birth' => ['nullable', 'date_format:Y-m-d'],
      'contact' => ['nullable', 'string', 'max:190'],
    ]);

    $player = Player::findOrFail($data['player_id']);
    $actor = auth()->user();
    $isPrivileged = $actor && method_exists($actor, 'hasAnyRole')
      && $actor->hasAnyRole(['super-user', 'admin']);

    if (!$isPrivileged && (
      ($player->userId && (int) $player->userId !== (int) $user->id)
      || $player->users()->whereKeyNot($user->id)->exists()
    )) {
      abort(403, 'This player is already linked to another family.');
    }

    if (!$isPrivileged && !$user->players()->where('player_id', $data['player_id'])->exists()) {
      $dobMatches = $data['date_of_birth']
        && substr((string) $player->dateOfBirth, 0, 10) === $data['date_of_birth'];
      $submittedContact = mb_strtolower(trim((string) ($data['contact'] ?? '')));
      $normalise = fn ($value) => mb_strtolower(preg_replace('/[^a-z0-9+@.]/i', '', (string) $value));
      $knownContacts = collect([$player->email, $player->cellNr])->filter()->map($normalise);
      $contactMatches = $submittedContact !== '' && $knownContacts->contains($normalise($submittedContact));

      if (!$dobMatches || !$contactMatches) {
        return response()->json(['message' => 'For your protection, enter the player date of birth and the email address or mobile number recorded for that player profile.'], 422);
      }
    }

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

    if ($user->players()->where('player_id', $player->id)->exists()) {
      $user->players()->detach($player->id);

      return response()->json([
        'message' => 'Player unlinked successfully',
      ]);
    }

    if ((int) $player->userId !== (int) $user->id) {
      return response()->json([
        'message' => 'Player not linked to this user',
      ], 404);
    }

    // Retire the legacy direct ownership link without deleting the player or
    // changing any registration, payment, result, or ranking history. Some
    // production schemas retain this legacy column as NOT NULL, so zero is
    // the unowned sentinel used by the old data model.
    $player->forceFill(['userId' => 0])->save();

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
