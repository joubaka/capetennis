<?php

namespace App\Services\Performance;

use Illuminate\Process\Factory;
use Symfony\Component\Process\PhpExecutableFinder;

class PlayerEventRatingHistoryLauncher
{
    public function launch(int $player, string $owner): void
    {
        if ($player < 1) throw new \InvalidArgumentException('Invalid player.');
        $php = (new PhpExecutableFinder)->find(false);
        if (!$php) throw new \RuntimeException('Background PHP runtime unavailable.');
        $process = app(Factory::class)->path(base_path())->env(['CT_EVENT_HISTORY_OWNER' => $owner,
            'CT_EVENT_PLAYER_ID' => (string) $player, 'CT_EVENT_HISTORY_PHP' => $php, 'CT_EVENT_HISTORY_ROOT' => base_path()])->timeout(15);
        if (PHP_OS_FAMILY === 'Windows') {
            $encoded = base64_encode(mb_convert_encoding(file_get_contents(base_path('scripts/run-requested-event-rating-history.ps1')), 'UTF-16LE', 'UTF-8'));
            $result = $process->run(['powershell.exe', '-NoProfile', '-NonInteractive', '-WindowStyle', 'Hidden', '-EncodedCommand', $encoded]);
        } else {
            $result = $process->run('nohup '.escapeshellarg($php).' '.escapeshellarg(base_path('artisan')).' player-event-ratings:requested-refresh '.$player.' </dev/null >/dev/null 2>&1 &');
        }
        if ($result->failed()) throw new \RuntimeException('Background event history could not start.');
    }
}
