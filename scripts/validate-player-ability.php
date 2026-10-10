<?php

// Local/operator CLI only: direct service invocation avoids Artisan lifecycle audit writes.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$options = getopt('', ['event:', 'events:', 'compare']);
try { $ids = App\Services\Performance\PlayerAbilityValidationService::eventIds($options); }
catch (InvalidArgumentException) {
    fwrite(STDERR, "Supply --event=ID or --events=ID,ID (at most ten completed events).\n");
    exit(1);
}
$connection = Illuminate\Support\Facades\DB::connection();
try {
    if ($connection->getDriverName() === 'mysql') { $connection->statement('SET TRANSACTION READ ONLY'); }
    elseif ($connection->getDriverName() === 'sqlite') { $connection->statement('PRAGMA query_only = ON'); }
    elseif ($connection->getDriverName() === 'pgsql') { $connection->statement('SET SESSION CHARACTERISTICS AS TRANSACTION READ ONLY'); }
    else { throw new RuntimeException('Unsupported read-only transaction driver.'); }
    $connection->beginTransaction();
    $reports = [];
    foreach ($ids as $id) {
        $event = App\Models\Event::find($id);
        if (!$event) { throw new RuntimeException('Event unavailable.'); }
        $reports[] = app(App\Services\Performance\PlayerAbilityValidationService::class)->run($event, isset($options['compare']));
    }
    $report = isset($options['event']) ? $reports[0] : ['reports' => $reports, 'interpretation' => 'Separate event holdouts; reports are not pooled across events. Each prediction compares players only within the same cohort and component; event metrics summarize those compatible pairs.'];
    fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
    $connection->rollBack();
    exit(0);
} catch (Throwable) {
    if ($connection->transactionLevel() > 0) { $connection->rollBack(); }
    fwrite(STDERR, "Validation withheld: invalid/completed-event requirement, processing limit, failed fit or changed sources. No snapshot was written.\n");
    exit(1);
}
