<?php

namespace Tests\Feature;

use App\Models\{Player, User};
use App\Services\Performance\{AllAgeRatingModel, PlayerAbilitySnapshotStore, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerRatingGroupedDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_sections_have_independent_gender_pages_stable_positions_and_birth_years(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01', 'Africa/Johannesburg'));
        if (DB::getDriverName() === 'sqlite') DB::getPdo()->sqliteCreateFunction('YEAR', fn ($date) => $date === null ? null : (int) substr($date, 0, 4));
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $ratings = []; $members = [];
        $add = function (string $name, ?string $birth, ?int $gender, float $score, bool $comparable = true) use (&$ratings, &$members) {
            $player = Player::factory()->create(['name' => $name, 'surname' => 'Synthetic', 'dateOfBirth' => $birth, 'gender' => $gender]);
            $component = $comparable ? 'reference' : 'local';
            $members[$component][] = $player->id;
            $ratings[$player->id] = ['score' => $score, 'component' => $component, 'comparable' => $comparable,
                'cohort' => AllAgeRatingModel::COHORT, 'last_played' => '2026-10-01', 'confidence_label' => 'Low', 'confidence_index' => 25];
            return $player;
        };
        foreach (range(1, 27) as $number) {
            $add(sprintf('Boy%02d', $number), '2013-01-01', 1, 80 - $number);
            $add(sprintf('Girl%02d', $number), '2013-12-31', 2, 80 - $number);
        }
        $add('LocalPlayer', '2013-05-01', 2, 99, false);
        $add('YoungPlayer', '2016-01-01', 1, 65);
        $add('MissingGender', '2013-01-01', null, 60);
        $add('MissingBirth', null, 1, 60);
        $add('FutureBirth', '2027-01-01', 2, 60);
        $add('AdultPlayer', '2000-01-01', 1, 60);
        $youngGroups = [
            'u7' => [['YoungestPlayer', '2020-06-01'], ['SevenJanuary', '2019-01-01'], ['SevenDecember', '2019-12-31']],
            'u8' => [['EightJanuary', '2018-01-01'], ['EightDecember', '2018-12-31']],
            'u9' => [['NineJanuary', '2017-01-01'], ['NineDecember', '2017-12-31']],
            'u10' => [['TenJanuary', '2016-01-01'], ['TenDecember', '2016-12-31']],
        ];
        foreach ($youngGroups as $profiles) foreach ($profiles as [$name, $birth]) $add($name, $birth, 1, 60);
        $components = [];
        foreach ($members as $key => $ids) $components[$key] = ['members' => $ids];
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn(['reason' => null,
            'built_at' => '2026-11-01', 'snapshot_stale' => false, 'all_age' => ['ratings' => $ratings, 'components' => $components, 'reference_component' => 'reference']]);
        $this->mock(PlayerSharedAbilityService::class)->shouldReceive('allAgeBadgeSnapshot')->andReturn($ratings);
        $url = route('backend.player-performance.ratings');
        $first = $this->get($url)->assertOk()->assertSee('data-ctr-age-section="u10"', false)->assertSee('data-ctr-age-section="u13"', false)
            ->assertSee('data-ctr-gender-column="boys"', false)->assertSee('data-ctr-gender-column="girls"', false)
            ->assertSee('Boy01 Synthetic (2013)')->assertSee('Girl01 Synthetic (2013)')->assertSee('MissingBirth Synthetic</a>', false)
            ->assertSee('FutureBirth Synthetic</a>', false)->assertDontSee('FutureBirth Synthetic (2027)')
            ->assertSee('AdultPlayer Synthetic (2000)')->assertSee('data-ctr-age-section="other"', false);
        $sections = $first->viewData('sections');
        $this->assertSame(['u7', 'u8', 'u9', 'u10', 'u13', 'unknown', 'other'], array_keys($sections));
        foreach ($youngGroups as $key => $profiles) {
            $filtered = $this->get($url.'?age_scope=group&age_group='.$key.'&gender=boys')->assertOk();
            $expected = array_column($profiles, 0);
            if ($key === 'u10') $expected[] = 'YoungPlayer';
            $this->assertEqualsCanonicalizing(array_map(fn ($name) => $name.' Synthetic', $expected), $filtered->viewData('players')->getCollection()->pluck('name')->all());
            $this->assertSame([$key], array_keys($filtered->viewData('sections')));
        }
        $boys = $sections['u13']['columns']['boys']; $girls = $sections['u13']['columns']['girls'];
        $this->assertCount(10, $boys); $this->assertCount(10, $girls);
        $this->assertSame(27, $boys->total()); $this->assertSame(28, $girls->total());
        $this->assertSame(range(1, 10), $boys->getCollection()->pluck('position')->all());
        $this->assertSame(range(1, 10), $girls->getCollection()->pluck('position')->all());
        $this->assertStringContainsString('u13_boys_page=2', $boys->url(2));
        $this->assertStringContainsString('u13_girls_page=2', $girls->url(2));
        $second = $this->get($url.'?u13_boys_page=2')->assertOk();
        $this->assertSame(11, $second->viewData('sections')['u13']['columns']['boys']->first()['position']);
        $this->assertSame(1, $second->viewData('sections')['u13']['columns']['girls']->first()['position']);
        $search = $this->get($url.'?age_scope=group&age_group=u13&search=Girl27')->assertOk();
        $this->assertSame(27, $search->viewData('sections')['u13']['columns']['girls']->first()['position']);
        $local = $this->get($url.'?age_scope=group&age_group=u13&gender=girls&u13_girls_page=3')->assertOk()->assertSee('LocalPlayer Synthetic (2013)')->assertSee('Awaiting calibration');
        $this->assertNull($local->viewData('sections')['u13']['columns']['girls']->getCollection()->last()['position']);
        $this->assertSame(['girls'], array_keys($local->viewData('sections')['u13']['columns']));
        $this->getJson($url.'?u13_boys_page=-1')->assertUnprocessable();
        $large = $this->get($url.'?age_scope=group&age_group=u13&per_page=500')->assertOk()->assertSee('Players per list');
        $largeColumns = $large->viewData('sections')['u13']['columns'];
        $this->assertSame(500, $largeColumns['boys']->perPage());
        $this->assertCount(27, $largeColumns['boys']);
        $this->assertCount(28, $largeColumns['girls']);
        $this->assertNull($largeColumns['girls']->getCollection()->last()['position']);
        $this->assertStringContainsString('per_page=500', $largeColumns['boys']->url(2));
        $shared = $this->get($url.'?sort=shared&age_scope=group&age_group=u13&per_page=500')->assertOk()->assertSee('Players per page');
        $this->assertSame(500, $shared->viewData('players')->perPage());
        $this->assertCount(56, $shared->viewData('players'));
        $this->getJson($url.'?per_page=501')->assertUnprocessable();
        $this->getJson($url.'?per_page=all')->assertUnprocessable();
        $this->assertDatabaseCount('user_players', 0);
        if ($path = getenv('CT_GROUPED_DIRECTORY_HTML')) file_put_contents($path, $first->getContent());
    }
}
