<?php

namespace Tests\Feature;

use App\Models\{Player, User};
use App\Services\Performance\{PlayerAbilitySnapshotStore, PlayerSharedAbilityService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CapePlayerRatingApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_session_non_super_and_insufficient_tokens_cannot_read_private_ratings(): void
    {
        $path = '/api/v1/integrations/jta/players/1/rating';
        $this->getJson($path)->assertUnauthorized();
        Role::findOrCreate('super-user', 'web');
        foreach ([[false, ['cape-ratings:read']], [true, ['jta-results:read']], [true, ['*']]] as [$super, $abilities]) {
            $user = User::factory()->create();
            if ($super) { $user->assignRole('super-user'); }
            app('auth')->forgetGuards();
            $this->withToken($user->createToken('test', $abilities)->plainTextToken)->getJson($path)->assertForbidden();
        }
        app('auth')->forgetGuards();
        $this->withHeader('Authorization', '')->actingAs($user)->getJson($path)->assertForbidden();
    }

    public function test_only_saved_rating_fields_and_actual_calculation_time_are_exported(): void
    {
        $user = User::factory()->create();
        Role::findOrCreate('super-user', 'web');
        $user->assignRole('super-user');
        $player = Player::factory()->create();
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->once()->andReturn([
            'reason' => null, 'built_at' => '2026-10-17 12:30:00', 'snapshot_stale' => true,
        ]);
        $this->mock(PlayerSharedAbilityService::class)->shouldReceive('badgeSnapshot')->once()->andReturn([
            $player->id => [['score' => 73.25, 'cohort' => 'boys', 'component' => 3,
                'confidence_label' => 'Moderate', 'baseline_status' => 'Linked to main-trial baseline', 'private' => 'withheld']],
        ]);
        $this->withToken($user->createToken('test', ['cape-ratings:read'])->plainTextToken)
            ->getJson('/api/v1/integrations/jta/players/'.$player->id.'/rating')
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertExactJson(['data' => ['source' => 'cape_tennis', 'cape_tennis_player_id' => $player->id,
                'model_version' => (string) PlayerSharedAbilityService::VERSION,
                'calculated_at' => '2026-10-17T12:30:00+02:00', 'stale' => true,
                'ratings' => [['score' => 73.25, 'cohort' => 'boys', 'component' => '3',
                    'confidence_label' => 'Moderate', 'baseline_status' => 'Linked to main-trial baseline']]]]);
    }

    public function test_bearer_super_user_reaches_real_saved_badge_reader(): void
    {
        $user = User::factory()->create();
        Role::findOrCreate('super-user', 'web');
        $user->assignRole('super-user');
        $player = Player::factory()->create();
        $snapshot = [
            'reason' => null, 'built_at' => '2026-10-18 12:30:00', 'snapshot_stale' => false,
            'badge_players' => [$player->id => [[
                'score' => 81.75, 'cohort' => 'boys', 'component' => 2, 'confidence_index' => 85,
            ]]],
            'cohorts' => ['boys' => [
                'components' => [2 => ['inferred' => 0, 'bridge_count' => 0, 'trial_anchored' => true]],
                'players' => [$player->id => ['played' => 7]],
                'baseline' => ['anchors' => [$player->id => 1]],
            ]],
        ];
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->twice()->andReturn($snapshot);
        $this->withToken($user->createToken('test', ['cape-ratings:read'])->plainTextToken)
            ->getJson('/api/v1/integrations/jta/players/'.$player->id.'/rating')
            ->assertOk()->assertJsonPath('data.calculated_at', '2026-10-18T12:30:00+02:00')
            ->assertJsonPath('data.stale', false)
            ->assertJsonPath('data.ratings', [['score' => 81.75, 'cohort' => 'boys', 'component' => '2',
                'confidence_label' => 'High', 'baseline_status' => 'Direct main-trial baseline']]);
    }
    public function test_withheld_snapshot_returns_no_rating_without_recalculating(): void
    {
        $user = User::factory()->create();
        Role::findOrCreate('super-user', 'web');
        $user->assignRole('super-user');
        $player = Player::factory()->create();
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->once()->andReturn([
            'reason' => 'Source withdrawn', 'built_at' => 'Not yet updated', 'snapshot_stale' => true,
        ]);
        $this->mock(PlayerSharedAbilityService::class)->shouldNotReceive('badgeSnapshot');
        $this->withToken($user->createToken('test', ['cape-ratings:read'])->plainTextToken)
            ->getJson('/api/v1/integrations/jta/players/'.$player->id.'/rating')
            ->assertOk()->assertJsonPath('data.ratings', [])->assertJsonPath('data.calculated_at', null);
    }
}
