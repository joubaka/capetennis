<?php

// This curated suite must never inherit a cached application/remote DB target.
$drawKnowledgeRoot = dirname(__DIR__, 2);
require $drawKnowledgeRoot.'/vendor/autoload.php';

foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'ct_testing',
    'DB_HOST' => '127.0.0.1', 'DB_PORT' => '3306', 'DB_SOCKET' => '', 'DATABASE_URL' => '',
    'DB_URL' => '', 'CT_TEST_ARRAY_TRANSPORTS' => '1'] as $drawKnowledgeKey => $drawKnowledgeValue) {
    if (getenv($drawKnowledgeKey) !== $drawKnowledgeValue) {
        throw new RuntimeException('Draw knowledge suite requires its isolated test configuration.');
    }
}

$drawKnowledgeCache = 'storage/framework/testing/draw-knowledge-unused-config.php';
if (file_exists($drawKnowledgeRoot.'/'.$drawKnowledgeCache)) {
    throw new RuntimeException('Draw knowledge suite refuses an existing configuration cache.');
}
putenv('APP_CONFIG_CACHE='.$drawKnowledgeCache);
$_ENV['APP_CONFIG_CACHE'] = $_SERVER['APP_CONFIG_CACHE'] = $drawKnowledgeCache;

// Rendering regressions must not reuse or clear the running app's compiled views.
$drawKnowledgeViews = $drawKnowledgeRoot.'/storage/framework/testing/draw-knowledge-views';
if (!is_dir($drawKnowledgeViews) && !mkdir($drawKnowledgeViews, 0777, true) && !is_dir($drawKnowledgeViews)) {
    throw new RuntimeException('Cannot create draw knowledge compiled-view directory.');
}
putenv('VIEW_COMPILED_PATH='.$drawKnowledgeViews);
$_ENV['VIEW_COMPILED_PATH'] = $_SERVER['VIEW_COMPILED_PATH'] = $drawKnowledgeViews;
