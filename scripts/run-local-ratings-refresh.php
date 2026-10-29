<?php

/** Local task entry point. This deliberately never runs Laravel's scheduler. */
final class LocalRatingsRefreshRunner
{
    public static function run(string $environment, array $connection, callable $refresh): int
    {
        $host = is_string($connection['host'] ?? null) ? strtolower($connection['host']) : '';
        if ($environment !== 'local' || ($connection['driver'] ?? '') !== 'mysql'
            || !in_array($host, ['127.0.0.1', 'localhost', '::1'], true)
            || isset($connection['read']) || isset($connection['write']) || isset($connection['direct'])) {
            throw new RuntimeException('Local ratings task requires a local environment and loopback MySQL.');
        }

        return $refresh();
    }
}

if (PHP_SAPI !== 'cli' || realpath($_SERVER['SCRIPT_FILENAME'] ?? '') !== __FILE__) {
    return;
}

$root = dirname(__DIR__);
$write = static function (string $status, ?int $code = null) use ($root): void {
    $now = new DateTimeImmutable('now', new DateTimeZone('Africa/Johannesburg'));
    $line = json_encode(['at' => $now->format(DATE_ATOM), 'status' => $status, 'exit_code' => $code], JSON_THROW_ON_ERROR).PHP_EOL;
    fwrite(STDOUT, $line);
    if (file_put_contents($root.'/storage/logs/local-ratings-refresh-'.$now->format('Y-m-d').'.log', $line, FILE_APPEND | LOCK_EX) === false) {
        throw new RuntimeException('Local ratings task log is unavailable.');
    }
};

try {
    require $root.'/vendor/autoload.php';
    $app = require $root.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    // Use Laravel's URL parser before constructing a connection. Split/read/write
    // configurations are rejected rather than accepting a randomly selected host.
    $connection = (new Illuminate\Support\ConfigurationUrlParser)->parseConfiguration(
        config('database.connections.'.config('database.default'))
    );
    $checkOnly = ($argv[1] ?? null) === '--check-config';
    if (count($argv) > ($checkOnly ? 2 : 1)) {
        throw new RuntimeException('Unsupported local ratings task arguments.');
    }
    $code = LocalRatingsRefreshRunner::run($app->environment(), $connection, static function () use ($app, $checkOnly, $write): int {
        if ($checkOnly) {
            fwrite(STDOUT, "Local ratings task configuration verified.\n");
            return 0;
        }
        $write('started');
        $command = $app->make(App\Console\Commands\RefreshPlayerAbility::class);
        $command->setLaravel($app);
        $output = new Symfony\Component\Console\Output\BufferedOutput;
        $code = $command->run(new Symfony\Component\Console\Input\ArrayInput(['--pending' => true]), $output);
        $text = $output->fetch();
        $status = $code !== 0 ? 'failed' : (str_contains($text, 'An ability refresh is already running.')
            ? 'skipped_lock' : (str_contains($text, 'Saved ability snapshot as of') ? 'saved' : 'checked'));
        $write($status, $code);
        return $code;
    });
    exit($code);
} catch (Throwable) {
    // Never include exception text, SQL, connection details or participant data.
    if (($argv[1] ?? null) === '--check-config') {
        fwrite(STDERR, "Local ratings task configuration rejected.\n");
    } else {
        try { $write('runner_failed', 1); } catch (Throwable) { fwrite(STDERR, "Local ratings task failed.\n"); }
    }
    exit(1);
}
