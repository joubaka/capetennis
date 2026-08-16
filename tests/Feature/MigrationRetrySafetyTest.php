<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationRetrySafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_draw_migrations_can_retry_after_their_schema_already_exists(): void
    {
        foreach ([
            '2026_07_17_000001_create_team_event_formats_table.php',
            '2026_07_17_000002_create_team_ties_table.php',
            '2026_07_17_000003_extend_team_draw_tables.php',
        ] as $migrationFile) {
            $migration = require database_path("migrations/{$migrationFile}");
            $migration->up();
        }

        $this->assertTrue(Schema::hasTable('team_event_formats'));
        $this->assertTrue(Schema::hasTable('team_event_format_rubbers'));
        $this->assertTrue(Schema::hasTable('team_ties'));
        $this->assertTrue(Schema::hasColumn('draws', 'team_event_format_id'));
        $this->assertTrue(Schema::hasColumn('team_fixtures', 'team_tie_id'));
        $this->assertTrue(Schema::hasColumn('team_fixture_players', 'slot_no'));
    }
}
