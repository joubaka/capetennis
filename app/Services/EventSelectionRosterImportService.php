<?php

namespace App\Services;

use App\Models\{CategoryEvent, Event, EventSelectionFinalization, EventSelectionRosterImport, NoProfileTeamPlayer, Player, Team, TeamFixture, TeamPlayer, TeamRegion, User};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventSelectionRosterImportService
{
    public function regionOverview(Event $target, EventSelectionFinalization $finalization, User $actor): array
    {
        abort_unless($actor->can('event.manage', $target), 403);
        $source = Event::findOrFail($finalization->event_id);
        abort_unless($actor->can('event.manage', $source), 403);
        abort_unless($target->isTeam() && $source->isTeam() && $source->id !== $target->id, 422, 'Choose a different team event.');
        abort_unless(EventSelectionFinalization::active()->whereKey($finalization->id)->exists(), 409, 'The source teams were unlocked. Finalize them again before importing.');
        $sourceRegions = $source->regions()->orderBy('region_name')->limit(501)->get();
        abort_if($sourceRegions->count() > 500, 422, 'Selection import supports up to 500 regions per event.');
        $sourceTeams = app(EventTeamScope::class)->query($source)->limit(501)->get()->keyBy('id');
        abort_if($sourceTeams->count() > 500, 422, 'Selection import supports up to 500 teams per event.');
        $regionGroups = [];
        foreach ($finalization->groups as $key => $group) {
            foreach ($this->groupRegions($group, $sourceTeams, $sourceRegions) as $id) $regionGroups[$id][] = $key;
        }
        return compact('source', 'sourceRegions', 'regionGroups');
    }

    private function groupRegions(array $group, $sourceTeams, $sourceRegions): array
    {
        $cohort = array_values(array_unique(array_map('intval', $group['region_ids'] ?? [])));
        if ($cohort === []) {
            $teamIds = collect($group['snapshot'] ?? [])->flatMap(fn ($row) => $row['source_team_ids'] ?? [])->map(fn ($id) => (int) $id);
            $cohort = $sourceTeams->only($teamIds->all())->pluck('region_id')->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        }
        abort_unless($cohort !== [] && ! array_diff($cohort, $sourceRegions->modelKeys()), 422, 'The finalized selection has regions outside the source event. Review its region links before importing.');
        return $cohort;
    }

    public function preview(Event $target, EventSelectionFinalization $finalization, User $actor, ?array $sourceRegionIds = null): array
    {
        abort_unless($actor->can('event.manage', $target), 403);
        $source = Event::findOrFail($finalization->event_id);
        abort_unless($actor->can('event.manage', $source), 403);
        abort_unless($target->isTeam() && $source->isTeam() && $source->id !== $target->id, 422, 'Choose a different team event.');
        abort_unless(EventSelectionFinalization::active()->whereKey($finalization->id)->exists(), 409, 'The source teams were unlocked. Finalize them again before importing.');
        $sourceTeams = app(EventTeamScope::class)->query($source)->with('category.category')->limit(501)->get()->keyBy('id');
        $targetTeams = app(EventTeamScope::class)->query($target)->with('category.category')->orderBy('name')->limit(501)->get();
        abort_if($sourceTeams->count() > 500 || $targetTeams->count() > 500, 422, 'Selection import supports up to 500 teams per event.');
        $sourceRegions = $source->regions()->orderBy('region_name')->limit(501)->get();
        abort_if($sourceRegions->count() > 500, 422, 'Selection import supports up to 500 regions per event.');
        $selectedRegions = $sourceRegionIds === null ? null : array_values(array_unique(array_map('intval', $sourceRegionIds)));
        if ($selectedRegions !== null && ($selectedRegions === [] || array_diff($selectedRegions, $sourceRegions->modelKeys()))) {
            throw ValidationException::withMessages(['source_region_ids' => 'Choose regions belonging to the source event.']);
        }
        $regionGroups = [];
        $groups = [];
        foreach ($finalization->groups as $key => $group) {
            // An age/gender selection may combine several regions. Keep that frozen
            // selection intact rather than splitting players or changing their ranks.
            $cohort = $this->groupRegions($group, $sourceTeams, $sourceRegions);
            foreach ($cohort as $id) $regionGroups[$id][] = $key;
            if ($selectedRegions !== null) {
                if (! array_intersect($cohort, $selectedRegions)) continue;
                if (array_diff($cohort, $selectedRegions)) {
                    throw ValidationException::withMessages(['source_region_ids' => 'The finalized u/'.str_replace('-', ' ', $key).' selection combines regions. Select all of these regions together: '.$sourceRegions->whereIn('id', $cohort)->pluck('region_name')->implode(', ').'.']);
                }
            }
            $snapshot = collect($group['snapshot'])->keyBy(fn ($row) => (string) $row['id']);
            $players = [];
            $categoryIds = [];
            foreach ($group['selected_keys'] as $identity) {
                $row = $snapshot->get((string) $identity);
                abort_unless($row && isset($group['selection_statuses'][(string) $identity]), 422, 'The finalized selection is incomplete.');
                $sourceIds = array_map('intval', $row['source_team_ids'] ?? []);
                abort_unless($sourceIds !== [] && ! array_diff($sourceIds, $sourceTeams->keys()->all()), 422, 'A selected player has source teams outside this event.');
                foreach ($sourceIds as $teamId) {
                    $categoryId = $sourceTeams[$teamId]->category?->category_id;
                    if ($categoryId) $categoryIds[] = (int) $categoryId;
                }
                $person = $this->person($sourceTeams, $row);
                $players[] = $person + ['key' => (string) $identity, 'name' => $row['name'], 'status' => $group['selection_statuses'][(string) $identity]['status'],
                    'rank' => (int) $group['selection_statuses'][(string) $identity]['position']];
            }
            $categoryIds = array_values(array_unique($categoryIds));
            abort_unless(count($categoryIds) === 1, 422, 'Link the source teams for '.$key.' to one category before importing.');
            $categoryId = $categoryIds[0];
            $categoryName = $sourceTeams->first(fn ($team) => (int) $team->category?->category_id === $categoryId)?->category?->category?->name;
            abort_unless((app(TeamResultRankingService::class)->group(new TeamFixture(['age' => $categoryName]))['key'] ?? null) === $key,
                422, 'The source category does not match the finalized age and gender group.');
            $groups[$key] = ['label' => 'u/'.str_replace('-', ' ', $key), 'category_id' => $categoryId, 'players' => $players, 'source_region_ids' => $cohort,
                'teams' => $targetTeams->filter(fn ($team) => (int) $team->category?->category_id === $categoryId)->values(),
                'main_count' => collect($players)->where('status', 'main')->count(), 'reserve_count' => collect($players)->where('status', 'reserve')->count()];
        }
        if ($selectedRegions !== null && ($groups === [] || array_diff($selectedRegions, array_keys($regionGroups)))) {
            throw ValidationException::withMessages(['source_region_ids' => 'Only regions included in a finalized selection can be imported.']);
        }
        return compact('source', 'groups', 'sourceRegions', 'regionGroups');
    }

    public function import(Event $target, EventSelectionFinalization $finalization, int $regionId, array $mapping, User $actor, ?array $sourceRegionIds = null): EventSelectionRosterImport
    {
        return DB::transaction(function () use ($target, $finalization, $regionId, $mapping, $actor, $sourceRegionIds) {
            // The same source-event lock serializes unlock, finalization and import.
            Event::whereIn('id', [$target->id, $finalization->event_id])->orderBy('id')->lockForUpdate()->get();
            $preview = $this->preview($target, $finalization->fresh(), $actor, $sourceRegionIds);
            abort_unless($target->regions()->where('team_regions.id', $regionId)->exists(), 422, 'Choose a region belonging to the destination event.');
            if (array_diff(array_keys($preview['groups']), array_keys($mapping)) || array_diff(array_keys($mapping), array_keys($preview['groups']))) {
                throw ValidationException::withMessages(['mapping' => 'Choose a destination for every selected finalized group.']);
            }
            $existing = EventSelectionRosterImport::where('event_id', $target->id)->where('finalization_id', $finalization->id)->where('region_id', $regionId)->first();
            if ($existing) {
                if (array_diff(array_keys($existing->groups), array_keys($preview['groups'])) || array_diff(array_keys($preview['groups']), array_keys($existing->groups))) {
                    throw ValidationException::withMessages(['source_region_ids' => 'A different selection from this finalized event was already imported into this destination region. Review the existing import before continuing.']);
                }
                return $existing;
            }
            if (TeamFixture::whereHas('draw', fn ($q) => $q->where('event_id', $target->id))->exists()
                || \App\Models\TeamTie::whereHas('draw', fn ($q) => $q->where('event_id', $target->id))->exists()) {
                throw ValidationException::withMessages(['mapping' => 'This event already has fixtures. Use its roster replacement workflow to preserve the competition.']);
            }
            $region = TeamRegion::findOrFail($regionId);
            $groups = [];
            $seen = [];
            $priorImports = EventSelectionRosterImport::where('event_id', $target->id)->limit(501)->get(['groups']);
            abort_if($priorImports->count() > 500, 422, 'Selection import supports up to 500 imports per event.');
            $priorKeys = $priorImports->flatMap(fn ($import) => collect($import->groups)->flatMap(fn ($group) => collect($group['players'])->pluck('key')))->all();
            foreach ($preview['groups'] as $key => $group) {
                $teamId = (int) $mapping[$key];
                if ($teamId) {
                    $team = $group['teams']->firstWhere('id', $teamId);
                    abort_unless($team && (int) $team->region_id === $regionId, 422, 'Choose a matching destination team and region.');
                    $team = Team::whereKey($teamId)->lockForUpdate()->firstOrFail();
                    app(\App\Services\TeamSelection\TeamSelectionInvitationService::class)->assertRosterEditable($team);
                    if (TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->where(fn ($q) => $q->where('player_id', '>', 0)->orWhere('pay_status', 1))->exists()
                        || NoProfileTeamPlayer::where('team_id', $team->id)->exists() || $team->published
                        || \App\Models\TeamPaymentOrder::where('team_id', $team->id)->exists()) {
                        throw ValidationException::withMessages(['mapping' => $team->name.' already contains players, payment history or is published. Choose an empty private team.']);
                    }
                } else {
                    $category = CategoryEvent::firstOrCreate(['event_id' => $target->id, 'category_id' => $group['category_id']], ['entry_fee' => 0, 'ordering' => (int) CategoryEvent::where('event_id', $target->id)->max('ordering') + 1]);
                    if (Team::where('category_event_id', $category->id)->where('region_id', $regionId)->exists()) {
                        throw ValidationException::withMessages(['mapping' => 'A destination team already exists for '.$group['label'].'. Select it explicitly.']);
                    }
                    $team = new Team(['name' => trim(($region->short_name ?: $region->region_name).' '.$group['label']), 'year' => $target->start_date?->format('Y') ?: date('Y'),
                        'region_id' => $regionId, 'category_event_id' => $category->id, 'published' => false, 'noProfile' => true, 'num_team_members' => $group['main_count']]);
                    $team->forceFill(['user_id' => $actor->id, 'personal_team' => false])->save();
                }
                app(\App\Services\TeamSelection\TeamSelectionInvitationService::class)->assertRosterEditable($team);
                $team->update(['num_team_members' => $group['main_count'], 'noProfile' => true]);
                TeamPlayer::withoutGlobalScopes()->where('team_id', $team->id)->where('player_id', 0)->where(fn ($q) => $q->whereNull('pay_status')->orWhere('pay_status', 0))->delete();
                foreach ($group['players'] as $person) {
                    $identity = $person['profile_id'] ? 'profile:'.$person['profile_id'] : $person['key'];
                    if (isset($seen[$identity])) throw ValidationException::withMessages(['mapping' => 'The same player is selected in more than one group. Resolve the source selection first.']);
                    if (in_array($person['key'], $priorKeys, true)) throw ValidationException::withMessages(['mapping' => $person['name'].' was already imported into this event.']);
                    $seen[$identity] = true;
                    if ($person['profile_id'] && (TeamPlayer::whereHas('team.category', fn ($q) => $q->where('event_id', $target->id))->where('player_id', $person['profile_id'])->exists()
                        || NoProfileTeamPlayer::whereHas('team.category', fn ($q) => $q->where('event_id', $target->id))->where('player_profile', $person['profile_id'])->exists()
                        || \App\Models\TeamPaymentOrder::where('event_id', $target->id)->forPlayerHistory([$person['profile_id']])->exists()
                        || \App\Models\TeamSelectionInvitation::where('event_id', $target->id)->where('player_id', $person['profile_id'])->exists())) {
                        throw ValidationException::withMessages(['mapping' => $person['name'].' is already selected in the destination event.']);
                    }
                    $slot = NoProfileTeamPlayer::create($person['attributes'] + ['team_id' => $team->id, 'rank' => $person['rank'], 'pay_status' => 0, 'player_profile' => $person['profile_id']]);
                    if ($person['profile_id']) {
                        try { app(PlayerEligibilityService::class)->assertEligible($person['profile_id'], $target); }
                        catch (\RuntimeException $exception) { throw ValidationException::withMessages(['mapping' => $exception->getMessage()]); }
                        app(\App\Domain\Payments\Services\TeamPaymentService::class)->createUnpaidTeamPlayerSlot($team, Player::findOrFail($person['profile_id']), $person['rank']);
                    }
                    $groups[$key]['players'][] = ['key' => $person['key'], 'name' => $person['name'], 'rank' => $person['rank'], 'status' => $person['status'], 'slot_id' => $slot->id, 'profile_id' => $person['profile_id']];
                }
                $groups[$key] += ['team_id' => $team->id, 'team_name' => $team->name, 'main_count' => $group['main_count'], 'reserve_count' => $group['reserve_count'], 'source_region_ids' => $group['source_region_ids']];
            }
            $import = EventSelectionRosterImport::create(['event_id' => $target->id, 'finalization_id' => $finalization->id, 'region_id' => $regionId, 'imported_by' => $actor->id, 'groups' => $groups, 'imported_at' => now()]);
            activity('team-selection')->performedOn($target)->causedBy($actor)->withProperties(['source_event_id' => $finalization->event_id, 'finalization_id' => $finalization->id, 'source_fingerprint' => $finalization->fingerprint, 'import_id' => $import->id, 'team_ids' => collect($groups)->pluck('team_id')->all()])->log('imported finalized selected teams and reserves');
            return $import;
        });
    }

    private function person($sourceTeams, array $row): array
    {
        $key = (string) $row['id'];
        if (ctype_digit($key) && (int) $key > 0) {
            $participants = collect($row['source_participants'] ?? []);
            abort_unless($participants->isNotEmpty() && $participants->every(fn ($participant) => $participant['type'] === 'profile'
                && (int) $participant['id'] === (int) $key && $sourceTeams->has((int) $participant['team_id'])), 422, 'A selected profile has incomplete source participant evidence.');
            $player = Player::findOrFail((int) $key);
            return ['profile_id' => (int) $player->id, 'attributes' => ['name' => $player->name, 'surname' => $player->surname]];
        }
        abort_unless(preg_match('/^imported:(\d+):(\d+)$/', $key, $matches) && $sourceTeams->has((int) $matches[1]), 422, 'A selected imported identity cannot be resolved.');
        $participants = collect($row['source_participants'] ?? [])->filter(fn ($person) => $person['type'] === 'imported' && (int) $person['team_id'] === (int) $matches[1])->unique('id');
        abort_unless($participants->count() === 1, 422, 'An imported selection has ambiguous participant history. Review the source selection.');
        $member = NoProfileTeamPlayer::where('team_id', (int) $matches[1])->findOrFail((int) $participants->first()['id']);
        $participant = $participants->first();
        $profileAttested = array_key_exists('profile_id', $participant) || array_key_exists('rating_player_id', $row);
        $frozenProfile = array_key_exists('profile_id', $participant) ? $participant['profile_id'] : ($row['rating_player_id'] ?? null);
        // Legacy snapshots attest the imported participant, not a later profile link.
        // Keep those copies unlinked until a manager reviews the destination identity.
        abort_unless(trim($member->name.' '.$member->surname) === trim($row['name'])
            && (! $profileAttested || (int) $frozenProfile === (int) ($member->player_profile ?? 0)), 422, 'An imported player identity changed after finalization. Review and finalize the source again.');
        return ['profile_id' => $frozenProfile ? (int) $frozenProfile : null, 'profile_link_review' => ! $profileAttested && $member->player_profile,
            'attributes' => $member->only(['name', 'surname'])];
    }
}
