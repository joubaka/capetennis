<?php

namespace Tests\Feature;

use App\Models\{Player, Team, TeamPlayer, TeamRegion, User};
use App\Services\Performance\{BallRatingModel, PlayerAbilitySnapshotStore, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerRatingDirectoryFiltersTest extends TestCase
{
    use RefreshDatabase;

    private array $snapshot;
    private array $players;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-15 12:00:00', 'Africa/Johannesburg'));
        if (DB::getDriverName() === 'sqlite') DB::getPdo()->sqliteCreateFunction('YEAR', fn ($date) => $date === null ? null : (int) substr($date, 0, 4));
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $this->snapshot = ['reason' => null, 'built_at' => '2026-11-15', 'snapshot_stale' => false, 'ball_ratings' => [], 'all_age' => ['ratings' => [], 'components' => []]];
        $this->players = [];
        foreach (['red', 'orange', 'standard'] as $stage) {
            $ratings = [];
            foreach (range(1, 12) as $number) {
                $player = Player::factory()->create(['name' => ucfirst($stage).sprintf('%02d', $number), 'surname' => 'Synthetic',
                    'gender' => $number % 2 ? 1 : 2, 'dateOfBirth' => $number === 12 ? '1990-01-01' : ($number === 11 ? null : ($number % 2 ? '2015-01-01' : '2017-12-31'))]);
                $this->players[$stage][$number] = $player;
                $ratings[$player->id] = ['score' => (float) (90 - $number), 'component' => 'reference', 'comparable' => true,
                    'cohort' => BallRatingModel::COHORTS[$stage], 'last_played' => '2026-11-01', 'confidence_index' => $number === 2 ? 15 : 65,
                    'confidence_label' => $number === 2 ? 'Low' : 'Medium', 'evidence_basis' => $number === 2 ? 'finish_only' : 'played'];
            }
            $this->snapshot['ball_ratings'][$stage] = ['ratings' => $ratings, 'reference_component' => 'reference', 'components' => ['reference' => ['members' => array_keys($ratings)]]];
        }
        $this->snapshot['all_age'] = $this->snapshot['ball_ratings']['standard'];
        foreach ($this->snapshot['all_age']['ratings'] as &$rating) $rating['score'] = 1.0;
        unset($rating);
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturnUsing(fn () => $this->snapshot);
        $this->mock(PlayerSharedAbilityService::class)->shouldReceive('ballBadgeSnapshot')->andReturnUsing(fn ($stage) => $this->snapshot['ball_ratings'][$stage]['ratings'] ?? []);
    }

    public function test_overall_is_the_same_ctr_for_all_ages_and_profile_links_keep_ctr_context(): void
    {
        $url = route('backend.player-performance.ratings');
        $standard = $this->get($url.'?ball_type=standard&age_group=u11&age_scope=group&sort=shared')->assertOk()
            ->assertSee('>Red CTR</option>', false)->assertSee('>Orange CTR</option>', false)->assertSee('>CTR</option>', false)
            ->assertDontSee('Overall CTR · all ball levels');
        $this->assertSame(5, $standard->viewData('players')->total());
        $overall = $this->get($url.'?ball_type=overall&age_group=u11&age_scope=group&sort=grouped')->assertOk()
            ->assertSee('Overall shows the same CTR scores for all ages')->assertDontSee('name="age_group"', false)
            ->assertSee('data-ctr-stage="standard"', false)->assertDontSee('data-ctr-stage="overall"', false);
        $this->assertSame('', $overall->viewData('ageGroup'));
        $this->assertSame('shared', $overall->viewData('sort'));
        $this->assertSame(12, $overall->viewData('players')->total());
        $allScores = $overall->viewData('players')->keyBy('id');
        foreach ($standard->viewData('players') as $player) $this->assertSame($player['rating']['score'], $allScores[$player['id']]['rating']['score']);
        $this->assertStringContainsString('ball_type=standard', $overall->getContent());
        $adult = $this->players['standard'][12];
        $this->assertContains($adult->id, $overall->viewData('players')->pluck('id')->all());
        foreach (['grouped', 'age', 'shared', 'score'] as $sort) {
            $response = $this->get($url.'?ball_type=overall&sort='.$sort.'&age_group=u9')->assertOk();
            $this->assertSame(12, $response->viewData('players')->total());
            $this->assertFalse($response->viewData('groupedOrder'));
            $this->assertFalse($response->viewData('ageOrder'));
        }
        if ($path = getenv('CT_DIRECTORY_FILTER_HTML')) file_put_contents($path, $standard->getContent());
        if ($path = getenv('CT_DIRECTORY_OVERALL_HTML')) {
            $pagedOverall = $this->get($url.'?ball_type=overall&per_page=10')->assertOk();
            file_put_contents($path, $pagedOverall->getContent());
        }
    }

    public function test_missing_ctr_graph_never_falls_back_to_a_different_combined_score(): void
    {
        unset($this->snapshot['ball_ratings']);
        $this->assertNotEmpty($this->snapshot['all_age']['ratings']);
        foreach (['', '?ball_type=standard', '?ball_type=overall'] as $query) {
            $response = $this->get(route('backend.player-performance.ratings').$query)->assertOk();
            $this->assertSame(0, $response->viewData('players')->total());
            $this->assertSame('standard', $response->viewData('ratingStage'));
        }
    }

    public function test_regions_remain_valid_when_switching_rating_and_unrated_or_arbitrary_regions_are_rejected(): void
    {
        $region = TeamRegion::create(['region_name' => 'Orange Only Region']);
        $team = Team::factory()->create(['region_id' => $region->id]);
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $this->players['orange'][1]->id, 'rank' => 1, 'pay_status' => 0]);
        $url = route('backend.player-performance.ratings');
        $orange = $this->get($url.'?ball_type=orange&region=Orange%20Only%20Region')->assertOk();
        $this->assertSame(1, $orange->viewData('players')->total());
        foreach (['red', 'standard', 'overall'] as $stage) {
            $response = $this->get($url.'?ball_type='.$stage.'&region=Orange%20Only%20Region')->assertOk();
            $this->assertSame('Orange Only Region', $response->viewData('region'));
            $this->assertSame(0, $response->viewData('players')->total());
        }
        $team->update(['region_id' => TeamRegion::create(['region_name' => 'Unrated Only Region'])->id]);
        TeamPlayer::where('team_id', $team->id)->update(['player_id' => Player::factory()->create()->id]);
        $this->getJson($url.'?ball_type=overall&region=Unrated%20Only%20Region')->assertUnprocessable();
        $this->getJson($url.'?ball_type=overall&region=Invented')->assertUnprocessable();
    }

    public function test_gender_search_evidence_and_pagination_preserve_scores_and_positions(): void
    {
        $url = route('backend.player-performance.ratings');
        foreach (['red', 'orange', 'standard', 'overall'] as $stage) {
            $response = $this->get($url.'?ball_type='.$stage.'&sort=shared&per_page=10')->assertOk();
            $this->assertCount(10, $response->viewData('players'));
            $page = $this->get($url.'?ball_type='.$stage.'&sort=shared&per_page=10&page=2')->assertOk();
            $this->assertSame([11, 12], $page->viewData('players')->pluck('position')->all());
            $this->assertStringContainsString('ball_type='.$stage, $response->viewData('players')->url(2));
            $supported = $this->get($url.'?ball_type='.$stage.'&sort=shared&supported_only=1')->assertOk();
            $this->assertSame(11, $supported->viewData('players')->total());
            $this->assertSame([1, 3, 4], $supported->viewData('players')->take(3)->pluck('position')->all());
            $girls = $this->get($url.'?ball_type='.$stage.'&sort=shared&gender=girls')->assertOk();
            $this->assertSame(6, $girls->viewData('players')->total());
            $ratingStage = $stage === 'overall' ? 'standard' : $stage;
            $searched = $this->get($url.'?ball_type='.$stage.'&sort=shared&search='.ucfirst($ratingStage).'03%20Synthetic')->assertOk();
            $this->assertSame(1, $searched->viewData('players')->total());
            $this->assertSame(3, $searched->viewData('players')->first()['position']);
            $this->assertSame(87.0, $searched->viewData('players')->first()['rating']['score']);
        }
        $this->getJson($url.'?ball_type=overall&per_page=501')->assertUnprocessable();
        $this->getJson($url.'?ball_type=overall&gender=invalid')->assertUnprocessable();
        $this->getJson($url.'?ball_type=overall&sort=invalid')->assertUnprocessable();
        $this->assertDatabaseCount('players', 36);
        $this->assertDatabaseCount('user_players', 0);
        $this->actingAs(User::factory()->create());
        $this->getJson($url.'?ball_type=overall')->assertForbidden();
    }
}
