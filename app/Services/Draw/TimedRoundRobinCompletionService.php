<?php

namespace App\Services\Draw;

use App\Domain\Draws\Guards\DrawGuard;
use App\Domain\Draws\Services\{ScoreValidationService, TennisScoreFormat};
use App\Models\{Draw, DrawAuditLog, Fixture};
use App\Services\IndividualMatchOutcomeService;
use Illuminate\Support\Facades\DB;

/** Explicit final-score submission can repair stale metadata without replaying tournament progression. */
final class TimedRoundRobinCompletionService
{
    public function finalizeIdentical(Fixture $expected, array $sets, ?int $decidingWinner = null): ?array
    {
        if ($decidingWinner !== null || $expected->stage !== 'RR' || !in_array((int) $expected->match_status, [1, 2], true)
            || ((int) $expected->match_status === 2 && $expected->winner_registration !== null)) return null;

        return DB::transaction(function () use ($expected, $sets): ?array {
            $draw = Draw::whereKey($expected->draw_id)->lockForUpdate()->firstOrFail();
            $draw->setRelation('settings', $draw->settings()->lockForUpdate()->first());
            $fixture = Fixture::whereKey($expected->id)->lockForUpdate()->firstOrFail();
            abort_unless((int) $fixture->draw_id === (int) $expected->draw_id
                && (int) $fixture->registration1_id === (int) $expected->registration1_id
                && (int) $fixture->registration2_id === (int) $expected->registration2_id, 409, 'Match participants changed. Refresh before saving.');
            $fixture->setRelation('draw', $draw);
            DrawGuard::requireScoreable($fixture);
            if ((int) $fixture->registration1_id < 1 || (int) $fixture->registration2_id < 1
                || (int) $fixture->registration1_id === (int) $fixture->registration2_id) return null;
            if ($draw->settings?->score_format !== TennisScoreFormat::TIMED || $fixture->stage !== 'RR'
                || !in_array((int) $fixture->match_status, [1, 2], true)
                || ((int) $fixture->match_status === 2 && $fixture->winner_registration !== null)) return null;
            $results = $fixture->fixtureResults()->orderBy('set_nr')->lockForUpdate()->get();
            $fixture->setRelation('fixtureResults', $results);
            if ($results->count() !== 1) return null;
            $row = $results->first();
            foreach (['set_nr', 'registration1_score', 'registration2_score'] as $column) {
                $raw = $row->getRawOriginal($column);
                if (filter_var($raw, FILTER_VALIDATE_INT) === false || $raw === null || (int) $raw < 0) return null;
            }
            if (!in_array((int) $row->set_nr, [0, 1], true)) return null;
            $recorded = $results->map(fn ($row) => [(int) $row->registration1_score, (int) $row->registration2_score])->all();
            if ($recorded !== $sets || !app(ScoreValidationService::class)->validate($fixture, $sets)['valid']) return null;
            $winner = app(IndividualMatchOutcomeService::class)->winnerFromSets($fixture, $sets);
            if (!$winner) return null;
            $loser = (int) $winner === (int) $fixture->registration1_id ? $fixture->registration2_id : $fixture->registration1_id;
            if ((int) $results->first()->winner_registration !== (int) $winner
                || (int) $results->first()->loser_registration !== (int) $loser) return null;

            $alreadyCompleted = (int) $fixture->match_status === 1;
            if ($alreadyCompleted && (int) $fixture->winner_registration !== (int) $winner) return null;
            if (!$alreadyCompleted) {
              $fixture->update(['winner_registration' => $winner, 'match_status' => 1]);
              DrawAuditLog::record($draw->id, 'timed_result_completion_normalized', $fixture->id, [
                'stage' => 'RR', 'sets' => $recorded, 'participants' => [$fixture->registration1_id, $fixture->registration2_id],
                'previous_status' => 2, 'previous_winner' => null, 'status' => 1, 'winner' => $winner,
                'metadata_only' => true, 'score_rows_preserved' => $results->pluck('id')->all(),
              ]);
            }

            return ['success' => true, 'mode' => 'RR', 'metadata_only' => true, 'already_completed' => $alreadyCompleted,
                'message' => 'The existing final timed score is now marked completed. Scores and playoff assignments were preserved.',
                'fixture' => ['id' => $fixture->id, 'draw_group_id' => $fixture->draw_group_id,
                    'r1_id' => $fixture->registration1_id, 'r2_id' => $fixture->registration2_id,
                    'home' => $fixture->registration1?->display_name ?? '', 'away' => $fixture->registration2?->display_name ?? '',
                    'score' => $sets[0][0].'-'.$sets[0][1], 'all_sets' => [$sets[0][0].'-'.$sets[0][1]],
                    'winner_registration' => $winner, 'home_score' => $sets[0][0], 'away_score' => $sets[0][1]]];
        });
    }
}
