<?php

namespace Tests\Feature\Cleanup;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * ProductionDataCleanupTest
 *
 * Covers all 7 data:cleanup-* artisan commands.
 *
 * Every test follows the same invariants:
 *  1. --dry-run never mutates data.
 *  2. --confirm is required for destructive actions; omitting it fails.
 *  3. --limit=N caps rows processed.
 *  4. --export=<path> creates a CSV file with a header row.
 *  5. PayFast duplicates are never auto-deleted even with --confirm.
 *  6. Duplicate fixture_results keeps the highest-id row.
 */
class ProductionDataCleanupTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function makeUser(): object
    {
        return DB::table("users")->insertGetId([
            "name"              => "Test User",
            "email"             => "test_" . uniqid() . "@example.com",
            "password"          => bcrypt("secret"),
            "created_at"        => now(),
            "updated_at"        => now(),
        ]);
    }

    private function makeDraw(): int
    {
        return DB::table("draws")->insertGetId([
            "drawName"            => "Test Draw",
            "event_id"            => 1,
            "category_event_id"   => 1,
            "published"           => 0,
            "locked"              => 0,
            "created_at"          => now(),
            "updated_at"          => now(),
        ]);
    }

    private function makeFixture(int $drawId): int
    {
        return DB::table("fixtures")->insertGetId([
            "draw_id"      => $drawId,
            "match_status" => 0,
            "round"        => 1,
            "created_at"   => now(),
            "updated_at"   => now(),
        ]);
    }

    private function makeFixtureResult(int $fixtureId, ?int $setNr = null): int
    {
        return DB::table("fixture_results")->insertGetId([
            "fixture_id" => $fixtureId,
            "set_nr"     => $setNr,
            "created_at" => now(),
            "updated_at" => now(),
        ]);
    }

    private function makeCer(array $overrides = []): int
    {
        return DB::table("category_event_registrations")->insertGetId(array_merge([
            "category_event_id" => 1,
            "registration_id"   => 1,
            "status"            => "active",
            "refund_status"     => "not_refunded",
            "created_at"        => now(),
            "updated_at"        => now(),
        ], $overrides));
    }

    private function makePfTransaction(string $pfPaymentId, array $overrides = []): int
    {
        return DB::table("transactions_pf")->insertGetId(array_merge([
            "pf_payment_id" => $pfPaymentId,
            "player_id"     => 1,
            "event_id"      => 1,
            "amount_gross"  => 100.00,
            "created_at"    => now(),
            "updated_at"    => now(),
        ], $overrides));
    }

    // =================================================================
    // 1. data:cleanup-duplicate-payfast-ids
    // =================================================================

    /** @test */
    public function payfast_dry_run_changes_nothing(): void
    {
        $this->makePfTransaction("PF_TEST_001");
        $this->makePfTransaction("PF_TEST_001"); // duplicate

        $before = DB::table("transactions_pf")->count();

        $this->artisan("data:cleanup-duplicate-payfast-ids --dry-run")
             ->assertSuccessful();

        $this->assertSame($before, DB::table("transactions_pf")->count());
    }

    /** @test */
    public function payfast_confirm_still_does_not_delete(): void
    {
        $this->makePfTransaction("PF_SAFE_001");
        $this->makePfTransaction("PF_SAFE_001");

        $before = DB::table("transactions_pf")->count();

        $this->artisan("data:cleanup-duplicate-payfast-ids --confirm")
             ->assertSuccessful();

        // Financial records MUST NOT be auto-deleted
        $this->assertSame($before, DB::table("transactions_pf")->count());
    }

    /** @test */
    public function payfast_export_creates_csv(): void
    {
        $this->makePfTransaction("PF_EXPORT_001");
        $this->makePfTransaction("PF_EXPORT_001");

        $path = tempnam(sys_get_temp_dir(), "dup_payfast_") . ".csv";

        $this->artisan("data:cleanup-duplicate-payfast-ids", ["--dry-run" => true, "--export" => $path])
             ->assertSuccessful();

        $this->assertFileExists($path);
        $lines = file($path);
        $this->assertGreaterThan(1, count($lines)); // header + at least 1 data row
        @unlink($path);
    }

    /** @test */
    public function payfast_limit_caps_scanned_rows(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->makePfTransaction("PF_LIMIT_001");
        }

        $path = tempnam(sys_get_temp_dir(), "payfast_limit_") . ".csv";

        $this->artisan("data:cleanup-duplicate-payfast-ids", ["--dry-run" => true, "--limit" => 2, "--export" => $path])
             ->assertSuccessful();

        $this->assertFileExists($path);
        $lines = array_filter(file($path)); // remove blanks
        // header + 2 data rows
        $this->assertSame(3, count($lines));
        @unlink($path);
    }

    // =================================================================
    // 2. data:cleanup-duplicate-registrations
    // =================================================================

    /** @test */
    public function dup_registrations_dry_run_changes_nothing(): void
    {
        $this->makeCer(["category_event_id" => 999, "registration_id" => 888]);
        $this->makeCer(["category_event_id" => 999, "registration_id" => 888]);

        $before = DB::table("category_event_registrations")->count();

        $this->artisan("data:cleanup-duplicate-registrations --dry-run")
             ->assertSuccessful();

        $this->assertSame($before, DB::table("category_event_registrations")->count());
    }

    /** @test */
    public function dup_registrations_requires_confirm(): void
    {
        $this->makeCer(["category_event_id" => 998, "registration_id" => 887]);
        $this->makeCer(["category_event_id" => 998, "registration_id" => 887]);

        $this->artisan("data:cleanup-duplicate-registrations")
             ->assertFailed();
    }

    /** @test */
    public function dup_registrations_confirm_soft_deletes_older_row(): void
    {
        $older  = $this->makeCer(["category_event_id" => 997, "registration_id" => 886]);
        $newer  = $this->makeCer(["category_event_id" => 997, "registration_id" => 886]);

        $this->artisan("data:cleanup-duplicate-registrations --confirm")
             ->assertSuccessful();

        // Older row should now be withdrawn + soft-deleted
        $olderRow = DB::table("category_event_registrations")->find($older);
        $this->assertSame("withdrawn",  $olderRow->status);
        $this->assertNotNull($olderRow->deleted_at);

        // Newer row untouched
        $newerRow = DB::table("category_event_registrations")->find($newer);
        $this->assertSame("active", $newerRow->status);
        $this->assertNull($newerRow->deleted_at);
    }

    /** @test */
    public function dup_registrations_export_creates_csv(): void
    {
        $this->makeCer(["category_event_id" => 996, "registration_id" => 885]);
        $this->makeCer(["category_event_id" => 996, "registration_id" => 885]);

        $path = tempnam(sys_get_temp_dir(), "dup_cer_") . ".csv";

        $this->artisan("data:cleanup-duplicate-registrations", ["--dry-run" => true, "--export" => $path])
             ->assertSuccessful();

        $this->assertFileExists($path);
        @unlink($path);
    }

    // =================================================================
    // 3. data:cleanup-duplicate-fixture-results
    // =================================================================

    /** @test */
    public function dup_fixture_results_dry_run_changes_nothing(): void
    {
        $drawId    = $this->makeDraw();
        $fixtureId = $this->makeFixture($drawId);
        $this->makeFixtureResult($fixtureId, null);
        $this->makeFixtureResult($fixtureId, null);

        $before = DB::table("fixture_results")->count();

        $this->artisan("data:cleanup-duplicate-fixture-results --dry-run")
             ->assertSuccessful();

        $this->assertSame($before, DB::table("fixture_results")->count());
    }

    /** @test */
    public function dup_fixture_results_requires_confirm(): void
    {
        $drawId    = $this->makeDraw();
        $fixtureId = $this->makeFixture($drawId);
        $this->makeFixtureResult($fixtureId, 1);
        $this->makeFixtureResult($fixtureId, 1);

        $this->artisan("data:cleanup-duplicate-fixture-results")
             ->assertFailed();
    }

    /** @test */
    public function dup_fixture_results_keeps_highest_id_row(): void
    {
        $drawId    = $this->makeDraw();
        $fixtureId = $this->makeFixture($drawId);
        $firstId   = $this->makeFixtureResult($fixtureId, null);
        $secondId  = $this->makeFixtureResult($fixtureId, null); // higher id = keep

        $this->artisan("data:cleanup-duplicate-fixture-results --confirm")
             ->assertSuccessful();

        $this->assertDatabaseMissing("fixture_results", ["id" => $firstId]);
        $this->assertDatabaseHas("fixture_results",     ["id" => $secondId]);
    }

    /** @test */
    public function dup_fixture_results_export_creates_csv(): void
    {
        $drawId    = $this->makeDraw();
        $fixtureId = $this->makeFixture($drawId);
        $this->makeFixtureResult($fixtureId, 2);
        $this->makeFixtureResult($fixtureId, 2);

        $path = tempnam(sys_get_temp_dir(), "dup_results_") . ".csv";

        $this->artisan("data:cleanup-duplicate-fixture-results", ["--dry-run" => true, "--export" => $path])
             ->assertSuccessful();

        $this->assertFileExists($path);
        @unlink($path);
    }

    // =================================================================
    // 4. data:cleanup-orphan-registrations
    // =================================================================

    /** @test */
    public function orphan_registrations_dry_run_changes_nothing(): void
    {
        // category_event_id=99999 almost certainly doesn't exist in test DB
        $this->makeCer(["category_event_id" => 99999]);

        $before = DB::table("category_event_registrations")->count();

        $this->artisan("data:cleanup-orphan-registrations --dry-run")
             ->assertSuccessful();

        $this->assertSame($before, DB::table("category_event_registrations")->count());
    }

    /** @test */
    public function orphan_registrations_requires_confirm(): void
    {
        $this->makeCer(["category_event_id" => 99998]);

        $this->artisan("data:cleanup-orphan-registrations")
             ->assertFailed();
    }

    /** @test */
    public function orphan_registrations_skips_rows_with_payment(): void
    {
        $id = $this->makeCer([
            "category_event_id" => 99997,
            "pf_transaction_id" => "PF_123",
        ]);

        $this->artisan("data:cleanup-orphan-registrations --confirm")
             ->assertSuccessful();

        // Must not be soft-deleted — has payment dependency
        $row = DB::table("category_event_registrations")->find($id);
        $this->assertNull($row->deleted_at);
    }

    /** @test */
    public function orphan_registrations_export_creates_csv(): void
    {
        $this->makeCer(["category_event_id" => 99996]);

        $path = tempnam(sys_get_temp_dir(), "orphan_cer_") . ".csv";

        $this->artisan("data:cleanup-orphan-registrations", ["--dry-run" => true, "--export" => $path])
             ->assertSuccessful();

        $this->assertFileExists($path);
        @unlink($path);
    }

    // =================================================================
    // 5. data:cleanup-withdrawn-softdeletes
    // =================================================================

    /** @test */
    public function withdrawn_softdeletes_dry_run_changes_nothing(): void
    {
        $this->makeCer(["status" => "withdrawn", "deleted_at" => null]);

        $before = DB::table("category_event_registrations")
            ->where("status", "withdrawn")->whereNull("deleted_at")->count();

        $this->artisan("data:cleanup-withdrawn-softdeletes --dry-run")
             ->assertSuccessful();

        $this->assertSame(
            $before,
            DB::table("category_event_registrations")
                ->where("status", "withdrawn")->whereNull("deleted_at")->count()
        );
    }

    /** @test */
    public function withdrawn_softdeletes_requires_confirm(): void
    {
        $this->makeCer(["status" => "withdrawn", "deleted_at" => null]);

        $this->artisan("data:cleanup-withdrawn-softdeletes")
             ->assertFailed();
    }

    /** @test */
    public function withdrawn_softdeletes_sets_deleted_at(): void
    {
        $id = $this->makeCer(["status" => "withdrawn", "deleted_at" => null]);

        $this->artisan("data:cleanup-withdrawn-softdeletes --confirm")
             ->assertSuccessful();

        $row = DB::table("category_event_registrations")->find($id);
        $this->assertNotNull($row->deleted_at);
    }

    /** @test */
    public function withdrawn_softdeletes_limit_works(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->makeCer(["status" => "withdrawn", "deleted_at" => null]);
        }

        $this->artisan("data:cleanup-withdrawn-softdeletes --confirm --limit=1")
             ->assertSuccessful();

        $remaining = DB::table("category_event_registrations")
            ->where("status", "withdrawn")->whereNull("deleted_at")->count();

        $this->assertSame(2, $remaining);
    }

    // =================================================================
    // 6. data:cleanup-refund-without-withdrawal
    // =================================================================

    /** @test */
    public function refund_no_withdrawal_dry_run_changes_nothing(): void
    {
        $this->makeCer(["refund_status" => "pending", "registration_id" => 99991]);

        $before = DB::table("category_event_registrations")
            ->where("refund_status", "pending")->count();

        $this->artisan("data:cleanup-refund-without-withdrawal --dry-run")
             ->assertSuccessful();

        $this->assertSame(
            $before,
            DB::table("category_event_registrations")
                ->where("refund_status", "pending")->count()
        );
    }

    /** @test */
    public function refund_no_withdrawal_never_auto_deletes(): void
    {
        $id = $this->makeCer(["refund_status" => "pending", "registration_id" => 99990]);

        $before = DB::table("category_event_registrations")->count();

        $this->artisan("data:cleanup-refund-without-withdrawal --confirm")
             ->assertSuccessful();

        $this->assertSame($before, DB::table("category_event_registrations")->count());
        $this->assertDatabaseHas("category_event_registrations", ["id" => $id]);
    }

    /** @test */
    public function refund_no_withdrawal_export_creates_csv(): void
    {
        $this->makeCer(["refund_status" => "pending", "registration_id" => 99989]);

        $path = tempnam(sys_get_temp_dir(), "refund_no_wd_") . ".csv";

        $this->artisan("data:cleanup-refund-without-withdrawal", ["--dry-run" => true, "--export" => $path])
             ->assertSuccessful();

        $this->assertFileExists($path);
        @unlink($path);
    }

    // =================================================================
    // 7. data:cleanup-orphan-fixtures
    // =================================================================

    /** @test */
    public function orphan_fixtures_dry_run_changes_nothing(): void
    {
        // Insert fixture with a draw_id that doesn't exist
        $fixtureId = DB::table("fixtures")->insertGetId([
            "draw_id"      => 999999,
            "match_status" => 0,
            "round"        => 1,
            "created_at"   => now(),
            "updated_at"   => now(),
        ]);
        $this->makeFixtureResult($fixtureId);

        $before = DB::table("fixtures")->count();

        $this->artisan("data:cleanup-orphan-fixtures --dry-run")
             ->assertSuccessful();

        $this->assertSame($before, DB::table("fixtures")->count());
    }

    /** @test */
    public function orphan_fixtures_requires_confirm(): void
    {
        DB::table("fixtures")->insertGetId([
            "draw_id"      => 999998,
            "match_status" => 0,
            "round"        => 1,
            "created_at"   => now(),
            "updated_at"   => now(),
        ]);

        $this->artisan("data:cleanup-orphan-fixtures")
             ->assertFailed();
    }

    /** @test */
    public function orphan_fixtures_confirm_deletes_fixture_and_results(): void
    {
        $fixtureId = DB::table("fixtures")->insertGetId([
            "draw_id"      => 999997,
            "match_status" => 0,
            "round"        => 1,
            "created_at"   => now(),
            "updated_at"   => now(),
        ]);
        $resultId = $this->makeFixtureResult($fixtureId);

        $this->artisan("data:cleanup-orphan-fixtures --confirm")
             ->assertSuccessful();

        $this->assertDatabaseMissing("fixtures",        ["id" => $fixtureId]);
        $this->assertDatabaseMissing("fixture_results", ["id" => $resultId]);
    }

    /** @test */
    public function orphan_fixtures_export_creates_csv(): void
    {
        DB::table("fixtures")->insertGetId([
            "draw_id"      => 999996,
            "match_status" => 0,
            "round"        => 1,
            "created_at"   => now(),
            "updated_at"   => now(),
        ]);

        $path = tempnam(sys_get_temp_dir(), "orphan_fix_") . ".csv";

        $this->artisan("data:cleanup-orphan-fixtures", ["--dry-run" => true, "--export" => $path])
             ->assertSuccessful();

        $this->assertFileExists($path);
        @unlink($path);
    }

    /** @test */
    public function orphan_fixtures_limit_works(): void
    {
        for ($i = 0; $i < 3; $i++) {
            DB::table("fixtures")->insertGetId([
                "draw_id"      => 888880 + $i,
                "match_status" => 0,
                "round"        => 1,
                "created_at"   => now(),
                "updated_at"   => now(),
            ]);
        }

        $before = DB::table("fixtures")->count();

        $this->artisan("data:cleanup-orphan-fixtures --confirm --limit=1")
             ->assertSuccessful();

        $this->assertSame($before - 1, DB::table("fixtures")->count());
    }
}
