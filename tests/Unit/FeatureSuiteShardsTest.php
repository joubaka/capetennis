<?php

namespace Tests\Unit;

use CapeTennis\CI\FeatureSuiteShards;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once dirname(__DIR__, 2).'/scripts/feature-suite-shards.php';

class FeatureSuiteShardsTest extends TestCase
{
    public function test_each_discovered_feature_file_is_in_exactly_one_nonempty_partition(): void
    {
        $files = FeatureSuiteShards::discover(dirname(__DIR__, 2));
        $partitions = FeatureSuiteShards::partition($files, 4);
        $assigned = array_merge(...$partitions);
        sort($assigned, SORT_STRING);
        $this->assertSame($files, $assigned);
        $this->assertCount(count($files), array_unique($assigned));
        foreach ($partitions as $partition) {
            $this->assertNotEmpty($partition);
        }
        $sizes = array_map('count', $partitions);
        $this->assertLessThanOrEqual(1, max($sizes) - min($sizes));
        $this->assertSame($partitions, FeatureSuiteShards::partition(array_reverse($files), 4));
    }

    #[DataProvider('invalidPartitions')]
    public function test_invalid_or_partial_inventories_cannot_produce_green_empty_shards(array $files, int $count): void
    {
        $this->expectException(InvalidArgumentException::class);
        FeatureSuiteShards::partition($files, $count);
    }

    public static function invalidPartitions(): array
    {
        return [[[], 4], [['a'], 0], [['a'], 2], [['a', 'a'], 1]];
    }

    public function test_only_isolated_memory_databases_and_fake_mail_are_allowed(): void
    {
        FeatureSuiteShards::assertEnvironment($this->safeEnvironment());
        $this->addToAssertionCount(1);
    }

    public function test_cached_application_configuration_is_rejected_before_discovery(): void
    {
        $directory = sys_get_temp_dir().'/ct-feature-shard-'.bin2hex(random_bytes(8));
        mkdir($directory.'/bootstrap/cache', 0775, true);
        file_put_contents($directory.'/bootstrap/cache/config.php', '<?php return [];');
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('uncached test checkout');
            FeatureSuiteShards::discover($directory);
        } finally {
            unlink($directory.'/bootstrap/cache/config.php');
            rmdir($directory.'/bootstrap/cache');
            rmdir($directory.'/bootstrap');
            rmdir($directory);
        }
    }

    public function test_an_existing_alternate_configuration_cache_cannot_override_memory_database_guards(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'ct-shard-cache-');
        file_put_contents($file, '<?php return [];');
        try {
            $this->expectException(RuntimeException::class);
            FeatureSuiteShards::assertEnvironment(array_replace($this->safeEnvironment(), ['APP_CONFIG_CACHE' => $file]));
        } finally {
            unlink($file);
        }
    }

    #[DataProvider('unsafeOverrides')]
    public function test_unsafe_runtime_overrides_are_rejected(string $key, string $value): void
    {
        $this->expectException(RuntimeException::class);
        FeatureSuiteShards::assertEnvironment(array_replace($this->safeEnvironment(), [$key => $value]));
    }

    public static function unsafeOverrides(): array
    {
        return [['APP_ENV', 'production'], ['DB_CONNECTION', 'mysql'], ['DB_DATABASE', 'local.sqlite'],
            ['CT_TEST_ARRAY_TRANSPORTS', '0'], ['DATABASE_URL', 'mysql://localhost/ct_testing'], ['APP_CONFIG_CACHE', '/alternate/config.php']];
    }

    private function safeEnvironment(): array
    {
        return ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:',
            'CT_TEST_ARRAY_TRANSPORTS' => '1', 'DATABASE_URL' => '', 'APP_CONFIG_CACHE' => ''];
    }
}
