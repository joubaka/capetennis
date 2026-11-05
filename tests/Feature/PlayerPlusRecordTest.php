<?php

namespace Tests\Feature;

use App\Models\{Player, User};
use App\Services\Performance\{PlayerAbilitySnapshotStore, PlayerPlusRecordService, PlayerRatingAccess, PlayerRatingHistoryService, PlayerRatingHistoryStore, PlayerSharedAbilityService};
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class PlayerPlusRecordTest extends TestCase
{
    private function player(): Player
    {
        $player = new Player; $player->setRawAttributes(['id' => 42, 'name' => 'Example', 'surname' => 'Player']); return $player;
    }

    private function row(string $id = 'match:1', array $extra = []): array
    {
        return $extra + ['source_id' => $id, 'kind' => 'match', 'months' => 1, 'cohort' => 'overall', 'event_id' => 1,
            'event' => 'Example Open', 'date' => '2026-02-01', 'date_basis' => 'event start proxy', 'players' => [42, 43],
            'names' => ['Example Player', 'Example Opponent'], 'winner_side' => 1, 'score' => '6-3 6-4'];
    }

    private function record(array $rows, array $filters = [], bool $super = false): array
    {
        $history = Mockery::mock(PlayerRatingHistoryService::class);
        $history->shouldReceive('recordSource')->once()->andReturn(['reason' => null, 'as_of' => '2026-11-03', 'built_at' => '2026-11-03', 'evidence' => $rows]);
        return (new PlayerPlusRecordService($history))->forPlayer($this->player(), $filters, $super);
    }

    public function test_counts_are_deduplicated_scoped_and_exclude_finishes_future_and_restricted_sources(): void
    {
        $rows = [$this->row(), $this->row('match:1', ['cohort' => 'ball']),
            $this->row('match:2', ['players' => [43, 42], 'names' => ['Example Opponent', 'Example Player'], 'date' => '2026-03-01']),
            $this->row('match:3', ['players' => [44, 45]]), $this->row('finish:1', ['kind' => 'finish']),
            $this->row('match:4', ['date' => '2027-01-01']), $this->row('match:5', ['months' => 0]),
            $this->row('teamsheet:ts-challenge-1', ['event_id' => -1, 'event' => 'Restricted event'])];
        $record = $this->record($rows, ['opponent' => 43]);
        $this->assertSame(2, $record['played']); $this->assertSame(1, $record['wins']);
        $this->assertSame(1, $record['opponent_count']); $this->assertSame(2, $record['meetings']['total']);
        $this->assertSame('3-6 4-6', $record['results']['rows'][0]['score']);
        $this->assertStringNotContainsString('Restricted event', json_encode($record));
        $this->assertSame(3, $this->record($rows, [], true)['played']);
    }

    public function test_conflicting_duplicates_are_withheld_and_year_and_opponent_filters_cannot_expand_scope(): void
    {
        $record = $this->record([$this->row(), $this->row('match:1', ['winner_side' => 2]),
            $this->row('match:2', ['date' => '2025-01-01']), $this->row('match:3')], ['year' => 2025, 'opponent' => 999]);
        $this->assertSame(1, $record['played']); $this->assertSame('2025-01-01', $record['first']['date']);
        $this->assertNull($record['opponent']); $this->assertSame(0, $record['meetings']['total']);
        $this->assertCount(2, $record['seasons']);
    }

    public function test_results_and_opponents_are_paginated_and_unknown_year_is_empty(): void
    {
        $rows = []; for ($i = 1; $i <= 45; $i++) $rows[] = $this->row('match:'.$i, ['players' => [42, 100 + $i]]);
        $record = $this->record($rows, ['page' => 3, 'opponent_page' => 2]);
        $this->assertCount(5, $record['results']['rows']); $this->assertCount(20, $record['opponents']['rows']);
        $this->assertSame(45, $record['played']); $this->assertCount(5, $record['form']);
        $this->assertSame(0, $this->record($rows, ['year' => 2000])['played']);
    }

    public function test_season_comparison_uses_two_distinct_years_independent_of_match_register_scope(): void
    {
        $rows = [$this->row('match:1', ['date' => '2025-01-01']),
            $this->row('match:2', ['date' => '2025-02-01', 'winner_side' => 2]),
            $this->row('match:3', ['date' => '2026-01-01', 'event_id' => 2]),
            $this->row('match:4', ['date' => '2026-02-01', 'players' => [42, 44], 'event_id' => 3])];
        $record = $this->record($rows, ['year' => 2025]); $comparison = $record['comparison'];
        $this->assertSame('2025', $comparison['first_year']); $this->assertSame('2026', $comparison['second_year']);
        $this->assertSame(2, $record['played']); $this->assertSame(100.0, $comparison['second']['rate']);
        $this->assertEquals(50, $comparison['delta']['rate']); $this->assertEquals(1, $comparison['delta']['events']);
        $this->assertEquals(1, $comparison['delta']['opponents']); $this->assertEquals(-1, $comparison['delta']['losses']);
        $this->assertSame('2025-01-01', $comparison['first']['from']);
        $this->assertSame('2026-02-01', $comparison['second']['through']);
    }

    public function test_percentage_point_delta_rounds_once_from_actual_outcomes(): void
    {
        $rows = [];
        for ($i = 0; $i < 3; $i++) $rows[] = $this->row('match:old'.$i, ['date' => '2025-01-01', 'winner_side' => $i === 0 ? 1 : 2]);
        for ($i = 0; $i < 7; $i++) $rows[] = $this->row('match:new'.$i, ['winner_side' => $i < 2 ? 1 : 2]);
        $record = $this->record($rows);
        $this->assertSame(-4.8, $record['comparison']['delta']['rate']);
        $this->assertSame(33.3, $record['comparison']['first']['rate']);
        $this->assertSame(28.6, $record['comparison']['second']['rate']);
    }

    public function test_missing_identical_and_empty_comparison_seasons_have_no_delta(): void
    {
        foreach ([[], ['compare_first' => 2026, 'compare_second' => 2026], ['compare_first' => 2025, 'compare_second' => 2026]] as $filters) {
            $comparison = $this->record([$this->row()], $filters)['comparison'];
            $this->assertNotNull($comparison['reason']); $this->assertNull($comparison['delta']);
        }
        $comparison = $this->record([])['comparison'];
        $this->assertNull($comparison['first']['rate']); $this->assertNull($comparison['delta']);
        $comparison = $this->record([$this->row(), $this->row('match:old', ['date' => '2025-01-01'])], ['compare_first' => null, 'compare_second' => 2026])['comparison'];
        $this->assertSame('', $comparison['first_year']); $this->assertNull($comparison['delta']);
    }

    public function test_opponent_trend_and_recent_meetings_respect_year_player_and_source_scope(): void
    {
        $rows = [$this->row('match:1', ['date' => '2025-01-01']), $this->row('match:2'),
            $this->row('match:3', ['winner_side' => 2]), $this->row('match:4', ['players' => [42, 44]]),
            $this->row('teamsheet:ts-challenge-1'), $this->row('match:5', ['players' => [45, 43]])];
        $record = $this->record($rows, ['opponent' => 43]);
        $this->assertCount(2, $record['opponent_trend']); $this->assertCount(3, $record['opponent_recent']);
        $this->assertSame(['2025', '2026'], array_column($record['opponent_trend'], 'year'));
        $this->assertSame(50.0, $record['opponent_trend'][1]['rate']);
        $filtered = $this->record($rows, ['year' => 2025, 'opponent' => 43]);
        $this->assertCount(1, $filtered['opponent_trend']); $this->assertCount(1, $filtered['opponent_recent']);
        $this->assertSame(1, $filtered['meetings']['total']);
    }

    public function test_controller_denies_access_before_reading_any_results(): void
    {
        $access = Mockery::mock(PlayerRatingAccess::class); $access->shouldReceive('allows')->once()->andReturn(false);
        $this->app->instance(PlayerRatingAccess::class, $access);
        $record = Mockery::mock(PlayerPlusRecordService::class); $record->shouldNotReceive('forPlayer');
        $this->app->instance(PlayerPlusRecordService::class, $record);
        $this->mock(\App\Services\Performance\PlayerUpcomingMatchInsights::class)->shouldNotReceive('forPlayer');
        $request = Request::create('/'); $request->setUserResolver(fn () => new User);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Http\Controllers\Frontend\MyTennisController::class)->playingRecord($request, $this->player());
    }

    public function test_canonical_access_requires_both_player_relationship_and_entitlement(): void
    {
        $user = Mockery::mock(User::class)->makePartial(); $user->id = 7;
        $user->shouldReceive('hasRole')->with('super-user')->andReturn(false);
        $user->shouldReceive('checkPermissionTo')->andReturn(false);
        $user->shouldReceive('players->whereKey->exists')->andReturn(false);
        $player = $this->player(); $player->userId = 7;
        \Illuminate\Support\Facades\Gate::define('player-premium-rating', fn () => false);
        $this->assertFalse((new PlayerRatingAccess)->allows($user, $player));
        \Illuminate\Support\Facades\Gate::define('player-premium-rating', fn () => true);
        $this->assertTrue((new PlayerRatingAccess)->allows($user, $player));
        $player->userId = 8;
        $this->assertFalse((new PlayerRatingAccess)->allows($user, $player));
    }

    public function test_authorized_controller_response_is_private_and_uses_only_selected_player(): void
    {
        $player = $this->player(); $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-user')->once()->andReturn(false);
        $access = Mockery::mock(PlayerRatingAccess::class); $access->shouldReceive('allows')->once()->with($user, $player)->andReturn(true);
        $this->app->instance(PlayerRatingAccess::class, $access);
        $service = Mockery::mock(PlayerPlusRecordService::class);
        $service->shouldReceive('forPlayer')->once()->with($player, ['year' => 2026], false)->andReturn($this->record([$this->row()]));
        $this->app->instance(PlayerPlusRecordService::class, $service);
        $this->mock(\App\Services\PlayerWorkspaceContext::class)->shouldReceive('forPlayer')->once()->with($user, $player)->andReturn([]);
        $path = storage_path('framework/testing/record-layout-'.bin2hex(random_bytes(5)));
        \Illuminate\Support\Facades\File::ensureDirectoryExists($path.'/layouts');
        file_put_contents($path.'/layouts/contentNavbarLayout.blade.php', '@yield("content")');
        app('view')->getFinder()->prependLocation($path);
        $request = Request::create('/my-tennis/players/42/record', 'GET', ['year' => 2026]); $request->setUserResolver(fn () => $user);
        try {
            $response = app(\App\Http\Controllers\Frontend\MyTennisController::class)->playingRecord($request, $player);
            $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
            $this->assertStringContainsString('Results', $response->getContent());
        } finally { \Illuminate\Support\Facades\File::deleteDirectory($path); }
    }

    public function test_stale_teamsheet_context_is_withheld_before_any_record_projection(): void
    {
        $ability = Mockery::mock(PlayerSharedAbilityService::class); $ability->shouldNotReceive('fingerprint');
        $store = Mockery::mock(PlayerRatingHistoryStore::class);
        $store->shouldReceive('read')->once()->andReturn(['publication_manifest' => ['teamsheet_context' => ['version' => 'old']]]);
        $teamsheet = Mockery::mock(\App\Services\Performance\TeamSheetTennisEvidence::class);
        $teamsheet->shouldReceive('published')->once()->andReturn(false);
        $this->app->instance(\App\Services\Performance\TeamSheetTennisEvidence::class, $teamsheet);
        $this->assertArrayNotHasKey('evidence', (new PlayerRatingHistoryService($ability, $store))->recordSource());
    }

    public function test_changed_publication_withholds_saved_evidence_and_missing_build_is_safe(): void
    {
        $ability = Mockery::mock(PlayerSharedAbilityService::class); $ability->shouldReceive('fingerprint')->once()->andReturn('changed');
        $store = Mockery::mock(PlayerRatingHistoryStore::class); $store->shouldReceive('read')->once()->andReturn(['source_fingerprint' => 'old', 'publication_manifest' => ['events' => []]]);
        $snapshots = Mockery::mock(PlayerAbilitySnapshotStore::class); $snapshots->shouldReceive('published')->once()->andReturn(false);
        $this->app->instance(PlayerAbilitySnapshotStore::class, $snapshots);
        $this->assertArrayNotHasKey('evidence', (new PlayerRatingHistoryService($ability, $store))->recordSource());
        $store = Mockery::mock(PlayerRatingHistoryStore::class); $store->shouldReceive('read')->once()->andReturn(null);
        $result = (new PlayerPlusRecordService(new PlayerRatingHistoryService($ability, $store)))->forPlayer($this->player());
        $this->assertNotNull($result['reason']); $this->assertSame(0, $result['played']);
    }

    public function test_blade_sections_render_escaped_results_and_do_not_link_private_opponent_profiles(): void
    {
        // Render content against an isolated layout: no shared navbar/database side effects.
        $compiler = app('blade.compiler');
        $template = file_get_contents(resource_path('views/frontend/my-tennis/playing-record.blade.php'));
        $template = preg_replace('/@extends\([^\n]+\)\r?\n/', '', $template);
        $record = $this->record([$this->row('match:1', ['names' => ['Example Player', '<script>unsafe</script>']])], ['opponent' => 43]);
        foreach (['record', 'opponents'] as $section) {
            $html = \Illuminate\Support\Facades\Blade::render($template.'@yield("content")', ['recordPlayer' => $this->player(), 'recordSection' => $section, 'record' => $record]);
            $this->assertStringContainsString('&lt;script&gt;unsafe&lt;/script&gt;', $html);
            $this->assertStringNotContainsString('<script>unsafe</script>', $html);
            $this->assertStringContainsString('Estimated date', $html);
            $this->assertStringContainsString('Some matches may be missing', $html);
            $this->assertStringContainsString('name="opponent" value="43"', $html);
            if ($section === 'opponents') {
                $this->assertStringContainsString('Across seasons', $html);
                $this->assertStringContainsString('Estimated match dates use the event start date.', $html);
            } else {
                $this->assertStringContainsString('Compare seasons', $html);
                $this->assertLessThan(strpos($html, 'id="season-compare-heading"'), strpos($html, 'class="record-row"'));
                $this->assertStringContainsString('Win-rate change is shown in percentage points (pp).', $html);
            }
        }
        foreach (['playing-record', 'record-matches', 'record-pagination', 'record-comparison', 'record-opponent-trend', 'index'] as $view) {
            $this->assertNotEmpty(token_get_all($compiler->compileString(file_get_contents(resource_path('views/frontend/my-tennis/'.$view.'.blade.php'))), TOKEN_PARSE));
        }
    }

    public function test_selected_matchup_is_before_the_list_with_one_match_register_and_anchored_navigation(): void
    {
        $rows = [];
        for ($i = 1; $i <= 25; $i++) $rows[] = $this->row('match:'.$i);
        $this->app->instance('request', Request::create('/my-tennis/players/42/record/opponents', 'GET', [
            'year' => 2026, 'opponent' => 43, 'opponent_page' => 2, 'compare_first' => 2025, 'compare_second' => 2026]));
        $record = $this->record($rows, ['year' => 2026, 'opponent' => 43]);
        $template = preg_replace('/@extends\([^\n]+\)\r?\n/', '', file_get_contents(resource_path('views/frontend/my-tennis/playing-record.blade.php')));
        $html = \Illuminate\Support\Facades\Blade::render($template.'@yield("content")', ['recordPlayer' => $this->player(), 'recordSection' => 'opponents', 'record' => $record]);
        $this->assertStringContainsString('Example Player vs Example Opponent', $html);
        $this->assertLessThan(strpos($html, 'class="record-opponent text-decoration-none"'), strpos($html, 'id="opponent-meetings"'));
        $this->assertStringNotContainsString('Recent meetings in this selection', $html);
        $this->assertSame(20, substr_count($html, 'class="record-row"'));
        $this->assertStringContainsString('aria-label="View matchup: Example Player vs Example Opponent"', $html);
        $this->assertStringContainsString('meeting_page=2#opponent-meetings', $html);
        $this->assertStringContainsString('opponent_page=2', $html);
        $this->assertStringContainsString('compare_first=2025', $html);
        $this->assertStringContainsString('year=2026', $html);
    }

    public function test_upcoming_matches_precede_unavailable_history_and_names_open_comparison_details(): void
    {
        $record = $this->record([]); $record['reason'] = 'History unavailable';
        $upcoming = ['rows' => [['date' => '2026-11-05 09:00:00', 'event' => 'Upcoming Open', 'draw' => 'Singles',
            'venue' => 'Example courts', 'court' => '2', 'team' => null,
            'opponents' => [['id' => 43, 'name' => '<script>Opponent</script>']], 'url' => '/published-draw']],
            'ratings' => [42 => ['score' => 80.0, 'confidence' => 'Low'], 43 => ['score' => 75.0, 'confidence' => 'Moderate']],
            'total' => 1, 'page' => 1, 'pages' => 1];
        $template = preg_replace('/@extends\([^\n]+\)\r?\n/', '', file_get_contents(resource_path('views/frontend/my-tennis/playing-record.blade.php')));
        $html = \Illuminate\Support\Facades\Blade::render($template.'@yield("content")', ['recordPlayer' => $this->player(), 'recordSection' => 'opponents', 'record' => $record, 'upcoming' => $upcoming]);
        $this->assertLessThan(strpos($html, 'History unavailable'), strpos($html, 'id="upcoming-heading"'));
        $this->assertStringContainsString('<details', $html);
        $this->assertStringContainsString('Overall CTR 80.0', $html);
        $this->assertStringContainsString('Overall CTR 75.0', $html);
        $this->assertStringContainsString('Moderate confidence', $html);
        $this->assertStringContainsString('Court 2', $html);
        $this->assertStringContainsString('&lt;script&gt;Opponent&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>Opponent</script>', $html);
        $this->assertStringContainsString('opponent=43#opponent-meetings', $html);
    }
}
