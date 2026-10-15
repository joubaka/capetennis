<?php

// Standalone test server only. It uses normal application routes and auth.
if (PHP_SAPI !== 'cli-server' || getenv('APP_ENV') !== 'testing') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__, 2).'/vendor/autoload.php';
$testRoot = realpath(dirname(__DIR__, 2).'/storage/framework/testing');
$database = realpath((string) getenv('DB_DATABASE'));
$sessions = realpath((string) getenv('CT_REVIEW_SESSION_DIR'));
if (! $database || ! $sessions || ! $testRoot
    || ! str_starts_with($database, $testRoot.DIRECTORY_SEPARATOR)
    || ! str_starts_with($sessions, $testRoot.DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit;
}

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Http\Kernel::class)->bootstrap();
config(['session.files' => $sessions, 'session.encrypt' => false]);
$app->handleRequest(\Illuminate\Http\Request::capture());
