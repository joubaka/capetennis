<?php

// Local/operator CLI only: direct service invocation avoids Artisan lifecycle audit writes.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$options = getopt('', ['event:']);
$id = $options['event'] ?? '';
if (!is_string($id) || !ctype_digit($id) || (int) $id < 1) {
    fwrite(STDERR, "Supply one completed event ID: php scripts/validate-player-ability.php --event=ID\n");
    exit(1);
}
$connection = Illuminate\Support\Facades\DB::connection();
try {
    if ($connection->getDriverName() === 'mysql') { $connection->statement('SET TRANSACTION READ ONLY'); }
    elseif ($connection->getDriverName() === 'sqlite') { $connection->statement('PRAGMA query_only = ON'); }
    elseif ($connection->getDriverName() === 'pgsql') { $connection->statement('SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY'); }
    else { throw new RuntimeException('Unsupported read-only transaction driver.'); }
    $connection->beginTransaction();
    $event = App\Models\Event::find((int) $id);
    if (!$event) { throw new RuntimeException('Event unavailable.'); }
    $report = app(App\Services\Performance\PlayerAbilityValidationService::class)->run($event);
    fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
    $connection->rollBack();
    exit(0);
} catch (Throwable) {
    if ($connection->transactionLevel() > 0) { $connection->rollBack(); }
    fwrite(STDERR, "Validation withheld: invalid/completed-event requirement, processing limit, failed fit or changed sources. No snapshot was written.\n");
    exit(1);
}
