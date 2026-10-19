<?php

namespace Tests\Unit;

use App\Models\{Draw, Event};
use App\Services\Scheduling\ScheduleProgramme;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ScheduleProgrammeDatesTest extends TestCase
{
    public static function dayCounts(): array
    {
        return [[1], [2], [3], [4]];
    }

    #[DataProvider('dayCounts')]
    public function test_inclusive_event_dates_determine_programme_windows(int $count): void
    {
        $service = new ScheduleProgramme();
        $event = new Event(['start_date' => '2026-10-23', 'end_date' => '2026-10-'.(22 + $count)]);
        $dates = $service->eventDates($event);
        $this->assertCount($count, $dates);
        $draw = new Draw(); $draw->id = 42;
        $programme = ['days' => array_map(fn ($date) => ['start' => $date.' 09:15:00', 'end' => $date.' 16:30:00'], $dates), 'rounds' => [['draw_id' => 42, 'round' => 1, 'day' => $count, 'sequence' => 1]]];
        $normalized = $service->normalize($programme, collect([$draw]), [42 => [1]], $event);
        $this->assertSame($count, $normalized['rounds'][0]['day']);
        $this->assertSame($dates[$count - 1].' 16:30:00', $normalized['days'][$count - 1]['end']);
    }

    public static function invalidWindows(): array
    {
        return [
            'outside date' => [['2026-10-23 08:00', '2026-10-23 18:00'], ['2026-10-25 08:00', '2026-10-25 18:00'], 1],
            'missing day' => [['2026-10-23 08:00', '2026-10-23 18:00'], null, 1],
            'overnight end' => [['2026-10-23 08:00', '2026-10-24 01:00'], ['2026-10-24 08:00', '2026-10-24 18:00'], 1],
            'end before start' => [['2026-10-23 18:00', '2026-10-23 08:00'], ['2026-10-24 08:00', '2026-10-24 18:00'], 1],
            'round outside days' => [['2026-10-23 08:00', '2026-10-23 18:00'], ['2026-10-24 08:00', '2026-10-24 18:00'], 3],
        ];
    }

    #[DataProvider('invalidWindows')]
    public function test_invalid_daily_windows_are_rejected(array $first, ?array $second, int $day): void
    {
        $event = new Event(['start_date' => '2026-10-23', 'end_date' => '2026-10-24']);
        $draw = new Draw(); $draw->id = 42;
        $days = array_map(fn ($window) => ['start' => $window[0], 'end' => $window[1]], array_filter([$first, $second]));
        $this->expectException(\InvalidArgumentException::class);
        (new ScheduleProgramme())->normalize(['days' => $days, 'rounds' => [['draw_id' => 42, 'round' => 1, 'day' => $day, 'sequence' => 1]]], collect([$draw]), [42 => [1]], $event);
    }
}
