<?php

namespace Tests\Feature\Draw;

use App\Domain\Draws\Services\{DrawRulesService, StandingsService, TennisScoreFormat};
use App\Models\{Draw, Event, Fixture, Registration, User};
use App\Services\{IndividualMatchOutcomeService, InterproDrawBuilder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TimedDrawScoringTest extends TestCase
{
    use RefreshDatabase;

    private function draw(): Draw
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $event = Event::factory()->create(['eventType' => 6]);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => false, 'locked' => false]);
        $draw->settings()->create(['workflow' => 'round_robin_playoffs', 'score_format' => TennisScoreFormat::TIMED,
            'num_sets' => 1, 'match_duration_minutes' => 20, 'changeover_minutes' => 5,
            'notes' => ['general' => 'Please report to the court marshal.'], 'notes_print' => ['general' => false],
            'playoff_config' => [['name' => 'Main', 'slug' => 'main', 'size' => 4, 'positions' => [1, 2], 'enabled' => true],
                ['name' => 'Future plate', 'slug' => 'plate', 'size' => 4, 'positions' => [3, 4], 'enabled' => true]]]);
        return $draw;
    }

    private function fixture(Draw $draw, string $stage = 'RR'): Fixture
    {
        $group = $draw->groups()->firstOrCreate(['name' => 'A']);
        $players = Registration::factory()->count(2)->create();
        foreach ($players as $index => $registration) {
            $draw->registrations()->attach($registration->id);
            $group->registrations()->attach($registration->id, ['seed' => $index + 1]);
        }
        return Fixture::factory()->create(['draw_id' => $draw->id, 'draw_group_id' => $group->id,
            'stage' => $stage, 'round' => 1, 'match_nr' => 1, 'match_status' => 0,
            'registration1_id' => $players[0]->id, 'registration2_id' => $players[1]->id]);
    }

    public function test_timed_settings_validate_bounds_and_preserve_values_when_switching_back(): void
    {
        $draw = $this->draw();
        $draw->settings->update(['score_format' => null, 'match_duration_minutes' => null, 'changeover_minutes' => null]);
        $url = route('backend.draw.update-settings', $draw);
        $this->postJson($url, ['score_format' => 'timed'])->assertUnprocessable();
        $this->assertNull($draw->settings->fresh()->score_format);
        $this->postJson($url, ['score_format' => 'timed', 'match_duration_minutes' => 0, 'changeover_minutes' => 61])->assertUnprocessable();
        $this->postJson($url, ['score_format' => 'timed', 'match_duration_minutes' => 20, 'changeover_minutes' => 0])->assertOk();
        $this->assertSame(0, $draw->settings->fresh()->changeover_minutes);
        $this->postJson($url, ['score_format' => TennisScoreFormat::SHORT_SET_4])->assertOk();
        $this->assertSame(20, $draw->settings->fresh()->match_duration_minutes);
        $this->assertSame(0, $draw->settings->fresh()->changeover_minutes);
    }

    public function test_timed_round_robin_draw_is_idempotent_and_awards_one_point_each_on_both_score_routes(): void
    {
        $draw = $this->draw();
        $fixture = $this->fixture($draw);
        $this->postJson(route('rr.fixture.saveScore', $fixture), ['sets' => ['3-3']])->assertOk();
        $this->postJson(route('api.draws.fixtures.score.store', ['draw' => $draw, 'fixture' => $fixture]), ['sets' => ['3-3']])->assertOk();
        $fixture->refresh();
        $this->assertNull($fixture->winner_registration);
        $this->assertSame(2, (int) $fixture->match_status);
        $this->assertSame(1, $fixture->fixtureResults()->count());
        $this->assertNull($fixture->fixtureResults()->first()->winner_registration);
        $this->assertNull($fixture->fixtureResults()->first()->loser_registration);
        $rows = app(StandingsService::class)->forDraw($draw->fresh())[$fixture->draw_group_id];
        foreach ($rows as $row) {
            $this->assertSame(1, $row['points']);
            $this->assertSame(1, $row['draws']);
            $this->assertSame(0, $row['wins']);
            $this->assertSame(0, $row['losses']);
            $this->assertSame(0, $row['sets_won']);
            $this->assertSame(0, $row['sets_lost']);
            $this->assertSame(3, $row['games_won']);
        }
        $this->postJson(route('rr.fixture.saveScore', $fixture), ['sets' => ['3-3'], 'deciding_point_winner_id' => $fixture->registration1_id])->assertUnprocessable();
    }

    public function test_tied_timed_playoff_requires_a_current_deciding_winner_and_keeps_the_actual_game_score(): void
    {
        foreach (['backend', 'api'] as $route) {
            $draw = $this->draw();
            $fixture = $this->fixture($draw, 'MAIN');
            $url = $route === 'backend' ? route('rr.fixture.saveScore', $fixture)
                : route('api.draws.fixtures.score.store', ['draw' => $draw, 'fixture' => $fixture]);
            $this->postJson($url, ['sets' => ['4-4']])->assertUnprocessable();
            $this->postJson($url, ['sets' => ['4-4'], 'deciding_point_winner_id' => Registration::factory()->create()->id])->assertUnprocessable();
            $this->assertSame(0, $fixture->fixtureResults()->count());
            $payload = ['sets' => ['4-4'], 'deciding_point_winner_id' => $fixture->registration2_id];
            $this->postJson($url, $payload)->assertOk();
            $this->postJson($url, $payload)->assertOk();
            $fixture->refresh();
            $this->assertSame((int) $fixture->registration2_id, (int) $fixture->winner_registration);
            $this->assertSame((int) $fixture->registration2_id, app(IndividualMatchOutcomeService::class)->winner($fixture));
            $this->assertSame(1, $fixture->fixtureResults()->count());
            $set = $fixture->fixtureResults()->first();
            $this->assertSame(4, (int) $set->registration1_score);
            $this->assertSame(4, (int) $set->registration2_score);
            $this->assertNull($set->winner_registration);
        }
    }

    public function test_timed_points_are_primary_and_wins_award_two_points(): void
    {
        $draw = $this->draw();
        $fixture = $this->fixture($draw);
        $this->postJson(route('rr.fixture.saveScore', $fixture), ['sets' => ['5-3']])->assertOk();
        $rows = app(StandingsService::class)->forDraw($draw->fresh())[$fixture->draw_group_id];
        $this->assertSame((int) $fixture->registration1_id, (int) $rows[0]['reg_id']);
        $this->assertSame([2, 0], array_column($rows, 'points'));
        $this->assertSame([1, 0], array_column($rows, 'wins'));
    }

    public function test_timed_score_authorization_and_cross_draw_boundaries_still_apply(): void
    {
        $draw = $this->draw();
        $fixture = $this->fixture($draw);
        $other = Draw::factory()->create();
        $this->postJson(route('api.draws.fixtures.score.store', ['draw' => $other, 'fixture' => $fixture]), ['sets' => ['3-3']])->assertForbidden();
        $this->actingAs(User::factory()->create());
        $this->postJson(route('rr.fixture.saveScore', $fixture), ['sets' => ['3-3']])->assertForbidden();
        $this->assertSame(0, $fixture->fixtureResults()->count());
    }

    public function test_draw_to_win_and_deciding_point_only_corrections_cannot_change_scored_downstream_matches(): void
    {
        foreach (['RR', 'MAIN'] as $stage) {
            $draw = $this->draw();
            $fixture = $this->fixture($draw, $stage);
            $url = route('rr.fixture.saveScore', $fixture);
            $payload = ['sets' => ['3-3']];
            if ($stage !== 'RR') $payload['deciding_point_winner_id'] = $fixture->registration1_id;
            $this->postJson($url, $payload)->assertOk();
            $before = $fixture->fresh()->getAttributes();
            $result = $fixture->fixtureResults()->first()->getAttributes();
            $dependent = Fixture::factory()->create(['draw_id' => $draw->id, 'stage' => 'MAIN', 'round' => 2]);
            $dependent->fixtureResults()->create(['set_nr' => 1, 'registration1_score' => 4, 'registration2_score' => 2]);
            if ($stage !== 'RR') {
                $fixture->update(['parent_fixture_id' => $dependent->id]);
                $before = $fixture->fresh()->getAttributes();
            }
            $correction = $stage === 'RR' ? ['sets' => ['4-3']]
                : ['sets' => ['3-3'], 'deciding_point_winner_id' => $fixture->registration2_id];
            $this->postJson($url, $correction)->assertConflict();
            $this->assertSame($before, $fixture->fresh()->getAttributes());
            $this->assertSame($result, $fixture->fixtureResults()->first()->getAttributes());
            $this->assertSame(1, $dependent->fixtureResults()->count());
        }
    }

    public function test_timed_rules_describe_points_and_deciding_point_and_omit_dormant_stages_from_print(): void
    {
        $draw = $this->draw();
        $this->fixture($draw);
        $rules = app(DrawRulesService::class);
        $sections = collect($rules->sections($draw->fresh()))->keyBy('key');
        $this->assertStringContainsString('2 points for a win, 1 for a draw and 0 for a loss', $sections['round_robin']['rules']);
        $this->assertStringContainsString('deciding point', $sections['bracket_main']['rules']);
        $this->assertTrue($sections['bracket_plate']['dormant']);
        $print = $rules->printable($draw->fresh());
        $this->assertArrayNotHasKey('Future plate rules', $print);
        $this->assertStringNotContainsString('court marshal', implode(' ', $print));
        $page = $this->get(route('backend.draw.roundrobin.show', $draw))->assertOk()
            ->assertSee('Timed match')->assertSee('Please report to the court marshal.')->assertSee('match_duration_minutes', false);
        if (getenv('DRAW_SETTINGS_SNAPSHOT')) file_put_contents(storage_path('framework/testing/timed-settings-qa.html'), $page->getContent());
    }
}
