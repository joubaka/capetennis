<?php

namespace Tests\Feature\Ranking;

use App\Domain\Ranking\Enums\RankingStatus;
use App\Models\{Category, Player, RankingList, Series, SeriesRanking, SiteSetting, User};
use App\Services\Performance\{PlayerAbilitySnapshotStore, PlayerRatingAccess, SeriesCtrLeaderboard};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Cache, Gate};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeriesCtrLeaderboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function superUser(): User
    {
        Role::findOrCreate('super-user', 'web');
        return User::factory()->create()->assignRole('super-user');
    }

    private function ranking(Player $player, ?Series $series = null, string $run = 'live', RankingStatus $status = RankingStatus::Published): Series
    {
        $series ??= Series::factory()->create(['leaderboard_published' => true]);
        $category = Category::factory()->create();
        $list = RankingList::factory()->create(['series_id' => $series->id, 'category_id' => $category->id]);
        SeriesRanking::create(['series_id' => $series->id, 'ranking_list_id' => $list->id, 'category_id' => $category->id,
            'player_id' => $player->id, 'rank_position' => 1, 'total_points' => 1000,
            'status' => $status->value, 'run_id' => $run, 'published_at' => $status === RankingStatus::Published ? now() : null,
            'meta_json' => ['events_played' => 1]]);
        return $series;
    }

    private function snapshot(array $scores, array $overrides = []): array
    {
        $ratings = [];
        foreach ($scores as $id => $score) {
            $ratings[$id] = ['component' => 'shared', 'comparable' => true, 'score' => $score, 'confidence_label' => 'Low'];
        }
        return array_replace_recursive(['reason' => null, 'all_age' => ['reference_component' => 'shared',
            'components' => ['shared' => ['members' => array_keys($scores), 'converged' => true]], 'ratings' => $ratings]], $overrides);
    }

    public function test_guests_and_nonmembers_never_load_or_render_private_ctr_even_with_requested_membership_parameters(): void
    {
        Cache::store('array')->flush();
        $player = Player::factory()->create();
        $series = $this->ranking($player);
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldNotReceive('current');
        $url = route('frontend.ranking.show', $series).'?member=1&ctr=1';
        $this->get($url)->assertOk()->assertDontSee('data-ctr-top-ten', false)->assertDontSee('data-ctr-rating', false);
        $this->actingAs(User::factory()->create())->get($url)->assertOk()
            ->assertDontSee('data-ctr-top-ten', false)->assertDontSee('data-ctr-rating', false);
        SiteSetting::set(PlayerRatingAccess::SETTING, '1');
        // A feature switch without an authoritative entitlement provider grants nothing.
        $this->get($url)->assertOk()->assertDontSee('data-ctr-top-ten', false)->assertDontSee('data-ctr-rating', false);
    }

    public function test_future_discovery_entitlement_is_separate_fails_closed_and_does_not_grant_player_history(): void
    {
        Cache::store('array')->flush();
        $member = User::factory()->create();
        $other = User::factory()->create();
        $player = Player::factory()->create();
        $series = $this->ranking($player);
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->once()->andReturn($this->snapshot([$player->id => 72.4]));
        $active = true;
        Gate::define('player-premium-rating-discovery', function (User $user) use ($member, &$active) { return $active && $user->id === $member->id; });
        $url = route('frontend.ranking.show', $series);
        $this->actingAs($member)->get($url)->assertOk()->assertDontSee('data-ctr-top-ten', false);
        SiteSetting::set(PlayerRatingAccess::SETTING, '1');
        $this->actingAs($other)->get($url)->assertOk()->assertDontSee('data-ctr-top-ten', false);
        $this->actingAs($member)->get($url)->assertOk()->assertSee('Top 10 CTR')->assertSee('CTR 72.4')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertFalse(app(PlayerRatingAccess::class)->allows($member, $player));
        $this->assertDatabaseMissing('user_players', ['user_id' => $member->id, 'player_id' => $player->id]);
        $active = false;
        $this->get($url)->assertOk()->assertDontSee('data-ctr-top-ten', false)->assertDontSee('data-ctr-rating', false);
    }

    public function test_super_user_sees_ctr_only_for_current_public_series_participants_without_changing_rankings(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-01', 'Africa/Johannesburg'));
        $public = Player::factory()->create(['dateOfBirth' => '2015-12-31', 'gender' => 1]);
        $series = $this->ranking($public);
        $draft = Player::factory()->create();
        $this->ranking($draft, $series, 'draft', RankingStatus::Calculated);
        $outsider = Player::factory()->create();
        $this->ranking($outsider);
        $unlinked = Player::factory()->create();
        $this->ranking($unlinked, $series);
        $local = Player::factory()->create();
        $this->ranking($local, $series);
        $snapshot = $this->snapshot([$public->id => 72.4, $draft->id => 99.9, $outsider->id => 98.8, $local->id => 93.1]);
        $snapshot['all_age']['ratings'][$local->id] = ['component' => 'local', 'comparable' => false, 'score' => 93.1];
        $snapshot['all_age']['components']['local'] = ['members' => [$local->id], 'converged' => true];
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->once()->andReturn($snapshot);
        $before = SeriesRanking::orderBy('id')->get()->toArray();
        $response = $this->actingAs($this->superUser())->get(route('frontend.ranking.show', $series))->assertOk()
            ->assertSee('Top 10 CTR')->assertSee('U11 · born 2015 · Boys')->assertSee('CTR 72.4')
            ->assertDontSee('CTR 93.1')->assertDontSee('99.9/100')->assertDontSee('98.8/100')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertCount(1, $response->viewData('ctr')['ratings']);
        $this->assertSame([$public->id], array_column($response->viewData('ctr')['groups']['u11|boys']['players'], 'id'));
        $this->assertSame($before, SeriesRanking::orderBy('id')->get()->toArray());
        if (getenv('CT_CTR_QA_HTML') === '1') file_put_contents(storage_path('framework/testing/ctr-top-ten.html'), $response->getContent());
    }

    public function test_top_ten_limits_each_age_and_gender_with_stable_ties_and_calendar_year_rollover(): void
    {
        $user = $this->superUser();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-01-01', 'Africa/Johannesburg'));
        $players = collect();
        for ($index = 0; $index < 12; $index++) {
            $players->push(Player::factory()->create(['name' => 'Same', 'surname' => sprintf('Name%02d', 11 - $index),
                'gender' => 1, 'dateOfBirth' => $index % 2 ? '2015-12-31' : '2015-01-01']));
        }
        $girl = Player::factory()->create(['dateOfBirth' => '2015-12-31', 'gender' => 2]);
        $young = Player::factory()->create(['dateOfBirth' => '2016-01-01', 'gender' => 1]);
        $players->push($girl, $young);
        $scores = $players->mapWithKeys(fn ($player) => [$player->id => 80.0])->all();
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->times(3)->andReturn($this->snapshot($scores));
        $service = app(SeriesCtrLeaderboard::class);
        $first = $service->build($user, $players);
        $this->assertCount(10, $first['groups']['u11|boys']['players']);
        $this->assertSame('Name00', $first['groups']['u11|boys']['players'][0]['surname']);
        $this->assertSame([$girl->id], array_column($first['groups']['u11|girls']['players'], 'id'));
        $this->assertSame([$young->id], array_column($first['groups']['u10|boys']['players'], 'id'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-12-31', 'Africa/Johannesburg'));
        $this->assertSame($first['groups'], $service->build($user, $players->reverse())['groups']);
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2027-01-01', 'Africa/Johannesburg'));
        $next = $service->build($user, $players);
        $this->assertCount(10, $next['groups']['u12|boys']['players']);
        $this->assertSame('U12 · born 2015 · Boys', $next['groups']['u12|boys']['label']);
        $this->assertSame([$young->id], array_column($next['groups']['u11|boys']['players'], 'id'));
    }

    public function test_invalidated_nonconverged_and_orphaned_group_evidence_cannot_populate_top_ten(): void
    {
        $user = $this->superUser();
        $player = Player::factory()->create();
        $base = $this->snapshot([$player->id => 75.0]);
        $invalid = $base; $invalid['reason'] = 'Publication changed';
        $failed = $base; $failed['all_age']['components']['shared']['converged'] = false;
        $orphan = $base; $orphan['all_age']['components']['shared']['members'] = [];
        $nullScore = $base; $nullScore['all_age']['ratings'][$player->id]['score'] = null;
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->times(4)->andReturn($invalid, $failed, $orphan, $nullScore);
        foreach (range(1, 4) as $_) {
            $result = app(SeriesCtrLeaderboard::class)->build($user, collect([$player]));
            $this->assertSame([], $result['groups']);
            $this->assertSame([], $result['ratings']);
        }
    }
}
