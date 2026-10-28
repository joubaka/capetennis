<?php

namespace App\Services;

use App\Domain\Payments\Services\TeamPaymentService;
use App\Models\{Event, EventRegion, NoProfileTeamPlayer, Player, Team, TeamPlayer, TeamPaymentOrder, TeamSelectionImport, TeamSelectionInvitation, User};
use App\Services\TeamSelection\{RegionManagerAccessService, TeamSelectionInvitationService, ImportedTeamRosterService};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeamRosterService
{
    public function authorize(Event $event, ?EventRegion $region, Team $team, User $actor): void
    {
        app(\App\Domain\Teams\Services\ExternalTeamRosterService::class)->assertTeamBelongsToEvent($team, $event);
        if ($region) abort_unless((int) $region->event_id === (int) $event->id && (int) $team->region_id === (int) $region->region_id, 404);
        if ($actor->hasRole('super-user') || \Illuminate\Support\Facades\Gate::forUser($actor)->allows('team.players.manage', $team)) return;
        abort_unless($event->isTeam() && $region && app(RegionManagerAccessService::class)->canManageRoster($actor, $region), 403);
    }

    public function change(Event $event, ?EventRegion $region, Team $team, User $actor, string $action, array $data): void
    {
        DB::transaction(function () use ($event, $region, $team, $actor, $action, $data): void {
            app(\App\Services\TeamDrawAdaptationService::class)->lockEvent($event->id);
            $team = Team::query()->lockForUpdate()->findOrFail($team->id);
            $this->authorize($event, $region?->fresh(), $team, $actor);
            $fullManager = $actor->hasRole('super-user') || \Illuminate\Support\Facades\Gate::forUser($actor)->allows('team.players.manage', $team);
            if ($action !== 'add' || !$fullManager) $this->assertBeforeCompetition($event, $team);
            $selection = app(TeamSelectionInvitationService::class);
            $payments = app(TeamPaymentService::class);
            if ($team->noProfile && $team->category) $selection->assertRosterEditable($team);
            $import = TeamSelectionImport::where('event_id', $event->id)->where('region_id', $team->region_id)
                ->latest('id')->lockForUpdate()->first();
            $managed = $import && in_array($import->status, ['draft', 'sent'], true)
                && $import->invitations()->where('team_id', $team->id)->exists();
            $before = null;

            if ($action === 'add') {
                $reserve = (bool) ($data['reserve'] ?? false);
                if ($reserve) {
                    abort_unless($fullManager, 403);
                    if (!$event->isTeam() || $event->isInterprovincialTrials() || $managed || (int) $team->num_team_members < 1) $this->fail('Use the selection workflow to manage reserves for this team.');
                    $this->assertBeforeCompetition($event, $team);
                }
                $slots = $team->noProfile ? $team->team_players_no_profile()->lockForUpdate()->get()
                    : TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->lockForUpdate()->get();
                if ($slots->count() !== $slots->pluck('rank')->unique()->count()) $this->fail('This roster needs administrator review.');
                $used = $slots->filter(fn ($slot) => $team->noProfile || (int) $slot->player_id > 0)->pluck('rank')->all();
                $rank = collect(range(1, max(1, (int) $team->num_team_members)))->first(fn ($rank) => ! in_array($rank, $used));
                if ($fullManager && !$managed && !$team->noProfile && !isset($data['_replacement_rank'])) $rank = max(0, (int) $slots->max('rank')) + 1;
                if (isset($data['_replacement_rank'])) {
                    $rank = (int) $data['_replacement_rank'];
                    if ($rank < 1 || in_array($rank, $used)) $this->fail('The replacement place changed. Refresh and try again.');
                }
                if ($reserve) $rank = max((int) $team->num_team_members, (int) $slots->max('rank')) + 1;
                if (((int) $team->num_team_members < 1 || ! $rank) && $fullManager && !$managed && !isset($data['_replacement_rank'])) $rank = max(0, (int) $slots->max('rank')) + 1;
                if (! $rank || ((int) $team->num_team_members < 1 && !$fullManager && !isset($data['_replacement_rank']))) $this->fail('This team is full. Ask the event administrator to change its size.');
                if ($team->noProfile) {
                    $mirror = TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->where('rank', $rank)->lockForUpdate()->get();
                    if ($mirror->count() > 1 || $mirror->contains(fn ($slot) => $slot->pay_status || (int) $slot->player_id > 0)) $this->fail('This roster needs administrator review.');
                    $duplicate = $this->hasDuplicateRawName($slots, $data['name'], $data['surname']);
                    if ($duplicate) $this->fail('This player is already on the team.');
                    $player = !empty($data['player_id']) ? Player::query()->lockForUpdate()->findOrFail($data['player_id']) : null;
                    if ($player) {
                        try { app(PlayerEligibilityService::class)->assertEligible($player, $event); }
                        catch (\RuntimeException $exception) { $this->fail($exception->getMessage()); }
                        if (TeamPlayer::whereIn('team_id', app(EventTeamScope::class)->query($event)->select('teams.id'))->where('player_id', $player->id)->lockForUpdate()->exists()
                            || NoProfileTeamPlayer::whereIn('team_id', app(EventTeamScope::class)->query($event)->select('teams.id'))->where('player_profile', $player->id)->lockForUpdate()->exists()) $this->fail('This player is already on a team in this event.');
                        if (TeamPaymentOrder::where('event_id', $event->id)->forPlayerHistory([$player->id])->lockForUpdate()->exists()
                            || TeamSelectionInvitation::where('event_id', $event->id)->where('player_id', $player->id)->lockForUpdate()->exists()
                            || \App\Models\ClothingOrder::where('event_id', $event->id)->where('player_id', $player->id)->lockForUpdate()->exists()
                            || \App\Models\CategoryEventRegistration::withTrashed()->whereHas('categoryEvent', fn ($query) => $query->where('event_id', $event->id))
                                ->whereHas('registration.players', fn ($query) => $query->where('players.id', $player->id))->lockForUpdate()->exists()) $this->fail('This player has payment, registration, clothing or selection history. Use the existing selection workflow.');
                        if ($mirror->first()) $payments->updateTeamPlayerSlot($mirror->first(), ['player_id' => $player->id, 'pay_status' => 0]);
                        else $payments->createUnpaidTeamPlayerSlot($team, $player, $rank);
                    }
                    NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => $data['name'], 'surname' => $data['surname'], 'rank' => $rank, 'pay_status' => 0, 'player_profile' => $player?->id]);
                } else {
                    $player = Player::query()->lockForUpdate()->findOrFail($data['player_id']);
                    try { app(\App\Services\PlayerEligibilityService::class)->assertEligible($player, $event); }
                    catch (\RuntimeException $exception) { $this->fail($exception->getMessage()); }
                    $eventTeamIds = app(\App\Services\EventTeamScope::class)->query($event)->select('teams.id');
                    if (TeamPlayer::whereIn('team_id', $eventTeamIds)->where('player_id', $player->id)->exists()
                        || NoProfileTeamPlayer::whereIn('team_id', app(\App\Services\EventTeamScope::class)->query($event)->select('teams.id'))->where('player_profile', $player->id)->exists()) $this->fail('This player is already on a team in this event.');
                    if (TeamPaymentOrder::where('event_id', $event->id)->forPlayerHistory([$player->id])->lockForUpdate()->exists()) $this->fail('This player has checkout history. Ask the event administrator to add them.');
                    if ($managed) {
                        $existing = $import->invitations()->where('team_id', $team->id)->where('player_id', $player->id)->first();
                        if ($existing && $existing->status !== TeamSelectionInvitation::RESERVE) $this->fail('This player has selection history. Ask the event administrator to restore them.');
                        $invitation = $existing ?: $selection->addSystemPlayerAsReserve($import, $team, $player, $actor, 'Added to regional roster');
                        $selection->activateReserveInOpenPlace($invitation, $actor, $rank);
                    } else {
                        if ($team->category) $selection->assertRosterEditable($team);
                        $slot = $slots->firstWhere('rank', $rank);
                        if ($slot?->pay_status) $this->fail('This roster position has payment history. Ask the event administrator.');
                        if ($slot) $payments->updateTeamPlayerSlot($slot, ['player_id' => $player->id, 'pay_status' => 0]);
                        else $payments->createUnpaidTeamPlayerSlot($team, $player, $rank);
                    }
                }
            } else {
                $slot = $team->noProfile ? NoProfileTeamPlayer::where('team_id', $team->id)->lockForUpdate()->findOrFail($data['slot_id'])
                    : TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->where('player_id', '>', 0)->lockForUpdate()->findOrFail($data['slot_id']);
                if ((int) $slot->rank !== (int) $data['expected_rank']) $this->fail('The player order changed. Refresh and try again.');
                $playerId = (int) ($team->noProfile ? $slot->player_profile : $slot->player_id);
                if ($playerId !== (int) $data['expected_player_id']) $this->fail('This player changed. Refresh and try again.');
                $before = $slot->getAttributes();
                $invitation = $managed ? $import->invitations()->where('team_id', $team->id)->where('player_id', $playerId)->first() : null;
                if ($action === 'remove') {
                    if ($slot->pay_status) $this->fail('Ask the event administrator to remove a paid player.');
                    if ($playerId && \App\Models\ClothingOrder::where('event_id', $event->id)->where('player_id', $playerId)->lockForUpdate()->exists()) $this->fail('This player has clothing history. Ask the event administrator.');
                    if ($playerId && \App\Models\CategoryEventRegistration::withTrashed()->whereHas('categoryEvent', fn ($query) => $query->where('event_id', $event->id))
                        ->whereHas('registration.players', fn ($query) => $query->where('players.id', $playerId))->lockForUpdate()->exists()) $this->fail('This player has registration history. Ask the event administrator.');
                    $orders = $playerId ? TeamPaymentOrder::where('event_id', $event->id)->forPlayerHistory([$playerId])->lockForUpdate()->get() : collect();
                    foreach ($orders as $order) {
                        if ((int) $order->team_id !== (int) $team->id) $this->fail('This player has checkout history. Ask the event administrator.');
                        $payments->assertUnpaidRosterCheckoutMayBeReset($order);
                    }
                    if ($invitation) {
                        $selection->markUnavailable($invitation, $actor, 'Removed from regional roster', ['expected_team_id' => $team->id, 'expected_player_id' => $playerId, 'expected_roster_rank' => $slot->rank]);
                    } else {
                        if ($team->category) $selection->assertRosterEditable($team);
                        foreach ($orders as $order) $payments->closeUnpaidLifecycle($order, $actor);
                        if ($team->noProfile) {
                            $mirrors = TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->where('rank', $slot->rank)->lockForUpdate()->get();
                            if ($mirrors->count() > 1 || $mirrors->contains(fn ($mirror) => $mirror->pay_status || ! in_array((int) $mirror->player_id, [0, $playerId], true))) $this->fail('This roster needs administrator review.');
                            foreach ($mirrors as $mirror) $payments->updateTeamPlayerSlot($mirror, ['player_id' => 0, 'pay_status' => 0]);
                            $slot->delete();
                        } else $payments->updateTeamPlayerSlot($slot, ['player_id' => 0, 'pay_status' => 0]);
                    }
                } else {
                    $orderedSlots = $team->noProfile ? $team->team_players_no_profile()->orderBy('rank')->get()
                        : TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->orderBy('rank')->get();
                    $ids = $orderedSlots->pluck('id')->map('intval')->all();
                    $activeIds = $orderedSlots->filter(fn ($candidate) => $team->noProfile || (int) $candidate->player_id > 0)->pluck('id')->map('intval')->all();
                    $index = array_search((int) $slot->id, $activeIds, true);
                    $targetIndex = $index + ($data['direction'] === 'up' ? -1 : 1);
                    if ($index === false || !array_key_exists($targetIndex, $activeIds)) $this->fail('This player is already at the end of the list.');
                    $sourceIndex = array_search((int) $slot->id, $ids, true);
                    $destinationIndex = array_search($activeIds[$targetIndex], $ids, true);
                    [$ids[$sourceIndex], $ids[$destinationIndex]] = [$ids[$destinationIndex], $ids[$sourceIndex]];
                    $this->reorder($event, $region, $team, $actor, $ids);
                }
            }
            activity('team-roster')->performedOn($team)->causedBy($actor)->withProperties(['event_id' => $event->id, 'region_id' => $team->region_id, 'action' => $action, 'before' => $before, 'details' => $data])->log('updated team roster');
        });
    }

    public function editName(Event $event, ?EventRegion $region, Team $team, User $actor, array $data): void
    {
        DB::transaction(function () use ($event, $region, $team, $actor, $data): void {
            app(TeamDrawAdaptationService::class)->lockEvent($event->id);
            $team = Team::query()->lockForUpdate()->findOrFail($team->id);
            $this->authorize($event, $region, $team, $actor);
            abort_unless($team->noProfile, 403);
            $this->assertBeforeCompetition($event, $team);
            if ($team->category) app(TeamSelectionInvitationService::class)->assertRosterEditable($team);
            $slots = NoProfileTeamPlayer::where('team_id', $team->id)->lockForUpdate()->get();
            $slot = $slots->firstWhere('id', $data['slot_id']);
            abort_unless($slot, 404);
            $mirrors = TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->where('rank', $slot->rank)->lockForUpdate()->get();
            if ((int) $slot->player_profile !== 0 || (int) $data['expected_player_id'] !== 0
                || $slot->pay_status || $mirrors->count() > 1
                || $mirrors->contains(fn ($mirror) => (int) $mirror->player_id !== 0 || $mirror->pay_status)) {
                $this->fail('Only an unlinked, unpaid name can be corrected here. Refresh the team.');
            }
            if ((int) $slot->rank !== (int) $data['expected_rank'] || $slot->name !== $data['expected_name'] || $slot->surname !== $data['expected_surname']) {
                $this->fail('This name changed. Refresh and try again.');
            }
            $name = trim($data['name']); $surname = trim($data['surname']);
            if ($name === '' || $surname === '') $this->fail('Enter a first name and surname.');
            if ($this->hasDuplicateRawName($slots, $name, $surname, (int) $slot->id)) {
                $this->fail('This player is already on the team.');
            }
            if ($slot->name === $name && $slot->surname === $surname) return;
            app(ImportedTeamRosterService::class)->rename($event, $slot, $name, $surname, $actor);
        });
    }

    public function replace(Event $event, ?EventRegion $region, Team $team, User $actor, array $data): void
    {
        DB::transaction(function () use ($event, $region, $team, $actor, $data): void {
            app(TeamDrawAdaptationService::class)->lockEvent($event->id);
            $team = Team::query()->lockForUpdate()->findOrFail($team->id);
            $this->authorize($event, $region, $team, $actor);
            $slot = $team->noProfile ? NoProfileTeamPlayer::where('team_id', $team->id)->lockForUpdate()->findOrFail($data['slot_id'])
                : TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->lockForUpdate()->findOrFail($data['slot_id']);
            $before = $slot->getAttributes();
            $rank = (int) $slot->rank;
            $playerId = (int) ($team->noProfile ? $slot->player_profile : $slot->player_id);
            if ($rank !== (int) $data['expected_rank'] || $playerId !== (int) $data['expected_player_id']) $this->fail('This player changed. Refresh and try again.');
            if (!empty($data['player_id']) && $playerId === (int) $data['player_id']) $this->fail('Choose a different player.');
            if ($team->noProfile && !empty($data['player_id'])) {
                $replacement = Player::query()->lockForUpdate()->findOrFail($data['player_id']);
                $data['name'] = $replacement->name;
                $data['surname'] = $replacement->surname;
            }
            $mirror = $team->noProfile ? TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->where('rank', $rank)->lockForUpdate()->first() : $slot;
            $paid = (int) $slot->pay_status === 1 || (int) $mirror?->pay_status === 1;
            if ($paid) {
                abort_unless($actor->hasRole('super-user'), 403);
                $this->assertBeforeCompetition($event, $team);
                if (empty($data['player_id']) || !in_array($data['refund_action'] ?? null, ['refund', 'waive'], true) || blank($data['reason'] ?? null)) $this->fail('Choose a database player, a refund or waiver, and give a reason for replacing a paid player.');
                if (!$mirror || (int) $mirror->player_id !== $playerId) $this->fail('The paid roster mirror no longer agrees. Refresh the player list.');
                $withdrawals = app(AdminTeamWithdrawalService::class);
                $review = $withdrawals->review($event, $team, $mirror, $actor, refundAction: $data['refund_action']);
                if ($data['refund_action'] === 'refund' && !$review['refundAvailable']) $this->fail('The refund deadline has passed. Choose an explicit refund waiver to continue.');
                $withdrawals->confirm($event, $team, $mirror, $actor, [
                    'order_id' => $review['order']->id, 'fingerprint' => $review['fingerprint'],
                    'reason' => $data['reason'], 'refund_action' => $data['refund_action'],
                ]);
            } else {
                if (!empty($data['refund_action'])) $this->fail('Refresh the player list: this player is unpaid and needs no refund decision.');
                $this->change($event, $region, $team, $actor, 'remove', $data);
            }
            $this->change($event, $region, $team, $actor, 'add', array_replace($data, ['_replacement_rank' => $rank]));
            $after = $team->noProfile ? NoProfileTeamPlayer::where('team_id', $team->id)->where('rank', $rank)->firstOrFail()
                : TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->where('rank', $rank)->firstOrFail();
            activity('team-roster')->performedOn($team)->causedBy($actor)
                ->withProperties(['event_id' => $event->id, 'rank' => $rank, 'before' => $before, 'after' => $after->getAttributes(), 'refund_action' => $data['refund_action'] ?? null, 'reason' => $data['reason'] ?? null])
                ->log('changed team roster player');
        });
    }

    public function fillEmptyPlace(Event $event, ?EventRegion $region, Team $team, User $actor, array $data): void
    {
        DB::transaction(function () use ($event, $region, $team, $actor, $data): void {
            app(TeamDrawAdaptationService::class)->lockEvent($event->id);
            $team = Team::query()->lockForUpdate()->findOrFail($team->id);
            $this->authorize($event, $region?->fresh(), $team, $actor);
            abort_unless($actor->hasRole('super-user') || \Illuminate\Support\Facades\Gate::forUser($actor)->allows('team.players.manage', $team), 403);
            $this->assertBeforeCompetition($event, $team);
            if (!$event->isTeam() || $event->isInterprovincialTrials()) $this->fail('Use the selection workflow for this event.');
            if (TeamSelectionInvitation::where('team_id', $team->id)->whereHas('selectionImport', fn ($query) => $query->whereIn('status', ['draft', 'sent']))->exists()) $this->fail('Use the selection workflow for this team.');
            if ($team->category) app(TeamSelectionInvitationService::class)->assertRosterEditable($team);
            $rank = (int) $data['target_rank'];
            if ($rank < 1 || $rank > (int) $team->num_team_members) $this->fail('This numbered place is outside the configured team.');
            $imported = NoProfileTeamPlayer::where('team_id', $team->id)->lockForUpdate()->get();
            $mirrors = TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->lockForUpdate()->get();
            if ($imported->pluck('rank')->unique()->count() !== $imported->count() || $mirrors->pluck('rank')->unique()->count() !== $mirrors->count()) $this->fail('This roster needs administrator review.');
            $target = $mirrors->firstWhere('rank', $rank);
            if ($imported->contains('rank', $rank) || ($target && ((int) $target->player_id !== 0 || (int) $target->pay_status !== 0))) $this->fail('This place is no longer empty. Refresh the player list.');
            $payments = app(TeamPaymentService::class);

            if (!empty($data['reserve_slot_id'])) {
                $source = ($team->noProfile ? $imported : $mirrors)->firstWhere('id', (int) $data['reserve_slot_id']);
                if (!$source || (int) $source->rank !== (int) $data['expected_reserve_rank']) $this->fail('The reserve changed. Refresh the player list.');
                $status = app(SelectedTeamRosterStatus::class)->classify($event, collect([$team]));
                $playerId = (int) ($team->noProfile ? $source->player_profile : $source->player_id);
                $isReserve = $team->noProfile ? isset($status['imported'][$team->id][$source->id]) : isset($status['profile'][$team->id][$playerId]);
                if (!$isReserve || (int) $source->pay_status !== 0) $this->fail('Choose an unpaid reserve from this team.');
                $sourceRank = (int) $source->rank;
                $mirror = $team->noProfile ? $mirrors->firstWhere('rank', $sourceRank) : $source;
                $this->assertReservePromotionAllowed($event, $team, $source, $actor);
                if ($playerId > 0) $player = Player::findOrFail($playerId);
                if ($mirror) {
                    if ($target) {
                        $payments->updateTeamPlayerSlot($mirror, ['rank' => min(0, (int) $mirrors->min('rank')) - 1]);
                        $payments->updateTeamPlayerSlot($target, ['rank' => $sourceRank]);
                    }
                    $payments->updateTeamPlayerSlot($mirror, ['rank' => $rank]);
                } elseif ($playerId > 0) {
                    if ($target) $payments->updateTeamPlayerSlot($target, ['player_id' => $playerId]);
                    else $mirror = $payments->createUnpaidTeamPlayerSlot($team, $player, $rank);
                }
                if ($team->noProfile) $source->update(['rank' => $rank]);
                \App\Models\TeamRosterPromotion::create([
                    'event_id' => $event->id, 'team_id' => $team->id, 'no_profile_slot_id' => $team->noProfile ? $source->id : null,
                    'profile_slot_id' => $team->noProfile ? $mirror?->id : $source->id, 'player_id' => $playerId,
                    'source_rank' => $sourceRank, 'target_rank' => $rank, 'actor_id' => $actor->id, 'created_at' => now(),
                ]);
                activity('team-roster')->performedOn($team)->causedBy($actor)->withProperties([
                    'event_id' => $event->id, 'source_slot_id' => $source->id, 'source_rank' => $sourceRank, 'target_rank' => $rank, 'player_id' => $playerId,
                ])->log('promoted team reserve into empty numbered place');
            } else {
                $player = Player::query()->lockForUpdate()->findOrFail($data['player_id']);
                if ($team->noProfile) {
                    $this->change($event, $region, $team, $actor, 'add', ['name' => $player->name, 'surname' => $player->surname, '_replacement_rank' => $rank]);
                    $newSlot = NoProfileTeamPlayer::where('team_id', $team->id)->where('rank', $rank)->firstOrFail();
                    app(ImportedTeamRosterService::class)->relink($event, $newSlot, $player->id, 0, $rank, $actor);
                } else {
                    $this->change($event, $region, $team, $actor, 'add', ['player_id' => $player->id, '_replacement_rank' => $rank]);
                }
            }
            app(TeamDrawAdaptationService::class)->adaptEvent($event);
        });
    }

    public function assertReservePromotionAllowed(Event $event, Team $team, NoProfileTeamPlayer|TeamPlayer $source, User $actor): void
    {
        abort_unless($actor->hasRole('super-user') || \Illuminate\Support\Facades\Gate::forUser($actor)->allows('team.players.manage', $team), 403);
        if (!$event->isTeam() || $event->isInterprovincialTrials()) $this->fail('Use the selection workflow for this event.');
        abort_unless(app(EventTeamScope::class)->query($event)->whereKey($team->id)->exists(), 404);
        abort_unless((int) $source->team_id === (int) $team->id, 404);
        if ($team->noProfile) {
            $identities = NoProfileTeamPlayer::where('team_id', $team->id)->where('rank', $source->rank)->lockForUpdate()->get();
            if ($identities->count() !== 1 || (int) $identities->first()->id !== (int) $source->id) $this->fail('This roster has duplicate or inconsistent imported positions.');
        }
        if ($team->category) app(TeamSelectionInvitationService::class)->assertRosterEditable($team);
        $this->assertBeforeCompetition($event, $team);
        if (TeamSelectionInvitation::where('team_id', $team->id)->whereHas('selectionImport', fn ($query) => $query->whereIn('status', ['draft', 'sent']))->exists()) $this->fail('Use the selection workflow for this team.');
        $playerId = (int) ($team->noProfile ? $source->player_profile : $source->player_id);
        $mirrors = TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->where('rank', $source->rank)->lockForUpdate()->get();
        $mirror = $mirrors->first();
        if ((int) $source->pay_status !== 0 || $mirrors->count() > 1 || ($mirror && ((int) $mirror->pay_status !== 0 || (int) $mirror->player_id !== $playerId))) $this->fail('The reserve profile and roster place are inconsistent or paid.');
        if ($playerId < 1) return;
        $player = Player::query()->lockForUpdate()->findOrFail($playerId);
        try { app(PlayerEligibilityService::class)->assertEligible($player, $event); }
        catch (\RuntimeException $exception) { $this->fail($exception->getMessage()); }
        if (TeamPaymentOrder::where('event_id', $event->id)->forPlayerHistory([$playerId])->lockForUpdate()->exists()
            || \App\Models\ClothingOrder::where('event_id', $event->id)->where('player_id', $playerId)->lockForUpdate()->exists()
            || \App\Models\CategoryEventRegistration::withTrashed()->whereHas('categoryEvent', fn ($query) => $query->where('event_id', $event->id))
                ->whereHas('registration.players', fn ($query) => $query->where('players.id', $playerId))->lockForUpdate()->exists()) $this->fail('This reserve has checkout or registration history. Use the selection workflow.');
        if (TeamPlayer::withoutGlobalScopes()->whereIn('team_id', app(EventTeamScope::class)->query($event)->select('teams.id'))->where('player_id', $playerId)->when($mirror, fn ($query) => $query->where('id', '!=', $mirror->id))->exists()
            || NoProfileTeamPlayer::whereIn('team_id', app(EventTeamScope::class)->query($event)->select('teams.id'))->where('player_profile', $playerId)
                ->when($team->noProfile, fn ($query) => $query->where('id', '!=', $source->id))->exists()) $this->fail('This reserve is also assigned to another place in this event.');
    }

    public function confirmMainPlace(Event $event, ?EventRegion $region, Team $team, User $actor, int $slotId, int $expectedRank): void
    {
        DB::transaction(function () use ($event, $region, $team, $actor, $slotId, $expectedRank): void {
            app(TeamDrawAdaptationService::class)->lockEvent($event->id);
            $team = Team::query()->lockForUpdate()->findOrFail($team->id);
            $this->authorize($event, $region?->fresh(), $team, $actor);
            $source = $team->noProfile ? NoProfileTeamPlayer::where('team_id', $team->id)->lockForUpdate()->findOrFail($slotId)
                : TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->lockForUpdate()->findOrFail($slotId);
            if ((int) $source->rank !== $expectedRank || $expectedRank < 1 || $expectedRank > $team->num_team_members) $this->fail('This main place changed. Refresh the player list.');
            $this->assertReservePromotionAllowed($event, $team, $source, $actor);
            $captured = app(SelectedTeamRosterStatus::class)->captureMainPromotions($event, $team);
            app(SelectedTeamRosterStatus::class)->continueMainPromotions($event, $team, $actor, array_intersect_key($captured, [$slotId => true]));
        });
    }

    public function reorder(Event $event, ?EventRegion $region, Team $team, User $actor, array $orderedIds): void
    {
        DB::transaction(function () use ($event, $region, $team, $actor, $orderedIds): void {
            app(TeamDrawAdaptationService::class)->lockEvent($event->id);
            $this->authorize($event, $region, $team, $actor);
            $this->assertBeforeCompetition($event, $team);
            $slots = $team->noProfile ? $team->team_players_no_profile()->orderBy('rank')->lockForUpdate()->get()
                : TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->orderBy('rank')->lockForUpdate()->get()->filter(fn ($slot) => (int) $slot->player_id > 0 || $slot->rank <= $team->num_team_members)->values();
            $ids = $slots->pluck('id')->map('intval')->all();
            $orderedIds = array_map('intval', $orderedIds);
            if (count(array_unique($orderedIds)) !== count($orderedIds) || collect($ids)->sort()->values()->all() !== collect($orderedIds)->sort()->values()->all()) $this->fail('The player list changed. Refresh and try again.');
            if ($slots->pluck('rank')->unique()->count() !== $slots->count()) $this->fail('This roster needs administrator review.');
            $promotionHistory = app(SelectedTeamRosterStatus::class)->captureMainPromotions($event, $team);
            if ($ids === $orderedIds) {
                app(SelectedTeamRosterStatus::class)->continueMainPromotions($event, $team, $actor, $promotionHistory);
                return;
            }
            $import = TeamSelectionImport::where('event_id', $event->id)->where('region_id', $team->region_id)->latest('id')->first();
            $invitations = $import && in_array($import->status, ['draft', 'sent'], true) ? $import->invitations()->where('team_id', $team->id)
                ->whereNotNull('roster_rank')->whereIn('status', [TeamSelectionInvitation::INVITED, TeamSelectionInvitation::ACCEPTED_PENDING_PAYMENT, TeamSelectionInvitation::PAID_CONFIRMED])->orderBy('roster_rank')->get() : collect();
            if ($invitations->isNotEmpty()) {
                $byPlayer = $invitations->keyBy('player_id');
                $byId = $slots->keyBy('id');
                $selectionIds = collect($orderedIds)->map(fn ($id) => $byPlayer->get($byId->get($id)->player_id)?->id)->filter()->values()->all();
                app(TeamSelectionInvitationService::class)->reorderRoster($import, $team, $selectionIds, $actor, $invitations->pluck('id')->all());
            } elseif ($team->noProfile) {
                $current = $ids;
                foreach ($orderedIds as $position => $id) {
                    $index = array_search($id, $current, true);
                    while ($index > $position) {
                        app(ImportedTeamRosterService::class)->move($event, NoProfileTeamPlayer::findOrFail($id), 'up', $actor, false);
                        [$current[$index - 1], $current[$index]] = [$current[$index], $current[$index - 1]];
                        $index--;
                    }
                }
            } else {
                if ($team->category) app(TeamSelectionInvitationService::class)->assertRosterEditable($team);
                $ranks = $slots->pluck('rank')->all();
                $base = min(0, (int) $slots->min('rank')) - $slots->count() - 1;
                $payments = app(TeamPaymentService::class);
                foreach ($slots as $offset => $slot) $payments->updateTeamPlayerSlot($slot, ['rank' => $base + $offset]);
                foreach ($orderedIds as $position => $id) $payments->updateTeamPlayerSlot($slots->firstWhere('id', $id), ['rank' => $ranks[$position]]);
            }
            app(SelectedTeamRosterStatus::class)->continueMainPromotions($event, $team, $actor, $promotionHistory);
            activity('team-roster')->performedOn($team)->causedBy($actor)->withProperties(['event_id' => $event->id, 'before_ids' => $ids, 'after_ids' => $orderedIds])->log('reordered team roster');
        });
    }

    private function assertBeforeCompetition(Event $event, Team $team): void
    {
        if ($this->isLocked($event, $team)) {
            $this->fail('The competition has fixture history. Ask the event administrator to change this roster.');
        }
    }

    public function isLocked(Event $event, Team $team): bool
    {
        return \App\Models\TeamSubstitution::where('event_id', $event->id)->exists()
            || \App\Models\TeamTie::where(fn ($query) => $query->where('home_team_id', $team->id)->orWhere('away_team_id', $team->id))->exists()
            || \App\Models\TeamFixturePlayer::whereHas('fixture.draw', fn ($query) => $query->where('event_id', $event->id))->exists();
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['roster' => $message]);
    }

    private function hasDuplicateRawName($slots, string $name, string $surname, ?int $exceptId = null): bool
    {
        $normalize = fn (string $first, string $last) => mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($first.' '.$last)));
        $wanted = $normalize($name, $surname);
        $profiles = Player::whereIn('id', $slots->pluck('player_profile')->filter())->lockForUpdate()->get()->keyBy('id');
        return $slots->contains(function ($slot) use ($exceptId, $normalize, $wanted, $profiles): bool {
            if ((int) $slot->id === $exceptId) return false;
            $profile = $profiles->get($slot->player_profile);
            return $normalize($slot->name, $slot->surname) === $wanted
                || ($profile && $normalize($profile->name, $profile->surname) === $wanted);
        });
    }
}
