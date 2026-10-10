<?php

namespace App\Services\Performance;

use App\Models\{NoProfileTeamPlayer, Player, Team, TeamFixturePlayer};
use App\Services\TeamParticipantHistoryService;

/** Historical imported identities require an attestation captured before roster changes. */
final class ImportedMatchIdentityResolver
{
    public const VERSION = 1;

    public function resolve(TeamFixturePlayer $row, int $side, Team $source, int $eventId): ?Player
    {
        $importedId = $row->{'team'.$side.'_no_profile_id'};
        $snapshot = $row->participant_snapshot[$side] ?? [];
        $profileId = (int) ($snapshot['linked_profile_id'] ?? 0);
        if (!$importedId || $row->{'team'.$side.'_id'} || $profileId < 1
            || !app(TeamParticipantHistoryService::class)->matches($row, $side, $source, $eventId)
            || (int) $source->category?->event_id !== $eventId) { return null; }
        $imported = NoProfileTeamPlayer::whereKey($importedId)->where('team_id', $source->id)->first();
        $rank = (int) ($snapshot['rank'] ?? 0);
        if (!$imported || $rank < 1 || (int) $imported->rank !== $rank || (int) $imported->player_profile !== $profileId
            || NoProfileTeamPlayer::where('team_id', $source->id)->where('rank', $rank)->count() !== 1
            || NoProfileTeamPlayer::where('team_id', $source->id)->where('player_profile', $profileId)->count() !== 1
            || $source->team_players()->where('rank', $rank)->count() !== 1
            || $source->team_players()->where('player_id', $profileId)->count() !== 1
            || !$source->team_players()->where('rank', $rank)->where('player_id', $profileId)->exists()) { return null; }
        return Player::find($profileId);
    }
}
