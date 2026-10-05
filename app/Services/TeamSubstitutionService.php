<?php

namespace App\Services;

use App\Models\{Event, NoProfileTeamPlayer, Player, Team, TeamFixture, TeamFixturePlayer, TeamPaymentOrder, TeamPlayer, TeamSubstitution, User};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** A competition transition, deliberately separate from payment transfers. */
class TeamSubstitutionService
{
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['replacement' => $message]);
    }

    public function preview(Team $team, array $input, User $actor): array
    {
        return DB::transaction(fn () => $this->plan($team, $input, $actor));
    }

    private function plan(Team $team, array $input, User $actor): array
    {
        $event = Event::whereKey($team->category->event_id)->lockForUpdate()->firstOrFail();
        $team = Team::without(['team_players', 'team_players_no_profile'])->lockForUpdate()->findOrFail($team->id);
        $category = \App\Models\CategoryEvent::lockForUpdate()->findOrFail($team->category_event_id);
        if ((int) $category->event_id !== (int) $event->id) $this->fail('The team event changed. Reload before replacing.');
        $category->setRelation('event', $event);
        $category->setRelation('category', \App\Models\Category::lockForUpdate()->findOrFail($category->category_id));
        $team->setRelation('category', $category);
        $team->setRelation('regions', \App\Models\TeamRegion::whereKey($team->region_id)->lockForUpdate()->first());
        $eventTeams = Team::without(['team_players', 'team_players_no_profile'])->whereHas('category', fn ($q) => $q->where('event_id', $event->id))->orderBy('id')->limit(501)->lockForUpdate()->get();
        if ($eventTeams->count() > 500) $this->fail('This event has over 500 teams; use a bounded replacement scope.');
        $members = TeamPlayer::whereIn('team_id', $eventTeams->pluck('id'))->lockForUpdate()->get();
        $profiles = Player::whereIn('id', $members->pluck('player_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($members as $member) $member->setRelation('player', $profiles->get($member->player_id));
        $importedMembers = NoProfileTeamPlayer::whereIn('team_id', $eventTeams->pluck('id'))->lockForUpdate()->get();
        $substitutions = TeamSubstitution::where('event_id', $event->id)->orderBy('id')->limit(1001)->lockForUpdate()->get();
        if ($substitutions->count() > 1000) $this->fail('This event has over 1000 replacement records. Use a bounded administration workflow.');
        foreach ($eventTeams as $eventTeam) {
            $eventTeam->setRelation('team_players', $members->where('team_id', $eventTeam->id)->values());
            $eventTeam->setRelation('team_players_no_profile', $importedMembers->where('team_id', $eventTeam->id)->values());
            $eventTeam->setRelation('competitionSubstitutions', $substitutions->where('team_id', $eventTeam->id)->values());
        }
        $team->setRelation('team_players', $members->where('team_id', $team->id)->values());
        $team->setRelation('team_players_no_profile', $importedMembers->where('team_id', $team->id)->values());
        $team->setRelation('competitionSubstitutions', $substitutions->where('team_id', $team->id)->values());
        Gate::forUser($actor)->authorize('individual-draw.create', $event);
        Gate::forUser($actor)->authorize('team.players.manage', $team);
        app(\App\Services\TeamSelection\TeamSelectionInvitationService::class)->assertRosterEditable($team, true);
        if ($team->category->locked_at) $this->fail('This event category is locked.');
        $team = app(TeamDrawSideResolver::class)->activeRoster($team);
        $oldType = $input['old_type'];
        $source = $oldType === 'profile' ? $team->team_players->firstWhere('player_id', (int) $input['old_id'])
            : $team->team_players_no_profile->firstWhere('id', (int) $input['old_id']);
        if (!$source) $this->fail('The source is no longer an active member of this team.');
        $anchorType = $source->competition_anchor_type ?? $oldType;
        $anchorId = (int) ($source->competition_anchor_id ?? $source->id);
        $target = $input['new_type'] === 'profile' ? Player::lockForUpdate()->findOrFail($input['new_id']) : null;
        if ($target && $oldType === 'profile' && (int) $source->player_id === (int) $target->id) $this->fail('Choose a different replacement.');
        if ($target && $oldType === 'imported' && (int) $source->player_profile === (int) $target->id) $this->fail('The imported entry and profile identify the same participant.');
        if ($target) app(PlayerEligibilityService::class)->assertEligible($target, $event);
        $name = $target?->full_name ?? trim($input['name'].' '.$input['surname']);
        $gender = $target?->gender ?? ($input['gender'] ?? null);
        $gender = in_array($gender, [1, '1', 'male'], true) ? 'boys' : (in_array($gender, [2, '2', 'female'], true) ? 'girls' : null);
        $key = app(TeamDrawSideResolver::class)->categoryKey($team->category->category->name);
        if ($key['gender'] && $gender !== $key['gender']) $this->fail('The replacement must meet the source category gender eligibility.');
        $dob = $target?->dateOfBirth ?? ($input['date_of_birth'] ?? null);
        if (!$target) {
            $keyFor = static fn ($name, $surname, $birth) => $birth ? mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name.' '.$surname))).'|'.Carbon::parse($birth)->format('Y-m-d') : null;
            $incomingKey = $keyFor($input['name'], $input['surname'], $dob);
            $historicalImportedIds = $substitutions->filter(fn ($record) => ($record->details['new_type'] ?? null) === 'imported')->map(fn ($record) => $record->details['new_identity_id']);
            $historicalImported = NoProfileTeamPlayer::whereIn('id', $historicalImportedIds)->lockForUpdate()->get();
            foreach ($importedMembers->concat($historicalImported) as $identity) {
                if ($incomingKey === $keyFor($identity->name, $identity->surname, $identity->date_of_birth)) $this->fail('This imported identity already appears in the event history. Verify the existing participant instead of creating a second identity.');
            }
            $historicalProfileIds = $substitutions->filter(fn ($record) => ($record->details['new_type'] ?? null) === 'profile')->map(fn ($record) => $record->details['new_identity_id']);
            $historicalProfiles = Player::whereIn('id', $historicalProfileIds)->lockForUpdate()->get();
            foreach ($profiles->values()->concat($historicalProfiles) as $profile) {
                if ($incomingKey === $keyFor($profile->name, $profile->surname, $profile->dateOfBirth)) $this->fail('These details match an existing event player profile. Verify that participant instead of creating a second identity.');
            }
        }
        if (preg_match('/^u\/(\d+)/', $key['group'], $age)) {
            if (!$dob || (int) Carbon::parse($event->start_date)->year - (int) Carbon::parse($dob)->year > (int) $age[1]) {
                $this->fail('Verify the replacement date of birth and age-category eligibility before substituting.');
            }
        }
        if ($target) {
            // Specific stand-ins do not change the active roster, but still reserve a
            // competition identity. Keep this conservative across event fixtures.
            foreach ($substitutions as $previous) {
                $data = $previous->details;
                if (($data['new_type'] ?? null) === 'profile' && (int) ($data['new_identity_id'] ?? 0) === (int) $target->id
                    && ($data['scope'] ?? null) === 'specific') {
                    $this->fail('This player has an audited stand-in assignment in this event. Choose another player to avoid duplicate lineup participation.');
                }
            }
            foreach ($eventTeams as $other) {
                $active = app(TeamDrawSideResolver::class)->activeRoster($other);
                if ($active->team_players->contains('player_id', $target->id) || $active->team_players_no_profile->contains('player_profile', $target->id)) {
                    $this->fail('The replacement is already on an active event roster.');
                }
            }
            if (\App\Models\TeamSelectionInvitation::where('event_id', $event->id)->where('player_id', $target->id)->exists()) {
                $this->fail('This participant has a selection invitation. Complete the selection/reserve workflow instead of bypassing it.');
            }
        }
        $profileIds = array_filter([$oldType === 'profile' ? $source->player_id : $source->player_profile, $target?->id]);
        $orders = TeamPaymentOrder::where('event_id', $event->id)->forPlayerHistory($profileIds)->lockForUpdate()->get();
        foreach ($orders as $order) {
            if (!$order->pay_status && ($order->payfast_handed_off_at || (float) $order->wallet_reserved > 0)) $this->fail('Resolve the existing in-flight checkout through the payment workflow before replacing this participant.');
        }
        $fee = round((float) $event->entryFee + (float) $team->regions?->region_fee, 2);
        if ($fee > 0 && !$target) $this->fail('Fresh fee-paying imported replacements are not enabled in this workflow. Existing paid coverage cannot automatically transfer.');
        if ($target) app(\App\Domain\Payments\Services\TeamPaymentService::class)->assertOwnCompetitionCoverage($event, $team, $target);
        $column = $oldType === 'profile' ? '_id' : '_no_profile_id';
        $oldId = $oldType === 'profile' ? $source->player_id : $source->id;
        $affectedQuery = TeamFixture::whereHas('draw', fn ($q) => $q->where('event_id', $event->id))
            ->whereHas('fixturePlayers', fn ($q) => $q->where(function ($q) use ($column, $oldId, $anchorType, $anchorId, $team) {
                $q->where('team1'.$column, $oldId)->orWhere('team2'.$column, $oldId);
                foreach ([1, 2] as $side) $q->orWhere(fn ($q) => $q->where('participant_snapshot->'.$side.'->source_team_id', $team->id)
                    ->where('participant_snapshot->'.$side.'->anchor_type', $anchorType)->where('participant_snapshot->'.$side.'->anchor_id', $anchorId));
            }));
        $references = (clone $affectedQuery)->limit(1001)->get(['id', 'draw_id', 'team_tie_id']);
        if ($references->count() > 1000) $this->fail('This participant has over 1000 event fixtures. Use a smaller event scope before replacing.');
        $lockedDraws = \App\Models\Draw::whereIn('id', $references->pluck('draw_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $lockedTies = \App\Models\TeamTie::whereIn('id', $references->pluck('team_tie_id')->filter())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $fixtures = $affectedQuery->with(['draw', 'teamTie', 'fixtureResults', 'fixturePlayers'])->orderBy('id')->limit(1001)->lockForUpdate()->get();
        $lockedRows = TeamFixturePlayer::whereIn('team_fixture_id', $fixtures->pluck('id'))->orderBy('id')->lockForUpdate()->get();
        $lockedResults = \App\Models\TeamFixtureResult::whereIn('team_fixture_id', $fixtures->pluck('id'))->lockForUpdate()->get();
        foreach ($fixtures as $fixture) {
            $fixture->setRelation('draw', $lockedDraws->get($fixture->draw_id));
            $fixture->setRelation('teamTie', $lockedTies->get($fixture->team_tie_id));
            $fixture->setRelation('fixturePlayers', $lockedRows->where('team_fixture_id', $fixture->id)->values());
            $fixture->setRelation('fixtureResults', $lockedResults->where('team_fixture_id', $fixture->id)->values());
        }
        if ($lockedRows->count() > 4000) $this->fail('This source slot has over 4000 participant assignments. Use a bounded event scope.');
        if ($fixtures->count() > 1000) $this->fail('This participant has over 1000 event fixtures. Use a smaller event scope before replacing.');
        $legacyCount = $fixtures->filter(fn ($fx) => !$fx->teamTie)->count();
        $fixtures = $fixtures->filter(function ($fx) use ($team) {
            if (!$fx->teamTie) return false;
            $map = $fx->draw->team_draw_selection['mixed_sides'] ?? [];
            return collect([$fx->teamTie->home_team_id, $fx->teamTie->away_team_id])->contains(fn ($id) => (int) $id === (int) $team->id
                || isset($map[$id]) && in_array((int) $team->id, [$map[$id]['boys'], $map[$id]['girls']], true));
        });
        $eligible = $fixtures->filter(fn ($fx) => \App\Services\Draw\DrawMutationPolicy::for($fx->draw)->canModifySchedule()
            && (int) $fx->match_status === 0 && $fx->fixtureResults->isEmpty() && !$fx->teamTie?->isCompleted()
            && (!$fx->scheduled_at || $fx->scheduled_at->isFuture()));
        if ($target) $eligible = $eligible->filter(function ($fx) use ($target, $team, $anchorType, $anchorId, $oldType, $oldId) {
            foreach ($fx->fixturePlayers as $row) foreach ([1, 2] as $side) {
                $participant = $row->participant_snapshot[$side] ?? null;
                $matches = $participant ? (int) $participant['source_team_id'] === (int) $team->id
                    && ($participant['anchor_type'] ?? null) === $anchorType && (int) ($participant['anchor_id'] ?? 0) === $anchorId
                    : (int) $row->{'team'.$side.($oldType === 'profile' ? '_id' : '_no_profile_id')} === (int) $oldId;
                if ($matches && ((int) $row->{'team'.$side.'_id'} !== (int) $target->id || $row->{'team'.$side.'_no_profile_id'})) return true;
            }
            return false;
        });
        $requested = array_values(array_unique(array_map('intval', $input['fixture_ids'] ?? [])));
        if ($input['scope'] === 'specific') {
            if (array_diff($requested, $eligible->pluck('id')->all())) $this->fail('A selected fixture is outside this event participant scope or has already started.');
            $selected = $eligible->whereIn('id', $requested);
        } elseif ($input['scope'] === 'round') {
            $selected = $eligible->where('round_nr', '>=', (int) $input['from_round']);
        } else {
            $selected = $eligible;
        }
        // Upcoming means not started; the schedule alone cannot establish play.
        $state = ['input' => $input, 'event' => $event->getAttributes(), 'category' => $category->getAttributes(),
            'region' => $team->regions?->getAttributes(), 'source' => $source->getAttributes(), 'target' => $target?->getAttributes(),
            'substitutions' => $substitutions->map->getAttributes()->all(),
            'eligible_ids' => $eligible->pluck('id')->values()->all(), 'selected_ids' => $selected->pluck('id')->values()->all(),
            'fixtures' => $fixtures->map(fn ($fx) => [$fx->id, $fx->getAttributes(), $fx->draw->getAttributes(), $fx->teamTie?->getAttributes(), $fx->fixturePlayers->map->getAttributes()->all(), $fx->fixtureResults->map->getAttributes()->all()])->all(),
            'payments' => $orders->map->getAttributes()->all()];
        $existingDrawIds = \App\Models\Draw::where('event_id', $event->id)->orderBy('id')->limit(1001)->lockForUpdate()->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (count($existingDrawIds) > 1000) $this->fail('This event has over 1000 draws; use a bounded replacement scope.');
        $state['existing_draw_ids'] = $existingDrawIds;
        app(TeamFixtureLineupPresenter::class)->prepare($fixtures);
        return ['fingerprint' => hash('sha256', json_encode($state)), 'team_id' => $team->id, 'event_id' => $event->id, 'existing_draw_ids' => $existingDrawIds, 'anchor_type' => $anchorType, 'anchor_id' => $anchorId,
            'source_rank' => (int) $source->rank, 'old_name' => $oldType === 'profile' ? $source->player->full_name : trim($source->name.' '.$source->surname),
            'new_name' => $name, 'selected_ids' => $selected->pluck('id')->values()->all(),
            'warnings' => $legacyCount ? ["{$legacyCount} legacy fixtures without source-team ties remain unchanged; this workflow cannot safely replace those assignments."] : [],
            'fixtures' => $fixtures->map(fn ($fx) => ['id' => $fx->id, 'draw' => $fx->draw->drawName, 'round' => $fx->round_nr,
                'home' => $fx->lineup_display['home'], 'away' => $fx->lineup_display['away'],
                'selected' => $selected->contains('id', $fx->id), 'protected' => !$eligible->contains('id', $fx->id), 'published' => (bool) $fx->draw->published || (bool) $fx->teamTie?->published_at])->all(),
            'financial_note' => 'Original payments, receipts and refunds remain with the original payer and beneficiary. No money changes here.'];
    }

    public function execute(Team $team, array $input, User $actor): TeamSubstitution
    {
        return DB::transaction(function () use ($team, $input, $actor) {
            Event::whereKey($team->category->event_id)->lockForUpdate()->firstOrFail();
            $team = Team::lockForUpdate()->findOrFail($team->id);
            Gate::forUser($actor)->authorize('team.players.manage', $team);
            $eventId = $team->category->event_id;
            Gate::forUser($actor)->authorize('individual-draw.create', Event::findOrFail($eventId));
            $replay = TeamSubstitution::where('event_id', $eventId)->where('request_key', $input['request_key'])->first();
            $planInput = array_diff_key($input, array_flip(['fingerprint', 'request_key']));
            if ($replay) {
                if ((int) $replay->team_id !== (int) $team->id || $replay->fingerprint !== $input['fingerprint']
                    || ($replay->details['input_hash'] ?? null) !== hash('sha256', json_encode($planInput))) $this->fail('This request key belongs to a different replacement.');
                return $replay;
            }
            $plan = $this->plan($team, $planInput, $actor);
            if (!hash_equals($plan['fingerprint'], $input['fingerprint'])) $this->fail('Roster, fixtures or payments changed after preview. Preview again.');
            if ($input['scope'] === 'specific' && !$plan['selected_ids']) $this->fail('Choose at least one eligible match for a specific-match replacement.');
            $all = TeamFixturePlayer::whereIn('team_fixture_id', array_column($plan['fixtures'], 'id'))
                ->with(['fixture.teamTie', 'fixture.draw'])->lockForUpdate()->get();
            $all = $all->filter(fn ($row) => collect($plan['fixtures'])->contains('id', $row->team_fixture_id));
            $sourceIds = $all->flatMap(function ($row) {
                $map = $row->fixture->draw->team_draw_selection['mixed_sides'] ?? [];
                return collect([$row->fixture->teamTie?->home_team_id, $row->fixture->teamTie?->away_team_id])->filter()
                    ->flatMap(fn ($id) => isset($map[$id]) ? [$map[$id]['boys'], $map[$id]['girls']] : [$id]);
            })->unique();
            $sourceTeams = Team::with(['category', 'team_players.player', 'team_players_no_profile'])->whereIn('id', $sourceIds)->get()
                ->map(fn ($sourceTeam) => app(TeamDrawSideResolver::class)->activeRoster($sourceTeam));
            app(TeamParticipantHistoryService::class)->capture($all, $sourceTeams);
            $anchorType = $plan['anchor_type'];
            $anchorId = $plan['anchor_id'];
            $incoming = $input['new_type'] === 'profile' ? Player::findOrFail($input['new_id'])
                : NoProfileTeamPlayer::create(['team_id' => null, 'name' => $input['name'], 'surname' => $input['surname'], 'date_of_birth' => $input['date_of_birth'], 'rank' => $plan['source_rank'], 'pay_status' => 0]);
            foreach ($all->whereIn('team_fixture_id', $plan['selected_ids']) as $row) {
                $snapshot = $row->participant_snapshot;
                $changed = false;
                foreach ([1, 2] as $side) {
                    if ((int) ($snapshot[$side]['source_team_id'] ?? 0) !== (int) $team->id
                        || ($snapshot[$side]['anchor_type'] ?? null) !== $anchorType || (int) ($snapshot[$side]['anchor_id'] ?? 0) !== $anchorId) continue;
                    $row->{'team'.$side.'_id'} = $input['new_type'] === 'profile' ? $incoming->id : null;
                    $row->{'team'.$side.'_no_profile_id'} = $input['new_type'] === 'imported' ? $incoming->id : null;
                    $snapshot[$side]['profile_id'] = $row->{'team'.$side.'_id'};
                    $snapshot[$side]['imported_id'] = $row->{'team'.$side.'_no_profile_id'};
                    $snapshot[$side]['name'] = $plan['new_name'];
                    $changed = true;
                }
                if (!$changed) continue;
                $row->forceFill(['participant_snapshot' => $snapshot])->save();
                $changedIds[$row->team_fixture_id] = true;
            }
            if (count($changedIds ?? []) !== count($plan['selected_ids'])) $this->fail('The reviewed source assignments changed. Preview again.');
            return TeamSubstitution::create(['event_id' => $eventId, 'team_id' => $team->id, 'actor_id' => $actor->id,
                'request_key' => $input['request_key'], 'fingerprint' => $input['fingerprint'], 'details' => $plan + ['input_hash' => hash('sha256', json_encode($planInput)), 'reason' => $input['reason'], 'scope' => $input['scope'], 'from_round' => $input['from_round'] ?? null, 'old_type' => $input['old_type'], 'old_id' => $input['old_id'], 'new_type' => $input['new_type'], 'new_identity_id' => $incoming->id, 'anchor_type' => $anchorType, 'anchor_id' => $anchorId], 'created_at' => now()]);
        });
    }
}
