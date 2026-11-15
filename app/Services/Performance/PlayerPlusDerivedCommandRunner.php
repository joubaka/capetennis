<?php

namespace App\Services\Performance;

use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/** Preserve the outer command's JSON output while keeping canonical command DI and locks. */
class PlayerPlusDerivedCommandRunner
{
    public function run(string $name, array $arguments = []): int
    {
        if (!in_array($name, ['player-ability:refresh', 'player-rating-history:refresh', 'player-match-progress:refresh', 'player-event-ratings:refresh'], true)) throw new \InvalidArgumentException('Unsupported derived-refresh stage.');
        $command = Artisan::all()[$name] ?? throw new \RuntimeException('Canonical derived-refresh command is unavailable.');
        $input = new ArrayInput(['command' => $name] + $arguments); $input->setInteractive(false);
        // Nested Artisan::call() changes the application's lastOutput pointer to the child.
        return $command->run($input, new BufferedOutput);
    }
}
