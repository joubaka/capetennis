<?php

namespace Tests\Unit;

use App\Services\Performance\PlayedMatchMarginPolicy;
use PHPUnit\Framework\TestCase;

class PlayedMatchMarginPolicyTest extends TestCase
{
    public function test_normalized_margin_and_reversed_sides(): void
    {
        $policy = new PlayedMatchMarginPolicy;
        $this->assertSame(1.0, $policy->target([[6,0], [6,0]], 1, ['full','full']));
        $this->assertEqualsWithDelta(0.75 + 0.25 / 13, $policy->target([[7,6], [7,6]], 1, ['full','full']), 0.000001);
        $this->assertSame($policy->target([[6,4]], 1), $policy->target([[4,6]], 2));
        $this->assertSame($policy->target([[3,2]], 1), $policy->target([[6,4]], 1));
        $this->assertSame(0.75, $policy->target([[0,6], [7,6], [7,6]], 1));
    }

    public function test_tiebreaks_are_normalized_separately_and_unknown_units_fall_back(): void
    {
        $policy = new PlayedMatchMarginPolicy;
        $this->assertEqualsWithDelta(0.75 + 0.25 / 27, $policy->target([[6,4], [4,6], [10,8]], 1, ['full','full','match_tiebreak']), 0.000001);
        $this->assertEqualsWithDelta(0.75 + 0.25 / 9, $policy->target([[10,8]], 1, ['match_tiebreak']), 0.000001);
        $this->assertSame(1.0, $policy->target([[10,8]], 1));
        $this->assertSame(1.0, $policy->target([[6,4]], 1, ['custom']));
    }
}
