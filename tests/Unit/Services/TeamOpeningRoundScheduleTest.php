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
}
