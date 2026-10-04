<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TeamPaymentBeneficiaryTransferMigrationTest extends TestCase
{
    private string $originalConnection;

    private string $connection = 'beneficiary_transfer_migration_test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = DB::getDefaultConnection();
        $configuration = config('database.connections.'.$this->originalConnection);
        $configuration['prefix'] = 'btm_';
        config()->set('database.connections.'.$this->connection, $configuration);
        DB::setDefaultConnection($this->connection);
        DB::purge($this->connection);

        Schema::dropIfExists('team_payment_transfers');
        Schema::dropIfExists('team_payment_orders');
        $this->createLegacyOrdersTable();
    }

    protected function tearDown(): void
    {
        if (DB::getDefaultConnection() === $this->connection) {
            Schema::dropIfExists('team_payment_transfers');
            Schema::dropIfExists('team_payment_orders');
            DB::setDefaultConnection($this->originalConnection);
            DB::purge($this->connection);
        }

        parent::tearDown();
    }

    public function test_upgrade_recovers_from_an_early_partial_run_and_is_retry_safe(): void
    {
        Schema::table('team_payment_orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('beneficiary_player_id')->nullable();
        });

        $migration = $this->migration();
        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasColumn('team_payment_orders', 'beneficiary_player_id'));
        $this->assertTrue(Schema::hasColumn('team_payment_orders', 'effective_player_id'));
        $this->assertTrue(Schema::hasIndex('team_payment_orders', 'team_payment_orders_effective_active_unique'));
        $this->assertFalse(Schema::hasIndex('team_payment_orders', 'team_payment_orders_one_active_order_unique'));
        $this->assertTrue(Schema::hasTable('team_payment_transfers'));
        $this->assertTrue(Schema::hasIndex('team_payment_transfers', 'team_payment_transfers_order_id_index'));
    }

    public function test_upgrade_recovers_after_the_replacement_constraint_was_created(): void
    {
        Schema::table('team_payment_orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('beneficiary_player_id')->nullable();
            $table->unsignedBigInteger('effective_player_id')
                ->nullable()
                ->storedAs('COALESCE(beneficiary_player_id, player_id)');
            $table->unique(
                ['team_id', 'effective_player_id', 'event_id', 'active_order_marker'],
                'team_payment_orders_effective_active_unique',
            );
        });
        $this->createTransferTableWithoutIndex();

        $this->migration()->up();

        $this->assertTrue(Schema::hasIndex('team_payment_orders', 'team_payment_orders_effective_active_unique'));
        $this->assertFalse(Schema::hasIndex('team_payment_orders', 'team_payment_orders_one_active_order_unique'));
        $this->assertTrue(Schema::hasTable('team_payment_transfers'));
        $this->assertTrue(Schema::hasIndex('team_payment_transfers', 'team_payment_transfers_order_id_index'));
    }

    public function test_upgrade_resumes_after_index_swap_before_transfer_table_creation(): void
    {
        Schema::table('team_payment_orders', function (Blueprint $table): void {
            $table->unsignedBigInteger('beneficiary_player_id')->nullable();
            $table->unsignedBigInteger('effective_player_id')
                ->nullable()
                ->storedAs('COALESCE(beneficiary_player_id, player_id)');
            $table->unique(
                ['team_id', 'effective_player_id', 'event_id', 'active_order_marker'],
                'team_payment_orders_effective_active_unique',
            );
            $table->dropUnique('team_payment_orders_one_active_order_unique');
        });

        $this->assertFalse(Schema::hasTable('team_payment_transfers'));
        $this->assertTrue(Schema::hasIndex('team_payment_orders', 'team_payment_orders_effective_active_unique'));
        $this->assertFalse(Schema::hasIndex('team_payment_orders', 'team_payment_orders_one_active_order_unique'));

        $migration = $this->migration();
        $migration->up();
        $migration->up();

        $this->assertTrue(Schema::hasTable('team_payment_transfers'));
        $this->assertTrue(Schema::hasIndex('team_payment_transfers', 'team_payment_transfers_order_id_index'));
        $this->assertTrue(Schema::hasIndex('team_payment_orders', 'team_payment_orders_effective_active_unique'));
        $this->assertFalse(Schema::hasIndex('team_payment_orders', 'team_payment_orders_one_active_order_unique'));
    }

    public function test_rollback_remains_blocked_when_transfer_history_exists(): void
    {
        $migration = $this->migration();
        $migration->up();
        DB::table('team_payment_transfers')->insert([
            'order_id' => 1,
            'event_id' => 2,
            'team_id' => 3,
            'original_player_id' => 4,
            'from_player_id' => 4,
            'to_player_id' => 5,
            'actor_id' => 6,
            'reason' => 'Verified test allocation',
            'created_at' => now(),
        ]);

        try {
            $migration->down();
            $this->fail('Rollback must preserve payment allocation history.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('cannot be rolled back', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasTable('team_payment_transfers'));
        $this->assertTrue(Schema::hasColumn('team_payment_orders', 'beneficiary_player_id'));
        $this->assertTrue(Schema::hasIndex('team_payment_orders', 'team_payment_orders_effective_active_unique'));
    }

    public function test_empty_rollback_is_retry_safe_and_restores_the_legacy_constraint(): void
    {
        $migration = $this->migration();
        $migration->up();

        $migration->down();
        $migration->down();

        $this->assertFalse(Schema::hasTable('team_payment_transfers'));
        $this->assertFalse(Schema::hasColumn('team_payment_orders', 'beneficiary_player_id'));
        $this->assertFalse(Schema::hasColumn('team_payment_orders', 'effective_player_id'));
        $this->assertTrue(Schema::hasIndex('team_payment_orders', 'team_payment_orders_one_active_order_unique'));
        $this->assertFalse(Schema::hasIndex('team_payment_orders', 'team_payment_orders_effective_active_unique'));
    }

    private function createLegacyOrdersTable(): void
    {
        Schema::create('team_payment_orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('player_id');
            $table->unsignedBigInteger('event_id');
            $table->timestamp('withdrawn_at')->nullable();
            $table->unsignedTinyInteger('active_order_marker')
                ->nullable()
                ->storedAs('CASE WHEN withdrawn_at IS NULL THEN 1 ELSE NULL END');
            $table->unique(
                ['team_id', 'player_id', 'event_id', 'active_order_marker'],
                'team_payment_orders_one_active_order_unique',
            );
        });
    }

    private function createTransferTableWithoutIndex(): void
    {
        Schema::create('team_payment_transfers', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('original_player_id');
            $table->unsignedBigInteger('from_player_id');
            $table->unsignedBigInteger('to_player_id');
            $table->unsignedBigInteger('actor_id');
            $table->string('reason', 1000);
            $table->timestamp('created_at');
        });
    }

    private function migration(): \Illuminate\Database\Migrations\Migration
    {
        return require database_path('migrations/2026_10_03_120000_add_team_payment_beneficiary_transfers.php');
    }
}
