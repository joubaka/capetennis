<?php

namespace App\Console\Commands;

use Illuminate\Foundation\Console\ViewCacheCommand;
use Symfony\Component\Console\Output\OutputInterface;

/** Warm live deployments without deleting usable, unchanged compiled views. */
class CacheDeploymentViews extends ViewCacheCommand
{
    protected $signature = 'deployment:cache-views';

    protected $description = 'Compile Blade views while preserving unchanged compiled files';

    public function handle()
    {
        // Laravel recompiles source and compares the resulting content before an
        // atomic replacement. Do not clear first: every deletion forces a write.
        $this->paths()->each(function ($path) {
            $this->components->task($path, null, OutputInterface::VERBOSITY_VERBOSE);
            $this->compileViews($this->bladeFilesIn([$path]));
        });

        $this->newLine();
        $this->components->info('Blade views warmed; unchanged compiled files retained.');
    }
}
