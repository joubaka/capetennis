<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CategoryEventRegistrationStatusMigrationTest extends TestCase
{
    private string $originalConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalConnection = config('database.default');
        if (DB::getDriverName() !== 'mysql') {
            $this->markTestSkipped('The legacy status enum requires MySQL.');
        }

        $connection = config('database.connections.'.$this->originalConnection);
        $connection['prefix'] = 'status_migration_test_';
        config(['database.connections.status_migration_test' => $connection]);
        DB::setDefaultConnection('status_migration_test');
        Schema::create('category_event_registrations', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['active', 'withdrawn'])->default('active');
            $table->unsignedTinyInteger('payment_status_id')->nullable();
        });
    }

    protected function tearDown(): void
    {
        if (DB::getDefaultConnection() === 'status_migration_test') {
            Schema::dropIfExists('category_event_registrations');
            DB::setDefaultConnection($this->originalConnection);
            DB::purge('status_migration_test');
        }

        parent::tearDown();
    }

    public function test_legacy_enum_upgrade_preserves_rows_and_accepts_pending_checkout(): void
    {
        DB::table('category_event_registrations')->insert([
            ['id' => 1, 'status' => 'active', 'payment_status_id' => 2],
            ['id' => 2, 'status' => 'withdrawn', 'payment_status_id' => 2],
        ]);
        $before = DB::table('category_event_registrations')->orderBy('id')->get()->toArray();
        $migration = $this->migration();

        $migration->up();
        $migration->up();

        $this->assertEquals($before, DB::table('category_event_registrations')->orderBy('id')->get()->toArray());
        DB::table('category_event_registrations')->insert(['id' => 3, 'status' => 'pending_checkout']);
        DB::table('category_event_registrations')->insert(['id' => 4]);

        $migration->down();

        $this->assertSame('pending_checkout', DB::table('category_event_registrations')->where('id', 3)->value('status'));
        $this->assertNull(DB::table('category_event_registrations')->where('id', 3)->value('payment_status_id'));
        $this->assertSame('active', DB::table('category_event_registrations')->where('id', 4)->value('status'));
        $this->assertSame(4, DB::table('category_event_registrations')->count());
    }

    public function test_upgrade_is_safe_when_the_registration_table_is_absent(): void
    {
        Schema::drop('category_event_registrations');

        $this->migration()->up();

        $this->assertFalse(Schema::hasTable('category_event_registrations'));
    }

    public function test_upgrade_preserves_nullable_and_historical_statuses(): void
    {
        Schema::table('category_event_registrations', function (Blueprint $table) {
            $table->enum('status', ['active', 'withdrawn', 'historical'])->nullable()->default('active')->change();
        });
        DB::table('category_event_registrations')->insert([
            ['id' => 1, 'status' => null],
            ['id' => 2, 'status' => 'historical'],
        ]);

        $this->migration()->up();

        $this->assertNull(DB::table('category_event_registrations')->where('id', 1)->value('status'));
        $this->assertSame('historical', DB::table('category_event_registrations')->where('id', 2)->value('status'));
        $this->assertSame(2, DB::table('category_event_registrations')->count());
    }

    private function migration(): \Illuminate\Database\Migrations\Migration
    {
        return require database_path('migrations/2026_10_02_000001_align_category_event_registration_status.php');
    }
}
