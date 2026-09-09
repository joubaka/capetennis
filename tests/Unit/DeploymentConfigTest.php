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
            '2026_08_18_120000_create_audit_events_table.php',
            '2026_08_18_120100_create_audit_daily_seals_table.php',
            '2026_09_04_213500_create_event_venue_court_allocations.php',
            '2026_09_05_000001_add_schedule_visibility_to_draw_settings.php',
            '2026_09_06_000001_add_require_full_sets_to_draw_settings.php',
            '2026_09_06_010000_repair_overberg_u10b_single_set_results.php',
            '2026_09_07_060000_add_score_format_to_draw_settings.php',
            '2026_09_09_040000_add_user_id_to_registration_order_items.php',
        ] as $migration) {
            $this->assertStringContainsString($migration, $config);
        }
    }

    public function test_deploy_fails_if_unapproved_migrations_remain_pending(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2).'/deploy.sh');

        $this->assertStringContainsString('migrate:status --pending --no-interaction --no-ansi', $script);
        $this->assertStringContainsString('Pending migrations remain after the approved migration list ran', $script);
    }

    public function test_deploy_reconciles_masters_payments_before_the_application_returns_online(): void
    {
        $script = file_get_contents(dirname(__DIR__, 2).'/deploy.sh');
        $reconciliation = 'masters:reconcile-payments --apply';
        $bringOnline = 'run_php "$APP_PATH/artisan" up; APP_IS_DOWN=0';

        $this->assertStringContainsString($reconciliation, $script);
        $this->assertStringContainsString($bringOnline, $script);
        $this->assertLessThan(
            strpos($script, $bringOnline),
            strpos($script, $reconciliation),
            'Masters payment reconciliation must complete before the application returns online.'
        );
    }
}
