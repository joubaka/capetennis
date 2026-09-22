<?php

namespace App\Console\Commands;

use App\Domain\Ranking\Services\WilsonU9IdentitySnapshotService;
use App\Models\User;
use Illuminate\Console\Command;
use Throwable;

final class RepairDirkieLegacyRankingTies extends Command
{
    protected $signature = 'ranking:repair-dirkie-legacy-ties {--actor=} {--confirm=}';
    protected $description = 'Confirm legacy tie groups on the guarded Dirkie calculated ranking clone without changing positions';

    public function handle(WilsonU9IdentitySnapshotService $service): int
    {
        $actorId = filter_var($this->option('actor'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $actor = $actorId ? User::query()->find($actorId) : null;
        if (! $actor || ! $actor->hasRole('super-user')) {
            $this->error('A valid super-user --actor is required; no ranking rows were changed.');
            return self::FAILURE;
        }
        try {
            $runId = $service->repairCalculatedLegacyTies($actor, (string) $this->option('confirm'));
        } catch (Throwable $exception) {
            report($exception);
            $this->error('The guarded legacy-tie recovery could not be completed. Review the application log; no ranking rows were changed.');
            return self::FAILURE;
        }
        $this->info("Legacy tie evidence is ready on calculated run {$runId}; positions and points were unchanged.");
        return self::SUCCESS;
    }
}
