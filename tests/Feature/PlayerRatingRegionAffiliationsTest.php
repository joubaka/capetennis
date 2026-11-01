<?php

namespace Tests\Feature;

use App\Models\{NoProfileTeamPlayer, Player, Team, TeamPlayer, TeamRegion, User};
use App\Services\Performance\{AllAgeRatingModel, PlayerAbilitySnapshotStore, PlayerRatingRegionAffiliations, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerRatingRegionAffiliationsTest extends TestCase
{
    use RefreshDatabase;

    private function roster(Player $player, string $regionName, bool $imported = false, bool $linked = true): Team
    {
        $region = TeamRegion::create(['region_name' => $regionName]);
        $team = Team::factory()->create(['region_id' => $region->id]);
        if ($imported) {
            NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => $player->name, 'surname' => $player->surname,
                'rank' => 1, 'pay_status' => 0, 'player_profile' => $linked ? $player->id : null]);
        } else {
            TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 0]);
        }
        return $team;
    }

    public function test_visible_players_regions_use_one_bounded_batch_and_exact_recorded_links(): void
    {
        $direct = Player::factory()->create(); $linked = Player::factory()->create();
        $unlinked = Player::factory()->create(); $offPage = Player::factory()->create();
        $this->roster($direct, ' Alpha Region ');
        $this->roster($direct, 'alpha region', true);
        foreach (['Beta Region', 'Gamma Region', 'Delta Region'] as $region) $this->roster($direct, $region);
        $this->roster($direct, '   ');
        $this->roster($linked, 'Linked Imported Region', true);
        $this->roster($unlinked, 'Must not match by name', true, false);
        $this->roster($offPage, 'Off-page Region');
        $counts = [DB::table('players')->count(), DB::table('team_players')->count(), DB::table('no_profile_team_players')->count(), DB::table('user_players')->count()];
        DB::enableQueryLog(); DB::flushQueryLog();
        $regions = app(PlayerRatingRegionAffiliations::class)->forPlayers([$direct->id, $linked->id, $unlinked->id]);
        $queries = DB::getQueryLog(); DB::disableQueryLog();
        $this->assertCount(1, $queries);
        $this->assertEqualsCanonicalizing([$direct->id, $linked->id], array_keys($regions));
        $this->assertCount(3, $regions[$direct->id]['names']);
        $this->assertSame(1, $regions[$direct->id]['additional']);
        $this->assertSame(['Alpha Region', 'Beta Region', 'Delta Region'], $regions[$direct->id]['names']);
        $this->assertSame(['Linked Imported Region'], $regions[$linked->id]['names']);
        $this->assertNotContains($offPage->id, $queries[0]['bindings']);
        DB::enableQueryLog(); DB::flushQueryLog();
        app(PlayerRatingRegionAffiliations::class)->forPlayers([$direct->id]);
        $this->assertCount(1, DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame($counts, [DB::table('players')->count(), DB::table('team_players')->count(), DB::table('no_profile_team_players')->count(), DB::table('user_players')->count()]);
        $this->assertSame([], app(PlayerRatingRegionAffiliations::class)->forPlayers([]));
    }

    public function test_directory_adds_escaped_region_suffix_without_changing_positions_or_membership(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-01', 'Africa/Johannesburg'));
        if (DB::getDriverName() === 'sqlite') DB::getPdo()->sqliteCreateFunction('YEAR', fn ($date) => $date === null ? null : (int) substr($date, 0, 4));
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $boy = Player::factory()->create(['name' => 'BoyRegion', 'surname' => 'Synthetic', 'dateOfBirth' => '2018-01-01', 'gender' => 1]);
        $girl = Player::factory()->create(['name' => 'GirlRegion', 'surname' => 'Synthetic', 'dateOfBirth' => '2017-01-01', 'gender' => 2]);
        $none = Player::factory()->create(['name' => 'NoRegion', 'surname' => 'Synthetic', 'dateOfBirth' => '2019-01-01', 'gender' => 1]);
        $hostile = '<img src=x onerror=alert(1)>';
        $this->roster($boy, $hostile);
        $this->roster($boy, 'Northern Cape and Coastal Schools Regional Tennis Association 2026');
        $this->roster($girl, 'Cavaliers Platteland Schools and Regional Tennis Development Association 2026', true);
        $ratings = [];
        foreach ([$boy, $girl, $none] as $index => $player) $ratings[$player->id] = ['score' => 80.0 - $index, 'component' => 'reference', 'comparable' => true,
            'cohort' => AllAgeRatingModel::COHORT, 'last_played' => '2026-10-01', 'confidence_label' => 'Low', 'confidence_index' => 25];
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn(['reason' => null, 'snapshot_stale' => false, 'built_at' => '2026-11-01',
            'all_age' => ['ratings' => $ratings, 'components' => ['reference' => ['members' => array_keys($ratings)]], 'reference_component' => 'reference']]);
        $this->mock(PlayerSharedAbilityService::class)->shouldReceive('allAgeBadgeSnapshot')->andReturn($ratings);
        $url = route('backend.player-performance.ratings');
        foreach (['grouped', 'shared', 'score', 'age'] as $sort) {
            $response = $this->get($url.'?age_group=u9&age_scope=younger&sort='.$sort)->assertOk()
                ->assertSee('BoyRegion Synthetic (2018)')->assertSee($hostile)->assertDontSee($hostile, false)
                ->assertSee('Recorded regional team affiliations; may include past seasons')->assertSee('Region not recorded');
            $this->assertSame(3, $response->viewData('players')->total());
            $this->assertCount(3, $response->viewData('players'));
            if ($sort === 'grouped') {
                $this->assertSame([1, 2], $response->viewData('sections')['u9']['columns']['boys']->getCollection()->pluck('position')->all());
                $this->assertSame(1, $response->viewData('sections')['u9']['columns']['girls']->first()['position']);
                if ($path = getenv('CT_REGION_DIRECTORY_HTML')) file_put_contents($path, $response->getContent());
            } else {
                $this->assertSame([1, 2, 3], $response->viewData('players')->getCollection()->pluck('position')->all());
            }
        }
        $this->assertDatabaseCount('user_players', 0);
    }
}
