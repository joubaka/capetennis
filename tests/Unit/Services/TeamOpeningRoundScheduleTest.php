<?php

namespace Tests\Unit\Services;

use App\Models\Team;
use App\Services\TeamDrawGenerationService;
use PHPUnit\Framework\TestCase;

class TeamOpeningRoundScheduleTest extends TestCase
{
    public function test_every_opening_round_preserves_even_and_odd_schedules(): void
    {
        $generator = new TeamDrawGenerationService;
        foreach ([3, 4, 5, 6] as $count) {
            $teams = collect(range(1, $count))->map(fn ($id) => (new Team)->forceFill(['id' => $id, 'name' => 'Region '.$id]));
            $default = $generator->buildRoundRobinSchedule($teams);
            foreach (array_keys($default) as $opening) {
                $schedule = $generator->buildRoundRobinSchedule($teams, $opening);
                $this->assertSame($default[$opening], $schedule[1]);
                $pairs = [];
                foreach ($schedule as $matches) {
                    $playing = [];
                    foreach ($matches as [$home, $away]) {
                        $playing[] = $home->id;
                        $playing[] = $away->id;
                        $pair = [$home->id, $away->id]; sort($pair);
                        $pairs[] = implode('-', $pair);
                    }
                    $this->assertSame(count($playing), count(array_unique($playing)));
                    $this->assertCount($count - $count % 2, $playing);
                }
                $this->assertCount($count * ($count - 1) / 2, array_unique($pairs));
                $this->assertSame(count($pairs), count(array_unique($pairs)));
            }
        }
    }

    public function test_unavailable_opening_round_is_rejected(): void
    {
        $teams = collect(range(1, 4))->map(fn ($id) => (new Team)->forceFill(['id' => $id]));
        $this->expectException(\InvalidArgumentException::class);
        (new TeamDrawGenerationService)->buildRoundRobinSchedule($teams, 4);
    }

    public function test_tie_order_is_an_exact_permutation_and_follows_its_round_when_rotated(): void
    {
        $teams = collect(range(1, 6))->map(fn ($id) => (new Team)->forceFill(['id' => $id]));
        $generator = new TeamDrawGenerationService;
        $base = $generator->buildRoundRobinSchedule($teams);
        $keys = array_reverse(array_map(fn ($pair) => $generator->pairKey(...$pair), $base[3]));
        $ordered = $generator->buildRoundRobinSchedule($teams, 3, [3 => $keys]);
        $this->assertSame(array_reverse($base[3]), $ordered[1]);
        $this->assertSame($base[4], $ordered[2]);
        $this->assertSame($base[1], $ordered[4]);
        foreach ([
            [3 => [$keys[0], $keys[0], $keys[2]]], [3 => array_slice($keys, 1)],
            [3 => [$keys[0], $keys[1], '999-1000']], [8 => $keys],
            [3 => array_map(fn ($pair) => $generator->pairKey(...$pair), $base[2])],
        ] as $invalid) {
            try { $generator->buildRoundRobinSchedule($teams, 1, $invalid); $this->fail('Invalid tie order was accepted.'); }
            catch (\InvalidArgumentException) { $this->addToAssertionCount(1); }
        }
    }

    public function test_scheduling_fallback_slots_preserve_priorities_and_order_with_competing_draws(): void
    {
        $service = new \App\Services\Scheduling\EventVenueScheduleService;
        $compare = new \ReflectionMethod($service, 'isEarlier');
        $candidate = ['id' => 'team:999999', 'time' => \Carbon\Carbon::parse('2026-11-06 08:00:00'), 'fairness' => 0, 'draw_priority' => 1];
        $best = array_replace($candidate, ['id' => 'team:1000000']);
        $nodes = [
            $candidate['id'] => ['draw_id' => 42, 'round' => 1, 'play_order' => 1, 'wave' => 1, 'fallback_order_key' => 'team:1000000'],
            $best['id'] => ['draw_id' => 42, 'round' => 1, 'play_order' => 1, 'wave' => 1, 'fallback_order_key' => 'team:999999'],
            'team:500000' => ['draw_id' => 43, 'round' => 1, 'play_order' => 1, 'wave' => 1],
        ];
        foreach ([false, true] as $dayBatch) {
            $candidate['day_batch'] = $dayBatch;
            $best['day_batch'] = $dayBatch;
            $this->assertTrue($compare->invoke($service, $candidate, $best, $nodes));
            $defaults = $nodes;
            unset($defaults[$candidate['id']]['fallback_order_key'], $defaults[$best['id']]['fallback_order_key']);
            $this->assertFalse($compare->invoke($service, $candidate, $best, $defaults));
            $other = array_replace($candidate, ['id' => 'team:500000']);
            $this->assertTrue($compare->invoke($service, $candidate, $other, $nodes));
            $this->assertTrue($compare->invoke($service, $other, $best, $nodes));
            $later = $candidate; $later['time'] = $candidate['time']->copy()->addHour();
            $this->assertFalse($compare->invoke($service, $later, $best, $nodes));
            $laterRubber = $nodes; $laterRubber[$candidate['id']]['play_order'] = 2;
            $this->assertFalse($compare->invoke($service, $candidate, $best, $laterRubber));
        }
    }
}
