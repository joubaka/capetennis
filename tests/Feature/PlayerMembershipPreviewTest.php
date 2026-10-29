<?php

namespace Tests\Feature;

use App\Http\Controllers\Backend\PlayerMembershipPreviewController;
use App\Models\User;
use App\Services\Performance\{PlayerAbilitySnapshotStore, PlayerMembershipPreviewService, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Bus, DB, Mail, Queue, Schema};
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PlayerMembershipPreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-29 12:00:00', 'Africa/Johannesburg'));
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function snapshot(): array
    {
        $snapshot = ['reason' => null, 'built_at' => '2026-10-29 00:01:03 SAST', 'snapshot_as_of' => '2026-10-29',
            'snapshot_stale' => false, 'names' => [], 'cohorts' => []];
        foreach (['u11 boys' => [4535, 4536, 4555, 4573], 'u12 boys' => [4561], 'u12 girls' => [3741, 3789, 4532, 4533, 4589, 4616]] as $cohort => $ids) {
            foreach ($ids as $id) {
                $snapshot['names'][$id] = 'Preview Player '.$id;
                $snapshot['cohorts'][$cohort]['ratings'][$id] = ['score' => 46.9, 'strength' => -0.12, 'component' => 'group'];
                $snapshot['cohorts'][$cohort]['players'][$id] = ['played' => 6, 'inferred' => 0, 'confidence_matches' => array_map(fn ($opponent) => [
                    'date' => '2026-10-10', 'basis' => 'scheduled match date', 'event' => 241, 'opponent' => $opponent,
                ], range(1, 6))];
            }
            $snapshot['cohorts'][$cohort]['components']['group'] = ['converged' => true, 'trial_anchored' => true, 'inferred' => 5, 'bridge_count' => 1];
            $snapshot['cohorts'][$cohort]['baseline'] = ['anchors' => []];
        }
        return $snapshot;
    }

    private function service(array $snapshot): PlayerMembershipPreviewService
    {
        $store = Mockery::mock(PlayerAbilitySnapshotStore::class);
        $store->shouldReceive('current')->once()->andReturn($snapshot);
        $store->shouldNotReceive('replace');
        return new PlayerMembershipPreviewService($store);
    }

    public function test_all_saved_target_cohort_players_are_included_and_other_cohorts_are_excluded(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['names'][99999] = 'Outside preview';
        $snapshot['cohorts']['u11 boys']['ratings'][99999] = $snapshot['cohorts']['u11 boys']['ratings'][4535];
        $snapshot['cohorts']['u11 boys']['players'][99999] = $snapshot['cohorts']['u11 boys']['players'][4535];
        $snapshot['cohorts']['u14 boys'] = $snapshot['cohorts']['u11 boys'];
        $snapshot['names'][99998] = 'Other cohort only';
        $snapshot['cohorts']['u14 boys']['ratings'][99998] = $snapshot['cohorts']['u11 boys']['ratings'][4535];
        $snapshot['cohorts']['u14 boys']['players'][99998] = $snapshot['cohorts']['u11 boys']['players'][4535];
        $snapshot['cohorts']['u11 boys']['ratings'][4561] = $snapshot['cohorts']['u11 boys']['ratings'][4535];
        $data = $this->service($snapshot)->build();
        $this->assertCount(12, $data['rows']);
        $this->assertSame(12, $data['active_count']);
        $this->assertSame(12, $data['provisional_count']);
        $this->assertCount(12, $data['active']);
        $this->assertSame(0, $data['established_count']);
        $this->assertSame([], array_diff([4535, 4536, 4555, 4573, 4561, 3741, 3789, 4532, 4533, 4589, 4616], array_column($data['active'], 'id')));
        $this->assertContains(99999, array_column($data['rows'], 'id'));
        $this->assertNotContains(99998, array_column($data['rows'], 'id'));
        foreach ($data['rows'] as $row) {
            $this->assertSame([6, 1, 6], [$row['played'], $row['events'], $row['opponents']]);
            $this->assertSame('Provisional', $row['status']);
            $this->assertSame('Medium', $row['confidence']);
        }
        $cp = array_values(array_filter($data['rows'], fn ($row) => $row['id'] === 4561))[0];
        $this->assertSame('u12 boys', $cp['cohort']);
    }

    public function test_established_evidence_is_separate_from_leaderboard_activity_and_uses_recent_diversity_and_reference(): void
    {
        $snapshot = $this->snapshot();
        $node = &$snapshot['cohorts']['u11 boys']['players'][4535];
        foreach ([7, 8] as $opponent) { $node['confidence_matches'][] = ['date' => '2026-09-01', 'basis' => 'scheduled match date', 'event' => 233, 'opponent' => $opponent]; }
        $node['confidence_matches'][] = ['date' => '2024-01-01', 'basis' => 'scheduled match date', 'event' => 100, 'opponent' => 9];
        $node['played'] = count($node['confidence_matches']);
        $data = $this->service($snapshot)->build('u11 boys', '4535');
        $this->assertCount(1, $data['active']);
        $this->assertSame([8, 2, 8], [$data['rows'][0]['played'], $data['rows'][0]['events'], $data['rows'][0]['opponents']]);
        $this->assertSame(11, $data['active_count']);
        $this->assertSame(1, $data['established_count']);
        $snapshot['cohorts']['u11 boys']['components']['group']['trial_anchored'] = false;
        $this->assertSame(0, $this->service($snapshot)->build()['established_count']);
        $snapshot['cohorts']['u11 boys']['components']['group']['trial_anchored'] = true;
        foreach ($node['confidence_matches'] as &$match) { if ($match['date'] !== '2024-01-01') { $match['date'] = '2026-03-01'; } }
        unset($match);
        $data = $this->service($snapshot)->build();
        $this->assertSame(0, $data['established_count']);
        $this->assertSame(11, $data['active_count']);
    }

    public function test_one_match_low_confidence_and_unanchored_players_are_included_with_provisional_cautions(): void
    {
        $snapshot = $this->snapshot();
        $node = &$snapshot['cohorts']['u11 boys']['players'][4535];
        $node['confidence_matches'] = [array_replace($node['confidence_matches'][0], ['date' => '2025-06-01'])]; $node['played'] = 1;
        $snapshot['cohorts']['u11 boys']['components']['group']['trial_anchored'] = false;
        $data = $this->service($snapshot)->build('u11 boys', '4535'); $row = $data['rows'][0];
        $this->assertTrue($row['eligible']); $this->assertFalse($row['established']); $this->assertSame('Provisional', $row['status']);
        $this->assertSame('Low', $row['confidence']); $this->assertNotNull($row['position']); $this->assertNotEmpty($row['needs']);
        $this->assertCount(1, $data['active']);
    }

    public function test_twenty_four_calendar_month_cutoff_is_inclusive_with_leap_and_month_end_dates(): void
    {
        foreach ([['2026-10-29', '2024-10-29'], ['2024-02-29', '2022-02-28'], ['2026-08-31', '2024-08-31']] as [$today, $cutoff]) {
            CarbonImmutable::setTestNow(CarbonImmutable::parse($today, 'Africa/Johannesburg'));
            $snapshot = $this->snapshot(); $snapshot['snapshot_as_of'] = $today; $snapshot['cohorts'] = [];
            $this->addActive($snapshot, 17001, 'u11 boys', 60); $this->addActive($snapshot, 17002, 'u11 boys', 55);
            foreach ([17001 => $cutoff, 17002 => CarbonImmutable::parse($cutoff)->subDay()->toDateString()] as $id => $date) {
                $snapshot['cohorts']['u11 boys']['players'][$id]['confidence_matches'] = [['date' => $date, 'basis' => 'scheduled match date', 'event' => 1, 'opponent' => 99]];
                $snapshot['cohorts']['u11 boys']['players'][$id]['played'] = 1;
            }
            $data = $this->service($snapshot)->build(); $rows = array_column($data['rows'], null, 'id');
            $this->assertTrue($rows[17001]['eligible']); $this->assertSame('Provisional', $rows[17001]['status']);
            $this->assertFalse($rows[17002]['eligible']); $this->assertSame('Inactive', $rows[17002]['status']);
            $this->assertSame(1, $data['active_count']);
        }
    }

    public function test_publication_withheld_stale_future_and_invalid_snapshot_dates_fail_closed_without_names(): void
    {
        foreach ([['reason' => 'publication revoked'], ['snapshot_stale' => true], ['snapshot_as_of' => '2026-10-30'], ['snapshot_as_of' => '2026-02-31']] as $change) {
            $data = $this->service(array_replace($this->snapshot(), $change))->build();
            $this->assertSame([], $data['rows']);
            $this->assertSame([], $data['active']);
            $this->assertNotNull($data['reason']);
            $this->assertNull($data['as_of']);
        }
    }

    public function test_malformed_or_nonconverged_player_data_is_not_displayed(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['cohorts']['u11 boys']['ratings'][4535]['score'] = INF;
        $snapshot['cohorts']['u11 boys']['players'][4536]['confidence_matches'][0]['date'] = '2026-02-31';
        $snapshot['cohorts']['u11 boys']['players'][4555]['confidence_matches'][0]['date'] = '2026-11-01';
        $snapshot['cohorts']['u12 boys']['components']['group']['converged'] = false;
        $data = $this->service($snapshot)->build();
        $this->assertCount(7, $data['rows']);
        $this->assertSame(4, $data['unavailable_count']);
    }

    public function test_real_saved_reader_works_under_sqlite_read_only_guard_without_snapshot_writes_jobs_or_mail(): void
    {
        Schema::create('player_ability_snapshots', function (Blueprint $table) {
            $table->string('snapshot_key')->primary(); $table->integer('model_version'); $table->string('policy_hash');
            $table->string('as_of'); $table->string('built_at'); $table->string('source_fingerprint');
            $table->string('publication_fingerprint'); $table->text('publication_manifest'); $table->text('payload');
        });
        $store = app(PlayerAbilitySnapshotStore::class);
        DB::table('player_ability_snapshots')->insert(['snapshot_key' => 'current', 'model_version' => PlayerSharedAbilityService::VERSION,
            'policy_hash' => $store->policyHash(), 'as_of' => '2026-10-29', 'built_at' => '2026-10-29 00:01:03',
            'source_fingerprint' => 'source', 'publication_fingerprint' => 'publication', 'publication_manifest' => '[]',
            'payload' => json_encode($this->snapshot(), JSON_THROW_ON_ERROR)]);
        $before = DB::table('player_ability_snapshots')->first();
        Bus::fake(); Queue::fake(); Mail::fake();
        DB::statement('PRAGMA query_only = ON');
        try {
            $data = (new PlayerMembershipPreviewService($store))->build();
            $this->assertCount(11, $data['rows']);
            $this->assertEquals($before, DB::table('player_ability_snapshots')->first());
            Bus::assertNothingDispatched(); Queue::assertNothingPushed(); Mail::assertNothingSent(); Mail::assertNothingQueued();
        } finally { DB::statement('PRAGMA query_only = OFF'); }
    }

    public function test_controller_denies_nonlocal_and_non_super_user_before_reading_snapshot(): void
    {
        $service = Mockery::mock(PlayerMembershipPreviewService::class);
        $service->shouldNotReceive('build');
        $controller = new PlayerMembershipPreviewController;
        $ordinary = Mockery::mock(User::class)->makePartial();
        $ordinary->shouldReceive('hasRole')->with('super-user')->andReturnFalse();
        foreach (['directory', 'leaderboard', 'membership'] as $page) {
            $this->app['env'] = 'production';
            try { $controller->{$page}(Request::create('/preview'), $service); $this->fail('Expected nonlocal denial'); }
            catch (HttpException $e) { $this->assertSame(404, $e->getStatusCode()); }
            $this->app['env'] = 'local';
            foreach ([null, $ordinary] as $actor) {
                $request = Request::create('/preview'); $request->setUserResolver(fn () => $actor);
                try { $controller->{$page}($request, $service); $this->fail('Expected role denial'); }
                catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
            }
        }
    }

    public function test_active_preview_separates_cohorts_and_disconnected_groups_with_private_positions(): void
    {
        $active = [];
        foreach ([['u11 boys', 'U11 boys', 'group-a'], ['u11 boys', 'U11 boys', 'group-b'], ['u12 girls', 'U12 girls', 'group-a']] as $i => [$cohort, $label, $component]) {
            $active[] = ['id' => 100 + $i, 'cohort' => $cohort, 'cohort_label' => $label, 'component' => $component, 'name' => 'Example '.$i,
                'score' => 55.0, 'played' => 8, 'events' => 2, 'opponents' => 6, 'position' => 1];
        }
        $html = view('backend.player-performance.membership-preview.active-groups', ['activeRows' => $active, 'as_of' => '2026-10-29'])->render();
        $this->assertSame(3, substr_count($html, '<section'));
        $this->assertSame(2, substr_count($html, '<h2>U11 boys</h2>'));
        $this->assertSame(1, substr_count($html, '<h2>U12 girls</h2>'));
        $this->assertStringContainsString('Comparison group group-a', $html);
        $this->assertStringContainsString('Comparison group group-b', $html);
        $this->assertSame(3, substr_count($html, 'Position #1'));
        $this->assertStringNotContainsString('<ol', strtolower($html));
    }

    public function test_unrated_and_inactive_profiles_are_distinct_and_unrated_scores_are_withheld(): void
    {
        $snapshot = $this->snapshot();
        unset($snapshot['cohorts']['u11 boys']['players'][4535]['confidence_matches']);
        $snapshot['cohorts']['u11 boys']['players'][4535]['played'] = 0;
        foreach ($snapshot['cohorts']['u11 boys']['players'][4536]['confidence_matches'] as &$match) { $match['date'] = '2024-01-01'; }
        unset($match);
        $data = $this->service($snapshot)->build();
        $unrated = array_values(array_filter($data['rows'], fn ($r) => $r['id'] === 4535))[0];
        $inactive = array_values(array_filter($data['rows'], fn ($r) => $r['id'] === 4536))[0];
        $this->assertSame('Unrated', $unrated['status']); $this->assertNull($unrated['score']); $this->assertNull($unrated['position']);
        $this->assertSame('Inactive', $inactive['status']); $this->assertSame(46.9, $inactive['score']); $this->assertNull($inactive['position']);
        $this->assertSame([1, 1, 9], [$data['unrated_count'], $data['inactive_count'], $data['provisional_count']]);
        $this->assertSame(11, $data['total_count']);
        unset($snapshot['cohorts']['u11 boys']['players'][4536]['confidence_matches']);
        $this->assertSame(1, $this->service($snapshot)->build()['unavailable_count']);
    }

    public function test_cap_withholds_all_names_and_partial_metadata_instead_of_showing_a_truncated_field(): void
    {
        $snapshot = $this->snapshot();
        $snapshot['cohorts']['u11 boys']['ratings'] = array_fill(1, 4001, []);
        $data = $this->service($snapshot)->build();
        $this->assertSame([], $data['rows']); $this->assertSame([], $data['active']); $this->assertSame([], $data['cohort_summary']);
        $this->assertSame(0, $data['total_count']); $this->assertNull($data['as_of']); $this->assertNotNull($data['reason']);
    }

    private function addActive(array &$snapshot, int $id, string $cohort, float $score, string $component = 'group'): void
    {
        $template = $this->snapshot();
        $node = $template['cohorts']['u11 boys']['players'][4535];
        foreach ([7, 8] as $opponent) { $node['confidence_matches'][] = ['date' => '2026-09-01', 'basis' => 'scheduled match date', 'event' => 233, 'opponent' => $opponent]; }
        $node['played'] = count($node['confidence_matches']);
        $snapshot['cohorts'][$cohort]['players'][$id] = $node;
        $snapshot['cohorts'][$cohort]['ratings'][$id] = ['score' => $score, 'strength' => 0.1, 'component' => $component];
        $snapshot['cohorts'][$cohort]['components'][$component] = $template['cohorts']['u11 boys']['components']['group'];
        $snapshot['cohorts'][$cohort]['baseline'] = ['anchors' => []];
        $snapshot['names'][$id] = 'Player '.$id;
    }

    public function test_positions_use_displayed_ties_and_complete_group_before_search(): void
    {
        $snapshot = $this->snapshot(); $snapshot['cohorts'] = [];
        $this->addActive($snapshot, 9001, 'u11 boys', 70.04);
        $this->addActive($snapshot, 9002, 'u11 boys', 70.01);
        $this->addActive($snapshot, 9003, 'u11 boys', 69.94);
        $this->addActive($snapshot, 9004, 'u11 boys', 95, 'other-group');
        $this->addActive($snapshot, 9005, 'u12 girls', 99);
        $all = $this->service($snapshot)->build();
        $positions = array_column($all['active'], 'position', 'id');
        $this->assertSame([9001 => 1, 9002 => 1, 9003 => 3, 9004 => 1, 9005 => 1], $positions);
        $filtered = $this->service($snapshot)->build('u11 boys', '9003');
        $this->assertCount(1, $filtered['active']); $this->assertSame(3, $filtered['active'][0]['position']);
        $this->assertSame(5, $filtered['active_count']);
        $this->assertSame(4, $filtered['cohort_summary']['u11 boys']['active']);
    }

    public function test_all_five_added_cohorts_support_directory_active_detail_and_comparison_scope(): void
    {
        $added = ['u10 boys', 'u10 girls', 'u11 girls', 'u13 boys', 'u13 girls'];
        $snapshot = $this->snapshot(); $snapshot['cohorts'] = []; $snapshot['snapshot_version'] = 'preview-version';
        foreach ($added as $i => $cohort) { $this->addActive($snapshot, 12000 + $i, $cohort, 60); }
        foreach (['u9 boys', 'u14 girls', 'green ball boys', 'u10 boys green ball'] as $i => $cohort) { $this->addActive($snapshot, 13000 + $i, $cohort, 65); }
        $all = $this->service($snapshot)->build();
        $this->assertCount(5, $all['rows']); $this->assertSame(5, $all['active_count']);
        $this->assertSame(['u10 boys', 'u10 girls', 'u11 boys', 'u11 girls', 'u12 boys', 'u12 girls', 'u13 boys', 'u13 girls'], array_keys($all['cohort_summary']));
        $this->app['env'] = 'local';
        $user = Mockery::mock(User::class)->makePartial(); $user->shouldReceive('hasRole')->with('super-user')->andReturnTrue();
        foreach ($added as $i => $cohort) {
            $id = 12000 + $i;
            $history = Mockery::mock(\App\Services\Performance\PlayerMembershipHistoryService::class);
            $history->shouldReceive('load')->once()->with($cohort, [$id], 'preview-version')->andReturn(['available' => false, 'reason' => 'fixture only', 'players' => []]);
            foreach (['detail', 'compare'] as $page) {
                $factory = Mockery::mock(\Illuminate\Contracts\Routing\ResponseFactory::class);
                $factory->shouldReceive('view')->once()->withArgs(function ($name, $data) use ($page, $cohort, $id) {
                    $this->assertSame('backend.player-performance.membership-preview.'.$page, $name);
                    $this->assertSame($cohort, $data['selected']);
                    $this->assertSame($id, $page === 'detail' ? $data['selectedPlayer']['id'] : $data['options'][0]['id']);
                    return true;
                })->andReturn(new \Illuminate\Http\Response('preview'));
                $this->app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class, $factory);
                $request = Request::create('/preview', 'GET', ['cohort' => $cohort]); $request->setUserResolver(fn () => $user);
                $controller = new PlayerMembershipPreviewController;
                if ($page === 'detail') { $controller->detail($request, (string) $id, $this->service($snapshot), $history); }
                else { $controller->compare($request, $this->service($snapshot), $history, new \App\Services\Performance\PlayerMembershipComparisonService); }
            }
        }
    }

    public function test_all_2011_saved_cohort_profiles_fit_bounded_preview_without_truncation(): void
    {
        $snapshot = $this->snapshot(); $template = $snapshot['cohorts']['u11 boys']; $snapshot['cohorts'] = [];
        $counts = ['u10 boys' => 238, 'u10 girls' => 206, 'u11 boys' => 282, 'u11 girls' => 249, 'u12 boys' => 282, 'u12 girls' => 248, 'u13 boys' => 271, 'u13 girls' => 235];
        foreach ($counts as $cohort => $count) {
            $snapshot['cohorts'][$cohort] = ['ratings' => [], 'players' => [], 'components' => $template['components'], 'baseline' => $template['baseline']];
            for ($i = 0; $i < $count; $i++) {
                $id = 50000 + $i; $snapshot['names'][$id] = 'Saved Player '.$id;
                $snapshot['cohorts'][$cohort]['ratings'][$id] = $template['ratings'][4535];
                $snapshot['cohorts'][$cohort]['players'][$id] = $template['players'][4535];
            }
        }
        $data = $this->service($snapshot)->build();
        $this->assertNull($data['reason']); $this->assertSame(2011, $data['total_count']);
        $this->assertCount(2011, $data['rows']); $this->assertSame(0, $data['unavailable_count']);
        foreach ($counts as $cohort => $count) { $this->assertSame($count, $data['cohort_summary'][$cohort]['saved']); }
    }

    public function test_missing_negative_fractional_or_inconsistent_saved_played_counts_are_withheld(): void
    {
        foreach ([null, -1, 6.5, '6.5', 7] as $played) {
            $snapshot = $this->snapshot(); $snapshot['cohorts']['u11 boys']['players'][4535]['played'] = $played;
            $data = $this->service($snapshot)->build();
            $this->assertSame(1, $data['unavailable_count']);
            $this->assertNotContains(4535, array_column($data['rows'], 'id'));
        }
    }

    public function test_controller_paginates_both_directories_and_preserves_only_validated_filters_without_renumbering(): void
    {
        $snapshot = $this->snapshot(); $snapshot['cohorts'] = [];
        for ($i = 0; $i < 60; $i++) { $this->addActive($snapshot, 11000 + $i, 'u11 boys', 90 - $i); }
        Schema::create('players', function (Blueprint $t) { $t->integer('id')->primary(); $t->string('profile_updated_at')->nullable(); });
        for ($i = 0; $i < 60; $i++) { DB::table('players')->insert(['id' => 11000 + $i, 'profile_updated_at' => '2026-10-10 10:00:00']); }
        DB::enableQueryLog();
        $this->app['env'] = 'local';
        $user = Mockery::mock(User::class)->makePartial(); $user->shouldReceive('hasRole')->with('super-user')->andReturnTrue();
        $factory = Mockery::mock(\Illuminate\Contracts\Routing\ResponseFactory::class);
        $factory->shouldReceive('view')->once()->withArgs(function ($name, $data) {
            foreach (['players', 'activePlayers'] as $key) {
                $this->assertCount(25, $data[$key]->items()); $this->assertSame(60, $data[$key]->total()); $this->assertSame(2, $data[$key]->currentPage());
                parse_str(parse_url($data[$key]->url(3), PHP_URL_QUERY), $query);
                $this->assertSame(['cohort' => 'u11 boys', 'search' => 'Player', 'page' => '3'], $query);
            }
            $this->assertSame(26, $data['activePlayers']->items()[0]['position']);
            $this->assertSame(50, $data['activePlayers']->items()[24]['position']);
            $this->assertSame('Profile updated within 6 months', $data['activePlayers']->items()[0]['profile_freshness']);
            return true;
        })->andReturn(new \Illuminate\Http\Response('preview'));
        $this->app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class, $factory);
        $request = Request::create('/preview', 'GET', ['cohort' => 'u11 boys', 'search' => 'Player', 'page' => 2, 'unapproved' => 'discard']);
        $request->setUserResolver(fn () => $user);
        (new PlayerMembershipPreviewController)->leaderboard($request, $this->service($snapshot));
        $queries = array_values(array_filter(DB::getQueryLog(), fn ($q) => str_contains($q['query'], 'from "players"')));
        $this->assertCount(1, $queries); $this->assertSame(range(11025, 11049), $queries[0]['bindings']);
    }

    public function test_controller_returns_three_private_view_responses_and_rejects_invalid_filters(): void
    {
        $this->app['env'] = 'local';
        view()->share('errors', new \Illuminate\Support\ViewErrorBag);
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-user')->andReturnTrue();
        foreach (['directory', 'leaderboard', 'membership'] as $page) {
            $factory = Mockery::mock(\Illuminate\Contracts\Routing\ResponseFactory::class);
            $factory->shouldReceive('view')->once()->withArgs(function ($name, $data) use ($page) {
                $this->assertSame('backend.player-performance.membership-preview.'.$page, $name);
                $this->assertSame(11, $data['provisional_count']);
                return true;
            })->andReturn(new \Illuminate\Http\Response('preview'));
            $this->app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class, $factory);
            $request = Request::create('/preview', 'GET', ['cohort' => 'u11 boys']);
            $request->setUserResolver(fn () => $user);
            $response = (new PlayerMembershipPreviewController)->{$page}($request, $this->service($this->snapshot()));
            $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
            $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
            $this->assertSame('preview', $response->getContent());
        }
        $request = Request::create('/preview', 'GET', ['cohort' => 'other group']);
        $request->setUserResolver(fn () => $user);
        $service = Mockery::mock(PlayerMembershipPreviewService::class);
        $service->shouldNotReceive('build');
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        (new PlayerMembershipPreviewController)->directory($request, $service);
    }
}
