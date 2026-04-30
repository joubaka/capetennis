<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Process queued jobs safely on a shared server.
        // --stop-when-empty: exits as soon as the queue is empty (not a daemon).
        // --max-time=270: hard stop after 4.5 min so the process is gone before
        //                 the next 5-minute cron fires another instance.
        // withoutOverlapping(): prevents a second instance starting while one runs.
        $schedule->command('queue:work --stop-when-empty --max-time=270 --memory=128')
            ->everyMinute()
            ->withoutOverlapping();

        // Remind super-users and players of bank refunds pending > 30 days.
        $schedule->command('refunds:send-bank-reminders')
            ->dailyAt('08:00')
            ->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
