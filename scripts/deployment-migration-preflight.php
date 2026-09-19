<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

const MIGRATION_PATH_PATTERN = '#^database/migrations/[A-Za-z0-9][A-Za-z0-9_.-]*\.php$#';

function failPreflight(string $message): never
{
    fwrite(STDERR, "Migration preflight failed: {$message}\n");
    exit(1);
}

/** @return array<string, string> */
function parseArguments(array $arguments): array
{
    $parsed = [];

    foreach (array_slice($arguments, 1) as $argument) {
        if (! preg_match('/^--([a-z-]+)=(.*)$/s', $argument, $matches)) {
            failPreflight("Unsupported argument: {$argument}");
        }

        $parsed[$matches[1]] = $matches[2];
    }

    foreach (['deploy-config', 'target-migrations', 'approved-migrations', 'pending-output'] as $required) {
        if (! array_key_exists($required, $parsed) || $parsed[$required] === '') {
            failPreflight("Missing --{$required}");
        }
    }

    $hasAppPath = isset($parsed['app-path']) && $parsed['app-path'] !== '';
    $hasAppliedFixture = isset($parsed['applied-migrations']) && $parsed['applied-migrations'] !== '';

    if ($hasAppPath === $hasAppliedFixture) {
        failPreflight('Supply exactly one of --app-path or --applied-migrations');
    }

    return $parsed;
}

/** @return list<string> */
function readStrictLines(string $path, string $label, string $pattern): array
{
    $contents = file_get_contents($path);
    if ($contents === false) {
        failPreflight("Unable to read {$label}");
    }

    $contents = str_replace("\r\n", "\n", $contents);
    $contents = rtrim($contents, "\n");
    if ($contents === '') {
        return [];
    }

    $lines = explode("\n", $contents);
    foreach ($lines as $line) {
        if ($line === '' || trim($line) !== $line || ! preg_match($pattern, $line)) {
            failPreflight("Invalid {$label} entry: {$line}");
        }
    }

    if (count($lines) !== count(array_unique($lines))) {
        failPreflight("Duplicate {$label} entries are not allowed");
    }

    sort($lines, SORT_STRING);

    return $lines;
}

/** @return list<string> */
function readAppliedMigrations(array $arguments): array
{
    if (isset($arguments['applied-migrations'])) {
        return readStrictLines(
            $arguments['applied-migrations'],
            'applied migration',
            '/^[A-Za-z0-9][A-Za-z0-9_.-]*$/'
        );
    }

    $appPath = realpath($arguments['app-path']);
    if ($appPath === false || ! is_file($appPath.'/vendor/autoload.php') || ! is_file($appPath.'/bootstrap/app.php')) {
        failPreflight('Application bootstrap files are unavailable');
    }

    require $appPath.'/vendor/autoload.php';
    $app = require $appPath.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    /** @var list<string> $applied */
    $applied = array_map(
        static fn (mixed $migration): string => (string) $migration,
        $app->make('db')->table('migrations')->pluck('migration')->all()
    );
    sort($applied, SORT_STRING);

    return $applied;
}

/** @return array{bool, list<string>} */
function readReleaseMigrationConfig(string $path): array
{
    $config = file_get_contents($path);
    if ($config === false) {
        failPreflight('Unable to read exact-release deploy.config');
    }
    $config = str_replace("\r\n", "\n", $config);
    if (str_contains($config, "\r")) {
        failPreflight('Exact-release deploy.config contains unsupported carriage returns');
    }

    preg_match_all('/^RUN_MIGRATIONS=(true|false)$/m', $config, $runMatches);
    if (count($runMatches[1]) !== 1) {
        failPreflight('Exact-release deploy.config must define RUN_MIGRATIONS once');
    }

    preg_match_all('/^MIGRATION_PATHS=/m', $config, $pathDefinitions);
    preg_match_all('/^MIGRATION_PATHS="([^"]*)"$/ms', $config, $pathMatches);
    if (count($pathDefinitions[0]) > 1) {
        failPreflight('Exact-release deploy.config must not define MIGRATION_PATHS more than once');
    }
    if (count($pathDefinitions[0]) === 1 && count($pathMatches[1]) !== 1) {
        failPreflight('Exact-release deploy.config has a malformed MIGRATION_PATHS assignment');
    }

    $rawMigrationPaths = trim($pathMatches[1][0] ?? '');
    $migrationPaths = $rawMigrationPaths === ''
        ? []
        : preg_split('/\s+/', $rawMigrationPaths);

    foreach ($migrationPaths as $migrationPath) {
        if (! preg_match(MIGRATION_PATH_PATTERN, $migrationPath)) {
            failPreflight("Invalid release allowlist entry: {$migrationPath}");
        }
    }

    if (count($migrationPaths) !== count(array_unique($migrationPaths))) {
        failPreflight('Duplicate release allowlist entries are not allowed');
    }

    sort($migrationPaths, SORT_STRING);

    return [$runMatches[1][0] === 'true', $migrationPaths];
}

$arguments = parseArguments($argv);
$targetMigrations = readStrictLines(
    $arguments['target-migrations'],
    'target migration',
    MIGRATION_PATH_PATTERN
);
$approvedMigrations = readStrictLines(
    $arguments['approved-migrations'],
    'approved migration',
    MIGRATION_PATH_PATTERN
);
[$runMigrations, $allowlistedMigrations] = readReleaseMigrationConfig($arguments['deploy-config']);

$appliedMigrations = array_flip(readAppliedMigrations($arguments));
$pendingMigrations = [];
foreach ($targetMigrations as $targetMigration) {
    $migrationName = basename($targetMigration, '.php');
    if (! isset($appliedMigrations[$migrationName])) {
        $pendingMigrations[] = $targetMigration;
    }
}

if (! $runMigrations) {
    if ($pendingMigrations !== []) {
        failPreflight('Target migrations are pending while RUN_MIGRATIONS=false: '.implode(', ', $pendingMigrations));
    }
    if ($approvedMigrations !== []) {
        failPreflight('Approval must be empty when RUN_MIGRATIONS=false');
    }

    file_put_contents($arguments['pending-output'], '');
    fwrite(STDOUT, "Migration preflight passed: migrations are disabled for this release.\n");
    exit(0);
}

if ($allowlistedMigrations === []) {
    failPreflight('RUN_MIGRATIONS=true requires a non-empty MIGRATION_PATHS allowlist');
}

$unknownAllowlistEntries = array_values(array_diff($allowlistedMigrations, $targetMigrations));
if ($unknownAllowlistEntries !== []) {
    failPreflight('Release allowlist contains files absent from the target commit: '.implode(', ', $unknownAllowlistEntries));
}

$unallowlistedPending = array_values(array_diff($pendingMigrations, $allowlistedMigrations));
if ($unallowlistedPending !== []) {
    failPreflight('Pending migrations are not release-allowlisted: '.implode(', ', $unallowlistedPending));
}

$nonPendingApprovals = array_values(array_diff($approvedMigrations, $pendingMigrations));
if ($nonPendingApprovals !== []) {
    failPreflight('Approval contains migrations that are not pending: '.implode(', ', $nonPendingApprovals));
}

$missingApprovals = array_values(array_diff($pendingMigrations, $approvedMigrations));
if ($missingApprovals !== []) {
    failPreflight('Pending migrations lack explicit per-run approval: '.implode(', ', $missingApprovals));
}

$pendingContents = $pendingMigrations === [] ? '' : implode("\n", $pendingMigrations)."\n";
if (file_put_contents($arguments['pending-output'], $pendingContents) === false) {
    failPreflight('Unable to write locked pending migration list');
}

$incidentMigration = 'database/migrations/2026_09_15_120000_reconcile_wilson_masters_registration_incidents.php';
if (in_array($incidentMigration, $pendingMigrations, true)) {
    fwrite(STDOUT, "Explicit per-run approval accepted for incident migration: {$incidentMigration}\n");
}

fwrite(STDOUT, 'Migration preflight passed: '.count($pendingMigrations)." exact pending migration(s) approved.\n");
