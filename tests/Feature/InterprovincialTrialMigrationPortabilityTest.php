<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InterprovincialTrialMigrationPortabilityTest extends TestCase
{
    public function test_migration_canonicalizes_and_deduplicates_nominations_on_sqlite(): void
    {
        config([
            'database.default' => 'interpro_migration_test',
            'database.connections.interpro_migration_test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => false,
            ],
        ]);
        DB::purge('interpro_migration_test');

        Schema::create('eventtypes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
        });
        Schema::create('category_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id');
        });
        Schema::create('event_nominations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('category_event_id');
            $table->unsignedBigInteger('player_id');
        });

        DB::table('eventtypes')->insert(['name' => 'Interpro Trials']);
        DB::table('category_events')->insert(['id' => 10, 'event_id' => 55]);
        DB::table('event_nominations')->insert([
            ['id' => 3, 'event_id' => 999, 'category_event_id' => 10, 'player_id' => 20],
            ['id' => 7, 'event_id' => 55, 'category_event_id' => 10, 'player_id' => 20],
        ]);

        $migration = require database_path('migrations/2026_09_28_000001_create_interprovincial_trials_invitation_tables.php');
        $migration->up();

        $this->assertEquals([(object) ['id' => 3, 'event_id' => 55]], DB::table('event_nominations')->select('id', 'event_id')->get()->all());
        $this->assertSame('interprovincial-trials', DB::table('eventtypes')->value('code'));
        $this->assertTrue(Schema::hasTable('interprovincial_trial_invitations'));
    }
}
