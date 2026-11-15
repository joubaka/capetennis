<?php

namespace Tests\Unit;

use Carbon\CarbonImmutable;
use Cron\CronExpression;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class PlayerRatingScheduleTest extends TestCase
{
    public function test_nightly_rating_jobs_are_due_on_host_scheduler_ticks(): void
    {
        $hostCron = new CronExpression('2-59/5 * * * *');
        $events = collect(app(Schedule::class)->events());

        foreach (['player-plus:refresh-derived --scheduled' => '03:02'] as $command => $time) {
            $event = $events->sole(fn ($event) => str_ends_with($event->command ?? '', ' '.$command));
            $dueAt = CarbonImmutable::parse('2026-01-15 '.$time, 'Africa/Johannesburg');

            $this->assertSame('Africa/Johannesburg', $event->timezone);
            $this->assertTrue((new CronExpression($event->expression))->isDue($dueAt));
            $this->assertTrue($hostCron->isDue($dueAt));
        }

        $this->assertCount(0, $events->filter(fn ($event) => str_contains($event->command ?? '', 'player-ability:refresh') || str_contains($event->command ?? '', 'player-rating-history:refresh')));
        $resume = $events->sole(fn ($event) => str_ends_with($event->command ?? '', ' player-plus:refresh-derived --resume --scheduled'));
        foreach (['03:07', '06:57'] as $time) {
            $dueAt = CarbonImmutable::parse('2026-01-15 '.$time, 'Africa/Johannesburg');
            $this->assertTrue((new CronExpression($resume->expression))->isDue($dueAt));
            $this->assertTrue($hostCron->isDue($dueAt));
        }
    }
}
