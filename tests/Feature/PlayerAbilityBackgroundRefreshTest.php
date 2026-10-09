<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Performance\{PlayerAbilityRefreshState, PlayerAbilitySnapshotStore, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, Schema};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerAbilityBackgroundRefreshTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return ['cohorts' => [], 'names' => [], 'reason' => null,
            'built_at' => CarbonImmutable::now('Africa/Johannesburg')->format('Y-m-d H:i:s').' SAST',
            'source_events' => [], 'source_matches' => []];
    }

    public function test_markers_wait_for_commit_and_rollback_and_bulk_deletion_are_safe(): void
    {
        $state = app(PlayerAbilityRefreshState::class);
        $before = $state->generation();
        DB::beginTransaction();
        $id = DB::table('team_fixture_results')->insertGetId(['team_fixture_id' => 123, 'team1_score' => 6, 'team2_score' => 3]);
        $this->assertSame($id, (int) DB::table('team_fixture_results')->value('id'));
        $this->assertSame($before, $state->generation());
        DB::rollBack();
        $this->assertSame($before, $state->generation());
        $this->assertDatabaseCount('team_fixture_results', 0);
        DB::transaction(fn () => DB::table('team_fixture_results')->insert(['team_fixture_id' => 123, 'team1_score' => 6, 'team2_score' => 3]));
        $this->assertGreaterThan($before, $state->generation());
        $committed = $state->generation();
        DB::table('team_fixture_results')->where('team_fixture_id', 123)->delete();
        $this->assertGreaterThan($committed, $state->generation());
    }

    public function test_batched_changes_refresh_once_then_idle_runs_do_not_calculate(): void
    {
        DB::table('team_fixture_results')->insert(['team_fixture_id' => 123, 'team1_score' => 6, 'team2_score' => 3]);
        DB::table('team_fixture_results')->update(['team2_score' => 4]);
        $service = $this->partialMock(PlayerSharedAbilityService::class);
        $service->shouldReceive('fingerprint')->twice()->andReturn('same');
        $service->shouldReceive('calculateSnapshot')->once()->andReturn($this->payload());
        $this->artisan('player-ability:refresh --pending')->assertSuccessful();
        $state = app(PlayerAbilityRefreshState::class);
        $this->assertFalse($state->status()['pending']);
        $this->artisan('player-ability:refresh --pending')->assertSuccessful();
        $this->assertDatabaseCount('player_ability_snapshots', 1);
        $this->assertDatabaseCount('player_ability_refresh_state', 1);
    }

    public function test_nested_rollback_discards_only_rolled_back_score_markers(): void
    {
        $state = app(PlayerAbilityRefreshState::class);
        $before = $state->generation();
        DB::beginTransaction();
        DB::beginTransaction();
        DB::table('team_fixture_results')->insert(['team_fixture_id' => 123, 'team1_score' => 6, 'team2_score' => 3]);
        DB::rollBack();
        DB::commit();
        $this->assertSame($before, $state->generation());
    }

    public function test_status_revision_and_badges_share_the_loaded_snapshot_during_replacement(): void
    {
        $store = app(PlayerAbilitySnapshotStore::class);
        $store->replace($this->payload(), CarbonImmutable::today('Africa/Johannesburg'), 'old', []);
        $state = app(PlayerAbilityRefreshState::class);
        $old = $state->status()['version'];
        // Simulate another process replacing the DB row after this request read it.
        DB::table('player_ability_snapshots')->update(['source_fingerprint' => 'new']);
        $this->assertSame($old, $state->status()['version']);
        app()->forgetInstance(PlayerAbilitySnapshotStore::class);
        $this->assertNotSame($old, $state->status()['version']);
    }

    public function test_publication_revocation_changes_status_revision_and_withholds_the_saved_snapshot(): void
    {
        $event = \App\Models\Event::factory()->create(['published' => true]);
        $draw = \App\Models\Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        $payload = $this->payload();
        $payload['source_events'] = [$event->id];
        $store = app(PlayerAbilitySnapshotStore::class);
        $store->replace($payload, CarbonImmutable::today('Africa/Johannesburg'), 'old', $store->manifest($payload));
        $state = app(PlayerAbilityRefreshState::class);
        $state->completed($state->generation());
        $old = $state->status();
        $this->assertFalse($old['pending']);
        DB::table('draws')->where('id', $draw->id)->update(['published' => false]);
        app()->forgetInstance(PlayerAbilitySnapshotStore::class);
        $new = $state->status();
        $this->assertTrue($new['pending']);
        $this->assertNotSame($old['version'], $new['version']);
        $this->assertNotNull(app(PlayerAbilitySnapshotStore::class)->current()['reason']);
    }

    public function test_pending_refresh_recovers_a_snapshot_withheld_after_context_changes(): void
    {
        $event = \App\Models\Event::factory()->create(['published' => true]);
        $draw = \App\Models\Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        $payload = $this->payload();
        $payload['source_events'] = [$event->id];
        $store = app(PlayerAbilitySnapshotStore::class);
        $store->replace($payload, CarbonImmutable::today('Africa/Johannesburg'), 'before', $store->manifest($payload));
        DB::table('draws')->where('id', $draw->id)->update(['drawName' => 'Corrected category context']);
        $state = app(PlayerAbilityRefreshState::class);
        $generation = $state->generation();
        $this->assertNotNull($store->current()['reason']);
        $this->assertTrue($state->status()['pending']);

        $service = $this->partialMock(PlayerSharedAbilityService::class);
        $service->shouldReceive('fingerprint')->twice()->andReturn('after');
        $service->shouldReceive('calculateSnapshot')->once()->andReturn($payload);
        $this->artisan('player-ability:refresh --pending')->assertSuccessful();

        $this->assertNull($store->current()['reason']);
        $this->assertFalse($store->current()['snapshot_stale']);
        $this->assertFalse($state->status()['pending']);
        $this->assertFalse($state->status()['failed']);
        $this->assertSame($generation, (int) DB::table('player_ability_refresh_state')->value('completed_generation'));
        $this->assertSame('after', DB::table('player_ability_snapshots')->value('source_fingerprint'));
        $this->assertDatabaseCount('player_ability_snapshots', 1);
    }

    public function test_source_tracking_adds_only_one_schema_check_per_request_and_updates_after_commit(): void
    {
        $queries = [];
        DB::listen(function ($query) use (&$queries) { $queries[] = $query->sql; });
        app()->forgetInstance(PlayerAbilityRefreshState::class);
        DB::transaction(function () {
            for ($set = 1; $set <= 3; $set++) {
                DB::table('team_fixture_results')->insert(['team_fixture_id' => 123, 'team1_score' => 6, 'team2_score' => 3, 'set_nr' => $set]);
            }
        });
        $schema = array_filter($queries, fn ($sql) => str_contains($sql, 'sqlite_master') && str_contains($sql, 'type'));
        $markers = array_filter($queries, fn ($sql) => str_starts_with($sql, 'update "player_ability_refresh_state"'));
        $this->assertCount(1, $schema);
        $this->assertCount(3, $markers);
        $this->assertDatabaseCount('team_fixture_results', 3);
    }

    public function test_changes_arriving_during_build_remain_pending_and_failure_keeps_snapshot(): void
    {
        $store = app(PlayerAbilitySnapshotStore::class);
        $store->replace($this->payload(), CarbonImmutable::today('Africa/Johannesburg'), 'before', []);
        $saved = DB::table('player_ability_snapshots')->value('payload');
        $service = $this->partialMock(PlayerSharedAbilityService::class);
        $service->shouldReceive('fingerprint')->twice()->andReturn('before', 'after');
        $service->shouldReceive('calculateSnapshot')->once()->andReturnUsing(function () {
            DB::table('team_fixture_results')->insert(['team_fixture_id' => 123, 'team1_score' => 6, 'team2_score' => 3]);
            return $this->payload();
        });
        $this->artisan('player-ability:refresh --pending')
            ->expectsOutputToContain('source_changed_during_build; stage: validation')->assertFailed();
        $this->assertSame($saved, DB::table('player_ability_snapshots')->value('payload'));
        $this->assertTrue(app(PlayerAbilityRefreshState::class)->status()['pending']);
        $this->assertTrue(app(PlayerAbilityRefreshState::class)->status()['failed']);
        // Retry backoff avoids repeatedly fitting an unavailable model each minute.
        $this->artisan('player-ability:refresh --pending')->assertSuccessful();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('diagnosticFailures')]
    public function test_failure_diagnostics_are_safe_and_preserve_the_previous_snapshot(string $failure, string $reason, string $stage): void
    {
        $store = app(PlayerAbilitySnapshotStore::class);
        $store->replace($this->payload(), CarbonImmutable::today('Africa/Johannesburg'), 'before', []);
        $saved = DB::table('player_ability_snapshots')->value('payload');
        $payload = $this->payload();
        $secret = 'password=private-secret SELECT player_name FROM private_table';
        $service = $this->partialMock(PlayerSharedAbilityService::class);
        $service->shouldReceive('fingerprint')->andReturn('same');
        if ($failure === 'calculation') {
            $service->shouldReceive('calculateSnapshot')->once()->andThrow(new \RuntimeException($secret));
        } else {
            if ($failure === 'nonconverged') { $payload['cohorts'] = [['components' => [['converged' => false]]]]; }
            if ($failure === 'cap') { $payload['reason'] = 'Shared calibration exceeds its safe local processing limit '.$secret; }
            if ($failure === 'withheld') { $payload['reason'] = $secret; }
            $service->shouldReceive('calculateSnapshot')->once()->andReturn($payload);
            $mockStore = $this->partialMock(PlayerAbilitySnapshotStore::class);
            if ($failure === 'manifest') { $mockStore->shouldReceive('manifest')->andThrow(new \RuntimeException($secret)); }
            else { $mockStore->shouldReceive('manifest')->andReturn([]); }
            if ($failure === 'revoked') { $mockStore->shouldReceive('published')->andReturn(false); }
            if ($failure === 'save') {
                $mockStore->shouldReceive('published')->andReturn(true);
                $mockStore->shouldReceive('replace')->andThrow(new \RuntimeException($secret));
            }
        }
        \Illuminate\Support\Facades\Log::spy();
        $this->artisan('player-ability:refresh')
            ->expectsOutputToContain($reason.'; stage: '.$stage)->doesntExpectOutputToContain($secret)->assertFailed();
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->once()->with(
            'Player ability refresh failed; last good snapshot retained.',
            ['exception_type' => \RuntimeException::class, 'reason' => $reason, 'stage' => $stage]
        );
        $this->assertSame($saved, DB::table('player_ability_snapshots')->value('payload'));
        $this->assertDatabaseCount('player_ability_snapshots', 1);
        $this->assertNotNull(DB::table('player_ability_refresh_state')->value('last_failed_at'));
    }

    public static function diagnosticFailures(): array
    {
        return [
            ['calculation', 'unexpected_error', 'calculation'],
            ['manifest', 'unexpected_error', 'manifest'],
            ['save', 'unexpected_error', 'save'],
            ['nonconverged', 'model_nonconverged', 'validation'],
            ['cap', 'safe_processing_limit', 'validation'],
            ['withheld', 'calculation_withheld', 'validation'],
            ['revoked', 'publication_or_context_revoked', 'validation'],
        ];
    }

    public function test_consuming_a_generation_does_not_consume_later_committed_changes(): void
    {
        $state = app(PlayerAbilityRefreshState::class);
        $captured = $state->generation();
        DB::table('team_fixture_results')->insert(['team_fixture_id' => 123, 'team1_score' => 6, 'team2_score' => 3]);
        $state->completed($captured);
        $this->assertGreaterThan(DB::table('player_ability_refresh_state')->value('completed_generation'), $state->generation());
    }

    public function test_exact_score_fingerprint_detects_corrections_with_unchanged_timestamps_and_count(): void
    {
        DB::table('team_fixture_results')->insert(['team_fixture_id' => 123, 'team1_score' => 6, 'team2_score' => 3]);
        $service = app(PlayerSharedAbilityService::class);
        $before = $service->fingerprint();
        DB::table('team_fixture_results')->update(['team2_score' => 4]);
        $this->assertNotSame($before, $service->fingerprint());
        $this->assertDatabaseCount('team_fixture_results', 1);
    }

    public function test_missing_state_cannot_interrupt_scores_or_start_minute_calculation(): void
    {
        Schema::drop('player_ability_refresh_state');
        DB::table('team_fixture_results')->insert(['team_fixture_id' => 123, 'team1_score' => 6, 'team2_score' => 3]);
        $this->partialMock(PlayerSharedAbilityService::class)->shouldNotReceive('calculateSnapshot', 'fingerprint');
        $this->artisan('player-ability:refresh --pending')->assertFailed();
        $this->assertDatabaseCount('team_fixture_results', 1);
    }

    public function test_freshness_status_is_private_and_never_calculates(): void
    {
        $url = route('backend.player-performance.badges');
        $this->getJson($url)->assertUnauthorized();
        Role::findOrCreate('admin', 'web');
        $this->actingAs(User::factory()->create()->assignRole('admin'));
        $this->getJson($url)->assertForbidden();
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $this->partialMock(PlayerSharedAbilityService::class)->shouldNotReceive('calculateSnapshot', 'fingerprint');
        $this->getJson($url)->assertOk()->assertJsonPath('status.pending', true)
            ->assertJsonStructure(['status' => ['version', 'last_updated', 'pending', 'failed']])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_category_contexts_are_exclusive_and_cannot_read_another_category_fixture(): void
    {
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $draw = \App\Models\Draw::factory()->create();
        $wrong = \App\Models\Category::factory()->create();
        $registration = \App\Models\Registration::factory()->create();
        $registration->players()->attach(\App\Models\Player::factory()->create());
        $fixture = \App\Models\Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => $registration->id]);
        $url = route('backend.player-performance.badges');
        $this->getJson($url.'?category_id='.$wrong->id.'&draw_id='.$draw->id)->assertUnprocessable();
        $this->partialMock(\App\Services\Performance\PlayerRatingBadgeService::class)->shouldNotReceive('forPlayer');
        $this->getJson($url.'?category_id='.$wrong->id.'&fixtures[]='.$fixture->id)->assertOk()
            ->assertJsonPath('ratings.f:'.$fixture->id.':1', []);
    }
}
