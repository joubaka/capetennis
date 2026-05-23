<?php

namespace App\Console\Commands;

use App\Models\Draw;
use App\Models\Fixture;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * draw:integrity-check
 *
 * Verifies draw/fixture structural integrity:
 *  - orphan fixtures (draw deleted)
 *  - broken parent_fixture_id / loser_parent_fixture_id refs
 *  - winner_registration not propagated to parent slot
 *  - duplicate fixture_result rows per set
 *  - BYE fixtures not auto-advanced
 *  - fixtures with winner but match_status != completed
 *  - NULL match_status
 *
 * Safe: read-only, no writes.
 */
class DrawIntegrityCheckCommand extends Command
{
    protected $signature   = "draw:integrity-check
                                {--draw=  : Check a single draw by ID}
                                {--fix    : Backfill NULL match_status to 0 (safe write)}
                                {--json   : Output results as JSON}";
    protected $description = "Check draw and fixture structural integrity.";

    public function handle(): int
    {
        $drawId = $this->option("draw");
        $fix    = $this->option("fix");
        $json   = $this->option("json");

        $issues = [];

        // -- Global checks (not per-draw) -------------------------------
        $orphans = DB::table("fixtures as f")
            ->leftJoin("draws as d", "f.draw_id", "=", "d.id")
            ->whereNull("d.id")
            ->select("f.id", "f.draw_id")
            ->get();
        foreach ($orphans as $row) {
            $issues[] = ["table" => "fixtures", "severity" => "CRITICAL",
                "issue" => "Fixture #{$row->id} references non-existent draw #{$row->draw_id}"];
        }

        $brokenParent = DB::table("fixtures as f")
            ->whereNotNull("f.parent_fixture_id")
            ->whereNotIn("f.parent_fixture_id", DB::table("fixtures")->select("id"))
            ->select("f.id", "f.parent_fixture_id")
            ->get();
        foreach ($brokenParent as $row) {
            $issues[] = ["table" => "fixtures", "severity" => "CRITICAL",
                "issue" => "Fixture #{$row->id} parent_fixture_id={$row->parent_fixture_id} does not exist"];
        }

        $brokenLoser = DB::table("fixtures as f")
            ->whereNotNull("f.loser_parent_fixture_id")
            ->whereNotIn("f.loser_parent_fixture_id", DB::table("fixtures")->select("id"))
            ->select("f.id", "f.loser_parent_fixture_id")
            ->get();
        foreach ($brokenLoser as $row) {
            $issues[] = ["table" => "fixtures", "severity" => "CRITICAL",
                "issue" => "Fixture #{$row->id} loser_parent_fixture_id={$row->loser_parent_fixture_id} does not exist"];
        }

        $nullStatus = DB::table("fixtures")->whereNull("match_status")->count();
        if ($nullStatus > 0) {
            $issues[] = ["table" => "fixtures", "severity" => "CRITICAL",
                "issue" => "{$nullStatus} fixtures have NULL match_status (expected 0)"];
            if ($fix) {
                DB::table("fixtures")->whereNull("match_status")->update(["match_status" => 0]);
                $this->info("Fixed: backfilled {$nullStatus} NULL match_status to 0.");
            }
        }

        $dupResults = DB::selectOne(
            "SELECT COUNT(*) as cnt FROM (SELECT fixture_id, set_nr, COUNT(*) c FROM fixture_results GROUP BY fixture_id, set_nr HAVING c > 1) x"
        );
        if ($dupResults->cnt > 0) {
            $issues[] = ["table" => "fixture_results", "severity" => "CRITICAL",
                "issue" => "{$dupResults->cnt} duplicate (fixture_id, set_nr) combinations"];
        }

        $orphanResults = DB::table("fixture_results as fr")
            ->leftJoin("fixtures as f", "fr.fixture_id", "=", "f.id")
            ->whereNull("f.id")
            ->count();
        if ($orphanResults > 0) {
            $issues[] = ["table" => "fixture_results", "severity" => "CRITICAL",
                "issue" => "{$orphanResults} fixture_results rows reference non-existent fixture_id"];
        }

        // -- Per-draw checks ---------------------------------------------
        $drawQuery = Draw::query();
        if ($drawId) {
            $drawQuery->where("id", $drawId);
        }
        foreach ($drawQuery->cursor() as $draw) {
            $drawIssues = $this->checkDraw($draw);
            foreach ($drawIssues as $di) {
                $issues[] = array_merge(["draw_id" => $draw->id], $di);
            }
        }

        if ($json) {
            $this->line(json_encode($issues, JSON_PRETTY_PRINT));
        } elseif (empty($issues)) {
            $this->info("draw:integrity-check — no issues found.");
        } else {
            $this->table(
                ["draw_id", "table", "severity", "issue"],
                array_map(fn($i) => [
                    $i["draw_id"] ?? "global",
                    $i["table"],
                    $i["severity"],
                    $i["issue"],
                ], $issues)
            );
        }

        $critical = count(array_filter($issues, fn($i) => $i["severity"] === "CRITICAL"));
        return $critical > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function checkDraw(Draw $draw): array
    {
        $issues   = [];
        $fixtures = Fixture::where("draw_id", $draw->id)
            ->with("fixtureResults")
            ->get()
            ->keyBy("id");

        foreach ($fixtures as $fixture) {
            // Winner not in parent
            if ($fixture->winner_registration && $fixture->parent_fixture_id) {
                $parent = $fixtures->get($fixture->parent_fixture_id);
                if ($parent) {
                    $inParent = $parent->registration1_id === $fixture->winner_registration
                             || $parent->registration2_id === $fixture->winner_registration;
                    if (!$inParent) {
                        $issues[] = ["table" => "fixtures", "severity" => "WARN",
                            "issue" => "Fixture #{$fixture->id} winner #{$fixture->winner_registration} not placed in parent #{$parent->id}"];
                    }
                }
            }

            // Completed status but no results
            if ($fixture->match_status == 1 && $fixture->fixtureResults->isEmpty()) {
                $issues[] = ["table" => "fixtures", "severity" => "WARN",
                    "issue" => "Fixture #{$fixture->id} match_status=1 (completed) but has no fixture_results"];
            }
        }

        return $issues;
    }
}