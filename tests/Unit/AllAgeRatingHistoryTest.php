<?php

namespace Tests\Unit;

use App\Models\Player;
use App\Services\Performance\{AllAgeRatingModel, PlayerRatingEvidenceBuilder, PlayerRatingHistoryService, PlayerRatingHistoryStore, PlayerSharedAbilityService};
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
        $this->assertSame(75, $explicit['point']['score']);
        $this->assertSame('cohort', $explicit['rating_scope']);
    }

    public function test_one_canonical_match_can_support_both_projections_without_provenance_collision(): void
    {
        $edge = ['source_id' => 'match:1', 'winner' => 1, 'loser' => 2, 'kind' => 'played'];
        $snapshot = ['names' => [1 => 'First', 2 => 'Second'], 'cohorts' => ['u13 boys' => ['history_evidence' => ['matches' => [$edge], 'fields' => []]]],
            'all_age' => ['history_evidence' => ['matches' => [$edge], 'fields' => []]]];
        $sources = [1 => ['matches' => [['source' => 'Individual match', 'fixture_id' => 1, 'event_id' => 1, 'event' => 'Event',
            'confidence_date' => '2026-10-01', 'confidence_date_basis' => 'scheduled match date', 'date' => '2026-10-01',
            'player1_id' => 1, 'player2_id' => 2, 'winner_side' => 1, 'score' => '6-2 6-3']]]];
        $builder = new PlayerRatingEvidenceBuilder;
        $builder->append($snapshot, $sources, 0);
        $builder->append($snapshot, $sources, 1);
        $rows = $builder->all();
        $this->assertCount(2, $rows);
        $this->assertSame(['u13 boys', AllAgeRatingModel::COHORT], array_column($rows, 'cohort'));
        $this->assertSame(['match:1', 'match:1'], array_column($rows, 'source_id'));
        $this->assertSame([3, 3], array_column($rows, 'months'));
        $this->assertSame([0, 0], array_column($rows, 'baseline_months'));
    }
}
