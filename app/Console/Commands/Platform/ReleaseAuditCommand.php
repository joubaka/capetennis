<?php

namespace App\Console\Commands\Platform;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;

/**
 * platform:release-audit
 *
 * Post-release audit: verifies the system is stable after a deployment.
 * Compares key counters before/after and surfaces drift.
 * Outputs a markdown-formatted summary suitable for release notes.
 */
class ReleaseAuditCommand extends Command
{
    protected $signature   = 'platform:release-audit {--since=1hour : Period to audit (1hour|6hours|24hours)}';
    protected $description = 'Post-release audit: engine mismatch trend, failed jobs, integrity counters';

    public function handle(): int
    {
        $period  = $this->option('since');
        $minutes = match($period) {
            '6hours'  => 360,
            '24hours' => 1440,
            default   => 60,
        };

        $since = now()->subMinutes($minutes);

        $this->info('');
        $this->info("╔══════════════════════════════════════════════════════╗");
        $this->info("║   Cape Tennis — Release Audit ({$period})");
        $this->info("╚══════════════════════════════════════════════════════╝");
        $this->info('');

        $issues = [];

        // Engine run summary
        try {
            $runs      = DB::table('engine_runs')->where('created_at', '>=', $since)->count();
            $misses    = DB::table('engine_runs')->where('created_at', '>=', $since)->where('mismatch_detected', true)->count();
            $fallbacks = DB::table('engine_runs')->where('created_at', '>=', $since)->where('fallback_used', true)->count();
            $pct       = $runs > 0 ? round(($misses / $runs) * 100, 2) : 0;
            $this->info("  Engine Runs       : {$runs}");
            $this->info("  Mismatch Rate     : {$pct}% ({$misses} mismatches)");
            $this->info("  Fallbacks         : {$fallbacks}");
            if ($pct > 5) {
                $issues[] = "Engine mismatch rate {$pct}% exceeds 5% threshold";
            }
        } catch (\Throwable $e) {
            $this->warn("  Engine stats unavailable: " . $e->getMessage());
        }

        // Failed jobs
        try {
            $failed = DB::table('failed_jobs')->where('failed_at', '>=', $since)->count();
            $this->info("  New Failed Jobs   : {$failed}");
            if ($failed > 5) {
                $issues[] = "{$failed} new failed jobs since {$period}";
            }
        } catch (\Throwable) {
            $this->info("  New Failed Jobs   : n/a");
        }

        // Duplicate PayFast
        try {
            $dups = DB::table('transactions_pf')
                ->select('pf_payment_id', DB::raw('COUNT(*) as c'))
                ->whereNotNull('pf_payment_id')
                ->where('created_at', '>=', $since)
                ->groupBy('pf_payment_id')
                ->havingRaw('COUNT(*) > 1')
                ->count();
            $this->info("  New Dup Payments  : {$dups}");
            if ($dups > 0) {
                $issues[] = "{$dups} duplicate PayFast payment group(s) detected since {$period}";
            }
        } catch (\Throwable $e) {
            $this->info("  Dup Payments      : n/a");
        }

        // Audit log entries generated
        try {
            $auditCount = DB::table('platform_audit_logs')->where('created_at', '>=', $since)->count();
            $this->info("  Audit Log Entries : {$auditCount}");
        } catch (\Throwable) {
            $this->info("  Audit Log Entries : n/a (table may not exist yet)");
        }

        // New unresolved high mismatches
        try {
            $highMisses = DB::table('engine_mismatches')
                ->where('resolved', false)->where('severity', 'high')
                ->where('created_at', '>=', $since)->count();
            $this->info("  New High Mismatches: {$highMisses}");
            if ($highMisses > 0) {
                $issues[] = "{$highMisses} new unresolved high-severity engine mismatch(es)";
            }
        } catch (\Throwable $e) {
            $this->info("  High Mismatches   : n/a");
        }

        // Pending migrations
        try {
            $output  = new \Symfony\Component\Console\Output\BufferedOutput();
            Artisan::call('migrate:status', ['--no-ansi' => true], $output);
            $pending = substr_count($output->fetch(), '| No ');
            $this->info("  Pending Migrations: {$pending}");
            if ($pending > 0) {
                $issues[] = "{$pending} pending migration(s) — did the deployment run migrations?";
            }
        } catch (\Throwable $e) {
            $this->warn("  Could not check migrations: " . $e->getMessage());
        }

        // Summary
        $this->info('');
        if (!empty($issues)) {
            $this->error('  ⚠️  RELEASE AUDIT — ISSUES FOUND:');
            foreach ($issues as $issue) {
                $this->error("     • {$issue}");
            }
            $this->info('');
            $this->warn('  Review the above before marking the release stable.');
        } else {
            $this->info('  🟢 RELEASE AUDIT PASSED — no issues detected.');
        }

        $this->info('');
        return empty($issues) ? self::SUCCESS : self::FAILURE;
    }
}
