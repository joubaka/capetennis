<?php

namespace App\Console\Commands\Pilot;

use App\Models\Draw;
use App\Models\EngineRun;
use App\Models\EngineMismatch;
use App\Models\PilotDrawApproval;
use App\Models\PilotFeedback;
use App\Models\PlatformAuditLog;
use App\Services\Pilot\PilotMonitor;
use App\Services\Pilot\PilotAutoRollback;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * pilot:daily-audit
 *
 * Daily monitoring command for the limited public RR pilot.
 * Includes:
 *   - Pilot draw summary (approved, revoked)
 *   - Mismatch / fallback rates per draw
 *   - Orphan progression detection
 *   - Duplicate results count
 *   - Rollback events
 *   - Performance summary
 *   - Open feedback items
 *   - Auto-rollback evaluation
 *
 * Usage:
 *   php artisan pilot:daily-audit
 *   php artisan pilot:daily-audit --hours=48
 *   php artisan pilot:daily-audit --auto-rollback   (trigger rollback for threshold breaches)
 */
class PilotDailyAuditCommand extends Command
{
    protected $signature = 'pilot:daily-audit
                            {--hours=24 : Audit window in hours (default 24)}
                            {--auto-rollback : Automatically rollback draws that breach thresholds}';

    protected $description = 'Daily audit for the limited public canonical RR pilot';

    private string $line80 = '────────────────────────────────────────────────────────────────────────────────';

    public function handle(): int
    {
        $hours = (int) $this->option('hours');
        $since = now()->subHours($hours);

        $this->info('');
        $this->info('╔══════════════════════════════════════════════════════════════════╗');
        $this->info('║     Cape Tennis — Canonical RR Pilot — Daily Audit              ║');
        $this->info('║     Window: last ' . str_pad("{$hours}h", 4) . '   Since: ' . $since->format('Y-m-d H:i') . '            ║');
        $this->info('╚══════════════════════════════════════════════════════════════════╝');
        $this->info('');

        // ── Section 1: Pilot draw registry
        $this->printDrawRegistry();

        // ── Section 2: Live metrics snapshot
        $snapshot = PilotMonitor::snapshot($since);
        $this->printMetricsSnapshot($snapshot);

        // ── Section 3: Orphan progression
        $this->printOrphanProgression($since);

        // ── Section 4: Duplicate results
        $this->printDuplicateResults($since);

        // ── Section 5: Rollback events
        $this->printRollbackEvents($since);

        // ── Section 6: Performance summary
        $this->printPerformanceSummary($since);

        // ── Section 7: Open feedback
        $this->printFeedbackSummary();

        // ── Section 8: Alerts + auto-rollback
        $this->printAlerts($snapshot['alerts']);

        if ($this->option('auto-rollback') && ! empty($snapshot['alerts'])) {
            $this->info('');
            $this->warn('  [--auto-rollback] Evaluating threshold breaches...');
            $rolledBack = PilotAutoRollback::evaluateAll();
            if (! empty($rolledBack)) {
                $this->error('  ✗ Auto-rolled back draws: ' . implode(', ', array_map(fn($id) => "#{$id}", $rolledBack)));
            } else {
                $this->line('  ✓ No draws required auto-rollback.');
            }
        }

        $this->info('');
        $this->info('  Run: php artisan pilot:readiness   for readiness matrix.');
        $this->info('  Run: php artisan pilot:public-report  for full public pilot report.');
        $this->info('');

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------
    // Section printers
    // ------------------------------------------------------------------

    private function printDrawRegistry(): void
    {
        $this->info('  ┌─── Pilot Draw Registry');

        $approvals = PilotDrawApproval::orderByDesc('id')->get();

        if ($approvals->isEmpty()) {
            $this->line('  │  No pilot draws registered yet. Run: php artisan pilot:enable-draw {draw_id}');
            $this->line('  └' . $this->line80);
            return;
        }

        foreach ($approvals as $a) {
            $draw   = Draw::find($a->draw_id);
            $name   = $draw?->drawName ?? "Draw #{$a->draw_id}";
            $status = $a->status === PilotDrawApproval::STATUS_APPROVED
                ? "\033[32mapproved\033[0m" : "\033[31mrevoked\033[0m";
            $mode   = $draw?->engine_mode ?? '?';

            // Live player count: check draw_registrations first, fall back to group registrations
            $liveCount = $draw ? $draw->registrations()->count() : 0;
            if ($liveCount === 0 && $draw) {
                $liveCount = \DB::table('draw_group_registrations')
                    ->join('draw_groups', 'draw_groups.id', '=', 'draw_group_registrations.draw_group_id')
                    ->where('draw_groups.draw_id', $draw->id)
                    ->distinct('draw_group_registrations.registration_id')
                    ->count('draw_group_registrations.registration_id');
            }

            // Keep stored player_count in sync
            if ($liveCount > 0 && $a->player_count !== $liveCount) {
                $a->update(['player_count' => $liveCount]);
            }

            $this->line("  │  #{$a->draw_id}  {$name}  status={$a->status}  engine_mode={$mode}  players={$liveCount}");
        }

        $this->line('  └' . str_repeat('─', 40));
        $this->info('');
    }

    private function printMetricsSnapshot(array $snapshot): void
    {
        $this->info('  ┌─── Live Metrics (' . count($snapshot['draws']) . ' pilot draw(s))');

        if (empty($snapshot['draws'])) {
            $this->line('  │  No approved pilot draws with engine runs yet.');
            $this->line('  └' . str_repeat('─', 40));
            $this->info('');
            return;
        }

        foreach ($snapshot['draws'] as $drawId => $m) {
            $this->line("  │  Draw #{$drawId}:");
            $this->line("  │    Runs={$m['total_runs']}  Mismatches={$m['mismatch_count']} ({$m['mismatch_pct']}%)  Fallbacks={$m['fallback_count']} ({$m['fallback_pct']}%)");
            $this->line("  │    Rollbacks={$m['rollback_count']}  ScoreDeletes={$m['score_delete_count']}  Duplicates={$m['duplicate_count']}  AvgMs={$m['avg_duration_ms']}");
            $this->line("  │    OpenFeedback={$m['open_feedback']}");
        }

        $t = $snapshot['totals'];
        $this->line('  ├─── Totals');
        $this->line("  │    Runs={$t['total_runs']}  Mismatch={$t['mismatch_pct']}%  Fallback={$t['fallback_pct']}%  AvgMs={$t['avg_duration_ms']}");
        $this->line('  └' . str_repeat('─', 40));
        $this->info('');
    }

    private function printOrphanProgression(\DateTimeInterface $since): void
    {
        $this->info('  ┌─── Orphan Progression Check');

        // Fixtures with match_status = 'complete' but no FixtureResult rows
        $pilotDrawIds = PilotDrawApproval::approved()->pluck('draw_id');

        if ($pilotDrawIds->isEmpty()) {
            $this->line('  │  No approved draws to check.');
            $this->line('  └' . str_repeat('─', 40));
            $this->info('');
            return;
        }

        $orphans = DB::table('fixtures')
            ->whereIn('draw_id', $pilotDrawIds)
            ->where('match_status', 'complete')
            ->whereNotIn('id', function ($q) {
                $q->select('fixture_id')->from('fixture_results');
            })
            ->count();

        if ($orphans > 0) {
            $this->error("  │  ✗ {$orphans} orphan fixture(s): marked complete with no FixtureResult rows.");
        } else {
            $this->line("  │  ✓ No orphan progressions detected.");
        }

        $this->line('  └' . str_repeat('─', 40));
        $this->info('');
    }

    private function printDuplicateResults(\DateTimeInterface $since): void
    {
        $this->info('  ┌─── Duplicate Results Check');

        $pilotDrawIds = PilotDrawApproval::approved()->pluck('draw_id');

        if ($pilotDrawIds->isEmpty()) {
            $this->line('  │  No approved draws to check.');
            $this->line('  └' . str_repeat('─', 40));
            $this->info('');
            return;
        }

        $dupes = DB::table('fixture_results')
            ->selectRaw('fixture_id, set_nr, COUNT(*) as cnt')
            ->whereIn('fixture_id', function ($q) use ($pilotDrawIds) {
                $q->select('id')->from('fixtures')->whereIn('draw_id', $pilotDrawIds);
            })
            ->groupBy('fixture_id', 'set_nr')
            ->havingRaw('cnt > 1')
            ->count();

        if ($dupes > 0) {
            $this->error("  │  ✗ {$dupes} duplicate (fixture_id, set_nr) pairs detected in pilot draws.");
        } else {
            $this->line("  │  ✓ No duplicate results detected.");
        }

        $this->line('  └' . str_repeat('─', 40));
        $this->info('');
    }

    private function printRollbackEvents(\DateTimeInterface $since): void
    {
        $this->info('  ┌─── Rollback Events');

        $pilotDrawIds = PilotDrawApproval::pluck('draw_id');

        $rollbacks = PlatformAuditLog::whereIn('subject_id', $pilotDrawIds)
            ->where('subject_type', 'draw')
            ->whereIn('action', ['progression.reset', 'draw.pilot_disabled', 'engine_fallback'])
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        if ($rollbacks->isEmpty()) {
            $this->line("  │  ✓ No rollback events in window.");
        } else {
            foreach ($rollbacks as $r) {
                $this->line("  │  [{$r->created_at}] action={$r->action}  draw=#{$r->subject_id}");
            }
        }

        $this->line('  └' . str_repeat('─', 40));
        $this->info('');
    }

    private function printPerformanceSummary(\DateTimeInterface $since): void
    {
        $this->info('  ┌─── Performance Summary (canonical runs)');

        $stats = EngineRun::where('engine_mode', 'canonical')
            ->where('created_at', '>=', $since)
            ->selectRaw('COUNT(*) as total, AVG(duration_ms) as avg_ms, MAX(duration_ms) as max_ms, MIN(duration_ms) as min_ms')
            ->first();

        if (! $stats || $stats->total == 0) {
            $this->line("  │  No canonical runs in window.");
        } else {
            $this->line("  │  Total={$stats->total}  Avg={$stats->avg_ms}ms  Max={$stats->max_ms}ms  Min={$stats->min_ms}ms");
        }

        $this->line('  └' . str_repeat('─', 40));
        $this->info('');
    }

    private function printFeedbackSummary(): void
    {
        $this->info('  ┌─── Convenor Feedback');

        $open = PilotFeedback::open()->selectRaw('category, COUNT(*) as cnt')->groupBy('category')->get();

        if ($open->isEmpty()) {
            $this->line("  │  ✓ No open feedback items.");
        } else {
            foreach ($open as $row) {
                $this->line("  │  [{$row->category}]  open={$row->cnt}");
            }
        }

        $this->line('  └' . str_repeat('─', 40));
        $this->info('');
    }

    private function printAlerts(array $alerts): void
    {
        $this->info('  ┌─── Threshold Alerts');

        if (empty($alerts)) {
            $this->line("  │  ✓ No threshold alerts.");
        } else {
            foreach ($alerts as $drawId => $messages) {
                foreach ($messages as $msg) {
                    $this->error("  │  ✗ {$msg}");
                }
            }
        }

        $this->line('  └' . str_repeat('─', 40));
    }
}
