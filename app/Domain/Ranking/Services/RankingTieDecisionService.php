<?php

namespace App\Domain\Ranking\Services;

use App\Domain\Ranking\Enums\RankingStatus;
use App\Models\Series;
use App\Models\SeriesRanking;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class RankingTieDecisionService
{
    public const REASONS = [
        'head_to_head',
        'third_event_score',
        'previous_ranking',
        'shared_position',
        'other',
    ];

    public function __construct(
        private readonly RankingHeadToHeadConfirmationService $headToHeadConfirmations,
    ) {}

    /** @return array<string, mixed> */
    public function confirm(
        Series $series,
        string $tieKey,
        array $orderedPlayerIds,
        string $reason,
        ?string $note,
        User $actor,
    ): array {
        return DB::transaction(function () use ($series, $tieKey, $orderedPlayerIds, $reason, $note, $actor): array {
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
                    (string) ($row->meta_json['tie_decision']['tie_key'] ?? '') === $tieKey
                )
                ->values();
            if ($rows->count() < 2) {
                throw new \RuntimeException('That tie does not belong to the current calculated ranking run.');
            }

            $decision = $rows->first()->meta_json['tie_decision'] ?? [];
            $expectedPlayers = $rows->pluck('player_id')->map(fn ($id) => (int) $id)->sort()->values();
            $storedPlayers = collect($decision['player_ids'] ?? [])->map(fn ($id) => (int) $id)->sort()->values();
            $submittedOrder = collect($orderedPlayerIds)->map(fn ($id) => (int) $id)->values();
            if ($storedPlayers->all() !== $expectedPlayers->all()
                || $submittedOrder->count() !== $expectedPlayers->count()
                || $submittedOrder->unique()->count() !== $expectedPlayers->count()
                || $submittedOrder->sort()->values()->all() !== $expectedPlayers->all()) {
                throw new \RuntimeException('The submitted player order must contain every tied player exactly once.');
            }
            if (! in_array($reason, self::REASONS, true)) {
                throw new \RuntimeException('Select a valid reason for the tie decision.');
            }

            $note = trim((string) $note);
            if ($reason === 'other' && $note === '') {
                throw new \RuntimeException('A custom note is required when Other is selected.');
            }
            $overridesAutomaticMethod = ($decision['suggested_method'] ?? null) === 'third_event_score'
                && $reason !== 'third_event_score';
            if ($overridesAutomaticMethod && $note === '') {
                throw new \RuntimeException('Explain why the automatic third-event score tie-break is being changed.');
            }

            $wasConfirmed = ! empty($decision['confirmed_at']);
            $previousDecision = $wasConfirmed ? $decision : null;
            if ($wasConfirmed) {
                $sameDecision = ($decision['confirmed_order'] ?? []) === $submittedOrder->all()
                    && ($decision['reason'] ?? null) === $reason
                    && trim((string) ($decision['note'] ?? '')) === $note;
                if ($sameDecision) {
                    return $decision;
                }

                $hasAuditRecord = DB::table('ranking_tie_decisions')
                    ->where('series_id', $series->id)
                    ->where('run_id', $runId)
                    ->where('ranking_list_id', (int) ($decision['ranking_list_id'] ?? 0))
                    ->where('tie_key', $tieKey)
                    ->exists();
                if (! $hasAuditRecord) {
                    throw new \RuntimeException('The existing tie decision audit record is missing. Rebuild the ranking before changing it.');
                }
            }

            $headToHead = $decision['head_to_head_decision'] ?? null;
            if ($reason === 'head_to_head') {
                if ($rows->count() !== 2 || ! $headToHead || empty($headToHead['fixture_id'])) {
                    throw new \RuntimeException('A qualifying two-player head-to-head is not available for this tie.');
                }
                if ((int) $submittedOrder->first() !== (int) ($headToHead['winner_player_id'] ?? 0)) {
                    throw new \RuntimeException('The qualifying head-to-head winner must be first in the confirmed order.');
                }
                $headToHead = $this->headToHeadConfirmations->confirm(
                    $series,
                    (int) $headToHead['fixture_id'],
                    $actor,
                );
            }
            if ($reason === 'third_event_score') {
                if (($decision['suggested_method'] ?? null) !== 'third_event_score'
                    || ($decision['suggested_order'] ?? []) !== $submittedOrder->all()) {
                    throw new \RuntimeException('The selected order does not match the calculated third-event score evidence.');
                }
            }

            $confirmedAt = now();
            $confirmedDecision = array_merge($decision, [
                'confirmed_order' => $submittedOrder->all(),
                'reason' => $reason,
                'note' => $note !== '' ? $note : null,
                'head_to_head_decision' => $headToHead,
                'confirmed_by' => (int) $actor->id,
                'confirmed_at' => $confirmedAt->toIso8601String(),
            ]);
            $startRank = (int) $rows->min('rank_position');
            $displayNote = $this->displayNote($reason, $note, $decision['suggested_method'] ?? null);

            foreach ($rows as $row) {
                $freshMeta = $row->fresh()->meta_json;
                $freshMeta['tie_decision'] = $confirmedDecision;
                if ($headToHead) {
                    $freshMeta['head_to_head_decision'] = $headToHead;
                }
                $notes = collect($freshMeta['tiebreak_notes'] ?? [])
                    ->reject(fn ($existing) => str_starts_with((string) $existing, 'Tie broken by ')
                        || str_starts_with((string) $existing, 'Tie-break manually changed from '))
                    ->reject(fn ($existing) => $overridesAutomaticMethod
                        && str_contains((string) $existing, 'compared by third-event score'))
                    ->values()
                    ->push($displayNote)
                    ->all();
                $freshMeta['tiebreak_notes'] = $notes;
                $rank = $reason === 'shared_position'
                    ? $startRank
                    : $startRank + $submittedOrder->search((int) $row->player_id);
                $row->forceFill(['rank_position' => $rank, 'meta_json' => $freshMeta])->save();
            }

            $auditIdentity = [
                'series_id' => (int) $series->id,
                'run_id' => $runId,
                'ranking_list_id' => (int) $decision['ranking_list_id'],
                'tie_key' => $tieKey,
            ];
            $auditValues = [
                'total_points' => (int) $decision['total_points'],
                'player_ids' => json_encode($expectedPlayers->all(), JSON_THROW_ON_ERROR),
                'ordered_player_ids' => json_encode($submittedOrder->all(), JSON_THROW_ON_ERROR),
                'reason' => $reason,
                'note' => $note !== '' ? $note : null,
                'fixture_id' => $headToHead['fixture_id'] ?? null,
                'confirmed_by' => (int) $actor->id,
                'confirmed_at' => $confirmedAt,
                'decision_snapshot' => json_encode($confirmedDecision, JSON_THROW_ON_ERROR),
                'updated_at' => $confirmedAt,
            ];
            if (! $wasConfirmed) {
                $auditValues['created_at'] = $confirmedAt;
            }

            DB::table('ranking_tie_decisions')->updateOrInsert(
                $auditIdentity,
                $auditValues,
            );

            activity('ranking')
                ->performedOn($series)
                ->causedBy($actor)
                ->withProperties([
                    'run_id' => $runId,
                    'ranking_list_id' => (int) $decision['ranking_list_id'],
                    'tie_key' => $tieKey,
                    'total_points' => (int) $decision['total_points'],
                    'player_ids' => $expectedPlayers->all(),
                    'ordered_player_ids' => $submittedOrder->all(),
                    'reason' => $reason,
                    'note' => $note !== '' ? $note : null,
                    'fixture_id' => $headToHead['fixture_id'] ?? null,
                    'previous_decision' => $previousDecision,
                ])
                ->log($wasConfirmed ? 'Ranking tie decision updated' : 'Ranking tie decision confirmed');

            return $confirmedDecision;
        });
    }

    public function assertAllConfirmed(Series $series, string $runId): void
    {
        $rows = SeriesRanking::where('series_id', $series->id)
            ->where('run_id', $runId)
            ->where('status', RankingStatus::Calculated->value)
            ->get();

        $tieGroups = $rows
            ->filter(fn (SeriesRanking $row) => ! empty($row->meta_json['tie_decision']['tie_key']))
            ->groupBy(fn (SeriesRanking $row) => (string) $row->meta_json['tie_decision']['tie_key'])
            ->filter(function (Collection $group): bool {
                $decision = $group->first()->meta_json['tie_decision'] ?? [];

                return ($decision['suggested_method'] ?? null) !== 'third_event_score'
                    || $group->pluck('rank_position')->unique()->count() < $group->count();
            });

        $pending = 0;
        foreach ($tieGroups as $group) {
            $decision = $group->map(fn (SeriesRanking $row) => $row->meta_json['tie_decision'] ?? null)->filter()->first();

            $players = $group->pluck('player_id')->map(fn ($id) => (int) $id)->sort()->values();
            if ($players->all() !== collect($decision['player_ids'] ?? [])->map(fn ($id) => (int) $id)->sort()->values()->all()) {
                throw new \RuntimeException('A stored tie decision does not match the current calculated ranking. Rebuild it before review.');
            }
            if (empty($decision['confirmed_at'])) {
                $pending++;
                continue;
            }

            $exists = DB::table('ranking_tie_decisions')
                ->where('series_id', $series->id)
                ->where('run_id', $runId)
                ->where('ranking_list_id', (int) ($decision['ranking_list_id'] ?? 0))
                ->where('tie_key', (string) ($decision['tie_key'] ?? ''))
                ->where('confirmed_by', (int) ($decision['confirmed_by'] ?? 0))
                ->exists();
            if (! $exists) {
                throw new \RuntimeException('A tie confirmation audit record is missing or does not match the ranking run.');
            }
        }

        $rowsWithoutDecision = $rows->filter(
            fn (SeriesRanking $row) => empty($row->meta_json['tie_decision']['tie_key'])
        );
        if ($rowsWithoutDecision->contains(
            fn (SeriesRanking $row) => ! empty($row->meta_json['head_to_head_decision'])
        )) {
            $this->headToHeadConfirmations->assertAllConfirmed($series, $runId);
        }

        $unresolvedLegacyGroups = $rowsWithoutDecision
            ->groupBy(fn (SeriesRanking $row) => implode(':', [
                $row->ranking_list_id,
                $row->total_points,
                $row->rank_position,
            ]))
            ->filter(fn (Collection $group) => $group->count() > 1);
        if ($unresolvedLegacyGroups->isNotEmpty()) {
            throw new \RuntimeException('This calculated ranking contains a tie without a decision record. Rebuild it before review.');
        }

        if ($pending > 0) {
            throw new \RuntimeException(
                "Confirm every ranking tie before marking this ranking reviewed. {$pending} decision(s) remain."
            );
        }
    }

    private function displayNote(string $reason, string $note, ?string $suggestedMethod): string
    {
        $label = match ($reason) {
            'head_to_head' => 'qualifying head-to-head',
            'third_event_score' => 'third-event score',
            'previous_ranking' => 'previous published ranking',
            'shared_position' => 'administrator-confirmed shared position',
            default => 'administrator decision',
        };

        $prefix = $suggestedMethod === 'third_event_score' && $reason !== 'third_event_score'
            ? 'Tie-break manually changed from third-event score to '.$label.' — administrator confirmed.'
            : 'Tie broken by '.$label.' — administrator confirmed.';

        return $prefix
            .($note !== '' ? ' '.$note : '');
    }
}
