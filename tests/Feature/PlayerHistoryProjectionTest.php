<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Services\Performance\{BallRatingModel, PlayerAbilitySnapshotStore, PlayerHistoryProjectionStore, PlayerPlusRecordService, PlayerRatingHistoryService, PlayerRatingHistoryStore, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB, File, Schema};
use Tests\TestCase;

class PlayerHistoryProjectionTest extends TestCase
{
    private PlayerRatingHistoryStore $history;
    private PlayerHistoryProjectionStore $projections;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.stores.file' => ['driver' => 'array']]); DB::purge('sqlite');
        $this->travelTo(CarbonImmutable::parse('2026-11-23 10:00:00', 'Africa/Johannesburg'));
        (require database_path('migrations/2026_11_23_140000_create_player_history_projections.php'))->up();
        $this->history = new class extends PlayerRatingHistoryStore {
            public int $globalReads = 0;
            public function read(): ?array { $this->globalReads++; return null; }
            public function policyHash(): string { return str_repeat('c', 64); }
        };
        $this->projections = app(PlayerHistoryProjectionStore::class);
    }

    private function player(int $id = 42): Player { $player = new Player; $player->id = $id; return $player; }

    private function saved(int $population = 50): array
    {
        $rating = ['score' => 50.0, 'component' => 'example', 'group' => str_repeat('b', 64), 'connection_status' => 'disconnected',
            'historical_connection' => false, 'connection_age_days' => null, 'connection_reference_date' => null];
        $ratings = array_fill_keys(range(42, 42 + $population - 1), $rating);
        $today = CarbonImmutable::today('Africa/Johannesburg'); $months = [];
        for ($index = 0; $index < 12; $index++) $months[] = ['date' => ($index === 11 ? $today : $today->startOfMonth()->subMonths(11 - $index)->endOfMonth())->toDateString(),
            'cohorts' => [BallRatingModel::COHORTS['standard'] => $ratings]];
        $evidence = [];
        for ($id = 42; $id < 42 + $population; $id += 2) $evidence[] = ['kind' => 'match', 'source_id' => 'match:'.$id,
            'cohort' => BallRatingModel::COHORTS['standard'], 'event_id' => 1, 'event' => 'Example Open', 'date' => '2026-11-20', 'completed_at' => '2026-11-20',
            'date_basis' => 'scheduled match date', 'months' => 2048, 'baseline_months' => 0, 'players' => [$id, $id + 1],
            'names' => ['Example '.$id, 'Example '.($id + 1)], 'winner_side' => 1, 'score' => '6-3 6-4', 'rating_ball_type' => 'standard'];
        return ['version' => PlayerRatingHistoryStore::VERSION, 'policy_hash' => $this->history->policyHash(), 'source_fingerprint' => str_repeat('a', 64),
            'as_of' => '2026-11-23', 'built_at' => '2026-11-23T03:02:00+02:00', 'months' => $months, 'evidence' => $evidence,
            'publication_manifest' => ['events' => [['id' => 1, 'published' => 1], ['id' => 999, 'published' => 1]], 'draws' => [], 'fixtures' => [], 'team_fixtures' => []]];
    }

    public function test_indexed_player_read_retains_full_context_but_avoids_global_file_decode_and_other_players_payloads(): void
    {
        $saved = $this->saved(500);
        $this->assertTrue($this->projections->replace($saved, [42, 43]));
        File::shouldReceive('get')->never();
        DB::flushQueryLog(); DB::enableQueryLog();
        $projected = $this->history->readForPlayer(42);
        $queries = DB::getQueryLog(); DB::disableQueryLog();
        $this->assertSame(0, $this->history->globalReads);
        $this->assertSame($saved['publication_manifest'], $projected['publication_manifest']);
        $this->assertCount(1, $projected['evidence']);
        $this->assertSame([42], array_keys($projected['months'][11]['cohorts'][BallRatingModel::COHORTS['standard']]));
        $this->assertLessThan(strlen(json_encode($saved)) / 20, strlen(json_encode($projected)));
        if (getenv('CT_PROJECTION_METRICS') === '1') fwrite(STDERR, json_encode(['synthetic_players' => 500,
            'global_bytes' => strlen(json_encode($saved)), 'selected_player_bytes' => strlen(json_encode($projected)),
            'read_queries' => count($queries), 'global_file_reads' => $this->history->globalReads]).PHP_EOL);
        $this->assertCount(2, DB::table('player_history_projections')->get());
        foreach ($queries as $query) $this->assertMatchesRegularExpression('/^(select|pragma)/i', $query['query']);
        $this->assertTrue(collect($queries)->contains(fn ($query) => str_contains($query['query'], '"player"."player_id" = ?') && in_array(42, $query['bindings'], true)));
    }

    public function test_projection_services_preserve_exact_fingerprint_and_global_publication_revocation_checks(): void
    {
        $saved = $this->saved(); $this->projections->replace($saved, [42]);
        $ability = $this->mock(PlayerSharedAbilityService::class);
        $ability->shouldReceive('fingerprint')->twice()->andReturn(str_repeat('d', 64));
        $snapshots = $this->mock(PlayerAbilitySnapshotStore::class);
        $snapshots->shouldReceive('published')->twice()->with($saved['publication_manifest'])->andReturn(false);
        $service = new PlayerRatingHistoryService($ability, $this->history);
        $this->assertStringContainsString('publication changed', $service->forPlayer($this->player())['reason']);
        $record = (new PlayerPlusRecordService($service))->forPlayer($this->player());
        $this->assertNotNull($record['reason']); $this->assertSame(0, $record['played']);
        $this->assertSame(0, $this->history->globalReads);
    }

    public function test_history_and_playing_record_consume_only_the_named_player_projection(): void
    {
        $saved = $this->saved(); $this->projections->replace($saved, [42]);
        $ability = $this->mock(PlayerSharedAbilityService::class);
        $ability->shouldReceive('fingerprint')->twice()->andReturn($saved['source_fingerprint']);
        $snapshots = $this->mock(PlayerAbilitySnapshotStore::class); $snapshots->shouldReceive('published')->once()->andReturn(true);
        $service = new PlayerRatingHistoryService($ability, $this->history);
        $history = $service->forPlayer($this->player());
        $this->assertCount(12, $history['points']);
        $this->assertSame(1, $history['evidence']['included']);
        $record = (new PlayerPlusRecordService($service))->forPlayer($this->player());
        $this->assertSame(1, $record['played']); $this->assertSame(1, $record['wins']);
        $this->assertSame(0, $this->history->globalReads);
    }

    public function test_missing_malformed_wrong_player_and_obsolete_policy_projections_use_only_validated_legacy_fallback(): void
    {
        $saved = $this->saved(); $this->projections->replace($saved, [42]);
        $this->assertNull($this->history->readForPlayer(99));
        $row = DB::table('player_history_projections')->where('player_id', 42)->first();
        DB::table('player_history_projections')->where('player_id', 42)->update(['checksum' => str_repeat('0', 64)]);
        $this->assertNull($this->history->readForPlayer(42));
        $payload = json_decode($row->payload, true); $payload['player_id'] = 43;
        $json = json_encode($payload);
        DB::table('player_history_projections')->where('player_id', 42)->update(['payload' => $json, 'checksum' => hash('sha256', $json)]);
        $this->assertNull($this->history->readForPlayer(42));
        $this->projections->replace($saved, [42]);
        $build = DB::table('player_history_projection_builds')->first(); $metadata = json_decode($build->metadata, true); $metadata['policy_hash'] = str_repeat('f', 64);
        $json = json_encode($metadata);
        DB::table('player_history_projection_builds')->update(['metadata' => $json, 'checksum' => hash('sha256', $json)]);
        $this->assertNull($this->history->readForPlayer(42));
        $this->assertSame(4, $this->history->globalReads);
    }

    public function test_failed_or_older_build_cannot_replace_the_coherent_current_build(): void
    {
        $saved = $this->saved(); $this->projections->replace($saved, [42, 43]);
        $before = DB::table('player_history_projection_builds')->first();
        $huge = $saved; $huge['built_at'] = '2026-11-23T04:00:00+02:00';
        $large = $saved['evidence'][0]; $large['players'] = [43, 44]; $large['event'] = str_repeat('x', PlayerHistoryProjectionStore::MAX_PLAYER_BYTES); $huge['evidence'][] = $large;
        try { $this->projections->replace($huge, [42, 43]); $this->fail('Oversize player payload activated'); } catch (\OverflowException) {}
        $older = $saved; $older['built_at'] = '2026-11-23T02:00:00+02:00';
        try { $this->projections->replace($older, [42]); $this->fail('Older projection activated'); } catch (\RuntimeException) {}
        $this->assertEquals($before, DB::table('player_history_projection_builds')->first());
        $this->assertSame(2, DB::table('player_history_projections')->count());
        $this->assertNotNull($this->history->readForPlayer(42));
    }

    public function test_canonical_file_replacement_disables_old_projection_even_if_next_projection_build_fails(): void
    {
        $path = storage_path('framework/testing/player-history-projection-'.bin2hex(random_bytes(6)).'.json');
        $store = new class($path) extends PlayerRatingHistoryStore {
            public function __construct(private string $filePath) {}
            public function path(): string { return $this->filePath; }
            public function policyHash(): string { return str_repeat('c', 64); }
        };
        try {
            $saved = $this->saved(); $store->replace($saved, $saved['source_fingerprint']);
            $this->projections->replace($saved, [42]);
            $new = $saved; $new['built_at'] = '2026-11-23T04:00:00+02:00'; $new['evidence'][0]['winner_side'] = 2;
            $store->replace($new, $new['source_fingerprint']);
            $this->assertSame(0, DB::table('player_history_projection_builds')->count());
            $huge = $new; $huge['evidence'][0]['event'] = str_repeat('x', PlayerHistoryProjectionStore::MAX_PLAYER_BYTES);
            try { $this->projections->replace($huge, [42]); $this->fail('Oversize player payload activated'); } catch (\OverflowException) {}
            $this->assertSame(2, $store->readForPlayer(42)['evidence'][0]['winner_side']);
            $this->assertSame(0, DB::table('player_history_projection_builds')->count());
        } finally { File::delete($path); }
    }

    public function test_recomputed_checksums_cannot_inject_other_player_ratings_or_unvalidated_metadata_payloads(): void
    {
        $saved = $this->saved(); $this->projections->replace($saved, [42]);
        $row = DB::table('player_history_projections')->first(); $payload = json_decode($row->payload, true);
        $payload['months'][0]['cohorts'][BallRatingModel::COHORTS['standard']][99] = $payload['months'][0]['cohorts'][BallRatingModel::COHORTS['standard']][42];
        $json = json_encode($payload);
        DB::table('player_history_projections')->update(['payload' => $json, 'checksum' => hash('sha256', $json)]);
        $this->assertNull($this->history->readForPlayer(42));
        $this->projections->replace($saved, [42]);
        $build = DB::table('player_history_projection_builds')->first(); $metadata = json_decode($build->metadata, true);
        $metadata['evidence'] = $saved['evidence']; $json = json_encode($metadata);
        DB::table('player_history_projection_builds')->update(['metadata' => $json, 'checksum' => hash('sha256', $json)]);
        $this->assertNull($this->history->readForPlayer(42));
        unset($metadata['evidence']); $metadata['source_fingerprint'] = ['invalid']; $json = json_encode($metadata);
        DB::table('player_history_projection_builds')->update(['metadata' => $json, 'checksum' => hash('sha256', $json)]);
        $this->assertNull($this->history->readForPlayer(42));
        $this->assertSame(3, $this->history->globalReads);
    }

    public function test_nightly_history_command_builds_active_player_projections_and_reports_failure_honestly(): void
    {
        Schema::create('players', fn ($table) => $table->id());
        DB::table('players')->insert([['id' => 42], ['id' => 99]]);
        $saved = $this->saved();
        $this->mock(PlayerRatingHistoryService::class)->shouldReceive('calculate')->once()->andReturn($saved);
        $this->mock(PlayerRatingHistoryStore::class)->shouldReceive('replace')->once()->with($saved, $saved['source_fingerprint']);
        $this->mock(PlayerSharedAbilityService::class)->shouldReceive('fingerprint')->once()->andReturn($saved['source_fingerprint']);
        $this->mock(\App\Services\Performance\PlayerAbilityConsistentRead::class)->shouldReceive('run')->once()->andReturnUsing(fn ($read) => $read());
        $this->mock(\App\Services\Performance\PlayerPlusEntitlement::class)->shouldReceive('activePlayers')->once()->andReturn(Player::query()->where('id', 42));
        $projection = $this->mock(PlayerHistoryProjectionStore::class);
        $projection->shouldReceive('ready')->once()->andReturn(true);
        $projection->shouldReceive('replace')->once()->with($saved, [42])->andThrow(new \RuntimeException('Synthetic build failure'));
        $refresh = $this->mock(\App\Services\Performance\PlayerRatingHistoryRefresh::class);
        $refresh->shouldReceive('running')->once(); $refresh->shouldReceive('failed')->once(); $refresh->shouldNotReceive('completed');
        $this->artisan('player-rating-history:refresh')
            ->expectsOutput('Rating history was saved, but player projections failed. Pages retain the validated history fallback; review the failed refresh.')
            ->assertFailed();
    }

    public function test_projection_preserves_separate_ball_pools_and_never_fills_a_missing_pool(): void
    {
        $saved = $this->saved();
        foreach ($saved['months'] as &$month) {
            $rating = $month['cohorts'][BallRatingModel::COHORTS['standard']][42];
            $month['cohorts'][BallRatingModel::COHORTS['red']] = [42 => array_replace($rating, ['score' => 25.0])];
            $month['cohorts'][BallRatingModel::COHORTS['orange']] = [42 => array_replace($rating, ['score' => 60.0])];
        }
        unset($month);
        unset($saved['months'][11]['cohorts'][BallRatingModel::COHORTS['standard']][42]);
        $this->projections->replace($saved, [42]);
        $projected = $this->history->readForPlayer(42);
        $cohorts = $projected['months'][11]['cohorts'];
        $this->assertEquals(25, $cohorts[BallRatingModel::COHORTS['red']][42]['score']);
        $this->assertEquals(60, $cohorts[BallRatingModel::COHORTS['orange']][42]['score']);
        $this->assertSame([], $cohorts[BallRatingModel::COHORTS['standard']]);
    }

    public function test_unavailable_reader_memory_budget_withholds_projection_before_payload_fetch(): void
    {
        $this->projections->replace($this->saved(), [42]);
        $this->mock(\App\Services\Performance\PlayerRatingReadBudget::class)->shouldReceive('ensure')->once()->andReturn(false);
        DB::flushQueryLog(); DB::enableQueryLog();
        $this->assertNull($this->history->readForPlayer(42));
        $queries = DB::getQueryLog(); DB::disableQueryLog();
        $this->assertFalse(collect($queries)->contains(fn ($query) => str_contains($query['query'], 'inner join "player_history_projections"')));
        $this->assertSame(1, $this->history->globalReads);
    }
}
