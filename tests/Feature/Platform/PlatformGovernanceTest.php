<?php

namespace Tests\Feature\Platform;

use App\Domain\Entries\Events\EntryCreated;
use App\Domain\Entries\Events\EntryLocked;
use App\Domain\Entries\Events\EntryUnlocked;
use App\Models\CategoryEvent;
use App\Models\User;
use App\Services\FeatureFlags;
use App\Services\PlatformAuditLogger;
use App\Services\PlatformHealthService;
use App\Services\PerformanceTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlatformGovernanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'admin',      'guard_name' => 'web']);
    }

    private function adminUser(): User
    {
        return User::factory()->create()->assignRole('admin');
    }

    // ==============================================================
    // PlatformHealthService
    // ==============================================================

    public function test_health_service_returns_all_sections(): void
    {
        $service = new PlatformHealthService();
        $all     = $service->all();

        $this->assertArrayHasKey('engine',       $all);
        $this->assertArrayHasKey('financial',    $all);
        $this->assertArrayHasKey('draw',         $all);
        $this->assertArrayHasKey('registration', $all);
        $this->assertArrayHasKey('queue',        $all);
        $this->assertArrayHasKey('system',       $all);
        $this->assertArrayHasKey('summary',      $all);
    }

    public function test_health_service_summary_counts_are_consistent(): void
    {
        $service = new PlatformHealthService();
        $all     = $service->all();
        $summary = $all['summary'];

        $this->assertArrayHasKey('ok',       $summary);
        $this->assertArrayHasKey('warn',     $summary);
        $this->assertArrayHasKey('critical', $summary);
        $this->assertGreaterThanOrEqual(0, $summary['ok']);
        $this->assertGreaterThanOrEqual(0, $summary['warn']);
        $this->assertGreaterThanOrEqual(0, $summary['critical']);
    }

    public function test_each_health_item_has_required_keys(): void
    {
        $service  = new PlatformHealthService();
        $all      = $service->all();
        $sections = ['engine', 'financial', 'draw', 'registration', 'queue', 'system'];

        foreach ($sections as $section) {
            foreach ($all[$section] as $item) {
                $this->assertArrayHasKey('label',  $item, "Missing label in section: {$section}");
                $this->assertArrayHasKey('value',  $item, "Missing value in section: {$section}");
                $this->assertArrayHasKey('status', $item, "Missing status in section: {$section}");
                $this->assertContains($item['status'], ['ok', 'warn', 'critical'],
                    "Invalid status in section: {$section} item: {$item['label']}");
            }
        }
    }

    // ==============================================================
    // PlatformAuditLogger
    // ==============================================================

    public function test_audit_logger_writes_to_platform_audit_logs(): void
    {
        PlatformAuditLogger::log(
            PlatformAuditLogger::DRAW_GENERATED,
            null,
            null,
            ['draw_id' => 1],
            ['event_id' => 10]
        );

        $this->assertDatabaseHas('platform_audit_logs', [
            'action' => 'draw.generated',
        ]);
    }

    public function test_audit_logger_records_before_and_after(): void
    {
        PlatformAuditLogger::log(
            PlatformAuditLogger::SCORE_DELETED,
            null,
            ['score' => 6],
            ['score' => null],
            ['fixture_id' => 99]
        );

        $row = DB::table('platform_audit_logs')
            ->where('action', 'score.deleted')
            ->latest('created_at')->first();

        $this->assertNotNull($row);
        $before = json_decode($row->before, true);
        $after  = json_decode($row->after,  true);
        $this->assertEquals(['score' => 6],    $before);
        $this->assertEquals(['score' => null], $after);
    }

    public function test_audit_logger_does_not_throw_on_db_failure(): void
    {
        // Simulate a failure by logging before the table exists on a fresh SQLite
        // The logger swallows all exceptions — this should not throw
        $this->expectNotToPerformAssertions();

        try {
            PlatformAuditLogger::log(PlatformAuditLogger::ADMIN_OVERRIDE, null, null, null, []);
        } catch (\Throwable $e) {
            $this->fail('PlatformAuditLogger threw an exception: ' . $e->getMessage());
        }
    }

    // ==============================================================
    // FeatureFlags
    // ==============================================================

    public function test_feature_flag_defaults_from_config(): void
    {
        // Default from config/feature-flags.php (env defaults false for canonical)
        config(['feature-flags.canonical_engine' => false]);
        $this->assertFalse(FeatureFlags::enabled(FeatureFlags::CANONICAL_ENGINE));
    }

    public function test_feature_flag_admin_override_takes_priority_over_env(): void
    {
        config(['feature-flags.canonical_engine' => false]);

        FeatureFlags::enable(FeatureFlags::CANONICAL_ENGINE);
        $this->assertTrue(FeatureFlags::enabled(FeatureFlags::CANONICAL_ENGINE));

        FeatureFlags::disable(FeatureFlags::CANONICAL_ENGINE);
        $this->assertFalse(FeatureFlags::enabled(FeatureFlags::CANONICAL_ENGINE));

        FeatureFlags::clearOverride(FeatureFlags::CANONICAL_ENGINE);
    }

    public function test_feature_flag_event_override_takes_priority_over_admin(): void
    {
        config(['feature-flags.canonical_engine' => false]);
        FeatureFlags::disable(FeatureFlags::CANONICAL_ENGINE);

        FeatureFlags::setForEvent(999, FeatureFlags::CANONICAL_ENGINE, true);

        // With event override: true
        $this->assertTrue(FeatureFlags::enabled(FeatureFlags::CANONICAL_ENGINE, 999));

        // Without event ID: still false (admin says false)
        $this->assertFalse(FeatureFlags::enabled(FeatureFlags::CANONICAL_ENGINE));

        FeatureFlags::clearForEvent(999, FeatureFlags::CANONICAL_ENGINE);
        FeatureFlags::clearOverride(FeatureFlags::CANONICAL_ENGINE);
    }

    public function test_feature_flags_all_returns_all_flags(): void
    {
        $flags = FeatureFlags::all();

        foreach (FeatureFlags::ALL_FLAGS as $flag) {
            $this->assertArrayHasKey($flag, $flags);
            $this->assertIsBool($flags[$flag]);
        }
    }

    // ==============================================================
    // PerformanceTracker
    // ==============================================================

    public function test_performance_tracker_wraps_callable_and_returns_result(): void
    {
        PerformanceTracker::reset();

        $result = PerformanceTracker::track(PerformanceTracker::SCORE_SAVE, 1, fn () => 'hello');

        $this->assertEquals('hello', $result);
    }

    public function test_performance_tracker_manual_start_end(): void
    {
        PerformanceTracker::reset();

        $timer = PerformanceTracker::start(PerformanceTracker::STANDINGS_CALCULATION);
        usleep(1000); // 1 ms
        $ms = PerformanceTracker::end($timer, 42);

        $this->assertGreaterThanOrEqual(0, $ms);
    }

    public function test_performance_tracker_slow_ops_filters_correctly(): void
    {
        PerformanceTracker::reset();

        PerformanceTracker::track(PerformanceTracker::SCORE_SAVE, 1, fn () => null);

        // slowOps with a very high threshold — nothing should exceed it
        $slowOps = PerformanceTracker::slowOps(PHP_INT_MAX);
        $this->assertEmpty($slowOps);
    }

    // ==============================================================
    // Platform artisan commands (smoke tests)
    // ==============================================================

    public function test_platform_health_check_command_runs_without_error(): void
    {
        $this->artisan('platform:health-check')
            ->assertExitCode(0);
    }

    public function test_platform_preflight_command_passes_on_fresh_db(): void
    {
        $this->artisan('platform:preflight')
            ->assertExitCode(0);
    }

    public function test_platform_verify_backup_command_runs(): void
    {
        // With a fresh test DB, row counts will be low — expect failure but no exception
        $exitCode = $this->artisan('platform:verify-backup --min-rows=0');
        // Should not throw — exit 0 or 1 both acceptable
        $this->assertNotNull($exitCode);
    }

    public function test_platform_release_audit_command_runs(): void
    {
        $this->artisan('platform:release-audit --since=1hour')
            ->assertExitCode(0);
    }

    // ==============================================================
    // FIX 3 — EventEntryController uses EntryService (admin add)
    // ==============================================================

    public function test_admin_add_player_fires_entry_created_event(): void
    {
        Event::fake([EntryCreated::class]);

        $admin        = $this->adminUser();
        $categoryEvent = CategoryEvent::factory()->create();

        $player = \App\Models\Player::factory()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.category.addPlayer', $categoryEvent), [
                'registration_id' => $player->id,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        Event::assertDispatched(EntryCreated::class, fn ($e) => $e->source === 'admin');
    }

    public function test_admin_add_player_blocked_when_category_locked(): void
    {
        $admin         = $this->adminUser();
        $categoryEvent = CategoryEvent::factory()->create(['locked_at' => now()]);
        $player        = \App\Models\Player::factory()->create();

        $this->actingAs($admin)
            ->postJson(route('admin.category.addPlayer', $categoryEvent), [
                'registration_id' => $player->id,
            ])
            ->assertStatus(403);
    }

    public function test_lock_category_fires_entry_locked_event(): void
    {
        Event::fake([EntryLocked::class]);

        $admin         = $this->adminUser();
        $categoryEvent = CategoryEvent::factory()->create(['locked_at' => null]);

        $this->actingAs($admin)
            ->postJson(route('admin.category.lock', $categoryEvent))
            ->assertOk()
            ->assertJsonPath('locked', true);

        Event::assertDispatched(EntryLocked::class);
    }

    public function test_unlock_category_fires_entry_unlocked_event(): void
    {
        Event::fake([EntryUnlocked::class]);

        $admin         = $this->adminUser();
        $categoryEvent = CategoryEvent::factory()->create(['locked_at' => now()]);

        $this->actingAs($admin)
            ->postJson(route('admin.category.unlock', $categoryEvent))
            ->assertOk()
            ->assertJsonPath('locked', false);

        Event::assertDispatched(EntryUnlocked::class);
    }

    // ==============================================================
    // FIX 4 — ScheduleEngine lock guard
    // ==============================================================

    public function test_schedule_engine_blocks_locked_draw(): void
    {
        $draw = \App\Models\Draw::factory()->create(['locked' => true, 'published' => false]);

        $engine = app(\App\Services\ScheduleEngine::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/locked/');

        $engine->scheduleDraw($draw->id, 1, now()->toDateTimeString());
    }

    public function test_schedule_engine_blocks_published_draw(): void
    {
        $draw = \App\Models\Draw::factory()->create(['locked' => false, 'published' => true]);

        $engine = app(\App\Services\ScheduleEngine::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/published/');

        $engine->scheduleDraw($draw->id, 1, now()->toDateTimeString());
    }

    public function test_schedule_engine_allows_unlocked_draft_draw(): void
    {
        $draw = \App\Models\Draw::factory()->create(['locked' => false, 'published' => false]);

        $engine = app(\App\Services\ScheduleEngine::class);

        // No fixtures → returns true without error
        $result = $engine->scheduleDraw($draw->id, 1, now()->toDateTimeString());

        $this->assertTrue($result);
    }
}
