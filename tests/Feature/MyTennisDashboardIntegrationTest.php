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
            ->assertSee('Date of birth')->assertSee('Edit profile')->assertSee('Preview Plus')
            ->assertDontSee('data-plus-locked-navigation', false)->assertDontSee('data-rating-mini-trend', false)
            ->assertDontSee(route('my.tennis.record', $player), false);
        foreach (['rating', 'record', 'opponents', 'pathway'] as $feature) {
            $standard->assertSee(route('my.tennis.feature-preview', ['player' => $player, 'feature' => $feature]), false);
        }
        $this->assertTabTargets($standard->getContent());
        $dom = new \DOMDocument; @$dom->loadHTML($standard->getContent()); $xpath = new \DOMXPath($dom);
        $this->assertSame(1, $xpath->query('//*[@data-player-profile-header]//select[@id="my-tennis-player"]')->length);
        $this->assertSame(1, $xpath->query('//*[@data-player-profile-header]//*[@id="my-tennis-tabs"]')->length);
        $this->export('standard', $standard);
        if (getenv('CT_DASHBOARD_QA_HTML') === '1') {
            $this->export('plus-preview', $this->get(route('my.tennis.feature-preview', ['player' => $player, 'feature' => 'rating']))->assertOk());
        }
        $this->get(route('my.tennis.record', $player))->assertForbidden();
        PlayerPlusAccessOverride::create(['player_id' => $player->id, 'mode' => 'grant', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $plus = $this->get(route('my.tennis', ['player' => $player->id]))->assertOk()
            ->assertSee('Rating not available yet')->assertSee('Edit profile')
            ->assertDontSee('data-plus-locked-navigation', false)->assertDontSee('data-locked-rating-snapshot', false);
        foreach (['my.tennis.record', 'my.tennis.pathway'] as $route) $plus->assertSee(route($route, $player), false);
        $this->assertTabTargets($plus->getContent());
        $this->export('plus', $plus);
        $this->export('rating', $this->get(route('my.tennis', ['player' => $player->id, 'tab' => 'rating']))->assertOk()
            ->assertSee('data-rating-url', false)->assertSee('id="ctr-panel-progress"', false)
            ->assertSee(route('my.tennis.event-history', $player), false)
            ->assertDontSee('data-rating-placeholder', false));
        $this->export('rating-fragment', $this->get(route('my.tennis.rating', $player), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertSee('data-profile-rating-update', false)
            ->assertSee(route('my.tennis.event-history', $player), false)
            ->assertDontSee('aria-label="Player Plus views"', false));
        $this->get(route('my.tennis', ['player' => $sibling->id]))->assertOk()
            ->assertSee('second-player@example.test')->assertSee('Preview Plus')
            ->assertDontSee(route('my.tennis.record', $sibling), false);
        $this->export('manage', $this->get(route('my.tennis', ['tab' => 'manage']))->assertOk()
            ->assertSee('Manage player profiles')->assertSee('data-player-row="'.$sibling->id.'"', false)
            ->assertSee('Restore existing access')->assertDontSee('data-admin-profile-link disabled', false));
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
        $this->get(route('my.tennis', ['player' => $preview->id, 'tab' => 'manage']))->assertOk()
            ->assertSee('Link and open profile')->assertSee('data-admin-profile-link disabled', false);
    }

    public function test_bare_landing_opens_profile_for_an_eligible_remembered_player(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id, 'name' => 'Taylor']);
        $standard = Player::factory()->create(['userId' => $user->id, 'name' => 'Avery']);
        $this->actingAs($user);
        PlayerPlusAccessOverride::create(['player_id' => $player->id, 'mode' => 'grant', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);

        $this->get(route('my.tennis'))->assertOk()->assertViewIs('frontend.my-tennis.index')->assertSee('Edit profile');
        $this->get(route('my.tennis', ['player' => $player, 'tab' => 'profile']))->assertOk()->assertSee('Edit profile');
        $progress = route('my.tennis.pathway', $player);
        $this->get(route('my.tennis'))->assertOk()
            ->assertViewHas('workspaceTab', 'profile')
            ->assertViewHas('selectedPlayer', fn (Player $selected) => $selected->is($player))
            ->assertHeader('Cache-Control', 'no-store, private');
        $response = $this->get($progress)->assertOk()
            ->assertViewIs('frontend.my-tennis.pathway-page')->assertViewHas('ratingPlayer', fn (Player $selected) => $selected->is($player))
            ->assertHeader('Cache-Control', 'no-store, private');
        $dom = new \DOMDocument;
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame('Tournament options', trim($xpath->evaluate('string(//*[@id="my-tennis-tabs"]/*[@aria-current="page"])')));
        $this->assertSame(1, $xpath->query('//*[@data-player-pathway]//ol[@data-pathway-snapshot and not(ancestor::details)]')->length);
        $this->export('progress-entry', $response);

        $this->get(route('my.tennis', ['tab' => 'activity']))->assertOk()->assertViewHas('workspaceTab', 'activity');
        $this->get(route('my.tennis', ['player' => $standard]))->assertOk()->assertViewHas('workspaceTab', 'profile');
        $this->get(route('my.tennis'))->assertOk()->assertViewHas('workspaceTab', 'profile');
    }

    public function test_ajax_tabs_return_only_the_workspace_and_keep_existing_access_checks(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id]);
        $other = Player::factory()->create();
        $headers = ['X-My-Tennis-Navigation' => '1'];
        $this->actingAs($user);
        $this->get(route('my.tennis.record', $player), $headers)->assertForbidden();
        PlayerPlusAccessOverride::create(['player_id' => $player->id, 'mode' => 'grant', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $this->mock(PlayerRatingHistoryService::class)->shouldReceive('forPlayer')->andReturn([
            'reason' => null, 'series' => [], 'as_of' => '2026-11-03', 'ball_stage' => 'standard',
            'points' => [['index' => 11, 'date' => '2026-11-03', 'score' => 91.1]],
        ]);
        $this->app->make(PlayerRatingHistoryService::class)->shouldReceive('recordSource')->twice()->andReturn([
            'reason' => null, 'as_of' => '2026-11-03', 'built_at' => '2026-11-03', 'evidence' => [
                ['source_id' => 'match:1', 'kind' => 'match', 'months' => 1, 'event_id' => 1,
                    'event' => 'Example Open', 'date' => '2026-10-01', 'date_basis' => 'event start proxy',
                    'players' => [$player->id, $other->id], 'names' => [$player->full_name, $other->full_name],
                    'winner_side' => 1, 'score' => '6-3 6-4'],
            ],
        ]);
        $this->mock(\App\Services\Performance\PlayerEventRatingHistory::class)->shouldReceive('forPlayer')->once()
            ->andReturn(['reason' => 'No event ratings.', 'events' => [], 'pages' => 1]);
        $this->mock(\App\Services\Performance\PlayerEventRatingHistoryRefresh::class)->shouldReceive('status')->once()
            ->andReturn(['state' => 'idle', 'message' => 'No update requested.']);

        $tabs = [];
        foreach (['profile', 'activity', 'rating', 'manage'] as $tab) {
            $tabs[$tab] = route('my.tennis', ['player' => $player, 'tab' => $tab]);
        }
        $tabs += [
            'record' => route('my.tennis.record', $player),
            'opponents' => route('my.tennis.record', ['player' => $player, 'section' => 'opponents']),
            'pathway' => route('my.tennis.pathway', $player),
            'events' => route('my.tennis.event-history', $player),
        ];
        $this->export('ajax-entry', $this->get($tabs['profile'])->assertOk());
        foreach ($tabs as $name => $url) {
            $response = $this->get($url, $headers)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
                ->assertSee('data-my-tennis-workspace', false)->assertSee('data-my-tennis-link', false)
                ->assertDontSee('my-tennis-navigation.js', false)
                ->assertSee('id="my-tennis-player"', false)->assertSee('91.1');
            if ($name === 'profile') $response->assertSee($player->getProfileStatus()['message']);
            if ($name === 'record') $response->assertSee('Recorded matches by month')->assertSee('record_month=2026-10', false);
            if ($name === 'opponents') $response->assertDontSee('Recorded matches by month');
            $this->export('ajax-'.$name, $response);
        }
        $this->get(route('my.tennis.pathway', $other), $headers)->assertForbidden();
    }

    public function test_profile_remembers_an_authorized_selection_and_activity_loads_separately(): void
    {
        $user = User::factory()->create();
        $first = Player::factory()->create(['userId' => $user->id, 'name' => 'Avery']);
        $other = Player::factory()->create(['userId' => $user->id, 'name' => 'Taylor']);
        $this->actingAs($user);
        $this->get(route('my.tennis', ['player' => $other->id]))->assertOk();
        $this->get(route('my.tennis', ['player' => $first->id, 'tab' => 'activity']), [
            'Purpose' => 'prefetch', 'X-My-Tennis-Navigation' => '1',
        ])->assertOk()->assertSessionHas('my_tennis.selected_player', $other->id);
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
