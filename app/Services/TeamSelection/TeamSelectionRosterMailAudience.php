<?php
namespace App\Services\TeamSelection;
use App\Models\{Event, EventRegion, Team, TeamSelectionInvitation, NoProfileTeamPlayer};
use Illuminate\Support\Facades\DB;
class TeamSelectionRosterMailAudience
{
    public function __construct(private TeamSelectionContactService $contacts) {}
    public function resolve(Event $event, EventRegion $region, array $selection)
    {
        $target = $selection['target_type'];
        if ($target === 'filtered') return app(TeamSelectionEmailAudienceService::class)->resolve($event, $region, $selection);
        if (in_array($target, ['unlinked_imported', 'linked_unpaid', 'linked_all'], true)) return $this->recipientCohort($event, $region, $target);
        $team = null;
        if (in_array($target, ['team', 'player', 'imported_player'], true)) {
            $team = Team::withoutGlobalScopes()->with('category')->findOrFail($selection['team_id']);
            abort_unless((int) $team->region_id === (int) $region->region_id && (int) $team->category?->event_id === (int) $event->id, 404);
        }
        if ($target === 'imported_player') {
            $slot = $team->team_players_no_profile()->with('profile.user', 'profile.users')->findOrFail($selection['slot_id']);
            return collect([['player_key' => $slot->player_profile ? 'player:'.$slot->player_profile : 'imported:'.$slot->id, 'name' => $slot->profile?->full_name ?? trim($slot->name.' '.$slot->surname), 'email' => $slot->player_profile ? $this->contacts->primaryEmail($slot->profile) : $slot->email]]);
        }
        if ($target === 'player') {
            $invitation = TeamSelectionInvitation::where('event_id', $event->id)->where('region_id', $region->region_id)->where('team_id', $team->id)->findOrFail($selection['invitation_id']);
            return $this->rosterRecipients($event, $region, $team->id, $invitation->id);
        }
        $hasSelection = TeamSelectionInvitation::where('import_id', $this->currentImportId($event, $region))->where('event_id', $event->id)->where('region_id', $region->region_id)->when($team, fn ($q) => $q->where('team_id', $team->id))->exists();
        if ($hasSelection) return $this->rosterRecipients($event, $region, $team?->id);
        return app(\App\Services\EventCommunicationService::class)->rosterEntries($event, [(int) $region->region_id])
            ->when($team, fn ($rows) => $rows->where('team_id', $team->id))
            ->flatMap(fn ($row) => collect($row['emails'] ?: [''])->map(fn ($email) => ['name' => $row['name'], 'email' => $email, 'player_key' => $row['key']]));
    }
    private function currentImportId(Event $event, EventRegion $region): ?int
    {
        return \App\Models\TeamSelectionImport::where('event_id', $event->id)->where('region_id', $region->region_id)->whereIn('status', ['draft', 'sent'])->latest('id')->value('id');
    }

    private function rosterRecipients(Event $event, EventRegion $eventRegion, ?int $teamId = null, ?int $invitationId = null, bool $unpaidOnly = false)
    {
        return TeamSelectionInvitation::query()->with(['player.user', 'player.users'])
            ->where('event_id', $event->id)
            ->where('import_id', $this->currentImportId($event, $eventRegion))
            ->where('region_id', $eventRegion->region_id)
            ->whereHas('team', fn ($q) => $q->where('region_id', $eventRegion->region_id)->whereHas('category', fn ($c) => $c->where('event_id', $event->id)))
            ->whereIn('status', $unpaidOnly
                ? [TeamSelectionInvitation::INVITED, TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT]
                : [TeamSelectionInvitation::INVITED, TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, TeamSelectionInvitation::PAID_CONFIRMED])
            ->when($teamId, fn ($query) => $query->where('team_id', $teamId))
            ->when($invitationId, fn ($query) => $query->whereKey($invitationId))
            ->get()
            ->map(function (TeamSelectionInvitation $invitation): array {
                $email = $this->contacts->primaryEmail($invitation->player);

                return ['player_key' => 'player:'.$invitation->player_id, 'email' => $email, 'name' => $invitation->player?->full_name];
            })
            ->unique(fn ($row) => ($row['player_key'] ?? '').':'.$row['email'])
            ->sortBy('email')
            ->values();
    }

    private function recipientCohort(Event $event, EventRegion $eventRegion, string $cohort)
    {
        $imported = $this->importedRosterRecipients($event, $eventRegion, $cohort);
        if ($cohort === 'unlinked_imported') return $imported;

        return $this->mergeRecipients(
            $imported,
            $this->rosterRecipients($event, $eventRegion, unpaidOnly: $cohort === 'linked_unpaid'),
        );
    }

    private function mergeRecipients($first, $second)
    {
        return $first->concat($second)->unique(fn ($row) => ($row['player_key'] ?? '').':'.$row['email'])->sortBy('email')->values();
    }

    private function importedRosterRecipients(Event $event, EventRegion $eventRegion, string $cohort)
    {
        return NoProfileTeamPlayer::query()->with(['team.category', 'profile.user', 'profile.users'])
            ->whereHas('team', fn ($query) => $query->where('region_id', $eventRegion->region_id)
                ->whereHas('category', fn ($category) => $category->where('event_id', $event->id)))
            ->when($cohort === 'unlinked_imported', fn ($query) => $query->whereNull('player_profile'))
            ->when(in_array($cohort, ['linked_unpaid', 'linked_all'], true), fn ($query) => $query->whereNotNull('player_profile'))
            ->when($cohort === 'linked_unpaid', fn ($query) => $query->whereNotExists(function ($payment): void {
                    $payment->select(DB::raw(1))->from('team_players')
                        ->whereColumn('team_players.team_id', 'no_profile_team_players.team_id')
                        ->whereColumn('team_players.rank', 'no_profile_team_players.rank')
                        ->where('team_players.pay_status', 1);
                }))
            ->get()
            ->map(function (NoProfileTeamPlayer $slot): array {
                $linkedEmail = $this->contacts->primaryEmail($slot->profile);

                return [
                    'player_key' => $slot->player_profile ? 'player:'.$slot->player_profile : 'imported:'.$slot->id,
                    'email' => mb_strtolower(trim((string) ($slot->player_profile ? $linkedEmail : $slot->email))),
                    'name' => trim($slot->name.' '.$slot->surname),
                ];
            })
            ->unique(fn ($row) => ($row['player_key'] ?? '').':'.$row['email'])->sortBy('email')->values();
    }

}
