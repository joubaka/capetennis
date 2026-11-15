<?php

namespace App\Console\Commands;

use App\Services\Performance\PlayerPlusDerivedRefresh;
use Illuminate\Console\Command;

class RefreshPlayerPlusDerived extends Command
{
    protected $signature = 'player-plus:refresh-derived {--resume : Continue the saved bounded batch without rebuilding completed base stages} {--scheduled : Restrict automatic work to the South African overnight window} {--limit=10 : Maximum player attempts in this invocation (1–100)} {--max-seconds=240 : Time budget between canonical commands (1–240)} {--reference= : Correlated manual refresh reference}';
    protected $description = 'Build saved ratings, monthly history, and bounded active-Plus match/event reconstructions in dependency order';

    public function handle(PlayerPlusDerivedRefresh $refresh): int
    {
        foreach (['limit' => 100, 'max-seconds' => 240] as $option => $max) {
            if (!ctype_digit((string) $this->option($option)) || (int) $this->option($option) < 1 || (int) $this->option($option) > $max) { $this->error('Choose valid bounded player and time limits.'); return self::FAILURE; }
        }
        try {
            $result = $refresh->run((bool) $this->option('resume'), (bool) $this->option('scheduled'), (int) $this->option('limit'), (int) $this->option('max-seconds'), $this->option('reference'));
        } catch (\Throwable) {
            $this->error('The derived refresh could not save its operational state. Saved results were retained.'); return self::FAILURE;
        }
        $exit = $result['exit_code']; unset($result['exit_code']); $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        return $exit;
    }
}
