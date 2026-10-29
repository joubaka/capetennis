<?php

// Local read-only operator tool. No Artisan command lifecycle or saved estimate writes.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$options = getopt('', ['events:', 'limit:', 'as-of:', 'output:']);
try {
    $cfg = (new Illuminate\Support\ConfigurationUrlParser)->parseConfiguration(config('database.connections.'.config('database.default')));
    if (!$app->environment('local') || ($cfg['driver'] ?? '') !== 'mysql'
        || !is_string($cfg['host'] ?? null) || !in_array(strtolower($cfg['host']), ['localhost', '127.0.0.1', '::1'], true)
        || isset($cfg['read']) || isset($cfg['write']) || isset($cfg['direct']) || !empty($cfg['unix_socket'])) { throw new RuntimeException('Local loopback database required.'); }
    $date = $options['as-of'] ?? Carbon\CarbonImmutable::today('Africa/Johannesburg')->toDateString();
    if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || Carbon\CarbonImmutable::parse($date)->toDateString() !== $date
        || $date > Carbon\CarbonImmutable::today('Africa/Johannesburg')->toDateString()) { throw new InvalidArgumentException('Invalid as-of date.'); }
    $limit = $options['limit'] ?? '20';
    if (!is_string($limit) || !ctype_digit($limit) || (int) $limit < 1 || (int) $limit > 20) { throw new InvalidArgumentException('Invalid event limit.'); }
    $ids = null;
    if (isset($options['events'])) {
        if (!is_string($options['events']) || !preg_match('/^[1-9]\d*(?:,[1-9]\d*){0,19}$/', $options['events'])) { throw new InvalidArgumentException('Invalid event list.'); }
        $ids = array_map('intval', explode(',', $options['events']));
        if (count(array_unique($ids)) !== count($ids)) { throw new InvalidArgumentException('Duplicate event ID.'); }
    }
    set_time_limit(0); ini_set('memory_limit', '512M');
    $progress = static fn ($phase, $event, $count) => fwrite(STDERR, $phase.' event '.$event.': '.$count." matches\n");
    $files = ['app/Services/Performance/AllAgeRatingExperimentService.php', 'app/Services/Performance/AllAgeRatingModel.php',
        'app/Services/Performance/AllAgeConnectionPolicy.php', 'app/Services/Performance/SharedAbilityModel.php',
        'app/Services/Performance/PlayerPerformanceHistoryService.php', 'app/Services/Performance/AllAgeMatchFormatResolver.php'];
    $hashes = array_combine($files, array_map(fn ($file) => hash_file('sha256', base_path($file)), $files));
    $report = app(App\Services\Performance\PlayerAbilityConsistentRead::class)->run(function () use ($date, $ids, $limit, $progress, $hashes) {
        $service = new App\Services\Performance\AllAgeRatingExperimentService;
        $sources = $service->sources(Carbon\CarbonImmutable::parse($date), $progress);
        $coverage = $service->coverageSummary($sources['matches'], Carbon\CarbonImmutable::parse($date));
        $selected = $ids ?? $service->selectEvents($sources['events'], (int) $limit);
        $report = $service->evaluate($sources['events'], $sources['matches'], $selected, $progress);
        return ['as_of' => $date, 'code_sha256' => $hashes, 'selected_event_ids' => $selected,
            'scanned_records' => $sources['scanned_records'], 'current_coverage' => $coverage, 'source_events' => array_values($sources['events'])] + $report;
    });
    foreach ($hashes as $file => $hash) { if (hash_file('sha256', base_path($file)) !== $hash) { throw new RuntimeException('Source code changed during validation.'); } }
    $json = json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
    if (isset($options['output'])) {
        $root = realpath(storage_path('framework/testing'));
        $destination = is_string($options['output']) ? $options['output'] : '';
        $parent = realpath(dirname($destination));
        if (!$root || !$parent || strcasecmp($root, $parent) !== 0 || !preg_match('/^[A-Za-z0-9._-]+\.json$/', basename($destination))) { throw new InvalidArgumentException('Output must be a JSON file directly in storage/framework/testing.'); }
        $temp = tempnam($root, 'rating-validation-');
        try { if (file_put_contents($temp, $json) === false || !rename($temp, $destination)) { throw new RuntimeException('Cannot save report.'); } }
        finally { if (is_file($temp)) { unlink($temp); } }
        fwrite(STDOUT, 'Saved aggregate report: '.$destination."\n");
    } else { fwrite(STDOUT, $json); }
} catch (Throwable $error) {
    fwrite(STDERR, 'Validation withheld ('.get_class($error).'). No saved ratings or source records were changed.'."\n");
    exit(1);
}
