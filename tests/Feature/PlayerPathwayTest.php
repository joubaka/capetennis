<?php

namespace Tests\Feature;

use App\Models\{Player, PlayerPathwayAchievement, PlayerPathwayProfile, SiteSetting, User};
use App\Services\Performance\{PlayerPathwayService, PlayerRatingAccess, PlayerRatingHistoryService};
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Gate, Schema};
use Mockery;
use Tests\TestCase;

class PlayerPathwayTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        Schema::create('players', function (Blueprint $table) {
            $table->id(); $table->unsignedBigInteger('userId')->nullable(); $table->string('name'); $table->string('surname');
        });
        Schema::create('user_players', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id'); $table->unsignedBigInteger('player_id');
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
        });
        Schema::create('site_settings', function (Blueprint $table) {
            $table->id(); $table->string('key'); $table->string('value'); $table->string('group')->nullable(); $table->string('label')->nullable(); $table->timestamps();
        });
        (require database_path('migrations/2026_11_01_160000_create_player_pathway_tables.php'))->up();
        DB::table('players')->insert([
            ['id' => 1, 'userId' => 10, 'name' => 'Fictional', 'surname' => 'Player'],
            ['id' => 2, 'userId' => 20, 'name' => 'Other', 'surname' => 'Player'],
        ]);
        // Other operational middleware owns unrelated checkout/audit state and is covered separately.
        $this->withoutMiddleware([
            \App\Http\Middleware\ExpireUnpaidEventCheckout::class,
            \App\Http\Middleware\RecordAuditRequest::class,
            \App\Http\Middleware\AuditJtaIntegrationAccess::class,
        ]);
        $this->mock(PlayerRatingHistoryService::class)->shouldReceive('forPlayer')->andReturn([
            'reason' => 'No saved history yet.', 'series' => [], 'points' => [],
        ]);
        $this->app->instance(\App\Services\Performance\PendingBankRefundCount::class, new class {
            public function count(): int { return 0; }
        });
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function actor(int $id = 99, bool $admin = true): User
    {
        $actor = Mockery::mock(User::class)->makePartial();
        $actor->id = $id;
        $actor->shouldReceive('hasRole')->with('super-user')->andReturn($admin);
        $actor->shouldReceive('hasAnyRole')->andReturn($admin);
        $actor->shouldReceive('checkPermissionTo')->andReturn(false);
        $actor->shouldReceive('getForeignKey')->andReturn('user_id');
        return $actor;
    }

    private function metrics(float $score = 60, array $stages = ['red' => 3, 'orange' => 0, 'standard' => 0]): array
    {
        return ['player_id' => 1, 'available' => true, 'score' => $score, 'events' => max($stages),
            'stage_ratings' => array_map(fn ($count) => ['score' => $count > 0 ? $score : null, 'comparable' => true, 'local' => false], $stages),
            'stage_events' => $stages, 'unknown_stage_events' => 0, 'date' => '2026-11-01',
            'comparable' => true, 'stale' => false, 'reason' => null];
    }

    public function test_initial_targets_have_exact_boundaries_and_require_three_own_stage_events(): void
    {
        $service = app(PlayerPathwayService::class); $player = Player::findOrFail(1); $actor = $this->actor();
        foreach ([['red', null, 50], ['orange', null, 60]] as [$stage, $division, $target]) {
            $service->recordStage($player, $actor, ['stage' => $stage, 'wilson_division' => $division]);
            $counts = ['red' => 0, 'orange' => 0, 'standard' => 0]; $counts[$stage] = 3;
            $summary = $service->summary($player, ['pathway_metrics' => $this->metrics($target, $counts)]);
            $this->assertTrue($summary['ready']);
            $this->assertSame((float) $target, $summary['target']['ctr']);
            $this->assertFalse($service->summary($player, ['pathway_metrics' => $this->metrics($target - .1, $counts)])['ready']);
            $counts[$stage] = 2;
            $this->assertFalse($service->summary($player, ['pathway_metrics' => $this->metrics($target, $counts)])['ready']);
        }
        $this->assertDatabaseCount('player_pathway_achievements', 0);
        $this->assertDatabaseHas('player_pathway_profiles', ['player_id' => 1, 'stage' => 'orange', 'wilson_division' => null]);
    }

    public function test_unrecorded_stage_automatically_assesses_latest_green_yellow_badge(): void
    {
        $metrics = $this->metrics(67.9, ['red' => 0, 'orange' => 0, 'standard' => 6]);
        $metrics['stage_ratings']['red']['score'] = null;
        $metrics['stage_ratings']['orange']['score'] = null;
        $history = ['reason' => null, 'pathway_metrics' => $metrics, 'series' => [], 'points' => []];
        $summary = app(PlayerPathwayService::class)->summary(Player::findOrFail(1), $history);
        $this->assertSame(['stage' => 'standard', 'score' => 67.9, 'local' => false], $summary['badge']);
        $this->assertNull($summary['stage']);
        $this->assertSame(67.9, $summary['score']);
        $this->assertSame('standard', $summary['current_stage']);
        $this->assertSame(6, $summary['ownEvents']);
        $this->assertSame('Wilson A suggested', $summary['assessment_status']);
        $this->assertNull($summary['target']);
        $this->assertFalse($summary['ready']);
        $this->assertFalse($summary['ratingMet']);
        $this->assertTrue($summary['eventsMet']);
        foreach ($summary['stageProgress'] as $progress) $this->assertFalse($progress['met']);

        $this->mock(PlayerRatingHistoryService::class)->shouldReceive('forPlayer')->andReturn($history);
        $this->actingAs($this->actor());
        $response = $this->get(route('my.tennis.pathway', 1))->assertOk()->assertSee('Ball level: Green/yellow ball');
        $this->assertMatchesRegularExpression('/data-pathway-top-badge>.*?aria-label="CTR 67\\.9 · Green\/yellow ball"/s', $response->getContent());
        $response->assertDontSee('a starting stage must be recorded')->assertSee('Wilson A suggested')->assertDontSee('Not completed')->assertDontSee('Current suitable competition');
        $response->assertSee('Suggested next step')->assertSee('All levels and targets')
            ->assertSee('data-pathway-snapshot', false)->assertSee('You are here')->assertSee('data-pathway-competition="Wilson A"', false);
        if (getenv('CT_PATHWAY_QA_HTML') === '1') {
            file_put_contents(storage_path('framework/testing/pathway-auto-qa.html'), $response->getContent());
        }
        $this->assertDatabaseCount('player_pathway_profiles', 0);
        $this->assertDatabaseCount('player_pathway_achievements', 0);
    }

    public function test_stage_details_load_on_demand_with_stage_specific_guidance_and_no_writes(): void
    {
        $this->actingAs($this->actor());
        $history = ['reason' => null, 'pathway_metrics' => $this->metrics(40), 'series' => [], 'points' => []];
        $this->mock(PlayerRatingHistoryService::class)->shouldReceive('forPlayer')->andReturn($history);
        $overview = $this->get(route('my.tennis.pathway', 1))->assertOk()->assertSee('More info')->assertSee('through 75')->assertDontSee('through 85')->assertDontSee('data-pathway-stage-details=', false);
        $this->assertSame(5, substr_count($overview->getContent(), 'data-pathway-info="'));
        foreach (['red', 'orange', 'wilson-b', 'wilson-a', 'tsa'] as $stage) {
            $response = $this->get(route('my.tennis.pathway.info', ['player' => 1, 'stage' => $stage]))->assertOk()
                ->assertSee('data-pathway-stage-details="'.$stage.'"', false)->assertSee('This is a guide to your next step.')
                ->assertSee('Each event or team still has its own entry and selection process.');
            $this->assertTrue($response->headers->hasCacheControlDirective('private'));
            $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
            if (in_array($stage, ['red', 'orange'], true)) {
                foreach (PlayerPathwayService::EVENT_OPTIONS[$stage] as $option) $response->assertSee($option);
                $response->assertSee($stage === 'red' ? '50 CTR and 3 published Red ball events' : '60 CTR and 3 published Orange ball events');
            } elseif ($stage === 'tsa') {
                $response->assertSee('https://www.satennis.co.za/', false)->assertSee('A CTR of exactly 75');
            } else {
                $response->assertSee('There is no event-count target for choosing between them.')->assertDontSee('Red ball · Fictional');
            }
        }
        $this->assertDatabaseCount('player_pathway_profiles', 0);
        $this->assertDatabaseCount('player_pathway_achievements', 0);
    }

    public function test_stage_details_require_private_rating_access_and_a_known_stage(): void
    {
        $url = fn ($player, $stage) => route('my.tennis.pathway.info', compact('player', 'stage'));
        $this->getJson($url(1, 'red'))->assertUnauthorized();
        SiteSetting::set(PlayerRatingAccess::SETTING, '1');
        Gate::define('player-premium-rating', fn () => true);
        $this->actingAs($this->actor(10, false));
        $this->get($url(2, 'red'))->assertForbidden();
        Gate::define('player-premium-rating', fn () => false);
        $this->get($url(1, 'red'))->assertForbidden();
        $this->actingAs($this->actor());
        $this->get($url(1, 'not-a-stage'))->assertNotFound();
        $this->get($url(1, 'red'))->assertOk()->assertSee('Rating unavailable');
        $this->assertDatabaseCount('player_pathway_profiles', 0);
        $this->assertDatabaseCount('player_pathway_achievements', 0);
    }
    public function test_wilson_suggestion_requires_current_comparable_standard_evidence(): void
    {
        $service = app(PlayerPathwayService::class);
        $player = Player::findOrFail(1);
        foreach (['stale', 'local', 'not_comparable', 'unavailable', 'orange_only'] as $state) {
            $metrics = $this->metrics(60, ['red' => 0, 'orange' => 0, 'standard' => 1]);
            if ($state === 'stale') $metrics['stale'] = true;
            if ($state === 'local') $metrics['stage_ratings']['standard']['local'] = true;
            if ($state === 'not_comparable') $metrics['stage_ratings']['standard']['comparable'] = false;
            if ($state === 'unavailable') $metrics['available'] = false;
            if ($state === 'orange_only') $metrics = $this->metrics(80, ['red' => 0, 'orange' => 3, 'standard' => 0]);
            $summary = $service->summary($player, ['pathway_metrics' => $metrics]);
            $this->assertNull($summary['suggestedDivision'], $state);
        }
    }

    public function test_competition_suggestions_use_strict_ctr_boundaries_without_an_event_count_gate(): void
    {
        $service = app(PlayerPathwayService::class);
        $player = Player::findOrFail(1);
        foreach ([49.9, 50.0, 50.1, 75.0, 75.1] as $score) {
            foreach ([1, 3] as $count) {
                $metrics = $this->metrics($score, ['red' => 0, 'orange' => 0, 'standard' => $count]);
                $summary = $service->summary($player, ['pathway_metrics' => $metrics]);
                $this->assertSame($score > 50 ? 'A' : 'B', $summary['suggestedDivision']);
                $this->assertSame($score > 75 ? 'TSA tournaments' : ($score > 50 ? 'Wilson A' : 'Wilson B'), $summary['suggestedCompetition']);
                $this->assertNull($summary['target']);
                $this->assertNull($summary['benchmark_division']);
                $this->assertSame('standard', $summary['current_stage']);
                $this->assertNull($summary['division']);
                $this->assertTrue($summary['assessed']);
                $this->assertFalse($summary['ready']);
            }
        }
        $before = $service->summary($player, ['pathway_metrics' => $metrics]);
        $service->recordStage($player, $this->actor(), ['stage' => 'red', 'wilson_division' => 'B']);
        $after = $service->summary($player, ['pathway_metrics' => $metrics]);
        foreach (['target', 'current_stage', 'assessment_status', 'wilsonProgress', 'benchmark_division', 'options', 'next_steps'] as $key) {
            $this->assertSame($before[$key], $after[$key]);
        }
        $this->assertDatabaseCount('player_pathway_profiles', 1);
        $this->assertDatabaseCount('player_pathway_achievements', 0);
    }

    public function test_dated_saved_assessment_uses_only_the_current_ball_levels_score(): void
    {
        $metrics = $this->metrics(99, ['red' => 3, 'orange' => 3, 'standard' => 1]);
        $metrics['stage_ratings']['standard']['score'] = 67.3;
        $metrics['stale'] = true;
        $metrics['assessment_available'] = true;
        $summary = app(PlayerPathwayService::class)->summary(Player::findOrFail(1), ['pathway_metrics' => $metrics]);
        $this->assertSame('standard', $summary['current_stage']);
        $this->assertSame(67.3, $summary['score']);
        $this->assertSame('Wilson A', $summary['suggestedCompetition']);
        $this->assertStringContainsString('Wilson pathway CTR of 67.3', $summary['reason']);
        $history = ['reason' => null, 'pathway_metrics' => $metrics, 'series' => [], 'points' => []];
        $this->mock(PlayerRatingHistoryService::class)->shouldReceive('forPlayer')->andReturn($history);
        $this->actingAs($this->actor());
        $this->get(route('my.tennis.pathway', 1))->assertOk()->assertSee('Wilson A suggested')
            ->assertSee('Assessment uses the latest saved rating')->assertDontSee('progress is not assessed');
        foreach (['unavailable', 'local', 'not_comparable'] as $state) {
            $changed = $metrics;
            if ($state === 'unavailable') $changed['assessment_available'] = false;
            if ($state === 'local') $changed['stage_ratings']['standard']['local'] = true;
            if ($state === 'not_comparable') $changed['stage_ratings']['standard']['comparable'] = false;
            $this->assertNull(app(PlayerPathwayService::class)->summary(Player::findOrFail(1), ['pathway_metrics' => $changed])['suggestedCompetition'], $state);
        }
    }

    public function test_highest_rated_badge_preserves_starting_metadata_and_withheld_history(): void
    {
        $service = app(PlayerPathwayService::class);
        $player = Player::findOrFail(1);
        $metrics = $this->metrics(67.9);
        $metrics['stage_ratings']['orange'] = ['score' => 42.1, 'local' => true];
        $metrics['stage_ratings']['standard']['score'] = null;
        $this->assertSame(['stage' => 'orange', 'score' => 42.1, 'local' => true], $service->summary($player, ['pathway_metrics' => $metrics])['badge']);
        foreach ([['reason' => 'Publication revoked'], ['pathway_metrics' => array_replace($metrics, ['player_id' => 2])], ['pathway_metrics' => []]] as $change) {
            $this->assertSame(['stage' => null, 'score' => null, 'local' => false], $service->summary($player, array_replace(['pathway_metrics' => $metrics], $change))['badge']);
        }
        $service->recordStage($player, $this->actor(), ['stage' => 'red']);
        $metrics['stage_ratings']['red']['score'] = null;
        $summary = $service->summary($player, ['pathway_metrics' => $metrics]);
        $this->assertSame(['stage' => 'orange', 'score' => 42.1, 'local' => true], $summary['badge']);
        $this->assertSame('red', $summary['stage']);
        $this->assertSame('orange', $summary['current_stage']);
        $this->assertDatabaseHas('player_pathway_profiles', ['player_id' => 1, 'stage' => 'red']);
    }

    public function test_overview_badge_requires_selected_players_entitlement_and_never_loads_locked_ratings(): void
    {
        $player = Player::findOrFail(1);
        $page = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25);
        $dashboard = ['accountUser' => $this->actor(10, false), 'selectedPlayer' => $player, 'players' => collect([$player]), 'linkedPlayerIds' => [],
            'linkedPlayerPage' => $page, 'profile' => ['message' => 'Profile up to date'], 'entries' => collect(),
            'upcomingMatches' => collect(), 'upcomingMatchPage' => $page,
            'history' => ['placements' => collect(), 'seriesRankings' => collect()]];
        $this->mock(\App\Services\MyTennisService::class)->shouldReceive('dashboard')->andReturn($dashboard);
        $this->mock(\App\Services\Performance\TeamSheetProfileLinkService::class)->shouldReceive('status')->andReturn(['available' => false]);
        $history = $this->mock(PlayerRatingHistoryService::class);
        $history->shouldReceive('forPlayer')->once()->with(Mockery::on(fn ($candidate) => $candidate->id === 1), [], true)
            ->andReturn(['ball_stage' => 'standard', 'points' => [
                ['index' => 11, 'date' => '2026-10-01', 'score' => 67.9],
            ]]);
        SiteSetting::set(PlayerRatingAccess::SETTING, '1');
        $entitled = false;
        Gate::define('player-premium-rating', function ($user, $candidate) use (&$entitled) { return $entitled && $candidate->id === 1; });
        $this->actingAs($this->actor(10, false));
        $locked = $this->get(route('my.tennis', ['player' => 1]))->assertOk()
            ->assertSee('data-profile-plus-preview', false)->assertSee('Explore Player Plus')->assertDontSee('>67.9<', false)->assertDontSee('aria-label="CTR 67.9', false)
            ->assertDontSee('id="my-tennis-rating-tab"', false)->assertDontSee('data-rating-preview', false)
            ->assertDontSee('data-rating-url=', false);
        $this->assertTrue($locked->headers->hasCacheControlDirective('private'));
        $this->assertTrue($locked->headers->hasCacheControlDirective('no-store'));
        $entitled = true;
        $this->get(route('my.tennis', ['player' => 1]))->assertOk()->assertSee('aria-label="CTR 67.9 · Green/Yellow ball"', false)
            ->assertDontSee('Explore Player Plus')->assertSee('tab=rating', false)->assertDontSee('data-rating-preview', false)
            ->assertDontSee('data-rating-url=', false);
        // Entitlement to this player does not allow an unlinked viewer to load the badge.
        $this->actingAs($this->actor(30, false));
        $this->get(route('my.tennis', ['player' => 1]))->assertOk()->assertDontSee('>67.9<', false)->assertDontSee('aria-label="CTR 67.9', false);
    }

    public function test_profile_development_comment_uses_latest_evidence_and_stays_hidden_in_locked_preview(): void
    {
        $player = Player::findOrFail(1);
        $page = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25);
        $dashboard = ['accountUser' => $this->actor(10), 'selectedPlayer' => $player, 'players' => collect([$player]), 'linkedPlayerIds' => [],
            'linkedPlayerPage' => $page, 'profile' => ['message' => 'Profile up to date'], 'entries' => collect(),
            'upcomingMatches' => collect(), 'upcomingMatchPage' => $page,
            'history' => ['placements' => collect(), 'seriesRankings' => collect()]];
        $this->mock(\App\Services\MyTennisService::class)->shouldReceive('dashboard')->andReturn($dashboard);
        $this->mock(\App\Services\Performance\TeamSheetProfileLinkService::class)->shouldReceive('status')->andReturn(['available' => false]);
        $allowed = true;
        $access = $this->mock(PlayerRatingAccess::class);
        $access->shouldReceive('allows')->andReturnUsing(function () use (&$allowed) { return $allowed; });
        $access->shouldReceive('allowsMarketing')->andReturn(true);
        $access->shouldReceive('comingSoon')->andReturn(false);
        $history = $this->mock(PlayerRatingHistoryService::class);
        $metrics = $this->metrics(67.9, ['red' => 0, 'orange' => 0, 'standard' => 1]);
        $history->shouldReceive('forPlayer')->times(3)->andReturnUsing(function () use (&$metrics) {
            return ['reason' => null, 'pathway_metrics' => $metrics];
        });
        $this->actingAs($this->actor(10));
        $this->get(route('my.tennis', ['player' => 1, 'tab' => 'activity']))->assertOk()
            ->assertSee('data-overview-development-comment', false)->assertSee('Your next development target')
            ->assertDontSee('points to the benchmark')
            ->assertSee('Wilson A')->assertSee(route('my.tennis.pathway', $player), false);
        $metrics['stale'] = true;
        $this->get(route('my.tennis', ['player' => 1, 'tab' => 'activity']))->assertOk()->assertSee('When your current comparable rating is ready')
            ->assertDontSee('2.1 points to the benchmark')->assertDontSee('Aim for');
        $metrics = $this->metrics(75, ['red' => 0, 'orange' => 0, 'standard' => 3]);
        $this->get(route('my.tennis', ['player' => 1, 'tab' => 'activity']))->assertOk()->assertSee('Chat with your coach about which Wilson A events would suit you.')
            ->assertDontSee('Benchmark achieved:')->assertDontSee('points to the benchmark');
        $this->get(route('my.tennis', ['player' => 1, 'preview_non_plus' => 1]))->assertOk()
            ->assertDontSee('data-overview-development-comment', false)->assertDontSee('Benchmark achieved:');
        $allowed = false;
        $this->get(route('my.tennis', ['player' => 1, 'tab' => 'activity']))->assertOk()->assertDontSee('data-overview-development-comment', false);
    }
    public function test_only_super_users_can_toggle_the_non_plus_preview_without_loading_private_history(): void
    {
        $player = Player::findOrFail(1);
        $page = new \Illuminate\Pagination\LengthAwarePaginator([], 0, 25);
        $dashboard = ['accountUser' => $this->actor(10), 'selectedPlayer' => $player, 'players' => collect([$player]), 'linkedPlayerIds' => [],
            'linkedPlayerPage' => $page, 'profile' => ['message' => 'Profile up to date'], 'entries' => collect(),
            'upcomingMatches' => collect(), 'upcomingMatchPage' => $page,
            'history' => ['placements' => collect(), 'seriesRankings' => collect()]];
        $this->mock(\App\Services\MyTennisService::class)->shouldReceive('dashboard')->andReturn($dashboard);
        $this->mock(\App\Services\Performance\TeamSheetProfileLinkService::class)->shouldReceive('status')->andReturn(['available' => false]);
        $this->mock(PlayerRatingHistoryService::class)->shouldReceive('forPlayer')->twice()
            ->andReturn(['reason' => null, 'series' => [], 'ball_stage' => 'standard', 'points' => [
                ['index' => 11, 'date' => '2026-10-01', 'score' => 67.9],
            ]]);
        $this->actingAs($this->actor(10));
        $this->get(route('my.tennis', ['player' => 1, 'preview_non_plus' => 1]))->assertOk()
            ->assertSee('data-non-plus-preview-control', false)->assertSee('aria-checked="true"', false)
            ->assertSee('Viewing as a player without Player Plus.')->assertSee('data-rating-preview', false)
            ->assertSee('name="preview_non_plus" value="1"', false)
            ->assertDontSee('data-rating-url=', false)->assertDontSee('>67.9<', false)->assertDontSee('aria-label="CTR 67.9', false);
        $this->get(route('my.tennis', ['player' => 1]))->assertOk()
            ->assertSee('aria-checked="false"', false)->assertDontSee('data-rating-preview', false)
            ->assertDontSee('data-rating-url=', false)->assertSee('67.9');
        // Ordinary entitled viewers cannot enable the administrative preview by submitting its parameter.
        SiteSetting::set(PlayerRatingAccess::SETTING, '1');
        Gate::define('player-premium-rating', fn () => true);
        $this->actingAs($this->actor(10, false));
        $this->get(route('my.tennis', ['player' => 1, 'preview_non_plus' => 1]))->assertOk()
            ->assertDontSee('data-non-plus-preview-control', false)->assertDontSee('data-rating-preview', false)
            ->assertSee('data-rating-url=', false)->assertSee('67.9');
    }

    public function test_starting_orange_or_skipping_to_standard_never_completes_earlier_stages(): void
    {
        $service = app(PlayerPathwayService::class); $player = Player::findOrFail(1); $actor = $this->actor();
        $service->recordStage($player, $actor, ['stage' => 'orange']);
        $summary = $service->summary($player, ['pathway_metrics' => $this->metrics(99, ['red' => 0, 'orange' => 3, 'standard' => 0])]);
        $this->assertTrue($summary['ready']);
        $this->assertFalse($summary['stageProgress']['red']['met']);
        $this->assertTrue($summary['stageProgress']['orange']['met']);
        $service->recordStage($player, $actor, ['stage' => 'standard', 'wilson_division' => 'B']);
        $summary = $service->summary($player, ['pathway_metrics' => $this->metrics(99, ['red' => 0, 'orange' => 0, 'standard' => 3])]);
        $this->assertFalse($summary['stageProgress']['red']['met']);
        $this->assertFalse($summary['stageProgress']['orange']['met']);
        $this->assertDatabaseCount('player_pathway_achievements', 0);
    }

    public function test_unknown_stage_missing_targets_or_unclassified_events_never_award_readiness(): void
    {
        $service = app(PlayerPathwayService::class); $player = Player::findOrFail(1);
        $history = ['pathway_metrics' => $this->metrics(99)];
        $this->assertTrue($service->summary($player, $history)['ready']);
        $this->assertSame(3, $service->summary($player, $history)['events']);
        $service->recordStage($player, $this->actor(), ['stage' => 'red']);
        $unclassified = $this->metrics(99, ['red' => 0, 'orange' => 0, 'standard' => 0]);
        $unclassified['stage_ratings']['red']['score'] = 99;
        $unclassified['events'] = 3; $unclassified['unknown_stage_events'] = 3;
        $summary = $service->summary($player, ['pathway_metrics' => $unclassified]);
        $this->assertFalse($summary['ready']);
        $this->assertStringContainsString('conflicting ball labels', $summary['reason']);
        config(['pathway.targets.red' => ['ctr' => null, 'events' => 3]]);
        $summary = $service->summary($player, $history);
        $this->assertFalse($summary['ready']);
        $this->assertNull($summary['target']);
        $this->assertStringContainsString('not configured', $summary['reason']);
    }

    public function test_stale_nonfinite_uncomparable_withheld_or_other_player_metrics_fail_closed(): void
    {
        $service = app(PlayerPathwayService::class); $player = Player::findOrFail(1);
        $service->recordStage($player, $this->actor(), ['stage' => 'red']);
        foreach ([['stale' => true], ['player_id' => 2], ['available' => false]] as $change) {
            $this->assertFalse($service->summary($player, ['pathway_metrics' => array_replace($this->metrics(99), $change)])['ready']);
        }
        foreach ([['comparable' => false], ['score' => INF], ['score' => NAN], ['score' => 101]] as $change) {
            $metrics = $this->metrics(99); $metrics['stage_ratings']['red'] = array_replace($metrics['stage_ratings']['red'], $change);
            $this->assertFalse($service->summary($player, ['pathway_metrics' => $metrics])['ready']);
        }
        $this->assertFalse($service->summary($player, ['reason' => 'Publication revoked', 'pathway_metrics' => $this->metrics(99)])['ready']);
        $this->assertDatabaseCount('player_pathway_achievements', 0);
    }

    public function test_current_level_and_guidance_use_ball_hierarchy_not_score_or_saved_starting_stage(): void
    {
        $service = app(PlayerPathwayService::class); $player = Player::findOrFail(1);
        $service->recordStage($player, $this->actor(), ['stage' => 'red']);
        $before = PlayerPathwayProfile::firstOrFail()->getAttributes();
        $metrics = $this->metrics(99, ['red' => 3, 'orange' => 3, 'standard' => 6]);
        $metrics['stage_ratings']['orange']['score'] = 80;
        $metrics['stage_ratings']['standard']['score'] = 67.9;
        $summary = $service->summary($player, ['pathway_metrics' => $metrics]);
        $this->assertSame('standard', $summary['current_stage']);
        $this->assertSame(67.9, $summary['score']);
        $this->assertSame(6, $summary['ownEvents']);
        $this->assertSame('Wilson events · Academy events · Regional trial events · TSA Rainbow Kids events · School leagues', $summary['competition']);
        $this->assertNull($summary['division']);
        $this->assertNull($summary['target']);
        $this->assertSame('Wilson A suggested', $summary['assessment_status']);
        $this->assertFalse($summary['ready']);
        $this->assertSame($before, PlayerPathwayProfile::firstOrFail()->getAttributes());
        $this->assertDatabaseCount('player_pathway_achievements', 0);

        $metrics['stage_ratings']['standard']['score'] = 0;
        $this->assertSame(['stage' => 'standard', 'score' => 0.0, 'local' => false], $service->summary($player, ['pathway_metrics' => $metrics])['badge']);
        foreach ([null, INF, NAN, -1, 101, 'invalid'] as $invalid) {
            $metrics['stage_ratings']['standard']['score'] = $invalid;
            $this->assertSame('orange', $service->summary($player, ['pathway_metrics' => $metrics])['current_stage']);
        }
        $metrics['available'] = false;
        $summary = $service->summary($player, ['pathway_metrics' => $metrics]);
        $this->assertNull($summary['current_stage']);
        $this->assertNull($summary['badge']['score']);
        $this->assertNull($summary['stageProgress']['orange']['score']);
    }

    public function test_pending_stale_and_local_assessments_are_distinct_from_unmet_and_met_targets(): void
    {
        $service = app(PlayerPathwayService::class); $player = Player::findOrFail(1);
        $service->recordStage($player, $this->actor(), ['stage' => 'standard', 'wilson_division' => 'B']);
        $metrics = $this->metrics(67.9, ['red' => 0, 'orange' => 0, 'standard' => 6]);
        $summary = $service->summary($player, ['pathway_metrics' => $metrics]);
        $this->assertSame('Wilson A suggested', $summary['assessment_status']);
        $this->assertSame('Wilson A suggested', $summary['stageProgress']['standard']['status']);
        $this->assertSame('Not assessed', $summary['stageProgress']['red']['status']);
        $this->assertTrue($summary['eventsMet']);
        $this->assertFalse($summary['ratingMet']);
        $metrics['stage_ratings']['standard']['score'] = 70;
        $this->assertSame('A', $service->summary($player, ['pathway_metrics' => $metrics])['suggestedDivision']);
        $this->assertNull($service->summary($player, ['pathway_metrics' => $metrics])['target']);
        foreach (['stale', 'local', 'disconnected'] as $state) {
            $pending = $metrics;
            if ($state === 'stale') $pending['stale'] = true;
            if ($state === 'local') $pending['stage_ratings']['standard']['local'] = true;
            if ($state === 'disconnected') $pending['stage_ratings']['standard']['comparable'] = false;
            $summary = $service->summary($player, ['pathway_metrics' => $pending]);
            $this->assertSame(70.0, $summary['badge']['score']);
            $this->assertSame('Not assessed', $summary['assessment_status']);
            $this->assertSame('Not assessed', $summary['stageProgress']['standard']['status']);
            $this->assertFalse($summary['ready']);
        }
        $service->recordStage($player, $this->actor(), ['stage' => 'standard']);
        $summary = $service->summary($player, ['pathway_metrics' => $metrics]);
        $this->assertSame('Wilson A suggested', $summary['assessment_status']);
        $this->assertSame('A', $summary['suggestedDivision']);
        $this->assertNull($summary['benchmark_division']);
        $this->assertDatabaseCount('player_pathway_achievements', 0);
    }

    public function test_stale_saved_badge_highlights_its_competition_without_assessing_progress(): void
    {
        $service = app(PlayerPathwayService::class); $player = Player::findOrFail(1);
        $metrics = $this->metrics(67.4, ['red' => 0, 'orange' => 0, 'standard' => 6]);
        $metrics['stale'] = true; $metrics['assessment_available'] = false;
        $history = ['reason' => null, 'pathway_metrics' => $metrics];
        $summary = $service->summary($player, $history);
        $this->assertSame('Wilson A', $summary['currentCompetition']);
        $this->assertNull($summary['suggestedCompetition']);
        $this->assertFalse($summary['assessed']);
        $this->assertFalse($summary['ready']);
        $this->mock(PlayerRatingHistoryService::class)->shouldReceive('forPlayer')->andReturn($history);
        $this->actingAs($this->actor());
        $html = $this->get(route('my.tennis.pathway', 1))->assertOk()->assertSee('Competition recommendation uncertain')->assertSee('The saved rating needs an update')->getContent();
        $this->assertStringContainsString('data-pathway-competition="Wilson A"  aria-current="step"', $html);
        preg_match_all('/<li\b[^>]*aria-current="step"[^>]*>/', $html, $currentCards);
        $this->assertCount(1, $currentCards[0]);
        $this->assertStringContainsString('You are here · latest saved badge', $html);
        foreach ([['local' => true], ['comparable' => false], ['score' => null]] as $change) {
            $invalid = $metrics;
            $invalid['stage_ratings']['standard'] = array_replace($invalid['stage_ratings']['standard'], $change);
            $this->assertNull($service->summary($player, ['pathway_metrics' => $invalid])['currentCompetition']);
        }
        $this->assertNull($service->summary($player, ['reason' => 'Publication changed', 'pathway_metrics' => $metrics])['currentCompetition']);
        $this->assertNull($service->summary($player, ['pathway_metrics' => array_replace($metrics, ['player_id' => 2])])['currentCompetition']);
        foreach ([0 => 'Wilson B', 50 => 'Wilson B', 75 => 'Wilson A', 100 => 'TSA tournaments'] as $score => $competition) {
            $boundary = $metrics; $boundary['stage_ratings']['standard']['score'] = $score;
            $this->assertSame($competition, $service->summary($player, ['pathway_metrics' => $boundary])['currentCompetition']);
        }
        $boundary['stage_ratings']['standard']['score'] = 75.1;
        $this->assertSame('TSA tournaments', $service->summary($player, ['pathway_metrics' => $boundary])['currentCompetition']);
        $boundary['stage_ratings']['standard']['score'] = 50.1;
        $this->assertSame('Wilson A', $service->summary($player, ['pathway_metrics' => $boundary])['currentCompetition']);
    }

    public function test_manual_verification_is_gone_and_existing_audit_records_are_not_progress(): void
    {
        $this->actingAs($this->actor());
        $service = app(PlayerPathwayService::class); $player = Player::findOrFail(1);
        $service->recordStage($player, $this->actor(), ['stage' => 'red']);
        $audit = PlayerPathwayAchievement::create(['player_id' => 1, 'stage' => 'red', 'milestone' => 'coach_review', 'verified_by_user_id' => 9, 'verified_at' => now()])->fresh()->getAttributes();
        $this->postJson(route('my.tennis.pathway.milestones', 1), ['stage' => 'red', 'milestones' => ['independent_scoring']])->assertStatus(410);
        $this->assertSame($audit, PlayerPathwayAchievement::firstOrFail()->getAttributes());
        $this->assertFalse($service->summary($player)['ready']);
        $this->get(route('my.tennis.pathway.info', ['player' => 1, 'stage' => 'red']))->assertOk()->assertSee('Your progress so far')->assertDontSee('coach_review')->assertDontSee('Verify selected milestones');
        $this->assertDatabaseCount('player_pathway_achievements', 1);
    }

    public function test_linked_entitled_viewer_can_read_but_cannot_write_or_read_another_player(): void
    {
        SiteSetting::set(PlayerRatingAccess::SETTING, '1'); Gate::define('player-premium-rating', fn () => true);
        DB::table('user_players')->insert(['user_id' => 11, 'player_id' => 1]);
        $this->actingAs($this->actor(11, false));
        $this->get(route('my.tennis.rating', 1))->assertOk()->assertSee('Next steps')->assertDontSee('data-player-pathway', false)->assertDontSee('record your starting stage')->assertDontSee('data-pathway-form', false);
        $this->get(route('my.tennis.rating', 2))->assertForbidden();
        $this->postJson(route('my.tennis.pathway.stage', 1), ['stage' => 'standard'])->assertForbidden();
        $this->postJson(route('my.tennis.pathway.milestones', 1), [])->assertForbidden();
        $this->assertDatabaseCount('player_pathway_profiles', 0);
    }

    public function test_recommendations_branch_after_red_without_entry_or_stage_changes(): void
    {
        $service = app(PlayerPathwayService::class); $player = Player::findOrFail(1);
        $service->recordStage($player, $this->actor(), ['stage' => 'red']);
        $summary = $service->summary($player, ['pathway_metrics' => $this->metrics(50)]);
        $this->assertTrue($summary['ready']); $this->assertSame('Academy events · TSA Rainbow Kids events · School leagues', $summary['competition']);
        $this->assertStringContainsString('Orange-ball events: U/9 B events', $summary['options'][0]);
        $orange = $service->summary($player, ['pathway_metrics' => $this->metrics(60, ['red' => 0, 'orange' => 3, 'standard' => 0])]);
        $this->assertSame('U/9 B events · Academy events · TSA Rainbow Kids events · School leagues', $orange['competition']);
        $this->assertDatabaseHas('player_pathway_profiles', ['player_id' => 1, 'stage' => 'red']);
        $this->assertDatabaseCount('player_pathway_achievements', 0);
    }

    public function test_stage_recording_validates_divisions_preserves_audit_and_does_not_accept_spoofed_player(): void
    {
        $this->actingAs($this->actor());
        $this->postJson(route('my.tennis.pathway.stage', 1), ['stage' => 'unknown'])->assertUnprocessable();
        $this->postJson(route('my.tennis.pathway.stage', 1), ['stage' => 'red', 'wilson_division' => 'unknown'])->assertUnprocessable();
        $this->postJson(route('my.tennis.pathway.stage', 1), ['stage' => 'red', 'wilson_division' => 'A'])->assertOk();
        $this->assertDatabaseHas('player_pathway_profiles', ['player_id' => 1, 'stage' => 'red', 'wilson_division' => 'A']);
        $this->postJson(route('my.tennis.pathway.stage', 1), ['stage' => 'green', 'player_id' => 2])->assertOk();
        $this->assertDatabaseHas('player_pathway_profiles', ['player_id' => 1, 'stage' => 'standard', 'wilson_division' => null, 'recorded_by_user_id' => 99]);
        $this->assertDatabaseMissing('player_pathway_profiles', ['player_id' => 2]);
        $this->assertNull(app(PlayerPathwayService::class)->summary(Player::findOrFail(1))['target']);
        Schema::drop('player_pathway_profiles');
        $this->get(route('my.tennis.pathway', 1))->assertOk()->assertSee('No saved ball-level rating available.');
        $this->postJson(route('my.tennis.pathway.stage', 1), ['stage' => 'red'])->assertStatus(503);
    }

    public function test_current_wilson_division_can_be_recorded_without_rewriting_starting_stage_or_inferring_level(): void
    {
        $service = app(PlayerPathwayService::class); $player = Player::findOrFail(1);
        $this->actingAs($this->actor());
        $this->postJson(route('my.tennis.pathway.stage', 1), ['stage' => 'red', 'wilson_division' => 'B'])->assertOk();
        $metrics = $this->metrics(70, ['red' => 0, 'orange' => 0, 'standard' => 6]);
        $history = ['reason' => null, 'pathway_metrics' => $metrics, 'series' => [], 'points' => []];
        $summary = $service->summary($player, $history);
        $this->assertSame('red', $summary['stage']);
        $this->assertSame('standard', $summary['current_stage']);
        $this->assertSame('B', $summary['division']);
        $this->assertNull($summary['target']);
        $this->assertSame('A', $summary['suggestedDivision']);
        $this->assertFalse($summary['ready']);
        $this->mock(PlayerRatingHistoryService::class)->shouldReceive('forPlayer')->andReturn($history);
        $this->get(route('my.tennis.pathway', 1))->assertOk()->assertSee('Wilson A suggested')->assertSee('Ball level: Green/yellow ball')
            ->assertDontSee('data-pathway-form', false);
        $this->assertDatabaseHas('player_pathway_profiles', ['player_id' => 1, 'stage' => 'red', 'wilson_division' => 'B']);
        $this->assertDatabaseCount('player_pathway_achievements', 0);

        $summary = $service->summary($player, ['pathway_metrics' => $this->metrics(60)]);
        $this->assertSame('red', $summary['current_stage']);
        $this->assertNull($summary['division']);
        $this->assertSame('B', $summary['saved_division']);
        $this->assertSame(50.0, $summary['target']['ctr']);
        $summary = $service->summary($player);
        $this->assertNull($summary['current_stage']);
        $this->assertNull($summary['target']);
        $this->assertSame('B', $summary['saved_division']);
    }

    public function test_red_and_orange_benchmarks_never_borrow_other_ball_scores_or_event_counts(): void
    {
        $service = app(PlayerPathwayService::class); $player = Player::findOrFail(1);
        $service->recordStage($player, $this->actor(), ['stage' => 'red']);
        $before = PlayerPathwayProfile::firstOrFail()->getAttributes();
        foreach (['red' => 50, 'orange' => 60] as $ball => $threshold) {
            $metrics = $this->metrics(99, ['red' => 6, 'orange' => 6, 'standard' => 6]);
            $metrics['stage_ratings'][$ball]['score'] = $threshold - .1;
            $summary = $service->summary($player, ['pathway_metrics' => $metrics]);
            $this->assertFalse($summary['stageProgress'][$ball]['met']);
            $metrics['stage_ratings'][$ball]['score'] = $threshold;
            $metrics['stage_events'][$ball] = 2;
            $this->assertFalse($service->summary($player, ['pathway_metrics' => $metrics])['stageProgress'][$ball]['met']);
            $metrics['stage_events'][$ball] = 3;
            $this->assertTrue($service->summary($player, ['pathway_metrics' => $metrics])['stageProgress'][$ball]['met']);
            $metrics['stage_events'][$ball] = 0;
            $this->assertFalse($service->summary($player, ['pathway_metrics' => $metrics])['stageProgress'][$ball]['met']);
        }
        $this->assertSame($before, PlayerPathwayProfile::firstOrFail()->getAttributes());
        $this->assertDatabaseCount('player_pathway_achievements', 0);
    }

    public function test_next_steps_describe_own_level_deficits_then_coach_review_without_automatic_promotion(): void
    {
        $service = app(PlayerPathwayService::class); $player = Player::findOrFail(1);
        foreach (['red' => [50, 'Red-ball CTR', 'Orange-ball'], 'orange' => [60, 'Orange-ball CTR', 'Wilson B / Wilson A']] as $ball => [$threshold, $label, $next]) {
            $service->recordStage($player, $this->actor(), ['stage' => $ball]);
            $counts = ['red' => 0, 'orange' => 0, 'standard' => 0]; $counts[$ball] = 1;
            $metrics = $this->metrics($threshold - 2.1, $counts);
            $summary = $service->summary($player, ['pathway_metrics' => $metrics]);
            $this->assertSame($label, $summary['ctr_label']);
            $steps = implode(' ', $summary['next_steps']);
            $this->assertStringContainsString('2.1 points to the benchmark', $steps);
            $this->assertStringContainsString('Play 2 more published '.PlayerPathwayService::STAGES[$ball].' events', $steps);
            $metrics['stage_ratings'][$ball]['score'] = $threshold;
            $metrics['stage_events'][$ball] = 3;
            $summary = $service->summary($player, ['pathway_metrics' => $metrics]);
            $this->assertTrue($summary['ready']);
            $this->assertStringContainsString('Chat with your coach about trying '.($ball === 'red' ? 'Orange-ball' : 'Wilson B or Wilson A').' events', $summary['next_steps'][0]);
            $this->assertStringContainsString('earlier levels and event entry are reviewed separately', $summary['next_steps'][1]);
            $this->assertDatabaseHas('player_pathway_profiles', ['player_id' => 1, 'stage' => $ball]);
        }
        $this->assertDatabaseCount('player_pathway_achievements', 0);
    }

    public function test_next_steps_and_collapsed_process_explain_pending_requirements_and_configured_own_ball_targets(): void
    {
        $service = app(PlayerPathwayService::class); $player = Player::findOrFail(1);
        $metrics = $this->metrics(48, ['red' => 1, 'orange' => 0, 'standard' => 0]);
        $this->assertStringContainsString('2.0 points to the benchmark', $service->summary($player, ['pathway_metrics' => $metrics])['next_steps'][0]);
        $service->recordStage($player, $this->actor(), ['stage' => 'red']);
        $pending = $metrics; $pending['stale'] = true;
        $this->assertStringContainsString('next saved rating update', $service->summary($player, ['pathway_metrics' => $pending])['next_steps'][0]);
        $pending = $metrics; $pending['stage_ratings']['red']['comparable'] = false;
        $this->assertStringContainsString('comparable rating evidence', $service->summary($player, ['pathway_metrics' => $pending])['next_steps'][0]);
        $this->assertStringContainsString('Play eligible published Cape Tennis singles', $service->summary($player)['next_steps'][0]);
        config(['pathway.targets.red' => ['ctr' => 52, 'events' => 4]]);
        $history = ['reason' => null, 'pathway_metrics' => $metrics, 'series' => [], 'points' => []];
        $this->mock(PlayerRatingHistoryService::class)->shouldReceive('forPlayer')->andReturn($history);
        $this->actingAs($this->actor());
        $response = $this->get(route('my.tennis.pathway.info', ['player' => 1, 'stage' => 'red']))->assertOk()->assertSee('What’s next?')
            ->assertSee('52 CTR and 4 published Red ball events')
            ->assertSee('4.0 points to the benchmark')->assertSee('Play 3 more published Red ball events')
            ->assertSee('your progress here comes from results at this level.');
        $response->assertSee('data-pathway-stage-details="red"', false);
        $this->assertDatabaseHas('player_pathway_profiles', ['player_id' => 1, 'stage' => 'red']);
        $this->assertDatabaseCount('player_pathway_achievements', 0);
    }

    public function test_guest_routes_and_direct_service_writes_remain_private(): void
    {
        $this->getJson(route('my.tennis.rating', 1))->assertUnauthorized();
        $this->getJson(route('my.tennis.pathway', 1))->assertUnauthorized();
        $this->postJson(route('my.tennis.pathway.stage', 1), ['stage' => 'red'])->assertUnauthorized();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(PlayerPathwayService::class)->recordStage(Player::findOrFail(1), $this->actor(10, false), ['stage' => 'red']);
    }

    public function test_dedicated_pathway_page_requires_private_rating_access_before_loading_evidence(): void
    {
        $history = ['reason' => null, 'pathway_metrics' => $this->metrics(67.9, ['red' => 0, 'orange' => 0, 'standard' => 6])];
        $this->mock(PlayerRatingHistoryService::class)->shouldReceive('forPlayer')->twice()
            ->with(Mockery::on(fn ($player) => $player->id === 1))->andReturn($history);
        SiteSetting::set(PlayerRatingAccess::SETTING, '1');
        Gate::define('player-premium-rating', fn () => true);
        $this->actingAs($this->actor(10, false));
        $page = $this->get(route('my.tennis.pathway', 1))->assertOk()
            ->assertSee('data-player-pathway-page', false)->assertSee('data-pathway-snapshot', false)
            ->assertSee('Ball level: Green/yellow ball')->assertSee('You are here')
            ->assertSeeInOrder(['Red ball', 'Academy events', 'TSA Rainbow Kids events', 'School leagues', 'Orange ball', 'U/9 B events', 'Academy events', 'TSA Rainbow Kids events', 'School leagues', 'data-pathway-competition="Wilson B"', 'data-pathway-competition="Wilson A"', 'data-pathway-competition="TSA tournaments"'], false)
            ->assertSee(e(route('my.tennis', ['player' => 1, 'tab' => 'rating'])), false)
            ->assertDontSee('data-pathway-form', false);
        $this->assertTrue($page->headers->hasCacheControlDirective('private'));
        $this->assertTrue($page->headers->hasCacheControlDirective('no-store'));
        if (getenv('CT_PATHWAY_QA_HTML') === '1') {
            file_put_contents(storage_path('framework/testing/pathway-page-qa.html'), $page->getContent());
        }
        $this->get(route('my.tennis.pathway', 2))->assertForbidden();
        Gate::define('player-premium-rating', fn () => false);
        $this->get(route('my.tennis.pathway', 1))->assertForbidden();
        $this->actingAs($this->actor());
        $this->get(route('my.tennis.pathway', 1))->assertOk()->assertDontSee('data-pathway-form', false);
    }
}
