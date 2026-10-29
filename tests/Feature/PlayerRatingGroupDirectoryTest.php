<?php

namespace Tests\Feature;

use App\Models\{Player, User};
use App\Services\Performance\{AllAgeRatingModel, PlayerAbilitySnapshotStore, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerRatingGroupDirectoryTest extends TestCase
{
    use RefreshDatabase;

    private function saved(): array
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::getPdo()->sqliteCreateFunction('YEAR', fn ($date) => $date === null ? null : (int) substr($date, 0, 4));
        }
        $players = collect();
        foreach (['Reference One', 'Reference Two', 'Reference Three', 'Girls First', 'Girls Second'] as $index => $name) {
            [$first, $last] = explode(' ', $name);
            $players->push(Player::factory()->create(['name' => $first, 'surname' => $last, 'gender' => $index < 3 ? 1 : 2, 'dateOfBirth' => '2013-01-01']));
        }
        $match = fn ($first, $second) => ['winner' => $first, 'loser' => $second, 'source_id' => $first.':'.$second,
            'event_id' => 1, 'event_name' => 'Synthetic singles', 'category' => 'U13 girls', 'cohort' => 'u13 girls',
            'confidence_date' => '2026-10-01', 'confidence_date_basis' => 'scheduled match date', 'ball_type' => 'yellow'];
        $graph = (new AllAgeRatingModel)->fit([$match($players[0]->id, $players[1]->id), $match($players[1]->id, $players[2]->id), $match($players[3]->id, $players[4]->id)], CarbonImmutable::parse('2026-10-29'));
        $snapshot = ['reason' => null, 'built_at' => '2026-10-29', 'snapshot_stale' => false, 'snapshot_as_of' => '2026-10-29', 'all_age' => $graph];
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn($snapshot);
        $this->mock(PlayerSharedAbilityService::class)->shouldReceive('allAgeBadgeSnapshot')->andReturn($graph['ratings']);
        return [$players, $snapshot, $graph['ratings'][$players[3]->id]['component']];
    }

    public function test_disconnected_girls_receive_explicit_within_group_values_only_after_selecting_their_group(): void
    {
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        [$players, $snapshot, $group] = $this->saved();
        $url = route('backend.player-performance.ratings');
        $default = $this->get($url.'?gender=girls')->assertOk()->assertSee('Girls First')->assertDontSee('within-group only');
        $this->assertNull($default->viewData('players')->getCollection()->first()['rating']);
        $response = $this->get($url.'?comparison_group='.$group)->assertOk()->assertSee('Within-group ratings only.')
            ->assertSee('within-group only')->assertSee('Girls First')->assertSee('Girls Second')->assertDontSee('Reference One')
            ->assertSee('Shared badges and profile ratings remain withheld.')->assertHeader('Cache-Control', 'no-store, private');
        if ($artifact = getenv('CT_GROUP_VIEW_HTML')) { file_put_contents($artifact, $response->getContent()); }
        $first = $response->viewData('players')->getCollection()->first();
        $this->assertSame($players[3]->id, $first['id']);
        $this->assertSame(1, $first['position']);
        $this->assertNull($first['rating']['score']);
        $this->assertFalse($first['rating']['comparable']);
        $this->assertIsFloat($first['rating']['within_group_score']);
        $this->assertNull($snapshot['all_age']['ratings'][$players[3]->id]['score']);
        $search = $this->get($url.'?comparison_group='.$group.'&search=Second')->assertOk()->assertDontSee('Girls First')->assertSee('Girls Second');
        $this->assertSame(2, $search->viewData('players')->getCollection()->first()['position']);
        $this->assertDatabaseCount('user_players', 0);
    }

    public function test_group_projection_does_not_expand_audience_or_accept_unknown_groups(): void
    {
        $url = route('backend.player-performance.ratings', ['comparison_group' => 'unknown']);
        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create());
        $this->getJson($url)->assertForbidden();
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $this->saved();
        $this->getJson($url)->assertUnprocessable();
    }

    public function test_withheld_snapshot_cannot_reveal_a_previously_selected_local_group(): void
    {
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn([
            'reason' => 'Source publication changed', 'all_age' => ['components' => ['girls' => ['members' => [1, 2], 'converged' => true]]],
        ]);
        $this->mock(PlayerSharedAbilityService::class)->shouldNotReceive('allAgeBadgeSnapshot');
        $this->getJson(route('backend.player-performance.ratings', ['comparison_group' => 'girls']))->assertUnprocessable();
    }
}
