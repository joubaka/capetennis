<?php

namespace Tests\Feature;

use App\Models\{Player, PlayerPlusAccessOverride, User};
use App\Services\Performance\PlayerRatingHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyTennisOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\Carbon::parse('2026-11-15 10:00', 'Africa/Johannesburg'));
        $this->mock(\App\Services\Performance\PlayerMatchProgress::class)->shouldReceive('forPlayer')->andReturn([
            'rows' => [], 'as_of' => null, 'restricted' => 0, 'reason' => 'Before/after reconstruction is awaiting a nightly saved update.',
        ]);
        $this->mock(\App\Services\Performance\PlayerMatchProgressCalculator::class)->shouldNotReceive('latest', 'pair');
        $this->mock(\App\Services\Performance\PlayerMatchInfluenceCalculator::class)->shouldNotReceive('calculate');
        $this->withoutMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class, \App\Http\Middleware\EnsurePlayerProfileUpdated::class]);
    }

    private function player(bool $plus = true): Player
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id, 'name' => 'Example', 'surname' => 'Player']);
        if ($plus) PlayerPlusAccessOverride::create(['player_id' => $player->id, 'mode' => 'grant', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $this->actingAs($user);
        return $player;
    }

    private function row(Player $player, string $id, string $date, bool $won = true): array
    {
        return ['kind' => 'match', 'months' => 1, 'source_id' => $id, 'date' => $date, 'date_basis' => 'scheduled match date', 'event' => 'Example Open', 'event_id' => 55,
            'players' => [$player->id, 99999], 'names' => ['Example Player', 'Example Opponent'], 'winner_side' => $won ? 1 : 2, 'score' => '6-3'];
    }

    private function export(string $name, string $html): void
    {
        if ($dir = getenv('CT_OVERVIEW_EXPORT_DIR')) {
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            file_put_contents($dir.'/'.$name.'.html', $html);
        }
    }

    public function test_activity_keeps_schedule_and_history_accessible_without_rating_calculations(): void
    {
        $player = $this->player();
        $history = $this->mock(PlayerRatingHistoryService::class);
        $history->shouldReceive('forPlayer')->once()->andReturn(['reason' => 'Rating update pending.']);
        $history->shouldNotReceive('recordSource');
        $response = $this->get(route('my.tennis', ['player' => $player->id, 'tab' => 'activity']))->assertOk()
            ->assertSee('Your tennis activity')->assertSee('Upcoming matches')->assertSee('Match reminders')
            ->assertSee('No upcoming published match times found.')->assertSee('Entries')
            ->assertSee('Recent published results')->assertSee('Current rankings');
        $this->export('activity', $response->getContent());
    }

    public function test_profile_composes_current_month_and_season_without_calculating_ratings(): void
    {
        $player = $this->player();
        $history = $this->mock(PlayerRatingHistoryService::class);
        $history->shouldReceive('forPlayer')->once()->withArgs(fn ($p, $f, $summaryOnly) => $p->id === $player->id && $f === [] && $summaryOnly)
            ->andReturn(['reason' => null, 'points' => [], 'as_of' => '2026-11-14']);
        $rows = [$this->row($player, 'match:1', '2026-11-10'), $this->row($player, 'match:2', '2026-11-11', false),
            $this->row($player, 'match:3', '2026-10-10'), $this->row($player, 'match:4', '2025-11-10'),
            $this->row($player, 'match:future', '2026-11-20'), $this->row($player, 'teamsheet:hidden', '2026-11-12')];
        $history->shouldReceive('recordSource')->once()->andReturn(['reason' => null, 'as_of' => '2026-11-14', 'stale' => true, 'evidence' => $rows]);
        $this->mock(\App\Services\Performance\PlayerMatchProgressCalculator::class)->shouldNotReceive('latest', 'pair');
        $response = $this->get(route('my.tennis', ['player' => $player->id]))->assertOk()
            ->assertSee('Your monthly tennis report')->assertSee('November 2026')->assertSee('Example Opponent')
            ->assertSee('An update is pending; recent matches may not be included yet.')->assertSee('Edit profile')
            ->assertHeader('Cache-Control', 'no-store, private');
        $record = $response->viewData('overviewRecord');
        $this->assertSame(3, $record['played']);
        $this->assertSame(2, $record['month_report']['summary']['played']);
        $this->assertSame(1, $record['month_report']['summary']['wins']);
        $this->assertSame(1, $record['month_report']['previous']['played']);
        $this->export('member', $response->getContent());
    }

    public function test_non_plus_profile_and_previews_show_readable_fictional_samples_without_private_reads(): void
    {
        $player = $this->player(false);
        $this->mock(\App\Services\Performance\PlayerMatchProgress::class)->shouldNotReceive('forPlayer', 'build');
        $this->mock(PlayerRatingHistoryService::class)->shouldNotReceive('forPlayer', 'recordSource');
        $response = $this->get(route('my.tennis', ['player' => $player->id]))->assertOk()
            ->assertSee('Fictional example')->assertSee('4 wins · 2 losses')->assertDontSee('Sample value hidden')
            ->assertDontSee('data-monthly-report', false)->assertSee('Edit profile');
        $this->export('non-plus', $response->getContent());
        $preview = $this->get(route('my.tennis.feature-preview', ['player' => $player, 'feature' => 'record']))->assertOk()
            ->assertSee('Fictional example')->assertSee('4 wins · 2 losses')->assertDontSee('Sample value hidden');
        $this->export('preview', $preview->getContent());
    }

    public function test_unrelated_account_cannot_read_an_entitled_players_report_or_preview(): void
    {
        $player = $this->player();
        $this->actingAs(User::factory()->create());
        $this->mock(\App\Services\Performance\PlayerMatchProgress::class)->shouldNotReceive('forPlayer', 'build');
        $this->mock(PlayerRatingHistoryService::class)->shouldNotReceive('forPlayer', 'recordSource');
        $this->get(route('my.tennis', ['player' => $player->id]))->assertOk()->assertDontSee('data-monthly-report', false);
        $this->get(route('my.tennis.feature-preview', ['player' => $player, 'feature' => 'record']))->assertForbidden();
    }

    public function test_unavailable_record_is_not_a_zero_season_and_empty_current_month_does_not_reuse_old_results(): void
    {
        $player = $this->player();
        $history = $this->mock(PlayerRatingHistoryService::class);
        $history->shouldReceive('forPlayer')->twice()->andReturn(['reason' => 'Rating update pending.']);
        $history->shouldReceive('recordSource')->once()->andReturn(['reason' => 'Publication changed.']);
        $this->get(route('my.tennis', ['player' => $player->id]))->assertOk()->assertSee('Your season report is not available yet')
            ->assertSee('Publication changed.')->assertDontSee('Recorded matches · 2026');
        $history->shouldReceive('recordSource')->once()->andReturn(['reason' => null, 'as_of' => '2026-11-14', 'evidence' => [$this->row($player, 'match:old', '2026-10-01')]]);
        $response = $this->get(route('my.tennis', ['player' => $player->id]))->assertOk()->assertSee('No matches recorded for November 2026');
        $this->assertSame(0, $response->viewData('overviewRecord')['month_report']['summary']['played']);
    }

    public function test_report_withholds_zero_counts_when_saved_coverage_ends_before_current_month(): void
    {
        $player = $this->player();
        $history = $this->mock(PlayerRatingHistoryService::class);
        $history->shouldReceive('forPlayer')->once()->andReturn(['reason' => 'Pending.']);
        $history->shouldReceive('recordSource')->once()->andReturn(['reason' => null, 'as_of' => '2026-10-31', 'stale' => true, 'evidence' => []]);
        $this->get(route('my.tennis', ['player' => $player->id]))->assertOk()
            ->assertSee('This month’s report is awaiting saved results coverage.')
            ->assertDontSee('No matches recorded for November 2026');
    }

    public function test_rating_summary_retains_its_comparison_and_evidence_qualifications(): void
    {
        $player = $this->player();
        $history = $this->mock(PlayerRatingHistoryService::class);
        $point = ['index' => 11, 'date' => '2026-11-14', 'score' => 50, 'group' => 'local', 'connection_status' => 'disconnected',
            'historical_connection' => false, 'confidence_label' => 'Low', 'evidence_basis' => 'finish_only'];
        $history->shouldReceive('forPlayer')->once()->andReturn(['points' => [$point], 'as_of' => '2026-11-14']);
        $history->shouldReceive('recordSource')->twice()->andReturn(['reason' => 'Pending.']);
        $this->get(route('my.tennis', ['player' => $player->id]))->assertOk()->assertSee('Within-group estimate only; not calibrated against other groups.')
            ->assertSee('Low confidence · final placings only.');
        $point['connection_status'] = 'historical'; $point['historical_connection'] = true;
        $history->shouldReceive('forPlayer')->once()->andReturn(['points' => [$point], 'as_of' => '2026-11-14']);
        $this->get(route('my.tennis', ['player' => $player->id]))->assertOk()->assertSee('Low confidence · depends on a historical connection.');
    }

    public function test_month_report_counts_full_record_and_previous_month_across_year_boundary(): void
    {
        $player = $this->player();
        $rows = [];
        for ($i = 1; $i <= 25; $i++) $rows[] = $this->row($player, 'match:'.$i, '2026-01-10');
        $rows[] = $this->row($player, 'match:previous', '2025-12-10', false);
        $this->mock(PlayerRatingHistoryService::class)->shouldReceive('recordSource')->once()->andReturn([
            'reason' => null, 'as_of' => '2026-01-15', 'evidence' => $rows,
        ]);
        $record = app(\App\Services\Performance\PlayerPlusRecordService::class)->forPlayer($player, ['year' => '2026', 'record_month' => '2026-01']);
        $this->assertCount(20, $record['results']['rows']);
        $this->assertSame(25, $record['month_report']['summary']['played']);
        $this->assertSame(1, $record['month_report']['previous']['losses']);
        $this->assertSame(25, $record['played']);
    }
    public function test_profile_prominently_shows_five_saved_pairs_in_chronological_order_without_calculation(): void
    {
        $player = $this->player();
        $history = $this->mock(PlayerRatingHistoryService::class);
        $history->shouldReceive('forPlayer')->once()->andReturn(['reason' => 'Rating update pending.']);
        $history->shouldReceive('recordSource')->once()->andReturn(['reason' => null, 'as_of' => '2026-11-15', 'evidence' => []]);
        $rows = [];
        foreach ([[70, 71.2, 1.2], [62, 60.5, -1.5], [55, 55, 0], [null, null, null], [0, 100, 100]] as $index => [$before, $after, $delta]) {
            $rows[] = ['date' => '2026-11-'.(10 + $index), 'time' => '2026-11-'.(10 + $index).' 09:00:00', 'opponent' => 'Fictional Opponent '.($index + 1),
                'score' => $index % 2 ? '3-6' : '6-3', 'won' => $index % 2 === 0,
                'before_score' => $before, 'after_score' => $after, 'delta' => $delta,
                'reason' => $delta === null ? 'Scheduled match order could not be established.' : null,
                'hidden_matches' => $index === 1 ? 2 : 0, 'used_matches' => []];
        }
        $progress = ['rows' => array_reverse($rows), 'as_of' => '2026-11-15', 'restricted' => 1, 'reason' => null];
        $service = $this->mock(\App\Services\Performance\PlayerMatchProgress::class);
        $service->shouldNotReceive('build');
        $service->shouldReceive('forPlayer')->once()->withArgs(fn ($user, $actual) => $user->id === $player->userId && $actual->id === $player->id)->andReturn($progress);
        $this->mock(\App\Services\Performance\PlayerRatingTopTen::class)->shouldNotReceive('build');
        $response = $this->get(route('my.tennis', ['player' => $player->id, 'tab' => 'profile']))->assertOk()
            ->assertSee('CTR before &amp; after each match', false)->assertSee('Reconstructed using scheduled match order.')
            ->assertSee('Scheduled match order could not be established.')->assertSee('Some match details are restricted')
            ->assertSee('2 evidence details are unavailable, restricted or beyond the display limit')
            ->assertSee('#ctr-match-progress-heading')->assertHeader('Cache-Control', 'no-store, private');
        $html = $response->getContent();
        $dom = new \DOMDocument; @$dom->loadHTML($html); $xp = new \DOMXPath($dom);
        $this->assertSame(1, $xp->query('//*[@data-profile-match-progress]')->length);
        $this->assertSame(5, $xp->query('//*[@data-profile-match-row]')->length);
        $this->assertSame(4, $xp->query('//*[@data-profile-match-pair]')->length);
        $this->assertSame(['1.2', '-1.5', '0', '100'], array_map(fn ($node) => $node->getAttribute('data-delta'), iterator_to_array($xp->query('//*[@data-profile-match-pair]'))));
        $this->assertSame(['Fictional Opponent 1', 'Fictional Opponent 2', 'Fictional Opponent 3', 'Fictional Opponent 4', 'Fictional Opponent 5'], array_map(fn ($node) => trim($node->textContent), iterator_to_array($xp->query('//*[@data-profile-match-row]//h3'))));
        $this->assertLessThan(strpos($html, 'data-player-overview'), strpos($html, 'data-profile-match-progress'));
        $this->assertStringContainsString('+0.0 CTR', $html);
        $this->assertStringContainsString('0–100 scale', $html);
        if (getenv('CT_PROFILE_MATCH_QA_HTML') === '1') file_put_contents(storage_path('framework/testing/profile-match-progress-qa.html'), $html);
    }

    public function test_profile_reads_only_saved_progress_and_missing_artifact_never_becomes_zero_pairs(): void
    {
        $player = $this->player();
        $history = $this->mock(PlayerRatingHistoryService::class);
        $history->shouldReceive('forPlayer')->once()->andReturn(['reason' => 'Rating update pending.']);
        $history->shouldReceive('recordSource')->once()->andReturn(['reason' => 'Saved record pending.']);
        $this->mock(\App\Services\Performance\PlayerMatchProgressStore::class)->shouldReceive('read')->once()->with((int) $player->id)->andReturnNull();
        $this->instance(\App\Services\Performance\PlayerMatchProgress::class, $this->app->build(\App\Services\Performance\PlayerMatchProgress::class));
        $this->mock(\App\Services\Performance\PlayerRatingTopTen::class)->shouldNotReceive('build', 'forMatchPlayers');
        $response = $this->get(route('my.tennis', ['player' => $player->id]))->assertOk()
            ->assertSee('data-profile-match-pending', false)->assertSee('Before/after reconstruction is awaiting a nightly saved update.')
            ->assertDontSee('data-profile-match-pair', false)->assertDontSee('data-before="0"', false);
        if (getenv('CT_PROFILE_MATCH_QA_HTML') === '1') file_put_contents(storage_path('framework/testing/profile-match-progress-pending-qa.html'), $response->getContent());
    }

    public function test_profile_compact_chart_caps_rows_and_withholds_stale_or_inconsistent_pairs(): void
    {
        $row = ['date' => '2026-11-14', 'time' => '2026-11-14 09:00:00', 'opponent' => 'Fictional opponent', 'score' => '6-3', 'won' => true,
            'before_score' => 50, 'after_score' => 51, 'delta' => 1, 'reason' => null];
        $rows = array_fill(0, 5, $row);
        $rows[] = array_replace($row, ['opponent' => 'Sixth row must be omitted']);
        $data = ['selectedPlayer' => new Player(['id' => 42]), 'profileMatchProgress' => ['rows' => $rows, 'reason' => null, 'as_of' => '2026-11-15', 'restricted' => 0]];
        $data['selectedPlayer']->id = 42;
        $html = view('frontend.my-tennis.profile-match-progress', $data)->render();
        $this->assertSame(5, substr_count($html, 'data-profile-match-pair'));
        $this->assertStringNotContainsString('Sixth row must be omitted', $html);
        $data['profileMatchProgress']['reason'] = 'Saved reconstruction is out of date.';
        $html = view('frontend.my-tennis.profile-match-progress', $data)->render();
        $this->assertStringContainsString('Saved reconstruction is out of date.', $html);
        $this->assertStringNotContainsString('data-profile-match-pair', $html);
        $data['profileMatchProgress']['reason'] = null;
        $data['profileMatchProgress']['rows'] = [array_replace($row, ['delta' => 5]), array_replace($row, ['reason' => 'Comparison context changed.'])];
        $html = view('frontend.my-tennis.profile-match-progress', $data)->render();
        $this->assertStringNotContainsString('data-profile-match-pair', $html);
        $this->assertStringContainsString('Comparison context changed.', $html);
    }

    public function test_profile_non_plus_preview_does_not_load_private_progress(): void
    {
        $player = $this->player();
        $this->actingAs(User::factory()->create());
        // Use the canonical super-user role, not a submitted preview flag, to authorize marketing preview.
        $user = auth()->user();
        $user->assignRole(\Spatie\Permission\Models\Role::findOrCreate('super-user'));
        $this->mock(\App\Services\Performance\PlayerMatchProgress::class)->shouldNotReceive('forPlayer', 'build');
        $this->mock(PlayerRatingHistoryService::class)->shouldNotReceive('forPlayer', 'recordSource');
        $this->get(route('my.tennis', ['player' => $player->id, 'tab' => 'profile', 'preview_non_plus' => 1]))->assertOk()->assertDontSee('data-profile-match-progress', false);
    }

}
