<?php

namespace App\Services\Performance;

use Illuminate\Process\Factory;
use Symfony\Component\Process\PhpExecutableFinder;

class PlayerRatingHistoryProcessLauncher
{
    public function launch(string $owner): void
    {
        $php = (new PhpExecutableFinder)->find(false);
        if (!$php) throw new \RuntimeException('CLI PHP is unavailable.');
        $environment = ['CT_HISTORY_REFRESH_OWNER' => $owner, 'CT_HISTORY_PHP' => $php, 'CT_HISTORY_ROOT' => $this->projectRoot()];
        $process = app(Factory::class)->path($this->projectRoot())->env($environment)->timeout(15);
        if ($this->windows()) {
            $script = file_get_contents(base_path('scripts/run-requested-rating-history.ps1'));
            $encoded = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));
            $result = $process->run(['powershell.exe', '-NoProfile', '-NonInteractive', '-WindowStyle', 'Hidden', '-EncodedCommand', $encoded]);
        } else {
            // All command values come from the local runtime, never request input.
            $command = 'nohup '.escapeshellarg($php).' '.escapeshellarg($this->projectRoot().DIRECTORY_SEPARATOR.'artisan').' player-rating-history:requested-refresh </dev/null >/dev/null 2>&1 &';
            $result = $process->run($command);
        }
        if ($result->failed()) throw new \RuntimeException('Background process could not start.');
    }

    protected function windows(): bool { return PHP_OS_FAMILY === 'Windows'; }
    protected function projectRoot(): string { return base_path(); }
}
