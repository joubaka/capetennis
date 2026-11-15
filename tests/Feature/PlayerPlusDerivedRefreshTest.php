<?php

namespace Tests\Feature;

use App\Services\Performance\{PlayerAbilitySnapshotStore, PlayerPlusDerivedCommandRunner, PlayerPlusDerivedRefresh, PlayerPlusDerivedRefreshState, PlayerPlusEntitlement, PlayerRatingHistoryStore, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{Artisan, Cache, DB, File, Schema};
use Tests\TestCase;

class PlayerPlusDerivedRefreshTest extends TestCase
{
    private string $directory;
    private string $version;
    private string $fingerprint;
    private array $calls = [];
    private $onCall = null;
    private $onFingerprint = null;
    private PlayerPlusDerivedRefreshState $state;
    private PlayerPlusDerivedRefresh $refresh;

    protected function setUp(): void
    {
        parent::setUp(); $this->travelTo(CarbonImmutable::parse('2026-11-15 03:02:00', 'Africa/Johannesburg'));
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.stores.file' => ['driver' => 'array']]); DB::purge('sqlite');
        $this->directory = storage_path('framework/testing/derived-refresh-'.bin2hex(random_bytes(6))); File::ensureDirectoryExists($this->directory);
        $this->version = str_repeat('b', 64); $this->fingerprint = str_repeat('a', 64);
        $this->state = new class($this->directory) extends PlayerPlusDerivedRefreshState { public function __construct(private string $directory) {} public function path(): string { return $this->directory.'/state.json'; } };
        Schema::create('players', fn ($t) => $t->id());
        Schema::create('player_plus_orders', fn ($t) => [$t->id(), $t->integer('player_id'), $t->string('status'), $t->boolean('pay_status'), $t->boolean('payfast_paid'), $t->timestamp('activated_at')->nullable()]);
        Schema::create('player_plus_memberships', fn ($t) => [$t->id(), $t->integer('player_id'), $t->integer('order_id'), $t->timestamp('starts_at'), $t->timestamp('ends_at')]);
        Schema::create('player_plus_access_overrides', fn ($t) => [$t->id(), $t->integer('player_id'), $t->string('mode'), $t->timestamp('starts_at')->nullable(), $t->timestamp('ends_at')->nullable()]);
        Schema::create('player_ability_snapshots', fn ($t) => [$t->string('snapshot_key'), $t->string('source_fingerprint')]);
        DB::table('player_ability_snapshots')->insert(['snapshot_key' => 'current', 'source_fingerprint' => $this->fingerprint]);
        foreach (range(1, 5) as $id) DB::table('players')->insert(['id' => $id]);
        foreach ([1, 2, 3, 4, 5] as $id) DB::table('player_plus_access_overrides')->insert(['player_id' => $id, 'mode' => $id === 2 ? 'suspended' : 'grant', 'starts_at' => now()->subDay(), 'ends_at' => $id === 4 ? now()->subHour() : now()->addDays(10)]);
        $snapshots = $this->mock(PlayerAbilitySnapshotStore::class);
        $snapshots->shouldReceive('freshCurrent')->andReturnUsing(fn () => ['reason' => null, 'snapshot_as_of' => CarbonImmutable::today('Africa/Johannesburg')->toDateString(), 'snapshot_version' => $this->version]);
        $snapshots->shouldReceive('policyHash')->andReturn(str_repeat('c', 64));
        $history = $this->mock(PlayerRatingHistoryStore::class); $history->shouldReceive('read')->andReturnUsing(fn () => ['as_of' => CarbonImmutable::today('Africa/Johannesburg')->toDateString(), 'source_fingerprint' => $this->fingerprint]);
        $ability = $this->mock(PlayerSharedAbilityService::class); $ability->shouldReceive('fingerprint')->andReturnUsing(function () { if ($this->onFingerprint) ($this->onFingerprint)(); return $this->fingerprint; });
        $ability->shouldNotReceive('calculateSnapshot', 'historySnapshot');
        $runner = $this->mock(PlayerPlusDerivedCommandRunner::class);
        $runner->shouldReceive('run')->andReturnUsing(function ($command, $arguments = []) { $this->calls[] = [$command, $arguments]; return $this->onCall ? ($this->onCall)($command, $arguments) : 0; });
        $this->refresh = new class($this->state, $snapshots, $history, $ability, new PlayerPlusEntitlement, $runner) extends PlayerPlusDerivedRefresh { public float $seconds = 0; protected function clock(): float { return $this->seconds; } };
    }
    protected function tearDown(): void { File::deleteDirectory($this->directory); parent::tearDown(); }
    private function commands(): array { return array_column($this->calls, 0); }
    private function progressPlayers(): array { return array_values(array_map(fn ($call) => $call[1]['player'], array_filter($this->calls, fn ($call) => $call[0] === 'player-match-progress:refresh'))); }

    public function test_ordered_pipeline_uses_only_canonical_active_plus_players_and_never_mutates_entitlements(): void
    {
        $before = DB::table('player_plus_access_overrides')->get()->toJson(); $result = $this->refresh->run(scheduled: true);
        $this->assertSame(['player-ability:refresh', 'player-rating-history:refresh', 'player-match-progress:refresh', 'player-event-ratings:refresh', 'player-match-progress:refresh', 'player-event-ratings:refresh', 'player-match-progress:refresh', 'player-event-ratings:refresh'], $this->commands());
        $this->assertSame([1, 3, 5], $this->progressPlayers()); $this->assertTrue($result['complete']); $this->assertFalse($result['partial']); $this->assertSame(3, $result['processed_players']);
        $this->assertSame($before, DB::table('player_plus_access_overrides')->get()->toJson()); $this->assertSame(0, $result['exit_code']);
        foreach (['cursor', 'player_id', 'context', 'reference', $this->fingerprint] as $private) $this->assertStringNotContainsString($private, json_encode($result));
    }

    public function test_failed_base_stage_blocks_dependents_and_retry_is_bounded_to_three_attempts_per_night(): void
    {
        $this->onCall = fn ($command) => $command === 'player-ability:refresh' ? 1 : 0;
        foreach ([false, true, true, true] as $resume) { $result = $this->refresh->run(resume: $resume); $this->assertSame('failed', $result['status']); $this->assertSame(1, $result['exit_code']); }
        $this->assertSame(array_fill(0, 3, 'player-ability:refresh'), $this->commands()); $this->assertSame(3, $this->state->read()['ability_attempts']);
    }

    public function test_history_failure_retries_history_without_rebuilding_successful_ability(): void
    {
        $historyCalls = 0; $this->onCall = function ($command) use (&$historyCalls) { return $command === 'player-rating-history:refresh' && ++$historyCalls === 1 ? 1 : 0; };
        $failed = $this->refresh->run(); $this->assertSame('history_failed', $failed['reason']); $this->assertSame(['player-ability:refresh', 'player-rating-history:refresh'], $this->commands());
        $done = $this->refresh->run(resume: true); $this->assertTrue($done['complete']);
        $this->assertSame(1, count(array_filter($this->commands(), fn ($c) => $c === 'player-ability:refresh'))); $this->assertSame(2, $this->state->read()['history_attempts']);
    }

    public function test_time_budget_resumes_exact_player_stage_without_repeating_progress(): void
    {
        $this->onCall = function ($command) { if ($command === 'player-match-progress:refresh') $this->refresh->seconds = 10; return 0; };
        $partial = $this->refresh->run(maxSeconds: 1); $this->assertTrue($partial['partial']); $this->assertSame('events', $partial['stage']); $this->assertSame(1, $this->state->read()['player_id']);
        $this->onCall = null; $done = $this->refresh->run(resume: true); $this->assertTrue($done['complete']);
        $this->assertSame([1, 3, 5], $this->progressPlayers()); $this->assertSame('player-event-ratings:refresh', $this->calls[3][0]); $this->assertSame(['player' => 1], $this->calls[3][1]);
    }

    public function test_daily_rotation_starts_from_unfinished_cursor_and_wraps_once_without_starvation(): void
    {
        foreach ([15, 16, 17] as $day) { $this->travelTo(CarbonImmutable::parse('2026-11-'.$day.' 03:02:00', 'Africa/Johannesburg')); $this->assertTrue($this->refresh->run(limit: 1)['partial']); }
        $this->assertSame([1, 3, 5], $this->progressPlayers());
        $this->travelTo(CarbonImmutable::parse('2026-11-18 03:02:00', 'Africa/Johannesburg')); $this->refresh->run(limit: 1); $this->assertSame([1, 3, 5, 1], $this->progressPlayers());
        $done = $this->refresh->run(resume: true, limit: 100); $this->assertTrue($done['complete']); $this->assertSame([1, 3, 5, 1, 3, 5], $this->progressPlayers());
    }

    public function test_source_context_change_halts_resume_without_mixing_or_resetting_cursor(): void
    {
        $this->refresh->run(limit: 1); $count = count($this->calls); $this->version = str_repeat('d', 64);
        $result = $this->refresh->run(resume: true); $this->assertSame('context_changed', $result['status']); $this->assertSame(1, $result['exit_code']);
        $this->assertCount($count, $this->calls); $this->assertSame(1, $this->state->read()['cursor']);
        $this->refresh->run(resume: true); $this->assertCount($count, $this->calls);
    }

    public function test_player_stage_failure_or_exception_advances_and_reports_non_green_completion(): void
    {
        $this->onCall = function ($command, $arguments) { if ($command === 'player-match-progress:refresh' && $arguments['player'] === 1) throw new \RuntimeException('PRIVATE'); return $command === 'player-event-ratings:refresh' && $arguments['player'] === 3 ? 1 : 0; };
        $result = $this->refresh->run(); $this->assertSame('complete_with_errors', $result['status']); $this->assertFalse($result['complete']); $this->assertTrue($result['attempted_all']); $this->assertSame(1, $result['exit_code']);
        $this->assertSame([1, 3, 5], $this->progressPlayers()); $this->assertSame(1, $result['progress_failures']); $this->assertSame(1, $result['event_failures']); $this->assertStringNotContainsString('PRIVATE', json_encode($result));
    }

    public function test_scheduled_daytime_is_noop_and_explicit_manual_resume_can_continue_outside_night(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00:00', 'Africa/Johannesburg'));
        $this->assertSame('outside_window', $this->refresh->run(scheduled: true)['status']); $this->assertSame([], $this->calls); $this->assertNull($this->state->read());
        $this->refresh->run(limit: 1); $count = count($this->calls); $this->assertSame('outside_window', $this->refresh->run(resume: true, scheduled: true)['status']); $this->assertCount($count, $this->calls);
        $this->assertTrue($this->refresh->run(resume: true)['complete']);
    }

    public function test_budget_is_rechecked_after_context_validation_before_starting_another_command(): void
    {
        $checks = 0; $this->onFingerprint = function () use (&$checks) { if (++$checks >= 4) $this->refresh->seconds = 6; };
        $result = $this->refresh->run(maxSeconds: 5); $this->assertTrue($result['partial']);
        $this->assertSame(['player-ability:refresh', 'player-rating-history:refresh'], $this->commands());
    }

    public function test_global_lease_prevents_overlapping_builds_without_mutating_state(): void
    {
        $lock = Cache::store('file')->lock(PlayerPlusDerivedRefresh::LOCK, 28800); $this->assertTrue($lock->get());
        try { $result = $this->refresh->run(); $this->assertSame('busy', $result['status']); $this->assertSame([], $this->calls); $this->assertNull($this->state->read()); }
        finally { $lock->release(); }
    }
}
