<?php

namespace Tests\Unit\DataCorrections;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class OverbergLeg1PlacementMigrationTest extends TestCase
{
    private string $previousConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousConnection = DB::getDefaultConnection();
        config()->set('database.connections.overberg_leg1_correction_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::setDefaultConnection('overberg_leg1_correction_test');
        Schema::connection('overberg_leg1_correction_test');

        $this->createSchema();
        $this->seedExpectedLegacyState();
    }

    protected function tearDown(): void
    {
        DB::disconnect('overberg_leg1_correction_test');
        DB::setDefaultConnection($this->previousConnection);
        DB::purge('overberg_leg1_correction_test');

        parent::tearDown();
    }

    public function test_it_resolves_the_category_when_the_legacy_draw_has_no_category_event_link(): void
    {
        $this->migration()->up();

        $fixture = DB::table('fixtures')
            ->where('draw_id', 901)
            ->where('stage', 'PLACEMENT_ADJUSTMENT')
            ->where('match_nr', 1003)
            ->sole();

        $this->assertSame(103, $fixture->registration1_id);
        $this->assertSame(102, $fixture->registration2_id);
        $this->assertSame(103, $fixture->winner_registration);
        $this->assertDatabaseHas('fixture_results', [
            'fixture_id' => $fixture->id,
            'registration1_score' => 7,
            'registration2_score' => 5,
            'winner_registration' => 103,
        ], 'overberg_leg1_correction_test');
        $this->assertSame(
            [101 => 1, 103 => 2, 102 => 3, 104 => 4, 105 => 5],
            DB::table('category_results')->orderBy('position')->pluck('position', 'registration_id')->all(),
        );
        $this->assertDatabaseHas('draw_audit_logs', [
            'draw_id' => 901,
            'fixture_id' => $fixture->id,
            'action' => 'placement_adjustment_recorded',
        ], 'overberg_leg1_correction_test');
    }

    public function test_it_refuses_an_ambiguous_legacy_category_match_without_partial_changes(): void
    {
        DB::table('categories')->insert(['id' => 14, 'name' => 'U13 Boys']);
        DB::table('category_events')->insert(['id' => 814, 'event_id' => 231, 'category_id' => 14]);
        foreach ([102 => 2, 103 => 3] as $registrationId => $position) {
            DB::table('category_results')->insert([
                'event_id' => 231,
                'category_id' => 14,
                'registration_id' => $registrationId,
                'position' => $position,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        try {
            $this->migration()->up();
            $this->fail('Expected the ambiguous legacy category match to stop the correction.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('exactly one event 231 result category', $exception->getMessage());
        }

        $this->assertDatabaseMissing('fixtures', [
            'draw_id' => 901,
            'stage' => 'PLACEMENT_ADJUSTMENT',
            'match_nr' => 1003,
        ], 'overberg_leg1_correction_test');
        $this->assertDatabaseHas('category_results', [
            'event_id' => 231,
            'category_id' => 13,
            'registration_id' => 102,
            'position' => 2,
        ], 'overberg_leg1_correction_test');
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_09_07_020000_record_overberg_leg1_u13_placement_adjustment.php');
    }

    private function createSchema(): void
    {
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        Schema::create('category_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('category_id');
        });
        Schema::create('draws', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('category_event_id')->nullable();
            $table->string('drawName');
        });
        Schema::create('fixtures', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('draw_id');
            $table->string('stage');
            $table->unsignedInteger('match_nr');
            $table->boolean('scheduled')->default(false);
            $table->unsignedInteger('round')->nullable();
            $table->unsignedInteger('match_status')->default(0);
            $table->unsignedInteger('position')->nullable();
            $table->string('playoff_type')->nullable();
            $table->unsignedBigInteger('registration1_id')->nullable();
            $table->unsignedBigInteger('registration2_id')->nullable();
            $table->unsignedBigInteger('winner_registration')->nullable();
            $table->unsignedBigInteger('loser_registration')->nullable();
            $table->unsignedBigInteger('draw_group_id')->nullable();
            $table->timestamps();
        });
        Schema::create('fixture_results', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('fixture_id');
            $table->unsignedInteger('set_nr');
            $table->unsignedInteger('registration1_score');
            $table->unsignedInteger('registration2_score');
            $table->unsignedBigInteger('winner_registration');
            $table->unsignedBigInteger('loser_registration');
            $table->timestamps();
        });
        Schema::create('players', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('surname');
        });
        Schema::create('player_registrations', function (Blueprint $table): void {
            $table->unsignedBigInteger('registration_id');
            $table->unsignedBigInteger('player_id');
        });
        Schema::create('category_results', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('registration_id');
            $table->unsignedInteger('position');
            $table->timestamps();
        });
        Schema::create('draw_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('draw_id');
            $table->unsignedBigInteger('fixture_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action');
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    private function seedExpectedLegacyState(): void
    {
        DB::table('events')->insert(['id' => 231, 'name' => 'Overberg Tennis Trials - Leg 1 2026']);
        DB::table('categories')->insert(['id' => 13, 'name' => 'U/13 Boys']);
        DB::table('category_events')->insert(['id' => 813, 'event_id' => 231, 'category_id' => 13]);
        DB::table('draws')->insert(['id' => 901, 'event_id' => 231, 'category_event_id' => null, 'drawName' => 'U/13 Boys']);

        foreach ([
            101 => ['Nico', 'Malan'],
            102 => ['Rome', 'Sargeant'],
            103 => ['Gerhard', 'Bruwer'],
            104 => ['Jeremiah', 'Jones'],
            105 => ['Frederick', 'Smith'],
        ] as $registrationId => [$name, $surname]) {
            DB::table('players')->insert(['id' => $registrationId, 'name' => $name, 'surname' => $surname]);
            DB::table('player_registrations')->insert(['registration_id' => $registrationId, 'player_id' => $registrationId]);
        }

        DB::table('fixtures')->insert([
            [
                'id' => 1000, 'draw_id' => 901, 'stage' => 'MAIN', 'match_nr' => 1000,
                'registration1_id' => 101, 'registration2_id' => 102, 'winner_registration' => 101,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'id' => 1001, 'draw_id' => 901, 'stage' => 'PLATE', 'match_nr' => 1001,
                'registration1_id' => 103, 'registration2_id' => 104, 'winner_registration' => 103,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        foreach ([101 => 1, 102 => 2, 103 => 3, 104 => 4, 105 => 5] as $registrationId => $position) {
            DB::table('category_results')->insert([
                'event_id' => 231,
                'category_id' => 13,
                'registration_id' => $registrationId,
                'position' => $position,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
