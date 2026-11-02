<?php

// Queue regressions must never inherit the running application's database or cache.
$capesterRoot = dirname(__DIR__, 2);
require $capesterRoot.'/vendor/autoload.php';

foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'ct_testing',
    'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_SOCKET' => '', 'DATABASE_URL' => '',
    'DB_URL' => '', 'CT_TEST_ARRAY_TRANSPORTS' => '1'] as $capesterKey => $capesterValue) {
    if (getenv($capesterKey) !== $capesterValue) {
        throw new RuntimeException('Capester tests require the isolated phpunit.capester.xml configuration.');
    }
}

foreach (['APP_CONFIG_CACHE' => 'capester-unused-config.php', 'APP_ROUTES_CACHE' => 'capester-unused-routes.php'] as $capesterKey => $capesterFile) {
    $capesterPath = $capesterRoot.'/storage/framework/testing/'.$capesterFile;
    if (file_exists($capesterPath)) {
        throw new RuntimeException('Capester tests refuse an existing runtime cache.');
    }
    putenv($capesterKey.'='.$capesterPath);
    $_ENV[$capesterKey] = $_SERVER[$capesterKey] = $capesterPath;
}

$capesterViews = $capesterRoot.'/storage/framework/testing/capester-views';
if (! is_dir($capesterViews) && ! mkdir($capesterViews, 0755, true) && ! is_dir($capesterViews)) {
    throw new RuntimeException('Cannot create isolated Capester compiled views.');
}
putenv('VIEW_COMPILED_PATH='.$capesterViews);
$_ENV['VIEW_COMPILED_PATH'] = $_SERVER['VIEW_COMPILED_PATH'] = $capesterViews;
