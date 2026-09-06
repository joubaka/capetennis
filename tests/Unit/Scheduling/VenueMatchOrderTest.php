<?php

namespace Tests\Unit\Scheduling;

use App\Services\Scheduling\VenueMatchOrder;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class VenueMatchOrderTest extends TestCase
{
    #[Test]
    public function it_orders_print_rows_by_time_venue_natural_court_then_draw_and_play_order(): void
    {
        $rows = collect([
            ['id' => 1, 'scheduled_at' => '2026-09-20 09:00:00', 'venue' => 'Manor', 'court' => 'Court 10', 'draw_name' => 'Boys', 'play_order' => 1],
            ['id' => 2, 'scheduled_at' => '2026-09-20 09:00:00', 'venue' => 'Manor', 'court' => 'Court 2', 'draw_name' => 'Girls', 'play_order' => 2],
            ['id' => 3, 'scheduled_at' => '2026-09-20 08:30:00', 'venue' => 'Manor', 'court' => 'Court 8', 'draw_name' => 'Boys', 'play_order' => 3],
        ]);

        $order = app(VenueMatchOrder::class);
        $sorted = $rows->sort(fn (array $left, array $right) => $order->compare($left, $right))->values();

        $this->assertSame([3, 2, 1], $sorted->pluck('id')->all());
    }
}
