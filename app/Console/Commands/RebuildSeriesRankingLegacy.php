<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RebuildSeriesRankingLegacy extends Command
{
    protected $signature = 'ranking:rebuild-legacy {series_id}';
    protected $description = 'Retired: legacy category-results ranking rebuild';

    public function handle(): int
    {
        $this->error(
            'This legacy rebuild is disabled because it bypasses the canonical ranking lifecycle. '
            .'Use the Series Ranking admin Rebuild action instead.'
        );

        return self::FAILURE;
    }
}
