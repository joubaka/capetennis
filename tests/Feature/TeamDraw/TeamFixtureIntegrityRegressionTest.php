<?php

namespace Tests\Feature\TeamDraw;

use App\Models\CategoryEvent;
use App\Models\Draw;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamFixture;
use App\Models\TeamTie;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamFixtureIntegrityRegressionTest extends TestCase
{
    use RefreshDatabase;

    private User $superUser;
    private Event $event;
    private Draw $draw;
    private Team $homeTeam;
    private Team $awayTeam;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        DB::table('eventtypes')->insert([
            'id' => 3,
            'name' => 'Team event',
            'type' => EventType::TEAM,
        ]);
        $this->superUser = User::factory()->create()->assignRole('super-user');
        $this->event = Event::factory()->create(['eventType' => 3]);
        $this->draw = Draw::factory()->create([
            'event_id' => $this->event->id,
            'locked' => false,
            'published' => false,
        ]);
        $categoryEvent = CategoryEvent::factory()->create(['event_id' => $this->event->id]);
        $this->homeTeam = Team::factory()->create(['category_event_id' => $categoryEvent->id]);
        $this->awayTeam = Team::factory()->create(['category_event_id' => $categoryEvent->id]);
    }

    public function test_manual_fixture_creation_builds_a_tie_backed_rubber(): void
    {
        $this->actingAs($this->superUser)->post(route('backend.team-fixtures.store'), [
            'draw_id' => $this->draw->id,
            'home_team_id' => $this->homeTeam->id,
            'away_team_id' => $this->awayTeam->id,
            'round_nr' => 1,
            'tie_nr' => 1,
            'fixture_type' => 1,
        ])->assertRedirect();

        $tie = TeamTie::firstOrFail();
        $fixture = TeamFixture::firstOrFail();
        $this->assertSame($tie->id, $fixture->team_tie_id);
        $this->assertSame($this->homeTeam->id, $fixture->homeTeam->id);
        $this->assertSame($this->awayTeam->id, $fixture->awayTeam->id);
    }

    public function test_player_assignment_rejects_players_outside_the_corresponding_roster(): void
    {
        $tie = TeamTie::create([
            'draw_id' => $this->draw->id,
            'round_nr' => 1,
            'tie_nr' => 1,
            'home_team_id' => $this->homeTeam->id,
            'away_team_id' => $this->awayTeam->id,
            'status' => TeamTie::STATUS_DRAFT,
        ]);
        $fixture = TeamFixture::create([
            'draw_id' => $this->draw->id,
            'team_tie_id' => $tie->id,
            'match_nr' => 1,
            'round_nr' => 1,
            'tie_nr' => 1,
            'fixture_type' => 1,
            'rubber_sequence' => 1,
        ]);
        $homePlayer = Player::factory()->create();
        $outsider = Player::factory()->create();
        DB::table('team_players')->insert([
            'team_id' => $this->homeTeam->id,
            'player_id' => $homePlayer->id,
            'rank' => 1,
        ]);

        $this->actingAs($this->superUser)
            ->putJson(route('backend.team-fixtures.updatePlayers', $fixture), [
                'home_players' => [$homePlayer->id],
                'away_players' => [$outsider->id],
            ])->assertUnprocessable();

        $this->assertDatabaseCount('team_fixture_players', 0);
    }

    public function test_score_write_serializes_and_repairs_duplicate_rows_for_the_set(): void
    {
        $fixture = TeamFixture::create([
            'draw_id' => $this->draw->id,
            'match_nr' => 1,
            'round_nr' => 1,
            'tie_nr' => 1,
            'fixture_type' => 1,
            'rubber_sequence' => 1,
        ]);
        DB::table('team_fixture_results')->insert([
            ['team_fixture_id' => $fixture->id, 'set_nr' => 1, 'team1_score' => 1, 'team2_score' => 0],
            ['team_fixture_id' => $fixture->id, 'set_nr' => 1, 'team1_score' => 2, 'team2_score' => 0],
        ]);

        $this->actingAs($this->superUser)
            ->withHeader('X-Requested-With', 'XMLHttpRequest')
            ->postJson(route('backend.team-fixtures.insertScore', $fixture), [
                'set1_home' => 6,
                'set1_away' => 3,
            ])->assertOk();

        $this->assertSame(1, DB::table('team_fixture_results')
            ->where('team_fixture_id', $fixture->id)->where('set_nr', 1)->count());
        $this->assertDatabaseHas('team_fixture_results', [
            'team_fixture_id' => $fixture->id,
            'set_nr' => 1,
            'team1_score' => 6,
            'team2_score' => 3,
            'match_winner_id' => null,
            'match_loser_id' => null,
        ]);
    }

    public function test_legacy_team_score_route_is_post_only_and_idempotent(): void
    {
        $fixture = TeamFixture::create([
            'draw_id' => $this->draw->id,
            'match_nr' => 2,
            'round_nr' => 1,
            'tie_nr' => 1,
            'fixture_type' => 1,
            'rubber_sequence' => 2,
        ]);
        $payload = [
            'type' => 'team',
            'fixture_id' => $fixture->id,
            'set_player1' => [6, 6],
            'set_player2' => [3, 4],
        ];

        $this->actingAs($this->superUser)
            ->get(route('draw.insert.result', $payload));
        $this->assertDatabaseMissing('team_fixture_results', [
            'team_fixture_id' => $fixture->id,
        ]);
        $this->postJson(route('draw.insert.result'), $payload)->assertOk();
        $this->postJson(route('draw.insert.result'), $payload)->assertOk();

        $this->assertSame(2, DB::table('team_fixture_results')
            ->where('team_fixture_id', $fixture->id)->count());
    }

    public function test_team_score_rejects_an_unpaired_set(): void
    {
        $fixture = TeamFixture::create([
            'draw_id' => $this->draw->id,
            'match_nr' => 3,
            'round_nr' => 1,
            'tie_nr' => 1,
            'fixture_type' => 1,
            'rubber_sequence' => 3,
        ]);

        $this->actingAs($this->superUser)
            ->postJson(route('backend.team-fixtures.insertScore', $fixture), [
                'set1_home' => 6,
            ])->assertUnprocessable();

        $this->assertDatabaseMissing('team_fixture_results', [
            'team_fixture_id' => $fixture->id,
        ]);
    }
}
