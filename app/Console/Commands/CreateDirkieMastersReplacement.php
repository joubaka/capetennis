<?php

namespace App\Console\Commands;

use App\Models\MastersInvitation;
use App\Models\Player;
use App\Models\User;
use App\Services\Masters\MastersInvitationService;
use Illuminate\Console\Command;
use Throwable;

class CreateDirkieMastersReplacement extends Command
{
    protected $signature = 'masters:create-dirkie-identity-replacement
        {--actor= : Super-user ID authorizing the correction}
        {--confirm= : Must equal DIRKIE-5332-WILSON-U9}';

    protected $description = 'Create the audited Dirkie Coetzee Masters replacement invitation after ranking publication';

    public function handle(MastersInvitationService $service): int
    {
        if ($this->option('confirm') !== 'DIRKIE-5332-WILSON-U9') {
            $this->error('Confirmation token mismatch; no invitation was created.');

            return self::FAILURE;
        }

        $actorId = filter_var($this->option('actor'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $actor = $actorId ? User::query()->find($actorId) : null;
        if (! $actor || ! $actor->hasRole('super-user')) {
            $this->error('A valid super-user --actor is required; no invitation was created.');

            return self::FAILURE;
        }

        try {
            $replacement = $service->createIdentityCorrectionReplacement(
                MastersInvitation::query()->findOrFail(807),
                Player::query()->findOrFail(5332),
                $actor,
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->error('The guarded replacement could not be created. Review the application log; no recipient details were displayed.');

            return self::FAILURE;
        }

        $this->info("Replacement invitation {$replacement->id} is ready; the player email was queued after commit.");

        return self::SUCCESS;
    }
}
