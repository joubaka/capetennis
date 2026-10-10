<?php

namespace App\Console\Commands;

use App\Jobs\RefreshPlayerRatings;
use App\Services\Performance\PlayerRatingRefreshDiagnostics;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** Host-local launcher acknowledgement; the owner is never part of command arguments. */
class RunRequestedPlayerAbilityRefresh extends Command
{
    protected $signature = 'player-ability:requested-refresh {--reference= : Correlated refresh reference}';
    protected $description = 'Run an already leased, requested private ratings refresh';

    public function handle(): int
    {
        $owner = getenv('CT_RATINGS_REFRESH_OWNER');
        $reference = $this->option('reference');
        putenv('CT_RATINGS_REFRESH_OWNER');
        unset($_ENV['CT_RATINGS_REFRESH_OWNER'], $_SERVER['CT_RATINGS_REFRESH_OWNER']);
        if (! is_string($owner) || ! preg_match('/^[A-Za-z0-9_-]{1,128}$/', $owner) || ! is_string($reference) || ! Str::isUuid($reference)) {
            PlayerRatingRefreshDiagnostics::write(PlayerRatingRefreshDiagnostics::reference(), 'launch', 'invalid_child_context', null, self::FAILURE);
            return self::FAILURE;
        }
        try {
            return (new RefreshPlayerRatings($owner, $reference))->handle() === 0 ? self::SUCCESS : self::FAILURE;
        } catch (\Throwable $error) {
            PlayerRatingRefreshDiagnostics::write($reference, 'launch', 'child_failed', $error);
            return self::FAILURE;
        }
    }
}
