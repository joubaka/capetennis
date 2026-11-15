<?php

declare(strict_types=1);

/** Run only with: php -S 127.0.0.1:8089 -t public scripts/qa/event-rehearsal-router.php */
if (PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)
    || !in_array($_SERVER['SERVER_NAME'] ?? '', ['127.0.0.1','localhost','::1'], true)) {
    http_response_code(403);
    exit('Local rehearsal only.');
}
$public = realpath(dirname(__DIR__, 2).'/public');
$path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$asset = realpath($public.$path);
if ($asset && str_starts_with($asset, $public.DIRECTORY_SEPARATOR) && is_file($asset)
    && !in_array(strtolower(pathinfo($asset, PATHINFO_EXTENSION)), ['php','phtml','phar'], true)) {
    return false;
}
define('LARAVEL_START', microtime(true));
[$app, $kernel] = require __DIR__.'/event-rehearsal-bootstrap.php';
$request = Illuminate\Http\Request::capture();
$response = $kernel->handle($request);
$response->headers->set('X-Cape-Tennis-Rehearsal', 'isolated-259');
$response->send();
$kernel->terminate($request, $response);
