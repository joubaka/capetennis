<?php

namespace Tests\Feature\Draw;

use App\Models\{CategoryEvent, Draw, Event, EventType, Team, TeamFixture, TeamTie};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicFixtureEmptyStateTest extends TestCase
{
    use RefreshDatabase;

    private function draw(bool $team = false): Draw
    {
        if ($team) {
            DB::table('eventtypes')->insertOrIgnore(['id' => 3, 'name' => 'Team', 'type' => EventType::TEAM]);
        }

        $event = Event::factory()->create(['eventType' => $team ? 3 : 1]);

        return Draw::factory()->create([
            'event_id' => $event->id, 'drawName' => 'Published draw', 'published' => true,
            'team_scoring_rules' => $team ? app(\App\Services\TeamEventRulesService::class)->defaults() : null,
        ]);
    }

    private function tie(Draw $draw, bool $published): TeamFixture
    {
        $category = CategoryEvent::factory()->create(['event_id' => $draw->event_id]);
        $home = Team::factory()->create(['category_event_id' => $category->id, 'name' => 'Hidden Home']);
        $away = Team::factory()->create(['category_event_id' => $category->id, 'name' => 'Hidden Away']);
        $tie = TeamTie::create([
            'draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => 1,
            'home_team_id' => $home->id, 'away_team_id' => $away->id,
            'status' => $published ? TeamTie::STATUS_PUBLISHED : TeamTie::STATUS_DRAFT,
            'published_at' => $published ? now() : null,
        ]);

        return TeamFixture::create([
            'draw_id' => $draw->id, 'team_tie_id' => $tie->id,
            'round_nr' => 1, 'tie_nr' => 1, 'match_nr' => 1,
            'fixture_type' => 1, 'numSets' => 3, 'scheduled_at' => '2026-10-09 13:45:00',
        ]);
    }

    public function test_guest_gets_an_empty_state_and_event_link_for_a_published_draw_without_fixtures(): void
    {
        $draw = $this->draw();

        $this->get(route('frontend.fixtures.index', $draw))->assertOk()
            ->assertViewIs('frontend.fixture.empty')->assertSee($draw->drawName)
            ->assertSee('No matches are available to view yet.')
            ->assertSee(route('events.show', $draw->event), false);
    }

    public function test_published_draw_pairings_are_visible_before_lineup_publication(): void
    {
        $draw = $this->draw(true);
        $fixture = $this->tie($draw, false);

        foreach (['frontend.fixtures.index', 'frontend.fixtures.show'] as $route) {
            $this->get(route($route, $draw))->assertOk()->assertViewIs('frontend.fixture.draw-fixtures-show-team')
                ->assertSee('Hidden Home')->assertSee('Hidden Away')
                ->assertDontSee('13:45')->assertDontSee('2026-10-09');
        }

        $this->assertDatabaseCount('team_fixtures', 1);
        $this->assertNull($fixture->teamTie->published_at);
    }

    public function test_published_team_ties_still_render_the_fixture_list(): void
    {
        $draw = $this->draw(true);
        $fixture = $this->tie($draw, true);

        $this->get(route('frontend.fixtures.index', $draw))->assertOk()
            ->assertViewIs('frontend.fixture.draw-fixtures-show-team')
            ->assertViewHas('fixtures', fn ($fixtures) => $fixtures->modelKeys() === [$fixture->id])
            ->assertSee('Hidden Home')->assertSee('Hidden Away')
            ->assertDontSee('No matches are available to view yet.');
    }

    public function test_legacy_fixtures_remain_visible_with_a_scoring_rules_snapshot(): void
    {
        $draw = $this->draw(true);
        $fixture = TeamFixture::create([
            'draw_id' => $draw->id, 'round_nr' => 1, 'match_nr' => 1,
            'fixture_type' => 1, 'numSets' => 3,
        ]);

        $this->get(route('frontend.fixtures.index', $draw))->assertOk()
            ->assertViewIs('frontend.fixture.draw-fixtures-show-team')
            ->assertViewHas('fixtures', fn ($fixtures) => $fixtures->modelKeys() === [$fixture->id]);
    }

    public function test_a_null_scoring_snapshot_does_not_expose_draft_ties(): void
    {
        $draw = $this->draw(true);
        $draw->update(['team_scoring_rules' => null]);
        $this->tie($draw, false);

        $this->assertSame(0, TeamFixture::publishedTeamTies()->count());
        $this->get(route('frontend.fixtures.index', $draw))->assertOk()
            ->assertViewIs('frontend.fixture.draw-fixtures-show-team')->assertSee('Hidden Home');
    }

    public function test_foreign_published_tie_cannot_expose_a_fixture(): void
    {
        $draw = $this->draw(true);
        $foreignFixture = $this->tie($this->draw(true), true);
        TeamFixture::create([
            'draw_id' => $draw->id, 'team_tie_id' => $foreignFixture->team_tie_id,
            'round_nr' => 1, 'match_nr' => 1, 'fixture_type' => 1, 'numSets' => 3,
        ]);

        $this->get(route('frontend.fixtures.index', $draw))->assertOk()
            ->assertViewIs('frontend.fixture.empty')->assertDontSee('Hidden Home');
    }

    public function test_unlinked_fixture_in_a_draw_with_draft_ties_is_not_treated_as_legacy(): void
    {
        $draw = $this->draw(true);
        $tieFixture = $this->tie($draw, false);
        TeamFixture::create([
            'draw_id' => $draw->id, 'round_nr' => 1, 'match_nr' => 2,
            'fixture_type' => 1, 'numSets' => 3,
        ]);

        $this->get(route('frontend.fixtures.index', $draw))->assertOk()
            ->assertViewIs('frontend.fixture.draw-fixtures-show-team')
            ->assertViewHas('fixtures', fn ($fixtures) => $fixtures->modelKeys() === [$tieFixture->id]);
        $this->assertDatabaseCount('team_fixtures', 2);
    }

    public function test_unpublished_draw_and_event_are_still_denied(): void
    {
        $draw = $this->draw();
        $draw->update(['published' => false]);
        $this->get(route('frontend.fixtures.index', $draw))->assertForbidden();
        $draw->update(['published' => true]);
        $draw->event->update(['published' => false]);
        $this->get(route('frontend.fixtures.index', $draw))->assertForbidden();
    }

    public function test_missing_draw_still_returns_not_found(): void
    {
        $this->get(route('frontend.fixtures.index', 999999))->assertNotFound();
    }

    public function test_draw_and_schedule_publication_show_draft_pairings_without_working_lineups_or_scores(): void
    {
        $draw = $this->draw(true);
        $draw->update(['published' => false, 'team_category_id' => 1]);
        $fixture = $this->tie($draw, false);
        $tie = $fixture->teamTie;
        $draw->teams_in_draw()->attach([$tie->home_team_id, $tie->away_team_id]);
        $player = \App\Models\Player::factory()->create(['name' => 'PrivateDraft', 'surname' => 'Player']);
        \App\Models\TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'slot_no' => 1, 'team1_id' => $player->id]);
        \App\Models\TeamFixtureResult::create(['team_fixture_id' => $fixture->id, 'set_nr' => 1, 'team1_score' => 6, 'team2_score' => 3]);
        $venue = new \App\Models\Venue;
        $venue->forceFill(['name' => 'Public schedule courts'])->save();
        $fixture->update(['venue_id' => $venue->id, 'court_label' => '1']);
        app(\App\Domain\Draws\Services\DrawPublicationService::class)->publish($draw);
        app(\App\Services\Scheduling\SchedulePublicationService::class)->publish($draw->event, ['draw_id' => $draw->id]);
        // Working schedule revisions must not replace the published snapshot.
        $fixture->update(['scheduled_at' => '2026-10-10 17:30:00']);

        foreach (['frontend.fixtures.index', 'frontend.fixtures.show', 'frontend.fixtures.draw'] as $route) {
            $this->get(route($route, $draw))->assertOk()
                ->assertSee('Hidden Home')->assertSee('Hidden Away')
                ->assertDontSee('PrivateDraft')->assertDontSee('6 - 3')->assertDontSee('6-3')
                ->assertDontSee('2026-10-10')->assertDontSee('winner-home"');
        }
        $this->get(route('frontend.fixtures.index', $draw))->assertOk()
            ->assertSee('Hidden Home')->assertSee('Hidden Away')
            ->assertSee('2026-10-09 13:45')->assertSee('Public schedule courts')->assertSee('TBD');
        $this->assertSame(0, TeamFixture::publishedTeamTies()->where('draw_id', $draw->id)->count());
        $this->assertSame(TeamTie::STATUS_DRAFT, $tie->fresh()->status);
        $this->assertNull($tie->fresh()->published_at);
        $this->assertDatabaseCount('team_fixture_players', 1);
        $this->assertDatabaseCount('team_fixture_results', 1);
        $this->assertDatabaseCount('published_schedule_assignments', 1);

        $this->app['request']->attributes->remove('published_schedule_rows_'.$draw->event_id);
        $rows = app(\App\Services\Scheduling\SchedulePublicationService::class)->publishedRows($draw->event);
        $this->assertSame(['Hidden Home', 'Hidden Away'], $rows->first()['participants']);
        $this->assertStringNotContainsString('PrivateDraft', json_encode($rows));
    }
}
