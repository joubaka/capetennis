<?php

namespace Tests\Feature;

use App\Services\Performance\PlayerMembershipProfileFreshnessService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class PlayerMembershipProfileFreshnessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp(); CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-29 12:00', 'Africa/Johannesburg'));
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']); DB::purge('sqlite');
        Schema::create('players', function (Blueprint $t) { $t->integer('id')->primary(); $t->string('profile_updated_at')->nullable(); $t->string('updated_at')->nullable(); });
    }

    protected function tearDown(): void { CarbonImmutable::setTestNow(); parent::tearDown(); }

    public function test_actual_profile_date_has_inclusive_six_calendar_month_badges_without_eligibility_changes(): void
    {
        foreach ([1 => '2026-04-29 00:00:00', 2 => '2026-04-28 23:59:59', 3 => null, 4 => '2026-10-30 00:00:00'] as $id => $date) {
            DB::table('players')->insert(['id' => $id, 'profile_updated_at' => $date, 'updated_at' => '2026-10-29 11:00:00']);
        }
        $rows = array_map(fn ($id) => ['id' => $id, 'eligible' => true, 'position' => $id], range(1, 5));
        DB::enableQueryLog();
        DB::statement('PRAGMA query_only = ON');
        try { $result = app(PlayerMembershipProfileFreshnessService::class)->enrich($rows); } finally { DB::statement('PRAGMA query_only = OFF'); }
        $this->assertSame('Profile updated within 6 months', $result[0]['profile_freshness']);
        $this->assertSame('Profile not updated in last 6 months', $result[1]['profile_freshness']);
        foreach ([2, 3, 4] as $i) { $this->assertSame('Profile update date unavailable', $result[$i]['profile_freshness']); $this->assertNull($result[$i]['profile_updated_date']); }
        foreach ($result as $i => $row) { $this->assertTrue($row['eligible']); $this->assertSame($rows[$i]['position'], $row['position']); }
        $this->assertSame('2026-04-28', $result[1]['profile_updated_date']);
        $html = view('backend.player-performance.membership-preview.profile-freshness', ['player' => $result[1]])->render();
        $this->assertStringContainsString('Profile not updated in last 6 months', $html);
        $this->assertStringContainsString('2026-04-28', $html);
        $this->assertStringContainsString('not identity verification or a leaderboard requirement', $html);
        $queries = array_values(array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'from "players"')));
        $this->assertCount(1, $queries); $this->assertSame([1, 2, 3, 4, 5], $queries[0]['bindings']);
        $this->assertStringContainsString('"profile_updated_at"', $queries[0]['query']);
        $this->assertStringNotContainsString('"updated_at"', $queries[0]['query']);
    }

    public function test_malformed_relative_and_zero_dates_are_unavailable_and_fractional_timestamp_is_valid(): void
    {
        $dates = ['today', 'last week', '2026-02-31 00:00:00', '0000-00-00 00:00:00', '2026-10-01 25:00:00', '2026-10-01 10:00:00.123'];
        foreach ($dates as $i => $date) { DB::table('players')->insert(['id' => $i + 1, 'profile_updated_at' => $date]); }
        $rows = app(PlayerMembershipProfileFreshnessService::class)->enrich(array_map(fn ($id) => ['id' => $id], range(1, 6)));
        foreach (array_slice($rows, 0, 5) as $row) { $this->assertSame('Profile update date unavailable', $row['profile_freshness']); $this->assertNull($row['profile_updated_date']); }
        $this->assertSame('Profile updated within 6 months', $rows[5]['profile_freshness']);
        $this->assertSame('2026-10-01', $rows[5]['profile_updated_date']);
    }

    public function test_calendar_month_end_and_leap_cutoffs_and_bounded_reads(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2024-08-31 12:00', 'Africa/Johannesburg'));
        DB::table('players')->insert(['id' => 1, 'profile_updated_at' => '2024-02-29 00:00:00']);
        $this->assertSame('Profile updated within 6 months', app(PlayerMembershipProfileFreshnessService::class)->enrich([['id' => 1]])[0]['profile_freshness']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-31 12:00', 'Africa/Johannesburg'));
        DB::table('players')->where('id', 1)->update(['profile_updated_at' => '2026-02-28 00:00:00']);
        $this->assertSame('Profile updated within 6 months', app(PlayerMembershipProfileFreshnessService::class)->enrich([['id' => 1]])[0]['profile_freshness']);
        $this->expectException(\LogicException::class);
        app(PlayerMembershipProfileFreshnessService::class)->enrich(array_fill(0, 51, ['id' => 1]));
    }
}
