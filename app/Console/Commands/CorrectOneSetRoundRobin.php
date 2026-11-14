<?php

namespace App\Console\Commands;

use App\Models\{Draw, User};
use App\Services\Draw\OneSetRoundRobinCorrectionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Gate;

class CorrectOneSetRoundRobin extends Command
{
    protected $signature = 'draw:correct-one-set {draw} {--event= : Exact owning event ID} {--actor= : Authorized event administrator ID} {--apply : Apply the unchanged preview} {--snapshot= : Required preview fingerprint for apply}';
    protected $description = 'Preview or explicitly correct an unconfigured round robin to one full set without replaying scores.';

    public function handle(OneSetRoundRobinCorrectionService $service): int
    {
        try {
            $draw = Draw::findOrFail((int) $this->argument('draw'));
            $actor = User::findOrFail((int) $this->option('actor'));
            Gate::forUser($actor)->authorize('correctRoundRobinScoring', $draw);
            $result = $this->option('apply')
                ? $service->apply($draw, (int) $this->option('event'), (string) $this->option('snapshot'), $actor)
                : $service->review($draw, (int) $this->option('event'));
            $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }
}
