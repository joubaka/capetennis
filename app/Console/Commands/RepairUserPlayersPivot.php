<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * RepairUserPlayersPivot
 *
 * Background
 * ----------
 * The system has two parallel ownership mechanisms for players:
 *
 *   1. user_players (pivot)  — created when a player is registered via the normal flow.
 *   2. players.userId (FK)   — a legacy/direct link set when a player is created or migrated.
 *
 * The frontend registration permission check in payNowPayfast() only checked the pivot,
 * so any player whose link existed only on players.userId was denied with:
 *   "You do not have permission to register player ID {X}."
 *
 * This command back-fills the user_players pivot for every player that has a userId FK
 * set but no corresponding pivot row, resolving the permission denial for all affected
 * players in one shot.
 *
 * It is safe to re-run: it uses INSERT IGNORE so existing rows are never duplicated.
 *
 * Usage
 * -----
 *   php artisan repair:user-players-pivot          — dry run (shows count only)
 *   php artisan repair:user-players-pivot --fix     — applies the repair
 */
class RepairUserPlayersPivot extends Command
{
    protected $signature = 'repair:user-players-pivot
                            {--fix : Actually insert the missing rows (omit for dry-run)}';

    protected $description = 'Back-fill user_players pivot rows for players that have players.userId set but no pivot row.';

    public function handle(): int
    {
        $orphans = DB::select(
            'SELECT p.id AS player_id, p.userId AS user_id, p.name, p.surname
             FROM players p
             WHERE p.userId IS NOT NULL
               AND NOT EXISTS (
                   SELECT 1 FROM user_players up
                   WHERE up.player_id = p.id
                     AND up.user_id   = p.userId
               )
             ORDER BY p.userId, p.id'
        );

        $count = count($orphans);

        if ($count === 0) {
            $this->info('✅ No missing user_players rows found. Nothing to do.');
            return self::SUCCESS;
        }

        $this->warn("⚠️  Found {$count} player(s) with players.userId set but no user_players pivot row.");

        if (! $this->option('fix')) {
            $this->table(
                ['player_id', 'user_id', 'name', 'surname'],
                array_slice(
                    array_map(fn($r) => [(string)$r->player_id, (string)$r->user_id, $r->name, $r->surname], $orphans),
                    0,
                    50
                )
            );

            if ($count > 50) {
                $this->line("   … and " . ($count - 50) . " more (run with --fix to repair all).");
            }

            $this->line('');
            $this->line('Run with --fix to insert the missing rows:');
            $this->line('   php artisan repair:user-players-pivot --fix');
            return self::SUCCESS;
        }

        // Apply repair in batches of 500
        $now     = now()->toDateTimeString();
        $chunks  = array_chunk($orphans, 500);
        $inserted = 0;

        foreach ($chunks as $chunk) {
            $rows = array_map(fn($r) => [
                'player_id'  => $r->player_id,
                'user_id'    => $r->user_id,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk);

            DB::table('user_players')->insertOrIgnore($rows);
            $inserted += count($rows);
            $this->line("  ✓ Inserted batch of " . count($rows) . " rows…");
        }

        // Verify
        $remaining = DB::selectOne(
            'SELECT COUNT(*) AS cnt
             FROM players p
             WHERE p.userId IS NOT NULL
               AND NOT EXISTS (
                   SELECT 1 FROM user_players up
                   WHERE up.player_id = p.id
                     AND up.user_id   = p.userId
               )'
        )->cnt;

        $this->newLine();

        if ((int) $remaining === 0) {
            $this->info("✅ Repair complete. {$inserted} rows inserted into user_players. 0 orphans remaining.");
        } else {
            $this->error("⚠️  Repair partial. {$inserted} rows inserted but {$remaining} orphans still remain.");
        }

        return self::SUCCESS;
    }
}
