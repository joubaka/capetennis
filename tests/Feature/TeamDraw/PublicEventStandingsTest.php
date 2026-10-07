<?php

namespace Tests\Feature\TeamDraw;

use App\Models\{CategoryEvent, Draw, Event, EventType, Team, TeamFixture, TeamRegion, TeamTie};
use App\Services\{EventStandingsService, TeamEventRulesService, TeamFixtureScoreService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicEventStandingsTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        DB::table('eventtypes')->insertOrIgnore(['id' => 3, 'name' => 'Team', 'type' => EventType::TEAM]);

        return Event::factory()->create(['eventType' => 3, 'published' => true]);
    }

    private function draw(Event $event, string $name = 'U10 Boys'): array
    {
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $regions = collect(['Audit Oak', 'Audit Pine'])->map(fn ($name) => TeamRegion::create(['region_name' => $name, 'short_name' => $name]));
        $event->regions()->attach($regions->pluck('id'));
        $teams = $regions->map(fn ($region) => Team::factory()->create(['category_event_id' => $category->id, 'region_id' => $region->id]));
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true, 'drawName' => $name,
            'category_event_id' => $category->id, 'team_scoring_rules' => app(TeamEventRulesService::class)->defaults()]);
        $tie = TeamTie::create(['draw_id' => $draw->id, 'home_team_id' => $teams[0]->id, 'away_team_id' => $teams[1]->id,
            'round_nr' => 1, 'tie_nr' => 1, 'published_at' => now(), 'status' => TeamTie::STATUS_PUBLISHED]);
        $rubber = TeamFixture::create(['draw_id' => $draw->id, 'team_tie_id' => $tie->id, 'fixture_type' => 1,
            'rubber_code' => 'singles', 'rubber_sequence' => 1, 'round_nr' => 1, 'match_nr' => 1]);

        return compact('draw', 'tie', 'rubber', 'teams', 'regions');
    }

    private function score(TeamFixture $fixture, bool $away = false): void
    {
        app(TeamFixtureScoreService::class)->save($fixture->fresh(), $away
            ? ['set1_home' => 2, 'set1_away' => 6, 'set2_home' => 3, 'set2_away' => 6]
            : ['set1_home' => 6, 'set1_away' => 2, 'set2_home' => 6, 'set2_away' => 3]);
    }

    public function test_disabling_auto_refresh_keeps_current_scores_visible(): void
    {
        $event = $this->event();
        $data = $this->draw($event);
        $event->update(['result_auto_refresh_enabled' => false]);
        $this->score($data['rubber']);
        $this->get(route('frontend.events.standings', $event))->assertOk()
            ->assertSee('data-auto-refresh="0"', false)->assertSee('Reload this page')->assertSee('12–5');
        $this->get(route('frontend.team-draw.standings', $data['draw']))->assertOk()
            ->assertSee('data-auto-refresh="0"', false)->assertSee('12–5');
        $event->update(['result_auto_refresh_enabled' => true]);
        $this->get(route('frontend.events.standings', $event))->assertOk()->assertSee('data-auto-refresh="1"', false);
    }

    public function test_parent_public_page_tracks_each_match_and_complete_tie_then_correction_and_deletion(): void
    {
        $event = $this->event();
        $data = $this->draw($event);
        $second = $data['rubber']->replicate();
        $second->fill(['rubber_sequence' => 2, 'match_nr' => 2])->save();
        $this->score($data['rubber']);
        $response = $this->get(route('frontend.events.standings', $event))->assertOk()
            ->assertSee('data-live-results="event-standings"', false)->assertSee('js/live-results.js')->assertSee('12–5');
        $rows = $response->viewData('overall');
        $this->assertSame([0, 0], array_column($rows, 'played'));
        $this->assertSame([1, 0], array_column($rows, 'rubber_wins'));
        $this->assertSame([2, 0], array_column($rows, 'sets_for'));
        $this->score($second);
        $rows = $this->get(route('frontend.events.standings', $event))->assertOk()->viewData('overall');
        $this->assertSame([1, 1], array_column($rows, 'played'));
        $this->assertSame([1, 0], array_column($rows, 'wins'));
        $this->assertSame([2, 0], array_column($rows, 'rubber_wins'));
        $this->assertSame([24, 10], array_column($rows, 'games_for'));
        $this->score($second, away: true);
        $rows = $this->get(route('frontend.team-draw.standings', $data['draw']))->assertOk()->viewData('rows');
        $this->assertSame([1, 1], array_column($rows, 'draws'));
        $this->assertSame([1, 1], array_column($rows, 'rubber_wins'));
        $this->assertSame([17, 17], array_column($rows, 'games_for'));
        app(TeamFixtureScoreService::class)->delete($second->fresh());
        $rows = $this->get(route('frontend.events.standings', $event))->assertOk()->viewData('overall');
        $this->assertSame([0, 0], array_column($rows, 'played'));
        $this->assertSame([12, 5], array_column($rows, 'games_for'));
        $this->assertDatabaseCount('team_fixture_results', 2);
    }

    public function test_unpublished_draws_private_ties_foreign_pairings_and_unlinked_orphans_are_hidden(): void
    {
        $event = $this->event();
        $local = $this->draw($event);
        $this->score($local['rubber']);
        $private = $this->draw($event, 'Secret draw');
        $this->score($private['rubber']);
        $private['draw']->update(['published' => false]);
        $draft = $this->draw($event, 'Private tie');
        $this->score($draft['rubber']);
        $draft['tie']->update(['published_at' => null, 'status' => TeamTie::STATUS_DRAFT]);
        $foreign = $this->draw($this->event(), 'Foreign event');
        $foreign['rubber']->update(['team_tie_id' => $local['tie']->id, 'rubber_sequence' => 2]);
        $orphan = $local['rubber']->replicate();
        $orphan->fill(['team_tie_id' => null, 'region1' => $local['regions'][0]->id, 'region2' => $local['regions'][1]->id, 'match_nr' => 9])->save();
        $this->score($orphan);
        $response = $this->get(route('frontend.events.standings', $event))->assertOk()->assertDontSee('Secret draw')->assertDontSee('Foreign event');
        $this->assertSame([], $response->viewData('overall'));
        $this->assertSame(0, $response->viewData('stats')['rubbers']);
        // Administrative standings still include private data within the same event.
        $this->assertGreaterThan(0, app(EventStandingsService::class)->forEvent($event)['stats']['rubbers']);
    }

    public function test_public_legacy_draw_includes_region_match_totals_without_fabricating_team_ties(): void
    {
        $event = $this->event();
        $data = $this->draw($event);
        $data['rubber']->delete();
        $data['tie']->delete();
        $data['draw']->update(['team_scoring_rules' => null]);
        $rubber = TeamFixture::create(['draw_id' => $data['draw']->id, 'region1' => $data['regions'][0]->id,
            'region2' => $data['regions'][1]->id, 'fixture_type' => 1, 'round_nr' => 1, 'match_nr' => 1]);
        app(TeamFixtureScoreService::class)->save($rubber, ['set1_home' => 6, 'set1_away' => 2]);
        $this->assertSame(0, $this->get(route('frontend.events.standings', $event))->assertOk()->viewData('stats')['rubbers']);
        $this->score($rubber);
        $rows = $this->get(route('frontend.team-draw.standings', $data['draw']))->assertOk()->assertSee('12–5')->viewData('rows');
        $this->assertSame([1, 0], array_column($rows, 'rubber_wins'));
        $this->assertSame([0, 0], array_column($rows, 'played'));
    }

    public function test_publication_and_filters_apply_to_initial_page_and_refresh_requests(): void
    {
        $event = $this->event();
        $data = $this->draw($event);
        $this->score($data['rubber']);
        $this->get(route('frontend.events.standings', [$event, 'gender' => 'Girls / Women']))->assertOk()->assertViewHas('overall', []);
        $this->get(route('frontend.events.standings', [$event, 'gender' => 'Boys / Men']))->assertOk()->assertSee('12–5');
        $event->update(['published' => false]);
        $this->get(route('frontend.events.standings', $event))->assertNotFound()->assertDontSee('12–5');
        $this->get(route('frontend.team-draw.standings', $data['draw']))->assertNotFound();
    }
}
