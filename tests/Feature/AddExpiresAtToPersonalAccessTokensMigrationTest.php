<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AddExpiresAtToPersonalAccessTokensMigrationTest extends TestCase
{
    public function test_it_upgrades_a_legacy_sanctum_table_and_is_retry_safe(): void
    {
        $originalConnection = config('database.default');
        $connection = 'sanctum_migration_test';

        config()->set("database.connections.{$connection}", [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('database.default', $connection);
        DB::purge($connection);

        try {
            Schema::create('personal_access_tokens', function (Blueprint $table) {
                $table->id();
                $table->timestamp('last_used_at')->nullable();
            });

            $migration = require database_path(
                'migrations/2026_08_20_120000_add_expires_at_to_personal_access_tokens.php'
            );

            $migration->up();
            $migration->up();

            $this->assertTrue(Schema::hasColumn('personal_access_tokens', 'expires_at'));
        } finally {
            DB::purge($connection);
            config()->set('database.default', $originalConnection);
        }
    }
}
