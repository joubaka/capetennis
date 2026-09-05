<?php

namespace Tests\Unit;

use App\Domain\Draws\Services\ScheduleAvailability;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class ScheduleAvailabilityTest extends TestCase
{
    public function test_unresolved_sibling_matches_in_one_flexible_draw_can_share_a_time(): void
    {
        $calendar = new ScheduleAvailability();
        $start = Carbon::parse('2026-09-06 09:00:00');
        $possiblePlayers = [1, 2, 3, 4];

        $calendar->reserveWithRest(52, '1', $start, 45, 60, $possiblePlayers, 'flexible-draw-1443');

        $sameDraw = $calendar->nextAvailableForMatch(
            $start, 45, 60, 52, '2', $possiblePlayers, 'flexible-draw-1443'
        );
        $sameCourt = $calendar->nextAvailableForMatch(
            $start, 45, 60, 52, '1', $possiblePlayers, 'flexible-draw-1443'
        );
        $otherDraw = $calendar->nextAvailableForMatch(
            $start, 45, 60, 52, '2', $possiblePlayers, 'flexible-draw-9999'
        );

        $this->assertSame('2026-09-06 09:00:00', $sameDraw->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-06 09:45:00', $sameCourt->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-06 10:00:00', $otherDraw->format('Y-m-d H:i:s'));
    }
}
