<?php

namespace Tests\Feature;

use App\Models\{Player, PlayerPlusAccessOverride, User};
use App\Services\Performance\PlayerRatingHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MyTennisDashboardIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class, \App\Http\Middleware\EnsurePlayerProfileUpdated::class]);
        $this->mock(PlayerRatingHistoryService::class)->shouldReceive('forPlayer')->andReturn(['series' => [], 'reason' => 'Saved results are pending.']);
    }

    private function export(string $name, $response): void
    {
        if (getenv('CT_DASHBOARD_QA_HTML') !== '1') return;
        File::ensureDirectoryExists(storage_path('framework/testing/dashboard-integration-qa'));
        file_put_contents(storage_path('framework/testing/dashboard-integration-qa/'.$name.'.html'), $response->getContent());
    }

    private function assertTabTargets(string $html): void
    {
        preg_match_all('/data-bs-target="#(my-tennis-[^"]+)"/', $html, $targets);
        $this->assertNotEmpty($targets[1]);
        foreach ($targets[1] as $target) $this->assertStringContainsString('id="'.$target.'"', $html);
    }

    public function test_empty_account_has_no_dead_history_tab_and_cannot_open_private_plus_views(): void
    {
        $this->actingAs(User::factory()->create());
        $response = $this->get(route('my.tennis'))->assertOk()->assertDontSee('id="my-tennis-history-tab"', false);
        $this->assertTabTargets($response->getContent());
        $this->export('empty', $response);
        $this->get(route('my.tennis.record', Player::factory()->create()))->assertForbidden();
    }

    public function test_linked_standard_and_plus_views_share_player_identity_but_not_private_features(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id, 'name' => 'Example', 'surname' => 'Player']);
        $this->actingAs($user);
        $standard = $this->get(route('my.tennis', ['player' => $player->id]))->assertOk()
            ->assertSee('Your dashboard and Player Plus')->assertSee('Preview a rating')->assertSee('data-plus-locked-navigation', false)
            ->assertSee('data-locked-rating-snapshot', false)->assertDontSee('data-rating-mini-trend', false)
            ->assertDontSee(route('my.tennis.record', $player), false);
        $this->assertTabTargets($standard->getContent());
        $dom = new \DOMDocument; @$dom->loadHTML($standard->getContent()); $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//*[@data-player-profile-header]//select[@id="my-tennis-player"]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-player-profile-header]//*[@id="my-tennis-tabs"]')->length);
        $this->export('standard', $standard);
        $this->get(route('my.tennis.record', $player))->assertForbidden();
        PlayerPlusAccessOverride::create(['player_id' => $player->id, 'mode' => 'grant', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $plus = $this->get(route('my.tennis', ['player' => $player->id]))->assertOk()->assertSee('aria-label="Player Plus views"', false)
            ->assertDontSee('Coming Soon')->assertSee('Latest rating and trend')->assertSee(route('player-plus.player', $player), false)
            ->assertDontSee('data-plus-locked-navigation', false)->assertDontSee('data-locked-rating-snapshot', false);
        foreach (['my.tennis.record', 'my.tennis.pathway', 'my.tennis.event-history'] as $route) $plus->assertSee(route($route, $player), false);
        $this->assertTabTargets($plus->getContent());
        $this->export('plus', $plus);
        $this->get(route('my.tennis', ['player' => Player::factory()->create()->id]))->assertOk()->assertDontSee('aria-label="Player Plus views"', false);
    }

    public function test_admin_unlinked_preview_identifies_the_preview_player_in_the_selector(): void
    {
        Role::findOrCreate('super-user', 'web');
        $admin = User::factory()->create()->assignRole('super-user');
        Player::factory()->create(['userId' => $admin->id]);
        $preview = Player::factory()->create(['name' => 'Preview', 'surname' => 'Player']);
        $response = $this->actingAs($admin)->get(route('my.tennis', ['player' => $preview->id]))->assertOk()
            ->assertSee('value="'.$preview->id.'" selected', false)->assertSee('Admin preview')->assertDontSee('id="my-tennis-history-tab"', false);
        $this->assertTabTargets($response->getContent());
        $this->export('admin-preview', $response);
    }
}
