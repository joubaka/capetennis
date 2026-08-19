<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PayFastMigrationCompatibilityTest extends TestCase
{
    public function test_unique_payment_id_migration_is_driver_neutral_and_retry_safe(): void
    {
        $originalConnection = DB::getDefaultConnection();

        config()->set('database.connections.migration_compatibility', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::setDefaultConnection('migration_compatibility');

        try {
            Schema::create('transactions_pf', function (Blueprint $table): void {
                $table->id();
                $table->string('pf_payment_id')->nullable();
            });

            $migration = require database_path(
                'migrations/2026_06_01_000001_add_unique_pf_payment_id_to_transactions_pf.php'
            );

            $migration->up();
            $migration->up();

            $index = collect(Schema::getIndexes('transactions_pf'))->firstWhere(
                'name',
                'transactions_pf_pf_payment_id_unique'
            );

            $this->assertNotNull($index);
            $this->assertTrue($index['unique']);
        } finally {
            DB::purge('migration_compatibility');
            DB::setDefaultConnection($originalConnection);
        }
    }
}
