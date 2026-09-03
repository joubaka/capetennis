<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\Masters\RetryMastersInvitationEmails as RetryService;
use Illuminate\Console\Command;

class RetryMastersInvitationEmails extends Command
{
    protected $signature = 'masters:retry-failed-emails {event : Masters event ID}
        {--queue : Queue eligible failed invitations; omitted means preview only}';

    protected $description = 'Preview or requeue failed, still-valid Masters invitations for one event';

    public function handle(RetryService $service): int
    {
        $eventId = (string) $this->argument('event');
        if (!ctype_digit($eventId) || !Event::whereKey((int) $eventId)->exists()) {
            $this->error('A valid event ID is required.');
            return self::FAILURE;
        }

        $report = $service->run((int) $eventId, (bool) $this->option('queue'));
        $this->info($this->option('queue') ? 'Eligible Masters emails queued.' : 'Preview only: nothing queued or sent.');
        $this->table(['Failed invitations', 'Eligible', 'Queued', 'Skipped'], [array_values($report)]);

        return self::SUCCESS;
    }
}
