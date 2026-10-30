<?php

namespace App\Services\Draw;

use App\Domain\Draws\Services\{ScoreValidationService, TennisScoreFormat};
use App\Models\Draw;
use App\Services\IndividualMatchOutcomeService;

/** Read-only group titles; never merges independent groups into category positions. */
final class RoundRobinGroupResultService
{
    public function annotate(Draw $draw, array $standings): array
    {
        if ($draw->isTwoStageRoundRobin()) {
            $offset = 0;
            foreach ($draw->groups->sortBy(fn ($group) => sprintf('%d_%04d_%010d', $group->round_robin_phase, $group->qualifying_position ?? 0, $group->id)) as $group) {
                $rows = array_values($standings[$group->id] ?? []);
                $fixtures = $draw->drawFixtures->where('draw_group_id', $group->id)->where('stage', 'RR');
                $complete = app(TwoStageRoundRobinService::class)->complete($draw, $group, $fixtures);
                $tied = ! app(TwoStageRoundRobinService::class)->positionsResolved($draw, $rows, $fixtures);
                $second = (int) $group->round_robin_phase === 2;
                foreach ($rows as $index => &$row) {
                    $row['group_result_status'] = ! $complete ? 'incomplete' : ($tied ? 'tied' : 'complete');
                    $row['group_winner'] = $second && $complete && ! $tied && $index === 0;
                    $row['display_position'] = $second ? ($complete && ! $tied ? $offset + $index + 1 : '—') : $index + 1;
                    $row['group_result_message'] = $second
                        ? ($complete && ! $tied ? 'Stage 2 — final positions.' : 'Stage 2 — final positions pending complete results and resolved ties.')
                        : 'Stage 1 — equal finishing positions qualify together for stage 2.';
                }
                unset($row);
                $standings[$group->id] = $rows;
                if ($second) $offset += count($rows);
            }
            return $standings;
        }
        if (! $draw->isRoundRobinOnly()) return $standings;

        foreach ($draw->groups as $group) {
            $rows = array_values($standings[$group->id] ?? []);
            $ids = $group->groupRegistrations->pluck('registration_id')->map(fn ($id) => (int) $id)->sort()->values();
            $fixtures = $draw->drawFixtures->filter(fn ($fixture) => $fixture->stage === 'RR' && (int) $fixture->draw_group_id === (int) $group->id);
            $pairs = [];
            $complete = $ids->count() >= 2 && $fixtures->count() === $ids->count() * ($ids->count() - 1) / 2;
            foreach ($fixtures as $fixture) {
                $pair = [(int) $fixture->registration1_id, (int) $fixture->registration2_id];
                sort($pair);
                $key = implode(':', $pair);
                $sets = $fixture->fixtureResults->sortBy('set_nr')->map(fn ($set) => [(int) $set->registration1_score, (int) $set->registration2_score])->values()->all();
                $complete = $complete && $pair[0] !== $pair[1] && $ids->contains($pair[0]) && $ids->contains($pair[1]) && ! isset($pairs[$key])
                    && app(ScoreValidationService::class)->validate($fixture, $sets)['valid']
                    && ($draw->settings?->score_format === TennisScoreFormat::TIMED || app(IndividualMatchOutcomeService::class)->winner($fixture) !== null);
                $pairs[$key] = true;
            }

            $status = ! $complete || count($rows) !== $ids->count() ? 'incomplete' : 'complete';
            if ($status === 'complete' && ! app(\App\Domain\Draws\Services\StandingsService::class)->hasUniqueLeader($draw, $rows, $fixtures)) $status = 'tied';
            foreach ($rows as $index => &$row) {
                $row['group_result_status'] = $status;
                $row['group_winner'] = $status === 'complete' && $index === 0;
            }
            unset($row);
            $standings[$group->id] = $rows;
        }

        return $standings;
    }

}
