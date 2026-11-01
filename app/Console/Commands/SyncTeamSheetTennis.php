<?php

namespace App\Console\Commands;

use App\Services\Performance\TeamSheetTennisImportService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class SyncTeamSheetTennis extends Command
{
    protected $signature = 'teamsheet:sync-tennis';
    protected $description = 'Sync a complete restricted native TeamSheet verified singles-results snapshot';

    public function handle(TeamSheetTennisImportService $import): int
    {
        try { $count = $import->sync(); }
        catch (ValidationException $error) { $this->error(collect($error->errors())->flatten()->first()); return self::FAILURE; }
        catch (\Throwable) { $this->error('TeamSheet sync failed. Earlier results were retained.'); return self::FAILURE; }
        $this->info("Synced {$count} verified singles results. Reviewed player identities and actual playing dates are required before rating use.");
        return self::SUCCESS;
    }
}
