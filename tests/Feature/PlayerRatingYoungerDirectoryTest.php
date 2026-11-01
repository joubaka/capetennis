<?php

namespace Tests\Feature;

use App\Models\{Player, User};
use App\Services\Performance\{AllAgeRatingModel, PlayerAbilitySnapshotStore, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerRatingYoungerDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_younger_selection_combines_age_groups_and_ranks_with_stable_search_pages(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01', 'Africa/Johannesburg'));
        if (DB::getDriverName() === 'sqlite') DB::getPdo()->sqliteCreateFunction('YEAR', fn ($date) => $date === null ? null : (int) substr($date, 0, 4));
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $ratings = []; $components = [];
        $add = function ($name, $birth, $gender, $score, $comparable = true) use (&$ratings, &$components) {
            $player = Player::factory()->create(['name' => $name, 'surname' => 'Synthetic', 'dateOfBirth' => $birth, 'gender' => $gender]);
            $component = $comparable ? 'reference' : 'local';
            $components[$component]['members'][] = $player->id;
            $ratings[$player->id] = ['score' => (float) $score, 'component' => $component, 'comparable' => $comparable,
                'cohort' => AllAgeRatingModel::COHORT, 'last_played' => '2026-10-01', 'confidence_label' => 'Low', 'confidence_index' => 25];
        };
        $add('NineJanuary', '2017-01-01', 1, 60);
        $add('NineDecember', '2017-12-31', 1, 40);
        $add('EightStronger', '2018-01-01', 1, 80);
        $add('SevenStrongest', '2019-12-31', 1, 90);
        $add('GirlNine', '2017-12-31', 2, 50);
        $add('GirlEight', '2018-12-31', 2, 70);
        $add('LocalGirl', '2020-01-01', 2, 99, false);
        $add('TooOld', '2016-12-31', 1, 95);
        $add('UnknownBirth', null, 1, 95);
        $add('FutureBirth', '2027-01-01', 1, 95);
        $add('Adult', '2000-01-01', 1, 95);
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturnUsing(function () use (&$ratings, &$components) { return ['reason' => null, 'snapshot_stale' => false, 'built_at' => '2026-11-01',
            'all_age' => ['ratings' => $ratings, 'components' => $components, 'reference_component' => 'reference']]; });
        $this->mock(PlayerSharedAbilityService::class)->shouldReceive('allAgeBadgeSnapshot')->andReturnUsing(function () use (&$ratings) { return $ratings; });
        $url = route('backend.player-performance.ratings');
        $response = $this->get($url.'?age_group=u9&age_scope=younger&per_page=500')->assertOk()->assertSee('U9 and younger · born 2017 or later')
            ->assertDontSee('TooOld Synthetic')->assertDontSee('UnknownBirth Synthetic')->assertDontSee('FutureBirth Synthetic')->assertDontSee('Adult Synthetic');
        $this->assertSame(['u9'], array_keys($response->viewData('sections')));
        $columns = $response->viewData('sections')['u9']['columns'];
        $this->assertSame(['SevenStrongest Synthetic', 'EightStronger Synthetic', 'NineJanuary Synthetic', 'NineDecember Synthetic'], $columns['boys']->getCollection()->pluck('name')->all());
        $this->assertSame([1, 2, 3, 4], $columns['boys']->getCollection()->pluck('position')->all());
        $this->assertSame(['u7', 'u8', 'u9', 'u9'], $columns['boys']->getCollection()->pluck('age_key')->all());
        $this->assertSame([1, 2, null], $columns['girls']->getCollection()->pluck('position')->all());
        $this->assertStringContainsString('age_scope=younger', $columns['boys']->url(2));
        $searched = $this->get($url.'?age_group=u9&age_scope=younger&search=NineJanuary')->assertOk();
        $this->assertSame(3, $searched->viewData('sections')['u9']['columns']['boys']->first()['position']);
        $exact = $this->get($url.'?age_group=u9')->assertOk();
        $this->assertSame(['NineJanuary Synthetic', 'NineDecember Synthetic'], $exact->viewData('sections')['u9']['columns']['boys']->getCollection()->pluck('name')->all());
        $age = $this->get($url.'?age_group=u9&age_scope=younger&sort=age&gender=boys')->assertOk();
        $this->assertSame([1, 2, 3, 4], $age->viewData('players')->getCollection()->pluck('position')->all());
        $this->assertSame(1, substr_count($age->getContent(), '<h2 class="h5">'));
        $shared = $this->get($url.'?age_group=u9&age_scope=younger&sort=shared')->assertOk();
        $this->assertSame([1, 2, 3, 4, 5, 6, null], $shared->viewData('players')->getCollection()->pluck('position')->all());
        $reference = $this->get($url.'?age_group=u9&age_scope=younger&comparison_group=reference&gender=boys')->assertOk();
        $this->assertSame([1, 2, 3, 4], $reference->viewData('players')->getCollection()->pluck('position')->all());
        $this->get($url.'?age_group=u18&age_scope=younger')->assertOk()->assertSee('TooOld Synthetic')->assertDontSee('Adult Synthetic');
        foreach (range(1, 8) as $number) $add('Extra'.$number, $number % 2 ? '2018-01-01' : '2019-01-01', 1, 30 - $number);
        $paged = $this->get($url.'?age_group=u9&age_scope=younger&per_page=10&u9_boys_page=2')->assertOk();
        $this->assertSame([11, 12], $paged->viewData('sections')['u9']['columns']['boys']->getCollection()->pluck('position')->all());
        $this->assertSame(1, $paged->viewData('sections')['u9']['columns']['girls']->first()['position']);
        foreach (['', 'unknown', 'other'] as $group) {
            $normal = $this->get($url.'?age_group='.$group.'&age_scope=younger')->assertOk();
            $this->assertSame('group', $normal->viewData('ageScope'));
        }
        $this->getJson($url.'?age_group=u9&age_scope=invalid')->assertUnprocessable();
        $this->assertDatabaseCount('user_players', 0);
        if ($path = getenv('CT_YOUNGER_DIRECTORY_HTML')) file_put_contents($path, $response->getContent());
        // Legacy context scores have no shared calibration across age groups.
        $legacy = ['reason' => null, 'snapshot_stale' => false, 'built_at' => '2026-11-01', 'cohorts' => []];
        $legacyBadges = [];
        foreach ($ratings as $id => $rating) {
            unset($rating['comparable']);
            $rating['cohort'] = 'context'.$id;
            $legacyBadges[$id] = [$rating];
            $legacy['cohorts'][$rating['cohort']] = ['ratings' => [$id => $rating], 'components' => [$rating['component'] => ['members' => [$id]]]];
        }
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn($legacy);
        $this->mock(PlayerSharedAbilityService::class)->shouldReceive('badgeSnapshot')->andReturn($legacyBadges);
        $legacyResponse = $this->get($url.'?age_group=u9&age_scope=younger&per_page=500')->assertOk();
        $this->assertSame([null, null, null], $legacyResponse->viewData('sections')['u9']['columns']['girls']->getCollection()->pluck('position')->all());
        $legacySingle = $this->get($url.'?age_group=u9&age_scope=younger&sort=shared&per_page=500')->assertOk();
        $this->assertTrue($legacySingle->viewData('players')->getCollection()->every(fn ($player) => $player['position'] === null));
    }
}
