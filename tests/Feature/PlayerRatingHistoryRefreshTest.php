<?php

namespace Tests\Feature;

use App\Console\Commands\RunRequestedRatingHistory;
use App\Http\Controllers\Frontend\PlayerRatingHistoryRefreshController;
use App\Models\User;
use App\Services\Performance\{PlayerRatingHistoryProcessLauncher, PlayerRatingHistoryRefresh};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Artisan, Cache, Process};
use Mockery;
use Tests\TestCase;

class PlayerRatingHistoryRefreshTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.stores.file' => ['driver' => 'array'], 'queue.default' => 'sync']);
    }

    protected function tearDown(): void
    {
        putenv('CT_HISTORY_REFRESH_OWNER');
        parent::tearDown();
    }

    public function test_sync_queue_request_only_launches_once_and_never_executes_builder_in_http(): void
    {
        Artisan::shouldReceive('call')->never();
        $owner = null;
        $launcher = Mockery::mock(PlayerRatingHistoryProcessLauncher::class);
        $launcher->shouldReceive('launch')->once()->andReturnUsing(function ($lease) use (&$owner) { $owner = $lease; });
        $refresh = new PlayerRatingHistoryRefresh($launcher);
        $first = $refresh->request();
        $second = $refresh->request();
        $this->assertSame('pending', $first['state']);
        $this->assertSame($first, $second);
        $this->assertNotEmpty($owner);
        $this->assertStringNotContainsString($owner, json_encode($first));
    }

    public function test_nightly_builder_is_deduplicated_and_status_never_claims_completion_on_failure(): void
    {
        $launcher = Mockery::mock(PlayerRatingHistoryProcessLauncher::class);
        $launcher->shouldNotReceive('launch');
        $refresh = new PlayerRatingHistoryRefresh($launcher);
        $builder = Cache::store('file')->lock(PlayerRatingHistoryRefresh::BUILDER, 7200);
        $this->assertTrue($builder->get());
        $refresh->running();
        $this->assertSame('running', $refresh->request()['state']);
        $refresh->failed();
        $builder->release();
        $this->assertSame('failed', $refresh->status()['state']);
    }

    public function test_request_guard_contention_is_reported_as_pending_before_first_state_is_written(): void
    {
        $guard = Cache::store('file')->lock('player-rating-history:request-guard', 30);
        $guard->get();
        $launcher = Mockery::mock(PlayerRatingHistoryProcessLauncher::class);
        $launcher->shouldNotReceive('launch');
        try { $this->assertSame('pending', (new PlayerRatingHistoryRefresh($launcher))->request()['state']); }
        finally { $guard->release(); }
    }

    public function test_launch_failure_is_safe_retryable_and_releases_launch_lease(): void
    {
        $launcher = Mockery::mock(PlayerRatingHistoryProcessLauncher::class);
        $launcher->shouldReceive('launch')->once()->andThrow(new \RuntimeException('private runtime detail'));
        $launcher->shouldReceive('launch')->once()->andReturnNull();
        $refresh = new PlayerRatingHistoryRefresh($launcher);
        $status = $refresh->request();
        $this->assertSame('failed', $status['state']);
        $this->assertStringNotContainsString('private runtime detail', json_encode($status));
        $this->assertFalse(Cache::store('file')->lock(PlayerRatingHistoryRefresh::LEASE)->isLocked());
        $this->assertSame('pending', $refresh->request()['state']);
    }

    public function test_missing_startup_acknowledgement_becomes_retryable_failure(): void
    {
        Cache::store('file')->put(PlayerRatingHistoryRefresh::STATE, ['state' => 'pending', 'submitted_at' => time() - 121], 600);
        $this->assertSame('failed', app(PlayerRatingHistoryRefresh::class)->status()['state']);
    }

    public function test_status_reads_execution_lock_without_acquiring_it(): void
    {
        $lock = Mockery::mock(\Illuminate\Contracts\Cache\Lock::class);
        $lock->shouldReceive('isLocked')->once()->andReturn(false);
        $lock->shouldNotReceive('get', 'release');
        $store = Mockery::mock();
        $store->shouldReceive('get')->once()->with(PlayerRatingHistoryRefresh::STATE, ['state' => 'idle'])->andReturn(['state' => 'idle']);
        $store->shouldReceive('lock')->once()->with(PlayerRatingHistoryRefresh::BUILDER, 1)->andReturn($lock);
        Cache::shouldReceive('store')->with('file')->andReturn($store);
        $this->assertSame('idle', app(PlayerRatingHistoryRefresh::class)->status()['state']);
    }

    public function test_worker_requires_owned_lease_and_calls_only_history_builder_once(): void
    {
        $lease = Cache::store('file')->lock(PlayerRatingHistoryRefresh::LEASE, 120);
        $lease->get();
        putenv('CT_HISTORY_REFRESH_OWNER='.$lease->owner());
        Artisan::shouldReceive('call')->once()->with('player-rating-history:refresh')->andReturn(0);
        $this->assertSame(0, (new RunRequestedRatingHistory)->handle(app(PlayerRatingHistoryRefresh::class)));
        $this->assertFalse(Cache::store('file')->lock(PlayerRatingHistoryRefresh::LEASE)->isLocked());
        $this->assertFalse(getenv('CT_HISTORY_REFRESH_OWNER'));
    }

    public function test_worker_without_lease_cannot_start_a_calculation(): void
    {
        Artisan::shouldReceive('call')->never();
        putenv('CT_HISTORY_REFRESH_OWNER=expired-owner');
        $this->assertSame(1, (new RunRequestedRatingHistory)->handle(app(PlayerRatingHistoryRefresh::class)));
    }

    public function test_controller_rejects_non_superuser_before_request_or_status_access(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-user')->andReturn(false);
        $refresh = Mockery::mock(PlayerRatingHistoryRefresh::class);
        $refresh->shouldNotReceive('request', 'status');
        $request = Request::create('/'); $request->setUserResolver(fn () => $user);
        foreach (['store', 'status'] as $method) {
            try { (new PlayerRatingHistoryRefreshController)->{$method}($request, $refresh); $this->fail('Non-superuser accepted'); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) { $this->assertSame(403, $error->getStatusCode()); }
        }
    }

    public function test_routes_require_web_auth_role_post_csrf_and_separate_poll_throttles(): void
    {
        $routes = app('router')->getRoutes();
        $post = $routes->getByName('my.tennis.rating.refresh');
        $status = $routes->getByName('my.tennis.rating.refresh-status');
        $this->assertSame(['POST'], $post->methods());
        foreach ([$post, $status] as $route) {
            $this->assertContains('web', $route->gatherMiddleware());
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertContains('role:super-user', $route->gatherMiddleware());
        }
        $this->assertContains(\App\Http\Middleware\VerifyCsrfToken::class, app('router')->getMiddlewareGroups()['web']);
        $this->assertContains('throttle:5,1,history-refresh:', $post->gatherMiddleware());
        $this->assertContains('throttle:120,1,history-status:', $status->gatherMiddleware());
    }

    public function test_launchers_use_only_fixed_command_and_private_environment_on_windows_and_linux(): void
    {
        Process::fake();
        foreach ([true, false] as $windows) {
            $launcher = new class($windows) extends PlayerRatingHistoryProcessLauncher {
                public function __construct(private bool $isWindows) {}
                protected function windows(): bool { return $this->isWindows; }
            };
            $launcher->launch('private-owner-token');
        }
        Process::assertRan(function ($process) {
            if (!is_array($process->command)) return false;
            $this->assertSame('powershell.exe', $process->command[0]);
            $this->assertContains('-EncodedCommand', $process->command);
            $this->assertNotContains('-ExecutionPolicy', $process->command);
            $this->assertStringNotContainsString('private-owner-token', json_encode($process->command));
            $script = mb_convert_encoding(base64_decode(end($process->command)), 'UTF-8', 'UTF-16LE');
            $this->assertStringContainsString('-WindowStyle Hidden', $script);
            $this->assertStringContainsString("'\"' + \$artisanPath + '\"'", $script);
            return $process->environment['CT_HISTORY_REFRESH_OWNER'] === 'private-owner-token';
        });
        Process::assertRan(function ($process) {
            if (!is_string($process->command)) return false;
            $this->assertStringContainsString('player-rating-history:requested-refresh', $process->command);
            $this->assertStringContainsString('nohup ', $process->command);
            $this->assertStringEndsWith('2>&1 &', $process->command);
            $this->assertStringNotContainsString('private-owner-token', $process->command);
            return $process->environment['CT_HISTORY_REFRESH_OWNER'] === 'private-owner-token';
        });
        Process::assertRanTimes(fn () => true, 2);
    }

    public function test_native_launch_is_detached_and_quotes_a_project_path_with_spaces(): void
    {
        $directory = storage_path('framework/testing/history launch '.bin2hex(random_bytes(6)));
        \Illuminate\Support\Facades\File::ensureDirectoryExists($directory.'/storage/logs');
        \Illuminate\Support\Facades\File::put($directory.'/artisan', '<?php file_put_contents(__DIR__."/started.json", json_encode(["command"=>$argv[1]??null,"owner_received"=>getenv("CT_HISTORY_REFRESH_OWNER")==="native-test-owner"])); usleep(300000);');
        $launcher = new class($directory) extends PlayerRatingHistoryProcessLauncher {
            public function __construct(private string $directory) {}
            protected function projectRoot(): string { return $this->directory; }
        };
        try {
            $start = microtime(true);
            $launcher->launch('native-test-owner');
            $this->assertLessThan(5.0, microtime(true) - $start);
            $deadline = microtime(true) + 5;
            while (!is_file($directory.'/started.json') && microtime(true) < $deadline) usleep(50000);
            $this->assertFileExists($directory.'/started.json');
            $result = json_decode(file_get_contents($directory.'/started.json'), true);
            $this->assertSame(['command' => 'player-rating-history:requested-refresh', 'owner_received' => true], $result);
            usleep(400000);
        } finally {
            $resolved = realpath($directory);
            $allowed = realpath(storage_path('framework/testing'));
            if ($resolved && $allowed && str_starts_with(strtolower($resolved), strtolower($allowed.DIRECTORY_SEPARATOR))) {
                \Illuminate\Support\Facades\File::deleteDirectory($resolved);
            }
        }
    }
}
