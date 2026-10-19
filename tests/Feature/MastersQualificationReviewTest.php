<?php

namespace Tests\Feature;

use App\Models\{Category, CategoryEvent, CategoryResult, Draw, Event, NoProfileTeamPlayer, Player, Registration, Team, TeamFixture, TeamPlayer, TeamRegion, TeamTie, User};
use App\Services\Performance\{MastersQualificationReviewService, PlayerAbilitySnapshotStore, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MastersQualificationReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-18 12:00:00', 'Africa/Johannesburg'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function admin(): void
    {
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
    }

    private function group(array $players, string $component = 'group-a'): array
    {
        $ratings = []; $own = [];
        foreach ($players as $offset => $player) {
            $ratings[$player->id] = ['score' => 90.0 - $offset, 'strength' => 2 - $offset / 10, 'component' => $component];
            $own[$player->id] = ['played' => 5, 'last_played' => '2026-03-01'];
        }
        return ['ratings' => $ratings, 'players' => $own, 'components' => [$component => ['members' => array_map(fn ($player) => $player->id, $players), 'converged' => true]], 'baseline' => []];
    }

    private function snapshot(array $cohorts, array $overrides = []): array
    {
        $snapshot = array_replace(['cohorts' => $cohorts, 'names' => Player::all()->mapWithKeys(fn ($player) => [$player->id => $player->name.' '.$player->surname])->all(),
            'reason' => null, 'built_at' => '2026-10-18 08:00 SAST', 'snapshot_as_of' => '2026-10-18', 'snapshot_stale' => false, 'source_matches' => []], $overrides);
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn($snapshot);
        $this->mock(PlayerSharedAbilityService::class)->shouldNotReceive('calculateSnapshot', 'identityReviewSnapshot', 'forPlayer', 'validationSnapshot');
        return $snapshot;
    }

    private function players(int $count = 12): array
    {
        return Player::factory()->female()->count($count)->create(['dateOfBirth' => '2014-02-01'])->all();
    }

    private function url(array $query = []): string
    {
        return route('backend.player-performance.masters-review', $query);
    }

    public function test_only_super_users_can_access_the_read_only_route(): void
    {
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldNotReceive('current');
        $this->getJson($this->url())->assertUnauthorized();
        Role::findOrCreate('admin', 'web');
        $this->actingAs(User::factory()->create()->assignRole('admin'));
        $this->getJson($this->url())->assertForbidden();
        $this->postJson($this->url())->assertMethodNotAllowed();
    }

    public function test_all_eight_divisions_and_calendar_year_flags_preserve_complete_group_positions(): void
    {
        $this->admin(); $players = $this->players();
        $players[7]->update(['dateOfBirth' => '2013-12-31']);
        $players[8]->update(['dateOfBirth' => '2015-01-01']);
        $players[9]->update(['dateOfBirth' => 'invalid']);
        $players[10]->update(['dateOfBirth' => '2027-01-01']);
        $players[11]->update(['gender' => 1]);
        $this->snapshot(['u12 girls' => $this->group($players)]);
        $response = $this->get($this->url(['cohort' => 'u12 girls']))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $response->assertSee('2026-12-07')->assertSee('Over the division age')->assertSee('Younger player')->assertSee('Birth date is missing or invalid.')->assertSee('Birth date is invalid or in the future.')->assertSee('Profile gender is missing or conflicts')->assertSee('Primary-school attendance has not been verified.');
        $this->assertSame(MastersQualificationReviewService::DIVISIONS, array_keys($response->viewData('summary')));
        $rows = $response->viewData('groups')->items()[0]['rows'];
        $this->assertSame(range(1, 12), array_column($rows, 'position'));
        $this->assertCount(5, array_filter($rows, fn ($row) => $row['band']));
        $this->assertSame(13, $rows[7]['age']);
        $this->assertSame(11, $rows[8]['age']);
    }

    public function test_disconnected_groups_stay_separate_and_ties_beyond_twelfth_are_visible(): void
    {
        $this->admin(); $players = $this->players(16);
        $a = $this->group(array_slice($players, 0, 14)); $b = $this->group(array_slice($players, 14), 'group-b');
        foreach (array_slice($players, 9, 5) as $offset => $player) { $a['ratings'][$player->id]['score'] = 81.04 - $offset / 100; }
        $data = ['ratings' => $a['ratings'] + $b['ratings'], 'players' => $a['players'] + $b['players'], 'components' => $a['components'] + $b['components'], 'baseline' => []];
        $this->snapshot(['u12 girls' => $data, 'u12 boys' => $this->group([$players[0]], 'boys-context')]);
        $response = $this->get($this->url(['cohort' => 'u12 girls']))->assertOk()->assertSee('Separate comparison groups cannot be ranked')->assertSee('tie rule required');
        $groups = $response->viewData('groups')->items();
        $this->assertCount(2, $groups); $this->assertCount(14, $groups[0]['rows']);
        $this->assertSame([1, 2], array_column($groups[1]['rows'], 'position'));
        $this->assertCount(5, array_filter($groups[0]['rows'], fn ($row) => $row['boundary_tie']));
        $this->assertSame('u12 girls', $response->viewData('selected'));
    }

    public function test_missing_profile_stale_snapshot_and_invalid_cohort_fail_closed(): void
    {
        $this->admin(); $players = $this->players(); $data = $this->group($players);
        $missing = $players[8]->id; DB::table('players')->where('id', $missing)->delete();
        $this->snapshot(['u12 girls' => $data], ['snapshot_stale' => true, 'snapshot_as_of' => '2026-10-17']);
        $this->get($this->url(['cohort' => 'u12 girls']))->assertOk()->assertSee('snapshot is stale')->assertSee('Current player profile is missing');
        $this->getJson($this->url(['cohort' => 'u14 girls']))->assertUnprocessable();
        $this->get($this->url(['cohort' => 'u12 girls', 'date' => '2025-12-07']))->assertOk()->assertSee('evidence after the proposed Masters date');
    }

    public function test_pending_snapshot_does_not_show_contenders_or_request_a_calculation(): void
    {
        $this->admin(); $this->snapshot([], ['reason' => 'Saved snapshot unavailable.', 'snapshot_stale' => true]);
        $this->get($this->url())->assertOk()->assertSee('No qualification assessment is available')->assertSee('No usable saved comparison group');
    }

    public function test_canonical_excluded_match_and_unpublished_finishing_result_are_reported_without_source_writes(): void
    {
        $this->admin(); $players = $this->players(); $target = $players[7];
        $event = Event::factory()->create(['published' => true, 'results_published' => false, 'start_date' => '2026-03-01', 'end_date' => '2026-03-02']);
        $category = Category::factory()->create(['name' => 'U12 Girls']);
        $field = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => $category->id]);
        $r1 = TeamRegion::create(['region_name' => 'Region One']); $r2 = TeamRegion::create(['region_name' => 'Region Two']);
        $event->regions()->attach([$r1->id, $r2->id]);
        $home = Team::factory()->create(['category_event_id' => $field->id, 'region_id' => $r1->id]);
        $away = Team::factory()->create(['category_event_id' => $field->id, 'region_id' => $r2->id]);
        TeamPlayer::create(['team_id' => $home->id, 'player_id' => $target->id, 'rank' => 1]);
        $imported = NoProfileTeamPlayer::create(['team_id' => $away->id, 'name' => 'Unverified', 'surname' => 'Opponent', 'rank' => 1, 'player_profile' => $players[9]->id, 'pay_status' => 1]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $field->id, 'published' => true, 'drawName' => 'U12 Girls Singles']);
        $tie = TeamTie::factory()->published()->create(['draw_id' => $draw->id, 'home_team_id' => $home->id, 'away_team_id' => $away->id]);
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'team_tie_id' => $tie->id, 'fixture_type' => 1, 'numSets' => 3, 'match_status' => 1, 'round_nr' => 1, 'match_nr' => 1]);
        $fixture->fixturePlayers()->create(['slot_no' => 1, 'team1_id' => $target->id, 'team2_no_profile_id' => $imported->id]);
        foreach ([1, 2] as $set) { $fixture->teamResults()->create(['set_nr' => $set, 'team1_score' => 6, 'team2_score' => 1]); }
        $registration = Registration::factory()->create(); $registration->players()->attach($target);
        CategoryResult::create(['event_id' => $event->id, 'category_id' => $category->id, 'registration_id' => $registration->id, 'position' => 1]);
        $this->snapshot(['u12 girls' => $this->group($players)]);
        $capture = function (): array {
            $out = [];
            foreach (['events', 'players', 'team_fixture_players', 'team_fixture_results', 'category_results', 'historical_identity_proposals', 'player_ability_snapshots', 'player_ability_refresh_state'] as $table) { $out[$table] = DB::table($table)->get()->toJson(); }
            return $out;
        };
        $before = $capture();
        $response = $this->get($this->url(['cohort' => 'u12 girls']))->assertOk()->assertSee('Canonical exclusion')->assertSee('Unpublished result gap')->assertSee('publication is off');
        $issues = $response->viewData('evidence')['rows'];
        $this->assertTrue(collect($issues)->contains(fn ($row) => $row['kind'] === 'team' && in_array($target->id, $row['players'], true) && $row['status'] === 'Canonical exclusion'));
        $this->assertSame($before, $capture());
    }
}
