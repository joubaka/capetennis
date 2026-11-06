<?php

namespace Tests\Feature;

use App\Models\{Player, User};
use App\Services\Performance\{PlayerRatingHistoryService, PlayerPlusRecordService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerPlusFeaturePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_locked_button_opens_its_marketing_page_without_private_reads(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id]);
        $this->mock(PlayerRatingHistoryService::class)->shouldNotReceive('forPlayer');
        $this->mock(PlayerPlusRecordService::class)->shouldNotReceive('forPlayer');
        foreach (['record' => 'Playing Record', 'opponents' => 'Opponent Insights', 'pathway' => 'Tennis pathway', 'events' => 'Past-event ratings', 'rating' => 'Player rating'] as $feature => $title) {
            $response = $this->actingAs($user)->get(route('my.tennis.feature-preview', ['player' => $player, 'feature' => $feature]))
                ->assertOk()->assertSee($title)->assertSee('Blurred example')
                ->assertSee('data-plus-sample', false)
                ->assertSee('View membership and availability')->assertDontSee('data-plus-preview-link', false)
                ->assertHeader('Cache-Control', 'no-store, private');
            $this->assertMatchesRegularExpression('/<article[^>]*data-plus-feature-panel="'.$feature.'"(?![^>]* hidden)[^>]*>/', $response->getContent());
        }
    }

    public function test_preview_rejects_unrelated_players_guests_and_unknown_features(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => 0]);
        $url = route('my.tennis.feature-preview', ['player' => $player, 'feature' => 'record']);
        $this->get($url)->assertRedirect();
        $this->actingAs($user)->get($url)->assertForbidden();
        $this->get(route('my.tennis.feature-preview', ['player' => $player, 'feature' => 'unknown']))->assertNotFound();
    }
}
