<?php

namespace Tests\Unit;

use App\Models\Player;
use App\Services\Performance\{AllAgeRatingModel, BallRatingModel, PlayerRatingEvidenceBuilder, PlayerRatingHistoryService, PlayerRatingHistoryStore, PlayerSharedAbilityService};
use PHPUnit\Framework\TestCase;

class AllAgeRatingHistoryTest extends TestCase
{
    public function test_unrated_shared_history_never_silently_defaults_to_an_age_relative_score(): void
    {
        $ability = $this->createStub(PlayerSharedAbilityService::class);
        $ability->method('fingerprint')->willReturn('same');
        $store = $this->createStub(PlayerRatingHistoryStore::class);
        $store->method('read')->willReturn(['source_fingerprint' => 'same', 'as_of' => '2026-10-01', 'built_at' => '2026-10-01', 'evidence' => [],
            'months' => [['date' => '2026-10-01', 'cohorts' => ['u13 boys' => [1 => ['score' => 75, 'group' => 'old', 'component' => 'old']], AllAgeRatingModel::COHORT => []]]]]);
        $player = new Player;
        $player->setRawAttributes(['id' => 1]);
        $service = new PlayerRatingHistoryService($ability, $store);
        $shared = $service->forPlayer($player);
        $this->assertSame(AllAgeRatingModel::COHORT, $shared['cohort']);
        $this->assertSame('all_age', $shared['rating_scope']);
        $this->assertTrue($shared['shared_available']);
        $this->assertSame([], $shared['points']);
        $this->assertNull($shared['point']);
        $explicit = $service->forPlayer($player, ['cohort' => 'u13 boys', 'month' => 0]);
        $this->assertNull($explicit['point']);
        $this->assertSame('all_age', $explicit['rating_scope']);
        $this->assertSame([AllAgeRatingModel::COHORT], array_keys($explicit['series']));
    }

    public function test_one_canonical_match_can_support_both_projections_without_provenance_collision(): void
    {
        $edge = ['source_id' => 'match:1', 'winner' => 1, 'loser' => 2, 'kind' => 'played'];
        $snapshot = ['names' => [1 => 'First', 2 => 'Second'], 'cohorts' => ['u13 boys' => ['history_evidence' => ['matches' => [$edge], 'fields' => []]]],
            'all_age' => ['history_evidence' => ['matches' => [$edge], 'fields' => []]],
            'ball_ratings' => ['standard' => ['history_evidence' => ['matches' => [$edge], 'fields' => []]]]];
        $sources = [1 => ['matches' => [['source' => 'Individual match', 'fixture_id' => 1, 'event_id' => 1, 'event' => 'Event',
            'confidence_date' => '2026-10-01', 'confidence_date_basis' => 'scheduled match date', 'date' => '2026-10-01',
            'player1_id' => 1, 'player2_id' => 2, 'winner_side' => 1, 'score' => '6-2 6-3']]]];
        $builder = new PlayerRatingEvidenceBuilder;
        $builder->append($snapshot, $sources, 0);
        $builder->append($snapshot, $sources, 1);
        $rows = $builder->all();
        $this->assertCount(2, $rows);
        $this->assertSame([AllAgeRatingModel::COHORT, BallRatingModel::COHORTS['standard']], array_column($rows, 'cohort'));
        $this->assertSame(['match:1', 'match:1'], array_column($rows, 'source_id'));
        $this->assertSame([3, 3], array_column($rows, 'months'));
        $this->assertSame([0, 0], array_column($rows, 'baseline_months'));
    }

    public function test_legacy_only_projections_remain_available_but_empty_modern_graphs_never_fall_back(): void
    {
        $legacy = ['u13 boys' => ['history_evidence' => ['matches' => [], 'fields' => []]]];
        $this->assertSame($legacy, PlayerRatingEvidenceBuilder::projections(['cohorts' => $legacy]));
        $this->assertSame([], PlayerRatingEvidenceBuilder::projections(['cohorts' => $legacy, 'ball_ratings' => []]));
        $this->assertSame([AllAgeRatingModel::COHORT => []], PlayerRatingEvidenceBuilder::projections(['cohorts' => $legacy, 'all_age' => []]));
        $this->assertSame([BallRatingModel::COHORTS['orange'] => []], PlayerRatingEvidenceBuilder::projections(['cohorts' => $legacy, 'ball_ratings' => ['orange' => []]]));
    }

    public function test_ball_finishes_retain_their_own_month_masks_without_legacy_finish_duplicates(): void
    {
        $field = ['players' => [1, 2], 'weight' => 0.2, 'source_id' => 'ball-finish:1:1', 'event_id' => 1,
            'event_name' => 'Fictional final', 'event_date' => '2026-10-01', 'completed_at' => '2026-10-02', 'rating_ball_type' => 'orange'];
        $graph = ['ratings' => [1 => ['component' => 'a'], 2 => ['component' => 'a']], 'components' => ['a' => ['members' => [1, 2]]],
            'history_evidence' => ['matches' => [], 'fields' => [$field]]];
        $snapshot = ['names' => [1 => 'First', 2 => 'Second'], 'cohorts' => ['u13 boys' => $graph],
            'all_age' => ['history_evidence' => ['matches' => [], 'fields' => []]], 'ball_ratings' => ['orange' => $graph]];
        $builder = new PlayerRatingEvidenceBuilder;
        $builder->append($snapshot, [], 0);
        $builder->append($snapshot, [], 11);
        $rows = $builder->all();
        $this->assertCount(1, $rows);
        $this->assertSame('finish', $rows[0]['kind']);
        $this->assertSame(BallRatingModel::COHORTS['orange'], $rows[0]['cohort']);
        $this->assertSame('ball-finish:1:1', $rows[0]['source_id']);
        $this->assertSame('orange', $rows[0]['rating_ball_type']);
        $this->assertSame(2049, $rows[0]['months']);
        $this->assertArrayNotHasKey('score', $rows[0]);

        $legacy = new PlayerRatingEvidenceBuilder;
        $legacy->append(['names' => $snapshot['names'], 'cohorts' => $snapshot['cohorts']], [], 0);
        $this->assertSame('u13 boys', $legacy->all()[0]['cohort']);
    }
}
