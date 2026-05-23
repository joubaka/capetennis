<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * schema:integrity-check
 *
 * Umbrella command: runs schema:audit, draw:integrity-check, and
 * finance:integrity-check in sequence and summarises.
 */
class SchemaIntegrityCheckCommand extends Command
{
    protected $signature   = "schema:integrity-check {--fix : Pass --fix to draw:integrity-check}";
    protected $description = "Run all schema, draw, and finance integrity checks.";

    public function handle(): int
    {
        $this->newLine();
        $this->line("<fg=cyan>-- schema:audit ----------------------------------</>");
        $r1 = $this->call("schema:audit");

        $this->newLine();
        $this->line("<fg=cyan>-- draw:integrity-check --------------------------</>");
        $r2 = $this->call("draw:integrity-check", $this->option("fix") ? ["--fix" => true] : []);

        $this->newLine();
        $this->line("<fg=cyan>-- finance:integrity-check -----------------------</>");
        $r3 = $this->call("finance:integrity-check");

        $this->newLine();
        if ($r1 === self::SUCCESS && $r2 === self::SUCCESS && $r3 === self::SUCCESS) {
            $this->info("All integrity checks passed.");
            return self::SUCCESS;
        }

        $this->warn("One or more integrity checks found issues — see above.");
        return self::FAILURE;
    }
}