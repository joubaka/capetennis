<?php

declare(strict_types=1);

namespace CapeTennis\CI;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/** Fresh-process partitions of PHPUnit's configured Feature suite, never a test filter. */
final class FeatureSuiteShards
{
    public static function assertEnvironment(array $environment): void
    {
        if (($environment['APP_ENV'] ?? '') !== 'testing'
            || ($environment['DB_CONNECTION'] ?? '') !== 'sqlite'
            || ($environment['DB_DATABASE'] ?? '') !== ':memory:'
            || ($environment['CT_TEST_ARRAY_TRANSPORTS'] ?? '') !== '1'
            || trim((string) ($environment['DATABASE_URL'] ?? '')) !== ''
            || trim((string) ($environment['APP_CONFIG_CACHE'] ?? '')) !== '') {
            throw new RuntimeException('Feature shards require testing, SQLite :memory:, array transports and no database URL or configuration-cache override.');
        }
    }

    public static function discover(string $root): array
    {
        if (is_file($root.'/bootstrap/cache/config.php')) {
            throw new RuntimeException('Feature shards require an uncached test checkout.');
        }
        $process = new Process([PHP_BINARY, '-d', 'memory_limit=512M', $root.'/vendor/bin/phpunit',
            '--configuration', $root.'/phpunit.xml', '--testsuite', 'Feature', '--list-test-files', '--no-logging', '--no-coverage'], $root,
            ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DATABASE_URL' => '',
                'APP_CONFIG_CACHE' => $root.'/bootstrap/cache/config.php', 'CT_TEST_ARRAY_TRANSPORTS' => '1']);
        $process->setTimeout(120);
        $process->mustRun();
        $prefix = str_replace('\\', '/', (string) realpath($root)).'/';
        $files = [];
        foreach (preg_split('/\R/', $process->getOutput()) as $line) {
            if (! preg_match('/^\s+- (.+)$/', $line, $match)) {
                continue;
            }
            $file = str_replace('\\', '/', (string) realpath(trim($match[1])));
            if (! str_starts_with($file, $prefix.'tests/Feature/') || ! str_ends_with($file, 'Test.php') || ! is_file($file)) {
                throw new RuntimeException('PHPUnit returned an unexpected Feature test path.');
            }
            $files[] = substr($file, strlen($prefix));
        }
        if (! $files || count(array_unique($files)) !== count($files)) {
            throw new RuntimeException('PHPUnit must discover a nonempty, unique Feature inventory.');
        }
        sort($files, SORT_STRING);

        return $files;
    }

    public static function partition(array $files, int $count): array
    {
        if (! $files || $count < 1 || $count > count($files) || count(array_unique($files)) !== count($files)) {
            throw new InvalidArgumentException('Feature shards require unique files and no empty partitions.');
        }
        sort($files, SORT_STRING);
        $partitions = array_fill(0, $count, []);
        foreach ($files as $index => $file) {
            $partitions[$index % $count][] = $file;
        }

        return $partitions;
    }

    public static function main(array $arguments): int
    {
        try {
            $options = ['shards' => 4];
            $manifestOnly = false;
            foreach (array_slice($arguments, 1) as $argument) {
                if ($argument === '--manifest-only') {
                    $manifestOnly = true;
                } elseif (preg_match('/^--(shard|shards)=([1-9][0-9]*)$/D', $argument, $match)) {
                    $options[$match[1]] = (int) $match[2];
                } else {
                    throw new InvalidArgumentException('Use --shard=N --shards=N [--manifest-only].');
                }
            }
            if (! isset($options['shard']) || $options['shard'] > $options['shards']) {
                throw new InvalidArgumentException('Choose a shard between 1 and the shard count.');
            }
            $environment = [];
            foreach (['APP_ENV', 'DB_CONNECTION', 'DB_DATABASE', 'CT_TEST_ARRAY_TRANSPORTS', 'DATABASE_URL', 'APP_CONFIG_CACHE'] as $key) {
                $value = getenv($key);
                $environment[$key] = $value === false ? '' : $value;
            }
            self::assertEnvironment($environment);
            $root = dirname(__DIR__);
            $files = self::discover($root);
            $partitions = self::partition($files, $options['shards']);
            $selected = $partitions[$options['shard'] - 1];
            $manifest = ['suite' => 'Feature', 'shard' => $options['shard'], 'shards' => $options['shards'],
                'total_files' => count($files), 'inventory_sha256' => hash('sha256', implode("\n", $files)), 'files' => $selected];
            $json = json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
            if ($manifestOnly) {
                echo $json;

                return 0;
            }
            $directory = $root.'/storage/framework/testing/feature-shards';
            if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
                throw new RuntimeException('Could not create the feature-shard report directory.');
            }
            if (file_put_contents($directory.'/shard-'.$options['shard'].'.json', $json) === false) {
                throw new RuntimeException('Could not save the feature-shard inventory.');
            }
            echo 'Feature shard '.$options['shard'].'/'.$options['shards'].': '.count($selected).'/'.count($files).' files'.PHP_EOL;
            $process = new Process([PHP_BINARY, '-d', 'memory_limit=512M', $root.'/vendor/bin/phpunit',
                '--configuration', $root.'/phpunit.xml', '--log-junit', $directory.'/shard-'.$options['shard'].'.xml', ...$selected], $root,
                ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'DATABASE_URL' => '',
                    'APP_CONFIG_CACHE' => $root.'/bootstrap/cache/config.php', 'CT_TEST_ARRAY_TRANSPORTS' => '1']);
            $process->setTimeout(null);

            return $process->run(static function (string $type, string $buffer): void {
                fwrite($type === Process::ERR ? STDERR : STDOUT, $buffer);
            });
        } catch (Throwable $error) {
            fwrite(STDERR, $error->getMessage().PHP_EOL);

            return 2;
        }
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__) {
    require_once dirname(__DIR__).'/vendor/autoload.php';
    exit(FeatureSuiteShards::main($argv));
}
