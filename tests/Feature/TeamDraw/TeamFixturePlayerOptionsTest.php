<?php

namespace Tests\Feature\TeamDraw;

use App\Models\{Draw, Event, Player, TeamFixture, TeamFixturePlayer, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamFixturePlayerOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function actor(string $role, Event $event): User
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        $user = User::factory()->create()->assignRole($role);
        if ($role === 'admin') {
            DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $user->id]);
        }
        return $user;
    }

    private function fixture(Draw $draw, ?Player $home = null, ?Player $away = null): TeamFixture
    {
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'round_nr' => 1,
            'tie_nr' => 1, 'match_nr' => 1, 'fixture_type' => 1, 'match_status' => 0, 'numSets' => 3]);
        TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'slot_no' => 1,
            'team1_id' => $home?->id, 'team2_id' => $away?->id]);
        return $fixture;
    }

    public function test_selected_draw_options_are_deduplicated_and_isolated_for_admin_and_super_user(): void
    {
        $event = Event::factory()->create();
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $otherDraw = Draw::factory()->create(['event_id' => $event->id]);
        $foreignDraw = Draw::factory()->create(['event_id' => Event::factory()->create()->id]);
        $home = Player::factory()->create();
        $away = Player::factory()->create();
        $other = Player::factory()->create();
        $foreign = Player::factory()->create();
        $this->fixture($draw, $home, $away);
        $this->fixture($draw, $away, $home);
        $this->fixture($draw);
        $this->fixture($otherDraw, $other);
        $this->fixture($foreignDraw, $foreign);
        $expected = [$home->id, $away->id];
        sort($expected);
        $before = [TeamFixture::count(), TeamFixturePlayer::count()];

        foreach (['admin', 'super-user'] as $role) {
            $this->actingAs($this->actor($role, $event))
                ->get(route('backend.team-fixtures.index', ['draw_id' => $draw->id]))
                ->assertOk()->assertViewHas('allPlayers', fn ($players) => $players->pluck('id')->sort()->values()->all() === $expected);
        }
        $this->assertSame($before, [TeamFixture::count(), TeamFixturePlayer::count()]);

        $this->actingAs($this->actor('admin', $event))
            ->get(route('backend.team-fixtures.index'))
            ->assertOk()->assertViewHas('allPlayers', fn ($players) => !$players->contains('id', $foreign->id));
        $this->get(route('backend.team-fixtures.index', ['draw_id' => $foreignDraw->id]))
            ->assertOk()->assertViewHas('allPlayers', fn ($players) => $players->isEmpty());
    }

    public function test_options_follow_the_current_filtered_fixture_page(): void
    {
        $event = Event::factory()->create();
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $firstPage = Player::factory()->create();
        $secondPage = Player::factory()->create();
        for ($index = 0; $index < 100; $index++) {
            $this->fixture($draw, $firstPage);
        }
        $this->fixture($draw, null, $secondPage);
        $this->actingAs($this->actor('super-user', $event));

        foreach ([1 => $firstPage, 2 => $secondPage, 3 => null] as $page => $player) {
            $this->get(route('backend.team-fixtures.index', ['draw_id' => $draw->id, 'sort' => 'id', 'page' => $page]))
                ->assertOk()->assertViewHas('allPlayers', fn ($players) => $players->pluck('id')->all() === ($player ? [$player->id] : []));
        }
    }
}
