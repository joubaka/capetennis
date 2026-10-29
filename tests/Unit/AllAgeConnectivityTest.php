<?php

namespace Tests\Unit;

use App\Services\Performance\AllAgeConnectivityService;
use PHPUnit\Framework\TestCase;

class AllAgeConnectivityTest extends TestCase
{
    private function graph(): array
    {
        return ['reference_component' => 'boys', 'components' => [
            'girls-young' => ['members' => [5]], 'older' => ['members' => [3]],
            'boys' => ['members' => [1, 2]], 'girls' => ['members' => [4, 6]],
        ], 'players' => [1 => ['age_groups' => ['u13 boys']], 2 => ['age_groups' => ['u15 boys']],
            3 => ['age_groups' => ['u19 boys']], 4 => ['age_groups' => ['u13 girls']],
            5 => ['age_groups' => ['u11 girls']], 6 => ['age_groups' => ['u15 girls']]]];
    }

    public function test_plan_connects_components_via_nearby_categories_without_manufacturing_scores(): void
    {
        $graph = $this->graph();
        $report = (new AllAgeConnectivityService)->summarize($graph);
        $this->assertSame(4, $report['component_count']);
        $this->assertSame(3, $report['minimum_links']);
        $this->assertCount(3, $report['links']);
        $byGroup = array_column($report['links'], null, 'from');
        $this->assertSame('boys', $byGroup['girls']['to']);
        $this->assertTrue($byGroup['girls']['cross_gender']);
        $this->assertSame('u15 boys', $byGroup['older']['to_category']);
        $this->assertSame('girls', $byGroup['girls-young']['to']);
        $this->assertSame($graph, $this->graph());
        $this->assertArrayNotHasKey('score', $report['links'][0]);
    }

    public function test_empty_and_single_components_need_no_links_and_source_order_does_not_change_plan(): void
    {
        $service = new AllAgeConnectivityService;
        $this->assertSame(0, $service->summarize([])['minimum_links']);
        $graph = $this->graph();
        $before = $service->summarize($graph);
        $graph['components'] = array_reverse($graph['components'], true);
        $this->assertSame($before, $service->summarize($graph));
        $graph['components'] = ['boys' => $graph['components']['boys']];
        $this->assertSame([], $service->summarize($graph)['links']);
    }
}
