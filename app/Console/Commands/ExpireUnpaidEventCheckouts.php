<?php

namespace App\Console\Commands;

use App\Domain\Payments\Services\UnpaidCheckoutExpiryService;
use Illuminate\Console\Command;

class ExpireUnpaidEventCheckouts extends Command
{
    protected $signature = 'registrations:expire-checkouts {--apply : Expire eligible drafts; otherwise show candidate counts only}';
    protected $description = 'Release event checkout drafts older than five minutes, preserving payments in progress and financial evidence';

    public function handle(UnpaidCheckoutExpiryService $service): int
    {
        if (!$this->option('apply')) {
            $counts = $service->candidateCounts();
            $this->info("Preview only: registration candidates {$counts['registration']}; team candidates {$counts['team']}. Financial and lifecycle checks are repeated under lock when applying.");
            return self::SUCCESS;
        }
        $counts = $service->expireAll();
        $this->info("Expired registration checkouts: {$counts['registration']}; team checkouts: {$counts['team']}; protected or inconsistent drafts skipped: {$counts['skipped']}.");
        return self::SUCCESS;
    }
}
