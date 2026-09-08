<?php

namespace App\Domain\Ranking\Services;

use App\Domain\Ranking\Enums\RankingStatus;
use App\Models\Series;
use App\Models\SeriesRanking;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RankingHeadToHeadConfirmationService
{
    public function __construct(
        private readonly RankingHeadToHeadEligibilityService $eligibility,
    ) {}

    /** @return array<string, mixed> */
    public function confirm(Series $series, int $fixtureId, User $actor): array
    {
        return DB::transaction(function () use ($series, $fixtureId, $actor): array {
            DB::table('series')->where('id', $series->id)->lockForUpdate()->first();

            $runIds = SeriesRanking::where('series_id', $series->id)
                ->where('status', RankingStatus::Calculated->value)
                ->whereNotNull('run_id')
                ->distinct()
                ->pluck('run_id');
            if ($runIds->count() !== 1) {
                throw new \RuntimeException(
                    "Expected exactly one calculated ranking run for series {$series->id}; found {$runIds->count()}."
                );
            }

            $runId = (string) $runIds->first();
            $rows = SeriesRanking::where('series_id', $series->id)
                ->where('run_id', $runId)
                ->where('status', RankingStatus::Calculated->value)
                ->lockForUpdate()
                ->get()
                ->filter(fn (SeriesRanking $row) =>
                    (int) ($row->meta_json['head_to_head_decision']['fixture_id'] ?? 0) === $fixtureId
                );

            if ($rows->count() !== 2) {
                throw new \RuntimeException('That fixture is not an applied head-to-head decision in the current calculated ranking run.');
            }

            $decision = $rows->first()->meta_json['head_to_head_decision'];
            $playerIds = collect($decision['player_ids'] ?? [])->map(fn ($id) => (int) $id)->sort()->values();
            if ($playerIds->count() !== 2
                || $rows->pluck('player_id')->map(fn ($id) => (int) $id)->sort()->values()->all() !== $playerIds->all()
                || (int) ($decision['ranking_list_id'] ?? 0) !== (int) $rows->first()->ranking_list_id
                || ! in_array((int) ($decision['winner_player_id'] ?? 0), $playerIds->all(), true)
                || empty($decision['qualifying_set']['score'])
                || ! in_array($decision['phase'] ?? null, ['playoff', 'single_phase_round_robin'], true)) {
                throw new \RuntimeException('The stored head-to-head decision is incomplete or inconsistent; rebuild the ranking before confirming it.');
            }

            if (! empty($decision['confirmed_at'])) {
                $exists = DB::table('ranking_head_to_head_confirmations')
                    ->where('series_id', $series->id)
                    ->where('run_id', $runId)
                    ->where('ranking_list_id', (int) $decision['ranking_list_id'])
                    ->where('fixture_id', $fixtureId)
                    ->where('player1_id', $playerIds[0])
                    ->where('player2_id', $playerIds[1])
                    ->where('winner_player_id', (int) $decision['winner_player_id'])
                    ->exists();
                if (! $exists) {
                    throw new \RuntimeException('The stored confirmation is missing its audit record; rebuild the ranking before review.');
                }

                return $decision;
            }

            $fixtureQuery = DB::table('fixtures as ranking_fixtures')
                ->join('draws as ranking_draws', 'ranking_draws.id', '=', 'ranking_fixtures.draw_id')
                ->leftJoin('draw_settings as ranking_draw_settings', 'ranking_draw_settings.draw_id', '=', 'ranking_draws.id')
                ->join('player_registrations as ranking_pr1', 'ranking_pr1.registration_id', '=', 'ranking_fixtures.registration1_id')
                ->join('player_registrations as ranking_pr2', 'ranking_pr2.registration_id', '=', 'ranking_fixtures.registration2_id')
                ->where('ranking_fixtures.id', $fixtureId);
            $fixture = $this->eligibility->applyPhaseScope($fixtureQuery)->first([
                'ranking_fixtures.id',
                'ranking_fixtures.registration1_id',
                'ranking_fixtures.registration2_id',
                'ranking_fixtures.winner_registration',
                'ranking_fixtures.draw_group_id',
                'ranking_pr1.player_id as player1_id',
                'ranking_pr2.player_id as player2_id',
            ]);
            $liveSet = $this->eligibility->qualifyingFullSets(collect([$fixtureId]))->get($fixtureId);
            $livePlayers = $fixture
                ? collect([(int) $fixture->player1_id, (int) $fixture->player2_id])->sort()->values()
                : collect();
            $resultWinner = DB::table('fixture_results')
                ->where('fixture_id', $fixtureId)
                ->whereNotNull('winner_registration')
                ->orderByDesc('set_nr')
                ->orderByDesc('id')
                ->value('winner_registration');
            $winnerRegistration = (int) (($fixture?->winner_registration) ?: $resultWinner);
            $liveWinnerPlayerId = $winnerRegistration === (int) ($fixture?->registration1_id)
                ? (int) ($fixture?->player1_id)
                : ($winnerRegistration === (int) ($fixture?->registration2_id) ? (int) ($fixture?->player2_id) : 0);
            if ($liveSet && $fixture) {
                $liveSet['score'] = $winnerRegistration === (int) $fixture->registration1_id
                    ? $liveSet['registration1_score'].'-'.$liveSet['registration2_score']
                    : $liveSet['registration2_score'].'-'.$liveSet['registration1_score'];
            }

            if (! $fixture
                || $livePlayers->all() !== $playerIds->all()
                || $liveWinnerPlayerId !== (int) $decision['winner_player_id']
                || ! $liveSet
                || $liveSet['score'] !== $decision['qualifying_set']['score']) {
                throw new \RuntimeException('The source fixture changed or is no longer eligible. Rebuild the ranking before confirming it.');
            }

            $confirmedAt = now();
            $confirmedDecision = array_merge($decision, [
                'confirmed_by' => (int) $actor->id,
                'confirmed_at' => $confirmedAt->toIso8601String(),
            ]);

            foreach ($rows as $row) {
                $meta = $row->meta_json;
                $meta['head_to_head_decision'] = $confirmedDecision;
                $row->forceFill(['meta_json' => $meta])->save();
            }

            DB::table('ranking_head_to_head_confirmations')->updateOrInsert(
                [
                    'series_id' => (int) $series->id,
                    'run_id' => $runId,
                    'ranking_list_id' => (int) $rows->first()->ranking_list_id,
                    'player1_id' => $playerIds[0],
                    'player2_id' => $playerIds[1],
                ],
                [
                    'fixture_id' => $fixtureId,
                    'winner_player_id' => (int) $decision['winner_player_id'],
                    'confirmed_by' => (int) $actor->id,
                    'confirmed_at' => $confirmedAt,
                    'decision_snapshot' => json_encode($confirmedDecision, JSON_THROW_ON_ERROR),
                    'created_at' => $confirmedAt,
                    'updated_at' => $confirmedAt,
                ]
            );

            activity('ranking')
                ->performedOn($series)
                ->causedBy($actor)
                ->withProperties([
                    'run_id' => $runId,
                    'ranking_list_id' => (int) $rows->first()->ranking_list_id,
                    'fixture_id' => $fixtureId,
                    'player_ids' => $playerIds->all(),
                    'winner_player_id' => (int) $decision['winner_player_id'],
                    'phase' => $decision['phase'],
                    'qualifying_set' => $decision['qualifying_set'],
                ])
                ->log('Ranking head-to-head decision confirmed');

            return $confirmedDecision;
        });
    }

    public function assertAllConfirmed(Series $series, string $runId): void
    {
        $rows = SeriesRanking::where('series_id', $series->id)
            ->where('run_id', $runId)
            ->where('status', RankingStatus::Calculated->value)
            ->get();

        $legacyApplied = $rows->filter(function (SeriesRanking $row): bool {
            $notes = $row->meta_json['tiebreak_notes'] ?? [];

            return collect($notes)->contains(fn ($note) => str_contains((string) $note, 'latest head-to-head winner'))
                && empty($row->meta_json['head_to_head_decision']);
        });
        if ($legacyApplied->isNotEmpty()) {
            throw new \RuntimeException('This ranking contains a legacy head-to-head result. Rebuild it before marking it reviewed.');
        }

        $decisions = $this->uniqueDecisions($rows);
        $pending = $decisions->filter(fn (array $decision) => empty($decision['confirmed_at']));
        if ($pending->isNotEmpty()) {
            throw new \RuntimeException(
                'Confirm all applied head-to-head decisions before marking this ranking reviewed. '
                .$pending->count().' confirmation(s) remain.'
            );
        }

        foreach ($decisions as $decision) {
            $players = collect($decision['player_ids'] ?? [])->map(fn ($id) => (int) $id)->sort()->values();
            $exists = $players->count() === 2 && DB::table('ranking_head_to_head_confirmations')
                ->where('series_id', $series->id)
                ->where('run_id', $runId)
                ->where('ranking_list_id', (int) ($decision['ranking_list_id'] ?? 0))
                ->where('fixture_id', (int) ($decision['fixture_id'] ?? 0))
                ->where('player1_id', $players[0])
                ->where('player2_id', $players[1])
                ->where('winner_player_id', (int) ($decision['winner_player_id'] ?? 0))
                ->exists();
            if (! $exists) {
                throw new \RuntimeException('A head-to-head confirmation audit record is missing or does not match the ranking run.');
            }
        }
    }

    /** @return Collection<string, array<string, mixed>> */
    private function uniqueDecisions(Collection $rows): Collection
    {
        return $rows
            ->map(fn (SeriesRanking $row) => $row->meta_json['head_to_head_decision'] ?? null)
            ->filter()
            ->keyBy(function (array $decision): string {
                $players = collect($decision['player_ids'] ?? [])->map(fn ($id) => (int) $id)->sort()->implode(':');

                return ($decision['ranking_list_id'] ?? 0).':'.$players;
            });
    }
}
