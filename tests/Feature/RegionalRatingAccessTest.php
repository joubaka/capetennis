<?php

namespace Tests\Feature;

use App\Models\{Category, CategoryEvent, Event, EventRegion, EventRegionManager, EventType, Player, Team, TeamPlayer, TeamRegion, User};
use App\Services\Performance\{PlayerAbilitySnapshotStore, PlayerSharedAbilityService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegionalRatingAccessTest extends TestCase
{
    use RefreshDatabase;

    private function roster(): array
    {
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Rating roster', 'type' => EventType::TEAM, 'code' => 'rating-roster-'.uniqid(), 'created_at' => now(), 'updated_at' => now()]);
        $event = Event::factory()->create(['eventType' => $type]);
        $field = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => 'U/10 Boys A'])->id]);
        $region = TeamRegion::create(['region_name' => 'Rating test region']);
        $scope = new EventRegion();
        $scope->forceFill(['event_id' => $event->id, 'region_id' => $region->id, 'ordering' => 1])->save();
        $manager = User::factory()->create();
        $assignment = EventRegionManager::create(['event_id' => $event->id, 'event_region_id' => $scope->id, 'region_id' => $region->id, 'user_id' => $manager->id, 'assigned_by' => $manager->id]);
        $team = Team::factory()->create(['category_event_id' => $field->id, 'region_id' => $region->id, 'noProfile' => 0]);
        $player = Player::factory()->create();
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 0]);

        return compact('event', 'field', 'region', 'scope', 'manager', 'assignment', 'team', 'player');
    }

    public function test_regional_badges_return_only_assigned_roster_and_revoke_with_assignment(): void
    {
        $own = $this->roster();
        $other = $this->roster();
        $rating = ['score' => 54.8, 'cohort' => 'u10 boys', 'component' => 'test', 'last_played' => '2026-09-01'];
        $this->partialMock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn([
            'reason' => null, 'built_at' => '2026-10-29 10:00:00', 'snapshot_stale' => false,
            'badge_players' => [$own['player']->id => [$rating], $other['player']->id => [$rating]],
        ]);
        $this->actingAs($own['manager']);
        $this->assertSame([$own['player']->id], array_keys(app(PlayerSharedAbilityService::class)->badgeSnapshot($own['field'])));
        $url = route('backend.player-performance.badges');
        $this->getJson($url.'?category_event_id='.$own['field']->id.'&players[]='.$own['player']->id)
            ->assertOk()->assertJsonPath('ratings.p:'.$own['player']->id.'.0.label', '54.8');
        $this->getJson($url.'?category_event_id='.$own['field']->id.'&players[]='.$other['player']->id)->assertForbidden();
        $this->getJson($url.'?category_event_id='.$other['field']->id.'&players[]='.$other['player']->id)->assertForbidden();
        $own['assignment']->delete();
        $this->getJson($url.'?category_event_id='.$own['field']->id.'&players[]='.$own['player']->id)->assertForbidden();
    }

    public function test_regional_badges_reject_unscoped_and_foreign_identifiers_before_reading_snapshot(): void
    {
        $own = $this->roster();
        $this->partialMock(PlayerAbilitySnapshotStore::class)->shouldNotReceive('current');
        $this->actingAs($own['manager']);
        $url = route('backend.player-performance.badges');
        foreach (['players[]='.$own['player']->id, 'category_id='.$own['field']->category_id,
            'category_event_id='.$own['field']->id.'&registrations[]=1', 'category_event_id='.$own['field']->id.'&fixtures[]=1'] as $query) {
            $this->getJson($url.'?'.$query)->assertForbidden();
        }
        $this->getJson(route('backend.player-performance.ratings'))->assertForbidden();
        $this->assertSame([], app(PlayerSharedAbilityService::class)->badgeSnapshot());
    }

    public function test_same_region_in_another_event_does_not_grant_rating_access(): void
    {
        $own = $this->roster();
        $other = $this->roster();
        $other['team']->update(['region_id' => $own['region']->id]);
        $other['scope']->forceFill(['region_id' => $own['region']->id])->save();
        $this->actingAs($own['manager']);
        $this->getJson(route('backend.player-performance.badges').'?category_event_id='.$other['field']->id.'&players[]='.$other['player']->id)->assertForbidden();
        $this->assertDatabaseCount('team_players', 2);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }
}
