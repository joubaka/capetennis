<?php

namespace Tests\Unit\DataCorrections;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class OverbergLeg3RiekieWithdrawalMigrationTest extends TestCase
{
    private string $previousConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousConnection = DB::getDefaultConnection();
        config()->set('database.connections.overberg_correction_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::setDefaultConnection('overberg_correction_test');
        Schema::connection('overberg_correction_test');

        $this->createSchema();
        $this->seedExpectedLiveState();
    }

    protected function tearDown(): void
    {
        DB::disconnect('overberg_correction_test');
        DB::setDefaultConnection($this->previousConnection);
        DB::purge('overberg_correction_test');

        parent::tearDown();
    }

    public function test_it_records_a_no_refund_withdrawal_and_removes_only_the_unplayed_result(): void
    {
        $this->migration()->up();

        $entry = DB::table('category_event_registrations')->where('id', 404)->first();

        $this->assertSame('withdrawn', $entry->status);
        $this->assertNotNull($entry->withdrawn_at);
        $this->assertSame('not_refunded', $entry->refund_status);
        $this->assertSame('PF-UNCHANGED', $entry->pf_transaction_id);
        $this->assertStringContainsString('did not compete', $entry->withdrawal_reason);

        $this->assertDatabaseMissing('category_results', [
            'event_id' => 233,
            'category_id' => 13,
            'registration_id' => 1004,
        ], 'overberg_correction_test');
        $this->assertSame([1, 2, 3], DB::table('category_results')->orderBy('position')->pluck('position')->all());

        $audit = DB::table('draw_audit_logs')->sole();
        $payload = json_decode($audit->payload, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('late_withdrawal_result_removed', $audit->action);
        $this->assertSame('Riekie Beyers', $payload['player']);
        $this->assertTrue($payload['ranking_rebuild_required']);
    }

    public function test_it_refuses_to_change_results_when_refund_activity_exists(): void
    {
        DB::table('category_event_registrations')->where('id', 404)->update([
            'refund_status' => 'completed',
            'refund_gross' => 250,
            'refunded_at' => now(),
        ]);

        try {
            $this->migration()->up();
            $this->fail('Expected the correction to stop for financial review.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('requires financial review', $exception->getMessage());
        }

        $this->assertDatabaseHas('category_event_registrations', [
            'id' => 404,
            'status' => 'active',
            'refund_status' => 'completed',
        ], 'overberg_correction_test');
        $this->assertDatabaseHas('category_results', [
            'event_id' => 233,
            'category_id' => 13,
            'registration_id' => 1004,
            'position' => 4,
        ], 'overberg_correction_test');
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_09_07_030000_withdraw_overberg_leg3_u13_riekie_beyers.php');
    }

    private function createSchema(): void
    {
        Schema::create('events', function (Blueprint $table): void {
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
            $table->unsignedBigInteger('registration1_id')->nullable();
            $table->unsignedBigInteger('registration2_id')->nullable();
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
        Schema::create('category_event_registrations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('category_event_id');
            $table->unsignedBigInteger('registration_id');
            $table->string('status');
            $table->timestamp('withdrawn_at')->nullable();
            $table->string('withdrawal_reason')->nullable();
            $table->string('pf_transaction_id')->nullable();
            $table->string('refund_status')->nullable();
            $table->decimal('refund_gross', 10, 2)->default(0);
            $table->decimal('refund_fee', 10, 2)->default(0);
            $table->decimal('refund_net', 10, 2)->default(0);
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
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
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 64);
            $table->unsignedBigInteger('fixture_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    private function seedExpectedLiveState(): void
    {
        DB::table('events')->insert(['id' => 233, 'name' => 'Overberg Tennis Trials - Leg 3 2026']);
        DB::table('category_events')->insert(['id' => 1924, 'event_id' => 233, 'category_id' => 13]);
        DB::table('draws')->insert(['id' => 1443, 'event_id' => 233, 'category_event_id' => 1924, 'drawName' => 'U/13 Boys']);
        DB::table('players')->insert(['id' => 44, 'name' => 'Riekie', 'surname' => 'Beyers']);
        DB::table('player_registrations')->insert(['registration_id' => 1004, 'player_id' => 44]);
        DB::table('category_event_registrations')->insert([
            'id' => 404,
            'category_event_id' => 1924,
            'registration_id' => 1004,
            'status' => 'active',
            'pf_transaction_id' => 'PF-UNCHANGED',
            'refund_status' => 'not_refunded',
            'refund_gross' => 0,
            'refund_fee' => 0,
            'refund_net' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([1001 => 1, 1002 => 2, 1003 => 3, 1004 => 4] as $registrationId => $position) {
            DB::table('category_results')->insert([
                'event_id' => 233,
                'category_id' => 13,
                'registration_id' => $registrationId,
                'position' => $position,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
