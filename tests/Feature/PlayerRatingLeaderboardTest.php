<?php

namespace Tests\Feature;

use App\Models\{Category, CategoryEvent, CategoryEventRegistration, Event, NoProfileTeamPlayer, Player, Registration, Team, TeamRegion, User};
use App\Services\Performance\{PlayerAbilitySnapshotStore, PlayerRatingLeaderboardService, PlayerSharedAbilityService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerRatingLeaderboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'sqlite') {
            \Illuminate\Support\Facades\DB::getPdo()->sqliteCreateFunction('YEAR', fn ($date) => $date === null ? null : (int) substr($date, 0, 4));
        }
    }

    public function test_event_ball_pools_have_distinct_rows_positions_badges_and_detail_links(): void
    {
        $this->admin();
        $event = Event::factory()->create(['eventType' => 6]);
        $player = Player::factory()->create(['name' => 'Separate', 'surname' => 'Pools']);
        $this->entry($this->field($event, 'U10 Boys A'), $player);
        $this->entry($this->field($event, 'U10 Boys B'), $player);
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn([
            'cohorts' => [], 'all_age' => [], 'reason' => null, 'built_at' => '2026-11-16', 'snapshot_stale' => false,
        ]);
        $ability = $this->mock(PlayerSharedAbilityService::class)->makePartial();
        $ability->shouldReceive('ballBadgeSnapshot')->with('red')->andReturn([]);
        $ability->shouldReceive('ballBadgeSnapshot')->with('orange')->andReturn([$player->id => $this->estimate(90, 'same-component')]);
        $ability->shouldReceive('ballBadgeSnapshot')->with('standard')->andReturn([$player->id => $this->estimate(40, 'same-component')]);
        $rows = app(PlayerRatingLeaderboardService::class)->build($event)['rows'];
        $this->assertCount(2, $rows);
        $this->assertSame(['orange', 'standard'], $rows->pluck('ball_stage')->all());
        $this->assertSame([1, 1], $rows->pluck('position')->all());
        $this->assertSame([90.0, 40.0], $rows->map(fn ($row) => $row['rating']['score'])->all());
        $response = $this->get(route('backend.player-performance.event-ratings', $event))->assertOk();
        $response->assertSee('Orange ball')->assertSee('Green/Yellow ball')->assertSee('ball_type=orange', false)->assertSee('ball_type=standard', false);
        $response->assertSee('data-ctr-stage="orange"', false)->assertSee('data-ctr-stage="standard"', false);
        $response->assertSee('class="ctr-directory ratings-page"', false)
            ->assertSee('class="ctr-column-head"', false)->assertSee('ctr-ranking-row', false)
            ->assertSee('data-ctr-confidence', false)->assertSee('width:3rem;height:3rem', false)
            ->assertDontSee('width:7rem;height:7rem', false);
        $ageGrouped = $this->get(route('backend.player-performance.event-ratings', [$event, 'group_by' => 'age']))->assertOk();
        $this->assertSame(['orange', 'standard'], $ageGrouped->viewData('players')->getCollection()->pluck('ball_stage')->all());
        $this->assertSame([1, 1], $ageGrouped->viewData('players')->getCollection()->pluck('position')->all());
        $this->assertSame([90.0, 40.0], $ageGrouped->viewData('players')->getCollection()->map(fn ($row) => $row['rating']['score'])->all());
    }

    use RefreshDatabase;

    public function test_event_player_age_grouping_uses_attained_age_at_event_start_without_changing_rating_groups(): void
    {
        $this->admin();
        $event = Event::factory()->create(['eventType' => 6, 'start_date' => '2026-12-04']);
        $field = $this->field($event, 'U13 Boys A');
        $ratings = [];
        foreach ([['Birthday', '2013-12-04', 90], ['Tomorrow', '2013-12-05', 70], ['Missing', null, 60], ['Future', '2027-01-01', 50]] as [$name, $birth, $score]) {
            $player = Player::factory()->create(['name' => $name, 'surname' => 'Age', 'dateOfBirth' => $birth]);
            $this->entry($field, $player);
            $ratings[$player->id] = [$this->estimate($score)];
        }
        $team = Team::factory()->create(['category_event_id' => $field->id]);
        NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => 'Imported', 'surname' => 'Age', 'rank' => 1, 'pay_status' => 0]);
        $girl = Player::factory()->create(['name' => 'Girl', 'surname' => 'Age', 'dateOfBirth' => '2013-12-05']);
        $this->entry($this->field($event, 'U13 Girls A'), $girl);
        $ratings[$girl->id] = [$this->estimate(99, 'different')];
        $foreign = Player::factory()->create(['name' => 'Foreign', 'surname' => 'Age', 'dateOfBirth' => '2013-12-05']);
        $this->entry($this->field(Event::factory()->create(['eventType' => 6]), 'U13 Boys A'), $foreign);
        $this->saved($ratings);
        $url = route('backend.player-performance.event-ratings', $event);
        $normal = $this->get($url.'?cohort=u13%20boys')->assertOk();
        $grouped = $this->get($url.'?cohort=u13%20boys&group_by=age&ball_type=standard')->assertOk()
            ->assertSee('Age on 4 Dec 2026 (event start)')->assertSee('Unknown age')->assertDontSee('Girl Age')->assertDontSee('Foreign Age')
            ->assertSeeInOrder(['data-player-age-group="12"', 'Tomorrow Age', 'data-player-age-group="13"', 'Birthday Age', 'data-player-age-group="unknown"'], false);
        $this->assertSame([12, 13, null, null, null], $grouped->viewData('players')->getCollection()->pluck('player_age')->all());
        $this->assertSame([2, 1, 3, 4, null], $grouped->viewData('players')->getCollection()->pluck('position')->all());
        $this->assertSame($normal->viewData('players')->getCollection()->keyBy('identity')->map(fn ($row) => [$row['rating'], $row['component'], $row['position']])->sortKeys()->all(),
            $grouped->viewData('players')->getCollection()->keyBy('identity')->map(fn ($row) => [$row['rating'], $row['component'], $row['position']])->sortKeys()->all());
        $all = $this->get($url.'?group_by=age')->assertOk()->assertSee('u13 boys')->assertSee('u13 girls')->assertSee('Comparison group: different');
        $this->assertSame(6, $all->viewData('players')->total());
        $this->getJson($url.'?group_by=invalid')->assertUnprocessable()->assertJsonValidationErrors('group_by');
        if (getenv('CT_LEADERBOARD_QA_HTML') === '1') {
            file_put_contents(storage_path('framework/testing/leaderboard-age-qa.html'), $all->getContent());
        }
    }

    public function test_event_age_grouping_happens_before_pagination_and_preserves_filter_queries(): void
    {
        $this->admin();
        $event = Event::factory()->create(['eventType' => 6, 'start_date' => '2026-12-04']);
        $field = $this->field($event);
        $ratings = [];
        foreach (range(1, 27) as $i) {
            $player = Player::factory()->create(['name' => 'Agepage', 'surname' => sprintf('%02d', $i), 'dateOfBirth' => $i === 1 ? '2013-01-01' : '2014-01-01']);
            $this->entry($field, $player);
            $ratings[$player->id] = [$this->estimate(100 - $i)];
        }
        $this->saved($ratings);
        $response = $this->get(route('backend.player-performance.event-ratings', $event).'?group_by=age&cohort=u12%20boys&ball_type=standard&search=Agepage&page=2')->assertOk();
        $this->assertSame([12, 13], $response->viewData('players')->getCollection()->pluck('player_age')->all());
        $this->assertSame([27, 1], $response->viewData('players')->getCollection()->pluck('position')->all());
        parse_str(parse_url($response->viewData('players')->url(1), PHP_URL_QUERY), $query);
        $this->assertSame(['group_by' => 'age', 'cohort' => 'u12 boys', 'ball_type' => 'standard', 'search' => 'Agepage', 'page' => '1'], $query);
    }

    public function test_age_grouping_labels_the_current_date_fallback_when_event_start_is_unavailable(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-26 12:00', config('app.timezone')));
        $data = app(PlayerRatingLeaderboardService::class)->groupByPlayerAge(['rows' => collect()], new Event);
        $this->assertSame('26 Nov 2026', $data['ageReference']);
        $this->assertTrue($data['ageReferenceFallback']);
        $this->assertCount(0, $data['rows']);
    }

    public function test_event_pool_filter_preserves_scores_positions_and_missing_ratings_without_cross_event_members(): void
    {
        $this->admin();
        $event = Event::factory()->create(['eventType' => 6]);
        $other = Event::factory()->create(['eventType' => 6]);
        $player = Player::factory()->create(['name' => 'Filtered', 'surname' => 'Member']);
        $higher = Player::factory()->create(['name' => 'Higher', 'surname' => 'Member']);
        $foreign = Player::factory()->create(['name' => 'Foreign', 'surname' => 'Member']);
        $orange = $this->field($event, 'U10 Boys B');
        $this->entry($orange, $player);
        $this->entry($orange, $higher);
        $this->entry($this->field($event, 'U10 Boys A'), $player);
        $this->entry($this->field($other, 'U10 Boys B'), $foreign);
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn([
            'cohorts' => [], 'all_age' => [], 'reason' => null, 'built_at' => '2026-11-16', 'snapshot_stale' => false,
        ]);
        $ability = $this->mock(PlayerSharedAbilityService::class)->makePartial();
        $ability->shouldReceive('ballBadgeSnapshot')->with('red')->andReturn([]);
        $ability->shouldReceive('ballBadgeSnapshot')->with('orange')->andReturn([
            $player->id => $this->estimate(65, 'connected'), $higher->id => $this->estimate(80, 'connected'),
            $foreign->id => $this->estimate(95, 'connected'),
        ]);
        $ability->shouldReceive('ballBadgeSnapshot')->with('standard')->andReturn([]);
        $ability->shouldNotReceive('calculateSnapshot');
        $url = route('backend.player-performance.event-ratings', $event);
        $all = $this->get($url)->assertOk()->assertSee('Rating pool')->assertSee('Review tools')
            ->assertSee('never a combined score')->assertDontSee('Foreign Member');
        $this->assertSame(3, $all->viewData('players')->total());
        $filtered = $this->get($url.'?ball_type=orange&search=Filtered')->assertOk();
        $rows = $filtered->viewData('players')->getCollection();
        $this->assertSame(['orange'], $rows->pluck('ball_stage')->all());
        $this->assertSame([2], $rows->pluck('position')->all());
        $this->assertSame(65.0, $rows->first()['rating']['score']);
        $this->assertSame('orange', $filtered->viewData('pool'));
        $standard = $this->get($url.'?ball_type=standard')->assertOk()->assertSee('Filtered Member')->assertSee('Unrated');
        $this->assertNull($standard->viewData('players')->getCollection()->first()['rating']);
        $this->get($url.'?ball_type=red')->assertOk()->assertSee('No matching players in this selection.');
        $this->getJson($url.'?ball_type=overall')->assertUnprocessable()->assertJsonValidationErrors('ball_type');
    }

    public function test_current_profile_age_and_gender_filters_do_not_relabel_saved_ratings(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-29', 'Africa/Johannesburg'));
        $this->admin();
        $match = Player::factory()->create(['name' => 'Find', 'surname' => 'This', 'dateOfBirth' => '2014-12-31', 'gender' => 2]);
        Player::factory()->create(['name' => 'Wrong', 'surname' => 'Gender', 'dateOfBirth' => '2014-01-01', 'gender' => 1]);
        Player::factory()->create(['name' => 'Wrong', 'surname' => 'Age', 'dateOfBirth' => '2013-12-31', 'gender' => 2]);
        $this->saved([$match->id => [$this->estimate(61, 'first')]]);
        $response = $this->get(route('backend.player-performance.ratings', ['age_group' => 'u12', 'gender' => 'girls']))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertSee('Find This')->assertDontSee('Wrong Gender')->assertDontSee('Wrong Age')
            ->assertSee('CTR 61.0')->assertDontSee('72.0/100')->assertSee('data-ctr-ball-type="standard"', false)->assertSee('Highest to lowest shared-scale');
        $this->assertSame(1, $response->viewData('players')->total());
        $this->get(route('backend.player-performance.ratings', ['search' => 'This Find']))->assertOk()->assertSee('Find This')->assertDontSee('Wrong Gender');
        $this->getJson(route('backend.player-performance.ratings', ['age_group' => 'u99']))->assertUnprocessable();
        $this->getJson(route('backend.player-performance.ratings', ['gender' => 'invalid']))->assertUnprocessable();
    }

    public function test_profile_groups_cover_year_boundaries_unknown_profiles_and_pagination(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-29', 'Africa/Johannesburg'));
        $this->admin();
        $ratings = [];
        foreach (['2016-01-01' => 'Youngest', '2017-12-31' => 'Younger', '2015-12-31' => 'Eleven', '2012-01-01' => 'Fourteen', '2008-12-31' => 'Eighteen', '2007-12-31' => 'Adult', '2027-01-01' => 'Future'] as $birth => $name) {
            $player = Player::factory()->create(['name' => $name, 'surname' => 'Boundary', 'dateOfBirth' => $birth, 'gender' => 1]);
            $ratings[$player->id] = [$this->estimate(50)];
        }
        $unknown = Player::factory()->create(['name' => 'Unknown', 'surname' => 'Profile', 'dateOfBirth' => null, 'gender' => null]);
        $unrecognised = Player::factory()->create(['name' => 'Unrecognised', 'surname' => 'Profile', 'dateOfBirth' => '2014-01-01', 'gender' => 9]);
        $ratings[$unknown->id] = [$this->estimate(50)];
        $ratings[$unrecognised->id] = [$this->estimate(50)];
        $this->saved($ratings);
        $url = route('backend.player-performance.ratings');
        $this->get($url.'?age_group=u10')->assertOk()->assertSee('Youngest Boundary')->assertSee('Younger Boundary')->assertDontSee('Eleven Boundary')->assertDontSee('Future Boundary');
        $this->get($url.'?age_group=u18')->assertOk()->assertSee('Fourteen Boundary')->assertSee('Eighteen Boundary')->assertDontSee('Adult Boundary');
        $this->get($url.'?age_group=unknown')->assertOk()->assertSee('Future Boundary')->assertSee('Unknown Profile')->assertDontSee('Youngest Boundary');
        $this->get($url.'?gender=unknown')->assertOk()->assertSee('Unknown Profile')->assertSee('Unrecognised Profile')->assertDontSee('Youngest Boundary');
        foreach (range(1, 27) as $i) {
            $player = Player::factory()->create(['name' => 'Paged', 'surname' => sprintf('Row%02d', $i), 'dateOfBirth' => '2013-01-01', 'gender' => 1]);
            $ratings[$player->id] = [$this->estimate(50)];
        }
        $this->saved($ratings);
        $response = $this->get($url.'?age_group=u13&age_scope=group&gender=boys&u13_boys_page=2&per_page=25')->assertOk()->assertSee('Paged Row26')->assertDontSee('Paged Row01')->assertSee('data-rating-position="26"', false);
        $this->assertSame(27, $response->viewData('players')->total());
    }

    public function test_profile_search_retains_profiles_when_ratings_are_unavailable(): void
    {
        $this->admin();
        Player::factory()->create(['name' => 'Visible', 'surname' => 'Profile']);
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn(['reason' => 'Unavailable', 'built_at' => 'Not yet updated', 'snapshot_stale' => true]);
        $this->mock(PlayerSharedAbilityService::class)->shouldNotReceive('badgeSnapshot');
        $this->get(route('backend.player-performance.ratings'))->assertOk()->assertDontSee('Visible Profile')->assertSee('Ratings are currently unavailable')
            ->assertSee('This page shows the latest available saved ratings.')->assertDontSee('Retry ratings update')->assertDontSee('CTLeaderboardRefresh')->assertDontSee('player-ratings-leaderboard-refresh.js')->assertDontSee('data-rating-position=', false);
    }

    public function test_current_age_gender_rating_order_uses_canonical_headline_and_resets_positions(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-29', 'Africa/Johannesburg'));
        $this->admin();
        $players = [];
        foreach (['Unrated', 'Nine', 'Eighty', 'Hundred', 'Zero', 'Tie Alpha', 'Tie Zulu', 'Headline'] as $name) {
            $players[$name] = Player::factory()->create(['name' => $name, 'surname' => $name, 'dateOfBirth' => '2014-01-01', 'gender' => 1]);
        }
        $girl = Player::factory()->create(['name' => 'Girl', 'surname' => 'First', 'dateOfBirth' => '2014-01-01', 'gender' => 2]);
        $older = Player::factory()->create(['name' => 'Older', 'surname' => 'First', 'dateOfBirth' => '2013-01-01', 'gender' => 1]);
        $ratings = [];
        foreach (['Nine' => 9, 'Eighty' => 80, 'Hundred' => 100, 'Zero' => 0, 'Tie Alpha' => 80, 'Tie Zulu' => 80] as $name => $score) {
            $ratings[$players[$name]->id] = [$this->estimate($score)];
        }
        $ratings[$girl->id] = [$this->estimate(90)];
        $ratings[$older->id] = [$this->estimate(70)];
        $ratings[$players['Headline']->id] = [$this->estimate(50)];
        $this->saved($ratings);
        $response = $this->get(route('backend.player-performance.ratings'))->assertOk();
        $rows = $response->viewData('players')->getCollection();
        $boys = $rows->where('age_key', 'u12')->where('gender_key', 'boys')->values();
        $this->assertSame(['Hundred Hundred', 'Eighty Eighty', 'Tie Alpha Tie Alpha', 'Tie Zulu Tie Zulu', 'Headline Headline', 'Nine Nine', 'Zero Zero'], $boys->pluck('name')->all());
        $this->assertSame(range(1, 7), $boys->pluck('position')->all());
        $this->assertSame(50.0, $boys->firstWhere('name', 'Headline Headline')['rating']['score']);
        $this->assertSame(\App\Services\Performance\BallRatingModel::COHORTS['standard'], $boys->firstWhere('name', 'Headline Headline')['rating']['cohort']);
        $this->assertSame(1, $rows->firstWhere('id', $girl->id)['position']);
        $this->assertSame(1, $rows->firstWhere('id', $older->id)['position']);
        $response->assertSee('U12 · born 2014')->assertSee('U13 · born 2013')->assertSee('Boys')->assertSee('Girls');
    }

    public function test_current_group_order_is_calculated_before_paging_and_search(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-29', 'Africa/Johannesburg'));
        $this->admin();
        $ratings = [];
        foreach (range(1, 28) as $i) {
            $player = Player::factory()->create(['name' => 'Ranked', 'surname' => sprintf('%02d', $i), 'dateOfBirth' => '2014-01-01', 'gender' => 1]);
            $ratings[$player->id] = [$this->estimate($i)];
        }
        $this->saved($ratings);
        $url = route('backend.player-performance.ratings', ['age_group' => 'u12', 'gender' => 'boys', 'per_page' => 25]);
        $response = $this->get($url.'&u12_boys_page=2')->assertOk()->assertSeeInOrder(['Ranked 03', 'Ranked 02', 'Ranked 01'])->assertDontSee('Ranked 28');
        $this->assertSame([26, 27, 28], $response->viewData('players')->getCollection()->pluck('position')->all());
        $searched = $this->get($url.'&search=Ranked%2002')->assertOk();
        $this->assertSame([27], $searched->viewData('players')->getCollection()->pluck('position')->all());
    }

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
        $cohorts = [];
        foreach ($ratings as $id => $saved) {
            foreach ($saved as $rating) {
                $cohorts[$rating['cohort']]['ratings'][$id] = $rating;
                $cohorts[$rating['cohort']]['components'][$rating['component']]['members'][] = (int) $id;
            }
        }
        $graph = ['ratings' => [], 'components' => [], 'reference_component' => null];
        $standard = [];
        foreach ($ratings as $id => $estimates) {
            $rating = $estimates[0];
            $graph['reference_component'] ??= $rating['component'];
            $rating += ['comparable' => $rating['component'] === $graph['reference_component'],
                'ball_type' => 'standard', 'rating_scope' => 'all_age', 'local_score' => $rating['score'],
                'connection_status' => $rating['component'] === $graph['reference_component'] ? 'recent' : 'disconnected',
                'historical_connection' => false, 'confidence_index' => 55];
            $rating['cohort'] = \App\Services\Performance\BallRatingModel::COHORTS['standard'];
            $graph['ratings'][$id] = $rating;
            $graph['components'][$rating['component']]['members'][] = (int) $id;
            $graph['components'][$rating['component']]['converged'] = true;
            $standard[$id] = $rating;
        }
        $empty = ['ratings' => [], 'components' => [], 'reference_component' => null];
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn([
            'cohorts' => $cohorts, 'names' => [], 'reason' => null, 'built_at' => '2026-10-09 08:00:00', 'snapshot_as_of' => '2026-10-09', 'snapshot_stale' => false,
            'all_age' => $graph, 'ball_ratings' => ['red' => $empty, 'orange' => $empty, 'standard' => $graph],
        ]);
        $mock = $this->mock(PlayerSharedAbilityService::class)->makePartial();
        $mock->shouldReceive('badgeSnapshot')->andReturn($ratings);
        $mock->shouldReceive('ballBadgeSnapshot')->with('standard')->andReturn($standard);
        $mock->shouldReceive('ballBadgeSnapshot')->with('red')->andReturn([]);
        $mock->shouldReceive('ballBadgeSnapshot')->with('orange')->andReturn([]);
        $mock->shouldNotReceive('calculateSnapshot');
        $mock->shouldNotReceive('forPlayer');
    }

    private function estimate(float $score, string $group = 'first', string $cohort = 'u12 boys'): array
    {
        return ['cohort' => $cohort, 'component' => $group, 'score' => $score, 'confidence_label' => 'Medium',
            'confidence_explanation' => 'Strength and freshness of evidence, not rating accuracy.', 'last_played' => '2026-09-01',
            'played' => 14, 'direct_opponents' => 8, 'last_direct_match' => '2026-09-01'];
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
            ->assertSee('Rating evidence')->assertSee('CTR 62.0')->assertSee('evidence confidence')
            ->assertSee('14 matches')->assertSee('8 opponents')->assertSee('Last match 2026-09-01');
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
        $this->get($url.'?view=recorded')->assertOk()->assertSee('Choose a cohort to see')->assertDontSee('High Test');
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
            ->assertSee('CTR Ratings')->assertDontSee('Tournament workspace')
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
        $site = $this->get(route('backend.player-performance.ratings', ['view' => 'recorded']))->assertOk();
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
        $poolPage = $this->get(route('backend.player-performance.event-ratings', $event).'?ball_type=standard&cohort=u12%20boys&search=Boundary&page=2')->assertOk();
        $this->assertSame([2, 3, 4], $poolPage->viewData('players')->getCollection()->pluck('position')->all());
        parse_str(parse_url($poolPage->viewData('players')->url(1), PHP_URL_QUERY), $paginationQuery);
        $this->assertSame('standard', $paginationQuery['ball_type']);
        $this->assertSame('u12 boys', $paginationQuery['cohort']);
        $this->assertSame('Boundary', $paginationQuery['search']);
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
            ->andReturn(['failed' => true, 'pending' => true, 'version' => 'withheld']);
        $this->get(route('backend.player-performance.event-ratings', $event))->assertOk()->assertSee('Visible Member')
            ->assertSee('Ratings unavailable')->assertSee('Rating unavailable')->assertSee('Imported Member')->assertSee('Unrated')
            ->assertSee('this does not mean the player has no rating evidence')->assertDontSee('Saved ability estimates are withheld')
            ->assertDontSee('99.9/100')->assertDontSee('Medium confidence')->assertDontSee('Rating evidence')
            ->assertDontSee('Unrated players')->assertDontSee('Last successful calculation:')
            ->assertSee('This page shows the latest available saved ratings.')->assertDontSee('CTLeaderboardRefresh');
    }

    public function test_failed_refresh_keeps_successful_ratings_and_labels_their_evidence_date(): void
    {
        $this->admin();
        $event = Event::factory()->create(['eventType' => 6]);
        $player = Player::factory()->create(['name' => 'Saved', 'surname' => 'Member']);
        $this->entry($this->field($event), $player);
        $this->saved([$player->id => [$this->estimate(62)]]);
        $this->partialMock(\App\Services\Performance\PlayerAbilityRefreshState::class)->shouldReceive('status')
            ->andReturn(['failed' => true, 'pending' => true, 'version' => 'saved']);

        $this->get(route('backend.player-performance.event-ratings', $event))->assertOk()
            ->assertSee('CTR 62.0')->assertSee('Last successful calculation: 2026-10-09 08:00:00')
            ->assertSee('evidence as of 2026-10-09')->assertSee('This page shows the latest available saved ratings.')
            ->assertDontSee('Retry ratings update')->assertDontSee('Rating unavailable')
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
