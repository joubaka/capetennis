<?php

// Live requests may already have observed the canonical cache. Never unlink it.
$appPath = $argv[1] ?? '';
$staged = null;
$exitCode = 0;

try {
    if (! is_dir($appPath) || ! is_file($appPath.'/artisan')) {
        throw new RuntimeException('A valid application path is required.');
    }

    require $appPath.'/vendor/autoload.php';
    $app = require $appPath.'/bootstrap/app.php';
    (new Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables)->bootstrap($app);
    $destination = $app->getCachedRoutesPath();
    if (is_link($destination)) {
        $destination = realpath($destination) ?: throw new RuntimeException('Route cache symlink target is unavailable.');
    }
    $directory = dirname($destination);
    if (! is_dir($directory) || ! is_writable($directory)) {
        throw new RuntimeException('Route cache directory is not writable.');
    }

    // Use the destination filesystem so rename is an atomic replacement on Linux.
    $staged = $directory.'/.routes-deploy-'.bin2hex(random_bytes(16)).'.php';
    $environment = getenv();
    $environment['APP_ROUTES_CACHE'] = $staged;
    $process = proc_open(
        [PHP_BINARY, '-d', 'display_errors=Off', $appPath.'/artisan', 'route:cache', '--no-interaction'],
        [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR],
        $pipes,
        $appPath,
        $environment
    );
    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start route cache build.');
    }
    fclose($pipes[0]);
    $status = proc_close($process);
    if ($status !== 0 || ! is_file($staged) || filesize($staged) === 0) {
        throw new RuntimeException('Route cache build failed (exit '.$status.'); the previous cache was preserved.');
    }

    $lint = proc_open([PHP_BINARY, '-l', $staged], [1 => STDOUT, 2 => STDERR], $pipes);
    if (! is_resource($lint) || proc_close($lint) !== 0) {
        throw new RuntimeException('Generated route cache failed PHP validation.');
    }
    $permissions = is_file($destination) ? fileperms($destination) & 0777 : 0666 & ~umask();
    if (! chmod($staged, $permissions) || ! rename($staged, $destination)) {
        throw new RuntimeException('Unable to atomically publish the route cache.');
    }
    $staged = null;
    fwrite(STDOUT, "Route cache replaced atomically.\n");
} catch (Throwable $exception) {
    fwrite(STDERR, 'Live route cache failed: '.$exception->getMessage()."\n");
    $exitCode = 1;
} finally {
    if ($staged !== null && is_file($staged)) {
        unlink($staged);
    }
}

exit($exitCode);
