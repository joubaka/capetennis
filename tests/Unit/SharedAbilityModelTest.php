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
}
