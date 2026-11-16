<?php

namespace Tests\Feature;

use App\Models\{Player, User};
use App\Services\Performance\{AllAgeRatingModel, PlayerAbilitySnapshotStore, PlayerMatchChronologySources, PlayerMatchProgress, PlayerMatchProgressCalculator, PlayerMatchProgressStore, PlayerRatingAccess, PlayerRatingHistoryStore, PlayerSharedAbilityService};
use Illuminate\Support\Facades\{Artisan, DB, File, Schema};
use Mockery;
use Tests\TestCase;

class PlayerMatchProgressTest extends TestCase
{
    private string $directory;
    protected function setUp(): void
    {
        parent::setUp(); config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']); DB::purge('sqlite');
        $this->directory = storage_path('framework/testing/match-progress-'.bin2hex(random_bytes(6))); File::ensureDirectoryExists($this->directory);
        $this->mock(\App\Services\Performance\PlayerRatingTopTen::class)->shouldReceive('forMatchPlayers')->andReturn([2 => ['score' => 99.9]]);
    }
    protected function tearDown(): void { File::deleteDirectory($this->directory); parent::tearDown(); }
    private function store(): PlayerMatchProgressStore
    {
        return new class($this->directory) extends PlayerMatchProgressStore {
            public function __construct(private string $directory) {}
            public function path(int $player): string { if ($player < 1) throw new \InvalidArgumentException; return $this->directory.'/player-'.$player.'.json'; }
        };
    }
    private function snapshot(): array
    {
        return ['reason' => null, 'snapshot_as_of' => now('Africa/Johannesburg')->toDateString(), 'snapshot_version' => str_repeat('b', 64),
            'all_age' => ['ratings' => [1 => ['score' => 52.6, 'comparable' => true]], 'history_evidence' => ['matches' => []]]];
    }
    private function readers(?array $snapshot = null): array
    {
        $snapshots = $this->mock(PlayerAbilitySnapshotStore::class); $snapshots->shouldReceive('policyHash')->andReturn(str_repeat('c', 64)); $snapshots->shouldReceive('current')->andReturn($snapshot ?? $this->snapshot());
        $ability = $this->mock(PlayerSharedAbilityService::class); $ability->shouldReceive('fingerprint')->andReturn(str_repeat('a', 64)); $ability->shouldNotReceive('calculateSnapshot', 'validationSnapshot');
        return [$snapshots, $ability];
    }
    private function artifact(PlayerMatchProgressStore $store): array
    {
        $date = now('Africa/Johannesburg')->toDateString();
        return ['version' => PlayerMatchProgressStore::VERSION, 'basis' => 'scheduled_match_order', 'player_id' => 1, 'policy_hash' => $store->policyHash(), 'source_fingerprint' => str_repeat('a', 64),
            'snapshot_version' => str_repeat('b', 64), 'as_of' => $date, 'built_at' => now()->toIso8601String(), 'current_score' => 52.6,
            'own_catalog' => ['match:1' => ['date' => $date, 'opponent' => 'Authorized peer', 'score' => '6-1', 'won' => true]],
            'rows' => [['source_id' => 'match:1', 'date' => $date, 'time' => $date.' 09:00:00', 'opponent' => 'Authorized peer', 'score' => '6-1', 'won' => true,
                'before_score' => 50.0, 'after_score' => 52.6, 'delta' => 2.6, 'reason' => null,
                'evidence_refs' => [['source_id' => 'match:1', 'before' => false, 'after' => true, 'opponent_id' => 2, 'opponent_before' => 0.0, 'opponent_after' => 0.1]], 'omitted_matches' => 6,
                'explanation' => ['network_before' => 10, 'network_after' => 11, 'own_before' => 6, 'own_after' => 7, 'opponents_before' => 2, 'opponents_after' => 2]]]];
    }
    private function player(): Player { $p = new Player; $p->id = 1; return $p; }
    private function user(): User { $u = Mockery::mock(User::class)->makePartial(); $u->id = 10; $u->shouldReceive('hasRole')->with('super-user')->andReturnFalse(); return $u; }
    private function readerService(PlayerMatchProgressStore $store, $snapshots, $ability, bool $allowed = true): PlayerMatchProgress
    {
        $calculator = Mockery::mock(PlayerMatchProgressCalculator::class); $calculator->shouldNotReceive('latest', 'pair');
        $chronology = Mockery::mock(PlayerMatchChronologySources::class); $chronology->shouldNotReceive('resolve');
        $history = Mockery::mock(PlayerRatingHistoryStore::class); $history->shouldNotReceive('read', 'replace');
        $ability->shouldNotReceive('historySnapshot');
        $access = Mockery::mock(PlayerRatingAccess::class); $access->shouldReceive('allows')->once()->andReturn($allowed);
        return new PlayerMatchProgress($calculator, $chronology, $store, $snapshots, $history, $ability, $access);
    }
    public function test_authorized_get_only_projects_saved_own_rows_and_never_reconstructs_or_exposes_private_provenance(): void
    {
        [$snapshots, $ability] = $this->readers(); $store = $this->store(); $saved = $this->artifact($store);
        $private = $saved['rows'][0]; $private['source_id'] = 'teamsheet:private'; $private['evidence_refs'][0]['source_id'] = 'teamsheet:private'; $private['opponent'] = 'PRIVATE_SCHOOL_PEER'; $saved['rows'][] = $private; $saved['private_graph'] = ['PRIVATE_GRAPH'];
        $saved['own_catalog']['teamsheet:private'] = ['date' => $saved['as_of'], 'opponent' => 'PRIVATE_EVIDENCE_PEER', 'score' => '0-6', 'won' => false];
        $saved['rows'][0]['evidence_refs'][] = ['source_id' => 'teamsheet:private', 'before' => true, 'after' => true, 'opponent_id' => 2, 'opponent_before' => 0.0, 'opponent_after' => 0.1]; $saved['rows'][0]['omitted_matches']--;
        $store->replace(1, $saved, str_repeat('a', 64)); $before = File::get($store->path(1));
        $result = $this->readerService($store, $snapshots, $ability)->forPlayer($this->user(), $this->player());
        $this->assertNull($result['reason']); $this->assertCount(1, $result['rows']); $this->assertSame(1, $result['restricted']); $this->assertSame(50.0, $result['rows'][0]['before_score']);
        foreach (['source_id', 'player_id', 'policy_hash', 'source_fingerprint', 'snapshot_version', 'PRIVATE_SCHOOL_PEER', 'PRIVATE_GRAPH'] as $private) $this->assertStringNotContainsString($private, json_encode($result));
        $this->assertSame($before, File::get($store->path(1)));
        $this->assertCount(1, $result['rows'][0]['used_matches']); $this->assertSame(6, $result['rows'][0]['hidden_matches']);
        $this->assertFalse($result['rows'][0]['used_matches'][0]['before']); $this->assertTrue($result['rows'][0]['used_matches'][0]['after']);
        $this->assertStringNotContainsString('PRIVATE_EVIDENCE_PEER', json_encode($result));
        $this->assertSame(0.0, $result['rows'][0]['used_matches'][0]['opponent_before']);
        $this->assertStringNotContainsString('opponent_id', json_encode($result));
    }
    public function test_access_is_checked_before_reading_private_artifacts(): void
    {
        [$snapshots, $ability] = $this->readers(); $store = Mockery::mock(PlayerMatchProgressStore::class); $store->shouldNotReceive('read');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->readerService($store, $snapshots, $ability, false)->forPlayer($this->user(), $this->player());
    }
    public function test_current_comparison_permission_denial_or_failure_withholds_only_opponent_numbers(): void
    {
        foreach (['denied', 'failure'] as $case) {
            [$snapshots, $ability] = $this->readers(); $store = $this->store(); $saved = $this->artifact($store); $store->replace(1, $saved, str_repeat('a', 64));
            $guard = Mockery::mock(\App\Services\Performance\PlayerRatingTopTen::class);
            $expectation = $guard->shouldReceive('forMatchPlayers')->once()->withArgs(fn ($user, $player, $ids) => $ids === [2]);
            if ($case === 'failure') $expectation->andThrow(new \RuntimeException('PRIVATE_FAILURE'));
            else $expectation->andReturn([]);
            $this->app->instance(\App\Services\Performance\PlayerRatingTopTen::class, $guard);
            $result = $this->readerService($store, $snapshots, $ability)->forPlayer($this->user(), $this->player());
            $used = $result['rows'][0]['used_matches'][0]; $this->assertSame('Authorized peer', $used['opponent']);
            $this->assertNull($used['opponent_before']); $this->assertNull($used['opponent_after']);
            $this->assertStringNotContainsString('opponent_id', json_encode($result)); $this->assertStringNotContainsString('PRIVATE_FAILURE', json_encode($result));
        }
    }
    public function test_stale_player_policy_source_version_score_publication_and_basis_mismatches_are_withheld(): void
    {
        foreach (['date', 'player', 'policy', 'source', 'version', 'artifact_version', 'score', 'publication', 'basis'] as $change) {
            $snapshot = $this->snapshot(); if ($change === 'publication') $snapshot['reason'] = 'Publication changed.';
            [$snapshots, $ability] = $this->readers($snapshot); $store = $this->store(); $saved = $this->artifact($store);
            match ($change) {
                'date' => $saved['as_of'] = $saved['rows'][0]['date'] = now('Africa/Johannesburg')->subDay()->toDateString(), 'player' => $saved['player_id'] = 2,
                'policy' => $saved['policy_hash'] = str_repeat('d', 64), 'source' => $saved['source_fingerprint'] = str_repeat('d', 64), 'version' => $saved['snapshot_version'] = str_repeat('d', 64),
                'artifact_version' => $saved['version'] = 4,
                'score' => $saved['current_score'] = 60.0, 'publication' => null, 'basis' => $saved['basis'] = 'leave_one_out',
            };
            if ($change === 'date') $saved['rows'][0]['time'] = $saved['rows'][0]['date'].' 09:00:00';
            File::put($store->path(1), json_encode($saved));
            $result = $this->readerService($store, $snapshots, $ability)->forPlayer($this->user(), $this->player());
            $this->assertSame([], $result['rows'], $change); $this->assertNotNull($result['reason'], $change);
        }
    }
    public function test_store_rejects_inconsistent_pairs_bad_times_oversize_and_older_replacements(): void
    {
        $this->readers(); $store = $this->store(); $saved = $this->artifact($store); $store->replace(1, $saved, str_repeat('a', 64));
        foreach (['arithmetic', 'time', 'partial', 'older', 'counts', 'extra_private', 'missing_counts', 'catalog_private', 'duplicate_ref', 'wrong_target', 'opponent_range', 'opponent_partial'] as $case) {
            $bad = $saved;
            match ($case) { 'arithmetic' => $bad['rows'][0]['delta'] = 7.0, 'time' => $bad['rows'][0]['time'] = $bad['as_of'].' 99:99:99', 'partial' => $bad['rows'][0]['delta'] = null,
                'older' => $bad['built_at'] = now()->subDay()->toIso8601String(),
                'counts' => $bad['rows'][0]['explanation']['network_after'] = 99,
                'extra_private' => $bad['rows'][0]['explanation']['player_ids'] = [12, 13],
                'missing_counts' => $bad['rows'][0]['explanation'] = null,
                'catalog_private' => $bad['own_catalog']['match:1']['player_id'] = 1,
                'duplicate_ref' => $bad['rows'][0]['evidence_refs'][] = $bad['rows'][0]['evidence_refs'][0],
                'wrong_target' => $bad['rows'][0]['evidence_refs'][0]['source_id'] = 'match:other',
                'opponent_range' => $bad['rows'][0]['evidence_refs'][0]['opponent_before'] = 101,
                'opponent_partial' => $bad['rows'][0]['evidence_refs'][0]['opponent_after'] = null };
            try { $store->replace(1, $bad, str_repeat('a', 64)); $this->fail('Invalid artifact accepted'); } catch (\RuntimeException) {}
            $this->assertSame($saved, $store->read(1));
        }
        $bad = $saved; $bad['rows'] = array_fill(0, 6, $saved['rows'][0]); File::put($store->path(1), json_encode($bad)); $this->assertNull($store->read(1));
        File::put($store->path(1), str_repeat('x', PlayerMatchProgressStore::MAX_BYTES + 1)); $this->assertNull($store->read(1));
    }
    public function test_bounded_evidence_keeps_target_after_only_and_reports_missing_and_omitted_details_honestly(): void
    {
        [$snapshots, $ability] = $this->readers(); $store = $this->store(); $saved = $this->artifact($store);
        $saved['rows'][0]['explanation'] = ['network_before' => 200, 'network_after' => 201, 'own_before' => 105, 'own_after' => 106, 'opponents_before' => 2, 'opponents_after' => 2];
        $saved['rows'][0]['omitted_matches'] = 5;
        for ($i = 0; $i < 100; $i++) $saved['rows'][0]['evidence_refs'][] = ['source_id' => 'match:earlier'.$i, 'before' => true, 'after' => true, 'opponent_id' => 2, 'opponent_before' => 0.0, 'opponent_after' => 0.1];
        $store->replace(1, $saved, str_repeat('a', 64));
        $result = $this->readerService($store, $snapshots, $ability)->forPlayer($this->user(), $this->player());
        $this->assertCount(1, $result['rows'][0]['used_matches']); $this->assertSame(105, $result['rows'][0]['hidden_matches']);
        $this->assertFalse($result['rows'][0]['used_matches'][0]['before']); $this->assertTrue($result['rows'][0]['used_matches'][0]['after']);
        $saved['rows'][0]['evidence_refs'][] = ['source_id' => 'match:excess', 'before' => true, 'after' => true, 'opponent_id' => 2, 'opponent_before' => 0.0, 'opponent_after' => 0.1];
        $this->expectException(\RuntimeException::class); $store->replace(1, $saved, str_repeat('a', 64));
    }
    public function test_offline_builder_uses_full_historical_day_window_and_orients_selected_side_two_scores(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-15', 'Africa/Johannesburg'));
        Schema::create('player_ability_snapshots', fn ($t) => [$t->string('snapshot_key'), $t->string('source_fingerprint')]); DB::table('player_ability_snapshots')->insert(['snapshot_key' => 'current', 'source_fingerprint' => str_repeat('a', 64)]);
        $target = ['source_id' => 'match:9', 'winner' => 2, 'loser' => 1, 'confidence_date' => '2026-10-15'];
        $older = ['source_id' => 'match:old', 'winner' => 1, 'loser' => 2, 'confidence_date' => '2024-11-01'];
        $snapshot = $this->snapshot(); $snapshot['all_age']['history_evidence']['matches'] = [$target];
        [$snapshots, $ability] = $this->readers($snapshot); $snapshots->shouldReceive('published')->once()->andReturnTrue();
        $ability->shouldReceive('historySnapshot')->once()->withArgs(fn ($day, $sources) => $day->toDateString() === '2026-10-15')->andReturnUsing(function ($day, &$sources) use ($older, $target) {
            $sources = [1 => ['matches' => array_map(fn ($m) => ['source_id' => $m['source_id'], 'event_id' => 1, 'event' => 'Verified event',
                'confidence_date' => $m['confidence_date'], 'confidence_date_basis' => 'scheduled match date', 'date' => $m['confidence_date'],
                'player1_id' => 2, 'player2_id' => 1, 'winner_side' => $m['winner'] === 2 ? 1 : 2, 'score' => $m['winner'] === 2 ? '6-1' : '4-6'], [$older, $target])]];
            return ['reason' => null, 'names' => [1 => 'Selected', 2 => 'Historical peer'], 'all_age' => ['history_evidence' => ['matches' => [$older, $target]]]];
        });
        $chronology = Mockery::mock(PlayerMatchChronologySources::class); $chronology->shouldReceive('resolve')->once()->with([$target])->andReturn(['current-order']); $chronology->shouldReceive('resolve')->once()->with([$older, $target])->andReturn(['historical-order']);
        $calculator = Mockery::mock(PlayerMatchProgressCalculator::class); $calculator->shouldReceive('latest')->once()->with(1, [$target], ['current-order'])->andReturn([$target]);
        $calculator->shouldReceive('pair')->once()->withArgs(fn ($player, $source, $matches, $order, $date) => $player === 1 && $source === 'match:9' && $matches === [$older, $target] && $date->toDateString() === '2026-10-15')
            ->andReturn(['time' => '2026-10-15 10:00:00', 'before_score' => 54.0, 'after_score' => 52.6, 'delta' => -1.4, 'reason' => null,
                'own_sources' => [['source_id' => 'match:old', 'date' => '2024-11-01', 'before' => true, 'after' => true, 'opponent_id' => 2, 'opponent_before' => 0.0, 'opponent_after' => 0.1], ['source_id' => 'match:9', 'date' => '2026-10-15', 'before' => false, 'after' => true, 'opponent_id' => 2, 'opponent_before' => 0.0, 'opponent_after' => 0.1]]]);
        $history = Mockery::mock(PlayerRatingHistoryStore::class); $history->shouldReceive('read')->once()->andReturn(['as_of' => '2026-11-15', 'source_fingerprint' => str_repeat('a', 64), 'publication_manifest' => [], 'evidence' => [
            ['kind' => 'match', 'cohort' => AllAgeRatingModel::COHORT, 'source_id' => 'match:9', 'date' => '2026-10-15', 'players' => [2, 1], 'names' => ['Peer', 'Selected'], 'winner_side' => 1, 'score' => '6-1', 'months' => 1 << 11],
        ]]);
        $service = new PlayerMatchProgress($calculator, $chronology, $this->store(), $snapshots, $history, $ability, Mockery::mock(PlayerRatingAccess::class));
        $result = $service->build($this->player()); $this->assertSame('1-6', $result['rows'][0]['score']); $this->assertFalse($result['rows'][0]['won']);
        $this->assertSame('scheduled_match_order', $result['basis']); $this->assertSame(54.0, $result['rows'][0]['before_score']); $this->assertSame(52.6, $result['rows'][0]['after_score']);
        $this->assertSame('6-4', $result['own_catalog']['match:old']['score']); $this->assertTrue($result['own_catalog']['match:old']['won']);
        $this->assertSame('1-6', $result['own_catalog']['match:9']['score']); $this->assertFalse($result['own_catalog']['match:9']['won']);
        $this->assertSame('2024-11-01', $result['own_catalog']['match:old']['date']);
        $this->assertSame(2, count($result['rows'][0]['evidence_refs'])); $this->assertSame(0, $result['rows'][0]['omitted_matches']);
    }
    public function test_cli_checks_fresh_snapshot_and_keeps_last_good_artifact_when_context_changes(): void
    {
        Schema::create('players', fn ($t) => $t->id()); DB::table('players')->insert(['id' => 1]); config(['cache.stores.file' => ['driver' => 'file', 'path' => $this->directory.'/cache', 'lock_path' => $this->directory.'/locks']]);
        $this->readers(); $store = $this->store(); $saved = $this->artifact($store); $store->replace(1, $saved, str_repeat('a', 64)); $before = File::get($store->path(1)); $this->app->instance(PlayerMatchProgressStore::class, $store);
        $service = $this->mock(PlayerMatchProgress::class); $service->shouldReceive('build')->twice()->andReturn($saved); $service->shouldReceive('current')->twice()->with($saved, true)->andReturn(false, true);
        $this->assertSame(1, Artisan::call('player-match-progress:refresh', ['player' => 1])); $this->assertSame($before, File::get($store->path(1)));
        $this->assertSame(0, Artisan::call('player-match-progress:refresh', ['player' => 1])); $this->assertSame($saved, $store->read(1)); $this->assertDatabaseCount('players', 1);
    }
}
