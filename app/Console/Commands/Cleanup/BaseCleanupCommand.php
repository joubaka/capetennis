<?php

namespace App\Console\Commands\Cleanup;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/**
 * BaseCleanupCommand
 *
 * Shared scaffolding for all production data cleanup commands.
 *
 * Features every subclass gets for free:
 *  - --dry-run  : never mutates data, reports what would change
 *  - --confirm  : required gate for any destructive action
 *  - --limit=N  : cap rows processed in one run
 *  - --export=  : write a CSV of affected rows to this path
 *
 * Subclasses must implement:
 *  @see scan()    – return collection of rows that need attention
 *  @see fix()     – apply the mutation to one row
 *  @see headers() – CSV column headers
 *  @see rowToCsv() – map a row to CSV line array
 */
abstract class BaseCleanupCommand extends Command
{
    // ---------------------------------------------------------------
    // Shared option string – prepend to $signature in every subclass:
    //   "{--dry-run} {--confirm} {--limit=0} {--export=}"
    // ---------------------------------------------------------------

    /** Scan for affected rows. Return a Collection or array. */
    abstract protected function scan(): iterable;

    /**
     * Apply the fix to one row.
     * Only called when --dry-run is NOT set and --confirm WAS given.
     */
    abstract protected function fix(object $row): void;

    /** CSV column headers. */
    abstract protected function headers(): array;

    /** Map a scanned row to a CSV row array (same order as headers). */
    abstract protected function rowToCsv(object $row): array;

    // ---------------------------------------------------------------
    // Helpers available to subclasses
    // ---------------------------------------------------------------

    protected function isDryRun(): bool
    {
        return (bool) $this->option("dry-run");
    }

    protected function isConfirmed(): bool
    {
        return (bool) $this->option("confirm");
    }

    protected function limit(): int
    {
        return max(0, (int) $this->option("limit"));
    }

    protected function exportPath(): ?string
    {
        $p = $this->option("export");
        return ($p && $p !== "") ? $p : null;
    }

    /**
     * Main execution loop shared by all cleanup commands.
     * Subclasses call this from handle() after any command-specific setup.
     */
    protected function runCleanup(string $label): int
    {
        $dryRun  = $this->isDryRun();
        $limit   = $this->limit();
        $export  = $this->exportPath();

        $this->line("");
        $this->info("[{$label}] Starting" . ($dryRun ? " — DRY RUN (no changes will be made)" : ""));
        $this->line("  Timestamp : " . Carbon::now()->toDateTimeString());

        // Gate: require --confirm for destructive runs
        if (!$dryRun && !$this->isConfirmed()) {
            $this->error("This command makes destructive changes. Re-run with --confirm to proceed.");
            $this->line("Tip: use --dry-run first to preview affected rows.");
            return self::FAILURE;
        }

        $rows = collect($this->scan());

        if ($limit > 0) {
            $rows = $rows->take($limit);
        }

        $total    = $rows->count();
        $affected = 0;
        $skipped  = 0;
        $csvLines = [];

        $this->line("  Rows found: {$total}" . ($limit > 0 ? " (limited to {$limit})" : ""));

        foreach ($rows as $row) {
            $csvLines[] = $this->rowToCsv($row);

            if ($dryRun) {
                $skipped++;
                continue;
            }

            try {
                $this->fix($row);
                $affected++;
            } catch (\Throwable $e) {
                $this->warn("  SKIP row (error): " . $e->getMessage());
                $skipped++;
            }
        }

        // Export CSV
        if ($export !== null) {
            $this->writeCsv($export, $this->headers(), $csvLines);
            $this->line("  Exported  : {$export}");
        }

        $this->line("");
        $this->table(
            ["Metric", "Count"],
            [
                ["Scanned",  $total],
                ["Affected", $affected],
                ["Skipped",  $skipped],
                ["Dry-run",  $dryRun ? "YES" : "no"],
            ]
        );

        if ($dryRun && $total > 0) {
            $this->warn("Dry-run complete. Re-run with --confirm to apply changes.");
        } elseif ($affected > 0) {
            $this->info("Done. {$affected} rows updated.");
        } else {
            $this->info("Nothing to do.");
        }

        return self::SUCCESS;
    }

    private function writeCsv(string $path, array $headers, array $rows): void
    {
        $dir = dirname($path);
        if ($dir && !is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $fp = fopen($path, "w");
        fputcsv($fp, $headers);
        foreach ($rows as $row) {
            fputcsv($fp, $row);
        }
        fclose($fp);
    }
}
