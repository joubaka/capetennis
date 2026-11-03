<?php

namespace Tests\Feature;

use App\Http\Controllers\Backend\PlayerPerformancePilotController;
use App\Models\Player;
use App\Services\Performance\{PlayerPerformancePilotService, PlayerSharedAbilityService};
use Illuminate\Http\Request;
use Mockery;
use Tests\TestCase;

class PlayerRatingDetailContextTest extends TestCase
{
    private function rating(float $score): array
    {
        return ['score' => $score, 'component' => 'group-a', 'cohort' => 'singles', 'comparable' => true,
            'confidence_label' => 'Low', 'last_played' => '2026-10-01'];
    }

    private function detail(array $query = [], ?array $override = null): array
    {
        $player = new Player; $player->setRawAttributes(['id' => 42, 'name' => 'Example', 'surname' => 'Player']);
        $ability = $override ?? ['reason' => null, 'headline' => $this->rating(43.9), 'all_age' => $this->rating(43.9),
            'ball_ratings' => ['red' => $this->rating(66.6), 'orange' => $this->rating(55.5), 'standard' => $this->rating(44.4)],
            'built_at' => '2026-11-03', 'cohorts' => collect()];
        $shared = Mockery::mock(PlayerSharedAbilityService::class); $shared->shouldReceive('forPlayer')->once()->with($player)->andReturn($ability);
        $this->app->instance(PlayerSharedAbilityService::class, $shared);
        $performance = Mockery::mock(PlayerPerformancePilotService::class); $performance->shouldReceive('forPlayer')->once()->with($player)->andReturn(['as_of' => '2026-11-03']);
        $request = Request::create('/player-performance/players/42', 'GET', $query);
        $this->app->instance('request', $request);
        return app(PlayerPerformancePilotController::class)->show($request, $player, $performance)->getData();
    }

    public function test_directory_links_and_detail_select_the_same_independent_rating(): void
    {
        foreach (['red' => 66.6, 'orange' => 55.5, 'standard' => 44.4, 'overall' => 43.9] as $stage => $score) {
            $data = $this->detail(['ball_type' => $stage]);
            $this->assertSame($stage, $data['ballType']); $this->assertSame($score, $data['selectedRating']['score']);
            $row = ['id' => 42, 'name' => 'Example Player', 'position' => 1, 'rating' => $data['selectedRating'],
                'awaiting_calibration' => false, 'age_group' => 'U9', 'gender' => 'Boys', 'ratings' => [$data['selectedRating']]];
            $html = view('backend.player-performance.partials.ctr-directory-row', ['player' => $row, 'ballType' => $stage,
                'ballLevel' => $stage !== 'overall', 'snapshot' => ['reason' => null], 'unified' => true,
                'diagnosticOrder' => false, 'ageOrder' => false])->render();
            $this->assertStringContainsString('ball_type='.$stage, $html);
            $this->assertStringContainsString(number_format($score, 1), $html);
            if ($stage !== 'overall') {
                $card = view('backend.player-performance.selected-rating', $data)->render();
                $this->assertStringContainsString('data-ctr-stage="'.$stage.'"', $card);
                $this->assertStringContainsString(number_format($score, 1), $card);
                $this->assertStringNotContainsString('43.9', $card);
            }
        }
        $this->assertSame('overall', $this->detail()['ballType']);
    }

    public function test_missing_stage_never_substitutes_overall_and_revoked_source_never_displays_score(): void
    {
        $ability = ['reason' => null, 'all_age' => $this->rating(43.9), 'ball_ratings' => [], 'cohorts' => collect(), 'built_at' => '2026-11-03'];
        $data = $this->detail(['ball_type' => 'red'], $ability);
        $this->assertNull($data['selectedRating']);
        $html = view('backend.player-performance.selected-rating', $data)->render();
        $this->assertStringContainsString('Red ball CTR unavailable', $html); $this->assertStringNotContainsString('43.9', $html);
        $ability['reason'] = 'Source publication changed'; $ability['ball_ratings']['red'] = $this->rating(66.6);
        $html = view('backend.player-performance.selected-rating', $this->detail(['ball_type' => 'red'], $ability))->render();
        $this->assertStringContainsString('Source publication changed', $html); $this->assertStringNotContainsString('66.6', $html);
    }

    public function test_explicit_local_group_preserves_score_labels_and_rejects_foreign_component(): void
    {
        $rating = $this->rating(66.6); $rating['comparable'] = false; $rating['within_group_score'] = 66.6;
        $ability = ['reason' => null, 'all_age' => null, 'ball_ratings' => ['red' => $rating], 'cohorts' => collect(), 'built_at' => '2026-11-03'];
        $data = $this->detail(['ball_type' => 'red', 'comparison_group' => 'group-a'], $ability);
        $html = view('backend.player-performance.selected-rating', $data)->render();
        $this->assertStringContainsString('within-group only', $html); $this->assertStringContainsString('66.6', $html);
        $this->assertStringContainsString('comparison_group=group-a', $html);
        $this->assertNull($this->detail(['ball_type' => 'red', 'comparison_group' => 'foreign'], $ability)['selectedRating']);
    }

    public function test_unsupported_context_is_rejected_before_services_and_route_remains_super_only(): void
    {
        $route = app('router')->getRoutes()->getByName('backend.player-performance.show');
        $this->assertContains('role:super-user', $route->gatherMiddleware());
        $this->assertContains('auth', $route->gatherMiddleware());
        $performance = Mockery::mock(PlayerPerformancePilotService::class); $performance->shouldNotReceive('forPlayer');
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(PlayerPerformancePilotController::class)->show(Request::create('/', 'GET', ['ball_type' => 'purple']), new Player, $performance);
    }
}
