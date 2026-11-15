<?php

namespace Tests\Unit;

use Cron\CronExpression;
use Illuminate\Console\Scheduling\Schedule;
use Tests\TestCase;

class PlayerPlusScheduleTest extends TestCase
{
    public function test_one_ordered_base_and_bounded_overnight_resume_replace_daytime_rating_builders(): void
    {
        $all = collect(app(Schedule::class)->events());
        $base = $all->first(fn ($event) => str_contains($event->command ?? '', 'player-plus:refresh-derived --scheduled'));
        $resume = $all->first(fn ($event) => str_contains($event->command ?? '', 'player-plus:refresh-derived --resume --scheduled'));
        $this->assertNotNull($base); $this->assertNotNull($resume); $this->assertSame('2 3 * * *', $base->expression);
        foreach ([$base, $resume] as $event) { $this->assertSame('Africa/Johannesburg', $event->timezone); $this->assertTrue($event->withoutOverlapping); $this->assertTrue($event->runInBackground); $this->assertSame(480, $event->expiresAt); }
        $this->assertSame('* * * * *', $resume->expression);
        $this->assertCount(0, $all->filter(fn ($event) => str_contains($event->command ?? '', 'player-ability:refresh') || str_contains($event->command ?? '', 'player-rating-history:refresh')));
        foreach (['03:02' => false, '03:07' => true, '06:57' => true, '06:58' => false, '12:02' => false] as $time => $due) {
            $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-15 '.$time.':00', 'Africa/Johannesburg'));
            // Laravel captures the invocation's clock when defining between(). Recreate schedule:run for each tick.
            $this->app->forgetInstance(Schedule::class);
            $atTick = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command ?? '', 'player-plus:refresh-derived --resume --scheduled'));
            $this->assertSame($due, $atTick->filtersPass($this->app), $time);
        }
    }

    public function test_membership_notifications_are_due_on_the_documented_host_cron_cadence(): void
    {
        $events = collect(app(Schedule::class)->events())->filter(
            fn ($event) => str_contains($event->command ?? '', 'player-plus:notifications --apply')
        );

        $this->assertCount(1, $events);
        $event = $events->first();
        $this->assertSame('32 8 * * *', $event->expression);
        $this->assertSame('Africa/Johannesburg', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
        $cron = new CronExpression($event->expression);
        $this->assertTrue($cron->isDue('2026-11-15 08:32:00', 'Africa/Johannesburg'));
        $this->assertFalse($cron->isDue('2026-11-15 08:30:00', 'Africa/Johannesburg'));
        $this->assertSame(2, 32 % 5);
    }
}
