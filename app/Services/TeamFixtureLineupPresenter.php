<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TeamFixture;
use Illuminate\Support\Collection;

/** Read-only display of original roster ranks, including composite mixed sides. */
class TeamFixtureLineupPresenter
{
    public function prepare(Collection $fixtures): void
    {
        $ids = $fixtures->flatMap(function ($fixture) {
            $map = $fixture->draw?->team_draw_selection['mixed_sides'] ?? [];
            return collect([$fixture->teamTie?->home_team_id, $fixture->teamTie?->away_team_id])
                ->filter()->flatMap(fn ($id) => isset($map[$id]) ? [$map[$id]['boys'], $map[$id]['girls']] : [$id]);
        })->unique();
        $teams = Team::with(['category', 'regions', 'team_players', 'team_players_no_profile'])->whereIn('id', $ids)->get()->keyBy('id');
        foreach ($fixtures as $fixture) {
            $fixture->setAttribute('lineup_display', [
                'home' => $this->side($fixture, 'home', $teams),
                'away' => $this->side($fixture, 'away', $teams),
            ]);
        }
    }

    private function side(TeamFixture $fixture, string $side, Collection $teams): array
    {
        $home = $side === 'home';
        $teamId = $fixture->teamTie?->{$side.'_team_id'};
        $entry = $fixture->draw?->team_draw_selection['mixed_sides'][$teamId] ?? null;
        $sourceIds = $entry ? [$entry['boys'], $entry['girls']] : [$teamId];
        $sources = collect($sourceIds)->map(fn ($id) => $teams->get($id))->filter(fn ($team) => $team
            && (int) $team->category?->event_id === (int) $fixture->draw?->event_id);
        $region = $sources->first()?->regions ?? ($home ? $fixture->region1Name : $fixture->region2Name);
        $label = trim((string) $region?->short_name) ?: (trim((string) $region?->region_name)
            ?: ($entry['name'] ?? $sources->first()?->name ?? 'TBD'));
        $template = collect($fixture->draw?->team_format_snapshot['rubbers'] ?? [])
            ->first(fn ($rubber) => (int) $rubber['sequence'] === (int) $fixture->rubber_sequence);
        $positions = $template[$side.'_positions'] ?? [];
        $players = [];
        foreach ($fixture->fixturePlayers->sortBy('slot_no')->values() as $index => $row) {
            $historical = $row->participant_snapshot[$home ? 1 : 2] ?? null;
            $sideNumber = $home ? 1 : 2;
            if ($historical && (($historical['profile_id'] ?? null) !== ($row->{'team'.$sideNumber.'_id'} ? (int) $row->{'team'.$sideNumber.'_id'} : null)
                || ($historical['imported_id'] ?? null) !== ($row->{'team'.$sideNumber.'_no_profile_id'} ? (int) $row->{'team'.$sideNumber.'_no_profile_id'} : null))) $historical = null;
            $profile = $home ? $row->player1 : $row->player2;
            $imported = $home ? $row->noProfile1 : $row->noProfile2;
            $member = null;
            foreach ($sources as $source) {
                $member = $profile ? $source->team_players->firstWhere('player_id', $profile->id)
                    : ($imported ? $source->team_players_no_profile->firstWhere('id', $imported->id) : null);
                if ($member) break;
            }
            $rank = $historical['rank'] ?? $member?->rank;
            if (!$rank && isset($positions[$index])) {
                $rank = $entry ? (int) ceil($positions[$index] / 2) : $positions[$index];
            }
            $rank ??= $fixture->{$side.'_rank_nr'};
            $name = $historical['name'] ?? $profile?->full_name ?? ($imported ? trim($imported->name.' '.$imported->surname) : 'TBD');
            $players[] = ['name' => $name, 'rank' => (int) $rank > 0 ? (int) $rank : null];
        }
        return ['region' => $label, 'players' => $players];
    }
}
