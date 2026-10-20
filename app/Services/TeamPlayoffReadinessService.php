<?php

namespace App\Services;

use App\Models\Draw;

/** Read-only explanation of the existing placement qualification prerequisites. */
final class TeamPlayoffReadinessService
{
    public function forDraw(Draw $draw): ?array
    {
        $competition = $draw->team_draw_selection['competition'] ?? [];
        if (($competition['phase'] ?? null) !== 'round_robin') {
            return null;
        }

        $blockers = [];
        $teamCount = 0;
        try {
            $teamCount = app(TeamDrawSideResolver::class)->teams($draw)->count();
        } catch (\InvalidArgumentException $exception) {
            $blockers[] = 'Review the selected teams: '.$exception->getMessage();
        }
        if ($teamCount < 2) {
            $blockers[] = 'At least two valid selected sides are required.';
        }
        $expected = (int) ($teamCount * ($teamCount - 1) / 2);
        $ties = $draw->teamTies()->with('rubbers.teamResults', 'rubbers.draw')->get();
        $standings = app(TeamStandingsService::class);
        $completed = $ties->filter(fn ($tie) => $standings->tieOutcome($tie)['complete'])->count();
        $rubbers = $ties->flatMap->rubbers;
        $completedRubbers = $rubbers->filter(fn ($rubber) => app(TeamRubberResultService::class)->outcome($rubber)['complete'])->count();
        if ($draw->locked) {
            $blockers[] = 'Unlock this competition before qualifying teams.';
        }
        $scoringConfirmed = !($draw->team_scoring_rules['undecided'] ?? false);
        if (!$scoringConfirmed) {
            $blockers[] = 'Confirm scoring before qualifying teams.';
        }
        if ($ties->count() !== $expected || $completed !== $ties->count()) {
            $blockers[] = 'Complete every expected round-robin encounter.';
        }
        $ranksResolved = false;
        if ($scoringConfirmed && $completed === $expected && $ties->count() === $expected) {
            $rows = $standings->forDraw($draw);
            $ranksResolved = $teamCount >= 2 && count($rows) === $teamCount && count(array_unique(array_column($rows, 'rank'))) === count($rows);
            if (!$ranksResolved) {
                $blockers[] = 'Resolve tied standings using the agreed scoring criteria.';
            }
        }

        $format = collect($competition['playoff_format']['rubbers'] ?? []);
        $formatCounts = $format->countBy('rubber_code')->all();
        if ($format->isEmpty()) {
            $blockers[] = 'No saved playoff match format is available. Review the competition setup.';
        }
        $playoff = !empty($competition['playoff_draw_id'])
            ? Draw::where('event_id', $draw->event_id)->find($competition['playoff_draw_id']) : null;
        if (!empty($competition['playoff_draw_id']) && !$playoff) {
            $blockers[] = 'Linked playoff is unavailable; review competition setup.';
        }

        return [
            'expected_encounters' => $expected, 'saved_encounters' => $ties->count(),
            'completed_encounters' => $completed, 'total_rubbers' => $rubbers->count(),
            'completed_rubbers' => $completedRubbers, 'scoring_confirmed' => $scoringConfirmed,
            'ranks_resolved' => $ranksResolved, 'format_counts' => $formatCounts,
            'format_total' => $format->count(), 'blockers' => $blockers,
            'can_create' => !$playoff && empty($competition['playoff_draw_id']) && $blockers === [],
            'playoff' => $playoff,
        ];
    }
}
