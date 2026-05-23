<?php

namespace App\Console\Commands\Pilot;

use App\Models\Draw;
use App\Models\EngineRun;
use App\Models\EngineMismatch;
use App\Models\PilotDrawApproval;
use App\Models\PilotFeedback;
use App\Models\PlatformAuditLog;
use App\Services\Pilot\PilotMonitor;
use App\Services\Pilot\PilotReadinessMatrix;
use Illuminate\Console\Command;

/**
 * pilot:public-report
 *
 * Full limited-public-pilot summary report (Step 8):
 *   - Pilot draws enabled (approved vs revoked)
 *   - Real-world mismatch rate
 *   - Fallback rate
 *   - Rollback events
 *   - Convenor feedback summary
 *   - Readiness matrix per domain
 *   - Recommendation for expanded RR rollout
 *
 * Usage:
 *   php artisan pilot:public-report
 *   php artisan pilot:public-report --days=7
 */
class PilotPublicReportCommand extends Command
{
    protected $signature   = 'pilot:public-report {--days=30 : Report window in days}';
    protected $description = 'Generate the limited public RR pilot summary report';

    public function handle(): int
    {
        $days  = (int) $this->option('days');
        $since = now()->subDays($days);

        $this->info('');
        $this->info('╔═══════════════════════════════════════════════════════════════╗');
        $this->info('║  Cape Tennis — Limited Public RR Pilot Report                ║');
        $this->info('║  Period: last ' . str_pad("{$days} days", 7) .
                    '   From: ' . $since->format('Y-m-d') . '                ║');
        $this->info('╚═══════════════════════════════════════════════════════════════╝');
        $this->info('');

        $this->printDrawRegistry();

        $snapshot = PilotMonitor::snapshot($since);
        $this->printEngineMetrics($snapshot);

        $this->printRollbackSummary($since);
        $this->printFeedback();
        $this->printReadinessMatrix();
        $this->printRecommendation($snapshot);

        return self::SUCCESS;
    }

    // ------------------------------------------------------------------
    // Sections
    // ------------------------------------------------------------------

    private function printDrawRegistry(): void
    {
        $this->info('  ┌─── 1. Pilot Draw Registry');

        $all      = PilotDrawApproval::orderByDesc('id')->get();
        $approved = $all->where('status', PilotDrawApproval::STATUS_APPROVED);
        $revoked  = $all->where('status', PilotDrawApproval::STATUS_REVOKED);

        $this->line("  │  Approved: {$approved->count()}   Revoked: {$revoked->count()}");
        $this->info('  │');

        foreach ($approved as $a) {
            $draw = Draw::find($a->draw_id);
            $mode = $draw?->engine_mode ?? '?';
            $this->line("  │  ✓ Draw #{$a->draw_id}  \"{$draw?->drawName}\"  mode={$mode}  players={$a->player_count}");
        }
        foreach ($revoked as $r) {
            $this->line("  │  ✗ Draw #{$r->draw_id} [REVOKED]  {$r->notes}");
        }

        $this->line('  └' . str_repeat('─', 60));
        $this->info('');
    }

    private function printEngineMetrics(array $snapshot): void
    {
        $this->info('  ┌─── 2. Real-World Engine Metrics');
        $t = $snapshot['totals'];

        $this->line("  │  Canonical runs:      {$t['total_runs']}");
        $this->line("  │  Mismatch count:      {$t['mismatch_count']}  ({$t['mismatch_pct']}%)");
        $this->line("  │  Fallback count:      {$t['fallback_count']}  ({$t['fallback_pct']}%)");
        $this->line("  │  Score deletes:       {$t['score_delete_count']}");
        $this->line("  │  Duplicate events:    {$t['duplicate_count']}");
        $this->line("  │  Avg duration:        {$t['avg_duration_ms']} ms");

        if (! empty($snapshot['draws'])) {
            $this->info('  │');
            $this->line('  │  Per-draw breakdown:');
            foreach ($snapshot['draws'] as $drawId => $m) {
                $this->line(
                    "  │    Draw #{$drawId}: runs={$m['total_runs']}" .
                    "  mismatch={$m['mismatch_pct']}%" .
                    "  fallback={$m['fallback_pct']}%" .
                    "  rollbacks={$m['rollback_count']}" .
                    "  avg={$m['avg_duration_ms']}ms"
                );
            }
        }

        $this->line('  └' . str_repeat('─', 60));
        $this->info('');
    }

    private function printRollbackSummary(\DateTimeInterface $since): void
    {
        $this->info('  ┌─── 3. Rollback Events');

        $pilotDrawIds = PilotDrawApproval::pluck('draw_id');

        $events = PlatformAuditLog::whereIn('subject_id', $pilotDrawIds)
            ->where('subject_type', 'draw')
            ->whereIn('action', ['progression.reset', 'draw.pilot_disabled', 'engine_fallback'])
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        if ($events->isEmpty()) {
            $this->line('  │  ✓ No rollback events in period.');
        } else {
            $this->line("  │  Total: {$events->count()}");
            foreach ($events as $e) {
                $this->line("  │  [{$e->created_at->format('Y-m-d H:i')}]  {$e->action}  draw=#{$e->subject_id}");
            }
        }

        $this->line('  └' . str_repeat('─', 60));
        $this->info('');
    }

    private function printFeedback(): void
    {
        $this->info('  ┌─── 4. Convenor Feedback');

        $rows = PilotFeedback::selectRaw('category, status, COUNT(*) as cnt')
            ->groupBy('category', 'status')
            ->orderBy('category')
            ->get();

        if ($rows->isEmpty()) {
            $this->line('  │  No feedback recorded yet.');
        } else {
            foreach ($rows as $row) {
                $this->line("  │  [{$row->category}]  {$row->status}: {$row->cnt}");
            }
        }

        $open = PilotFeedback::open()->count();
        $this->line("  │  Total open: {$open}");

        $this->line('  └' . str_repeat('─', 60));
        $this->info('');
    }

    private function printReadinessMatrix(): void
    {
        $this->info('  ┌─── 5. Readiness Matrix (each domain is independent)');

        $matrix = PilotReadinessMatrix::compute();

        $labels = [
            PilotReadinessMatrix::DOMAIN_RR_CANONICAL      => 'RR Canonical Engine     ',
            PilotReadinessMatrix::DOMAIN_PLAYOFF_CANONICAL => 'Playoff Canonical Engine',
            PilotReadinessMatrix::DOMAIN_FEED_IN           => 'Feed-in / Consolation   ',
            PilotReadinessMatrix::DOMAIN_SCHEDULING        => 'Scheduling (OOP)        ',
            PilotReadinessMatrix::DOMAIN_RENDERING         => 'Rendering               ',
        ];

        foreach ($matrix as $domain => $data) {
            $label = $labels[$domain] ?? $domain;
            $level = strtoupper($data['level']);
            $this->line("  │  {$label}  {$level}");
        }

        $this->line('  └' . str_repeat('─', 60));
        $this->info('');
    }

    private function printRecommendation(array $snapshot): void
    {
        $this->info('  ┌─── 6. Recommendation');

        $t            = $snapshot['totals'];
        $hasAlerts    = ! empty($snapshot['alerts']);
        $matrix       = PilotReadinessMatrix::compute();
        $rrLevel      = $matrix[PilotReadinessMatrix::DOMAIN_RR_CANONICAL]['level'];
        $openCritical = PilotFeedback::open()
            ->whereIn('category', [PilotFeedback::CAT_SCORING, PilotFeedback::CAT_STANDINGS])
            ->count();

        $ready = ! $hasAlerts
            && $rrLevel === PilotReadinessMatrix::LEVEL_READY
            && $t['mismatch_pct'] <= 5.0
            && $t['fallback_pct'] <= 2.0
            && $openCritical === 0;

        if ($ready) {
            $this->info('  │  ✅  READY FOR EXPANDED RR ROLLOUT');
            $this->line('  │');
            $this->line("  │  Mismatch:          {$t['mismatch_pct']}%   (threshold ≤ 5%)");
            $this->line("  │  Fallback:          {$t['fallback_pct']}%   (threshold ≤ 2%)");
            $this->line("  │  RR Readiness:      {$rrLevel}");
            $this->line("  │  Open scoring/standings feedback: {$openCritical}");
            $this->line('  │');
            $this->line('  │  Next step:');
            $this->line('  │    php artisan pilot:enable-draw {draw_id}  to approve additional RR draws');
        } else {
            $this->warn('  │  ⚠   NOT YET READY FOR EXPANDED ROLLOUT');
            $this->line('  │');
            $this->line("  │  Mismatch:          {$t['mismatch_pct']}%   (threshold ≤ 5%)");
            $this->line("  │  Fallback:          {$t['fallback_pct']}%   (threshold ≤ 2%)");
            $this->line("  │  RR Readiness:      {$rrLevel}");
            $this->line("  │  Open scoring/standings feedback: {$openCritical}");
            $this->line("  │  Active alerts:     " . count($snapshot['alerts']));
            $this->line('  │');
            $this->line('  │  Resolve issues above, then re-run: php artisan pilot:public-report');
        }

        $this->line('  └' . str_repeat('─', 60));
        $this->info('');
    }
}
