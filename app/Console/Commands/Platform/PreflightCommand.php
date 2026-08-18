<?php

namespace App\Console\Commands\Platform;

use App\Services\PlatformHealthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use App\Support\Audit\AuditDailyDigest;

/**
 * platform:preflight
 *
 * Pre-deployment safety gate. Run this before every production release.
 * Exits with code 1 if any critical blocker is found.
 */
class PreflightCommand extends Command
{
    protected $signature   = 'platform:preflight {--strict : Fail on warnings too}';
    protected $description = 'Pre-deployment safety gate — checks migrations, queue, engine mismatch, duplicate growth';

    public function __construct(private PlatformHealthService $health) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('');
        $this->info('╔══════════════════════════════════════════════╗');
        $this->info('║     Cape Tennis — Platform Preflight Check   ║');
        $this->info('╚══════════════════════════════════════════════╝');
        $this->info('');

        $blockers = [];
        $warnings = [];

        // 1. Pending migrations
        $this->line('  Checking pending migrations...');
        try {
            $output = new \Symfony\Component\Console\Output\BufferedOutput();
            Artisan::call('migrate:status', ['--no-ansi' => true], $output);
            $statusOutput = $output->fetch();
            $pending = preg_match_all('/\bPending\s*$/mi', $statusOutput);
            if ($pending > 0) {
                $blockers[] = "⛔ {$pending} pending migration(s) — inspect and deploy only approved migration paths";
            } else {
                $this->info('  ✅ Migrations: all applied');
            }
        } catch (\Throwable $e) {
            $warnings[] = "⚠️  Could not check migrations: " . $e->getMessage();
        }

        // 2. Failed jobs
        $this->line('  Checking failed jobs...');
        try {
            $failed = DB::table('failed_jobs')->count();
            if ($failed > 0) {
                $warnings[] = "⚠️  {$failed} failed job(s) in queue — review before releasing";
            } else {
                $this->info('  ✅ Failed jobs: none');
            }
        } catch (\Throwable) {
            $this->info('  ✅ Failed jobs: table not present (skipped)');
        }

        // 3. Engine mismatch spike (last 2 hours > 5%)
        $this->line('  Checking engine mismatch rate...');
        try {
            $since   = now()->subHours(2);
            $total   = DB::table('engine_runs')->where('created_at', '>=', $since)->count();
            $misses  = DB::table('engine_runs')->where('created_at', '>=', $since)->where('mismatch_detected', true)->count();
            if ($total > 0) {
                $pct = round(($misses / $total) * 100, 1);
                if ($pct > 5) {
                    $blockers[] = "⛔ Engine mismatch rate {$pct}% in last 2 h (threshold: 5%) — investigate before releasing";
                } else {
                    $this->info("  ✅ Engine mismatch rate: {$pct}% ({$misses}/{$total} runs)");
                }
            } else {
                $this->info('  ✅ Engine runs: none in last 2 h (skipped)');
            }
        } catch (\Throwable $e) {
            $warnings[] = "⚠️  Could not check engine mismatch: " . $e->getMessage();
        }

        // 4. Duplicate PayFast growth (more than 5 new in 24 h)
        $this->line('  Checking duplicate payment growth...');
        try {
            $dups = DB::table('transactions_pf')
                ->select('pf_payment_id', DB::raw('COUNT(*) as c'))
                ->whereNotNull('pf_payment_id')
                ->where('created_at', '>=', now()->subDay())
                ->groupBy('pf_payment_id')
                ->havingRaw('COUNT(*) > 1')
                ->count();
            if ($dups > 5) {
                $blockers[] = "⛔ {$dups} new duplicate PayFast payment groups in last 24 h — investigate";
            } elseif ($dups > 0) {
                $warnings[] = "⚠️  {$dups} duplicate PayFast payment group(s) today — monitor closely";
            } else {
                $this->info('  ✅ Duplicate payments: none today');
            }
        } catch (\Throwable $e) {
            $warnings[] = "⚠️  Could not check duplicate payments: " . $e->getMessage();
        }

        // 5. Environment
        $this->line('  Checking environment...');
        if (config('app.debug') && app()->environment('production')) {
            $blockers[] = '⛔ APP_DEBUG=true in production — must be false before release';
        } else {
            $this->info('  ✅ Environment: ' . strtoupper(app()->environment()));
        }

        // 6. Unresolved high-severity mismatches
        $this->line('  Checking unresolved high-severity mismatches...');
        try {
            $highCount = DB::table('engine_mismatches')->where('severity', 'high')->where('resolved', false)->count();
            if ($highCount > 0) {
                $warnings[] = "⚠️  {$highCount} unresolved high-severity engine mismatch(es) — resolve or accept before release";
            } else {
                $this->info('  ✅ Unresolved high mismatches: none');
            }
        } catch (\Throwable $e) {
            $warnings[] = "⚠️  Could not check mismatches: " . $e->getMessage();
        }

        // 7. Canonical audit availability and previous-day integrity seal
        $this->line('  Checking canonical audit integrity...');
        try {
            if (! Schema::hasTable('audit_events') || ! Schema::hasTable('audit_daily_seals')) {
                $blockers[] = '⛔ Canonical audit tables are missing — apply only the approved audit migrations before release';
            } else {
                $yesterday = now()->subDay()->toDateString();
                $seal = DB::table('audit_daily_seals')->where('audit_date', $yesterday)->first();
                if (! $seal) {
                    $warnings[] = "⚠️  No audit integrity seal exists for {$yesterday} — run audit:seal {$yesterday}";
                } else {
                    $digest = app(AuditDailyDigest::class)->calculate($yesterday);
                    $valid = $digest['integrity_failures'] === 0
                        && hash_equals((string) $seal->digest, $digest['digest'])
                        && (int) $seal->event_count === $digest['event_count'];
                    if (! $valid) {
                        $blockers[] = "⛔ Audit integrity verification failed for {$yesterday} — investigate before release";
                    } else {
                        $this->info("  ✅ Audit integrity: {$digest['event_count']} events verified for {$yesterday}");
                    }
                }
            }
        } catch (\Throwable $e) {
            $blockers[] = '⛔ Could not verify canonical audit integrity: '.$e->getMessage();
        }

        // ---------------------------------------------------------------
        // Summary
        $this->info('');
        $strict = $this->option('strict');

        if (!empty($warnings)) {
            $this->warn('  WARNINGS:');
            foreach ($warnings as $w) {
                $this->warn("    {$w}");
            }
            $this->info('');
        }

        if (!empty($blockers)) {
            $this->error('  BLOCKERS:');
            foreach ($blockers as $b) {
                $this->error("    {$b}");
            }
            $this->info('');
            $this->error('  ❌ PREFLIGHT FAILED — resolve all blockers before deploying.');
            $this->info('');
            return self::FAILURE;
        }

        if ($strict && !empty($warnings)) {
            $this->error('  ❌ PREFLIGHT FAILED (--strict mode) — resolve all warnings.');
            return self::FAILURE;
        }

        $this->info('  🟢 PREFLIGHT PASSED' . (!empty($warnings) ? ' (with warnings)' : '') . ' — safe to deploy.');
        $this->info('');
        return self::SUCCESS;
    }
}
