<?php

namespace Tests\Feature;

use App\Jobs\RefreshPlayerRatings;
use App\Models\User;
use App\Services\Performance\{PlayerAbilityRefreshState, PlayerRatingRefreshRequest};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan, Cache, Log, Schema};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerRatingRefreshRequestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $path = storage_path('framework/testing/ratings-refresh-'.uniqid());
        config(['cache.stores.file.path' => $path, 'cache.stores.file.lock_path' => $path]);
        Cache::purge('file');
    }

    public function test_refresh_and_polling_deny_guests_and_normal_admins(): void
    {
        $this->partialMock(PlayerAbilityRefreshState::class)->shouldNotReceive('status');
        $this->postJson(route('backend.player-performance.ratings.refresh'))->assertUnauthorized();
        $this->getJson(route('backend.player-performance.ratings.status'))->assertUnauthorized();
        Role::findOrCreate('admin', 'web');
        $this->actingAs(User::factory()->create()->assignRole('admin'));
        $this->postJson(route('backend.player-performance.ratings.refresh'))->assertForbidden();
        $this->getJson(route('backend.player-performance.ratings.status'))->assertForbidden();
    }

    public function test_failed_snapshot_requests_refresh_without_calculating_in_the_request(): void
    {
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $status = ['version' => 'old', 'pending' => true, 'failed' => true, 'last_updated' => null];
        $this->partialMock(PlayerAbilityRefreshState::class)->shouldReceive('status')->twice()->andReturn($status);
        $refresh = $this->mock(PlayerRatingRefreshRequest::class);
        $refresh->shouldReceive('request')->once();
        $refresh->shouldReceive('launchFailure')->twice()->andReturnNull();
        $refresh->shouldReceive('running')->twice()->andReturn(true);
        $this->postJson(route('backend.player-performance.ratings.refresh'))->assertOk()
            ->assertJsonPath('refreshing', true)->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson(route('backend.player-performance.ratings.status'))->assertOk()->assertJsonPath('status.failed', true);
    }

    public function test_current_snapshot_does_not_request_another_calculation(): void
    {
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $this->partialMock(PlayerAbilityRefreshState::class)->shouldReceive('status')->once()
            ->andReturn(['version' => 'current', 'pending' => false, 'failed' => false]);
        $refresh = $this->mock(PlayerRatingRefreshRequest::class);
        $refresh->shouldNotReceive('request');
        $refresh->shouldReceive('launchFailure')->once()->andReturnNull();
        $refresh->shouldReceive('running')->once()->andReturn(false);
        $this->postJson(route('backend.player-performance.ratings.refresh'))->assertOk()->assertJsonPath('refreshing', false);
    }

    public function test_concurrent_requests_are_deduplicated_and_job_releases_its_owned_lock(): void
    {
        $lock = Cache::store('file')->lock('player-ability:requested-refresh', 7200);
        $this->assertTrue($lock->get());
        try {
            $refresh = new PlayerRatingRefreshRequest;
            $refresh->request();
            $this->assertTrue($refresh->running());
            // Deliberately no --pending: a user retry bypasses the scheduler's
            // five-minute failure cooldown, while retaining the builder lock.
            Artisan::shouldReceive('call')->once()->with('player-ability:refresh', \Mockery::type('array'))->andReturn(1);
            (new RefreshPlayerRatings($lock->owner()))->handle();
            $this->assertFalse($refresh->running());
        } finally { $lock->release(); }
    }

    public function test_scheduler_build_is_reported_as_running_and_user_requests_do_not_duplicate_it(): void
    {
        $lock = Cache::store('file')->lock('player-ability:refresh', 60);
        $this->assertTrue($lock->get());
        try {
            $refresh = new PlayerRatingRefreshRequest;
            $refresh->request();
            $this->assertTrue($refresh->running());
            $request = Cache::store('file')->lock('player-ability:requested-refresh', 60);
            $this->assertTrue($request->get());
            $request->release();
        } finally { $lock->release(); }
    }

    public function test_unexpected_job_failure_releases_execution_and_launch_leases(): void
    {
        $lock = Cache::store('file')->lock('player-ability:requested-refresh', 60);
        $this->assertTrue($lock->get());
        Artisan::shouldReceive('call')->once()->andThrow(new \RuntimeException('Test failure'));
        try {
            (new RefreshPlayerRatings($lock->owner()))->handle();
            $this->fail('Expected the command error to propagate.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('Reference:', $error->getMessage());
            $this->assertStringNotContainsString('Test failure', $error->getMessage());
            $this->assertFalse((new PlayerRatingRefreshRequest)->running());
        } finally { $lock->release(); }
    }
    public function test_server_start_failure_returns_private_safe_reference_and_correlated_diagnostic(): void
    {
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        Log::spy();
        $this->partialMock(PlayerAbilityRefreshState::class)->shouldReceive('status')->once()
            ->andThrow(new \RuntimeException('SECRET SQL player-name'));
        $response = $this->postJson(route('backend.player-performance.ratings.refresh'))->assertStatus(503)
            ->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('error.reason', 'refresh_unavailable');
        $reference = $response->json('error.reference_id');
        $this->assertTrue(\Illuminate\Support\Str::isUuid($reference));
        $this->assertStringNotContainsString('SECRET', $response->getContent());
        Log::shouldHaveReceived('log')->with('warning', 'Player ratings refresh diagnostic.', \Mockery::on(fn ($context) =>
            $context['reference_id'] === $reference && $context['phase'] === 'request' && ! str_contains(json_encode($context), 'SECRET')))->once();
    }

    public function test_actual_deferred_launcher_failure_releases_only_owned_lease_and_is_correlated(): void
    {
        Log::spy();
        $reference = (string) \Illuminate\Support\Str::uuid();
        $factory = \Mockery::mock(\Illuminate\Process\Factory::class);
        $factory->shouldReceive('path')->with(base_path())->once()->andReturnSelf();
        $factory->shouldReceive('env')->once()->with(\Mockery::on(fn ($env) => count($env) === 1 && isset($env['CT_RATINGS_REFRESH_OWNER'])))->andReturnSelf();
        $factory->shouldReceive('run')->once()->with(\Mockery::on(fn ($command) => str_contains($command, 'player-ability:requested-refresh') && str_contains($command, $reference)))
            ->andThrow(new \RuntimeException('SECRET SQL'));
        $this->app->instance(\Illuminate\Process\Factory::class, $factory);
        $refresh = new class extends PlayerRatingRefreshRequest {
            public $callback;
            protected function runsOnWindows(): bool { return false; }
            protected function afterResponse(callable $callback): void { $this->callback = $callback; }
        };
        $refresh->request($reference);
        $this->assertTrue($refresh->running());
        ($refresh->callback)();
        $this->assertFalse($refresh->running());
        $this->assertSame(['reference_id'=>$reference, 'reason'=>'launch_failed'], $refresh->launchFailure());
        Log::shouldHaveReceived('log')->with('warning', 'Player ratings refresh diagnostic.', \Mockery::on(fn ($context) =>
            $context['reference_id'] === $reference && $context['phase'] === 'launch' && $context['reason'] === 'failed'
            && ! str_contains(json_encode($context), 'SECRET')))->once();
        $newLock = Cache::store('file')->lock('player-ability:requested-refresh', 60);
        $this->assertTrue($newLock->get());
        try {
            Cache::store('file')->put('player-ability:requested-refresh-cooldown', true, 60);
            $refresh->launchFailed('stale-owner', $reference, new \RuntimeException('SECRET'));
            $this->assertTrue($refresh->running());
            $this->assertTrue(Cache::store('file')->has('player-ability:requested-refresh-cooldown'));
        } finally { $newLock->release(); }
    }

    public function test_nonzero_command_is_logged_as_failure_and_marks_failure_without_replacing_snapshot(): void
    {
        Log::spy();
        $reference = (string) \Illuminate\Support\Str::uuid();
        $lock = Cache::store('file')->lock('player-ability:requested-refresh', 60);$this->assertTrue($lock->get());
        Artisan::shouldReceive('call')->once()->with('player-ability:refresh', ['--reference'=>$reference])->andReturn(1);
        (new RefreshPlayerRatings($lock->owner(), $reference))->handle();
        $this->assertFalse((new PlayerRatingRefreshRequest)->running());
        $this->assertNotNull(\Illuminate\Support\Facades\DB::table('player_ability_refresh_state')->where('id',1)->value('last_failed_at'));
        Log::shouldHaveReceived('log')->with('warning', 'Player ratings refresh diagnostic.', ['reference_id'=>$reference,'phase'=>'job','reason'=>'command_exited','exit_code'=>1])->once();
        Log::shouldNotHaveReceived('log', ['info', 'Player ratings refresh diagnostic.', ['reference_id'=>$reference,'phase'=>'job','reason'=>'completed']]);
    }

    public function test_logging_failure_does_not_hold_execution_or_launch_leases(): void
    {
        Log::shouldReceive('log')->andThrow(new \RuntimeException('logger unavailable'));
        $lock = Cache::store('file')->lock('player-ability:requested-refresh',60);$this->assertTrue($lock->get());
        Artisan::shouldReceive('call')->once()->andReturn(0);
        (new RefreshPlayerRatings($lock->owner()))->handle();
        $this->assertFalse((new PlayerRatingRefreshRequest)->running());
    }

    public function test_missing_snapshot_table_logs_only_actionable_reason_and_reference(): void
    {
        Log::spy();Schema::drop('player_ability_snapshots');
        $reference = (string) \Illuminate\Support\Str::uuid();
        $this->artisan('player-ability:refresh', ['--reference'=>$reference])->assertExitCode(1);
        Log::shouldHaveReceived('log')->with('warning','Player ratings refresh diagnostic.', ['reference_id'=>$reference,'phase'=>'command','reason'=>'snapshot_table_missing','exit_code'=>1])->once();
    }

    public function test_linux_launch_uses_real_application_termination_without_concurrency_bootstrap(): void
    {
        Log::spy();$calls = 0;$childOwner = null;
        $reference = (string) \Illuminate\Support\Str::uuid();
        $result = \Mockery::mock(\Illuminate\Contracts\Process\ProcessResult::class);
        $result->shouldReceive('exitCode')->once()->andReturn(0);$result->shouldReceive('failed')->once()->andReturnFalse();
        $factory = \Mockery::mock(\Illuminate\Process\Factory::class);
        $factory->shouldReceive('path')->with(base_path())->once()->andReturnSelf();
        $factory->shouldReceive('env')->once()->andReturnUsing(function ($env) use (&$childOwner, $factory) { $childOwner=$env['CT_RATINGS_REFRESH_OWNER'];return $factory; });
        $factory->shouldReceive('run')->once()->andReturnUsing(function ($command) use (&$calls, &$childOwner, $reference, $result) {
            $calls++;$this->assertStringContainsString($reference,$command);$this->assertStringNotContainsString($childOwner,$command);return $result;
        });
        $this->app->instance(\Illuminate\Process\Factory::class,$factory);
        $refresh = new class extends PlayerRatingRefreshRequest { protected function runsOnWindows(): bool { return false; } };
        $refresh->request($reference);$this->assertSame(0,$calls);
        $this->app->terminate();$this->assertSame(1,$calls);$this->assertTrue($refresh->running());
        $this->assertSame($reference,Cache::store('file')->get('player-ability:requested-refresh-submitted')['reference_id']);
        Cache::store('file')->restoreLock('player-ability:requested-refresh',$childOwner)->release();
        Cache::store('file')->put('player-ability:requested-refresh-submitted',['reference_id'=>$reference,'submitted_at'=>time()-61],180);
        $this->assertSame('startup_unconfirmed',$refresh->launchFailure()['reason']);
        Log::shouldHaveReceived('log')->with('info','Player ratings refresh diagnostic.',['reference_id'=>$reference,'phase'=>'launch','reason'=>'startup_unconfirmed'])->once();
    }

    public function test_stale_job_does_not_release_new_launch_lease_or_invoke_builder(): void
    {
        $lock=Cache::store('file')->lock('player-ability:requested-refresh',60);$this->assertTrue($lock->get());
        Artisan::shouldReceive('call')->never();
        try {
            $this->assertSame(0,(new RefreshPlayerRatings('expired-owner'))->handle());
            $this->assertTrue((new PlayerRatingRefreshRequest)->running());
        } finally {$lock->release();}
    }

    public function test_child_cli_requires_context_clears_owner_env_and_propagates_builder_failure(): void
    {
        $reference=(string)\Illuminate\Support\Str::uuid();
        $this->artisan('player-ability:requested-refresh',['--reference'=>$reference])->assertExitCode(1);
        $lock=Cache::store('file')->lock('player-ability:requested-refresh',60);$this->assertTrue($lock->get());
        Schema::drop('player_ability_snapshots');
        putenv('CT_RATINGS_REFRESH_OWNER='.$lock->owner());
        try {
            $this->artisan('player-ability:requested-refresh',['--reference'=>$reference])->assertExitCode(1);
            $this->assertFalse(getenv('CT_RATINGS_REFRESH_OWNER'));
            $this->assertFalse((new PlayerRatingRefreshRequest)->running());
        } finally {putenv('CT_RATINGS_REFRESH_OWNER');$lock->release();}
    }

}
