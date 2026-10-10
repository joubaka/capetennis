<?php
namespace Tests\Feature;

use App\Models\{Event, Draw, Player};
use App\Services\Performance\{PlayerAbilitySnapshotStore, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlayerAbilitySnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_mysql_consistent_reader_uses_next_transaction_policy_and_finishes_before_returning(): void
    {
        $connection = \Mockery::mock(\Illuminate\Database\Connection::class);
        $connection->shouldReceive('getDriverName')->once()->andReturn('mysql');
        $connection->shouldReceive('transactionLevel')->once()->andReturn(0)->ordered();
        $connection->shouldReceive('getDatabaseName')->once()->andReturn('testing');
        $connection->shouldReceive('getTablePrefix')->andReturn('');
        $tables = \Mockery::mock();
        $connection->shouldReceive('table')->with('information_schema.tables')->once()->andReturn($tables);
        $tables->shouldReceive('where')->with('table_schema', 'testing')->once()->andReturnSelf();
        $tables->shouldReceive('whereIn')->once()->andReturnUsing(function ($column, $names) use ($tables) {
            $this->assertSame('table_name', $column);
            $tables->shouldReceive('get')->once()->andReturn(collect($names)->map(fn ($name) => (object) ['table_name' => $name, 'engine' => 'InnoDB']));
            return $tables;
        });
        $tables->shouldReceive('selectRaw')->once()->andReturnSelf();
        $connection->shouldReceive('statement')->with('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ')->once()->ordered();
        $connection->shouldReceive('statement')->with('SET TRANSACTION READ ONLY')->once()->ordered();
        $connection->shouldReceive('beginTransaction')->once()->ordered();
        $connection->shouldReceive('transactionLevel')->once()->andReturn(1)->ordered();
        $connection->shouldReceive('rollBack')->with(0)->once()->ordered();
        DB::shouldReceive('connection')->once()->andReturn($connection);
        $this->assertSame('captured', (new \App\Services\Performance\PlayerAbilityConsistentRead)->run(fn () => 'captured'));
    }

    public function test_mysql_nested_and_unsupported_consistent_reads_fail_closed_before_reading(): void
    {
        foreach ([['mysql', 1], ['pgsql', 0]] as [$driver, $level]) {
            $connection = \Mockery::mock(\Illuminate\Database\Connection::class);
            $connection->shouldReceive('getDriverName')->once()->andReturn($driver);
            $connection->shouldReceive('transactionLevel')->once()->andReturn($level);
            $connection->shouldNotReceive('beginTransaction');
            DB::shouldReceive('connection')->once()->andReturn($connection);
            try { (new \App\Services\Performance\PlayerAbilityConsistentRead)->run(fn () => $this->fail('Unsafe read executed')); $this->fail('Unsafe driver/transaction accepted'); }
            catch (\RuntimeException) { $this->addToAssertionCount(1); }
        }
    }

    public function test_consistent_reader_is_read_only_and_restores_transaction_and_sqlite_policy_on_failure(): void
    {
        $connection = DB::connection();
        $level = $connection->transactionLevel();
        $before = (int) $connection->selectOne('PRAGMA query_only')->query_only;
        try {
            app(\App\Services\Performance\PlayerAbilityConsistentRead::class)->run(function () use ($connection, $level) {
                $this->assertSame($level + 1, $connection->transactionLevel());
                DB::table('players')->insert(['name' => 'Forbidden']);
            });
            $this->fail('Source write allowed in read transaction');
        } catch (\Illuminate\Database\QueryException) {
            $this->assertSame($level, $connection->transactionLevel());
            $this->assertSame($before, (int) $connection->selectOne('PRAGMA query_only')->query_only);
        }
        $this->assertDatabaseMissing('players', ['name' => 'Forbidden']);
    }

    public function test_newer_scoring_generation_remains_pending_after_consistent_build_is_saved(): void
    {
        DB::table('player_ability_refresh_state')->where('id', 1)->update(['generation' => 10, 'completed_generation' => 0]);
        $service = $this->partialMock(PlayerSharedAbilityService::class);
        $service->shouldReceive('fingerprint')->times(2)->andReturn('captured');
        $service->shouldReceive('calculateSnapshot')->once()->andReturn($this->payload());
        $this->partialMock(\App\Services\Performance\PlayerAbilityConsistentRead::class)->shouldReceive('run')->once()->andReturnUsing(function ($read) {
            $result = (new \App\Services\Performance\PlayerAbilityConsistentRead)->run($read);
            DB::table('player_ability_refresh_state')->where('id', 1)->update(['generation' => 11]);
            return $result;
        });
        $this->artisan('player-ability:refresh')->assertSuccessful();
        $this->assertDatabaseCount('player_ability_snapshots', 1);
        $state = DB::table('player_ability_refresh_state')->where('id', 1)->first();
        $this->assertSame(10, (int) $state->completed_generation);
        $this->assertSame(11, (int) $state->generation);
    }

    public function test_live_publication_revocation_after_consistent_read_preserves_last_success(): void
    {
        $event = Event::factory()->create(['published' => true]);
        Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        $this->save();
        $saved = DB::table('player_ability_snapshots')->value('payload');
        $this->partialMock(PlayerSharedAbilityService::class)->shouldReceive('fingerprint')->andReturn('captured')
            ->shouldReceive('calculateSnapshot')->once()->andReturn($this->payload());
        $this->partialMock(\App\Services\Performance\PlayerAbilityConsistentRead::class)->shouldReceive('run')->once()->andReturnUsing(function ($read) use ($event) {
            $result = (new \App\Services\Performance\PlayerAbilityConsistentRead)->run($read);
            DB::table('events')->where('id', $event->id)->update(['published' => false]);
            return $result;
        });
        $this->artisan('player-ability:refresh')->assertFailed();
        $this->assertSame($saved, DB::table('player_ability_snapshots')->value('payload'));
    }

    public function test_live_registration_identity_change_after_consistent_read_is_rejected(): void
    {
        $event = Event::factory()->create(['published' => true]);
        $field = \App\Models\CategoryEvent::factory()->create(['event_id' => $event->id]);
        $registration = \App\Models\Registration::factory()->create();
        $registration->players()->attach(Player::factory()->create());
        \App\Models\CategoryEventRegistration::factory()->create(['category_event_id' => $field->id, 'registration_id' => $registration->id]);
        $replacement = Player::factory()->create();
        $this->save();
        $saved = DB::table('player_ability_snapshots')->value('payload');
        $this->partialMock(PlayerSharedAbilityService::class)->shouldReceive('fingerprint')->andReturn('captured')
            ->shouldReceive('calculateSnapshot')->once()->andReturn($this->payload());
        $this->partialMock(\App\Services\Performance\PlayerAbilityConsistentRead::class)->shouldReceive('run')->once()->andReturnUsing(function ($read) use ($registration, $replacement) {
            $result = (new \App\Services\Performance\PlayerAbilityConsistentRead)->run($read);
            DB::table('player_registrations')->where('registration_id', $registration->id)->update(['player_id' => $replacement->id]);
            return $result;
        });
        $this->artisan('player-ability:refresh')->assertFailed();
        $this->assertSame($saved, DB::table('player_ability_snapshots')->value('payload'));
    }

    private function payload(string $date = '2026-10-07'): array
    {
        return ['cohorts' => [], 'names' => [], 'reason' => null, 'built_at' => $date.' 00:01:00 SAST', 'source_events' => Event::pluck('id')->all(), 'source_matches' => []];
    }
    private function save(string $date = '2026-10-07'): void
    {
        $store = new PlayerAbilitySnapshotStore;
        $store->replace($this->payload($date), CarbonImmutable::parse($date), 'test', $store->manifest($this->payload($date)));
    }
    public function test_missing_saved_snapshot_never_fits_on_read_or_check(): void
    {
        $this->partialMock(PlayerSharedAbilityService::class)->shouldNotReceive('calculateSnapshot', 'fingerprint');
        $player = Player::factory()->create();
        $this->assertNull(app(PlayerSharedAbilityService::class)->forPlayer($player)['headline']);
        $this->assertStringContainsString('pending', (new PlayerAbilitySnapshotStore)->current()['reason']);
        $this->artisan('player-ability:refresh --check')->assertFailed();
        $this->assertDatabaseCount('player_ability_snapshots', 0);
    }
    public function test_new_publications_wait_but_revocation_deletion_and_context_changes_withhold(): void
    {
        CarbonImmutable::setTestNow('2026-10-07');
        $event = Event::factory()->create(['published' => true, 'results_published' => false]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        $this->save();
        Event::factory()->create(['published' => true]);
        DB::table('events')->where('id', $event->id)->update(['results_published' => true]);
        $this->assertNull((new PlayerAbilitySnapshotStore)->current()['reason']);
        DB::table('draws')->where('id', $draw->id)->update(['drawName' => 'Changed category']);
        $this->assertNotNull((new PlayerAbilitySnapshotStore)->current()['reason']);
        DB::table('draws')->where('id', $draw->id)->update(['drawName' => $draw->drawName, 'published' => false]);
        $this->assertNotNull((new PlayerAbilitySnapshotStore)->current()['reason']);
        DB::table('draws')->where('id', $draw->id)->delete();
        $this->assertNotNull((new PlayerAbilitySnapshotStore)->current()['reason']);
        CarbonImmutable::setTestNow();
    }
    public function test_old_failed_and_dry_run_candidates_preserve_last_successful_payload(): void
    {
        $this->save();
        $saved = DB::table('player_ability_snapshots')->value('payload');
        $service = $this->partialMock(PlayerSharedAbilityService::class);
        $service->shouldReceive('fingerprint')->andReturn('unchanged');
        $service->shouldReceive('calculateSnapshot')->once()->andReturn($this->payload());
        $this->artisan('player-ability:refresh --dry-run')->assertSuccessful();
        $service->shouldReceive('calculateSnapshot')->once()->andThrow(new \RuntimeException('Private internal failure'));
        $this->artisan('player-ability:refresh')->assertFailed();
        $this->assertSame($saved, DB::table('player_ability_snapshots')->value('payload'));
        try { $this->save('2026-10-06'); $this->fail('Older snapshot replaced newer one'); } catch (\RuntimeException) {}
        $this->assertSame($saved, DB::table('player_ability_snapshots')->value('payload'));
    }
    public function test_stale_version_policy_and_future_snapshots_are_explicit(): void
    {
        CarbonImmutable::setTestNow('2026-10-08');
        $this->save();
        $this->assertTrue((new PlayerAbilitySnapshotStore)->current()['snapshot_stale']);
        DB::table('player_ability_snapshots')->update(['model_version' => 5]);
        $this->assertNotNull((new PlayerAbilitySnapshotStore)->current()['reason']);
        $this->save();
        DB::table('player_ability_snapshots')->update(['policy_hash' => 'old']);
        $this->assertNotNull((new PlayerAbilitySnapshotStore)->current()['reason']);
        $this->save();
        DB::table('player_ability_snapshots')->update(['as_of' => '2026-10-09']);
        $this->assertNotNull((new PlayerAbilitySnapshotStore)->current()['reason']);
        CarbonImmutable::setTestNow();
    }
    public function test_saved_payload_corruption_and_captured_category_edits_fail_closed(): void
    {
        $event = Event::factory()->create(['published' => true]);
        $category = \App\Models\Category::factory()->create(['name' => 'U10 Boys']);
        \App\Models\CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => $category->id]);
        $this->save();
        DB::table('categories')->where('id', $category->id)->update(['name' => 'U10 Girls']);
        $this->assertNotNull((new PlayerAbilitySnapshotStore)->current()['reason']);
        $this->save();
        DB::table('player_ability_snapshots')->update(['payload' => '{invalid']);
        $this->assertStringContainsString('unreadable', (new PlayerAbilitySnapshotStore)->current()['reason']);
        $this->save();
        DB::table('player_ability_snapshots')->update(['publication_manifest' => '{"draws":[{"wrong":1}]}']);
        $this->assertNotNull((new PlayerAbilitySnapshotStore)->current()['reason']);
    }
    public function test_refresh_uses_same_manual_lock_and_rejects_nonconverged_replacement(): void
    {
        $this->save();
        $saved = DB::table('player_ability_snapshots')->value('payload');
        $lock = \Illuminate\Support\Facades\Cache::store('file')->lock('player-ability:refresh', 60);
        $this->assertTrue($lock->get());
        try { $this->artisan('player-ability:refresh')->assertFailed(); } finally { $lock->release(); }
        $payload = $this->payload();
        $payload['cohorts'] = ['u10 boys' => ['components' => ['bad' => ['converged' => false]]]];
        try {
            $store = new PlayerAbilitySnapshotStore;
            $store->replace($payload, CarbonImmutable::parse('2026-10-07'), 'test', $store->manifest($payload));
            $this->fail('Nonconverged result replaced saved snapshot');
        } catch (\RuntimeException) {}
        $this->assertSame($saved, DB::table('player_ability_snapshots')->value('payload'));
    }
    public function test_scheduler_explicitly_uses_midnight_south_african_time_and_overlap_lock(): void
    {
        $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
        $events = collect($schedule->events())->filter(fn ($event) => str_contains($event->command ?? '', 'player-ability:refresh'));
        $this->assertCount(2, $events);
        $event = $events->first(fn ($event) => !str_contains($event->command, '--pending'));
        $this->assertSame('0 0 * * *', $event->expression);
        $this->assertSame('Africa/Johannesburg', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
        $background = $events->first(fn ($event) => str_contains($event->command, '--pending'));
        $this->assertSame('* * * * *', $background->expression);
        $this->assertTrue($background->withoutOverlapping);
        $this->assertTrue($background->runInBackground);
    }    public function test_new_member_or_duplicate_category_context_withholds_existing_snapshot(): void
    {
        $event = Event::factory()->create(['published' => true]);
        $field = \App\Models\CategoryEvent::factory()->create(['event_id' => $event->id]);
        $registration = \App\Models\Registration::factory()->create();
        $player = Player::factory()->create();
        $registration->players()->attach($player);
        \App\Models\CategoryEventRegistration::factory()->create(['category_event_id' => $field->id, 'registration_id' => $registration->id]);
        $this->save();
        $registration->players()->attach(Player::factory()->create());
        $this->assertNotNull((new PlayerAbilitySnapshotStore)->current()['reason']);
        $this->save();
        \App\Models\CategoryEventRegistration::factory()->create(['category_event_id' => $field->id]);
        $this->assertNotNull((new PlayerAbilitySnapshotStore)->current()['reason']);
        $this->save();
        \App\Models\CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => $field->category_id]);
        $this->assertNotNull((new PlayerAbilitySnapshotStore)->current()['reason']);
    }
    public function test_raw_source_context_edit_during_calculation_keeps_last_good(): void
    {
        $event = Event::factory()->create(['published' => true]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        $this->save();
        $saved = DB::table('player_ability_snapshots')->value('payload');
        $this->partialMock(PlayerSharedAbilityService::class)->shouldReceive('calculateSnapshot')->once()->andReturnUsing(function () use ($draw) {
            DB::table('draws')->where('id', $draw->id)->update(['team_draw_selection' => '{"changed":true}']);
            return $this->payload();
        });
        $this->artisan('player-ability:refresh')->assertFailed();
        $this->assertSame($saved, DB::table('player_ability_snapshots')->value('payload'));
    }}
