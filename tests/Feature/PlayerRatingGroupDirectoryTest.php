<?php

namespace Tests\Feature;

use App\Models\{Player, User};
use App\Services\Performance\{AllAgeRatingModel, PlayerAbilitySnapshotStore, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerRatingGroupDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function saved(): array
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::getPdo()->sqliteCreateFunction('YEAR', fn ($date) => $date === null ? null : (int) substr($date, 0, 4));
        }
        $players = collect();
        foreach (['Reference One', 'Reference Two', 'Reference Three', 'Girls First', 'Girls Second'] as $index => $name) {
            [$first, $last] = explode(' ', $name);
            $players->push(Player::factory()->create(['name' => $first, 'surname' => $last, 'gender' => $index < 3 ? 1 : 2, 'dateOfBirth' => '2013-01-01']));
        }
        $match = fn ($first, $second) => ['winner' => $first, 'loser' => $second, 'source_id' => $first.':'.$second,
            'event_id' => 1, 'event_name' => 'Synthetic singles', 'category' => 'U13 girls', 'cohort' => 'u13 girls',
            'confidence_date' => '2026-10-01', 'confidence_date_basis' => 'scheduled match date', 'ball_type' => 'yellow'];
        $graph = (new AllAgeRatingModel)->fit([$match($players[0]->id, $players[1]->id), $match($players[1]->id, $players[2]->id), $match($players[3]->id, $players[4]->id)], CarbonImmutable::parse('2026-10-29'));
        $snapshot = ['reason' => null, 'built_at' => '2026-10-29', 'snapshot_stale' => false, 'snapshot_as_of' => '2026-10-29', 'all_age' => $graph];
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn($snapshot);
        $this->mock(PlayerSharedAbilityService::class)->shouldReceive('allAgeBadgeSnapshot')->andReturn($graph['ratings']);
        return [$players, $snapshot, $graph['ratings'][$players[3]->id]['component']];
    }

    public function test_disconnected_girls_have_provisional_ratings_and_positions_only_within_their_group(): void
    {
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        [$players, $snapshot, $group] = $this->saved();
        $url = route('backend.player-performance.ratings');
        $default = $this->get($url.'?gender=girls')->assertOk()->assertSee('Girls First')->assertSee('within-group only')
            ->assertSee('Awaiting calibration')->assertDontSee('data-rating-position=', false);
        $this->assertNull($default->viewData('players')->getCollection()->first()['position']);
        $this->assertIsFloat($default->viewData('players')->getCollection()->first()['rating']['score']);
        $default->assertSee('Provisional within-group rating')->assertSee('Not calibrated against other groups.');
        $response = $this->get($url.'?comparison_group='.$group.'&sort=score')->assertOk()->assertSee('Within-group ratings only.')
            ->assertSee('within-group only')->assertSee('Girls First')->assertSee('Girls Second')->assertDontSee('Reference One')
            ->assertSee('Provisional badges and profile ratings carry the same low-confidence, within-group restriction.')->assertHeader('Cache-Control', 'no-store, private');
        if ($artifact = getenv('CT_GROUP_VIEW_HTML')) { file_put_contents($artifact, $response->getContent()); }
        $first = $response->viewData('players')->getCollection()->first();
        $this->assertFalse($response->viewData('scoreOrder'));
        $response->assertDontSee('id="rating-sort"', false)->assertDontSee('Diagnostic score order');
        $ageGroupResponse = $this->get($url.'?comparison_group='.$group.'&sort=age')->assertOk()
            ->assertSee('Within-group ratings only.')->assertDontSee('Diagnostic score order');
        $this->assertFalse($ageGroupResponse->viewData('diagnosticOrder'));
        $this->assertSame($players[3]->id, $first['id']);
        $this->assertSame(1, $first['position']);
        $this->assertIsFloat($first['rating']['score']);
        $this->assertFalse($first['rating']['comparable']);
        $this->assertIsFloat($first['rating']['within_group_score']);
        $this->assertSame($snapshot['all_age']['ratings'][$players[3]->id]['score'], $first['rating']['score']);
        $search = $this->get($url.'?comparison_group='.$group.'&search=Second')->assertOk()->assertDontSee('Girls First')->assertSee('Girls Second');
        $this->assertSame(2, $search->viewData('players')->getCollection()->first()['position']);
        $this->assertDatabaseCount('user_players', 0);
    }

    public function test_score_list_is_an_explicit_diagnostic_sort_across_groups_with_stable_ties_and_unrated_last(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-29', 'Africa/Johannesburg'));
        if (DB::getDriverName() === 'sqlite') {
            DB::getPdo()->sqliteCreateFunction('YEAR', fn ($date) => $date === null ? null : (int) substr($date, 0, 4));
        }
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $ratings = []; $ids = [];
        foreach ([['Local', 'Top', 99.0, false], ['Same', 'Alpha', 70.0, true], ['Same', 'Alpha', 70.0, false], ['Shared', 'Zulu', 70.0, true]] as [$name, $surname, $score, $comparable]) {
            $player = Player::factory()->create(['name' => $name, 'surname' => $surname, 'gender' => 1, 'dateOfBirth' => '2013-01-01']);
            $ids[] = $player->id;
            $ratings[$player->id] = ['score' => $score, 'component' => $comparable ? 'reference' : 'local'.$player->id,
                'comparable' => $comparable, 'cohort' => AllAgeRatingModel::COHORT, 'last_played' => '2026-10-01', 'confidence_label' => 'Low'];
        }
        $unrated = Player::factory()->create(['name' => 'Unrated', 'surname' => 'Aardvark', 'gender' => 1, 'dateOfBirth' => '2013-01-01']);
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn([
            'reason' => null, 'built_at' => '2026-10-29', 'snapshot_stale' => false,
            'all_age' => ['ratings' => $ratings, 'components' => [], 'reference_component' => 'reference'],
        ]);
        $this->mock(PlayerSharedAbilityService::class)->shouldReceive('allAgeBadgeSnapshot')->andReturnUsing(function () use (&$ratings) { return $ratings; });
        $url = route('backend.player-performance.ratings');
        $response = $this->get($url.'?sort=score')->assertOk()->assertSee('Diagnostic score order')
            ->assertSee('not calibrated between comparison groups')->assertSee('within-group only')
            ->assertDontSee('data-rating-position=', false)->assertSee('data-score-list-position="1"', false)
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame([...$ids, $unrated->id], $response->viewData('players')->getCollection()->pluck('id')->all());
        $this->assertSame([1, 2, 3, 4, null], $response->viewData('players')->getCollection()->pluck('position')->all());
        if (getenv('CT_ALL_AGE_QA_HTML') === '1') {
            file_put_contents(storage_path('framework/testing/all-age-ratings-score.html'), $response->getContent());
        }
        $searched = $this->get($url.'?sort=score&search=Same')->assertOk();
        $this->assertSame([2, 3], $searched->viewData('players')->getCollection()->pluck('position')->all());
        foreach (range(1, 24) as $number) {
            $player = Player::factory()->create(['name' => 'Paged', 'surname' => sprintf('Row%02d', $number), 'gender' => 1, 'dateOfBirth' => '2013-01-01']);
            $ratings[$player->id] = array_replace($ratings[$ids[0]], ['score' => 60.0 - $number]);
        }
        $firstPage = $this->get($url.'?sort=score&age_group=u13&gender=boys')->assertOk();
        $this->assertStringContainsString('sort=score', $firstPage->viewData('players')->url(2));
        $this->assertStringContainsString('age_group=u13', $firstPage->viewData('players')->url(2));
        $this->assertStringContainsString('gender=boys', $firstPage->viewData('players')->url(2));
        $secondPage = $this->get($url.'?sort=score&page=2')->assertOk();
        $this->assertSame([26, 27, 28, null], $secondPage->viewData('players')->getCollection()->pluck('position')->all());
        $this->getJson($url.'?sort=unknown')->assertUnprocessable();
        $this->assertDatabaseCount('user_players', 0);
    }

    public function test_age_score_lists_restart_numbers_per_current_age_without_gender_partitions_or_score_changes(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::getPdo()->sqliteCreateFunction('YEAR', fn ($date) => $date === null ? null : (int) substr($date, 0, 4));
        }
        $this->travelTo(CarbonImmutable::parse('2026-10-29', 'Africa/Johannesburg'));
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $ratings = [];
        foreach ([['Young', 'First', '2016-01-01', 1, 20.0], ['Young', 'Second', '2017-01-01', 2, 10.0],
            ['Girl', 'Higher', '2013-01-01', 2, 99.0], ['Older', 'Lower', '2013-01-01', 1, 60.0],
            ['Unknown', 'Age', null, 1, 90.0], ['Adult', 'Age', '2000-01-01', 1, 95.0]] as [$name, $surname, $birth, $gender, $score]) {
            $player = Player::factory()->create(['name' => $name, 'surname' => $surname, 'dateOfBirth' => $birth, 'gender' => $gender]);
            $ratings[$player->id] = ['score' => $score, 'component' => 'group'.$player->id, 'comparable' => false,
                'cohort' => AllAgeRatingModel::COHORT, 'last_played' => '2026-10-01', 'confidence_label' => 'Low'];
        }
        Player::factory()->create(['name' => 'Unrated', 'surname' => 'Aardvark', 'dateOfBirth' => '2013-01-01', 'gender' => 1]);
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn([
            'reason' => null, 'built_at' => '2026-10-29', 'snapshot_stale' => false,
            'all_age' => ['ratings' => $ratings, 'components' => [], 'reference_component' => null],
        ]);
        $this->mock(PlayerSharedAbilityService::class)->shouldReceive('allAgeBadgeSnapshot')->andReturnUsing(function () use (&$ratings) { return $ratings; });
        $url = route('backend.player-performance.ratings');
        $response = $this->get($url.'?sort=age')->assertOk()->assertSee('Rank by age group')->assertSee('Numbers restart within each current age group')
            ->assertSee('not calibrated between comparison groups')->assertDontSee('data-rating-position=', false);
        $rows = $response->viewData('players')->getCollection();
        $this->assertSame(['Young First', 'Young Second', 'Girl Higher', 'Older Lower', 'Unrated Aardvark', 'Unknown Age', 'Adult Age'], $rows->pluck('name')->all());
        $this->assertSame([1, 2, 1, 2, null, 1, 1], $rows->pluck('position')->all());
        $this->assertSame([20.0, 10.0, 99.0, 60.0, null, 90.0, 95.0], $rows->map(fn ($row) => $row['rating']['score'] ?? null)->all());
        $this->assertSame(4, substr_count($response->getContent(), '<h2 class="h5">'));
        if (getenv('CT_ALL_AGE_QA_HTML') === '1') {
            file_put_contents(storage_path('framework/testing/all-age-ratings-age.html'), $response->getContent());
        }
        $search = $this->get($url.'?sort=age&search=Older')->assertOk();
        $this->assertSame([2], $search->viewData('players')->getCollection()->pluck('position')->all());
        $filtered = $this->get($url.'?sort=age&age_group=u13&gender=boys')->assertOk();
        $this->assertSame(['Older Lower', 'Unrated Aardvark'], $filtered->viewData('players')->getCollection()->pluck('name')->all());
        $this->assertSame([1, null], $filtered->viewData('players')->getCollection()->pluck('position')->all());
        foreach (range(1, 26) as $number) {
            $player = Player::factory()->create(['name' => 'Paged', 'surname' => sprintf('Row%02d', $number), 'dateOfBirth' => '2013-01-01', 'gender' => 1]);
            $ratings[$player->id] = array_replace(reset($ratings), ['score' => 50.0 - $number]);
        }
        $paged = $this->get($url.'?sort=age&age_group=u13&page=2')->assertOk();
        $this->assertSame([26, 27, 28, null], $paged->viewData('players')->getCollection()->pluck('position')->all());
        $this->assertStringContainsString('sort=age', $paged->viewData('players')->url(1));
        $this->assertStringContainsString('age_group=u13', $paged->viewData('players')->url(1));
        $this->assertDatabaseCount('user_players', 0);
        $this->assertDatabaseCount('players', 33);
    }

    public function test_group_projection_does_not_expand_audience_or_accept_unknown_groups(): void
    {
        $scoreUrl = route('backend.player-performance.ratings', ['sort' => 'score']);
        $this->getJson($scoreUrl)->assertUnauthorized();
        $ageUrl = route('backend.player-performance.ratings', ['sort' => 'age']);
        $this->getJson($ageUrl)->assertUnauthorized();
        $url = route('backend.player-performance.ratings', ['comparison_group' => 'unknown']);
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        $this->getJson($scoreUrl)->assertForbidden();
        $this->getJson($ageUrl)->assertForbidden();
        $this->getJson($url)->assertForbidden();
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $this->saved();
        $this->getJson($url)->assertUnprocessable();
    }

    public function test_combined_directory_ranks_only_shared_scale_scores_before_unranked_local_estimates(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::getPdo()->sqliteCreateFunction('YEAR', fn ($date) => $date === null ? null : (int) substr($date, 0, 4));
        }
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $ratings = [];
        foreach (range(1, 27) as $place) {
            $player = Player::factory()->create(['name' => 'Shared', 'surname' => sprintf('Player%02d', $place), 'gender' => $place % 2 ? 1 : 2]);
            $ratings[$player->id] = ['score' => (float) (80 - $place), 'component' => 'reference', 'comparable' => true,
                'cohort' => AllAgeRatingModel::COHORT, 'last_played' => '2026-10-01', 'confidence_label' => 'Low'];
        }
        foreach (['Alpha' => 2.0, 'Zulu' => 99.0, 'Implicit' => 98.0] as $surname => $score) {
            $player = Player::factory()->create(['name' => 'Local', 'surname' => $surname]);
            $ratings[$player->id] = ['score' => $score, 'component' => strtolower($surname),
                'cohort' => AllAgeRatingModel::COHORT, 'last_played' => '2026-10-01', 'confidence_label' => 'Low'];
            if ($surname !== 'Implicit') { $ratings[$player->id]['comparable'] = false; }
        }
        Player::factory()->create(['name' => 'Unrated', 'surname' => 'FirstAlphabetically']);
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn([
            'reason' => null, 'built_at' => '2026-10-29', 'snapshot_stale' => false, 'snapshot_as_of' => '2026-10-29',
            'all_age' => ['ratings' => $ratings, 'components' => [], 'reference_component' => 'reference'],
        ]);
        $this->mock(PlayerSharedAbilityService::class)->shouldReceive('allAgeBadgeSnapshot')->andReturn($ratings);
        $url = route('backend.player-performance.ratings');
        $first = $this->get($url)->assertOk()->assertSee('All players')->assertDontSee('Comparison group reference')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(range(1, 25), $first->viewData('players')->getCollection()->pluck('position')->all());
        $this->assertSame(31, $first->viewData('players')->total());
        $second = $this->get($url.'?page=2')->assertOk();
        $rows = $second->viewData('players')->getCollection();
        $this->assertSame(['Shared Player26', 'Shared Player27', 'Local Alpha', 'Local Implicit', 'Local Zulu', 'Unrated FirstAlphabetically'], $rows->pluck('name')->all());
        $this->assertSame([26, 27, null, null, null, null], $rows->pluck('position')->all());
        $this->assertSame([false, false, true, true, true, false], $rows->pluck('awaiting_calibration')->all());
        $this->assertSame(99.0, $rows->firstWhere('name', 'Local Zulu')['rating']['score']);
        $searched = $this->get($url.'?search=Player27')->assertOk();
        $this->assertSame([27], $searched->viewData('players')->getCollection()->pluck('position')->all());
        $this->get($url.'?search=Local')->assertOk()->assertSee('Awaiting calibration')->assertDontSee('data-rating-position=', false);
        $this->assertDatabaseCount('user_players', 0);
    }

    public function test_withheld_snapshot_cannot_reveal_a_previously_selected_local_group(): void
    {
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn([
            'reason' => 'Source publication changed', 'all_age' => ['components' => ['girls' => ['members' => [1, 2], 'converged' => true]]],
        ]);
        $this->mock(PlayerSharedAbilityService::class)->shouldNotReceive('allAgeBadgeSnapshot');
        $this->getJson(route('backend.player-performance.ratings', ['comparison_group' => 'girls']))->assertUnprocessable();
    }

    public function test_combined_directory_without_a_shared_reference_or_available_snapshot_has_no_positions(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::getPdo()->sqliteCreateFunction('YEAR', fn ($date) => $date === null ? null : (int) substr($date, 0, 4));
        }
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $player = Player::factory()->create(['name' => 'Local', 'surname' => 'Only']);
        $rating = ['score' => 90.0, 'component' => 'local', 'comparable' => false, 'cohort' => AllAgeRatingModel::COHORT,
            'last_played' => '2026-10-01', 'confidence_label' => 'Low'];
        $snapshot = ['reason' => null, 'built_at' => '2026-10-29', 'snapshot_stale' => false,
            'all_age' => ['reference_component' => null, 'components' => [], 'ratings' => [$player->id => $rating]]];
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn($snapshot);
        $this->mock(PlayerSharedAbilityService::class)->shouldReceive('allAgeBadgeSnapshot')->andReturn([$player->id => $rating]);
        $url = route('backend.player-performance.ratings');
        $response = $this->get($url)->assertOk()->assertSee('Awaiting calibration')->assertDontSee('data-rating-position=', false);
        $this->assertNull($response->viewData('players')->getCollection()->first()['position']);
        $snapshot['reason'] = 'Source publication changed';
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn($snapshot);
        $this->mock(PlayerSharedAbilityService::class)->shouldNotReceive('allAgeBadgeSnapshot');
        $this->get($url)->assertOk()->assertSee('Rating unavailable')->assertDontSee('90.0/100')->assertDontSee('data-rating-position=', false);
    }

    public function test_missing_or_invalidated_snapshot_without_all_age_keeps_one_alphabetical_unranked_directory(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::getPdo()->sqliteCreateFunction('YEAR', fn ($date) => $date === null ? null : (int) substr($date, 0, 4));
        }
        $this->travelTo(CarbonImmutable::parse('2026-10-29', 'Africa/Johannesburg'));
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        Player::factory()->create(['name' => 'Young', 'surname' => 'Zulu', 'dateOfBirth' => '2016-01-01', 'gender' => 1]);
        Player::factory()->create(['name' => 'Older', 'surname' => 'Alpha', 'dateOfBirth' => '2008-01-01', 'gender' => 2]);
        Player::factory()->create(['name' => 'Unknown', 'surname' => 'Middle', 'dateOfBirth' => null, 'gender' => null]);
        $this->mock(PlayerSharedAbilityService::class)->shouldNotReceive('allAgeBadgeSnapshot')->shouldNotReceive('badgeSnapshot');
        foreach (['Ability update pending. A saved snapshot is not yet available.',
            'Saved ability estimates are withheld because their source publication or context changed.'] as $reason) {
            $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn([
                'cohorts' => [], 'names' => [], 'reason' => $reason, 'built_at' => 'Not yet updated',
                'snapshot_as_of' => null, 'snapshot_stale' => true,
            ]);
            $response = $this->get(route('backend.player-performance.ratings', ['sort' => 'score']))->assertOk()
                ->assertSee('one alphabetical list while ratings are unavailable.')
                ->assertSeeInOrder(['Older Alpha', 'Unknown Middle', 'Young Zulu'])
                ->assertDontSee('data-rating-position=', false)->assertDontSee('/100')
                ->assertDontSee('data-score-list-position=', false)->assertDontSee('Diagnostic score order')
                ->assertDontSee('Awaiting calibration')->assertHeader('Cache-Control', 'no-store, private');
            $this->assertSame([null, null, null], $response->viewData('players')->getCollection()->pluck('position')->all());
            $this->assertSame(1, substr_count($response->getContent(), '<h2 class="h5">All players</h2>'));
            $this->assertSame(1, substr_count($response->getContent(), '<ul class="list-group mb-3">'));
            $unavailableAge = $this->get(route('backend.player-performance.ratings', ['sort' => 'age']))->assertOk()
                ->assertSee('one alphabetical list while ratings are unavailable.')
                ->assertSeeInOrder(['Older Alpha', 'Unknown Middle', 'Young Zulu'])
                ->assertDontSee('data-rating-position=', false)->assertDontSee('data-score-list-position=', false)->assertDontSee('/100');
            $this->assertSame(1, substr_count($unavailableAge->getContent(), '<h2 class="h5">All players</h2>'));
            if (getenv('CT_ALL_AGE_QA_HTML') === '1') {
                file_put_contents(storage_path('framework/testing/all-age-ratings-unavailable.html'), $response->getContent());
            }
        }
    }
}
