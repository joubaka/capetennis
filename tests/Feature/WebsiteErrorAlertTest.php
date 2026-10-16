<?php

namespace Tests\Feature;

use App\Exceptions\Handler;
use App\Jobs\SendWebsiteErrorAlert;
use App\Mail\WebsiteErrorAlertMail;
use App\Models\User;
use App\Services\MailAccountManager;
use App\Services\WebsiteErrorAlertService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WebsiteErrorAlertTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Isolate the alert's recipient/role schema; do not migrate a shared database.
        config([
            'database.default' => 'website_alert_test',
            'database.connections.website_alert_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'cache.default' => 'array',
        ]);
        DB::purge('website_alert_test');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
        (require glob(database_path('migrations/*create_permission_tables.php'))[0])->up();
        Bus::fake();
        Mail::fake();
        Cache::flush();
        Role::findOrCreate('super-user', 'web');
        Role::findOrCreate('admin', 'web');
    }

    private function website(string $environment = 'production'): void
    {
        $this->app->instance('env', $environment);
        (new \ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
        $request = Request::create('/invite/SECRET_PATH?token=SECRET_QUERY', 'POST', ['password' => 'SECRET_BODY']);
        $route = new Route('POST', 'invite/{token}', fn () => null);
        $request->setRouteResolver(fn () => $route);
        $this->app->instance('request', $request);
    }

    public function test_handler_queues_only_super_users_with_safe_diagnostics_and_keeps_logging(): void
    {
        $admin = User::factory()->create()->assignRole('super-user');
        User::factory()->create()->assignRole('admin');
        User::factory()->create();
        $this->website();
        Log::shouldReceive('error')->once();
        Log::shouldReceive('notice')->once();

        $this->app->make(Handler::class)->report(new \RuntimeException('SQL SECRET_EXCEPTION password=SECRET_PASSWORD'));

        Bus::assertDispatchedTimes(SendWebsiteErrorAlert::class, 1);
        Bus::assertDispatched(SendWebsiteErrorAlert::class, function ($job) use ($admin): bool {
            $this->assertSame($admin->id, $job->adminId);
            $this->assertSame('invite/{token}', $job->diagnostic['route']);
            $snapshot = serialize($job);
            $this->assertStringNotContainsString('SECRET_', $snapshot);
            $this->assertStringNotContainsString(base_path(), $snapshot);
            $this->assertSame(\RuntimeException::class, $job->diagnostic['exception']);
            $html = (new WebsiteErrorAlertMail($job->diagnostic))->render();
            $this->assertStringContainsString('RuntimeException', $html);
            $this->assertStringNotContainsString('SECRET_', $html);

            return true;
        });
        Mail::assertNothingSent();
    }

    public function test_ordinary_client_errors_are_ignored_but_server_http_errors_are_reported(): void
    {
        User::factory()->create()->assignRole('super-user');
        $this->website();
        $handler = $this->app->make(Handler::class);
        foreach ([new HttpException(404), new HttpException(403), new AuthenticationException(), ValidationException::withMessages(['name' => 'Required'])] as $exception) {
            $handler->report($exception);
        }
        Bus::assertNothingDispatched();
        // Laravel normally ignores HTTP exceptions. Explicitly opt server errors in.
        $handler->report(new HttpException(503));
        Bus::assertDispatchedTimes(SendWebsiteErrorAlert::class, 1);
    }

    public function test_local_and_console_errors_do_not_queue_alerts(): void
    {
        $service = $this->app->make(WebsiteErrorAlertService::class);
        $service->report(new \RuntimeException('console'));
        $this->website('local');
        $service->report(new \RuntimeException('local'));
        Bus::assertNothingDispatched();
    }

    public function test_repeated_source_errors_are_grouped_for_five_minutes(): void
    {
        User::factory()->create()->assignRole('super-user');
        $this->website('staging');
        $service = $this->app->make(WebsiteErrorAlertService::class);
        $exception = new \RuntimeException('First occurrence');
        $service->report($exception);
        $service->report($exception);
        Bus::assertDispatchedTimes(SendWebsiteErrorAlert::class, 1);
        $this->travel(6)->minutes();
        $service->report($exception);
        Bus::assertDispatchedTimes(SendWebsiteErrorAlert::class, 2);
    }

    public function test_distinct_error_storms_are_bounded(): void
    {
        User::factory()->create()->assignRole('super-user');
        $this->website();
        for ($index = 0; $index < 12; $index++) {
            $exception = new class extends \RuntimeException
            {
                public function location(int $line): void { $this->line = $line; }
            };
            $exception->location($index + 1);
            $this->app->make(WebsiteErrorAlertService::class)->report($exception);
        }
        Bus::assertDispatchedTimes(SendWebsiteErrorAlert::class, 10);
    }

    public function test_queue_failure_cannot_replace_original_error_or_recurse(): void
    {
        User::factory()->create()->assignRole('super-user');
        $this->website();
        Bus::shouldReceive('dispatch')->once()->andReturnUsing(function (): never {
            $this->app->make(WebsiteErrorAlertService::class)->report(new \RuntimeException('nested'));
            throw new \RuntimeException('SECRET_TRANSPORT');
        });
        Log::shouldReceive('warning')->once()->with('Website error alert could not be queued.', ['exception' => \RuntimeException::class]);
        Log::shouldReceive('notice')->once();
        $this->app->make(WebsiteErrorAlertService::class)->report(new \RuntimeException('original'));
        $this->assertTrue(true);
    }

    public function test_delivery_rechecks_super_user_role_and_uses_managed_transport(): void
    {
        $admin = User::factory()->create()->assignRole('super-user');
        $ordinary = User::factory()->create()->assignRole('admin');
        $this->website();
        $this->app->make(WebsiteErrorAlertService::class)->report(new \RuntimeException('test'));
        $job = Bus::dispatched(SendWebsiteErrorAlert::class)->first();
        $accounts = \Mockery::mock(MailAccountManager::class);
        $accounts->shouldReceive('getMailer')->once()->andReturn('array');
        $job->handle($accounts);
        Mail::assertSent(WebsiteErrorAlertMail::class, fn ($mail) => $mail->hasTo($admin->email) && $mail->mailer === 'array');
        Mail::assertSentCount(1);
        $admin->removeRole('super-user');
        $job->handle($accounts);
        (new SendWebsiteErrorAlert($ordinary->id, $job->diagnostic))->handle($accounts);
        Mail::assertSentCount(1);
        $this->assertCount(1, $job->middleware());
    }
}
