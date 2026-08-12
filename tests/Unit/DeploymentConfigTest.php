<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class DeploymentConfigTest extends TestCase
{
    public function test_release_allowlists_every_required_migration(): void
    {
        $config = file_get_contents(dirname(__DIR__, 2).'/deploy.config');

        foreach ([
            '2026_08_06_000001_add_device_fields_to_authentication_log_table.php',
            '2026_08_12_000001_add_series_ranking_snapshot_indexes.php',
            '2026_08_12_000002_add_sort_order_to_ranking_list_category_events.php',
        ] as $migration) {
            $this->assertStringContainsString($migration, $config);
        }
    }
}
