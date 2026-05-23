<?php

namespace App\Console\Commands\Pilot;

use Database\Seeders\PilotEventSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

/**
 * php artisan pilot:seed
 *
 * Seeds the 4 internal pilot events.
 * Blocked in production unless --force is passed.
 */
class PilotSeedCommand extends Command
{
    protected $signature   = 'pilot:seed {--force : Allow seeding in non-local environments}';
    protected $description = 'Seed the 4 internal pilot test events (local/staging only).';

    public function handle(): int
    {
        if (App::environment('production') && ! $this->option('force')) {
            $this->error('[pilot:seed] Blocked in production. Use --force to override (not recommended).');
            return self::FAILURE;
        }

        $this->info('[pilot:seed] Seeding pilot events...');
        $this->call('db:seed', ['--class' => PilotEventSeeder::class]);
        $this->info('[pilot:seed] Done. Run php artisan pilot:report to view results.');
        return self::SUCCESS;
    }
}
