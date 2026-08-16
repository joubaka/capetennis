<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Artisan;

/**
 * PlatformHealthService
 *
 * Aggregates all operational health signals into a single data structure
 * consumed by the health dashboard and the platform:health-check command.
 *
 * Each section returns an array of health items:
 *   [ 'label', 'value', 'status' => ok|warn|critical, 'detail' ]
 */
class PlatformHealthService
{
    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    public function all(): array
    {
        return [
            'engine'       => $this->engineHealth(),
            'financial'    => $this->financialHealth(),
            'draw'         => $this->drawHealth(),
            'registration' => $this->registrationHealth(),
            'queue'        => $this->queueHealth(),
            'system'       => $this->systemHealth(),
            'summary'      => $this->summary(),
        ];
    }

    // ------------------------------------------------------------------
    // Engine Health
    // ------------------------------------------------------------------

    public function engineHealth(): array
    {
        $items = [];

        try {
            $mode = config('engine.mode', env('ENGINE_MODE', 'legacy'));
            $items[] = $this->item('Engine Mode', strtoupper($mode),
                $mode === 'canonical' ? 'ok' : ($mode === 'hybrid' ? 'warn' : 'ok'));

            $since = now()->subDay();

            $totalRuns = DB::table('engine_runs')
                ->where('created_at', '>=', $since)->count();

            if ($totalRuns > 0) {
                $mismatches = DB::table('engine_runs')
                    ->where('created_at', '>=', $since)
                    ->where('mismatch_detected', true)->count();
                $fallbacks = DB::table('engine_runs')
                    ->where('created_at', '>=', $since)
                    ->where('fallback_used', true)->count();

                $mismatchPct = round(($mismatches / $totalRuns) * 100, 1);
                $fallbackPct = round(($fallbacks  / $totalRuns) * 100, 1);
                $parityPct   = round(100 - $mismatchPct, 1);

                $items[] = $this->item('Mismatch %', "{$mismatchPct}%",
                    $mismatchPct > 5 ? 'critical' : ($mismatchPct > 1 ? 'warn' : 'ok'),
                    "{$mismatches} / {$totalRuns} runs in last 24 h");

                $items[] = $this->item('Fallback %', "{$fallbackPct}%",
                    $fallbackPct > 5 ? 'critical' : ($fallbackPct > 0 ? 'warn' : 'ok'),
                    "{$fallbacks} fallbacks in last 24 h");

                $items[] = $this->item('Canonical Parity %', "{$parityPct}%",
                    $parityPct >= 99 ? 'ok' : ($parityPct >= 95 ? 'warn' : 'critical'));
            } else {
                $items[] = $this->item('Engine Runs (24 h)', '0', 'ok', 'No runs recorded');
            }

            // Unresolved high-severity mismatches
            $highMismatches = DB::table('engine_mismatches')
                ->where('severity', 'high')
                ->where('resolved', false)->count();
            $items[] = $this->item('Unresolved High Mismatches', (string)$highMismatches,
                $highMismatches > 0 ? 'critical' : 'ok');

        } catch (\Throwable $e) {
            $items[] = $this->item('Engine Health', 'ERROR', 'critical', $e->getMessage());
        }

        return $items;
    }

    // ------------------------------------------------------------------
    // Financial Health
    // ------------------------------------------------------------------

    public function financialHealth(): array
    {
        $items = [];

        try {
            $dupPayfast = DB::table('transactions_pf')
                ->select('pf_payment_id', DB::raw('COUNT(*) as c'))
                ->whereNotNull('pf_payment_id')
                ->groupBy('pf_payment_id')
                ->havingRaw('COUNT(*) > 1')
                ->count();
            $items[] = $this->item('Duplicate PayFast IDs', (string)$dupPayfast,
                $dupPayfast > 0 ? 'critical' : 'ok',
                $dupPayfast > 0 ? 'Run: data:cleanup-duplicate-payfast-ids --dry-run' : null);
        } catch (\Throwable $e) {
            $items[] = $this->item('Duplicate PayFast IDs', 'ERROR', 'critical', $e->getMessage());
        }

        try {
            $negWallets = DB::table('wallet_transactions as wt')
                ->join('wallets as w', 'wt.wallet_id', '=', 'w.id')
                ->select('w.id')
                ->groupBy('w.id')
                ->havingRaw('SUM(CASE WHEN wt.type = "credit" THEN wt.amount ELSE -wt.amount END) < 0')
                ->get()->count();
            $items[] = $this->item('Negative Wallet Balances', (string)$negWallets,
                $negWallets > 0 ? 'critical' : 'ok');
        } catch (\Throwable $e) {
            $items[] = $this->item('Negative Wallet Balances', 'ERROR', 'warn', $e->getMessage());
        }

        try {
            $pendingRefunds = DB::table('category_event_registrations')
                ->where('refund_status', 'pending')->count();
            $items[] = $this->item('Pending Refunds', (string)$pendingRefunds,
                $pendingRefunds > 10 ? 'warn' : 'ok');
        } catch (\Throwable $e) {
            $items[] = $this->item('Pending Refunds', 'ERROR', 'warn', $e->getMessage());
        }

        try {
            $refundNoWithdrawal = DB::table('category_event_registrations')
                ->where('refund_status', 'pending')
                ->whereNotIn('registration_id', DB::table('withdrawals')->whereNotNull('registration_id')->pluck('registration_id'))
                ->count();
            $items[] = $this->item('Refunds Without Withdrawal', (string)$refundNoWithdrawal,
                $refundNoWithdrawal > 0 ? 'warn' : 'ok',
                $refundNoWithdrawal > 0 ? 'Run: data:cleanup-refund-without-withdrawal' : null);
        } catch (\Throwable $e) {
            $items[] = $this->item('Refunds Without Withdrawal', 'ERROR', 'warn', $e->getMessage());
        }

        return $items;
    }

    // ------------------------------------------------------------------
    // Draw Health
    // ------------------------------------------------------------------

    public function drawHealth(): array
    {
        $items = [];

        try {
            $orphans = DB::table('fixtures as f')
                ->leftJoin('draws as d', 'f.draw_id', '=', 'd.id')
                ->whereNull('d.id')->count();
            $items[] = $this->item('Orphan Fixtures', (string)$orphans,
                $orphans > 0 ? 'warn' : 'ok',
                $orphans > 0 ? 'Run: data:cleanup-orphan-fixtures --dry-run' : null);
        } catch (\Throwable $e) {
            $items[] = $this->item('Orphan Fixtures', 'ERROR', 'warn', $e->getMessage());
        }

        try {
            $dupResults = DB::table('fixture_results')
                ->select('fixture_id', 'set_nr', DB::raw('COUNT(*) as c'))
                ->groupBy('fixture_id', 'set_nr')
                ->havingRaw('COUNT(*) > 1')
                ->count();
            $items[] = $this->item('Duplicate Fixture Results', (string)$dupResults,
                $dupResults > 0 ? 'warn' : 'ok',
                $dupResults > 0 ? 'Run: data:cleanup-duplicate-fixture-results --dry-run' : null);
        } catch (\Throwable $e) {
            $items[] = $this->item('Duplicate Fixture Results', 'ERROR', 'warn', $e->getMessage());
        }

        try {
            $unpublishedLocked = DB::table('draws')
                ->where('locked', 1)->where('published', 0)->count();
            $items[] = $this->item('Unpublished Locked Draws', (string)$unpublishedLocked,
                $unpublishedLocked > 0 ? 'warn' : 'ok');
        } catch (\Throwable $e) {
            $items[] = $this->item('Unpublished Locked Draws', 'ERROR', 'warn', $e->getMessage());
        }

        try {
            $brokenParent = DB::table('fixtures as f')
                ->whereNotNull('f.parent_fixture_id')
                ->whereNotIn('f.parent_fixture_id', DB::table('fixtures')->select('id'))
                ->count();
            $items[] = $this->item('Broken Parent References', (string)$brokenParent,
                $brokenParent > 0 ? 'critical' : 'ok');
        } catch (\Throwable $e) {
            $items[] = $this->item('Broken Parent References', 'ERROR', 'critical', $e->getMessage());
        }

        return $items;
    }

    // ------------------------------------------------------------------
    // Registration Health
    // ------------------------------------------------------------------

    public function registrationHealth(): array
    {
        $items = [];

        try {
            $dupCer = DB::table('category_event_registrations')
                ->select('category_event_id', 'registration_id', DB::raw('COUNT(*) as c'))
                ->where('status', 'active')->whereNull('deleted_at')
                ->groupBy('category_event_id', 'registration_id')
                ->havingRaw('COUNT(*) > 1')
                ->count();
            $items[] = $this->item('Duplicate Active CERs', (string)$dupCer,
                $dupCer > 0 ? 'warn' : 'ok',
                $dupCer > 0 ? 'Run: data:cleanup-duplicate-registrations --dry-run' : null);
        } catch (\Throwable $e) {
            $items[] = $this->item('Duplicate Active CERs', 'ERROR', 'warn', $e->getMessage());
        }

        try {
            $existingCeIds = DB::table('category_events')->pluck('id');
            $orphanCer = DB::table('category_event_registrations')
                ->whereNotIn('category_event_id', $existingCeIds)
                ->whereNull('deleted_at')->count();
            $items[] = $this->item('Orphan Registrations', (string)$orphanCer,
                $orphanCer > 0 ? 'warn' : 'ok',
                $orphanCer > 0 ? 'Run: data:cleanup-orphan-registrations --dry-run' : null);
        } catch (\Throwable $e) {
            $items[] = $this->item('Orphan Registrations', 'ERROR', 'warn', $e->getMessage());
        }

        try {
            $withdrawnNoDel = DB::table('category_event_registrations')
                ->where('status', 'withdrawn')->whereNull('deleted_at')->count();
            $items[] = $this->item('Withdrawn Without deleted_at', (string)$withdrawnNoDel,
                $withdrawnNoDel > 0 ? 'warn' : 'ok',
                $withdrawnNoDel > 0 ? 'Run: data:cleanup-withdrawn-softdeletes --dry-run' : null);
        } catch (\Throwable $e) {
            $items[] = $this->item('Withdrawn Without deleted_at', 'ERROR', 'warn', $e->getMessage());
        }

        return $items;
    }

    // ------------------------------------------------------------------
    // Queue Health
    // ------------------------------------------------------------------

    public function queueHealth(): array
    {
        $items = [];

        try {
            $failed = DB::table('failed_jobs')->count();
            $items[] = $this->item('Failed Jobs', (string)$failed,
                $failed > 10 ? 'critical' : ($failed > 0 ? 'warn' : 'ok'));
        } catch (\Throwable $e) {
            $items[] = $this->item('Failed Jobs', 'n/a', 'ok', 'failed_jobs table may not exist');
        }

        try {
            $connection = config('queue.default');
            $isSync     = $connection === 'sync';
            $isProd     = app()->environment('production');
            $items[] = $this->item('Queue Driver', $connection,
                ($isSync && $isProd) ? 'critical' : 'ok',
                $isSync && $isProd ? 'sync driver in production — jobs run inline, no worker' : null);
        } catch (\Throwable $e) {
            $items[] = $this->item('Queue Driver', 'ERROR', 'warn', $e->getMessage());
        }

        return $items;
    }

    // ------------------------------------------------------------------
    // System Health
    // ------------------------------------------------------------------

    public function systemHealth(): array
    {
        $items = [];

        // Pending migrations
        try {
            $output = new \Symfony\Component\Console\Output\BufferedOutput();
            Artisan::call('migrate:status', ['--no-ansi' => true], $output);
            $migOut  = $output->fetch();
            $pending = preg_match_all('/\bPending\s*$/mi', $migOut);
            $items[] = $this->item('Pending Migrations', (string)$pending,
                $pending > 0 ? 'critical' : 'ok');
        } catch (\Throwable $e) {
            $items[] = $this->item('Pending Migrations', 'ERROR', 'warn', $e->getMessage());
        }

        // Cache
        try {
            $key   = '_health_' . time();
            Cache::put($key, 'ok', 5);
            $read  = Cache::get($key);
            Cache::forget($key);
            $items[] = $this->item('Cache (' . config('cache.default') . ')',
                $read === 'ok' ? 'OK' : 'FAIL',
                $read === 'ok' ? 'ok' : 'critical');
        } catch (\Throwable $e) {
            $items[] = $this->item('Cache', 'ERROR', 'critical', $e->getMessage());
        }

        // Environment
        $debug = config('app.debug');
        $env   = app()->environment();
        $items[] = $this->item('Environment', strtoupper($env),
            ($debug && $env === 'production') ? 'critical' : 'ok',
            $debug && $env === 'production' ? 'APP_DEBUG=true in production!' : null);

        // DB connectivity
        try {
            DB::select('SELECT 1');
            $items[] = $this->item('Database', 'OK', 'ok');
        } catch (\Throwable $e) {
            $items[] = $this->item('Database', 'FAIL', 'critical', $e->getMessage());
        }

        return $items;
    }

    // ------------------------------------------------------------------
    // Summary
    // ------------------------------------------------------------------

    public function summary(): array
    {
        $all = array_merge(
            $this->engineHealth(),
            $this->financialHealth(),
            $this->drawHealth(),
            $this->registrationHealth(),
            $this->queueHealth(),
            $this->systemHealth(),
        );

        $critical = collect($all)->where('status', 'critical')->count();
        $warn     = collect($all)->where('status', 'warn')->count();
        $ok       = collect($all)->where('status', 'ok')->count();

        return compact('critical', 'warn', 'ok');
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function item(string $label, string $value, string $status, ?string $detail = null): array
    {
        return compact('label', 'value', 'status', 'detail');
    }
}
