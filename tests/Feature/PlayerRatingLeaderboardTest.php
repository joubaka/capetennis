<?php

namespace Tests\Feature;

use App\Models\{Category, CategoryEvent, CategoryEventRegistration, Event, NoProfileTeamPlayer, Player, Registration, Team, TeamRegion, User};
use App\Services\Performance\{PlayerAbilitySnapshotStore, PlayerRatingLeaderboardService, PlayerSharedAbilityService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerRatingLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
    }

    private function field(Event $event, string $label = 'U/12 Boys A'): CategoryEvent
    {
        return CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => $label])->id]);
    }

    private function entry(CategoryEvent $field, Player $player): void
    {
        $registration = Registration::factory()->create();
        $registration->players()->attach($player);
        CategoryEventRegistration::factory()->create(['category_event_id' => $field->id, 'registration_id' => $registration->id]);
    }

    private function saved(array $ratings = []): void
    {
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn([
            'cohorts' => ['u12 boys' => []], 'names' => [], 'reason' => null, 'built_at' => '2026-10-09 08:00:00', 'snapshot_as_of' => '2026-10-09', 'snapshot_stale' => false,
        ]);
        $mock = $this->mock(PlayerSharedAbilityService::class)->makePartial();
        $mock->shouldReceive('badgeSnapshot')->andReturn($ratings);
        $mock->shouldNotReceive('calculateSnapshot');
        $mock->shouldNotReceive('forPlayer');
    }

    private function estimate(float $score, string $group = 'first', string $cohort = 'u12 boys'): array
    {
        return ['cohort' => $cohort, 'component' => $group, 'score' => $score, 'confidence_label' => 'Medium',
            'confidence_explanation' => 'Strength and freshness of evidence, not rating accuracy.', 'last_played' => '2026-09-01'];
    }

    public function test_routes_deny_guests_and_non_super_users_before_loading_snapshot(): void
    {
        $event = Event::factory()->create();
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldNotReceive('current');
        foreach ([route('backend.player-performance.ratings'), route('backend.player-performance.event-ratings', $event)] as $url) {
            $this->getJson($url)->assertUnauthorized();
        }
        Role::findOrCreate('admin', 'web');
        $this->actingAs(User::factory()->create()->assignRole('admin'));
        foreach ([route('backend.player-performance.ratings'), route('backend.player-performance.event-ratings', $event)] as $url) {
            $this->getJson($url)->assertForbidden();
        }
    }

    public function test_event_membership_deduplicates_and_excludes_other_events_preserving_unrated_rosters(): void
    {
        $this->admin();
        $event = Event::factory()->create(['eventType' => 6]);
        $field = $this->field($event);
        $player = Player::factory()->create(['name' => 'Recorded', 'surname' => 'Player']);
        $foreign = Player::factory()->create(['name' => 'Foreign', 'surname' => 'Player']);
        $this->entry($field, $player);
        $this->entry($this->field(Event::factory()->create(['eventType' => 6])), $foreign);
        $team = Team::factory()->create(['category_event_id' => $field->id]);
        $team->players()->attach($player, ['rank' => 1]);
        NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => 'Imported', 'surname' => 'Unrated', 'rank' => 2, 'pay_status' => 0]);
        NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => 'Stale', 'surname' => 'Imported name', 'rank' => 3, 'pay_status' => 0, 'player_profile' => $player->id]);
        NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => 'Dangling', 'surname' => 'Profile', 'rank' => 4, 'pay_status' => 0, 'player_profile' => 999999]);
        $this->saved([$player->id => [$this->estimate(62)]]);
        $rows = app(PlayerRatingLeaderboardService::class)->build($event)['rows'];
        $this->assertCount(3, $rows);
        $this->assertSame([$player->id, null, null], $rows->pluck('player_id')->all());
        $response = $this->get(route('backend.player-performance.event-ratings', $event))->assertOk()->assertSee('Recorded Player')
            ->assertSee('Imported Unrated')->assertDontSee('Stale Imported name')->assertDontSee('Foreign Player')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertNull($rows->firstWhere('name', 'Dangling Profile')['rating']);
        if (getenv('CT_LEADERBOARD_QA_HTML') === '1') {
            file_put_contents(storage_path('framework/testing/leaderboard-qa.html'), $response->getContent());
        }
    }

    public function test_site_requires_exact_cohort_and_orders_only_within_comparison_groups(): void
    {
        $this->admin();
        $field = $this->field(Event::factory()->create(['eventType' => 6]));
        $players = collect(['Low', 'High', 'Separate', 'Unrated'])->map(fn ($name) => Player::factory()->create(['name' => $name, 'surname' => 'Test']));
        foreach ($players as $player) { $this->entry($field, $player); }
        $this->saved([
            $players[0]->id => [$this->estimate(20)], $players[1]->id => [$this->estimate(70)],
            $players[2]->id => [$this->estimate(99, 'second'), $this->estimate(3, 'other', 'u10 boys')],
        ]);
        $url = route('backend.player-performance.ratings');
        $this->get($url)->assertOk()->assertSee('Choose a cohort to see')->assertDontSee('High Test');
        $this->get($url.'?cohort=u12%20boys')->assertOk()->assertSeeInOrder(['High Test', 'Low Test', 'Separate Test', 'Unrated Test']);
        $rows = app(PlayerRatingLeaderboardService::class)->build(null, 'u12 boys')['rows'];
        $this->assertSame([1, 2, 1, null], $rows->pluck('position')->all());
        $this->getJson($url.'?cohort=u12')->assertUnprocessable();
        $this->get($url.'?cohort=u12%20boys&search=high')->assertOk()->assertSee('High Test')->assertDontSee('Low Test');
    }

    public function test_pagination_preserves_within_group_positions_and_members_without_snapshot(): void
    {
        $this->admin();
        $field = $this->field(Event::factory()->create(['eventType' => 6]));
        $estimates = [];
        foreach (range(1, 27) as $i) {
            $player = Player::factory()->create(['name' => 'Player', 'surname' => sprintf('%02d', $i)]);
            $this->entry($field, $player);
            $estimates[$player->id] = [$this->estimate(100 - $i)];
        }
        $this->saved($estimates);
        $response = $this->get(route('backend.player-performance.ratings').'?cohort=u12%20boys&page=2');
        $response->assertOk()->assertSee('Player 26')->assertSee('Player 27')->assertDontSee('Player 01');
        $this->assertSame([26, 27], $response->viewData('players')->getCollection()->pluck('position')->all());
        $searched = $this->get(route('backend.player-performance.ratings').'?cohort=u12%20boys&search=26');
        $this->assertSame([26], $searched->viewData('players')->getCollection()->pluck('position')->all());
    }

    public function test_withheld_snapshot_never_exposes_saved_badges_but_keeps_members_visible(): void
    {
        $this->admin();
        $event = Event::factory()->create(['eventType' => 6]);
        $player = Player::factory()->create(['name' => 'Visible', 'surname' => 'Member']);
        $this->entry($this->field($event), $player);
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn([
            'cohorts' => [], 'names' => [], 'reason' => 'Saved ability estimates are withheld because their source publication or context changed.',
            'built_at' => 'Not yet updated', 'snapshot_as_of' => null, 'snapshot_stale' => true,
            'badge_players' => [$player->id => [$this->estimate(99.9)]],
        ]);
        $this->partialMock(PlayerSharedAbilityService::class)->shouldNotReceive('calculateSnapshot');
        $this->get(route('backend.player-performance.event-ratings', $event))->assertOk()->assertSee('Visible Member')
            ->assertSee('Unrated')->assertSee('Saved ability estimates are withheld')->assertDontSee('99.9/100');
    }

    public function test_legacy_teams_require_event_exclusive_regions_and_unresolved_selection_is_available(): void
    {
        $this->admin();
        $event = Event::factory()->create(['eventType' => 6]);
        $other = Event::factory()->create(['eventType' => 6]);
        $exclusive = TeamRegion::create(['region_name' => 'Exclusive']);
        $shared = TeamRegion::create(['region_name' => 'Shared']);
        $event->regions()->attach([$exclusive->id, $shared->id]);
        $other->regions()->attach($shared->id);
        foreach ([[$exclusive, 'Legacy included'], [$shared, 'Shared excluded']] as [$region, $name]) {
            $team = Team::factory()->create(['region_id' => $region->id, 'category_event_id' => null]);
            $player = Player::factory()->create(['name' => $name, 'surname' => 'Member']);
            $team->players()->attach($player, ['rank' => 1]);
        }
        $foreignTeam = Team::factory()->create(['category_event_id' => $this->field($other)->id]);
        $foreignPlayer = Player::factory()->create(['name' => 'Foreign roster', 'surname' => 'Member']);
        $foreignTeam->players()->attach($foreignPlayer, ['rank' => 1]);
        $this->saved();
        $url = route('backend.player-performance.event-ratings', $event);
        $this->get($url)->assertOk()->assertSee('Legacy included Member')->assertDontSee('Shared excluded Member')->assertDontSee('Foreign roster Member');
        $this->get($url.'?cohort='.urlencode('Unresolved legacy category'))->assertOk()->assertSee('Legacy included Member')->assertSee('Unrated');
    }
}
