<?php

namespace Tests\Unit;

use App\Services\Performance\{AllAgeRatingModel, PlayerAbilitySnapshotStore};
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class AllAgeRatingSnapshotValidationTest extends TestCase
{
    private function graph(): array
    {
        return (new AllAgeRatingModel)->fit([
            ['source_id' => 'match:1', 'winner' => 1, 'loser' => 2, 'event_id' => 1, 'event_name' => 'Match', 'cohort' => 'u13 boys', 'category' => 'U13 boys', 'ball_type' => 'yellow', 'confidence_date' => '2026-10-01', 'confidence_date_basis' => 'scheduled match date'],
            ['source_id' => 'match:2', 'winner' => 10, 'loser' => 11, 'event_id' => 1, 'event_name' => 'Match', 'cohort' => 'u18 boys', 'category' => 'U18 boys', 'ball_type' => 'yellow', 'confidence_date' => '2026-10-01', 'confidence_date_basis' => 'scheduled match date'],
        ], CarbonImmutable::parse('2026-10-02'));
    }

    public function test_valid_shared_graph_and_empty_graph_are_accepted(): void
    {
        $method = new \ReflectionMethod(PlayerAbilitySnapshotStore::class, 'assertAllAgeGraph');
        $store = new PlayerAbilitySnapshotStore;
        $this->assertNull($method->invoke($store, $this->graph()));
        $this->assertNull($method->invoke($store, ['ratings' => [], 'players' => [], 'components' => [], 'reference_component' => null]));
    }

    public function test_corrupt_shared_graph_is_rejected_before_any_database_replacement(): void
    {
        $graph = $this->graph();
        $reference = $graph['reference_component'];
        $mutations = [
            fn (&$g) => $g['policy']['regularization'] = 0.25,
            fn (&$g) => $g['policy']['regularization'] = 0.5,
            fn (&$g) => $g['policy']['version'] = AllAgeRatingModel::VERSION - 1,
            fn (&$g) => $g['reference_component'] = 'missing',
            fn (&$g) => $g['components'][$reference]['converged'] = false,
            fn (&$g) => $g['ratings'][1]['score'] = INF,
            fn (&$g) => $g['ratings'][1]['score'] = 101,
            fn (&$g) => $g['ratings'][1]['strength'] = NAN,
            fn (&$g) => $g['ratings'][1]['component'] = 'missing',
            fn (&$g) => $g['ratings'][1]['comparison_group'] = 'missing',
            fn (&$g) => $g['ratings'][1]['comparable'] = false,
            fn (&$g) => $g['ratings'][10]['score'] = 60,
            fn (&$g) => $g['components'][$reference]['members'][] = 1,
            fn (&$g) => $g['players'][1] = null,
            fn (&$g) => $g['ratings'][99] = $g['ratings'][1],
        ];
        foreach ($mutations as $mutation) {
            $broken = $graph;
            $mutation($broken);
            try {
                (new PlayerAbilitySnapshotStore)->replace(['cohorts' => [], 'all_age' => $broken], CarbonImmutable::parse('2026-10-02'), 'test', []);
                $this->fail('Corrupt shared projection was accepted.');
            } catch (\UnexpectedValueException) { $this->addToAssertionCount(1); }
        }
    }
}
