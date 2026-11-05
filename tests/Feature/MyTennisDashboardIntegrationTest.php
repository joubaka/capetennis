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
        $sibling = Player::factory()->create(['userId' => $user->id, 'email' => 'second-player@example.test']);
        $this->actingAs($user);
        $standard = $this->get(route('my.tennis', ['player' => $player->id]))->assertOk()
            ->assertSee('Date of birth')->assertSee('Edit profile')->assertSee('Explore Player Plus')
            ->assertDontSee('data-plus-locked-navigation', false)->assertDontSee('data-rating-mini-trend', false)
            ->assertDontSee(route('my.tennis.record', $player), false);
        $this->assertTabTargets($standard->getContent());
        $dom = new \DOMDocument; @$dom->loadHTML($standard->getContent()); $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//*[@data-player-profile-header]//select[@id="my-tennis-player"]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-player-profile-header]//*[@id="my-tennis-tabs"]')->length);
        $this->export('standard', $standard);
        $this->get(route('my.tennis.record', $player))->assertForbidden();
        PlayerPlusAccessOverride::create(['player_id' => $player->id, 'mode' => 'grant', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $plus = $this->get(route('my.tennis', ['player' => $player->id]))->assertOk()
            ->assertSee('Rating not available yet')->assertSee('Edit profile')
            ->assertDontSee('data-plus-locked-navigation', false)->assertDontSee('data-locked-rating-snapshot', false);
        foreach (['my.tennis.record', 'my.tennis.pathway'] as $route) $plus->assertSee(route($route, $player), false);
        $this->assertTabTargets($plus->getContent());
        $this->export('plus', $plus);
        $this->export('rating', $this->get(route('my.tennis', ['player' => $player->id, 'tab' => 'rating']))->assertOk()
            ->assertSee('data-rating-url', false)->assertSee(route('my.tennis.event-history', $player), false));
        $this->export('rating-fragment', $this->get(route('my.tennis.rating', $player), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('data-profile-rating-update', false)
            ->assertDontSee('aria-label="Player Plus views"', false));
        $this->get(route('my.tennis', ['player' => $sibling->id]))->assertOk()
            ->assertSee('second-player@example.test')->assertSee('Explore Player Plus')
            ->assertDontSee(route('my.tennis.record', $sibling), false);
        $this->export('manage', $this->get(route('my.tennis', ['tab' => 'manage']))->assertOk()
            ->assertSee('Manage player profiles')->assertSee('data-player-row="'.$sibling->id.'"', false));
        $this->get(route('my.tennis', ['player' => Player::factory()->create()->id]))->assertOk()->assertDontSee('Edit profile');
    }

    public function test_admin_unlinked_preview_identifies_the_preview_player_in_the_selector(): void
    {
        Role::findOrCreate('super-user', 'web');
        $admin = User::factory()->create()->assignRole('super-user');
        Player::factory()->create(['userId' => $admin->id]);
        $preview = Player::factory()->create(['name' => 'Preview', 'surname' => 'Player', 'email' => 'private-preview@example.test']);
        $response = $this->actingAs($admin)->get(route('my.tennis', ['player' => $preview->id]))->assertOk()
            ->assertSee('value="'.$preview->id.'" selected', false)->assertSee('Admin preview')->assertDontSee('id="my-tennis-history-tab"', false)
            ->assertDontSee('private-preview@example.test')->assertDontSee('Edit profile');
        $this->assertTabTargets($response->getContent());
        $this->export('admin-preview', $response);
    }

    public function test_profile_remembers_an_authorized_selection_and_activity_loads_separately(): void
    {
        $user = User::factory()->create();
        $first = Player::factory()->create(['userId' => $user->id, 'name' => 'Avery']);
        $other = Player::factory()->create(['userId' => $user->id, 'name' => 'Taylor']);
        $this->actingAs($user);
        $this->get(route('my.tennis', ['player' => $other->id]))->assertOk();
        $profile = $this->get(route('my.tennis'))->assertOk();
        $this->assertSame($other->id, $profile->viewData('selectedPlayer')->id);
        $this->assertArrayNotHasKey('upcomingMatchPage', $profile->original->getData());
        $activity = $this->get(route('my.tennis', ['tab' => 'activity']))->assertOk();
        $this->assertSame($other->id, $activity->viewData('selectedPlayer')->id);
        $this->assertStringContainsString('tab=activity', $activity->viewData('upcomingMatchPage')->url(2));
        $this->export('activity', $activity);
        $other->update(['userId' => null]);
        $this->assertSame($first->id, $this->get(route('my.tennis'))->assertOk()->viewData('selectedPlayer')->id);
    }

    public function test_profile_edit_returns_to_selected_player_without_consuming_registration_return(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id]);
        $return = route('my.tennis', ['player' => $player->id]);
        $this->actingAs($user)->withSession(['url.intended' => '/events']);
        $this->get(route('player.profile.edit', ['player' => $player, 'from' => 'my-tennis']))
            ->assertOk()->assertSee($return, false)->assertSessionHas('url.intended', '/events');
        $this->put(route('player.profile.update', $player), [
            'from' => 'my-tennis', 'name' => $player->name, 'surname' => $player->surname,
            'cellNr' => '0821234567', 'dateOfBirth' => '2013-06-19', 'gender' => 'Female',
        ])->assertRedirect($return)->assertSessionHas('url.intended', '/events');
        $this->assertDatabaseHas('players', ['id' => $player->id, 'dateOfBirth' => '2013-06-19']);
        $this->actingAs(User::factory()->create())
            ->get(route('player.profile.edit', ['player' => $player, 'from' => 'my-tennis']))->assertForbidden();
    }
}
