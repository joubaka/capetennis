<?php

namespace App\Console\Commands;

use App\Domain\Ranking\Services\WilsonU9IdentitySnapshotService;
use App\Models\User;
use Illuminate\Console\Command;
use Throwable;

final class CloneWilsonRankingForDirkie extends Command
{
    protected $signature = 'ranking:clone-wilson-for-dirkie
        {--actor= : Super-user ID authorizing the correction}
        {--confirm= : Exact one-off confirmation token}';

    protected $description = 'Replace the Wilson Series calculated run with the published snapshot, swapping only Dirk 2439 to Dirkie 5332';

    public function handle(WilsonU9IdentitySnapshotService $service): int
    {
        $actorId = filter_var($this->option('actor'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $actor = $actorId ? User::query()->find($actorId) : null;
        if (! $actor || ! $actor->hasRole('super-user')) {
            $this->error('A valid super-user --actor is required; no ranking rows were changed.');
            return self::FAILURE;
        }

        try {
            $runId = $service->replaceCalculatedRun($actor, (string) $this->option('confirm'));
        } catch (Throwable $exception) {
            report($exception);
            $this->error('The guarded ranking clone could not be created. Review the application log; no ranking rows were changed.');
            return self::FAILURE;
        }

        $this->info("Calculated ranking run {$runId} now mirrors the published snapshot with the audited Dirkie identity swap.");
        return self::SUCCESS;
    }
}
