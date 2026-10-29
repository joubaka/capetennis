<?php

namespace Tests\Unit;

use Illuminate\Cache\CacheManager;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

require_once dirname(__DIR__, 2).'/scripts/run-local-ratings-refresh.php';

final class LocalRatingsRefreshTaskTest extends TestCase
{
    public function test_runner_rejects_nonlocal_or_remote_connections_before_invoking_refresh(): void
    {
        foreach ([['production', 'mysql', '127.0.0.1'], ['testing', 'mysql', 'localhost'], ['local', 'sqlite', 'localhost'], ['local', 'mysql', 'remote.example'], ['local', 'mysql', '']] as [$environment, $driver, $host]) {
            $called = false;
            try {
                \LocalRatingsRefreshRunner::run($environment, compact('driver', 'host'), function () use (&$called): int { $called = true; return 0; });
                $this->fail('Unsafe local task configuration accepted.');
            } catch (\RuntimeException) {
                $this->assertFalse($called);
            }
        }
    }

    public function test_runner_preserves_canonical_refresh_exit_code_for_loopback_hosts(): void
    {
        foreach (['localhost', '127.0.0.1', '::1'] as $host) {
            $this->assertSame(7, \LocalRatingsRefreshRunner::run('local', ['driver' => 'mysql', 'host' => $host], fn (): int => 7));
        }
    }

    public function test_runner_rejects_split_hosts_and_url_overrides_before_refresh(): void
    {
        $unsafe = [
            ['driver' => 'mysql', 'host' => ['localhost', 'remote.example']],
            ['driver' => 'mysql', 'host' => 'localhost', 'read' => ['host' => ['remote.example']]],
            ['driver' => 'mysql', 'host' => 'localhost', 'write' => ['host' => 'remote.example']],
            (new \Illuminate\Support\ConfigurationUrlParser)->parseConfiguration(['driver' => 'mysql', 'host' => 'localhost', 'url' => 'mysql://example:example@remote.example/ct']),
        ];
        foreach ($unsafe as $configuration) {
            try {
                \LocalRatingsRefreshRunner::run('local', $configuration, fn () => $this->fail('Remote database accepted.'));
                $this->fail('Unsafe database accepted.');
            } catch (\RuntimeException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_file_builder_lock_blocks_another_process_and_releases_without_default_cache_state(): void
    {
        $directory = sys_get_temp_dir().'/ct-rating-lock-'.bin2hex(random_bytes(8));
        $files = new Filesystem;
        $container = new Container;
        $container->instance('config', new Repository(['cache' => ['default' => 'array', 'stores' => [
            'array' => ['driver' => 'array'],
            'file' => ['driver' => 'file', 'path' => $directory],
        ]]]));
        $container->instance('files', $files);
        $manager = new CacheManager($container);
        $this->assertSame('array', $manager->getDefaultDriver());
        $store = $manager->store('file');
        $lock = $store->lock('player-ability:refresh', 60);
        $this->assertTrue($lock->get());
        $program = 'require $argv[1]; $store = new Illuminate\\Cache\\FileStore(new Illuminate\\Filesystem\\Filesystem, $argv[2]); $lock = $store->lock("player-ability:refresh", 60); $held = $lock->get(); echo $held ? "acquired" : "blocked"; if ($held) $lock->release();';
        try {
            $process = new Process([PHP_BINARY, '-r', $program, dirname(__DIR__, 2).'/vendor/autoload.php', $directory]);
            $process->mustRun();
            $this->assertSame('blocked', $process->getOutput());
            $lock->release();
            $process->mustRun();
            $this->assertSame('acquired', $process->getOutput());
        } finally {
            $lock->release();
            $files->deleteDirectory($directory);
        }
    }
}
