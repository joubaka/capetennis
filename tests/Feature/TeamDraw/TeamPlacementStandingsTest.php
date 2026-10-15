<?php

namespace Tests\Feature\TeamDraw;

use App\Models\{CategoryEvent, Draw, Event, EventType, Team, TeamFixture, TeamFixtureResult, TeamRegion, TeamTie};
use App\Services\{EventStandingsService, TeamEventRulesService, TeamPlacementResultsService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeamPlacementStandingsTest extends TestCase
{
    use RefreshDatabase;

    private function competition(): array
    {
        DB::table('eventtypes')->insertOrIgnore(['id' => 3, 'name' => 'Team', 'type' => EventType::TEAM]);
        $event = Event::factory()->create(['eventType' => 3, 'published' => true, 'standings_published' => true]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $category->category->update(['name' => 'U15 Boys']);
        $teams = collect(['North', 'South'])->map(function ($name) use ($category, $event) {
            $region = TeamRegion::create(['region_name' => $name, 'short_name' => $name]);
            $event->regions()->attach($region->id);

            return Team::factory()->create(['name' => $name, 'category_event_id' => $category->id, 'region_id' => $region->id]);
        });
        $rules = app(TeamEventRulesService::class)->defaults();
        $rules['rubbers']['singles']['sets_to_win'] = 1;
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id,
            'drawName' => 'U15 round robin', 'published' => true, 'team_scoring_rules' => $rules]);
        $playoff = $draw->replicate();
        $playoff->drawName = 'U15 placement playoffs';
        $playoff->team_draw_selection = ['competition' => ['phase' => 'playoff', 'group' => 'U15', 'source_draw_id' => $draw->id]];
        $playoff->save();

        return compact('event', 'draw', 'playoff', 'teams');
    }

    private function encounter(Draw $draw, $teams, bool $homeWins = true): TeamTie
    {
        $tie = TeamTie::create(['draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => 1,
            'home_team_id' => $teams[0]->id, 'away_team_id' => $teams[1]->id,
            'status' => TeamTie::STATUS_COMPLETED, 'published_at' => now()]);
        $this->rubber($tie, $homeWins, 1);

        return $tie;
    }

    private function rubber(TeamTie $tie, bool $homeWins, int $sequence): void
    {
        $fixture = TeamFixture::create(['draw_id' => $tie->draw_id, 'team_tie_id' => $tie->id,
            'fixture_type' => 1, 'rubber_code' => 'singles', 'rubber_sequence' => $sequence,
            'round_nr' => 1, 'match_nr' => $sequence]);
        TeamFixtureResult::create(['team_fixture_id' => $fixture->id, 'set_nr' => 1,
            'team1_score' => $homeWins ? 6 : 2, 'team2_score' => $homeWins ? 2 : 6]);
    }

    public function test_playoffs_determine_places_without_changing_round_robin_totals(): void
    {
        ['event' => $event, 'draw' => $draw, 'playoff' => $playoff, 'teams' => $teams] = $this->competition();
        $this->encounter($draw, $teams);
        $this->encounter($playoff, $teams, false);
        $data = app(EventStandingsService::class)->forEvent($event, publishedOnly: true);
        $this->assertCount(1, $data['sections']);
        $this->assertSame([3, 0], array_column($data['ageStandings']['U15'], 'points'));
        $this->assertSame([1, 1], array_column($data['ageStandings']['U15'], 'played'));
        $this->assertSame('South', $data['placementSections'][0]['results'][0]['winner_name']);
        $this->assertSame('North', $data['placementSections'][0]['results'][0]['loser_name']);
        $this->assertSame(1, $data['stats']['draws']);
        $this->get(route('frontend.events.standings', $event))->assertOk()
            ->assertSee('Playoff results determine final places.')
            ->assertSee('data-placement-results="'.$playoff->id.'"', false);
        $this->get(route('frontend.team-draw.standings', $playoff))->assertOk()
            ->assertSee('data-live-results="draw-standings"', false)
            ->assertSee('Playoff results determine final places.');
        $this->assertSame([], $this->get(route('frontend.events.standings', [$event, 'age' => 'U19']))
            ->assertOk()->viewData('placementSections'));
    }

    public function test_public_placements_hide_unpublished_draws_and_ties(): void
    {
        ['event' => $event, 'playoff' => $playoff, 'teams' => $teams] = $this->competition();
        $tie = $this->encounter($playoff, $teams);
        $tie->update(['status' => TeamTie::STATUS_DRAFT, 'published_at' => null]);
        $service = app(TeamPlacementResultsService::class);
        $this->assertSame([], $service->forDraw($playoff, true));
        $this->assertCount(1, $service->forDraw($playoff));
        $playoff->update(['published' => false]);
        $this->assertSame([], app(EventStandingsService::class)->forEvent($event, publishedOnly: true)['placementSections']);
    }

    public function test_a_tied_playoff_does_not_award_final_places(): void
    {
        ['playoff' => $playoff, 'teams' => $teams] = $this->competition();
        $tie = $this->encounter($playoff, $teams);
        $this->rubber($tie, false, 2);
        $result = app(TeamPlacementResultsService::class)->forDraw($playoff)[0];
        $this->assertTrue($result['complete']);
        $this->assertNull($result['winner_name']);
        $this->assertNull($result['loser_name']);
    }

    public function test_foreign_team_or_draw_matches_cannot_leak_into_placements(): void
    {
        ['playoff' => $playoff, 'teams' => $teams] = $this->competition();
        $tie = $this->encounter($playoff, $teams);
        $foreignCategory = CategoryEvent::factory()->create();
        $teams[1]->update(['category_event_id' => $foreignCategory->id]);
        $this->assertSame([], app(TeamPlacementResultsService::class)->forDraw($playoff, true));
    }
}
