<?php
namespace App\Console\Commands;
use App\Services\PlusEventUpdateService;
use Illuminate\Console\Command;
class PreparePlusEventUpdates extends Command
{
    protected $signature = 'player-plus:event-updates {--limit=50}';
    protected $description = 'Prepare entered-event Plus updates after ten unchanged minutes';
    public function handle(PlusEventUpdateService $service): int
    {
        if (! config('plus_event_updates.delivery_enabled')) return self::SUCCESS;
        $limit = max(1, min(100, (int) $this->option('limit')));
        $service->reconcile($limit); $this->info((string) $service->prepareDue($limit)); return self::SUCCESS;
    }
}
