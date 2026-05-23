<?php

namespace App\Console\Commands;

use App\Models\Draw;
use App\Models\EngineMismatch;
use App\Models\EngineRun;
use Illuminate\Console\Command;

/**
 * engine:rollback-draw
 *
 * Instantly rolls a draw back to legacy engine mode.
 * - Sets draw engine_mode = 'legacy'
 * - Marks all unresolved mismatches for the draw as resolved
 * - Logs the rollback event to engine_runs
 *
 * Safe to run at any time, including during a live tournament.
 */
class RollbackDraw extends Command
{
    protected $signature   = 'engine:rollback-draw
                                {draw : The Draw ID to roll back}
                                {--reason= : Optional reason for rollback (logged)}
                                {--force : Skip confirmation prompt}';

    protected $description = 'Instantly roll a draw back to legacy engine mode with a single command.';

    public function handle(): int
    {
        $drawId = (int) $this->argument('draw');
        $reason = $this->option('reason') ?? 'Manual rollback via artisan command';

        $draw = Draw::find($drawId);

        if (! $draw) {
            $this->error("Draw #{$drawId} not found.");
            return self::FAILURE;
        }

        $previousMode = $draw->engine_mode ?? 'inherit';

        $this->line("Draw #{$drawId}: <fg=yellow>{$draw->drawName}</>");
        $this->line("Current engine mode: <fg=cyan>{$draw->effectiveEngineMode()}</> (override: {$previousMode})");

        if ($draw->engine_mode === 'legacy') {
            $this->info("Draw is already in LEGACY mode. Nothing to do.");
            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            if (! $this->confirm("Roll back draw #{$drawId} to LEGACY mode?")) {
                $this->line("Cancelled.");
                return self::SUCCESS;
            }
        }

        // Set draw to legacy
        $draw->update(['engine_mode' => 'legacy']);

        // Mark all unresolved mismatches as resolved
        $resolved = EngineMismatch::forDraw($drawId)->unresolved()->get();
        $resolvedCount = $resolved->count();
        EngineMismatch::forDraw($drawId)->unresolved()->update(['resolved' => true]);

        // Log the rollback as an engine_run entry
        try {
            EngineRun::create([
                'draw_id'           => $drawId,
                'engine_mode'       => 'legacy',
                'operation_type'    => 'rollback',
                'legacy_success'    => true,
                'canonical_success' => null,
                'mismatch_detected' => false,
                'fallback_used'     => false,
                'mismatch_count'    => 0,
                'duration_ms'       => 0,
                'exception'         => "ROLLBACK: {$reason} (was: {$previousMode})",
                'created_at'        => now(),
            ]);
        } catch (\Throwable $e) {
            $this->warn("Could not log rollback to engine_runs: " . $e->getMessage());
        }

        $this->newLine();
        $this->info("Draw #{$drawId} successfully rolled back:");
        $this->line("  Mode: <fg=yellow>{$previousMode}</> → <fg=green>LEGACY</>");
        $this->line("  Mismatches marked resolved: <fg=green>{$resolvedCount}</>");
        $this->line("  Reason: {$reason}");

        return self::SUCCESS;
    }
}
