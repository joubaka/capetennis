<?php

// Private local CLI only. No Artisan lifecycle or snapshot/cache writer.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    $options = getopt('', ['events:', 'assume-compatible-context']);
    $ids = App\Services\Performance\PlayerAbilityValidationService::eventIds(['events' => $options['events'] ?? null]);
    $connection = (new Illuminate\Support\ConfigurationUrlParser)->parseConfiguration(config('database.connections.'.config('database.default')));
    if (!$app->environment('local') || ($connection['driver'] ?? '') !== 'mysql' || !is_string($connection['host'] ?? null)
        || !in_array(strtolower($connection['host']), ['localhost', '127.0.0.1', '::1'], true)
        || isset($connection['read']) || isset($connection['write']) || isset($connection['direct'])) { throw new RuntimeException('Local database required.'); }
    $before = App\Services\Performance\CohortTransitionValidationService::savedDigest();
    $reports = app(App\Services\Performance\PlayerAbilityConsistentRead::class)->run(function () use ($ids, $options) {
        $reports = [];
        foreach ($ids as $id) {
            $event = App\Models\Event::findOrFail($id);
            fwrite(STDERR, 'Evaluating transition holdout '.$id."\n");
            $reports[] = app(App\Services\Performance\CohortTransitionValidationService::class)->run($event, isset($options['assume-compatible-context']));
        }
        return $reports;
    });
    $after = App\Services\Performance\CohortTransitionValidationService::savedDigest();
    if (!hash_equals($before, $after)) { throw new RuntimeException('Saved snapshot changed during experiment.'); }
    echo json_encode(['preview_only' => true, 'saved_snapshot_digest_before' => $before, 'saved_snapshot_digest_after' => $after,
        'saved_ratings_unchanged' => true, 'reports' => $reports], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
} catch (Throwable) {
    fwrite(STDERR, "Transition validation withheld: local configuration, event, source or calculation safety check failed. No experimental snapshot was saved.\n");
    exit(1);
}
