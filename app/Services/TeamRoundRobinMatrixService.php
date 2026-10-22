<?php

namespace App\Services;

use App\Models\Draw;
use Illuminate\Support\Collection;

/** A presentation of canonical tie outcomes and standings, never a scoring engine. */
final class TeamRoundRobinMatrixService
{
    public function supports(Draw $draw): bool
    {
        $phase = $draw->team_draw_selection['competition']['phase'] ?? null;
        $roundRobin = $phase === 'round_robin'
            || ($draw->team_format_snapshot['pairing_format'] ?? null) === 'round_robin'
            || in_array($draw->settings?->workflow, ['round_robin', 'round_robin_playoffs'], true)
            || preg_match('/round[ -]?robin/i', $draw->draw_types?->drawTypeName ?? '') === 1;
        return $draw->isTeamDraw()
            && $phase !== 'playoff' && $roundRobin
            && !$draw->usesFlexibleMonrad()
            && $draw->teamTies()->exists();
    }

    public function forDraw(Draw $draw, ?array $rows = null, bool $publishedOnly = false, ?Collection $allowedFixtures = null): array
    {
        $rows ??= app(TeamStandingsService::class)->forDraw($draw, $publishedOnly);
        $ids = array_column($rows, 'team_id');
        $ties = $draw->teamTies()
            ->whereIn('home_team_id', $ids)->whereIn('away_team_id', $ids)
            ->when($publishedOnly, fn ($query) => $query->whereNotNull('published_at')->whereIn('status', ['published', 'completed']))
            ->with(['rubbers.teamResults', 'rubbers.draw'])->orderBy('round_nr')->orderBy('tie_nr')->get();
        $cells = [];
        foreach ($ties as $tie) {
            if ($tie->rubbers->contains(fn ($rubber) => (int) $rubber->draw_id !== (int) $draw->id)) { continue; }
            $outcome = app(TeamStandingsService::class)->tieOutcome($tie);
            $started = $tie->rubbers->contains(fn ($rubber) => $rubber->teamResults->isNotEmpty()
                || (int) $rubber->match_status === \App\Domain\Draws\Enums\FixtureState::STATUS_PARTIAL);
            $canScore = $tie->rubbers->contains(fn ($rubber) =>
                ($allowedFixtures === null || $allowedFixtures->contains('id', $rubber->id))
                && (auth()->user()?->can('team-fixture.saveScore', $rubber) ?? false));
            foreach (['home' => 'away', 'away' => 'home'] as $side => $other) {
                $cells[$tie->{$side.'_team_id'}][$tie->{$other.'_team_id'}][] = [
                    'tie' => $tie, 'for' => $outcome[$side], 'against' => $outcome[$other],
                    'status' => $outcome['complete'] ? 'Complete' : ($started ? 'In progress' : 'Unplayed'),
                    'canScore' => $canScore,
                ];
            }
        }
        return compact('draw', 'rows', 'cells');
    }
}
