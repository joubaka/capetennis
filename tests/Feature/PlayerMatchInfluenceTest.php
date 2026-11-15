<?php

namespace Tests\Feature;

use App\Models\{Player, User};
use App\Services\Performance\{PlayerAbilitySnapshotStore, PlayerMatchInfluence, PlayerMatchInfluenceCalculator, PlayerMatchInfluenceStore, PlayerRatingAccess, PlayerRatingHistoryStore, PlayerSharedAbilityService};
use Illuminate\Support\Facades\{DB, File, Schema};
use Mockery;
use Tests\TestCase;

class PlayerMatchInfluenceTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        $this->directory = storage_path('framework/testing/match-influence-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($this->directory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function store(): PlayerMatchInfluenceStore
    {
        return new class($this->directory) extends PlayerMatchInfluenceStore {
            public function __construct(private string $directory) {}
            public function path(int $player): string { if ($player < 1) throw new \InvalidArgumentException; return $this->directory.'/player-'.$player.'.json'; }
        };
    }

    private function snapshot(): array
    {
        return ['reason' => null, 'snapshot_as_of' => now('Africa/Johannesburg')->toDateString(), 'snapshot_version' => str_repeat('b', 64),
            'all_age' => ['ratings' => [1 => ['score' => 50.0, 'comparable' => true]]]];
    }

    private function artifact(PlayerMatchInfluenceStore $store): array
    {
        return ['version' => PlayerMatchInfluenceStore::VERSION, 'player_id' => 1, 'policy_hash' => $store->policyHash(),
            'source_fingerprint' => str_repeat('a', 64), 'snapshot_version' => str_repeat('b', 64),
            'as_of' => now('Africa/Johannesburg')->toDateString(), 'built_at' => now('Africa/Johannesburg')->toIso8601String(), 'baseline_score' => 50.0,
            'rows' => [['source_id' => 'match:1', 'date' => now('Africa/Johannesburg')->toDateString(), 'won' => false,
                'opponent' => 'Authorized peer', 'score' => '4-6', 'delta' => -1.0, 'without_score' => 51.0, 'reason' => null]]];
    }

    private function readers(?array $snapshot = null): array
    {
        $snapshots = $this->mock(PlayerAbilitySnapshotStore::class);
        $snapshots->shouldReceive('policyHash')->andReturn(str_repeat('c', 64));
        $snapshots->shouldReceive('current')->andReturn($snapshot ?? $this->snapshot());
        $ability = $this->mock(PlayerSharedAbilityService::class);
        $ability->shouldReceive('fingerprint')->andReturn(str_repeat('a', 64));
        $ability->shouldNotReceive('calculateSnapshot', 'historySnapshot', 'validationSnapshot');
        return [$snapshots, $ability];
    }

    private function player(): Player { $p = new Player; $p->id = 1; return $p; }
    private function user(): User { $u = Mockery::mock(User::class)->makePartial(); $u->id = 10; $u->shouldReceive('hasRole')->with('super-user')->andReturnFalse(); return $u; }

    public function test_authorized_reads_project_only_own_saved_rows_without_refitting_writing_or_restricted_evidence(): void
    {
        [$snapshots, $ability] = $this->readers();
        $store = $this->store(); $saved = $this->artifact($store);
        $restricted = $saved['rows'][0]; $restricted['source_id'] = 'teamsheet:private'; $restricted['opponent'] = 'PRIVATE_SCHOOL_NAME';
        $saved['rows'][] = $restricted; $saved['private_graph'] = ['PRIVATE_GRAPH_NAME'];
        $store->replace(1, $saved, str_repeat('a', 64)); $before = File::get($store->path(1));
        $calculator = Mockery::mock(PlayerMatchInfluenceCalculator::class); $calculator->shouldNotReceive('calculate');
        $history = Mockery::mock(PlayerRatingHistoryStore::class); $history->shouldNotReceive('read', 'replace');
        $access = Mockery::mock(PlayerRatingAccess::class); $access->shouldReceive('allows')->once()->andReturnTrue();
        $service = new PlayerMatchInfluence($calculator, $store, $snapshots, $history, $ability, $access);
        $result = $service->forPlayer($this->user(), $this->player());
        $this->assertNull($result['reason']); $this->assertCount(1, $result['rows']); $this->assertSame(1, $result['restricted']);
        $this->assertSame(-1.0, $result['rows'][0]['delta']);
        $json = json_encode($result);
        foreach (['source_id', 'policy_hash', 'source_fingerprint', 'snapshot_version', 'player_id', 'PRIVATE_SCHOOL_NAME', 'PRIVATE_GRAPH_NAME'] as $private) $this->assertStringNotContainsString($private, $json);
        $this->assertSame($before, File::get($store->path(1)));
    }

    public function test_access_is_checked_before_any_artifact_or_private_source_read(): void
    {
        [$snapshots, $ability] = $this->readers();
        $store = Mockery::mock(PlayerMatchInfluenceStore::class); $store->shouldNotReceive('read');
        $calculator = Mockery::mock(PlayerMatchInfluenceCalculator::class); $calculator->shouldNotReceive('calculate');
        $history = Mockery::mock(PlayerRatingHistoryStore::class); $history->shouldNotReceive('read');
        $access = Mockery::mock(PlayerRatingAccess::class); $access->shouldReceive('allows')->once()->andReturnFalse();
        $service = new PlayerMatchInfluence($calculator, $store, $snapshots, $history, $ability, $access);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $service->forPlayer($this->user(), $this->player());
    }

    public function test_stale_wrong_player_policy_source_snapshot_score_and_publication_are_withheld_without_refits(): void
    {
        foreach (['old_date', 'wrong_player', 'policy', 'source', 'snapshot', 'score', 'publication'] as $change) {
            $snapshot = $this->snapshot();
            if ($change === 'publication') $snapshot['reason'] = 'Source publication changed.';
            [$snapshots, $ability] = $this->readers($snapshot); $store = $this->store(); $saved = $this->artifact($store);
            match ($change) {
                'old_date' => $saved['as_of'] = $saved['rows'][0]['date'] = now('Africa/Johannesburg')->subDay()->toDateString(),
                'wrong_player' => $saved['player_id'] = 2,
                'policy' => $saved['policy_hash'] = str_repeat('d', 64),
                'source' => $saved['source_fingerprint'] = str_repeat('d', 64),
                'snapshot' => $saved['snapshot_version'] = str_repeat('d', 64),
                'score' => $snapshot = null,
                'publication' => null,
            };
            if ($change === 'score') { $saved['baseline_score'] = 60.0; $saved['rows'][0]['without_score'] = 61.0; }
            File::put($store->path(1), json_encode($saved));
            $calculator = Mockery::mock(PlayerMatchInfluenceCalculator::class); $calculator->shouldNotReceive('calculate');
            $history = Mockery::mock(PlayerRatingHistoryStore::class); $history->shouldNotReceive('read');
            $access = Mockery::mock(PlayerRatingAccess::class); $access->shouldReceive('allows')->once()->andReturnTrue();
            $result = (new PlayerMatchInfluence($calculator, $store, $snapshots, $history, $ability, $access))->forPlayer($this->user(), $this->player());
            $this->assertSame([], $result['rows'], $change); $this->assertNotNull($result['reason'], $change);
        }
    }

    public function test_malformed_inconsistent_oversized_and_older_artifacts_cannot_replace_last_good_data(): void
    {
        $this->readers(); $store = $this->store(); $saved = $this->artifact($store);
        $store->replace(1, $saved, str_repeat('a', 64));
        $bad = $saved; $bad['rows'][0]['delta'] = 7.0;
        try { $store->replace(1, $bad, str_repeat('a', 64)); $this->fail('Inconsistent influence accepted'); } catch (\RuntimeException) {}
        $this->assertSame($saved, $store->read(1));
        $older = $saved; $older['built_at'] = now('Africa/Johannesburg')->subDay()->toIso8601String();
        try { $store->replace(1, $older, str_repeat('a', 64)); $this->fail('Older artifact accepted'); } catch (\RuntimeException) {}
        $this->assertSame($saved, $store->read(1));
        File::put($store->path(1), str_repeat('x', PlayerMatchInfluenceStore::MAX_BYTES + 1));
        $this->assertNull($store->read(1));
        File::put($store->path(1), json_encode($saved + ['extra' => 'value']));
        $this->assertNull($store->read(2));
        $bad = $saved; $bad['rows'] = array_fill(0, 6, $saved['rows'][0]); File::put($store->path(1), json_encode($bad));
        $this->assertNull($store->read(1));
    }

    public function test_offline_build_orients_side_two_score_and_preserves_source_results(): void
    {
        Schema::create('player_ability_snapshots', fn ($t) => [$t->string('snapshot_key'), $t->string('source_fingerprint')]);
        DB::table('player_ability_snapshots')->insert(['snapshot_key' => 'current', 'source_fingerprint' => str_repeat('a', 64)]);
        [$snapshots, $ability] = $this->readers(); $snapshots->shouldReceive('published')->once()->andReturnTrue(); $store = $this->store();
        $calculator = Mockery::mock(PlayerMatchInfluenceCalculator::class);
        $calculator->shouldReceive('calculate')->once()->andReturn(['baseline_score' => 50.0, 'rows' => [['source_id' => 'match:1', 'date' => now('Africa/Johannesburg')->toDateString(), 'opponent_id' => 2, 'won' => false, 'delta' => -1.0, 'without_score' => 51.0, 'reason' => null]]]);
        $evidence = ['kind' => 'match', 'cohort' => \App\Services\Performance\AllAgeRatingModel::COHORT, 'source_id' => 'match:1', 'date' => now('Africa/Johannesburg')->toDateString(), 'players' => [2, 1], 'names' => ['Peer', 'Selected'], 'winner_side' => 1, 'score' => '6-4 7-6', 'months' => 1 << 11];
        $history = Mockery::mock(PlayerRatingHistoryStore::class);
        $history->shouldReceive('read')->once()->andReturn(['as_of' => now('Africa/Johannesburg')->toDateString(), 'source_fingerprint' => str_repeat('a', 64), 'publication_manifest' => [], 'evidence' => [$evidence]]);
        $access = Mockery::mock(PlayerRatingAccess::class); $access->shouldNotReceive('allows');
        $before = serialize($evidence);
        $saved = (new PlayerMatchInfluence($calculator, $store, $snapshots, $history, $ability, $access))->build($this->player());
        $this->assertSame('4-6 6-7', $saved['rows'][0]['score']); $this->assertSame('Peer', $saved['rows'][0]['opponent']); $this->assertFalse($saved['rows'][0]['won']);
        $this->assertSame($before, serialize($evidence));
    }

    public function test_pre_save_revalidation_uses_a_fresh_snapshot_even_when_source_fingerprint_is_unchanged(): void
    {
        [$snapshots, $ability] = $this->readers(); $store = $this->store(); $saved = $this->artifact($store);
        $changed = $this->snapshot(); $changed['snapshot_version'] = str_repeat('e', 64);
        $snapshots->shouldReceive('freshCurrent')->once()->andReturn($changed);
        $service = new PlayerMatchInfluence(Mockery::mock(PlayerMatchInfluenceCalculator::class), $store, $snapshots, Mockery::mock(PlayerRatingHistoryStore::class), $ability, Mockery::mock(PlayerRatingAccess::class));
        $this->assertFalse($service->current($saved, freshSnapshot: true));
    }

    public function test_source_changes_during_an_offline_build_fail_without_replacing_last_good_artifact(): void
    {
        Schema::create('player_ability_snapshots', fn ($t) => [$t->string('snapshot_key'), $t->string('source_fingerprint')]);
        DB::table('player_ability_snapshots')->insert(['snapshot_key' => 'current', 'source_fingerprint' => str_repeat('a', 64)]);
        [$snapshots] = $this->readers(); $snapshots->shouldReceive('published')->once()->andReturnTrue();
        $store = $this->store(); $saved = $this->artifact($store); $store->replace(1, $saved, str_repeat('a', 64)); $before = File::get($store->path(1));
        $ability = Mockery::mock(PlayerSharedAbilityService::class); $ability->shouldReceive('fingerprint')->twice()->andReturn(str_repeat('a', 64), str_repeat('d', 64));
        $calculator = Mockery::mock(PlayerMatchInfluenceCalculator::class); $calculator->shouldReceive('calculate')->once()->andReturn(['baseline_score' => 50.0, 'rows' => []]);
        $history = Mockery::mock(PlayerRatingHistoryStore::class); $history->shouldReceive('read')->once()->andReturn(['as_of' => $saved['as_of'], 'source_fingerprint' => str_repeat('a', 64), 'publication_manifest' => [], 'evidence' => []]);
        $service = new PlayerMatchInfluence($calculator, $store, $snapshots, $history, $ability, Mockery::mock(PlayerRatingAccess::class));
        try { $service->build($this->player()); $this->fail('Changed source accepted'); }
        catch (\RuntimeException $error) { $this->assertStringContainsString('Sources changed', $error->getMessage()); }
        $this->assertSame($before, File::get($store->path(1)));
    }

    public function test_cli_revalidates_after_consistent_read_and_retains_last_good_on_failure(): void
    {
        Schema::create('players', fn ($t) => $t->id()); DB::table('players')->insert(['id' => 1]);
        config(['cache.stores.file' => ['driver' => 'file', 'path' => $this->directory.'/cache', 'lock_path' => $this->directory.'/locks']]);
        $this->readers(); $store = $this->store(); $saved = $this->artifact($store); $store->replace(1, $saved, str_repeat('a', 64)); $before = File::get($store->path(1));
        $this->app->instance(PlayerMatchInfluenceStore::class, $store);
        $service = $this->mock(PlayerMatchInfluence::class);
        $service->shouldReceive('build')->twice()->withArgs(fn ($player) => $player->id === 1)->andReturn($saved);
        $service->shouldReceive('current')->twice()->with($saved, true)->andReturn(false, true);
        $this->assertSame(1, \Illuminate\Support\Facades\Artisan::call('player-match-influence:refresh', ['player' => 1]));
        $this->assertSame($before, File::get($store->path(1)));
        $this->assertSame(0, \Illuminate\Support\Facades\Artisan::call('player-match-influence:refresh', ['player' => 1]));
        $this->assertSame($saved, $store->read(1));
        $this->assertDatabaseCount('players', 1);
    }

    public function test_canonical_fresh_reader_discards_its_scoped_cached_snapshot(): void
    {
        Schema::create('player_ability_snapshots', function ($t) {
            $t->string('snapshot_key'); $t->integer('model_version'); $t->string('policy_hash'); $t->string('built_at');
            $t->string('as_of'); $t->string('source_fingerprint'); $t->string('publication_fingerprint');
        });
        $reader = new PlayerAbilitySnapshotStore;
        $initial = $reader->current();
        DB::table('player_ability_snapshots')->insert(['snapshot_key' => 'current', 'model_version' => -1, 'policy_hash' => 'previous-policy',
            'built_at' => now()->toIso8601String(), 'as_of' => now('Africa/Johannesburg')->toDateString(), 'source_fingerprint' => str_repeat('a', 64), 'publication_fingerprint' => str_repeat('a', 64)]);
        $this->assertSame($initial, $reader->current());
        $fresh = $reader->freshCurrent();
        $this->assertNotSame($initial['reason'], $fresh['reason']);
        $this->assertNotNull($fresh['snapshot_version']);
    }
}
