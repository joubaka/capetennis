<?php

namespace Tests\Unit;

use App\Services\Ranking\RankingEngine;
use App\Services\Ranking\Strategies\OverbergRankingStrategy;
use RuntimeException;
use Tests\TestCase;

class RankingEngineTest extends TestCase
{
    private RankingEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new RankingEngine();
    }

    public function test_resolves_overberg_type(): void
    {
        $strategy = $this->engine->resolve('overberg');
        $this->assertInstanceOf(OverbergRankingStrategy::class, $strategy);
    }

    public function test_resolves_platteland_type(): void
    {
        $strategy = $this->engine->resolve('platteland');
        $this->assertInstanceOf(OverbergRankingStrategy::class, $strategy);
    }

    public function test_resolves_overberg_series_type(): void
    {
        $strategy = $this->engine->resolve('overberg_series');
        $this->assertInstanceOf(OverbergRankingStrategy::class, $strategy);
    }

    public function test_resolves_platteland_series_type(): void
    {
        $strategy = $this->engine->resolve('platteland_series');
        $this->assertInstanceOf(OverbergRankingStrategy::class, $strategy);
    }

    public function test_throws_for_unknown_rank_type(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Unknown ranking type/');

        $this->engine->resolve('unknown_type_xyz');
    }
}
