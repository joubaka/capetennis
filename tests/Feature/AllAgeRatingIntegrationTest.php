<?php

namespace Tests\Feature;

use App\Models\{Category, CategoryEvent, CategoryEventRegistration, Draw, Event, EventRegion, EventRegionManager, EventType, Player, Registration, Team, TeamPlayer, TeamRegion, User};
use App\Services\Performance\{AllAgeRatingModel, PlayerAbilitySnapshotStore, PlayerRatingBadgeService, PlayerRatingLeaderboardService, PlayerSharedAbilityService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AllAgeRatingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() === 'sqlite') {
            DB::getPdo()->sqliteCreateFunction('YEAR', fn ($date) => $date === null ? null : (int) substr($date, 0, 4));
        }
    }

    private function admin(): User
    {
        Role::findOrCreate('super-user', 'web');
        $user = User::factory()->create()->assignRole('super-user');
        $this->actingAs($user);
        return $user;
    }

    private function rating(?float $score = 62.5): array
    {
        return ['score' => $score, 'cohort' => AllAgeRatingModel::COHORT, 'component' => 'shared-reference',
            'rating_scope' => 'all_age', 'rating_status' => $score === null ? 'disconnected' : 'provisional',
            'comparable' => $score !== null, 'reason' => $score === null ? 'No recent played-match connection to the shared reference group.' : null,
            'played' => 12, 'last_played' => '2026-10-20', 'direct_opponents' => 6, 'confidence_index' => 60,
            'confidence_label' => 'Medium', 'confidence_explanation' => 'Strength and freshness of evidence.',
            'component_inferred' => 0, 'bridge_count' => 0, 'standard_assumed_matches' => 0,
            'age_groups' => ['u10 boys', 'u18 boys'], 'last_direct_match' => '2026-10-20'];
    }

    private function saved(array $ratings): void
    {
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn([
            'reason' => null, 'all_age' => ['ratings' => $ratings], 'cohorts' => [], 'names' => [],
            'built_at' => '2026-10-29 10:00:00', 'snapshot_as_of' => '2026-10-29', 'snapshot_stale' => false,
        ]);
        $this->partialMock(PlayerSharedAbilityService::class)->shouldReceive('allAgeBadgeSnapshot')->andReturn($ratings)
            ->shouldNotReceive('badgeSnapshot')->shouldNotReceive('calculateSnapshot');
    }

    private function field(Event $event, string $label): CategoryEvent
    {
        return CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => $label])->id]);
    }

    public function test_one_badge_score_follows_player_across_age_contexts_and_withheld_never_falls_back(): void
    {
        $this->admin();
        $player = Player::factory()->create();
        $other = Player::factory()->create();
        $this->saved([$player->id => $this->rating(), $other->id => $this->rating(null)]);
        $event = Event::factory()->create();
        $service = app(PlayerRatingBadgeService::class);
        foreach (['U10 Boys A', 'U18 Boys A'] as $label) {
            $field = $this->field($event, $label);
            $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $field->id, 'drawName' => $label]);
            $this->assertSame('62.5', $service->forPlayer($player->id, $draw)['label']);
            $this->assertStringContainsString('compare across ages', $service->forPlayer($player->id, $draw)['title']);
            $this->assertNull($service->forPlayer($other->id, $draw));
        }
        $this->assertDatabaseCount('user_players', 0);
    }

    public function test_profile_directory_orders_shared_rating_across_ages_and_filters_without_recalculation(): void
    {
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-10-29', 'Africa/Johannesburg'));
        $this->admin();
        $young = Player::factory()->create(['name' => 'Younger', 'surname' => 'Shared', 'dateOfBirth' => '2016-01-01', 'gender' => 1]);
        $older = Player::factory()->create(['name' => 'Older', 'surname' => 'Shared', 'dateOfBirth' => '2008-01-01', 'gender' => 1]);
        $disconnected = Player::factory()->create(['name' => 'Disconnected', 'surname' => 'Shared', 'dateOfBirth' => '2013-01-01', 'gender' => 1]);
        $historical = array_replace($this->rating(60), ['historical_connection' => true, 'connection_status' => 'historical',
            'connection_age_days' => 500, 'connection_reference_date' => '2025-05-19', 'confidence_label' => 'Low', 'confidence_index' => 39]);
        $this->saved([$young->id => $this->rating(80), $older->id => $historical, $disconnected->id => $this->rating(null)]);
        $url = route('backend.player-performance.ratings');
        $response = $this->get($url)->assertOk()->assertSeeInOrder(['Younger Shared', 'Older Shared', 'Disconnected Shared'])
            ->assertSee('One provisional singles rating across ages')->assertSee('No recent played-match connection')
            ->assertSee('Low confidence — historical connection')->assertSee('2025-05-19');
        $this->assertSame([80.0, 60.0, null], $response->viewData('players')->getCollection()->map(fn ($row) => $row['rating']['score'] ?? null)->all());
        $this->get($url.'?age_group=u18')->assertOk()->assertSee('60.0/100')->assertDontSee('Younger Shared');
        if (getenv('CT_ALL_AGE_QA_HTML') === '1') {
            file_put_contents(storage_path('framework/testing/all-age-ratings.html'), $response->getContent());
        }
    }

    public function test_event_membership_uses_shared_score_without_importing_foreign_players(): void
    {
        $this->admin();
        $event = Event::factory()->create();
        $player = Player::factory()->create();
        $foreign = Player::factory()->create();
        foreach (['U10 Boys A', 'U18 Boys A'] as $label) {
            $field = $this->field($event, $label);
            $registration = Registration::factory()->create();
            $registration->players()->attach($player);
            CategoryEventRegistration::factory()->create(['category_event_id' => $field->id, 'registration_id' => $registration->id]);
        }
        $this->saved([$player->id => $this->rating(), $foreign->id => $this->rating(90)]);
        $rows = app(PlayerRatingLeaderboardService::class)->build($event)['rows'];
        $this->assertCount(2, $rows);
        $this->assertSame([$player->id, $player->id], $rows->pluck('player_id')->all());
        $this->assertSame([62.5, 62.5], $rows->pluck('rating.score')->all());
    }

    public function test_api_exports_shared_metadata_and_withholds_disconnected_score(): void
    {
        $user = $this->admin();
        $player = Player::factory()->create();
        $this->saved([$player->id => $this->rating(null)]);
        app('auth')->forgetGuards();
        $this->withToken($user->createToken('test', ['cape-ratings:read'])->plainTextToken)
            ->getJson('/api/v1/integrations/jta/players/'.$player->id.'/rating')->assertOk()
            ->assertJsonPath('data.rating_scope', 'all_age')->assertJsonPath('data.rating_status', 'disconnected')
            ->assertJsonPath('data.ratings', [])->assertDontSee('local_score')->assertDontSee('strength');
    }

    public function test_regional_projection_uses_shared_scale_without_foreign_players_or_internal_model_fields(): void
    {
        $type = DB::table('eventtypes')->insertGetId(['name' => 'All-age regional', 'type' => EventType::TEAM, 'code' => 'all-age-region']);
        $event = Event::factory()->create(['eventType' => $type]);
        $field = $this->field($event, 'U10 Boys A');
        $region = TeamRegion::create(['region_name' => 'Synthetic test region']);
        $scope = new EventRegion;
        $scope->forceFill(['event_id' => $event->id, 'region_id' => $region->id, 'ordering' => 1])->save();
        $manager = User::factory()->create();
        $assignment = EventRegionManager::create(['event_id' => $event->id, 'event_region_id' => $scope->id,
            'region_id' => $region->id, 'user_id' => $manager->id, 'assigned_by' => $manager->id]);
        $team = Team::factory()->create(['category_event_id' => $field->id, 'region_id' => $region->id, 'noProfile' => 0]);
        $player = Player::factory()->create();
        $foreign = Player::factory()->create();
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 0]);
        $rating = $this->rating() + ['local_score' => 99, 'strength' => 42];
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn([
            'reason' => null, 'all_age' => ['ratings' => [$player->id => $rating, $foreign->id => $rating]],
            'built_at' => '2026-10-29 10:00:00', 'snapshot_as_of' => '2026-10-29', 'snapshot_stale' => false,
        ]);
        $this->actingAs($manager);
        $projection = app(PlayerSharedAbilityService::class)->allAgeBadgeSnapshot($field);
        $this->assertSame([$player->id], array_keys($projection));
        $this->assertArrayNotHasKey('local_score', $projection[$player->id]);
        $this->assertArrayNotHasKey('strength', $projection[$player->id]);
        $url = route('backend.player-performance.badges').'?category_event_id='.$field->id.'&players[]=';
        $this->getJson($url.$player->id)->assertOk()->assertJsonPath('ratings.p:'.$player->id.'.0.label', '62.5');
        $this->getJson($url.$foreign->id)->assertForbidden();
        $assignment->delete();
        $this->getJson($url.$player->id)->assertForbidden();
    }
}
