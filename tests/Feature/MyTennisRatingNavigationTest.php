<?php

namespace Tests\Feature;

use App\Models\{Player, PlayerPlusAccessOverride, User};
use App\Services\Performance\PlayerRatingHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyTennisRatingNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class, \App\Http\Middleware\EnsurePlayerProfileUpdated::class]);
        $this->mock(\App\Services\Performance\PlayerMatchInfluenceCalculator::class)->shouldNotReceive('calculate');
        $this->mock(\App\Services\Performance\PlayerMatchProgressCalculator::class)->shouldNotReceive('latest', 'pair');
        $topTen = \Mockery::mock(\App\Services\Performance\PlayerRatingTopTen::class, [
            app(\App\Services\Performance\PlayerRatingAccess::class), app(\App\Services\Performance\PlayerAbilitySnapshotStore::class),
        ])->makePartial();
        $topTen->shouldNotReceive('build');
        $this->instance(\App\Services\Performance\PlayerRatingTopTen::class, $topTen);
    }

    private function member(): Player
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id]);
        PlayerPlusAccessOverride::create(['player_id' => $player->id, 'mode' => 'grant', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $this->actingAs($user);
        return $player;
    }

    public function test_full_page_fragment_and_workspace_navigation_share_validated_history_filters(): void
    {
        $player = $this->member();
        $filters = ['month' => '8', 'scope' => 'all', 'scale' => 'full', 'page' => '2', 'rating_section' => 'history'];
        $this->mock(PlayerRatingHistoryService::class)->shouldReceive('forPlayer')->times(5)
            ->withArgs(fn ($actual, $received, $summaryOnly = false) => $actual->id === $player->id && $received == $filters && !$summaryOnly)
            ->andReturn(['series' => [], 'reason' => 'Saved history pending.']);
        $index = route('my.tennis', ['player' => $player->id, 'tab' => 'rating'] + $filters);
        $direct = route('my.tennis.rating', ['player' => $player->id] + $filters);
        foreach ([$index, $direct] as $url) {
            $this->get($url)->assertOk()->assertSee('data-my-tennis-workspace', false)
                ->assertSee('data-rating-workspace-url', false)->assertSee('id="ctr-panel-progress"', false)
                ->assertHeader('Cache-Control', 'no-store, private');
        }
        $this->get($direct, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertSee('data-profile-rating-update', false)->assertDontSee('data-my-tennis-workspace', false)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->get($direct, ['X-My-Tennis-Navigation' => '1', 'X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertSee('data-my-tennis-workspace', false)->assertDontSee('<!DOCTYPE', false);
        $this->get($direct.'&player=999999')->assertOk()->assertViewHas('ratingPlayer', fn ($actual) => $actual->id === $player->id);
    }

    public function test_invalid_filters_and_unlinked_players_never_read_private_history(): void
    {
        $player = $this->member();
        $this->mock(PlayerRatingHistoryService::class)->shouldNotReceive('forPlayer');
        $this->getJson(route('my.tennis.rating', ['player' => $player->id, 'month' => 12, 'scope' => 'invalid', 'scale' => 'invalid', 'page' => 0, 'rating_section' => 'invalid']))
            ->assertUnprocessable()->assertJsonValidationErrors(['month', 'scope', 'scale', 'page', 'rating_section']);
        $this->getJson(route('my.tennis', ['player' => $player->id, 'tab' => 'rating', 'month' => -1]))->assertUnprocessable();
        $other = Player::factory()->create();
        PlayerPlusAccessOverride::create(['player_id' => $other->id, 'mode' => 'grant', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $this->get(route('my.tennis.rating', $other))->assertForbidden();
        $this->get(route('my.tennis.rating', $other), ['X-Requested-With' => 'XMLHttpRequest'])->assertForbidden();
        $this->get(route('my.tennis.rating', $other), ['X-Requested-With' => 'XMLHttpRequest', 'X-My-Tennis-Navigation' => '1'])->assertForbidden();
    }
}
