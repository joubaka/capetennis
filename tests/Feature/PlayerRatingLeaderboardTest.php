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
            ->assertSee('Imported Unrated')->assertDontSee('Stale Imported name')->assertDontSee('Foreign Player')->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('Private provisional singles ratings')->assertSee('not a win percentage')
            ->assertSee('Confidence describes the strength and freshness of the evidence, not rating accuracy.')
            ->assertSee('Rating evidence')->assertSee('62.0/100')->assertSee('Medium confidence');
        $this->assertNull($rows->firstWhere('name', 'Dangling Profile')['rating']);
        if (getenv('CT_LEADERBOARD_QA_HTML') === '1') {
            file_put_contents(storage_path('framework/testing/leaderboard-qa.html'), $response->getContent());
        }
    }

    public function test_event_region_labels_follow_recorded_rosters_and_do_not_leak_to_other_events_or_site(): void
    {
        $this->admin();
        $event = Event::factory()->create(['eventType' => 6]);
        $field = $this->field($event);
        $player = Player::factory()->create(['name' => 'Regional', 'surname' => 'Player']);
        $individual = Player::factory()->create(['name' => 'Individual', 'surname' => 'Only']);
        $this->entry($field, $player);
        $this->entry($field, $individual);
        foreach (['WP', 'BOL', 'WP', '  '] as $shortName) {
            $region = TeamRegion::create(['region_name' => 'Region '.$shortName, 'short_name' => $shortName]);
            $team = Team::factory()->create(['category_event_id' => $field->id, 'region_id' => $region->id]);
            $team->players()->attach($player, ['rank' => 1]);
            NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => 'Linked', 'surname' => 'Import', 'rank' => 2, 'pay_status' => 0, 'player_profile' => $player->id]);
            if ($shortName === 'BOL') {
                NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => 'Unlinked', 'surname' => 'Import', 'rank' => 3, 'pay_status' => 0]);
            }
        }
        $foreignRegion = TeamRegion::create(['region_name' => 'Foreign region', 'short_name' => 'FOREIGN']);
        $foreignTeam = Team::factory()->create(['category_event_id' => $this->field(Event::factory()->create(['eventType' => 6]))->id, 'region_id' => $foreignRegion->id]);
        $foreignTeam->players()->attach($player, ['rank' => 1]);
        $this->saved();
        $rows = app(PlayerRatingLeaderboardService::class)->build($event, 'u12 boys')['rows'];
        $this->assertCount(3, $rows);
        $this->assertSame(['BOL', 'WP'], $rows->firstWhere('player_id', $player->id)['regions']);
        $this->assertSame(['BOL'], $rows->firstWhere('name', 'Unlinked Import')['regions']);
        $this->assertSame([], $rows->firstWhere('player_id', $individual->id)['regions']);
        $this->get(route('backend.player-performance.event-ratings', $event).'?cohort=u12%20boys')->assertOk()
            ->assertSee('(BOL, WP)')->assertSee('(BOL)')->assertDontSee('FOREIGN')->assertDontSee('<span class="text-muted">()</span>', false);
        $this->get(route('backend.player-performance.ratings').'?cohort=u12%20boys')->assertOk()
            ->assertDontSee('(BOL')->assertDontSee('(WP')->assertDontSee('(FOREIGN');
    }

    public function test_site_requires_exact_cohort_and_orders_by_rating_with_unrated_last(): void
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
        $this->get($url.'?cohort=u12%20boys')->assertOk()->assertSeeInOrder(['High Test', 'Low Test', 'Comparison group: second', 'Separate Test', 'Unrated players', 'Unrated Test']);
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
        $response->assertSee('class="pagination"', false)->assertDontSee('class="w-5 h-5"', false);
        $this->assertSame([26, 27], $response->viewData('players')->getCollection()->pluck('position')->all());
        $searched = $this->get(route('backend.player-performance.ratings').'?cohort=u12%20boys&search=26');
        $this->assertSame([26], $searched->viewData('players')->getCollection()->pluck('position')->all());
    }

    public function test_event_ratings_reuses_workspace_banner_with_active_navigation_only_once(): void
    {
        $this->admin();
        $event = Event::factory()->create(['name' => 'Ratings banner event', 'eventType' => 6]);
        $this->saved();

        $response = $this->get(route('backend.player-performance.event-ratings', $event))->assertOk()
            ->assertSee('Tournament workspace')->assertSee('Ratings banner event')
            ->assertSee('Public page')->assertSee('<h2 class="h3">Player ratings</h2>', false);
        $html = $response->getContent();
        $this->assertSame(1, substr_count($html, 'class="event-workspace-chrome no-print"'));
        $this->assertSame(1, substr_count($html, 'aria-label="Event navigation"'));
        $this->assertMatchesRegularExpression('/href="'.preg_quote(route('backend.player-performance.event-ratings', $event), '/').'"\s+aria-current="page"/', $html);
        $response->assertDontSee('Ratings banner event · player ratings');

        $this->get(route('backend.player-performance.ratings'))->assertOk()
            ->assertSee('Site-wide player ratings')->assertDontSee('Tournament workspace')
            ->assertDontSee('event-workspace-chrome');
    }

    public function test_event_cohorts_follow_natural_age_order_and_comparison_groups_never_interleave(): void
    {
        $this->admin();
        $event = Event::factory()->create(['eventType' => 6]);
        $estimates = [];
        foreach ([12, 8, 10] as $age) {
            $field = $this->field($event, 'U/'.$age.' Boys A');
            foreach ([['Second High', 95, 'group 2'], ['First Low', 20, 'group 1'], ['Second Low', 10, 'group 2'], ['First High', 70, 'group 1'], ['Unrated Z', null, null], ['Unrated A', null, null]] as [$name, $score, $component]) {
                $player = Player::factory()->create(['name' => $name, 'surname' => (string) $age]);
                $this->entry($field, $player);
                if ($score !== null) {
                    $estimates[$player->id] = [$this->estimate($score, $component, 'u'.$age.' boys')];
                }
            }
        }
        $this->saved($estimates);
        $projection = app(PlayerRatingLeaderboardService::class)->build($event);
        $this->assertSame(['u8 boys', 'u10 boys', 'u12 boys'], $projection['cohorts']);
        $this->assertSame(['u8 boys', 'u10 boys', 'u12 boys'], $projection['rows']->pluck('cohort')->unique()->values()->all());
        foreach ($projection['rows']->groupBy('cohort') as $members) {
            $this->assertSame(['group 1', 'group 1', 'group 2', 'group 2', 'Unrated', 'Unrated'], $members->pluck('component')->all());
            $this->assertSame([70.0, 20.0, 95.0, 10.0, null, null], $members->map(fn ($row) => $row['rating']['score'] ?? null)->all());
            $this->assertSame([1, 2, 1, 2, null, null], $members->pluck('position')->all());
        }
        $this->get(route('backend.player-performance.event-ratings', $event))->assertOk()
            ->assertSeeInOrder(['First High 8', 'First Low 8', 'Second High 8', 'Second Low 8', 'Unrated A 8', 'Unrated Z 8', 'First High 10', 'First High 12']);
        $site = $this->get(route('backend.player-performance.ratings'))->assertOk();
        $this->assertSame(['u8 boys', 'u10 boys', 'u12 boys'], $site->viewData('cohorts'));
    }

    public function test_group_boundaries_and_ranks_survive_event_and_site_pagination_and_search(): void
    {
        $this->admin();
        $event = Event::factory()->create(['eventType' => 6]);
        $field = $this->field($event);
        $estimates = [];
        foreach (range(1, 28) as $i) {
            $player = Player::factory()->create(['name' => 'Boundary', 'surname' => sprintf('%02d', $i)]);
            $this->entry($field, $player);
            $estimates[$player->id] = [$this->estimate($i <= 24 ? 50 - $i : 125 - $i, $i <= 24 ? 'first' : 'second')];
        }
        $this->saved($estimates);
        foreach ([route('backend.player-performance.event-ratings', $event), route('backend.player-performance.ratings')] as $url) {
            $response = $this->get($url.'?cohort=u12%20boys&page=2')->assertOk();
            $response->assertSee('Comparison group: second')->assertSeeInOrder(['Boundary 26', 'Boundary 27', 'Boundary 28'])->assertDontSee('Boundary 24');
            $this->assertSame([2, 3, 4], $response->viewData('players')->getCollection()->pluck('position')->all());
            $searched = $this->get($url.'?cohort=u12%20boys&search=26')->assertOk();
            $this->assertSame([2], $searched->viewData('players')->getCollection()->pluck('position')->all());
        }
    }

    public function test_withheld_snapshot_never_exposes_saved_badges_but_keeps_members_visible(): void
    {
        $this->admin();
        $event = Event::factory()->create(['eventType' => 6]);
        $player = Player::factory()->create(['name' => 'Visible', 'surname' => 'Member']);
        $this->entry($this->field($event), $player);
        $team = Team::factory()->create(['category_event_id' => $event->categoryEvents()->first()->id]);
        NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => 'Imported', 'surname' => 'Member', 'rank' => 1, 'pay_status' => 0]);
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn([
            'cohorts' => [], 'names' => [], 'reason' => 'Saved ability estimates are withheld because their source publication or context changed.',
            'built_at' => 'Not yet updated', 'snapshot_as_of' => null, 'snapshot_stale' => true,
            'badge_players' => [$player->id => [$this->estimate(99.9)]],
        ]);
        $this->partialMock(PlayerSharedAbilityService::class)->shouldNotReceive('calculateSnapshot');
        $this->partialMock(\App\Services\Performance\PlayerAbilityRefreshState::class)->shouldReceive('status')
            ->andReturn(['failed' => true, 'pending' => true]);
        $this->get(route('backend.player-performance.event-ratings', $event))->assertOk()->assertSee('Visible Member')
            ->assertSee('Ratings unavailable')->assertSee('Rating unavailable')->assertSee('Imported Member')->assertSee('Unrated')
            ->assertSee('this does not mean the player has no rating evidence')->assertSee('Saved ability estimates are withheld')
            ->assertDontSee('99.9/100')->assertDontSee('Medium confidence')->assertDontSee('Rating evidence')
            ->assertDontSee('Unrated players')->assertDontSee('Last successful calculation:')
            ->assertSee('Ratings remain unavailable until a successful refresh.');
    }

    public function test_failed_refresh_keeps_successful_ratings_and_labels_their_evidence_date(): void
    {
        $this->admin();
        $event = Event::factory()->create(['eventType' => 6]);
        $player = Player::factory()->create(['name' => 'Saved', 'surname' => 'Member']);
        $this->entry($this->field($event), $player);
        $this->saved([$player->id => [$this->estimate(62)]]);
        $this->partialMock(\App\Services\Performance\PlayerAbilityRefreshState::class)->shouldReceive('status')
            ->andReturn(['failed' => true, 'pending' => true]);

        $this->get(route('backend.player-performance.event-ratings', $event))->assertOk()
            ->assertSee('62.0/100')->assertSee('Last successful calculation: 2026-10-09 08:00:00')
            ->assertSee('evidence as of 2026-10-09')->assertSee('The latest background refresh failed.')
            ->assertSee('it does not include the failed update')->assertDontSee('Rating unavailable')
            ->assertDontSee('A background update is pending.');
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
