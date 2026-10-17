<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RefundStatusMigrationTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalConnection = DB::getDefaultConnection();
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('Legacy refund ENUM coverage requires MySQL.');
        }

        $connection = config('database.connections.'.$this->originalConnection);
        $connection['prefix'] = 'refund_status_test_'.getmypid().'_';
        config(['database.connections.refund_status_test' => $connection]);
        DB::setDefaultConnection('refund_status_test');
    }

    protected function tearDown(): void
    {
        if (DB::getDefaultConnection() === 'refund_status_test') {
            foreach (['category_event_registrations', 'team_payment_orders'] as $table) {
                Schema::dropIfExists($table);
            }
            DB::setDefaultConnection($this->originalConnection);
            DB::purge('refund_status_test');
        }
        parent::tearDown();
    }

    public static function columnDefinitions(): array
    {
        return [
            'non-null default' => [false, 'not_refunded'],
            'nullable default' => [true, 'pending'],
            'nullable null default' => [true, null],
        ];
    }

    #[DataProvider('columnDefinitions')]
    public function test_legacy_refund_enums_accept_waivers_without_losing_history(bool $nullable, ?string $default): void
    {
        $tables = ['category_event_registrations', 'team_payment_orders'];
        $before = [];
        foreach ($tables as $table) {
            Schema::create($table, function (Blueprint $blueprint) use ($nullable, $default): void {
                $blueprint->id();
                $blueprint->enum('refund_status', ['not_refunded', 'pending', 'processing', 'completed', 'historical'])
                    ->nullable($nullable)->default($default)->comment('Refund audit state');
                $blueprint->decimal('refund_net', 10, 2)->default(285);
            });
            foreach (['not_refunded', 'pending', 'processing', 'completed', 'historical'] as $status) {
                DB::table($table)->insert(['refund_status' => $status]);
            }
            if ($nullable) {
                DB::table($table)->insert(['refund_status' => null]);
            }
            $before[$table] = DB::table($table)->orderBy('id')->get()->toArray();

            try {
                DB::table($table)->where('id', 2)->update(['refund_status' => 'waived']);
                $this->fail('The legacy ENUM must reproduce the rejected waiver.');
            } catch (QueryException $exception) {
                $this->assertSame(1265, $exception->errorInfo[1]);
            }
        }

        $migration = require database_path('migrations/2026_10_16_000001_allow_waived_refund_status.php');
        $migration->up();
        $migration->up();

        foreach ($tables as $table) {
            $this->assertEquals($before[$table], DB::table($table)->orderBy('id')->get()->toArray());
            $column = collect(Schema::getColumns($table))->firstWhere('name', 'refund_status');
            $this->assertSame('varchar', $column['type_name']);
            $this->assertSame($nullable, $column['nullable']);
            $this->assertSame($default, $column['default']);
            $this->assertSame('Refund audit state', $column['comment']);
            DB::table($table)->where('id', 2)->update(['refund_status' => 'waived']);
            $id = DB::table($table)->insertGetId([]);
            $this->assertSame($default, DB::table($table)->where('id', $id)->value('refund_status'));
            $this->assertSame('285.00', DB::table($table)->where('id', 2)->value('refund_net'));
            $this->assertSame(count($before[$table]) + 1, DB::table($table)->count());
        }

        $migration->down();
        foreach ($tables as $table) {
            $this->assertSame('waived', DB::table($table)->where('id', 2)->value('refund_status'));
        }
    }

    public function test_missing_tables_and_columns_are_safe(): void
    {
        Schema::create('team_payment_orders', fn (Blueprint $table) => $table->id());
        $migration = require database_path('migrations/2026_10_16_000001_allow_waived_refund_status.php');
        $migration->up();
        $this->assertFalse(Schema::hasTable('category_event_registrations'));
        $this->assertFalse(Schema::hasColumn('team_payment_orders', 'refund_status'));
    }

    public function test_canonical_string_columns_are_unchanged(): void
    {
        foreach (['category_event_registrations', 'team_payment_orders'] as $table) {
            Schema::create($table, function (Blueprint $blueprint): void {
                $blueprint->id();
                $blueprint->string('refund_status', 80)->nullable()->default('pending');
            });
            DB::table($table)->insert(['refund_status' => 'waived']);
        }

        $migration = require database_path('migrations/2026_10_16_000001_allow_waived_refund_status.php');
        $migration->up();

        foreach (['category_event_registrations', 'team_payment_orders'] as $table) {
            $column = collect(Schema::getColumns($table))->firstWhere('name', 'refund_status');
            $this->assertSame('varchar(80)', $column['type']);
            $this->assertSame('pending', $column['default']);
            $this->assertTrue($column['nullable']);
            $this->assertSame('waived', DB::table($table)->value('refund_status'));
            $this->assertSame(1, DB::table($table)->count());
        }
    }
}
