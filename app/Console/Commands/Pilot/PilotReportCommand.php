<?php

namespace App\Console\Commands\Pilot;

use App\Models\EngineRun;
use App\Models\PilotEvent;
use App\Models\PlatformAuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * php artisan pilot:report [--event=<id>]
 *
 * Prints a summary of all pilot events or a single one.
 * Also queries engine_runs and platform_audit_logs for pilot-related activity.
 */
class PilotReportCommand extends Command
{
    protected $signature   = 'pilot:report {--event= : Filter to a specific pilot_event id}';
    protected $description = 'Print a summary report for all internal pilot events.';

    public function handle(): int
    {
        $query = PilotEvent::with('event')->orderBy('id');

        if ($id = $this->option('event')) {
            $query->where('id', $id);
        }

        $pilots = $query->get();

        if ($pilots->isEmpty()) {
            $this->warn('[pilot:report] No pilot events found. Run: php artisan pilot:seed');
            return self::SUCCESS;
        }

        $this->info('');
        $this->info('╔══════════════════════════════════════════════════════════╗');
        $this->info('║           CAPE TENNIS — INTERNAL PILOT REPORT            ║');
        $this->info('╚══════════════════════════════════════════════════════════╝');
        $this->info('');

        foreach ($pilots as $pilot) {
            $this->printPilotSummary($pilot);
        }

        // Engine runs summary across all pilot events
        $this->printEngineRunSummary($pilots->pluck('event_id')->all());

        // Audit log summary
        $this->printAuditSummary($pilots->pluck('event_id')->all());

        return self::SUCCESS;
    }

    private function printPilotSummary(PilotEvent $pilot): void
    {
        $event  = $pilot->event;
        $status = match ($pilot->status) {
            'complete' => '<fg=green>COMPLETE</>',
            'failed'   => '<fg=red>FAILED</>',
            default    => '<fg=yellow>ACTIVE</>',
        };

        $this->line("  ┌─────────────────────────────────────────");
        $this->line("  │ Pilot #<fg=cyan>{$pilot->id}</>  [{$pilot->scenario}]  {$status}");
        $this->line("  │ Event:      #{$event?->id} — " . Str::limit($event?->name ?? '—', 50));
        $this->line("  │ Engine:     {$pilot->engine_mode}");
        $this->line("  │ Players:    {$pilot->player_count}   Draws: {$pilot->draw_count}");
        $this->line("  ├─────────────────────────────────────────");

        $mismatchColor = $pilot->mismatch_count > 0   ? 'red'    : 'green';
        $fallbackColor = $pilot->fallback_count > 0   ? 'yellow' : 'green';
        $exceptionColor= $pilot->canonical_exception_count > 0 ? 'red' : 'green';

        $this->line("  │ Mismatches:          <fg={$mismatchColor}>{$pilot->mismatch_count}</>");
        $this->line("  │ Fallbacks:           <fg={$fallbackColor}>{$pilot->fallback_count}</>");
        $this->line("  │ Rollbacks:           {$pilot->rollback_count}");
        $this->line("  │ Score deletes:       {$pilot->score_delete_count}");
        $this->line("  │ Canonical exceptions:<fg={$exceptionColor}>{$pilot->canonical_exception_count}</>");

        if (! empty($pilot->notes)) {
            $this->line("  ├─── Notes");
            foreach ($pilot->notes as $k => $v) {
                $this->line("  │   {$k}: {$v}");
            }
        }

        $this->line("  └─────────────────────────────────────────");
        $this->info('');
    }

    private function printEngineRunSummary(array $eventIds): void
    {
        // Look up draw_ids for these events
        $drawIds = \App\Models\Draw::whereIn('event_id', $eventIds)->pluck('id')->all();

        if (empty($drawIds)) {
            return;
        }

        $runs      = EngineRun::whereIn('draw_id', $drawIds)->get();
        $total     = $runs->count();
        $misses    = $runs->where('mismatch_detected', true)->count();
        $fallbacks = $runs->where('fallback_used', true)->count();
        $canonical = $runs->where('canonical_success', true)->count();
        $avgMs     = $total > 0 ? round($runs->avg('duration_ms'), 1) : 0;

        $this->info('  ┌─── Engine Run Summary (pilot draws)');
        $this->line("  │ Total runs:      {$total}");
        $this->line("  │ Canonical OK:    {$canonical}");
        $missColor = $misses > 0 ? 'red' : 'green';
        $fbColor   = $fallbacks > 0 ? 'yellow' : 'green';
        $this->line("  │ Mismatches:      <fg={$missColor}>{$misses}</>");
        $this->line("  │ Fallbacks used:  <fg={$fbColor}>{$fallbacks}</>");
        $this->line("  │ Avg duration:    {$avgMs} ms");
        $this->info('  └─────────────────────────────────────────');
        $this->info('');
    }

    private function printAuditSummary(array $eventIds): void
    {
        // Count relevant audit actions in the last 24 hours for these events
        // PlatformAuditLog doesn't directly store event_id, but subject_type=Draw with subject_id in drawIds
        $drawIds = \App\Models\Draw::whereIn('event_id', $eventIds)->pluck('id')->all();

        if (empty($drawIds)) {
            return;
        }

        $logs = PlatformAuditLog::where('subject_type', 'Draw')
            ->whereIn('subject_id', $drawIds)
            ->selectRaw('action, count(*) as cnt')
            ->groupBy('action')
            ->pluck('cnt', 'action');

        if ($logs->isEmpty()) {
            return;
        }

        $this->info('  ┌─── Audit Log Summary (pilot draws)');
        foreach ($logs as $action => $count) {
            $this->line("  │ {$action}: {$count}");
        }
        $this->info('  └─────────────────────────────────────────');
        $this->info('');
    }
}
