<?php

namespace App\Console\Commands;

use App\Services\PlusMorningDigestService;
use Illuminate\Console\Command;

class PreparePlusMorningDigest extends Command
{
    protected $signature = 'player-plus:morning-digest {--limit=50 : Maximum accounts per invocation}';
    protected $description = 'Capture bounded public changes and prepare conditional Plus morning emails';

    public function handle(PlusMorningDigestService $service): int
    {
        if (! config('plus_morning_digest.delivery_enabled')) { $this->info('Plus morning digest delivery is disabled.'); return self::SUCCESS; }
        $service->scan();
        $count = $service->prepare((int) $this->option('limit'));
        $service->recoverPending();
        $this->info($count.' morning digests queued; transport acceptance is recorded separately.');
        return self::SUCCESS;
    }
}
