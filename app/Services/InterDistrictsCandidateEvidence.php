<?php

namespace App\Services;

use App\Models\Draw;
use App\Models\Event;
use App\Models\NoProfileTeamPlayer;
use App\Models\TeamFixturePlayer;
use App\Models\TeamPlayer;
use App\Models\TeamSelectionImport;
use App\Models\TeamSelectionInvitation;
use Illuminate\Support\Collection;

/** Private evidence used only after the source event's management gate. */
final class InterDistrictsCandidateEvidence
{
    public function __construct(private EventTeamScope $scope) {}

    public function invitations(Event $event, Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        $imports = TeamSelectionImport::query()->where('event_id', $event->id)->latest('id')
            ->get(['id', 'region_id', 'status'])->unique('region_id')->whereIn('status', ['draft', 'sent'])->keyBy('id');
        $teams = $this->scope->query($event)->without(['team_players', 'team_players_no_profile'])->pluck('region_id', 'id');

        return TeamSelectionInvitation::query()->where('event_id', $event->id)
            ->whereIn('player_id', $ids)->whereIn('import_id', $imports->keys())->latest('id')
            ->get(['team_id', 'import_id', 'region_id', 'player_id', 'status', 'decline_method', 'declined_at'])
            ->filter(fn (TeamSelectionInvitation $row) => $teams->has($row->team_id)
                && (int) $row->region_id === (int) $teams->get($row->team_id)
                && (int) $row->region_id === (int) $imports[$row->import_id]->region_id)
            ->unique(fn (TeamSelectionInvitation $row) => $row->team_id.':'.$row->player_id);
    }

    public function missingMainStatuses(Event $event, Collection $ids): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        $teams = $this->scope->query($event)->without(['team_players', 'team_players_no_profile'])
            ->get(['id', 'category_event_id', 'region_id'])->keyBy('id');
        $teamIds = $teams->keys();
        $imported = NoProfileTeamPlayer::query()->whereIn('team_id', $teamIds)
            ->whereIn('player_profile', $ids)->pluck('player_profile');
        $rostered = TeamPlayer::withoutGlobalScopes()->whereIn('team_id', $teamIds)
            ->whereIn('player_id', $ids)->pluck('player_id')->concat($imported);
        // Assignment or roster membership is enough to rule out the missing-player
        // exception. Neither is reported as proof that a match was played.
        $missing = $ids->diff($rostered);
        if ($missing->isEmpty()) {
            return collect();
        }
        $drawIds = Draw::query()->where('event_id', $event->id)->get(['id', 'team_draw_selection'])
            ->reject(fn (Draw $draw) => $draw->isPlacementDraft())->modelKeys();
        $assigned = TeamFixturePlayer::query()->whereHas('fixture', fn ($query) => $query->whereIn('draw_id', $drawIds))
            ->where(fn ($query) => $query->whereIn('team1_id', $missing)->orWhereIn('team2_id', $missing)
                ->orWhereNotNull('participant_snapshot'))
            ->get(['team1_id', 'team2_id', 'team1_no_profile_id', 'team2_no_profile_id', 'participant_snapshot'])
            ->flatMap(function (TeamFixturePlayer $row) use ($teams, $event) {
                $ids = [$row->team1_id, $row->team2_id];
                foreach ([1, 2] as $side) {
                    $snapshot = $row->participant_snapshot[$side] ?? [];
                    $team = $teams->get($snapshot['source_team_id'] ?? 0);
                    if ($team && ! empty($snapshot['linked_profile_id'])
                        && app(TeamParticipantHistoryService::class)->matches($row, $side, $team, (int) $event->id)) {
                        $ids[] = (int) $snapshot['linked_profile_id'];
                    }
                }

                return $ids;
            });
        $missing = $missing->diff($assigned);
        $invitations = $this->invitations($event, $missing)->groupBy('player_id');

        return $missing->mapWithKeys(function (int $id) use ($invitations) {
            $rows = $invitations->get($id, collect());
            $confirmed = $rows->contains(fn (TeamSelectionInvitation $row) => $row->status === TeamSelectionInvitation::DECLINED
                && $row->decline_method === 'authenticated_invitation' && $row->declined_at);
            $status = $confirmed ? 'Confirmed Inter-Districts decline'
                : ($rows->contains('status', TeamSelectionInvitation::DECLINED) ? 'No confirmed player decline' : 'No current decline recorded');

            return [$id => 'No Inter-Districts participation recorded · '.$status];
        });
    }
}
