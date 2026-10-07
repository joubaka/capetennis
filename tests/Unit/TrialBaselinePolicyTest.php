<?php

namespace Tests\Unit;

use App\Models\{Event, EventType};
use App\Services\Performance\{SharedAbilityModel, TrialBaselinePolicy};
use Carbon\CarbonImmutable;
use Tests\TestCase;

class TrialBaselinePolicyTest extends TestCase
{
    private function edges(int $count = 1): array
    {
        return array_map(fn ($i) => ['winner' => 1, 'loser' => 2, 'weight' => 1.0, 'kind' => 'played', 'source_id' => 'match:'.$i], range(1,$count));
    }

    public function test_event_budget_is_volume_invariant_and_preserves_unique_match_records(): void
    {
        $policy = new TrialBaselinePolicy; $model = new SharedAbilityModel;
        $one = $policy->budget($this->edges()); $many = $policy->budget($this->edges(20));
        $this->assertCount(20,$many);
        $this->assertEqualsWithDelta(1.0,array_sum(array_column($many,'weight')),1e-12);
        $this->assertSame($model->fit($one)['ratings'][1]['score'],$model->fit($many)['ratings'][1]['score']);
    }

    public function test_anchor_replaces_zero_prior_and_is_not_recentered(): void
    {
        $edges = $this->edges(); $edges[0]['topology_only'] = true;
        $anchors = [1 => ['target' => 0.7,'weight' => 2.0],2 => ['target' => -0.2,'weight' => 2.0]];
        $fit = (new SharedAbilityModel)->fit($edges,'u13 boys',[],$anchors);
        $this->assertEqualsWithDelta(0.7,$fit['ratings'][1]['strength'],1e-8);
        $this->assertEqualsWithDelta(-0.2,$fit['ratings'][2]['strength'],1e-8);
        $this->assertTrue(array_values($fit['components'])[0]['trial_anchored']);
        $support = array_fill(0,5,['winner'=>2,'loser'=>1,'weight'=>1.0,'kind'=>'played','source_id'=>'support']);
        $later = (new SharedAbilityModel)->fit(array_merge($edges,$support),'u13 boys',[],$anchors);
        $this->assertLessThan($fit['ratings'][1]['score'],$later['ratings'][1]['score']);
        $this->assertGreaterThan($fit['ratings'][2]['score'],$later['ratings'][2]['score']);
    }

    public function test_trial_targets_are_unaged_but_anchor_weight_decays_and_only_largest_group_is_used(): void
    {
        $policy = new TrialBaselinePolicy; $event = (new Event)->forceFill(['id'=>200,'eventType'=>5,'name'=>'Main trial','start_date'=>'2025-01-01','end_date'=>'2025-01-02']);
        $event->setRelation('eventTypeModel',(new EventType)->forceFill(['id'=>5,'name'=>'Cavaliers Trials']));
        $this->assertTrue($policy->eligible($event));
        $edges = $this->edges(); $edges[]=['winner'=>2,'loser'=>3,'weight'=>1.0,'kind'=>'played','source_id'=>'bridge'];
        $edges[]=['winner'=>10,'loser'=>11,'weight'=>1.0,'kind'=>'played','source_id'=>'separate'];
        $edges=$policy->budget($edges);
        $fresh=$policy->baseline($event,'u13 boys',$edges,CarbonImmutable::parse('2025-01-02'));
        $old=$policy->baseline($event,'u13 boys',$edges,CarbonImmutable::parse('2026-01-02'));
        $this->assertSame([1,2,3],array_keys($fresh['anchors']));
        $this->assertSame(5,$fresh['metadata']['trial_players']);
        $this->assertSame($fresh['anchors'][1]['target'],$old['anchors'][1]['target']);
        $this->assertLessThan($fresh['anchors'][1]['weight'],$old['anchors'][1]['weight']);
        $event->eventType=1; $this->assertFalse($policy->eligible($event));
    }
}
