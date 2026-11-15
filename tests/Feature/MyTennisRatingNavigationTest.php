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
        $this->mock(\App\Services\Performance\PlayerAbilitySnapshotStore::class)->shouldReceive('current')
            ->andReturn(['reason' => 'No saved snapshot in this test.']);
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
    public function test_rating_routes_render_actual_public_positions_instead_of_empty_filter_context(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-15'));
        $player = $this->member();
        $player->update(['name' => 'Selected', 'surname' => 'Fictional', 'dateOfBirth' => '2015-01-01', 'gender' => 1]);
        $peer = Player::factory()->create(['name' => 'Public', 'surname' => 'Peer', 'dateOfBirth' => '2015-02-01', 'gender' => 1]);
        $private = Player::factory()->create(['name' => 'Hidden', 'surname' => 'Peer', 'dateOfBirth' => '2015-02-01', 'gender' => 1]);
        foreach ([$player, $peer, $private] as $participant) {
            $event = \App\Models\Event::factory()->create(['published' => $participant->id !== $private->id]);
            $category = \App\Models\Category::create(['name' => 'U/11 Boys']);
            $field = \App\Models\CategoryEvent::create(['event_id' => $event->id, 'category_id' => $category->id]);
            $registration = \App\Models\Registration::factory()->create();
            $registration->players()->attach($participant->id);
            \Illuminate\Support\Facades\DB::table('category_event_registrations')->insert([
                'category_event_id' => $field->id, 'registration_id' => $registration->id, 'payment_status_id' => 0, 'status' => 'active',
            ]);
            $draw = \App\Models\Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $field->id, 'published' => true]);
            \Illuminate\Support\Facades\DB::table('fixtures')->insert(['draw_id' => $draw->id, 'registration1_id' => $registration->id]);
        }
        $ratings = [];
        foreach ([$player->id => 80, $peer->id => 90, $private->id => 99] as $id => $score) {
            $ratings[$id] = ['component' => 'shared', 'comparable' => true, 'score' => $score, 'confidence_label' => 'Medium'];
        }
        $snapshot = ['reason' => null, 'snapshot_as_of' => '2026-11-15', 'snapshot_stale' => false,
            'all_age' => ['reference_component' => 'shared', 'components' => ['shared' => ['converged' => true, 'members' => array_keys($ratings)]], 'ratings' => $ratings]];
        $this->mock(\App\Services\Performance\PlayerAbilitySnapshotStore::class)->shouldReceive('current')->times(4)->andReturn($snapshot);
        $this->mock(PlayerRatingHistoryService::class)->shouldReceive('forPlayer')->andReturn(['series' => [], 'reason' => 'Saved history pending.']);
        $direct = route('my.tennis.rating', $player);
        foreach ([
            [route('my.tennis', ['player' => $player->id, 'tab' => 'rating']), []], [$direct, []],
            [$direct, ['X-Requested-With' => 'XMLHttpRequest']],
            [$direct, ['X-Requested-With' => 'XMLHttpRequest', 'X-My-Tennis-Navigation' => '1']],
        ] as [$url, $headers]) {
            $response = $this->get($url, $headers)->assertOk()->assertSee('Your age-group Top 10')->assertSee('Public Peer')
                ->assertDontSee('No comparable position yet.')
                ->assertDontSee('No comparable published ratings for this age group yet.')->assertDontSee('Hidden Peer');
            $this->assertStringContainsString('Rating position #2 of 2', strip_tags($response->getContent()));
            $this->assertSame([$peer->id, $player->id], array_column($response->viewData('ratingTopTen')['lists']['overall']['players'], 'id'));
            $this->assertSame(80.0, $response->viewData('ratingTopTen')['summary']['age']['player']['score']);
        }
    }

    public function test_unavailable_saved_snapshot_remains_an_honest_unavailable_comparison(): void
    {
        $player = $this->member();
        $player->update(['dateOfBirth' => '2015-01-01', 'gender' => 1]);
        $this->mock(PlayerRatingHistoryService::class)->shouldReceive('forPlayer')->andReturn(['series' => [], 'reason' => 'Saved history pending.']);
        $response = $this->get(route('my.tennis.rating', $player), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertSee('Rating comparisons unavailable.')->assertSee('Saved ratings are not available yet.')
            ->assertDontSee('No comparable published ratings for this age group yet.');
        $this->assertSame('Saved ratings are not available yet.', $response->viewData('ratingTopTen')['reason']);
        $this->assertSame([], $response->viewData('ratingTopTen')['lists']['overall']['players']);
    }

    public function test_available_snapshot_with_no_public_comparable_members_keeps_qualified_empty_state(): void
    {
        $player = $this->member();
        $player->update(['dateOfBirth' => '2015-01-01', 'gender' => 1]);
        $this->mock(\App\Services\Performance\PlayerAbilitySnapshotStore::class)->shouldReceive('current')->once()
            ->andReturn(['reason' => null, 'snapshot_as_of' => '2026-11-15', 'all_age' => []]);
        $this->mock(PlayerRatingHistoryService::class)->shouldReceive('forPlayer')->andReturn(['series' => [], 'reason' => 'Saved history pending.']);
        $response = $this->get(route('my.tennis.rating', $player), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertSee('No comparable published ratings for this age group yet.')->assertDontSee('Rating comparisons unavailable.');
        $this->assertNull($response->viewData('ratingTopTen')['reason']);
    }

    public function test_profile_and_activity_never_build_rating_comparison_lists(): void
    {
        $player = $this->member();
        $this->mock(\App\Services\Performance\PlayerRatingTopTen::class)->shouldNotReceive('build');
        $this->mock(PlayerRatingHistoryService::class)->shouldReceive('forPlayer')->andReturn(['series' => [], 'reason' => 'Saved history pending.']);
        $this->app->make(PlayerRatingHistoryService::class)->shouldReceive('recordSource')->once()->andReturn(['reason' => 'Saved record pending.']);
        foreach (['profile', 'activity'] as $tab) {
            $this->get(route('my.tennis', ['player' => $player->id, 'tab' => $tab]))->assertOk();
        }
    }

}
