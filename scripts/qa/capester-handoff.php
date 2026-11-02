<?php

// Explicit, synthetic test only. No migrations, production calls, or persistent fixtures.
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--live-codex' || count($argv) !== 2) {
    fwrite(STDERR, "Usage: php scripts/qa/capester-handoff.php --live-codex\nUses ct_testing and a real fixed-message Codex call.\n");
    exit(1);
}

foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'ct_testing',
    'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_SOCKET' => '', 'DATABASE_URL' => '',
    'DB_URL' => '', 'CT_TEST_ARRAY_TRANSPORTS' => '1', 'CACHE_DRIVER' => 'array',
    'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync',
    'LOG_CHANNEL' => 'null'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require dirname(__DIR__, 2).'/tests/bootstrap/capester.php';

use App\Models\{CapesterSupportRequest, Event, User};
use App\Services\Assistant\{CapesterConnection, CapesterReceiver, CapesterSupportQueue};
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Http, Schema};
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql'
    || config('database.connections.mysql.database') !== 'ct_testing'
    || config('database.connections.mysql.host') !== '127.0.0.1'
    || config('database.connections.mysql.url')) {
    fwrite(STDERR, "Refusing a database other than isolated local ct_testing.\n");
    exit(1);
}
foreach (array_keys(config('mail.mailers', [])) as $mailer) {
    config(['mail.mailers.'.$mailer => ['transport' => 'array']]);
}
if (! Schema::hasTable('capester_support_requests')) {
    fwrite(STDERR, "Run the focused Capester test suite to prepare ct_testing first.\n");
    exit(1);
}

$initialTransactionLevel = DB::transactionLevel();
$exitCode = 1;
DB::beginTransaction();
try {
    Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
    $actor = User::factory()->create(['name' => 'Synthetic Capester receiver', 'email' => 'capester-'.Str::uuid().'@example.test'])->assignRole('super-user');
    $event = Event::factory()->create(['name' => 'Synthetic Capester handoff']);
    $bearer = $actor->createToken('Synthetic Capester handoff', ['capester:receive'], now()->addMinutes(10))->plainTextToken;
    $item = app(CapesterSupportQueue::class)->submit($actor, $event, [
        'submission_uuid' => (string) Str::uuid(), 'kind' => 'hello',
        'capester_description' => 'Synthetic fixed-prompt connection test.', 'reviewed' => true,
    ]);
    config(['capester.enabled' => true, 'capester.receiver.base_url' => 'https://capester-handoff.test',
        'capester.receiver.token' => $bearer]);

    $app['auth']->guard('web')->setUser($actor);
    $pageRequest = Request::create('http://localhost/backend/events/'.$event->id.'/capester', 'GET');
    $pageKernel = $app->make(HttpKernel::class);
    $pageResponse = $pageKernel->handle($pageRequest);
    $pageKernel->terminate($pageRequest, $pageResponse);
    if ($pageResponse->getStatusCode() !== 200
        || file_put_contents(storage_path('framework/testing/capester-queue.html'), $pageResponse->getContent()) === false) {
        throw new RuntimeException('Synthetic support page did not render.');
    }

    // Exercise the actual API authentication, middleware, lease and acknowledgement in-process.
    // This deliberately does not claim to verify remote DNS, TLS or network reachability.
    $requests = 0;
    Http::preventStrayRequests();
    Http::fake(function ($outgoing) use ($app, &$requests) {
        $parts = parse_url($outgoing->url());
        if (($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'capester-handoff.test'
            || $outgoing->method() !== 'POST' || ! str_starts_with($parts['path'] ?? '', '/api/v1/capester/')) {
            throw new RuntimeException('Unexpected synthetic transport target.');
        }
        $app['auth']->forgetGuards();
        $incoming = Request::create($outgoing->url(), 'POST', [], [], [], [
            'HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => $outgoing->header('Authorization')[0] ?? '',
        ], json_encode($outgoing->data(), JSON_THROW_ON_ERROR));
        $kernel = $app->make(HttpKernel::class);
        $response = $kernel->handle($incoming);
        $kernel->terminate($incoming, $response);
        $requests++;
        return Http::response($response->getContent(), $response->getStatusCode(), $response->headers->all());
    });

    $result = (new CapesterReceiver(new CapesterConnection))->receive();
    $saved = CapesterSupportRequest::findOrFail($item->id);
    if ($result !== 'hello_passed' || $saved->status !== 'hello_passed' || ! $saved->acknowledged_at || $requests !== 2) {
        throw new RuntimeException('Synthetic handoff did not complete.');
    }
    fwrite(STDOUT, "PASS: synthetic queue -> receiver -> real Codex ping -> acknowledgement.\nTransport: in-process API; no remote server contacted. Test fixtures are rolled back.\n");
    $exitCode = 0;
} catch (Throwable) {
    fwrite(STDERR, "Synthetic handoff failed. No provider diagnostics or credentials are displayed.\n");
} finally {
    while (DB::transactionLevel() > $initialTransactionLevel) {
        DB::rollBack();
    }
}
exit($exitCode);
