<?php

namespace App\Console\Commands;

use App\Services\PlusPublicationNotificationService;
use Illuminate\Console\Command;

class SendPlusPublicationNotifications extends Command
{
    protected $signature = 'player-plus:publication-notifications {--limit=50 : Maximum due draws to prepare}';
    protected $description = 'Queue due Player Plus publication notices when explicitly enabled';

    public function handle(PlusPublicationNotificationService $service): int
    {
        $count = $service->prepareDue(max(1, min(200, (int) $this->option('limit'))));
        $this->info("Queued {$count} publication notifications.");
        return self::SUCCESS;
    }
}
