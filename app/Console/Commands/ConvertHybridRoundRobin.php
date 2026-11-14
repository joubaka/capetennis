<?php

namespace App\Console\Commands;

use App\Models\{Draw, User};
use App\Services\Draw\HybridRoundRobinService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Gate;

class ConvertHybridRoundRobin extends Command
{
    protected $signature = 'draw:convert-hybrid {draw} {--event= : Required owning event ID} {--actor= : Required authorized administrator ID} {--apply : Apply the reviewed conversion} {--snapshot= : Exact preview snapshot required for apply}';
    protected $description = 'Preview or apply top-four playoffs plus a lower round robin with carried results.';

    public function handle(HybridRoundRobinService $service): int
    {
        try {
            $draw = Draw::findOrFail((int) $this->argument('draw'));
            $actor = User::findOrFail((int) $this->option('actor'));
            Gate::forUser($actor)->authorize('convertHybridRoundRobin', $draw);
            $eventId = (int) $this->option('event');
            $result = $this->option('apply')
                ? $service->apply($draw, $eventId, (string) $this->option('snapshot'), $actor)
                : $service->review($draw, $eventId);
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}
