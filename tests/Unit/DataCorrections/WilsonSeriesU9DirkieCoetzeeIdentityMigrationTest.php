<?php

namespace Tests\Unit\DataCorrections;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class WilsonSeriesU9DirkieCoetzeeIdentityMigrationTest extends TestCase
{
    private string $previousConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousConnection = DB::getDefaultConnection();
        config()->set('database.connections.wilson_dirkie_correction_test', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::setDefaultConnection('wilson_dirkie_correction_test');

        $this->createSchema();
        $this->seedAuditedState();
    }

    protected function tearDown(): void
    {
        DB::disconnect('wilson_dirkie_correction_test');
        DB::setDefaultConnection($this->previousConnection);
        DB::purge('wilson_dirkie_correction_test');

        parent::tearDown();
    }

    public function test_it_reassigns_only_the_two_entry_identity_links_and_is_idempotent(): void
    {
        $beforeOrders = DB::table('registration_orders')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
        $beforeEntries = DB::table('category_event_registrations')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
        $beforeRanking = DB::table('series_rankings')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all();
        $beforeInvitation = (array) DB::table('masters_invitations')->where('id', 807)->sole();

        $migration = $this->migration();
        $migration->up();
        $migration->up();

        foreach ([[16689, 18445], [18407, 20410]] as [$id, $registrationId]) {
            $this->assertDatabaseHas('player_registrations', [
                'id' => $id,
                'registration_id' => $registrationId,
                'player_id' => 5332,
            ], 'wilson_dirkie_correction_test');
        }
        foreach ([[9435, 8411, 18445], [11283, 9997, 20410]] as [$id, $orderId, $registrationId]) {
            $this->assertDatabaseHas('registration_order_items', [
                'id' => $id,
                'order_id' => $orderId,
                'registration_id' => $registrationId,
                'player_id' => 5332,
                'user_id' => 4025,
            ], 'wilson_dirkie_correction_test');
        }
        $this->assertDatabaseHas('player_registrations', [
            'id' => 30000,
            'registration_id' => 30000,
            'player_id' => 2439,
        ], 'wilson_dirkie_correction_test');

        $this->assertSame($beforeOrders, DB::table('registration_orders')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all());
        $this->assertSame($beforeEntries, DB::table('category_event_registrations')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all());
        $this->assertSame($beforeRanking, DB::table('series_rankings')->orderBy('id')->get()->map(fn ($row): array => (array) $row)->all());
        $this->assertSame($beforeInvitation, (array) DB::table('masters_invitations')->where('id', 807)->sole());
        $this->assertSame(1, DB::table('ranking_audit_logs')->where('action', 'player_identity_correction_requires_review')->count());
    }

    #[DataProvider('paymentStateDriftProvider')]
    public function test_it_refuses_payment_state_drift_without_partial_changes(string $table, int $id, string $column, float $value): void
    {
        DB::table($table)->where('id', $id)->update([$column => $value]);

        try {
            $this->migration()->up();
            $this->fail('Expected payment state drift to stop the identity correction.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('9435', $exception->getMessage());
        }

        $this->assertSame(
            [16689 => 2439, 18407 => 2439, 30000 => 2439],
            DB::table('player_registrations')->orderBy('id')->pluck('player_id', 'id')->all(),
        );
        $this->assertSame(
            [9435 => 2439, 11283 => 2439],
            DB::table('registration_order_items')->orderBy('id')->pluck('player_id', 'id')->all(),
        );
        $this->assertSame(0, DB::table('ranking_audit_logs')->count());
    }

    /** @return array<string, array{string, int, string, float}> */
    public static function paymentStateDriftProvider(): array
    {
        return [
            'legacy order total drift' => ['registration_orders', 8411, 'total_fee', 1.00],
            'legacy order item price drift' => ['registration_order_items', 9435, 'item_price', 284.00],
        ];
    }

    public function test_it_refuses_a_target_ranking_collision_without_partial_changes(): void
    {
        DB::table('series_rankings')->insert([
            'id' => 13000,
            'series_id' => 18,
            'ranking_list_id' => 938,
            'player_id' => 5332,
            'rank_position' => 9,
            'total_points' => 75,
            'status' => 'calculated',
            'run_id' => 'replacement-run',
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already has a Wilson Series ranking snapshot');

        try {
            $this->migration()->up();
        } finally {
            $this->assertSame(2439, (int) DB::table('player_registrations')->where('id', 16689)->value('player_id'));
            $this->assertSame(2439, (int) DB::table('registration_order_items')->where('id', 9435)->value('player_id'));
        }
    }

    public function test_it_refuses_category_entry_owner_drift_without_partial_changes(): void
    {
        DB::table('category_event_registrations')->where('id', 16877)->update(['user_id' => 9999]);

        try {
            $this->migration()->up();
            $this->fail('Expected category entry owner drift to stop the correction.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('18445', $exception->getMessage());
        }

        $this->assertSame(2439, (int) DB::table('player_registrations')->where('id', 16689)->value('player_id'));
        $this->assertSame(2439, (int) DB::table('registration_order_items')->where('id', 9435)->value('player_id'));
        $this->assertSame(0, DB::table('ranking_audit_logs')->count());
    }

    public function test_it_refuses_a_conflicting_target_player_registration_without_partial_changes(): void
    {
        DB::table('player_registrations')->insert([
            'id' => 31000,
            'registration_id' => 18445,
            'player_id' => 5332,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $this->migration()->up();
            $this->fail('Expected the conflicting target player registration to stop the correction.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('conflicting Dirkie Coetzee link', $exception->getMessage());
        }

        $this->assertSame(2439, (int) DB::table('player_registrations')->where('id', 16689)->value('player_id'));
        $this->assertSame(2439, (int) DB::table('registration_order_items')->where('id', 9435)->value('player_id'));
        $this->assertSame(0, DB::table('ranking_audit_logs')->count());
    }

    public function test_it_refuses_a_target_masters_invitation_without_partial_changes(): void
    {
        DB::table('masters_invitations')->insert([
            'id' => 808,
            'batch_id' => 4,
            'event_id' => 254,
            'category_event_id' => 2182,
            'player_id' => 5332,
            'registration_id' => null,
            'order_id' => null,
            'ranking_position' => 9,
            'total_points' => 75,
            'status' => 'reserve',
        ]);

        try {
            $this->migration()->up();
            $this->fail('Expected the target Masters invitation to stop the correction.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('already has a Masters invitation', $exception->getMessage());
        }

        $this->assertSame(2439, (int) DB::table('player_registrations')->where('id', 16689)->value('player_id'));
        $this->assertSame(2439, (int) DB::table('registration_order_items')->where('id', 9435)->value('player_id'));
        $this->assertSame(0, DB::table('ranking_audit_logs')->count());
    }

    public function test_it_fails_closed_when_a_required_table_is_missing(): void
    {
        Schema::drop('ranking_audit_logs');

        try {
            $this->migration()->up();
            $this->fail('Expected the missing required table to stop the correction.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Required table ranking_audit_logs is missing', $exception->getMessage());
        }

        $this->assertSame(2439, (int) DB::table('player_registrations')->where('id', 16689)->value('player_id'));
        $this->assertSame(2439, (int) DB::table('registration_order_items')->where('id', 9435)->value('player_id'));
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_09_22_010000_correct_wilson_series_u9_dirkie_coetzee_identity.php');
    }

    private function createSchema(): void
    {
        Schema::create('players', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('surname');
            $table->date('dateOfBirth');
            $table->unsignedBigInteger('userId');
        });
        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        Schema::create('category_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('event_id');
        });
        Schema::create('registrations', fn (Blueprint $table) => $table->id());
        Schema::create('player_registrations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('registration_id');
            $table->unsignedBigInteger('player_id');
            $table->timestamps();
            $table->unique(['registration_id', 'player_id']);
        });
        Schema::create('category_event_registrations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('category_event_id');
            $table->unsignedBigInteger('registration_id');
            $table->unsignedBigInteger('user_id');
            $table->string('status');
            $table->unsignedTinyInteger('payment_status_id');
            $table->timestamps();
        });
        Schema::create('registration_orders', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->boolean('pay_status');
            $table->boolean('payfast_paid');
            $table->decimal('total_fee', 10, 2);
            $table->string('status')->nullable();
            $table->timestamps();
        });
        Schema::create('registration_order_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('registration_id');
            $table->unsignedBigInteger('player_id');
            $table->unsignedBigInteger('category_event_id');
            $table->unsignedBigInteger('user_id');
            $table->decimal('item_price', 10, 2);
            $table->timestamps();
        });
        Schema::create('series_rankings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('series_id');
            $table->unsignedBigInteger('ranking_list_id');
            $table->unsignedBigInteger('player_id');
            $table->unsignedInteger('rank_position');
            $table->unsignedInteger('total_points');
            $table->string('status');
            $table->string('run_id')->nullable();
        });
        Schema::create('masters_invitations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('batch_id');
            $table->unsignedBigInteger('event_id');
            $table->unsignedBigInteger('category_event_id');
            $table->unsignedBigInteger('player_id');
            $table->unsignedBigInteger('registration_id')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedInteger('ranking_position');
            $table->unsignedInteger('total_points');
            $table->string('status');
        });
        Schema::create('ranking_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('series_id');
            $table->string('run_id')->nullable();
            $table->string('action');
            $table->longText('payload')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });
    }

    private function seedAuditedState(): void
    {
        DB::table('players')->insert([
            ['id' => 2439, 'name' => 'Dirk', 'surname' => 'Coetzee', 'dateOfBirth' => '2010-03-25', 'userId' => 2340],
            ['id' => 5332, 'name' => 'Dirkie', 'surname' => 'Coetzee', 'dateOfBirth' => '2017-09-09', 'userId' => 4025],
        ]);
        DB::table('events')->insert([
            ['id' => 230, 'name' => 'Cavaliers Junior Wilson Paarl Tournament 2026'],
            ['id' => 237, 'name' => 'Cavaliers Junior Strand Tournament 2026'],
        ]);
        DB::table('category_events')->insert([
            ['id' => 1861, 'event_id' => 230],
            ['id' => 2011, 'event_id' => 237],
        ]);
        DB::table('registrations')->insert([['id' => 18445], ['id' => 20410], ['id' => 30000]]);
        DB::table('player_registrations')->insert([
            ['id' => 16689, 'registration_id' => 18445, 'player_id' => 2439, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 18407, 'registration_id' => 20410, 'player_id' => 2439, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 30000, 'registration_id' => 30000, 'player_id' => 2439, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('category_event_registrations')->insert([
            ['id' => 16877, 'category_event_id' => 1861, 'registration_id' => 18445, 'user_id' => 4025, 'status' => 'active', 'payment_status_id' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 18627, 'category_event_id' => 2011, 'registration_id' => 20410, 'user_id' => 4025, 'status' => 'active', 'payment_status_id' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('registration_orders')->insert([
            ['id' => 8411, 'user_id' => null, 'pay_status' => false, 'payfast_paid' => false, 'total_fee' => 0, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 9997, 'user_id' => 4025, 'pay_status' => true, 'payfast_paid' => true, 'total_fee' => 285, 'status' => 'paid', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('registration_order_items')->insert([
            ['id' => 9435, 'order_id' => 8411, 'registration_id' => 18445, 'player_id' => 2439, 'category_event_id' => 1861, 'user_id' => 4025, 'item_price' => 285, 'created_at' => now(), 'updated_at' => now()],
            ['id' => 11283, 'order_id' => 9997, 'registration_id' => 20410, 'player_id' => 2439, 'category_event_id' => 2011, 'user_id' => 4025, 'item_price' => 285, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('series_rankings')->insert([
            ['id' => 11897, 'series_id' => 18, 'ranking_list_id' => 938, 'player_id' => 2439, 'rank_position' => 8, 'total_points' => 80, 'status' => 'archived', 'run_id' => 'archived-run'],
            ['id' => 12398, 'series_id' => 18, 'ranking_list_id' => 938, 'player_id' => 2439, 'rank_position' => 8, 'total_points' => 80, 'status' => 'published', 'run_id' => 'published-run'],
        ]);
        DB::table('masters_invitations')->insert([
            'id' => 807,
            'batch_id' => 4,
            'event_id' => 254,
            'category_event_id' => 2182,
            'player_id' => 2439,
            'registration_id' => null,
            'order_id' => null,
            'ranking_position' => 8,
            'total_points' => 80,
            'status' => 'declined',
        ]);
    }
}
