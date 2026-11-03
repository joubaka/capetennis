<?php

namespace Tests\Feature;

use App\Models\{Player, PlayerPlusAccessOverride, User};
use App\Services\Performance\{PlayerPathwayService, PlayerRatingHistoryService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MyTennisRatingSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private function export(string $name, $response): void
    {
        if (getenv('CT_RATING_SNAPSHOT_QA') !== '1') return;
        $directory = storage_path('framework/testing/rating-header-qa');
        \Illuminate\Support\Facades\File::ensureDirectoryExists($directory);
        file_put_contents($directory.'/dashboard-'.$name.'.html', $response->getContent());
    }

    private function history(Player $player): void
    {
        $this->mock(PlayerRatingHistoryService::class)->shouldReceive('forPlayer')->once()->withArgs(fn ($actual) => $actual->id === $player->id)
            ->andReturn(['reason' => null, 'ball_stage' => 'orange', 'as_of' => '2026-11-03', 'points' => [
                ['index' => 11, 'date' => '2026-11-03', 'score' => 66.6, 'group' => 'a', 'confidence_label' => 'Medium', 'private' => 'must-not-project']]]);
    }

    public function test_authorized_dashboard_reuses_one_history_read_and_projects_only_selected_player_snapshot(): void
    {
        $user = User::factory()->create(); $player = Player::factory()->create(['userId' => $user->id]);
        PlayerPlusAccessOverride::create(['player_id' => $player->id, 'mode' => 'grant', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $this->history($player);
        $this->mock(PlayerPathwayService::class)->shouldReceive('summary')->once()->andReturn(PlayerPathwayService::unrecorded());
        $response = $this->actingAs($user)->get(route('my.tennis', ['player' => $player->id]))->assertOk();
        $snapshot = $response->viewData('ratingSnapshot');
        $this->assertSame(66.6, $snapshot['points'][0]['score']); $this->assertSame('orange', $snapshot['stage']);
        $this->assertArrayNotHasKey('private', $snapshot['points'][0]);
        $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
        $this->export('plus', $response);
    }

    public function test_standard_dashboard_does_not_read_or_expose_private_rating_history(): void
    {
        $user = User::factory()->create(); $player = Player::factory()->create(['userId' => $user->id]);
        $this->mock(PlayerRatingHistoryService::class)->shouldNotReceive('forPlayer');
        $response = $this->actingAs($user)->get(route('my.tennis', ['player' => $player->id]))->assertOk();
        $this->assertNull($response->viewData('ratingSnapshot'));
        $this->export('standard', $response);
    }

    public function test_super_preview_snapshot_does_not_create_ownership_or_run_pathway_for_unlinked_player(): void
    {
        Role::findOrCreate('super-user', 'web'); $user = User::factory()->create()->assignRole('super-user'); $player = Player::factory()->create(['userId' => 0]);
        $this->history($player); $this->mock(PlayerPathwayService::class)->shouldNotReceive('summary');
        $response = $this->actingAs($user)->get(route('my.tennis', ['player' => $player->id]))->assertOk();
        $this->assertSame(66.6, $response->viewData('ratingSnapshot')['points'][0]['score']);
        $this->assertNull($response->viewData('selectedPlayer')); $this->assertDatabaseCount('user_players', 0);
        $this->export('preview', $response);
    }
}
