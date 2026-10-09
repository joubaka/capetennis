<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\Series;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RemainingTournamentAdminUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create(['email' => 'admin@example.test'])->assignRole('super-user'));
        view()->share('errors', new ViewErrorBag());
    }

    public function test_invalid_series_create_retains_inputs_without_creating_records(): void
    {
        $before = Series::count();
        $this->from(route('series.create'))->post(route('series.store'), [
            'name' => 'Example junior circuit', 'year' => now()->year, 'numScores' => 3,
        ])->assertRedirect(route('series.create'))->assertSessionHasErrors('rankType');
        $response = $this->get(route('series.create'))->assertOk();
        $response->assertSee('Example junior circuit')->assertSee('value="3" selected', false);
        $this->assertSame($before, Series::count());
        $this->fixture('series-create', $response->getContent());
    }

    public function test_empty_results_are_truthful_without_creating_ranking_records(): void
    {
        $series = Series::factory()->create(['name' => 'Example circuit']);
        $player = Player::factory()->create(['name' => 'Example', 'surname' => 'Player']);
        $html = view('backend.ranking.details', compact('series', 'player') + ['results' => collect()])->render();
        $this->assertStringContainsString('Results for Example circuit', $html);
        $this->assertStringContainsString('No recorded results for this player in this series.', $html);
        $this->fixture('ranking-details', $html);
        $html = view('backend.player.player_results', ['player' => $player, 'results' => ['fixture' => collect()]])->render();
        $this->assertStringContainsString('No recorded team results for this player.', $html);
        $this->fixture('player-results', $html);
    }

    public function test_event_create_remains_available_without_creating_an_event(): void
    {
        $before = \App\Models\Event::count();
        $response = $this->get(route('backend.events.create'))->assertOk();
        $response->assertSee('Fill from an event notice (optional)');
        $this->assertSame($before, \App\Models\Event::count());
        $this->fixture('event-create', $response->getContent());
    }

    private function fixture(string $name, string $html): void
    {
        if (getenv('CT_REMAINING_QA') !== '1') {
            return;
        }
        $directory = storage_path('app/all-admin-improvements-qa');
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        file_put_contents($directory.'/'.$name.'.html', $html);
    }
}
