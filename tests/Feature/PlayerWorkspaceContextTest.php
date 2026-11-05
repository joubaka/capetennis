<?php

namespace Tests\Feature;

use App\Models\{Player, PlayerPlusAccessOverride, User};
use App\Services\{PlayerWorkspaceContext, MyTennisService};
use App\Services\Performance\PlayerRatingHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PlayerWorkspaceContextTest extends TestCase
{
    use RefreshDatabase;

    private function history(): array
    {
        return ['reason' => null, 'as_of' => '2026-11-03', 'points' => [
            ['index' => 11, 'date' => '2026-11-03', 'score' => 66.6, 'group' => 'a', 'confidence_label' => 'Medium']]];
    }

    private function export(string $name, $response): void
    {
        if (getenv('CT_WORKSPACE_CONTEXT_QA') !== '1') return;
        $directory = storage_path('framework/testing/workspace-context-qa');
        \Illuminate\Support\Facades\File::ensureDirectoryExists($directory);
        file_put_contents($directory.'/'.$name.'.html', $response->getContent());
    }

    public function test_authorized_context_reuses_supplied_history_and_selector_excludes_unrelated_players(): void
    {
        $user = User::factory()->create(); $player = Player::factory()->create(['userId' => $user->id]);
        $other = Player::factory()->create(['userId' => 0]);
        PlayerPlusAccessOverride::create(['player_id' => $player->id, 'mode' => 'grant', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $this->mock(PlayerRatingHistoryService::class)->shouldNotReceive('forPlayer');
        $context = app(PlayerWorkspaceContext::class)->forPlayer($user, $player, $this->history());
        $this->assertTrue($context['canViewRating']);
        $this->assertSame(66.6, $context['snapshot']['points'][0]['score']);
        $this->assertSame([$player->id], $context['players']->pluck('id')->all());
        $this->assertFalse($context['players']->contains('id', $other->id));
    }

    public function test_standard_and_sponsored_player_never_read_private_history_and_tampered_context_is_ignored(): void
    {
        $user = User::factory()->create(); $linked = Player::factory()->create(['userId' => $user->id]);
        $sponsored = Player::factory()->create(['userId' => 0]);
        PlayerPlusAccessOverride::create(['player_id' => $sponsored->id, 'mode' => 'grant', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $this->mock(PlayerRatingHistoryService::class)->shouldNotReceive('forPlayer');
        foreach ([$linked, $sponsored] as $player) {
            $context = app(PlayerWorkspaceContext::class)->forPlayer($user, $player);
            $this->assertFalse($context['canViewRating']); $this->assertNull($context['snapshot']);
        }
        $this->assertNull(app(PlayerWorkspaceContext::class)->fromRequest($user, $sponsored->id)['player']);
        $html = Blade::render('<x-player-workspace-header title="Sponsor order" :workspace="$workspace" />', ['workspace' => $context]);
        $this->assertStringNotContainsString('data-rating-snapshot', $html);
        $this->assertStringNotContainsString('data-locked-rating-snapshot', $html);
        $this->assertStringContainsString('action="'.route('my.tennis').'"', $html);
        $this->assertStringContainsString('Open the selected player’s dashboard.', $html);
    }

    public function test_selector_is_bounded_and_keeps_explicit_related_player_outside_first_page(): void
    {
        $user = User::factory()->create(); Player::factory()->count(MyTennisService::PLAYER_LIMIT)->create(['userId' => $user->id, 'name' => 'A']);
        $player = Player::factory()->create(['userId' => $user->id, 'name' => 'ZZZ']);
        $this->mock(PlayerRatingHistoryService::class)->shouldNotReceive('forPlayer');
        $context = app(PlayerWorkspaceContext::class)->forPlayer($user, $player);
        $this->assertCount(MyTennisService::PLAYER_LIMIT, $context['players']);
        $this->assertTrue($context['players']->contains('id', $player->id));
    }

    public function test_public_terms_remain_available_and_standard_feature_query_is_safe_and_useful(): void
    {
        $this->get(route('player-plus.terms'))->assertOk();
        $user = User::factory()->create(); $player = Player::factory()->create(['userId' => $user->id]);
        $this->mock(PlayerRatingHistoryService::class)->shouldNotReceive('forPlayer');
        $response = $this->actingAs($user)->get(route('my.tennis', ['player' => $player->id, 'feature' => 'opponents']))->assertOk();
        $this->export('standard', $response);
        $response->assertSee('data-plus-feature="opponents"', false)->assertSee('id="plus-feature-opponents"', false)
            ->assertSee(route('player-plus.player', $player), false);
        $this->assertMatchesRegularExpression('/id="plus-feature-opponents"[^>]+data-plus-feature-panel="opponents"\s*>/', $response->getContent());
        $this->actingAs($user)->get(route('my.tennis', ['player' => $player->id, 'feature' => ['invalid']]))->assertOk();
    }

    public function test_route_player_is_authoritative_and_pathway_reuses_its_history(): void
    {
        $user = User::factory()->create(); $player = Player::factory()->create(['userId' => $user->id]);
        $other = Player::factory()->create(['userId' => $user->id]);
        PlayerPlusAccessOverride::create(['player_id' => $player->id, 'mode' => 'grant', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $this->mock(PlayerRatingHistoryService::class)->shouldReceive('forPlayer')->once()->withArgs(fn ($actual) => $actual->id === $player->id)->andReturn($this->history());
        $this->mock(\App\Services\Performance\PlayerPathwayService::class)->shouldReceive('summary')->once()->andReturn(\App\Services\Performance\PlayerPathwayService::unrecorded());
        $response = $this->actingAs($user)->get(route('my.tennis.pathway', $player).'?player='.$other->id)->assertOk();
        $this->assertSame($player->id, $response->viewData('workspace')['player']->id);
        $response->assertDontSee('data-rating-snapshot', false)->assertSee('data-workspace-player-selector', false);
        $this->export('pathway', $response);
    }

    public function test_sponsor_order_keeps_immutable_player_and_does_not_gain_snapshot_from_query(): void
    {
        $this->withoutMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class, \App\Http\Middleware\EnsurePlayerProfileUpdated::class]);
        $user = User::factory()->create(); $linked = Player::factory()->create(['userId' => $user->id]);
        $sponsored = Player::factory()->create(['userId' => 0]);
        \App\Models\SiteSetting::set(\App\Services\Performance\PlayerRatingAccess::SETTING, '1');
        $order = app(\App\Services\Performance\PlayerPlusService::class)->start($sponsored, $user, (string) \Illuminate\Support\Str::uuid());
        $this->mock(PlayerRatingHistoryService::class)->shouldNotReceive('forPlayer');
        $response = $this->actingAs($user)->get(route('player-plus.orders.show', ['order' => $order, 'player' => $linked->id]))->assertOk();
        $this->assertSame($sponsored->id, $response->viewData('workspace')['player']->id);
        $this->assertSame($sponsored->id, $order->fresh()->player_id);
        $response->assertDontSee('data-rating-snapshot', false)->assertSee('data-workspace-player-selector', false);
        $this->export('sponsor-order', $response);
        $this->actingAs(User::factory()->create())->get(route('player-plus.orders.show', $order))->assertForbidden();
    }

    public function test_dedicated_pages_share_authorized_header_and_keep_player_in_billing_navigation(): void
    {
        $this->withoutMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class, \App\Http\Middleware\EnsurePlayerProfileUpdated::class]);
        $user = User::factory()->create(); $player = Player::factory()->create(['name' => 'Example', 'surname' => 'Player', 'userId' => $user->id]);
        PlayerPlusAccessOverride::create(['player_id' => $player->id, 'mode' => 'grant', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $this->mock(PlayerRatingHistoryService::class)->shouldReceive('forPlayer')->times(3)->andReturn($this->history());
        $this->mock(\App\Services\Performance\PlayerPlusRecordService::class)->shouldReceive('forPlayer')->once()->andReturn(['reason' => 'Synthetic saved record unavailable.']);
        $this->mock(\App\Services\Performance\PlayerEventRatingHistory::class)->shouldReceive('forPlayer')->once()->andReturn(['reason' => 'Synthetic event history unavailable.', 'events' => [], 'pages' => 1]);
        $this->mock(\App\Services\Performance\PlayerEventRatingHistoryRefresh::class)->shouldReceive('status')->once()->andReturn(['state' => 'idle', 'message' => 'No update requested.']);
        $this->mock(\App\Services\Performance\PlayerPlusReadiness::class)->shouldReceive('forPlayer')->once()->andReturn(null);
        foreach (['record' => route('my.tennis.record', $player), 'events' => route('my.tennis.event-history', $player),
            'offer' => route('player-plus.player', $player), 'orders' => route('player-plus.orders.index', ['player' => $player->id]),
            'terms' => route('player-plus.terms', ['player' => $player->id])] as $name => $url) {
            $response = $this->actingAs($user)->get($url)->assertOk();
            if (in_array($name, ['record', 'events'], true)) {
                $response->assertDontSee('data-rating-snapshot', false);
                $this->assertTrue($response->viewData('workspace')['canViewRating']);
            } else {
                $response->assertSee('data-rating-snapshot', false);
            }
            $response->assertSee('data-workspace-player-selector', false);
            $this->assertSame($player->id, $response->viewData('workspace')['player']->id);
            $this->export($name, $response);
        }
    }

    public function test_entitled_offer_reuses_one_history_read_for_readiness_and_header(): void
    {
        $this->withoutMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class, \App\Http\Middleware\EnsurePlayerProfileUpdated::class]);
        $user = User::factory()->create(); $player = Player::factory()->create(['userId' => $user->id]);
        PlayerPlusAccessOverride::create(['player_id' => $player->id, 'mode' => 'grant', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $this->mock(PlayerRatingHistoryService::class)->shouldReceive('forPlayer')->once()->andReturn($this->history());
        $response = $this->actingAs($user)->get(route('player-plus.player', $player))->assertOk();
        $response->assertSee('data-rating-snapshot', false);
        $this->assertSame(66.6, $response->viewData('workspace')['snapshot']['points'][0]['score']);
    }

    public function test_opponents_loads_schedule_and_record_without_building_header_rating_history(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id, 'name' => 'Jamie', 'surname' => 'Example']);
        PlayerPlusAccessOverride::create(['player_id' => $player->id, 'mode' => 'grant', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $history = $this->mock(PlayerRatingHistoryService::class);
        $history->shouldNotReceive('forPlayer');
        $history->shouldReceive('recordSource')->once()->andReturn(['reason' => null, 'as_of' => '2026-11-03', 'evidence' => [[
            'source_id' => 'match:1', 'kind' => 'match', 'months' => 1, 'event_id' => 1, 'event' => 'Example Open',
            'date' => '2026-10-30', 'date_basis' => 'Match date', 'players' => [$player->id, 700], 'names' => [$player->full_name, 'Taylor Example'],
            'winner_side' => 1, 'score' => '6-3 6-4',
        ]]]);
        $this->mock(\App\Services\Performance\PlayerUpcomingMatchInsights::class)->shouldReceive('forPlayer')->once()->andReturn([
            'rows' => [['date' => '2026-11-07 09:00:00', 'event' => 'Example Saturday Open', 'draw' => 'Singles', 'venue' => 'Example Courts',
                'court' => '2', 'team' => null, 'opponents' => [['id' => 700, 'name' => 'Taylor Example']], 'url' => '/example-draw']],
            'ratings' => [$player->id => ['score' => 74.0, 'confidence' => 'Medium'], 700 => ['score' => 71.5, 'confidence' => 'Low']],
            'total' => 1, 'page' => 1, 'pages' => 1,
        ]);
        $response = $this->actingAs($user)->get(route('my.tennis.record', ['player' => $player, 'section' => 'opponents', 'opponent' => 700]))->assertOk();
        $response->assertSeeInOrder(['Upcoming matches', 'Example Saturday Open', 'Past opponents', '6-3 6-4'])
            ->assertDontSee('data-rating-snapshot', false);
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>Opponents\s*<\/a>/', $response->getContent());
        $this->export('opponents', $response);
    }
}
