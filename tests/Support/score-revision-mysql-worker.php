<?php

declare(strict_types=1);

// CLI-only child for the disposable-database contention regression, never an endpoint.
if (PHP_SAPI !== 'cli') exit(1);
$database = getenv('CT_STALE_SAVE_MYSQL_DATABASE');
if (getenv('APP_ENV') !== 'testing' || getenv('DB_CONNECTION') !== 'mysql'
    || getenv('DB_HOST') !== '127.0.0.1' || !is_string($database)
    || !preg_match('/^ct_testing_stale_save_[a-z0-9_]+$/D', $database)
    || getenv('DB_DATABASE') !== $database || getenv('DB_URL') || getenv('DATABASE_URL')
    || !getenv('APP_CONFIG_CACHE') || file_exists(getenv('APP_CONFIG_CACHE'))) {
    fwrite(STDERR, "Refusing non-isolated MySQL worker configuration.\n");
    exit(1);
}

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    if (config('database.default') !== 'mysql'
        || config('database.connections.mysql.host') !== '127.0.0.1'
        || config('database.connections.mysql.database') !== $database
        || Illuminate\Support\Facades\DB::selectOne('SELECT DATABASE() AS db')->db !== $database) {
        throw new RuntimeException('Worker database does not match its disposable target.');
    }
    foreach (array_keys(config('mail.mailers', [])) as $mailer) {
        config(["mail.mailers.$mailer" => ['transport' => 'array']]);
    }
    config(['mail.default' => 'array', 'cache.default' => 'array', 'session.driver' => 'array']);
    Illuminate\Support\Facades\DB::statement('SET SESSION innodb_lock_wait_timeout = 15');
    $input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    $actor = App\Models\User::findOrFail($input['actor_id']);
    Illuminate\Support\Facades\Auth::guard()->setUser($actor);
    $connectionId = (int) Illuminate\Support\Facades\DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
    echo json_encode(['connection_id' => $connectionId])."\n";
    flush();

    if ($input['client'] === 'team_service') {
        // Exercise the service with no preloaded draw/result relations as well as HTTP clients.
        $fixture = App\Models\TeamFixture::withoutEagerLoads()->findOrFail($input['fixture_id']);
        $service = app(App\Services\TeamFixtureScoreService::class);
        if ($input['operation'] === 'delete') $service->delete($fixture, $input['revision']);
        else $service->save($fixture, ['set1_home' => 6, 'set1_away' => 3, 'score_revision' => $input['revision']]);
        $status = 200;
    } else {
        $team = $input['client'] === 'team_http';
        $delete = $input['operation'] === 'delete';
        $route = $team ? ($delete ? 'frontend.fixtures.score.delete' : 'frontend.fixtures.score.store')
            : ($input['client'] === 'api'
                ? ($delete ? 'api.draws.fixtures.score.delete' : 'api.draws.fixtures.score.store')
                : ($delete ? 'backend.roundrobin.score.delete' : 'backend.roundrobin.score.store'));
        $parameters = $input['client'] === 'api' ? [$input['draw_id'], $input['fixture_id']] : [$input['fixture_id']];
        $payload = ['score_revision' => $input['revision']];
        if (!$delete) $payload += $team ? ['set1_home' => 6, 'set1_away' => 3] : ['sets' => ['6-3']];
        $request = Illuminate\Http\Request::create(route($route, $parameters, false), $delete ? 'DELETE' : 'POST',
            server: ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'],
            content: json_encode($payload, JSON_THROW_ON_ERROR));
        $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
        $response = $kernel->handle($request);
        $status = $response->getStatusCode();
        $kernel->terminate($request, $response);
    }
    echo json_encode(['status' => $status])."\n";
} catch (Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $exception) {
    echo json_encode(['status' => $exception->getStatusCode(), 'message' => $exception->getMessage()])."\n";
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage()."\n");
    exit(1);
}
