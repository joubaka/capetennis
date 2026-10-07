<?php

namespace Tests\Unit;

use App\Services\Performance\SharedAbilityModel;
use PHPUnit\Framework\TestCase;

class SharedAbilityModelTest extends TestCase
{
    private function edge(int $winner, int $loser, float $weight = 1, string $source = 'match'): array
    {
        return ['winner' => $winner, 'loser' => $loser, 'weight' => $weight, 'kind' => 'played', 'source_id' => $source];
    }

    public function test_global_opponent_strength_changes_two_players_with_identical_local_wins(): void
    {
        $edges = [$this->edge(1,2), $this->edge(4,3)];
        for ($i=0; $i<10; $i++) { $edges[] = $this->edge(2,3,1,'anchor-'.$i); }
        $fit = (new SharedAbilityModel())->fit($edges, 'u10 boys');
        $this->assertGreaterThan($fit['ratings'][4]['score'], $fit['ratings'][1]['score']);
        $this->assertSame($fit['ratings'][1]['component'], $fit['ratings'][4]['component']);
    }

    public function test_deterministic_finite_fit_with_no_losses_and_disconnected_components(): void
    {
        $edges = [$this->edge(1,2),$this->edge(2,3),$this->edge(10,11)];
        $model = new SharedAbilityModel(); $fit = $model->fit($edges, 'u10 boys');
        $this->assertSame($fit, $model->fit(array_reverse($edges), 'u10 boys'));
        $this->assertGreaterThan(50, $fit['ratings'][1]['score']);
        $this->assertLessThan(100, $fit['ratings'][1]['score']);
        $this->assertNotSame($fit['ratings'][1]['component'], $fit['ratings'][10]['component']);
        $this->assertNotSame($fit['ratings'][1]['component'], $model->fit($edges,'u12 boys')['ratings'][1]['component']);
        foreach ($fit['components'] as $component) { $this->assertTrue($component['converged']); }
    }

    public function test_invalid_edges_cannot_create_fake_ratings_or_join_components(): void
    {
        $fit = (new SharedAbilityModel())->fit([$this->edge(1,2),$this->edge(2,3,0),$this->edge(3,4,-1),$this->edge(5,5),$this->edge(6,7,INF),$this->edge(8,9,NAN)]);
        $this->assertSame([1,2], array_keys($fit['ratings']));
        $this->assertSame(1, array_values($fit['components'])[0]['played']);
    }

    public function test_weaker_finish_inferences_do_not_dominate_played_result(): void
    {
        $played = $this->edge(2,1);
        $inferred = $this->edge(1,2,0.2,'finish'); $inferred['kind'] = 'finish_order';
        $fit = (new SharedAbilityModel())->fit([$played,$inferred]);
        $this->assertGreaterThan($fit['ratings'][1]['score'], $fit['ratings'][2]['score']);
        $component = array_values($fit['components'])[0];
        $this->assertSame(1,$component['played']); $this->assertSame(1,$component['inferred']);
    }
    private function ordinal(array $ids, float $weight = 0.2, string $source = 'field'): array
    {
        $edges = [];
        for ($rank = 0; $rank < count($ids) - 1; $rank++) {
            $edges[] = ['winner' => $ids[$rank], 'loser' => $ids[$rank+1], 'weight' => $weight, 'kind' => 'finish_order', 'source_id' => $source.':'.$rank, 'topology_only' => true];
        }
        return [$edges, ['players' => $ids, 'weight' => $weight, 'source_id' => $source]];
    }

    public function test_ordinal_fields_use_every_rank_without_size_dilution_and_are_deterministic(): void
    {
        [$edges, $field] = $this->ordinal(range(1, 7));
        $model = new SharedAbilityModel; $fit = $model->fit($edges, 'u10 boys', [$field]);
        $scores = array_column($fit['ratings'], 'score');
        for ($i = 0; $i < 6; $i++) { $this->assertGreaterThan($scores[$i+1], $scores[$i]); }
        $this->assertSame(50.0, $scores[3]);
        $this->assertSame($fit, $model->fit(array_reverse($edges), 'u10 boys', [$field]));
        [$largeEdges, $largeField] = $this->ordinal(range(1, 256));
        $large = $model->fit($largeEdges, 'u10 boys', [$largeField]);
        $this->assertSame($scores[0], $large['ratings'][1]['score']);
        $this->assertSame(255, count($largeEdges));
        $this->assertSame(0, array_values($large['components'])[0]['played']);
    }

    public function test_played_contradictions_outweigh_weak_ordinal_evidence_and_recent_evidence_matters_more(): void
    {
        [$edges, $field] = $this->ordinal([1,2]); $model = new SharedAbilityModel;
        $contradicted = $model->fit(array_merge($edges, [$this->edge(2,1)]), '', [$field]);
        $this->assertGreaterThan($contradicted['ratings'][1]['score'], $contradicted['ratings'][2]['score']);
        $recent = $model->fit($edges, '', [$field]);
        $field['weight'] /= 4;
        $old = $model->fit($edges, '', [$field]);
        $this->assertGreaterThan($old['ratings'][1]['score'], $recent['ratings'][1]['score']);
    }

    public function test_shared_ordinal_entrants_transfer_field_strength_without_linking_other_components(): void
    {
        [$a, $first] = $this->ordinal([1,2,3], source: 'strong');
        [$b, $second] = $this->ordinal([4,5,6], source: 'weak');
        $edges = array_merge($a, $b);
        for ($i=0; $i<10; $i++) { $edges[] = $this->edge(3,4,1,'anchor-'.$i); }
        $edges[] = $this->edge(10,11);
        $fit = (new SharedAbilityModel)->fit($edges, 'u10 boys', [$first,$second]);
        $this->assertGreaterThan($fit['ratings'][5]['score'], $fit['ratings'][2]['score']);
        $this->assertSame($fit['ratings'][2]['component'], $fit['ratings'][5]['component']);
        $this->assertNotSame($fit['ratings'][10]['component'], $fit['ratings'][2]['component']);
    }

    public function test_safe_age_gender_word_order_aliases_retain_ball_and_masters_context(): void
    {
        $service = new \App\Services\Performance\PlayerSharedAbilityService;
        $this->assertSame('u13 boys', $service->cohort('Boys U/13'));
        $this->assertSame('u13 girls', $service->cohort('Girl u13'));
        $this->assertSame('u13 girls green ball', $service->cohort('Girls u/13 Green Ball'));
        $this->assertSame('masters · u13 boys', $service->cohort('Masters · Boys U/13'));
        $this->assertNotSame($service->cohort('u13 boys'), $service->cohort('u13 girls'));
        $this->assertNotSame($service->cohort('u13 boys'), $service->cohort('u13 boys green ball'));
    }

}
