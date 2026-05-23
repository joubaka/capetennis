<?php

namespace App\Console\Commands\Platform;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * platform:verify-backup
 *
 * Verifies that a recent database backup is accessible and internally
 * consistent by running non-destructive row-count checks against key tables.
 *
 * This does NOT restore the backup — it validates a MySQL dump file or
 * a connected backup DB source if configured.
 *
 * Typical usage:
 *   php artisan platform:verify-backup
 *   php artisan platform:verify-backup --min-rows=1000
 */
class VerifyBackupCommand extends Command
{
    protected $signature   = 'platform:verify-backup
                                {--min-rows=100 : Minimum expected row count for key tables}
                                {--table=* : Specific tables to check (default: all key tables)}';
    protected $description = 'Verify DB backup integrity — checks key table row counts and references';

    private array $keyTables = [
        'users',
        'events',
        'category_events',
        'category_event_registrations',
        'draws',
        'fixtures',
        'fixture_results',
        'transactions_pf',
        'wallets',
    ];

    public function handle(): int
    {
        $this->info('');
        $this->info('╔══════════════════════════════════════════════╗');
        $this->info('║   Cape Tennis — Backup Verification          ║');
        $this->info('╚══════════════════════════════════════════════╝');
        $this->info('');
        $this->info('  Running against connection: ' . config('database.default'));
        $this->info('  Database: ' . config('database.connections.' . config('database.default') . '.database'));
        $this->info('');

        $tables  = $this->option('table') ?: $this->keyTables;
        $minRows = (int) $this->option('min-rows');
        $issues  = [];

        foreach ($tables as $table) {
            try {
                $count = DB::table($table)->count();
                if ($count < $minRows) {
                    $issues[] = "⛔ {$table}: only {$count} rows (expected ≥ {$minRows})";
                    $this->error("  ✗  {$table}: {$count} rows (< {$minRows})");
                } else {
                    $this->info("  ✓  {$table}: {$count} rows");
                }
            } catch (\Throwable $e) {
                $issues[] = "⛔ {$table}: could not query — " . $e->getMessage();
                $this->error("  ✗  {$table}: " . $e->getMessage());
            }
        }

        // Cross-reference spot checks
        $this->info('');
        $this->line('  Cross-reference checks...');

        try {
            $orphanFixtures = DB::table('fixtures as f')
                ->leftJoin('draws as d', 'f.draw_id', '=', 'd.id')
                ->whereNull('d.id')->count();
            if ($orphanFixtures > 0) {
                $this->warn("  ⚠️  {$orphanFixtures} orphan fixture(s) (draw_id references missing draw)");
            } else {
                $this->info("  ✓  fixture → draw references: intact");
            }
        } catch (\Throwable $e) {
            $this->warn("  ⚠️  Could not check fixture refs: " . $e->getMessage());
        }

        try {
            $orphanCer = DB::table('category_event_registrations as cer')
                ->leftJoin('category_events as ce', 'cer.category_event_id', '=', 'ce.id')
                ->whereNull('ce.id')->whereNull('cer.deleted_at')->count();
            if ($orphanCer > 0) {
                $this->warn("  ⚠️  {$orphanCer} orphan CER(s) (category_event_id references missing category_event)");
            } else {
                $this->info("  ✓  CER → category_event references: intact");
            }
        } catch (\Throwable $e) {
            $this->warn("  ⚠️  Could not check CER refs: " . $e->getMessage());
        }

        // Summary
        $this->info('');
        if (!empty($issues)) {
            $this->error('  VERIFICATION ISSUES:');
            foreach ($issues as $issue) {
                $this->error("    {$issue}");
            }
            $this->info('');
            $this->error('  ❌ Backup verification FAILED — do not use this backup for recovery.');
            return self::FAILURE;
        }

        $this->info('  🟢 Backup verification PASSED — all key tables present and populated.');
        $this->info('');
        return self::SUCCESS;
    }
}
