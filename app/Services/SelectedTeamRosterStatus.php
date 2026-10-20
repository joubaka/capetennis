<?php

namespace App\Services;

use App\Models\{Event, EventSelectionRosterImport, NoProfileTeamPlayer, Team, TeamSelectionImport, TeamSelectionInvitation};
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class SelectedTeamRosterStatus
{
    /** Resolve frozen slot identities; rank or capacity edits cannot promote reserves. */
    public function reserveSlots(Event $event, Collection $teams): array
    {
        return $this->classify($event, $teams)['imported'];
    }

    public function classify(Event $event, Collection $teams): array
    {
        if (!$event->isTeam() || $event->isInterprovincialTrials()) return ['imported' => [], 'profile' => []];
        $imports = EventSelectionRosterImport::where('event_id', $event->id)->orderByDesc('id')->limit(501)->get(['groups']);
        abort_if($imports->count() > 500, 422, 'This event supports up to 500 selected-team imports.');
        $byTeam = [];
        foreach ($imports as $import) {
            foreach ($import->groups as $group) {
                $teamId = (int) ($group['team_id'] ?? 0);
                if ($teams->contains('id', $teamId) && ! array_key_exists($teamId, $byTeam)) $byTeam[$teamId] = $group;
            }
        }
        $managed = TeamSelectionImport::where('event_id', $event->id)->orderByDesc('id')->limit(501)->get();
        abort_if($managed->count() > 500, 422, 'This event supports up to 500 regional selection imports.');
        $managed = $managed->unique('region_id');
        $invitations = TeamSelectionInvitation::where('event_id', $event->id)->whereIn('team_id', $teams->pluck('id'))
            ->whereIn('import_id', $managed->whereIn('status', ['draft', 'sent'])->pluck('id'))->with('selectionImport')->limit(5001)->get();
        abort_if($invitations->count() > 5000, 422, 'This event supports up to 5000 selected-team invitations.');
        $legacyRegionIds = $teams->contains(fn ($team) => !$team->category_event_id)
            ? $event->regions()->whereDoesntHave('events', fn ($query) => $query->where('events.id', '!=', $event->id))->pluck('team_regions.id')
            : collect();
        $reserves = [];
        $profileReserves = [];
        $promotions = \App\Models\TeamRosterPromotion::where('event_id', $event->id)->whereIn('team_id', $teams->pluck('id'))->orderByDesc('id')->limit(5001)->get();
        abort_if($promotions->count() > 5000, 422, 'This event supports up to 5000 roster promotions.');
        foreach ($teams as $team) {
            if ($team->category_event_id ? (int) $team->category?->event_id !== (int) $event->id : !$legacyRegionIds->contains($team->region_id)) continue;
            $team->loadMissing(['team_players_no_profile', 'team_players']);
            $frozen = collect($byTeam[$team->id]['players'] ?? [])->keyBy('slot_id');
            foreach ($team->team_players_no_profile as $slot) {
                $frozenStatus = $frozen->get($slot->id)['status'] ?? null;
                $current = $invitations->first(fn ($invitation) => (int) $invitation->team_id === (int) $team->id
                    && (int) $invitation->player_id === (int) $slot->player_profile
                    && (int) $invitation->region_id === (int) $team->region_id
                    && (int) $invitation->selectionImport?->region_id === (int) $team->region_id);
                $currentMain = $current && in_array($current->status, [TeamSelectionInvitation::INVITED, TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, TeamSelectionInvitation::PAID_CONFIRMED], true)
                    && $current->roster_rank > 0 && (!$team->num_team_members || $current->roster_rank <= $team->num_team_members)
                    && (int) $slot->rank === (int) $current->roster_rank
                    && $team->team_players->contains(fn ($mirror) => (int) $mirror->player_id === (int) $slot->player_profile && (int) $mirror->rank === (int) $current->roster_rank);
                $reserve = $frozenStatus === 'reserve' || ($current && !$currentMain)
                    || (!$frozenStatus && !$current && $team->num_team_members > 0 && $slot->rank > $team->num_team_members);
                if (!$reserve) continue;
                $promoted = $invitations->contains(fn ($invitation) => $this->hasPromotion($invitation, $event, $team, (int) $slot->player_profile, (int) $slot->rank));
                $manualPromotion = $promotions->contains(fn ($promotion) => (int) $promotion->team_id === (int) $team->id
                    && (int) $promotion->no_profile_slot_id === (int) $slot->id
                    && (!(int) $promotion->player_id || (int) $promotion->player_id === (int) $slot->player_profile)
                    && (int) $promotion->target_rank === (int) $slot->rank && $slot->rank > 0 && $slot->rank <= $team->num_team_members
                    && $promotion->actor_id > 0 && $promotion->created_at);
                if (!$promoted && !$manualPromotion) $reserves[(int) $team->id][(int) $slot->id] = true;
            }
            foreach ($team->team_players as $slot) {
                if ((int) $slot->player_id < 1) continue;
                $importedSlots = $team->team_players_no_profile->filter(fn ($member) => (int) $member->player_profile === (int) $slot->player_id);
                if ($importedSlots->isNotEmpty()) {
                    if ($importedSlots->contains(fn ($member) => isset($reserves[$team->id][$member->id]))) $profileReserves[$team->id][$slot->player_id] = true;
                    continue;
                }
                $invitation = $invitations->first(fn ($candidate) => (int) $candidate->team_id === (int) $team->id
                    && (int) $candidate->player_id === (int) $slot->player_id
                    && (int) $candidate->region_id === (int) $team->region_id
                    && (int) $candidate->selectionImport?->region_id === (int) $team->region_id);
                $frozenRole = $frozen->first(fn ($record) => (int) ($record['profile_id'] ?? 0) === (int) $slot->player_id)['status'] ?? null;
                $frozenReserve = $frozenRole === 'reserve';
                $manualPromotion = $promotions->contains(fn ($promotion) => (int) $promotion->team_id === (int) $team->id
                    && (int) $promotion->profile_slot_id === (int) $slot->id && (int) $promotion->player_id === (int) $slot->player_id
                    && (int) $promotion->target_rank === (int) $slot->rank && $slot->rank > 0 && $slot->rank <= $team->num_team_members
                    && $promotion->actor_id > 0 && $promotion->created_at);
                if ($frozenReserve && !$manualPromotion && !($invitation && $this->hasPromotion($invitation, $event, $team, (int) $slot->player_id, (int) $slot->rank))) {
                    $profileReserves[$team->id][$slot->player_id] = true;
                    continue;
                }
                if (($invitation && (!in_array($invitation->status, [TeamSelectionInvitation::INVITED, TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, TeamSelectionInvitation::PAID_CONFIRMED], true) || !$invitation->roster_rank
                    || (int) $invitation->roster_rank !== (int) $slot->rank || ($team->num_team_members > 0 && $invitation->roster_rank > $team->num_team_members)))
                    || (!$frozenRole && !$invitation && $team->num_team_members > 0 && $slot->rank > $team->num_team_members)) {
                    $profileReserves[$team->id][$slot->player_id] = true;
                }
            }
        }
        return ['imported' => $reserves, 'profile' => $profileReserves];
    }

    private function hasPromotion(TeamSelectionInvitation $invitation, Event $event, Team $team, int $playerId, int $rank): bool
    {
        if ((int) $invitation->team_id !== (int) $team->id || (int) $invitation->player_id !== $playerId
            || (int) $invitation->region_id !== (int) $team->region_id
            || (int) $invitation->selectionImport?->region_id !== (int) $team->region_id
            || !in_array($invitation->status, [TeamSelectionInvitation::INVITED, TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, TeamSelectionInvitation::PAID_CONFIRMED], true)
            || !$invitation->roster_rank || $invitation->roster_rank > $team->num_team_members || (int) $invitation->roster_rank !== $rank) return false;
        $activation = $invitation->snapshot_json['activation'] ?? [];
        $proof = !empty($activation['activated_at']) && !empty($activation['activated_by_user_id']);
        if ($invitation->promoted_from_id) {
            $proof = TeamSelectionInvitation::whereKey($invitation->promoted_from_id)->where('import_id', $invitation->import_id)
                ->where('event_id', $event->id)->where('team_id', $team->id)
                ->whereIn('status', [TeamSelectionInvitation::DECLINED, TeamSelectionInvitation::WITHDRAWN])
                ->where('vacated_roster_rank', $rank)->exists();
        }
        return $proof && $team->team_players->contains(fn ($mirror) => (int) $mirror->player_id === $playerId && (int) $mirror->rank === $rank);
    }

    public function assertMainPlayer(Event $event, Team $team, int $playerId): void
    {
        $team = $team->fresh();
        if ($event->isTeam() && !$event->isInterprovincialTrials()) abort_unless(app(EventTeamScope::class)->query($event)->whereKey($team->id)->exists(), 404);
        if (isset($this->classify($event, collect([$team]))['profile'][$team->id][$playerId])) {
            throw ValidationException::withMessages(['player' => 'This player is a reserve. A tournament administrator must approve promotion before registration.']);
        }
    }

    public function assertMainSlot(Event $event, Team $team, NoProfileTeamPlayer $slot): void
    {
        $team = $team->fresh();
        abort_unless((int) $slot->team_id === (int) $team->id, 404);
        if ($event->isTeam() && !$event->isInterprovincialTrials()) abort_unless(app(EventTeamScope::class)->query($event)->whereKey($team->id)->exists(), 404);
        if (isset($this->reserveSlots($event, collect([$team]))[$team->id][$slot->id])) {
            throw ValidationException::withMessages(['player' => 'This player is a reserve. A tournament administrator must approve promotion before registration.']);
        }
    }
}
