<?php

namespace Tests\Feature;

use App\Models\{Player, User};
use App\Services\Performance\{AllAgeRatingModel, PlayerAbilitySnapshotStore, PlayerSharedAbilityService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AllAgeConnectionDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_group_links_filter_actual_saved_members_without_assigning_disconnected_scores(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::getPdo()->sqliteCreateFunction('YEAR', fn ($date) => $date === null ? null : (int) substr($date, 0, 4));
        }
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $first = Player::factory()->create(['name' => 'Reference', 'surname' => 'Player']);
        $second = Player::factory()->create(['name' => 'Disconnected', 'surname' => 'Player']);
        $rating = ['cohort' => AllAgeRatingModel::COHORT, 'component' => 'reference', 'score' => 60.0,
            'confidence_label' => 'Low', 'last_played' => '2026-10-01'];
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn([
            'reason' => null, 'snapshot_stale' => false, 'built_at' => '2026-10-29 10:00:00', 'snapshot_as_of' => '2026-10-29',
            'all_age' => ['reference_component' => 'reference',
                'components' => ['reference' => ['members' => [$first->id]], 'disconnected' => ['members' => [$second->id]]],
                'players' => [$first->id => ['age_groups' => ['u13 boys']], $second->id => ['age_groups' => ['u13 girls']]],
                'ratings' => [$first->id => $rating, $second->id => array_replace($rating, ['score' => null, 'component' => 'disconnected'])]],
        ]);
        $this->mock(PlayerSharedAbilityService::class)->shouldReceive('allAgeBadgeSnapshot')->andReturn([
            $first->id => $rating, $second->id => array_replace($rating, ['score' => null, 'component' => 'disconnected', 'reason' => 'Awaiting connecting evidence.']),
        ]);
        $url = route('backend.player-performance.ratings');
        $response = $this->get($url.'?comparison_group=disconnected')->assertOk()->assertSee('Disconnected Player')->assertDontSee('Reference Player')
            ->assertSee('Awaiting connecting evidence.')->assertSee('Show all groups')->assertDontSee('60.0/100');
        $this->assertSame(1, $response->viewData('players')->total());
        $this->assertNull($response->viewData('players')->getCollection()->first()['rating']);
        $this->getJson($url.'?comparison_group=missing')->assertUnprocessable();
        $this->assertDatabaseCount('user_players', 0);
    }

    public function test_comparison_group_parameter_does_not_expand_rating_audience(): void
    {
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldNotReceive('current');
        $url = route('backend.player-performance.ratings', ['comparison_group' => 'reference']);
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        $this->getJson($url)->assertForbidden();
    }
}
