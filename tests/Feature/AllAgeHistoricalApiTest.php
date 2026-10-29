<?php

namespace Tests\Feature;

use App\Models\{Player, User};
use App\Services\Performance\{AllAgeRatingModel, PlayerAbilitySnapshotStore, PlayerRatingBadgeService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AllAgeHistoricalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_and_badge_carry_the_low_confidence_historical_connection_without_internal_strength(): void
    {
        Role::findOrCreate('super-user', 'web');
        $user = User::factory()->create()->assignRole('super-user');
        $player = Player::factory()->create();
        $rating = ['score' => 61.5, 'strength' => 0.5, 'local_score' => 61.5, 'cohort' => AllAgeRatingModel::COHORT,
            'component' => 'shared', 'comparable' => true, 'rating_status' => 'provisional', 'rating_scope' => 'all_age',
            'connection_status' => 'historical', 'historical_connection' => true, 'connection_age_days' => 500,
            'connection_reference_date' => '2025-05-19', 'confidence_index' => 39, 'confidence_label' => 'Low',
            'played' => 20, 'last_played' => '2026-10-01', 'component_inferred' => 0, 'bridge_count' => 0];
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn([
            'reason' => null, 'built_at' => '2026-10-29 10:00:00', 'snapshot_stale' => false, 'all_age' => ['ratings' => [$player->id => $rating]],
        ]);
        $this->actingAs($user);
        $badge = app(PlayerRatingBadgeService::class)->forPlayer($player->id);
        $this->assertSame('61.5 | Low · old link', $badge['display_label']);
        $this->assertStringContainsString('500 days old', $badge['title']);
        $this->assertArrayNotHasKey('strength', $badge);
        app('auth')->forgetGuards();
        $this->withToken($user->createToken('test', ['cape-ratings:read'])->plainTextToken)
            ->getJson('/api/v1/integrations/jta/players/'.$player->id.'/rating')->assertOk()
            ->assertJsonPath('data.ratings.0.confidence_label', 'Low')
            ->assertJsonPath('data.ratings.0.connection_status', 'historical')
            ->assertJsonPath('data.ratings.0.historical_connection', true)
            ->assertJsonPath('data.ratings.0.connection_age_days', 500)
            ->assertJsonPath('data.ratings.0.connection_reference_date', '2025-05-19')
            ->assertDontSee('strength')->assertDontSee('local_score');
    }
}
