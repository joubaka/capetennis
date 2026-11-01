<?php

require_once __DIR__.'/../autoload.php';

if ($argc !== 2 || ! is_file($argv[1]) || filesize($argv[1]) > 10 * 1024 * 1024) {
    fwrite(STDERR, "Usage: php evaluate-durations.php <reviewed-observations.json> (max 10 MB)\n");
    exit(1);
}
try {
    $rows = json_decode(file_get_contents($argv[1]), true, 32, JSON_THROW_ON_ERROR);
    if (! is_array($rows) || ! array_is_list($rows) || count($rows) > 20000) {
        throw new RuntimeException('Use an array of at most 20000 reviewed observations.');
    }
    echo json_encode((new \SportStack\Assistant\DurationEvaluation)->evaluate($rows), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
} catch (Throwable) {
    fwrite(STDERR, "Cannot evaluate this dataset. Check the reviewed observation schema.\n");
    exit(1);
}
