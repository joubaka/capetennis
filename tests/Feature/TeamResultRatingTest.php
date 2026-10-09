<?php

namespace Tests\Feature;

use App\Models\{Event, User};
use App\Services\Performance\PlayerRatingLeaderboardService;
use App\Services\TeamResultRatingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamResultRatingTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_super_user_receives_no_private_rating_or_intermediate_identity_and_no_snapshot_read(): void
    {
        $this->actingAs(User::factory()->create());
        $this->mock(PlayerRatingLeaderboardService::class)->shouldNotReceive('build');
        $row = ['id' => 'imported:1:2', 'name' => 'Same Name', 'rating_player_id' => 10];
        $result = app(TeamResultRatingService::class)->enrich(collect([$row]), Event::factory()->create(), ['name' => 'u/10 Boys']);
        $this->assertSame([['id' => 'imported:1:2', 'name' => 'Same Name']], $result->all());
    }

    public function test_explicit_linked_identity_uses_playing_cohort_and_missing_or_wrong_cohort_stays_unavailable(): void
    {
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $this->mock(PlayerRatingLeaderboardService::class)->shouldReceive('build')->once()->with(null, 'u10 girls')->andReturn([
            'rows' => collect([
                ['identity' => 'p:10', 'cohort' => 'u10 girls', 'position' => 4, 'rating' => ['score' => 71.2, 'component' => 'local']],
                ['identity' => 'p:11', 'cohort' => 'u12 girls', 'position' => 1, 'rating' => ['score' => 90, 'component' => 'other']],
            ]), 'snapshot' => [], 'limitReason' => null,
        ]);
        $rows = collect([
            ['id' => 'imported:1:2', 'name' => 'Same Name', 'rating_player_id' => 10],
            ['id' => 'imported:1:3', 'name' => 'Same Name', 'rating_player_id' => null],
            ['id' => 11, 'name' => 'Different cohort', 'rating_player_id' => 11],
        ]);
        $result = app(TeamResultRatingService::class)->enrich($rows, Event::factory()->create(), ['name' => 'u/10 Girls']);
        $this->assertSame($rows->pluck('id')->all(), $result->pluck('id')->all());
        $this->assertSame(71.2, $result[0]['cape_tennis_rating']['score']);
        $this->assertSame(4, $result[0]['cape_tennis_rating']['position']);
        $this->assertSame('local', $result[0]['cape_tennis_rating']['component']);
        $this->assertNull($result[1]['cape_tennis_rating']['score']);
        $this->assertNull($result[2]['cape_tennis_rating']['score']);
    }
}
