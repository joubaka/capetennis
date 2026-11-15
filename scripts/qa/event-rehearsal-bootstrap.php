<?php

declare(strict_types=1);

/** Private process configuration shared by the importer and loopback router. */
use Illuminate\Support\Facades\{DB, Http, Mail, Notification, Queue};

$root = dirname(__DIR__, 2);
$runtime = $root.'/storage/app/private/event-rehearsal-259/runtime';
$cachePath = 'storage/app/private/event-rehearsal-259/runtime';
foreach (['framework/cache/data','framework/sessions','framework/views','logs'] as $directory) {
    if (!is_dir($runtime.'/'.$directory) && !mkdir($runtime.'/'.$directory, 0700, true) && !is_dir($runtime.'/'.$directory)) {
        throw new RuntimeException('Cannot create private rehearsal runtime.');
    }
}
foreach ([
    'APP_ENV' => 'local', 'APP_URL' => 'http://127.0.0.1:8089',
    'DB_CONNECTION' => 'mysql', 'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_SOCKET' => '',
    'DB_DATABASE' => 'ct_rehearsal_259', 'DATABASE_URL' => '',
    // Laravel's cache path normalizer does not recognize Windows drive prefixes.
    'APP_CONFIG_CACHE' => $cachePath.'/config.php', 'APP_ROUTES_CACHE' => $cachePath.'/routes.php',
    'APP_EVENTS_CACHE' => $cachePath.'/events.php', 'APP_SERVICES_CACHE' => $cachePath.'/services.php',
    'APP_PACKAGES_CACHE' => $cachePath.'/packages.php',
    // Role/login caches use tags; the process-local array store supports them.
    'CACHE_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'CACHE_PREFIX' => 'ct_rehearsal_259',
    'SESSION_DRIVER' => 'file', 'SESSION_COOKIE' => 'ct_rehearsal_259_session',
    'SESSION_DOMAIN' => '', 'SESSION_SECURE_COOKIE' => 'false', 'SESSION_PATH' => '/',
    'MAIL_MAILER' => 'log', 'QUEUE_CONNECTION' => 'sync',
] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
require_once $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$app->useStoragePath($runtime);
$kernel = $app->make(PHP_SAPI === 'cli-server' ? Illuminate\Contracts\Http\Kernel::class : Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$db = DB::connection();
if (!$app->environment('local') || $db->getDriverName() !== 'mysql'
    || $db->getDatabaseName() !== 'ct_rehearsal_259'
    || !in_array($db->getConfig('host'), ['127.0.0.1','localhost','::1'], true)) {
    throw new RuntimeException('Refusing: rehearsal requires exact isolated loopback MySQL database.');
}
// A request can neither send nor execute asynchronous work. No worker is started.
Mail::fake(); Notification::fake(); Queue::fake(); Http::preventStrayRequests();
return [$app, $kernel];
