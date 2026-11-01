<?php

namespace Tests\Feature;

use App\Models\{NoProfileTeamPlayer, Player, Team, TeamPlayer, TeamRegion, User};
use App\Services\Performance\{AllAgeRatingModel, PlayerAbilitySnapshotStore, PlayerRatingRegionAffiliations, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerRatingRegionFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_region_filters_exact_affiliations_before_rank_and_preserves_combined_age_pagination(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01', 'Africa/Johannesburg'));
        if (DB::getDriverName() === 'sqlite') DB::getPdo()->sqliteCreateFunction('YEAR', fn ($date) => $date === null ? null : (int) substr($date, 0, 4));
        $url = route('backend.player-performance.ratings');
        $this->getJson($url.'?region=Hidden')->assertUnauthorized();
        $this->actingAs(User::factory()->create())->getJson($url.'?region=Hidden')->assertForbidden();
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $ratings = []; $members = [];
        $add = function ($name, $gender, $birth, $score, $comparable = true) use (&$ratings, &$members) {
            $player = Player::factory()->create(['name' => $name, 'surname' => 'Synthetic', 'gender' => $gender, 'dateOfBirth' => $birth]);
            $component = $comparable ? 'reference' : 'local';
            $members[$component][] = $player->id;
            $ratings[$player->id] = ['score' => (float) $score, 'component' => $component, 'comparable' => $comparable,
                'cohort' => AllAgeRatingModel::COHORT, 'last_played' => '2026-10-01', 'confidence_index' => 25, 'confidence_label' => 'Low'];
            return $player;
        };
        $roster = function (Player $player, string $label, bool $imported = false, bool $linked = true) {
            $team = Team::factory()->create(['region_id' => TeamRegion::create(['region_name' => $label])->id]);
            if ($imported) NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => $player->name, 'surname' => $player->surname, 'rank' => 1, 'pay_status' => 0, 'player_profile' => $linked ? $player->id : null]);
            else TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 0]);
        };
        $label = 'Selected Province Schools Regional Tennis Affiliation for Coastal and Inland Junior Players and Development Teams Season 2026';
        foreach (range(1, 12) as $number) {
            $boy = $add(sprintf('Boy%02d', $number), 1, $number % 2 ? '2017-01-01' : '2018-12-31', 80 - $number);
            $roster($boy, $label);
        }
        foreach (['AAA affiliation', 'BBB affiliation', 'CCC affiliation'] as $otherLabel) $roster($boy, $otherLabel);
        $girl = $add('LinkedGirl', 2, '2019-01-01', 75);
        $roster($girl, ' '.mb_strtolower($label).' ', true);
        $unlinked = $add('UnlinkedGirl', 2, '2018-01-01', 90);
        $roster($unlinked, $label, true, false);
        $roster($unlinked, 'Different Region');
        $outside = $add('OutsideBoy', 1, '2017-01-01', 99);
        $roster($outside, 'Different Region');
        $local = $add('LocalGirl', 2, '2019-01-01', 99, false);
        $roster($local, $label);
        $unrated = Player::factory()->create();
        $roster($unrated, 'Hidden Unrated Region');
        $orphan = Player::factory()->create();
        $roster($orphan, 'Hidden Orphan Region');
        $ratings[$orphan->id] = array_replace($ratings[$outside->id], ['component' => 'missing']);
        $hostile = '<script>region</script>';
        $roster($outside, $hostile);
        $components = [];
        foreach ($members as $component => $ids) $components[$component] = ['members' => $ids];
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn(['reason' => null, 'built_at' => '2026-11-01', 'snapshot_stale' => false,
            'all_age' => ['ratings' => $ratings, 'components' => $components, 'reference_component' => 'reference']]);
        $this->mock(PlayerSharedAbilityService::class)->shouldReceive('allAgeBadgeSnapshot')->andReturn($ratings);
        $query = '?age_group=u9&age_scope=younger&region='.urlencode($label);
        $first = $this->get($url.$query)->assertOk()->assertDontSee('OutsideBoy Synthetic')->assertDontSee('UnlinkedGirl Synthetic')
            ->assertSee('Recorded team affiliations, including past seasons.')->assertDontSee('Hidden Unrated Region')->assertDontSee('Hidden Orphan Region')
            ->assertSee($hostile)->assertDontSee($hostile, false);
        $this->assertSame(14, $first->viewData('players')->total());
        $options = $first->viewData('regionOptions');
        $this->assertCount(1, array_filter($options, fn ($option) => mb_strtolower($option) === mb_strtolower($label)));
        $columns = $first->viewData('sections')['u9']['columns'];
        $this->assertSame(range(1, 10), $columns['boys']->getCollection()->pluck('position')->all());
        $this->assertSame([1, null], $columns['girls']->getCollection()->pluck('position')->all());
        $second = $this->get($url.$query.'&u9_boys_page=2')->assertOk();
        $secondBoys = $second->viewData('sections')['u9']['columns']['boys'];
        $this->assertSame([11, 12], $secondBoys->getCollection()->pluck('position')->all());
        $this->assertSame(['AAA affiliation', 'BBB affiliation', 'CCC affiliation'], $secondBoys->last()['region_affiliations']['names']);
        $this->assertSame(1, $secondBoys->last()['region_affiliations']['additional']);
        $this->assertStringContainsString('region='.rawurlencode($label), $columns['boys']->url(2));
        $searched = $this->get($url.$query.'&search=Boy12')->assertOk();
        $this->assertSame(12, $searched->viewData('sections')['u9']['columns']['boys']->first()['position']);
        $large = $this->get($url.$query.'&per_page=500')->assertOk();
        $this->assertCount(12, $large->viewData('sections')['u9']['columns']['boys']);
        $this->assertSame(500, $large->viewData('sections')['u9']['columns']['boys']->perPage());
        foreach (['shared', 'score', 'age'] as $sort) {
            $mode = $this->get($url.$query.'&sort='.$sort.'&per_page=500')->assertOk();
            $this->assertSame(14, $mode->viewData('players')->total());
        }
        $this->getJson($url.'?region=Hidden Unrated Region')->assertUnprocessable();
        $this->getJson($url.'?region=Unknown')->assertUnprocessable();
        $this->getJson($url.'?region='.str_repeat('x', 256))->assertUnprocessable();
        $this->assertDatabaseCount('user_players', 0);
        if ($path = getenv('CT_REGION_FILTER_HTML')) file_put_contents($path, $large->getContent());
    }

    public function test_region_options_are_bounded_without_loading_roster_rows(): void
    {
        $player = Player::factory()->create();
        $teams = []; $regions = []; $links = [];
        foreach (range(1, 505) as $id) {
            $regions[] = ['id' => $id, 'region_name' => sprintf('Region %03d', $id)];
            $teams[] = ['id' => $id, 'name' => 'Synthetic', 'region_id' => $id];
            $links[] = ['team_id' => $id, 'player_id' => $player->id];
        }
        DB::table('team_regions')->insert($regions);
        DB::table('teams')->insert($teams);
        DB::table('team_players')->insert($links);
        DB::enableQueryLog(); DB::flushQueryLog();
        $options = app(PlayerRatingRegionAffiliations::class)->options([$player->id]);
        $this->assertCount(500, $options);
        $this->assertCount(1, DB::getQueryLog());
        DB::disableQueryLog();
    }
}
