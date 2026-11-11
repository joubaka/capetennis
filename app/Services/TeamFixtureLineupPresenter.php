<?php

namespace App\Services;

use App\Models\{NoProfileTeamPlayer, Team, TeamFixture, TeamPlayer, TeamTie};
use App\Support\RegionAbbreviation;
use App\Support\RegionLogo;
use App\Support\RegionBadge;
use Illuminate\Support\Collection;

/** Read-only display of original roster ranks, including composite mixed sides. */
class TeamFixtureLineupPresenter
{
    public function eligiblePublicLineups(Collection $fixtures): Collection
    {
        if ($fixtures->isEmpty()) return $fixtures;

        $legacy = $fixtures->filter(fn (TeamFixture $fixture) => !$fixture->team_tie_id);
        $slots = $legacy->flatMap(fn (TeamFixture $fixture) => $fixture->fixturePlayers);
        $eventIds = $legacy->pluck('draw.event_id')->unique();
        $profiles = $slots->pluck('team1_id')->merge($slots->pluck('team2_id'))->filter()->unique();
        $imports = $slots->pluck('team1_no_profile_id')->merge($slots->pluck('team2_no_profile_id'))->filter()->unique();
        $profileMemberships = collect();
        foreach ([TeamPlayer::class => 'player_id', NoProfileTeamPlayer::class => 'player_profile'] as $model => $profileKey) {
            if ($profiles->isEmpty()) break;
            $memberships = $model::query()->with('team.category')->whereIn($profileKey, $profiles)
                ->whereHas('team.category', fn ($query) => $query->whereIn('event_id', $eventIds))->get();
            foreach ($memberships as $membership) {
                if ($category = $membership->team?->category) {
                    $profileMemberships->put($category->event_id.':'.$membership->{$profileKey}, true);
                }
            }
        }
        $importEvents = $imports->isEmpty() ? collect() : NoProfileTeamPlayer::query()->with('team.category')->whereKey($imports->all())->get()
            ->mapWithKeys(fn (NoProfileTeamPlayer $import) => [$import->id => (int) $import->team?->category?->event_id]);

        $sourceIds = $fixtures->mapWithKeys(fn (TeamFixture $fixture) => [$fixture->id => $this->teamSourceIds($fixture)]);
        $teamIds = $sourceIds->flatten()->filter()->unique();
        $teamEvents = $teamIds->isEmpty() ? collect() : Team::query()->with('category')->whereKey($teamIds->all())->get()
            ->mapWithKeys(fn (Team $team) => [$team->id => (int) $team->category?->event_id]);

        return $fixtures->filter(function (TeamFixture $fixture) use ($profileMemberships, $importEvents, $sourceIds, $teamEvents) {
            $event = (int) $fixture->draw?->event_id;
            if (!$event || $fixture->fixturePlayers->contains(fn ($slot) =>
                ($slot->team1_id && $slot->team1_no_profile_id) || ($slot->team2_id && $slot->team2_no_profile_id))) return false;
            if ($fixture->team_tie_id) {
                $tie = $fixture->teamTie;
                return $tie?->published_at && (int) $tie->draw_id === (int) $fixture->draw_id
                    && in_array($tie->status, [TeamTie::STATUS_PUBLISHED, TeamTie::STATUS_COMPLETED], true)
                    && $sourceIds[$fixture->id]->every(fn ($id) => $teamEvents->get($id) === $event);
            }
            if ($fixture->draw->team_scoring_rules !== null || $fixture->draw->team_format_snapshot !== null) return false;

            // Legacy fixtures have no canonical team sides for the lineup presenter to validate.
            return $fixture->fixturePlayers->every(function ($slot) use ($event, $profileMemberships, $importEvents) {
                foreach ([1, 2] as $side) {
                    $profile = $slot->{'team'.$side.'_id'};
                    $import = $slot->{'team'.$side.'_no_profile_id'};
                    if ($profile && !$profileMemberships->has($event.':'.$profile)) return false;
                    if ($import && $importEvents->get($import) !== $event) return false;
                }

                return true;
            });
        })->values();
    }

    private function teamSourceIds(TeamFixture $fixture): Collection
    {
        $tie = $fixture->teamTie;
        if (!$tie) return collect();

        $mixed = $fixture->draw?->team_draw_selection['mixed_sides'] ?? [];

        return collect([$tie->home_team_id, $tie->away_team_id])->flatMap(fn ($id) => isset($mixed[$id])
            ? [$mixed[$id]['boys'], $mixed[$id]['girls']]
            : [$id]);
    }

    public function prepare(Collection $fixtures, bool $publicDraw = false, ?int $selectedPlayerId = null, bool $includeProfileIds = false): void
    {
        (new \Illuminate\Database\Eloquent\Collection($fixtures->all()))->loadMissing([
            'draw.categoryEvent.category', 'teamTie', 'fixtureResults', 'region1Name', 'region2Name', 'fixturePlayers.player1', 'fixturePlayers.player2',
            'fixturePlayers.noProfile1', 'fixturePlayers.noProfile2', 'team1', 'team2',
        ]);
        $ids = $fixtures->flatMap(function ($fixture) {
            $map = $fixture->draw?->team_draw_selection['mixed_sides'] ?? [];
            $tie = $this->tie($fixture);
            return collect([$tie?->home_team_id, $tie?->away_team_id])
                ->filter()->flatMap(fn ($id) => isset($map[$id]) ? [$map[$id]['boys'], $map[$id]['girls']] : [$id]);
        })->unique();
        $teams = Team::with(['category.category', 'regions', 'team_players', 'team_players_no_profile'])->whereIn('id', $ids)->get()->keyBy('id');
        $regionIds = $fixtures->flatMap(fn ($fixture) => $fixture->fixturePlayers->flatMap(fn ($row) => collect($row->participant_snapshot ?? [])->pluck('region_id')))->filter()->unique();
        $historicalRegions = \App\Models\TeamRegion::whereIn('id', $regionIds)->get()->keyBy('id');
        foreach ($fixtures as $fixture) {
            $rankSources = ['home' => [], 'away' => []];
            $fixture->setAttribute('lineup_display', [
                'home' => $this->side($fixture, 'home', $teams, $historicalRegions, $rankSources['home'], $publicDraw, $selectedPlayerId, $includeProfileIds),
                'away' => $this->side($fixture, 'away', $teams, $historicalRegions, $rankSources['away'], $publicDraw, $selectedPlayerId, $includeProfileIds),
            ]);
            $fixture->setAttribute('lineup_rank_sources', $rankSources);
            $fixture->setAttribute('tie_display', [
                'home' => $this->teamLabel($fixture, 'home', $teams),
                'away' => $this->teamLabel($fixture, 'away', $teams),
            ]);
            $fixture->setAttribute('tie_mobile_display', [
                'home' => $this->mobileTeamLabel($fixture, 'home', $teams),
                'away' => $this->mobileTeamLabel($fixture, 'away', $teams),
            ]);
        }
    }

    private function side(TeamFixture $fixture, string $side, Collection $teams, Collection $historicalRegions, array &$rankSources, bool $publicDraw, ?int $selectedPlayerId, bool $includeProfileIds): array
    {
        $home = $side === 'home';
        $tie = $this->tie($fixture);
        $teamId = $tie?->{$side.'_team_id'};
        $entry = $fixture->draw?->team_draw_selection['mixed_sides'][$teamId] ?? null;
        $sourceIds = $entry ? [$entry['boys'], $entry['girls']] : [$teamId];
        $sources = collect($sourceIds)->map(fn ($id) => $teams->get($id))->filter(fn ($team) => $team
            && (int) $team->category?->event_id === (int) $fixture->draw?->event_id);
        $region = $sources->first()?->regions ?? ($home ? $fixture->region1Name : $fixture->region2Name);
        $label = RegionAbbreviation::label($region);
        $regionName = $region?->region_name;
        $historicalRegionUsed = false;
        $protected = $fixture->fixtureResults->isNotEmpty() || (int) $fixture->match_status !== 0 || $tie?->isCompleted();
        $template = collect(($tie?->format_snapshot ?? $fixture->draw?->team_format_snapshot)['rubbers'] ?? [])
            ->first(fn ($rubber) => (int) $rubber['sequence'] === (int) $fixture->rubber_sequence);
        $positions = $template[$side.'_positions'] ?? [];
        $players = [];
        $selectedPlayerAssigned = false;
        foreach ($fixture->fixturePlayers->sortBy('slot_no')->values() as $index => $row) {
            $historical = $row->participant_snapshot[$home ? 1 : 2] ?? null;
            $sideNumber = $home ? 1 : 2;
            if ($historical && ((int) ($historical['event_id'] ?? 0) !== (int) $fixture->draw?->event_id
                || ($protected
                    ? !in_array((int) ($historical['source_team_id'] ?? 0), array_map('intval', array_filter($sourceIds)), true)
                    : !$sources->contains(fn ($source) => (int) $source->id === (int) ($historical['source_team_id'] ?? 0)))
                || ($historical['profile_id'] ?? null) !== ($row->{'team'.$sideNumber.'_id'} ? (int) $row->{'team'.$sideNumber.'_id'} : null)
                || ($historical['imported_id'] ?? null) !== ($row->{'team'.$sideNumber.'_no_profile_id'} ? (int) $row->{'team'.$sideNumber.'_no_profile_id'} : null))) $historical = null;
            if ($historical && $protected && !$historicalRegionUsed && ($originalRegion = $historicalRegions->get($historical['region_id'] ?? null))) {
                $region = $originalRegion;
                $label = RegionAbbreviation::label($originalRegion);
                $regionName = $originalRegion->region_name;
                $historicalRegionUsed = true;
            }
            $profile = $home ? $row->player1 : $row->player2;
            $imported = $home ? $row->noProfile1 : $row->noProfile2;
            $member = null;
            foreach ($sources as $source) {
                $member = $profile ? $source->team_players->firstWhere('player_id', $profile->id)
                    : ($imported ? $source->team_players_no_profile->firstWhere('id', $imported->id) : null);
                if ($member) break;
            }
            if ($publicDraw && $tie && !$member && !$historical) {
                $profile = null;
                $imported = null;
            }
            $rank = $historical['rank'] ?? $member?->rank;
            $rankSource = ($profile || $imported) && (int) $rank > 0 ? ($historical ? 'snapshot' : 'roster') : null;
            if (!$rank && isset($positions[$index])) {
                $rank = $entry ? (int) ceil($positions[$index] / 2) : $positions[$index];
            }
            if ($rank === null) {
                $rank = $fixture->{$side.'_rank_nr'};
                if (($profile || $imported) && (int) $rank > 0) $rankSource = 'legacy';
            }
            $rankSources[] = $rankSource;
            $name = $historical['name'] ?? $profile?->full_name ?? ($imported ? trim($imported->name.' '.$imported->surname) : 'TBD');
            if ($selectedPlayerId && (int) $profile?->id === $selectedPlayerId) $selectedPlayerAssigned = true;
            $display = ['name' => $name, 'rank' => (int) $rank > 0 ? (int) $rank : null];
            if ($includeProfileIds || auth()->user()?->hasRole('super-user')) { $display['player_id'] = $profile?->id; }
            $players[] = $display;
        }
        if (!$players) {
            $legacyRank = $fixture->{$side.'_rank_nr'};
            foreach (($home ? $fixture->team1 : $fixture->team2) as $profile) {
                if ($selectedPlayerId && (int) $profile->id === $selectedPlayerId) $selectedPlayerAssigned = true;
                $rankSources[] = (int) $legacyRank > 0 ? 'legacy' : null;
                $display = ['name' => $profile->full_name, 'rank' => (int) $legacyRank > 0 ? (int) $legacyRank : null];
                if ($includeProfileIds || auth()->user()?->hasRole('super-user')) { $display['player_id'] = $profile->id; }
                $players[] = $display;
            }
        }
        return ['region' => $label, 'region_short_name' => trim((string) $region?->short_name), 'region_name' => $regionName, 'region_logo' => RegionLogo::path($regionName),
            'region_color' => RegionBadge::color($region), 'players' => $players]
            + ($selectedPlayerId ? ['selected_player_assigned' => $selectedPlayerAssigned] : []);
    }

    private function tie(TeamFixture $fixture): ?\App\Models\TeamTie
    {
        $tie = $fixture->teamTie;
        return $tie && (int) $tie->draw_id === (int) $fixture->draw_id ? $tie : null;
    }

    private function teamLabel(TeamFixture $fixture, string $side, Collection $teams): string
    {
        if ($fixture->draw?->isPlacementDraft()) return $this->tie($fixture)?->{$side.'_side_name'} ?? 'Qualifier pending';
        $teamId = $this->tie($fixture)?->{$side.'_team_id'};
        $team = $teams->get($teamId);
        if ($team && (int) $team->category?->event_id === (int) $fixture->draw?->event_id) {
            return $fixture->draw?->team_draw_selection['mixed_sides'][$teamId]['name'] ?? $team->name;
        }
        $region = $side === 'home' ? $fixture->region1Name : $fixture->region2Name;
        return $region?->region_name ?: 'TBD';
    }

    private function mobileTeamLabel(TeamFixture $fixture, string $side, Collection $teams): string
    {
        $teamId = $this->tie($fixture)?->{$side.'_team_id'};
        $mixed = $fixture->draw?->team_draw_selection['mixed_sides'][$teamId] ?? null;
        $sourceIds = $mixed ? [$mixed['boys'], $mixed['girls']] : [$teamId];
        $sources = collect($sourceIds)->map(fn ($id) => $teams->get($id))
            ->filter(fn ($team) => $team && (int) $team->category?->event_id === (int) $fixture->draw?->event_id);
        $regions = $sources->map(fn ($team) => RegionAbbreviation::label($team->regions))->filter()->unique()->join(' + ');
        $categories = $sources->map(fn ($team) => $team->category?->category?->name)->filter()->unique()->join(' + ');
        if (!$regions) {
            $region = $side === 'home' ? $fixture->region1Name : $fixture->region2Name;
            $regions = RegionAbbreviation::label($region);
        }
        if (!$categories) {
            $categories = $fixture->draw?->categoryEvent?->category?->name;
        }

        return $regions ? trim($regions.' '.$categories) : $this->teamLabel($fixture, $side, $teams);
    }

}
