<?php

namespace Tests\Feature;

use App\Http\Controllers\Backend\PlayerMembershipPreviewController;
use App\Models\User;
use App\Services\Performance\{PlayerAbilityConsistentRead, PlayerAbilitySnapshotStore, PlayerMembershipComparisonService, PlayerMembershipHistoryService,
    PlayerMembershipPreviewService, PlayerPerformanceHistoryService, PlayerPerformancePilotService, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Bus, DB, Mail, Queue, Schema};
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PlayerMembershipHistoryTest extends TestCase
{
    private array $snapshot;
    private array $preview;
    private array $row;
    private string $version;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-29', 'Africa/Johannesburg'));
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']); DB::purge('sqlite');
        Schema::create('events', function (Blueprint $t) { $t->integer('id')->primary(); $t->string('name'); $t->boolean('published'); $t->string('start_date'); $t->string('end_date'); });
        DB::table('events')->insert(['id' => 1, 'name' => 'Published test event', 'published' => true, 'start_date' => '2026-10-09', 'end_date' => '2026-10-11']);
        Schema::create('player_ability_snapshots', function (Blueprint $t) {
            $t->string('snapshot_key')->primary(); foreach (['built_at', 'as_of', 'source_fingerprint', 'publication_fingerprint', 'policy_hash'] as $c) { $t->string($c); } $t->integer('model_version');
        });
        $meta = ['snapshot_key' => 'current', 'built_at' => '2026-10-29 00:01:03', 'as_of' => '2026-10-29', 'source_fingerprint' => 'unchanged', 'publication_fingerprint' => 'publication', 'policy_hash' => 'policy', 'model_version' => 7];
        DB::table('player_ability_snapshots')->insert($meta);
        $this->version = hash('sha256', json_encode([$meta['built_at'], $meta['as_of'], $meta['source_fingerprint'], $meta['publication_fingerprint'], $meta['policy_hash'], $meta['model_version']]));
        Schema::create('player_ability_refresh_state', function (Blueprint $t) { $t->integer('id')->primary(); $t->integer('generation'); $t->integer('completed_generation'); $t->string('last_failed_at')->nullable(); });
        DB::table('player_ability_refresh_state')->insert(['id' => 1, 'generation' => 2, 'completed_generation' => 2]);
        $this->snapshot = ['snapshot_version' => $this->version, 'source_matches' => ['team-match:700'], 'names' => [1 => 'First Player', 2 => 'Second Player'],
            'cohorts' => ['u11 boys' => ['players' => [1 => ['confidence_matches' => [['event' => 1, 'opponent' => 2, 'date' => '2026-10-10', 'basis' => 'scheduled match date']]],
                2 => ['confidence_matches' => [['event' => 1, 'opponent' => 1, 'date' => '2026-10-10', 'basis' => 'scheduled match date']]]]]]];
        $this->preview = ['reason' => null, 'as_of' => '2026-10-29', 'snapshot_version' => $this->version, 'rows' => [$this->player(1), $this->player(2)]];
        $this->row = ['source' => 'Team match', 'fixture_id' => 700, 'event_id' => 1, 'event' => 'Published test event', 'player1_id' => 1, 'player2_id' => 2,
            'reason' => null, 'discipline' => 'singles', 'shared_cohort' => 'u11 boys', 'cohort' => 'u11 boys', 'winner_side' => 2,
            'confidence_date' => '2026-10-10', 'confidence_date_basis' => 'scheduled match date', 'confidence_future' => false, 'score' => '2-6 4-6'];
    }

    protected function tearDown(): void { CarbonImmutable::setTestNow(); parent::tearDown(); }

    private function player(int $id): array
    {
        return ['id' => $id, 'name' => 'Player '.$id, 'cohort' => 'u11 boys', 'component' => 'one', 'status' => 'Provisional', 'score' => 45.0 + $id];
    }

    private function history(array $rows, string $fingerprint = 'unchanged'): PlayerMembershipHistoryService
    {
        $store = Mockery::mock(PlayerAbilitySnapshotStore::class); $store->shouldReceive('current')->andReturn($this->snapshot); $store->shouldNotReceive('replace');
        $preview = Mockery::mock(PlayerMembershipPreviewService::class); $preview->shouldReceive('build')->with('u11 boys')->andReturn($this->preview);
        $shared = Mockery::mock(PlayerSharedAbilityService::class); $shared->shouldReceive('fingerprint')->andReturn($fingerprint); $shared->shouldReceive('cohort')->andReturnUsing(fn ($v) => $v);
        $canonical = Mockery::mock(PlayerPerformanceHistoryService::class);
        $canonical->shouldReceive('individualMatches')->withArgs(fn ($event, $player, $pilot, $date, $network) => $player === null && $network === true)->andReturnUsing(function () { yield from []; });
        $canonical->shouldReceive('teamMatches')->withArgs(fn ($event, $player) => $player === null)->andReturnUsing(function () use ($rows) { yield from $rows; });
        return new PlayerMembershipHistoryService($store, $preview, app(PlayerAbilityConsistentRead::class), $shared, $canonical, app(PlayerPerformancePilotService::class));
    }

    public function test_one_network_read_produces_correct_player_relative_scores_and_results_for_both_players_without_writes(): void
    {
        Bus::fake(); Queue::fake(); Mail::fake(); $before = DB::table('player_ability_snapshots')->first();
        $r = $this->history([$this->row, $this->row])->load('u11 boys', [1, 2], $this->version);
        $this->assertTrue($r['available']);
        $this->assertSame('2-6 4-6', $r['players'][1]['matches'][0]['score']);
        $this->assertSame('6-2 6-4', $r['players'][2]['matches'][0]['score']);
        $this->assertFalse($r['players'][1]['matches'][0]['won']); $this->assertTrue($r['players'][2]['matches'][0]['won']);
        $this->assertSame(1, $r['players'][2]['wins']); $this->assertCount(1, $r['players'][1]['matches']);
        $this->assertSame(0, DB::transactionLevel()); $this->assertSame(0, (int) DB::selectOne('PRAGMA query_only')->query_only);
        $this->assertEquals($before, DB::table('player_ability_snapshots')->first());
        Bus::assertNothingDispatched(); Queue::assertNothingPushed(); Mail::assertNothingSent(); Mail::assertNothingQueued();
    }

    public function test_changed_results_pending_failed_or_replaced_snapshot_withholds_every_history(): void
    {
        $this->assertFalse($this->history([$this->row], 'changed-score')->load('u11 boys', [1], $this->version)['available']);
        $this->assertFalse($this->history([$this->row])->load('u11 boys', [1], 'different-snapshot')['available']);
        DB::table('player_ability_refresh_state')->update(['generation' => 3]);
        $this->assertFalse($this->history([$this->row])->load('u11 boys', [1], $this->version)['available']);
        DB::table('player_ability_refresh_state')->update(['generation' => 2, 'last_failed_at' => '2026-10-29 01:00:00']);
        $this->assertFalse($this->history([$this->row])->load('u11 boys', [1], $this->version)['available']);
    }

    public function test_canonical_rejections_wrong_identity_cohort_date_basis_and_missing_saved_sources_fail_closed(): void
    {
        foreach ([['reason' => 'Unverified identity'], ['discipline' => 'doubles'], ['player2_id' => 99], ['shared_cohort' => 'u12 boys'],
            ['confidence_date_basis' => 'event start proxy'], ['confidence_date' => '2026-10-11'], ['confidence_future' => true], ['fixture_id' => 701]] as $change) {
            $r = $this->history([array_replace($this->row, $change)])->load('u11 boys', [1], $this->version);
            $this->assertFalse($r['available']); $this->assertSame([], $r['players']);
        }
        $this->assertFalse($this->history([$this->row])->load('u11 boys', [999], $this->version)['available']);
        $this->assertFalse($this->history([$this->row])->load('u14 boys', [1], $this->version)['available']);
        DB::table('events')->where('id', 1)->update(['published' => false]);
        $this->assertFalse($this->history([$this->row])->load('u11 boys', [1], $this->version)['available']);
    }

    public function test_new_unfrozen_sources_are_not_added_and_confidence_multiset_must_be_complete(): void
    {
        $extra = array_replace($this->row, ['fixture_id' => 999]);
        $r = $this->history([$this->row, $extra])->load('u11 boys', [1], $this->version);
        $this->assertTrue($r['available']); $this->assertCount(1, $r['players'][1]['matches']);
        $this->snapshot['cohorts']['u11 boys']['players'][1]['confidence_matches'][] = $this->snapshot['cohorts']['u11 boys']['players'][1]['confidence_matches'][0];
        $r = $this->history([$this->row])->load('u11 boys', [1], $this->version);
        $this->assertFalse($r['available']); $this->assertSame([], $r['players']);
    }

    public function test_unrated_empty_history_is_explicit_and_oversized_history_is_withheld_without_partial_results(): void
    {
        $this->snapshot['cohorts']['u11 boys']['players'][1]['confidence_matches'] = [];
        $r = $this->history([])->load('u11 boys', [1], $this->version);
        $this->assertTrue($r['available']); $this->assertSame(0, $r['players'][1]['played_total']); $this->assertSame([], $r['players'][1]['matches']);
        $m = $this->snapshot['cohorts']['u11 boys']['players'][2]['confidence_matches'][0];
        $this->snapshot['cohorts']['u11 boys']['players'][1]['confidence_matches'] = array_fill(0, 501, $m);
        $r = $this->history([$this->row])->load('u11 boys', [1], $this->version);
        $this->assertFalse($r['available']); $this->assertSame([], $r['players']);
    }

    public function test_added_cohorts_use_the_same_frozen_history_identity_and_source_rules(): void
    {
        $snapshot = $this->snapshot; $preview = $this->preview;
        foreach (['u10 boys', 'u10 girls', 'u11 girls', 'u13 boys', 'u13 girls'] as $cohort) {
            $this->snapshot = $snapshot; $this->snapshot['cohorts'] = [$cohort => $snapshot['cohorts']['u11 boys']];
            $this->preview = $preview; foreach ($this->preview['rows'] as &$row) { $row['cohort'] = $cohort; } unset($row);
            $store = Mockery::mock(PlayerAbilitySnapshotStore::class); $store->shouldReceive('current')->andReturn($this->snapshot);
            $scope = Mockery::mock(PlayerMembershipPreviewService::class); $scope->shouldReceive('build')->with($cohort)->andReturn($this->preview);
            $shared = Mockery::mock(PlayerSharedAbilityService::class); $shared->shouldReceive('fingerprint')->once()->andReturn('unchanged');
            $canonical = Mockery::mock(PlayerPerformanceHistoryService::class);
            $canonical->shouldReceive('individualMatches')->andReturnUsing(function () { yield from []; });
            $source = array_replace($this->row, ['shared_cohort' => $cohort]);
            $canonical->shouldReceive('teamMatches')->andReturnUsing(function () use ($source) { yield $source; });
            $history = new PlayerMembershipHistoryService($store, $scope, app(PlayerAbilityConsistentRead::class), $shared, $canonical, app(PlayerPerformancePilotService::class));
            $result = $history->load($cohort, [2], $this->version);
            $this->assertTrue($result['available']); $this->assertSame('6-2 6-4', $result['players'][2]['matches'][0]['score']);
        }
    }

    public function test_comparison_only_returns_descriptive_gap_for_current_same_cohort_and_group_with_verified_evidence(): void
    {
        $service = new PlayerMembershipComparisonService;
        $a = $this->player(1); $b = $this->player(2);
        $history = $this->history([$this->row])->load('u11 boys', [1, 2], $this->version);
        $r = $service->assess($a, $b, $history);
        $this->assertTrue($r['comparable']); $this->assertTrue($r['provisional']); $this->assertSame(-1.0, $r['gap']);
        $this->assertSame(['played' => 1, 'first_wins' => 0, 'second_wins' => 1], $r['head_to_head']);
        foreach ([['component' => 'other'], ['cohort' => 'u12 boys'], ['status' => 'Inactive'], ['status' => 'Unrated', 'score' => null], ['id' => 1]] as $change) {
            $r = $service->assess($a, array_replace($b, $change), $history);
            $this->assertFalse($r['comparable']); $this->assertNull($r['gap']); $this->assertNull($r['head_to_head']);
        }
        $this->assertNull($service->assess($a, $b, ['available' => false])['gap']);
    }

    public function test_detail_controller_paginates_history_and_compare_requires_explicit_scope_before_loading(): void
    {
        $this->app['env'] = 'local';
        $user = Mockery::mock(User::class)->makePartial(); $user->shouldReceive('hasRole')->with('super-user')->andReturnTrue();
        $preview = Mockery::mock(PlayerMembershipPreviewService::class); $preview->shouldReceive('build')->with('u11 boys')->andReturn($this->preview + ['cohorts' => PlayerMembershipPreviewService::COHORTS]);
        $history = Mockery::mock(PlayerMembershipHistoryService::class); $history->shouldReceive('load')->once()->with('u11 boys', [1], $this->version)->andReturn(['available' => true, 'reason' => null, 'players' => [1 => ['matches' => array_fill(0, 45, $this->row)]]]);
        $factory = Mockery::mock(\Illuminate\Contracts\Routing\ResponseFactory::class);
        $factory->shouldReceive('view')->once()->withArgs(function ($view, $data) {
            $this->assertSame('backend.player-performance.membership-preview.detail', $view);
            $this->assertCount(20, $data['historyPage']->items()); $this->assertSame(45, $data['historyPage']->total()); $this->assertSame(2, $data['historyPage']->currentPage());
            $this->assertStringContainsString('cohort=u11%20boys', $data['historyPage']->url(3)); return true;
        })->andReturn(new \Illuminate\Http\Response('detail'));
        $this->app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class, $factory);
        $request = Request::create('/players/1', 'GET', ['cohort' => 'u11 boys', 'page' => 2]); $request->setUserResolver(fn () => $user);
        $response = (new PlayerMembershipPreviewController)->detail($request, '1', $preview, $history);
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $request = Request::create('/compare', 'GET', ['first' => 1, 'second' => 2]); $request->setUserResolver(fn () => $user);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        (new PlayerMembershipPreviewController)->compare($request, $preview, $history, new PlayerMembershipComparisonService);
    }

    public function test_new_actions_enforce_local_role_and_selected_player_scope_before_history(): void
    {
        $preview = Mockery::mock(PlayerMembershipPreviewService::class); $preview->shouldReceive('build')->with('u11 boys')->andReturn($this->preview);
        $history = Mockery::mock(PlayerMembershipHistoryService::class); $history->shouldNotReceive('load');
        $controller = new PlayerMembershipPreviewController;
        $ordinary = Mockery::mock(User::class)->makePartial(); $ordinary->shouldReceive('hasRole')->with('super-user')->andReturnFalse();
        foreach (['detail', 'compare'] as $action) {
            $invoke = fn ($request) => $action === 'detail' ? $controller->detail($request, '1', $preview, $history) : $controller->compare($request, $preview, $history, new PlayerMembershipComparisonService);
            $this->app['env'] = 'production';
            try { $invoke(Request::create('/preview')); $this->fail(); } catch (HttpException $e) { $this->assertSame(404, $e->getStatusCode()); }
            $this->app['env'] = 'local';
            foreach ([null, $ordinary] as $actor) {
                $request = Request::create('/preview'); $request->setUserResolver(fn () => $actor);
                try { $invoke($request); $this->fail(); } catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
            }
        }
        $user = Mockery::mock(User::class)->makePartial(); $user->shouldReceive('hasRole')->with('super-user')->andReturnTrue();
        $request = Request::create('/players/999', 'GET', ['cohort' => 'u11 boys']); $request->setUserResolver(fn () => $user);
        try { $controller->detail($request, '999', $preview, $history); $this->fail(); } catch (HttpException $e) { $this->assertSame(404, $e->getStatusCode()); }
    }
}
