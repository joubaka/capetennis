<?php

namespace App\Domain\Ranking\Services;

use App\Domain\Ranking\Enums\RankingStatus;
use App\Models\Series;
use App\Models\SeriesRanking;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class WilsonU9IdentitySnapshotService
{
    public const CONFIRMATION = 'CLONE-WILSON-18-SWAP-DIRKIE-5332';

    public const LEGACY_TIE_CONFIRMATION = 'REPAIR-DIRKIE-5332-LEGACY-TIES';

    private const SERIES_ID = 18;
    private const LIST_ID = 938;
    private const CATEGORY_ID = 131;
    private const SOURCE_PLAYER_ID = 2439;
    private const TARGET_PLAYER_ID = 5332;

    public function replaceCalculatedRun(User $actor, string $confirmation): string
    {
        if (! $actor->hasRole('super-user')) {
            throw new AuthorizationException('Only a super-user may perform this correction.');
        }
        if (! hash_equals(self::CONFIRMATION, $confirmation)) {
            throw new RuntimeException('Confirmation token mismatch.');
        }

        return DB::transaction(function () use ($actor): string {
            $series = Series::query()->whereKey(self::SERIES_ID)->lockForUpdate()->firstOrFail();
            if (DB::table('ranking_audit_logs')
                ->where('series_id', self::SERIES_ID)
                ->where('action', 'clone_published_snapshot_for_player_identity_correction')
                ->lockForUpdate()
                ->exists()) {
                throw new RuntimeException('The guarded Dirkie ranking snapshot correction has already been created.');
            }
            $this->assertRegistrationAndInvitationBoundaries();

            $publishedRunIds = SeriesRanking::query()
                ->where('series_id', self::SERIES_ID)
                ->where('status', RankingStatus::Published->value)
                ->whereNotNull('run_id')
                ->distinct()->lockForUpdate()->pluck('run_id');
            if ($publishedRunIds->count() !== 1) {
                throw new RuntimeException('Expected exactly one active published Wilson Series ranking run.');
            }
            $sourceRunId = (string) $publishedRunIds->first();
            $publishedRows = SeriesRanking::query()
                ->where('series_id', self::SERIES_ID)
                ->where('status', RankingStatus::Published->value)
                ->where('run_id', $sourceRunId)
                ->orderBy('id')->lockForUpdate()->get();
            if ($publishedRows->isEmpty()) {
                throw new RuntimeException('The active published Wilson Series snapshot is empty.');
            }

            $allSourceRows = $publishedRows->filter(fn (SeriesRanking $row): bool => (int) $row->player_id === self::SOURCE_PLAYER_ID);
            $sourceMatches = $allSourceRows->filter(fn (SeriesRanking $row): bool =>
                (int) $row->ranking_list_id === self::LIST_ID
                && (int) $row->category_id === self::CATEGORY_ID
                && (int) $row->player_id === self::SOURCE_PLAYER_ID
                && (int) $row->rank_position === 8
                && (int) $row->total_points === 80
            );
            if ($sourceMatches->count() !== 1 || $allSourceRows->count() !== 1) {
                throw new RuntimeException('The published Boys U/9 source row no longer matches the audited rank and points.');
            }
            if ($publishedRows->contains(fn (SeriesRanking $row): bool => (int) $row->player_id === self::TARGET_PLAYER_ID)) {
                throw new RuntimeException('The published snapshot already contains the target player.');
            }

            if (SeriesRanking::query()->where('series_id', self::SERIES_ID)
                ->where('status', RankingStatus::Reviewed->value)->exists()) {
                throw new RuntimeException('A reviewed Wilson Series run exists and will not be replaced.');
            }
            $calculatedRunIds = SeriesRanking::query()->where('series_id', self::SERIES_ID)
                ->where('status', RankingStatus::Calculated->value)
                ->whereNotNull('run_id')->distinct()->lockForUpdate()->pluck('run_id');
            if ($calculatedRunIds->count() !== 1) {
                throw new RuntimeException('Expected exactly one current calculated Wilson Series run.');
            }
            $calculatedRowCount = SeriesRanking::query()->where('series_id', self::SERIES_ID)
                ->where('status', RankingStatus::Calculated->value)->count();
            $selectedCalculatedRowCount = SeriesRanking::query()->where('series_id', self::SERIES_ID)
                ->where('status', RankingStatus::Calculated->value)
                ->where('run_id', (string) $calculatedRunIds->first())->count();
            if ($calculatedRowCount !== $selectedCalculatedRowCount) {
                throw new RuntimeException('Calculated Wilson Series rows exist outside the single current run.');
            }
            if (SeriesRanking::query()->where('series_id', self::SERIES_ID)
                ->whereNotIn('status', [RankingStatus::Published->value, RankingStatus::Archived->value, RankingStatus::Calculated->value])
                ->exists()) {
                throw new RuntimeException('Unexpected mutable Wilson Series ranking state exists.');
            }

            $oldCalculatedRunId = (string) $calculatedRunIds->first();
            $newRunId = 'dirkie-identity-'.Str::uuid()->toString();
            $tieKeyMap = $this->buildTieKeyMap($publishedRows);

            $this->deleteRunDecisionArtifacts($oldCalculatedRunId);

            SeriesRanking::query()->where('series_id', self::SERIES_ID)
                ->where('status', RankingStatus::Calculated->value)
                ->where('run_id', $oldCalculatedRunId)->delete();

            foreach ($publishedRows as $row) {
                SeriesRanking::query()->create([
                    'series_id' => self::SERIES_ID,
                    'ranking_list_id' => $row->ranking_list_id,
                    'category_id' => $row->category_id,
                    'player_id' => (int) $row->ranking_list_id === self::LIST_ID
                        && (int) $row->category_id === self::CATEGORY_ID
                        && (int) $row->player_id === self::SOURCE_PLAYER_ID
                        && (int) $row->rank_position === 8
                        && (int) $row->total_points === 80
                            ? self::TARGET_PLAYER_ID : $row->player_id,
                    'rank_position' => $row->rank_position,
                    'total_points' => $row->total_points,
                    'meta_json' => $this->mapIdentity($row->meta_json ?? [], $tieKeyMap),
                    'status' => RankingStatus::Calculated->value,
                    'run_id' => $newRunId,
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'published_by' => null,
                    'published_at' => null,
                ]);
            }

            $this->cloneTieDecisions($sourceRunId, $newRunId, $tieKeyMap);
            $this->cloneHeadToHeadConfirmations($sourceRunId, $newRunId);

            DB::table('ranking_audit_logs')->insert([
                'series_id' => self::SERIES_ID,
                'run_id' => $newRunId,
                'action' => 'clone_published_snapshot_for_player_identity_correction',
                'payload' => json_encode([
                    'source_run_id' => $sourceRunId,
                    'replaced_calculated_run_id' => $oldCalculatedRunId,
                    'new_run_id' => $newRunId,
                    'source_player_id' => self::SOURCE_PLAYER_ID,
                    'target_player_id' => self::TARGET_PLAYER_ID,
                ], JSON_THROW_ON_ERROR),
                'user_id' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            activity('ranking')->performedOn($series)->causedBy($actor)->withProperties([
                'source_run_id' => $sourceRunId,
                'replaced_calculated_run_id' => $oldCalculatedRunId,
                'new_run_id' => $newRunId,
                'source_player_id' => self::SOURCE_PLAYER_ID,
                'target_player_id' => self::TARGET_PLAYER_ID,
            ])->log('Published ranking snapshot cloned for Dirkie identity correction');

            return $newRunId;
        });
    }

    /**
     * Add modern audit evidence to legacy equal-points groups in the already-created
     * one-off calculated clone. Ranking positions and points are never changed.
     */
    public function repairCalculatedLegacyTies(User $actor, string $confirmation): string
    {
        if (! $actor->hasRole('super-user')) {
            throw new AuthorizationException('Only a super-user may perform this correction.');
        }
        if (! hash_equals(self::LEGACY_TIE_CONFIRMATION, $confirmation)) {
            throw new RuntimeException('Confirmation token mismatch.');
        }

        return DB::transaction(function () use ($actor): string {
            $series = Series::query()->whereKey(self::SERIES_ID)->lockForUpdate()->firstOrFail();
            $runIds = SeriesRanking::query()->where('series_id', self::SERIES_ID)
                ->where('status', RankingStatus::Calculated->value)->whereNotNull('run_id')
                ->distinct()->lockForUpdate()->pluck('run_id');
            if ($runIds->count() !== 1) {
                throw new RuntimeException('Expected exactly one current calculated Wilson Series run.');
            }
            $runId = (string) $runIds->first();
            if (! str_starts_with($runId, 'dirkie-identity-')) {
                throw new RuntimeException('The calculated run is not the guarded Dirkie identity clone.');
            }
            $cloneAudit = DB::table('ranking_audit_logs')->where('series_id', self::SERIES_ID)
                ->where('run_id', $runId)->where('action', 'clone_published_snapshot_for_player_identity_correction')
                ->lockForUpdate()->first();
            if (! $cloneAudit) {
                throw new RuntimeException('The calculated run is missing its identity-correction audit record.');
            }
            $this->assertRegistrationAndInvitationBoundaries();
            if (! SeriesRanking::query()->where('series_id', self::SERIES_ID)->where('run_id', $runId)
                ->where('ranking_list_id', self::LIST_ID)->where('category_id', self::CATEGORY_ID)
                ->where('player_id', self::TARGET_PLAYER_ID)->where('rank_position', 8)->where('total_points', 80)->exists()
                || SeriesRanking::query()->where('series_id', self::SERIES_ID)->where('run_id', $runId)
                    ->where('player_id', self::SOURCE_PLAYER_ID)->exists()) {
                throw new RuntimeException('The calculated Dirkie identity row no longer matches the audited clone.');
            }

            $repairAudit = DB::table('ranking_audit_logs')->where('series_id', self::SERIES_ID)
                ->where('run_id', $runId)->where('action', 'confirm_legacy_ties_from_published_order')->lockForUpdate()->first();
            if ($repairAudit) {
                app(RankingTieDecisionService::class)->assertAllConfirmed($series, $runId);
                return $runId;
            }

            $rows = SeriesRanking::query()->where('series_id', self::SERIES_ID)->where('run_id', $runId)
                ->where('status', RankingStatus::Calculated->value)->orderBy('ranking_list_id')
                ->orderBy('rank_position')->orderBy('player_id')->lockForUpdate()->get();
            $legacyGroups = $rows->groupBy(fn (SeriesRanking $row): string => $row->ranking_list_id.':'.$row->total_points)
                ->filter(fn ($group): bool => $group->count() > 1)
                ->filter(function ($group): bool {
                    $withDecision = $group->filter(fn (SeriesRanking $row): bool => ! empty($row->meta_json['tie_decision']['tie_key']));
                    if ($withDecision->isNotEmpty() && $withDecision->count() !== $group->count()) {
                        throw new RuntimeException('An equal-points group mixes modern and legacy tie evidence.');
                    }
                    return $withDecision->isEmpty();
                });

            $created = 0;
            foreach ($legacyGroups as $group) {
                $ordered = $group->sortBy(fn (SeriesRanking $row): string => sprintf('%010d:%020d', $row->rank_position, $row->player_id))->values();
                $ranks = $ordered->pluck('rank_position')->map(fn ($rank): int => (int) $rank);
                $reason = $ranks->unique()->count() === 1
                    ? 'shared_position'
                    : ($ranks->unique()->count() === $ordered->count() ? 'other' : null);
                if ($reason === null) {
                    throw new RuntimeException('A legacy tie group has a mixed shared/sequential position pattern.');
                }
                if ($reason !== 'shared_position') {
                    $expectedRanks = range((int) $ranks->min(), (int) $ranks->max());
                    if ($ranks->sort()->values()->all() !== $expectedRanks
                        || $rows->filter(fn (SeriesRanking $candidate): bool =>
                            (int) $candidate->ranking_list_id === (int) $ordered->first()->ranking_list_id
                            && (int) $candidate->total_points !== (int) $ordered->first()->total_points
                            && (int) $candidate->rank_position >= (int) $ranks->min()
                            && (int) $candidate->rank_position <= (int) $ranks->max()
                        )->isNotEmpty()) {
                        throw new RuntimeException('A legacy sequential tie group is noncontiguous or interleaved with another ranking row.');
                    }
                } elseif ($rows->filter(fn (SeriesRanking $candidate): bool =>
                    (int) $candidate->ranking_list_id === (int) $ordered->first()->ranking_list_id
                    && (int) $candidate->total_points !== (int) $ordered->first()->total_points
                    && (int) $candidate->rank_position === (int) $ranks->first()
                )->isNotEmpty()) {
                    throw new RuntimeException('A legacy shared-position group overlaps a different-points row at the same rank.');
                }
                $thirdEventEvidence = [];
                $mentionsThirdEvent = [];
                $previousRankingEvidence = [];
                foreach ($ordered as $row) {
                    $meta = $row->meta_json ?? [];
                    $notes = collect($meta['tiebreak_notes'] ?? [])->map(fn ($note): string => strtolower((string) $note));
                    if (! empty($meta['head_to_head_decision'])
                        || $notes->contains(fn (string $note): bool => str_contains($note, 'head-to-head') || str_contains($note, 'head to head'))) {
                        throw new RuntimeException('A legacy tie group contains head-to-head evidence that cannot be inferred safely.');
                    }
                    $thirdEventEvidence[] = $notes->contains(fn (string $note): bool =>
                        preg_match('/^tied on \d+ points; compared by third-event score \(\d+ points\)\.$/i', trim($note)) === 1
                    );
                    $mentionsThirdEvent[] = $notes->contains(fn (string $note): bool => str_contains($note, 'third-event') || str_contains($note, 'third event'));
                    $previousRankingEvidence[] = $notes->contains(fn (string $note): bool => str_contains($note, 'previous published ranking') || str_contains($note, 'previous ranking'));
                }
                $hasThirdEventEvidence = in_array(true, $thirdEventEvidence, true);
                if (in_array(true, $mentionsThirdEvent, true)
                    && ($hasThirdEventEvidence === false || in_array(false, $thirdEventEvidence, true))) {
                    throw new RuntimeException('A legacy tie group mentions third-event scoring without one consistent exact resolving pattern.');
                }
                $hasPreviousRankingEvidence = in_array(true, $previousRankingEvidence, true);
                if ($hasThirdEventEvidence && (in_array(false, $thirdEventEvidence, true) || $reason === 'shared_position')) {
                    throw new RuntimeException('A legacy third-event group does not preserve one consistent sequential published order.');
                }
                if ($hasPreviousRankingEvidence && (in_array(false, $previousRankingEvidence, true) || $reason === 'shared_position')) {
                    throw new RuntimeException('A legacy previous-ranking group does not preserve one consistent sequential published order.');
                }
                if ($hasThirdEventEvidence && $hasPreviousRankingEvidence) {
                    throw new RuntimeException('A legacy tie group contains conflicting decision provenance.');
                }

                $playerIds = $ordered->pluck('player_id')->map(fn ($id): int => (int) $id);
                if ($playerIds->duplicates()->isNotEmpty()) {
                    throw new RuntimeException('A legacy tie group contains duplicate player identities.');
                }
                $sortedPlayerIds = $playerIds->sort()->values();
                $rankingListId = (int) $ordered->first()->ranking_list_id;
                $points = (int) $ordered->first()->total_points;
                $tieKey = hash('sha256', implode(':', [$rankingListId, $points, $sortedPlayerIds->implode(',')]));
                if (DB::table('ranking_tie_decisions')->where('series_id', self::SERIES_ID)
                    ->where('run_id', $runId)->where('tie_key', $tieKey)->exists()) {
                    throw new RuntimeException('A legacy tie decision key already exists without matching row metadata.');
                }
                $confirmedReason = $hasThirdEventEvidence
                    ? 'third_event_score'
                    : ($hasPreviousRankingEvidence ? 'previous_ranking' : $reason);
                $decisionNote = $confirmedReason === 'other'
                    ? 'Order preserved from the previously published snapshot during audited player identity correction'
                    : 'Preserved exactly from the previously published ranking snapshot during the audited Dirkie identity correction.';
                $decision = [
                    'tie_key' => $tieKey,
                    'ranking_list_id' => $rankingListId,
                    'total_points' => $points,
                    'player_ids' => $sortedPlayerIds->all(),
                    'suggested_method' => $hasThirdEventEvidence
                        ? 'third_event_score'
                        : ($hasPreviousRankingEvidence ? 'previous_ranking' : null),
                    'suggested_order' => $playerIds->all(),
                    'confirmed_order' => $playerIds->all(),
                    'reason' => $confirmedReason,
                    'note' => $decisionNote,
                    'confirmed_by' => (int) $actor->id,
                    'confirmed_at' => now()->toIso8601String(),
                ];
                foreach ($ordered as $row) {
                    $meta = $row->meta_json ?? [];
                    $meta['tie_decision'] = $decision;
                    $row->forceFill(['meta_json' => $meta])->save();
                }
                DB::table('ranking_tie_decisions')->insert([
                    'series_id' => self::SERIES_ID, 'run_id' => $runId, 'ranking_list_id' => $rankingListId,
                    'tie_key' => $tieKey, 'total_points' => $points,
                    'player_ids' => json_encode($sortedPlayerIds->all(), JSON_THROW_ON_ERROR),
                    'ordered_player_ids' => json_encode($playerIds->all(), JSON_THROW_ON_ERROR),
                    'reason' => $confirmedReason, 'note' => $decision['note'], 'fixture_id' => null,
                    'confirmed_by' => $actor->id, 'confirmed_at' => now(),
                    'decision_snapshot' => json_encode($decision, JSON_THROW_ON_ERROR),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
                $created++;
            }

            app(RankingTieDecisionService::class)->assertAllConfirmed($series, $runId);
            DB::table('ranking_audit_logs')->insert([
                'series_id' => self::SERIES_ID, 'run_id' => $runId,
                'action' => 'confirm_legacy_ties_from_published_order',
                'payload' => json_encode(['run_id' => $runId, 'groups_confirmed' => $created], JSON_THROW_ON_ERROR),
                'user_id' => $actor->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            activity('ranking')->performedOn($series)->causedBy($actor)->withProperties([
                'run_id' => $runId, 'groups_confirmed' => $created,
            ])->log('Legacy ranking ties confirmed from published order');

            return $runId;
        });
    }

    private function assertRegistrationAndInvitationBoundaries(): void
    {
        $source = DB::table('players')->where('id', self::SOURCE_PLAYER_ID)->lockForUpdate()->first();
        $target = DB::table('players')->where('id', self::TARGET_PLAYER_ID)->lockForUpdate()->first();
        if (! $source || strcasecmp(trim((string) $source->name), 'Dirk') !== 0
            || strcasecmp(trim((string) $source->surname), 'Coetzee') !== 0
            || (string) $source->dateOfBirth !== '2010-03-25' || (int) $source->userId !== 2340
            || ! $target || strcasecmp(trim((string) $target->name), 'Dirkie') !== 0
            || strcasecmp(trim((string) $target->surname), 'Coetzee') !== 0
            || (string) $target->dateOfBirth !== '2017-09-09' || (int) $target->userId !== 4025) {
            throw new RuntimeException('The audited Dirk and Dirkie player identities no longer match.');
        }
        foreach ([18445, 20410] as $registrationId) {
            $players = DB::table('player_registrations')->where('registration_id', $registrationId)
                ->lockForUpdate()->pluck('player_id')->map(fn ($id): int => (int) $id);
            if ($players->count() !== 1 || $players->first() !== self::TARGET_PLAYER_ID) {
                throw new RuntimeException("Registration {$registrationId} is not exclusively linked to Dirkie 5332.");
            }
        }
        foreach ([18445 => 9435, 20410 => 11283] as $registrationId => $itemId) {
            $items = DB::table('registration_order_items')->where('registration_id', $registrationId)->lockForUpdate()->get();
            if ($items->count() !== 1 || (int) $items->first()->id !== $itemId || (int) $items->first()->player_id !== self::TARGET_PLAYER_ID) {
                throw new RuntimeException("Registration order item {$itemId} is not exclusively linked to Dirkie 5332.");
            }
        }
        $invitation = DB::table('masters_invitations')->where('id', 807)->lockForUpdate()->first();
        if (! $invitation || (int) $invitation->player_id !== self::SOURCE_PLAYER_ID
            || (string) $invitation->status !== 'declined'
            || $invitation->registration_id !== null || $invitation->order_id !== null) {
            throw new RuntimeException('Masters invitation 807 no longer matches the preserved declined record.');
        }
    }

    /** @return array<string, string> */
    private function buildTieKeyMap($rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $decision = $row->meta_json['tie_decision'] ?? null;
            if (! is_array($decision) || empty($decision['tie_key']) || ! in_array(self::SOURCE_PLAYER_ID, array_map('intval', $decision['player_ids'] ?? []), true)) {
                continue;
            }
            $players = collect($decision['player_ids'])->map(fn ($id): int => (int) $id === self::SOURCE_PLAYER_ID ? self::TARGET_PLAYER_ID : (int) $id)->sort()->values();
            if ($players->duplicates()->isNotEmpty()) {
                throw new RuntimeException('The identity swap would collapse a tie decision onto one player.');
            }
            $newTieKey = hash('sha256', implode(':', [
                (int) ($decision['ranking_list_id'] ?? $row->ranking_list_id),
                (int) ($decision['total_points'] ?? $row->total_points),
                $players->implode(','),
            ]));
            $oldTieKey = (string) $decision['tie_key'];
            if (isset($map[$oldTieKey]) && $map[$oldTieKey] !== $newTieKey) {
                throw new RuntimeException('The published snapshot contains inconsistent tie-decision identity evidence.');
            }
            $map[$oldTieKey] = $newTieKey;
        }
        return $map;
    }

    private function mapIdentity(mixed $value, array $tieKeyMap): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $item) {
            if ($key === 'tie_key' && is_string($item) && isset($tieKeyMap[$item])) {
                $value[$key] = $tieKeyMap[$item];
            } elseif ((str_ends_with((string) $key, 'player_id') || in_array((string) $key, ['player_ids', 'confirmed_order', 'suggested_order', 'ordered_player_ids'], true))) {
                $value[$key] = $this->replacePlayerId($item);
            } else {
                $value[$key] = $this->mapIdentity($item, $tieKeyMap);
            }
        }
        return $value;
    }

    private function replacePlayerId(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->replacePlayerId($item), $value);
        }
        return is_numeric($value) && (int) $value === self::SOURCE_PLAYER_ID ? self::TARGET_PLAYER_ID : $value;
    }

    private function cloneTieDecisions(string $sourceRunId, string $newRunId, array $tieKeyMap): void
    {
        $decisions = DB::table('ranking_tie_decisions')->where('series_id', self::SERIES_ID)->where('run_id', $sourceRunId)->lockForUpdate()->get();
        $oldKeys = $decisions->pluck('tie_key')->map(fn ($key): string => (string) $key);
        if ($oldKeys->duplicates()->isNotEmpty() || collect($tieKeyMap)->duplicates()->isNotEmpty()
            || collect($tieKeyMap)->values()->intersect($oldKeys->diff(array_keys($tieKeyMap)))->isNotEmpty()) {
            throw new RuntimeException('The mapped tie-decision key would collide with another published decision.');
        }
        $mappedAuditKeys = [];
        foreach ($decisions as $decision) {
            $oldTieKey = (string) $decision->tie_key;
            $players = collect(json_decode((string) $decision->player_ids, true, 512, JSON_THROW_ON_ERROR))->map(fn ($id): int => (int) $id)->sort()->values();
            $expectedOldKey = hash('sha256', implode(':', [(int) $decision->ranking_list_id, (int) $decision->total_points, $players->implode(',')]));
            if ($oldTieKey !== $expectedOldKey || $players->duplicates()->isNotEmpty()) {
                throw new RuntimeException('A published tie audit has inconsistent list, points, players, or key evidence.');
            }
            $metaRows = SeriesRanking::query()->where('series_id', self::SERIES_ID)->where('run_id', $sourceRunId)
                ->where('status', RankingStatus::Published->value)->where('ranking_list_id', $decision->ranking_list_id)
                ->where('total_points', $decision->total_points)->get()
                ->filter(fn (SeriesRanking $row): bool => (string) ($row->meta_json['tie_decision']['tie_key'] ?? '') === $oldTieKey);
            if ($metaRows->pluck('player_id')->map(fn ($id): int => (int) $id)->sort()->values()->all() !== $players->all()) {
                throw new RuntimeException('A published tie audit does not match its ranking metadata.');
            }
            if ($players->contains(self::SOURCE_PLAYER_ID)) {
                if (! isset($tieKeyMap[$oldTieKey])) {
                    throw new RuntimeException('A source-containing tie audit was not mapped.');
                }
                $mappedAuditKeys[] = $oldTieKey;
            }
            $values = (array) $decision;
            unset($values['id']);
            $values['run_id'] = $newRunId;
            $values['tie_key'] = $tieKeyMap[$oldTieKey] ?? $oldTieKey;
            foreach (['player_ids', 'ordered_player_ids', 'decision_snapshot'] as $column) {
                $decoded = json_decode((string) $values[$column], true, 512, JSON_THROW_ON_ERROR);
                $values[$column] = json_encode($this->mapIdentity($decoded, $tieKeyMap), JSON_THROW_ON_ERROR);
            }
            $values['created_at'] = now();
            $values['updated_at'] = now();
            DB::table('ranking_tie_decisions')->insert($values);
        }
        if (collect($mappedAuditKeys)->sort()->values()->all() !== collect(array_keys($tieKeyMap))->sort()->values()->all()) {
            throw new RuntimeException('Every source-containing tie decision must have exactly one matching audit record.');
        }
    }

    private function cloneHeadToHeadConfirmations(string $sourceRunId, string $newRunId): void
    {
        if (! DB::getSchemaBuilder()->hasTable('ranking_head_to_head_confirmations')) {
            return;
        }
        $confirmations = DB::table('ranking_head_to_head_confirmations')->where('series_id', self::SERIES_ID)->where('run_id', $sourceRunId)->lockForUpdate()->get();
        $metaDecisionKeys = SeriesRanking::query()->where('series_id', self::SERIES_ID)->where('run_id', $sourceRunId)
            ->where('status', RankingStatus::Published->value)->get()
            ->map(fn (SeriesRanking $ranking): ?array => $ranking->meta_json['head_to_head_decision'] ?? null)
            ->filter()->map(function (array $decision): string {
                $players = collect($decision['player_ids'] ?? [])->map(fn ($id): int => (int) $id)->sort()->values();
                return implode(':', [(int) ($decision['ranking_list_id'] ?? 0), (int) ($decision['fixture_id'] ?? 0), $players->implode(','), (int) ($decision['winner_player_id'] ?? 0)]);
            })->unique()->sort()->values();
        $auditDecisionKeys = $confirmations->map(function (object $row): string {
            $players = collect([(int) $row->player1_id, (int) $row->player2_id])->sort()->values();
            return implode(':', [(int) $row->ranking_list_id, (int) $row->fixture_id, $players->implode(','), (int) $row->winner_player_id]);
        })->sort()->values();
        if ($metaDecisionKeys->all() !== $auditDecisionKeys->all()) {
            throw new RuntimeException('Every published head-to-head decision must have exactly one matching audit record.');
        }
        foreach ($confirmations as $row) {
            $players = collect([(int) $row->player1_id, (int) $row->player2_id])->sort()->values();
            $snapshot = json_decode((string) $row->decision_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $snapshotPlayers = collect($snapshot['player_ids'] ?? [])->map(fn ($id): int => (int) $id)->sort()->values();
            $metaRows = SeriesRanking::query()->where('series_id', self::SERIES_ID)->where('run_id', $sourceRunId)
                ->where('status', RankingStatus::Published->value)->where('ranking_list_id', $row->ranking_list_id)->get()
                ->filter(fn (SeriesRanking $ranking): bool => (int) ($ranking->meta_json['head_to_head_decision']['fixture_id'] ?? 0) === (int) $row->fixture_id);
            $meta = $metaRows->first()?->meta_json['head_to_head_decision'] ?? [];
            if ($players->count() !== 2 || $players->duplicates()->isNotEmpty() || $snapshotPlayers->all() !== $players->all()
                || ! in_array((int) $row->winner_player_id, $players->all(), true)
                || $metaRows->pluck('player_id')->map(fn ($id): int => (int) $id)->sort()->values()->all() !== $players->all()
                || (int) ($meta['ranking_list_id'] ?? 0) !== (int) $row->ranking_list_id
                || (int) ($meta['fixture_id'] ?? 0) !== (int) $row->fixture_id
                || (int) ($meta['winner_player_id'] ?? 0) !== (int) $row->winner_player_id) {
                throw new RuntimeException('A published head-to-head confirmation does not match its ranking metadata.');
            }
            $values = (array) $row;
            unset($values['id']);
            $values['run_id'] = $newRunId;
            foreach (['player1_id', 'player2_id', 'winner_player_id'] as $column) {
                $values[$column] = $this->replacePlayerId($values[$column]);
            }
            if ((int) $values['player1_id'] === (int) $values['player2_id']) {
                throw new RuntimeException('The identity swap would collapse a head-to-head confirmation onto one player.');
            }
            $values['decision_snapshot'] = json_encode($this->mapIdentity($snapshot, []), JSON_THROW_ON_ERROR);
            $values['created_at'] = now();
            $values['updated_at'] = now();
            DB::table('ranking_head_to_head_confirmations')->insert($values);
        }
    }

    private function deleteRunDecisionArtifacts(string $runId): void
    {
        DB::table('ranking_tie_decisions')->where('series_id', self::SERIES_ID)->where('run_id', $runId)->delete();
        if (DB::getSchemaBuilder()->hasTable('ranking_head_to_head_confirmations')) {
            DB::table('ranking_head_to_head_confirmations')->where('series_id', self::SERIES_ID)->where('run_id', $runId)->delete();
        }
    }
}
