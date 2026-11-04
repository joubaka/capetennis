<?php

namespace Tests\Feature;

use App\Models\{Category, CategoryEvent, CategoryEventRegistration, CategoryResult, Event, Player, Registration, User};
use App\Services\Performance\{AllAgeRatingModel, BallRatingModel, PlayerAbilitySnapshotStore, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerBallRatingDirectoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-02 12:00:00', 'Africa/Johannesburg'));
        if (DB::getDriverName() === 'sqlite') DB::getPdo()->sqliteCreateFunction('YEAR', fn ($date) => $date === null ? null : (int) substr($date, 0, 4));
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
    }

    private function finishingField(string $ball, bool $published = true): array
    {
        $event = Event::factory()->create(['name' => 'Synthetic '.$ball.' results', 'start_date' => '2026-10-20', 'end_date' => '2026-10-20', 'published' => true, 'results_published' => $published]);
        $category = Category::factory()->create(['name' => $ball.' Ball Boys A']);
        $field = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => $category->id]);
        $players = Player::factory()->count(3)->create(['surname' => $ball.'Finish', 'dateOfBirth' => '2017-01-01', 'gender' => 1]);
        foreach ($players as $index => $player) {
            $registration = Registration::factory()->create();
            $registration->players()->attach($player);
            CategoryEventRegistration::factory()->create(['category_event_id' => $field->id, 'registration_id' => $registration->id]);
            CategoryResult::create(['event_id' => $event->id, 'category_id' => $category->id, 'registration_id' => $registration->id, 'position' => $index + 1]);
        }
        return [$event, $field, $players];
    }

    private function savedSnapshot(): array
    {
        $snapshot = app(PlayerSharedAbilityService::class)->calculateSnapshot(CarbonImmutable::today('Africa/Johannesburg'));
        $snapshot += ['snapshot_stale' => false, 'snapshot_as_of' => '2026-11-02'];
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn($snapshot);
        return $snapshot;
    }

    public function test_published_finish_only_players_appear_in_their_independent_ball_lists_without_an_overall_rating(): void
    {
        [, , $red] = $this->finishingField('Red');
        [, , $orange] = $this->finishingField('Orange');
        [, , $green] = $this->finishingField('Green');
        $this->finishingField('Unpublished Orange', false);
        [$invalid] = $this->finishingField('Invalid Orange');
        CategoryResult::where('event_id', $invalid->id)->where('position', 3)->update(['position' => 2]);
        $snapshot = $this->savedSnapshot();
        $this->assertSame([], $snapshot['all_age']['ratings']);
        $url = route('backend.player-performance.ratings');
        foreach (['red' => $red, 'orange' => $orange, 'standard' => $green] as $ball => $players) {
            $response = $this->get($url.'?ball_type='.$ball)->assertOk()->assertSee('Published final placings only')
                ->assertSee('low confidence')->assertSee('Ball levels have independent scales')
                ->assertDontSee('Unpublished OrangeFinish')->assertDontSee('Invalid OrangeFinish')
                ->assertDontSee('data-rating-position=', false)->assertSee('data-ctr-stage="'.$ball.'"', false);
            $this->assertSame($ball, $response->viewData('ballType'));
            $this->assertSame(3, $response->viewData('players')->total());
            $this->assertEqualsCanonicalizing($players->pluck('id')->all(), $response->viewData('players')->getCollection()->pluck('id')->all());
            foreach ($response->viewData('players') as $row) {
                $this->assertSame('finish_only', $row['rating']['evidence_basis']);
                $this->assertSame(BallRatingModel::COHORTS[$ball], $row['rating']['cohort']);
                $this->assertLessThanOrEqual(15, $row['rating']['confidence_index']);
                $this->assertNull($row['position']);
            }
        }
        $default = $this->get($url)->assertOk();
        $this->assertSame('standard', $default->viewData('ballType'));
        $this->assertEqualsCanonicalizing($green->pluck('id')->all(), $default->viewData('players')->getCollection()->pluck('id')->all());
        $overall = $this->get($url.'?ball_type=overall')->assertOk();
        $this->assertSame(0, $overall->viewData('players')->total());
        $this->assertSame(0, DB::table('user_players')->count());
        $this->assertDatabaseCount('players', 15);
        if ($path = getenv('CT_BALL_DIRECTORY_HTML')) file_put_contents($path, $default->getContent());
    }

    public function test_red_and_orange_have_one_all_age_list_even_for_legacy_age_filtered_links(): void
    {
        $fields = [];
        foreach (['red' => 'Red', 'orange' => 'Orange', 'standard' => 'Green'] as $ball => $label) {
            [, , $players] = $this->finishingField($label);
            $players[0]->update(['dateOfBirth' => '2019-01-01']);
            $players[1]->update(['dateOfBirth' => '2018-01-01', 'gender' => 2]);
            $players[2]->update(['dateOfBirth' => '2013-01-01']);
            $fields[$ball] = $players;
        }
        $this->savedSnapshot();
        $url = route('backend.player-performance.ratings');
        foreach (['red', 'orange'] as $ball) {
            foreach (['grouped', 'shared', 'age', 'score'] as $sort) {
                $response = $this->get($url.'?ball_type='.$ball.'&age_group=u9&age_scope=younger&sort='.$sort)
                    ->assertOk()->assertSee(ucfirst($ball).' ball CTR · all ages')
                    ->assertDontSee('name="age_group"', false)->assertDontSee('name="age_scope"', false)
                    ->assertDontSee('data-ctr-age-section=', false)->assertDontSee('value="age"', false)
                    ->assertDontSee('value="grouped"', false);
                $this->assertSame('', $response->viewData('ageGroup'));
                $this->assertFalse($response->viewData('groupedOrder'));
                $this->assertFalse($response->viewData('ageOrder'));
                $this->assertSame([], $response->viewData('sections'));
                $this->assertEqualsCanonicalizing($fields[$ball]->pluck('id')->all(), $response->viewData('players')->pluck('id')->all());
            }
            $boys = $this->get($url.'?ball_type='.$ball.'&gender=boys&age_group=u13')->assertOk();
            $this->assertEqualsCanonicalizing([$fields[$ball][0]->id, $fields[$ball][2]->id], $boys->viewData('players')->pluck('id')->all());
            $girls = $this->get($url.'?ball_type='.$ball.'&gender=girls&age_group=u7')->assertOk();
            $this->assertSame([$fields[$ball][1]->id], $girls->viewData('players')->pluck('id')->all());
            $search = $this->get($url.'?ball_type='.$ball.'&search='.urlencode($fields[$ball][1]->name))->assertOk();
            $this->assertContains($fields[$ball][1]->id, $search->viewData('players')->pluck('id')->all());
        }
        $standard = $this->get($url.'?ball_type=standard&age_group=u13')->assertOk()
            ->assertSee('name="age_group"', false)->assertSee('data-ctr-age-section="u13"', false);
        $this->assertEqualsCanonicalizing($fields['standard']->pluck('id')->all(), $standard->viewData('players')->pluck('id')->all());
        $this->assertTrue($standard->viewData('includeYounger'));
        $this->assertTrue($standard->viewData('groupedOrder'));
        $this->assertDatabaseCount('players', 9);
    }

    public function test_selected_ball_graph_membership_is_required_and_foreign_groups_are_rejected(): void
    {
        [, , $red] = $this->finishingField('Red');
        [, , $orange] = $this->finishingField('Orange');
        $snapshot = $this->savedSnapshot();
        $redGraph = &$snapshot['ball_ratings']['red'];
        $component = $redGraph['ratings'][$red[0]->id]['component'];
        $redGraph['components'][$component]['members'] = [$red[0]->id];
        $redGraph['ratings'][$red[2]->id]['component'] = 'orphan';
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn($snapshot);
        $url = route('backend.player-performance.ratings');
        foreach (['grouped', 'shared', 'score', 'age'] as $sort) {
            $response = $this->get($url.'?ball_type=red&sort='.$sort)->assertOk();
            $this->assertSame([$red[0]->id], $response->viewData('players')->getCollection()->pluck('id')->all());
        }
        $foreign = $snapshot['ball_ratings']['orange']['ratings'][$orange[0]->id]['component'];
        $this->getJson($url.'?ball_type=red&comparison_group='.$foreign)->assertUnprocessable();
        $this->getJson($url.'?ball_type=purple')->assertUnprocessable();
        $this->assertDatabaseCount('players', 6);
    }

    public function test_unavailable_snapshot_and_private_audience_do_not_expose_ball_players(): void
    {
        $this->finishingField('Red');
        $snapshot = $this->savedSnapshot();
        $snapshot['reason'] = 'Saved ability estimates are withheld because their source publication or context changed.';
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn($snapshot);
        $url = route('backend.player-performance.ratings', ['ball_type' => 'red']);
        $response = $this->get($url)->assertOk()->assertDontSee('RedFinish')->assertSee('Ratings are currently unavailable');
        $this->assertSame(0, $response->viewData('players')->total());
        $this->actingAs(User::factory()->create());
        $this->getJson($url)->assertForbidden();
        auth()->logout();
        $this->getJson($url)->assertUnauthorized();
    }

    public function test_within_group_ball_projection_preserves_finish_only_provenance(): void
    {
        $players = Player::factory()->count(5)->create(['dateOfBirth' => '2017-01-01', 'gender' => 1]);
        $match = fn ($a, $b, $event) => ['winner' => $a, 'loser' => $b, 'source_id' => 'match:'.$event,
            'event_id' => $event, 'event_name' => 'Synthetic Red singles', 'category' => 'Red Ball Boys',
            'cohort' => 'red ball boys', 'confidence_date' => '2026-10-20', 'confidence_date_basis' => 'scheduled match date',
            'ball_type' => 'red'];
        $field = ['players' => [$players[2]->id, $players[3]->id, $players[4]->id], 'source_id' => 'ball-finish:3:1',
            'event_id' => 3, 'event_name' => 'Synthetic Red final', 'event_date' => '2026-10-20', 'completed_at' => '2026-10-20',
            'category' => 'Red Ball Boys', 'rating_ball_type' => 'red'];
        $graph = (new AllAgeRatingModel)->fit([
            $match($players[0]->id, $players[1]->id, 1), $match($players[3]->id, $players[4]->id, 2),
        ], CarbonImmutable::today('Africa/Johannesburg'), ordinalFields: [$field]);
        $finishOnly = $graph['ratings'][$players[2]->id];
        $this->assertSame('finish_only', $finishOnly['evidence_basis']);
        $this->assertFalse($finishOnly['comparable']);
        $snapshot = ['reason' => null, 'built_at' => '2026-11-02', 'snapshot_stale' => false,
            'all_age' => ['ratings' => [], 'components' => []], 'ball_ratings' => ['red' => $graph]];
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn($snapshot);
        $response = $this->get(route('backend.player-performance.ratings', ['ball_type' => 'red', 'comparison_group' => $finishOnly['component']]))
            ->assertOk()->assertSee('Within-group ratings only.')->assertSee('Published final placings only')
            ->assertSee('data-ctr-stage="red"', false);
        $row = $response->viewData('players')->getCollection()->firstWhere('id', $players[2]->id);
        $this->assertSame('finish_only', $row['rating']['evidence_basis']);
        $this->assertArrayHasKey('within_group_score', $row['rating']);
        $this->assertSame('red', $row['rating']['ball_type']);
        $this->assertStringContainsString('ball_type=red', $response->getContent());
    }
}
