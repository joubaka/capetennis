<?php

namespace Tests\Feature\TeamDraw;

use App\Models\{Category, CategoryEvent, Draw, Event, EventType, Team, TeamFixture, TeamRegion, TeamTie, User};
use App\Services\{EventStandingsService, TeamEventRulesService, TeamFixtureScoreService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventStandingsTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        DB::table('eventtypes')->updateOrInsert(['id' => 3], ['name' => 'Team', 'type' => EventType::TEAM]);
        return Event::factory()->create(['eventType' => 3, 'name' => 'Schools tournament']);
    }

    private function draw(Event $event, string $categoryName): array
    {
        $category = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => $categoryName])->id]);
        $regions = collect(['Oak Primary School', 'Pine Primary School'])->map(fn ($name) => TeamRegion::create(['region_name' => $name, 'short_name' => $name]));
        $event->regions()->attach($regions->pluck('id'));
        $teams = $regions->map(fn ($region) => Team::factory()->create(['category_event_id' => $category->id, 'region_id' => $region->id]));
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'drawName' => $categoryName.' singles', 'team_scoring_rules' => app(TeamEventRulesService::class)->defaults()]);
        $tie = TeamTie::create(['draw_id' => $draw->id, 'home_team_id' => $teams[0]->id, 'away_team_id' => $teams[1]->id, 'round_nr' => 1, 'tie_nr' => 1, 'status' => 'published', 'published_at' => now()]);
        $rubber = TeamFixture::create(['draw_id' => $draw->id, 'team_tie_id' => $tie->id, 'fixture_type' => 1, 'rubber_code' => 'singles', 'rubber_sequence' => 1, 'round_nr' => 1, 'match_nr' => 1]);
        app(TeamFixtureScoreService::class)->save($rubber, ['set1_home' => 6, 'set1_away' => 2, 'set2_home' => 6, 'set2_away' => 3]);
        return compact('draw', 'tie', 'rubber', 'teams', 'regions');
    }

    public function test_event_access_and_read_only_render(): void
    {
        $event = $this->event();
        $fixture = $this->draw($event, 'u/10 Boys');
        $other = $this->event();
        $this->getJson(route('admin.events.standings', $event))->assertUnauthorized();
        $admin = User::factory()->create();
        $this->actingAs($admin)->get(route('admin.events.standings', $event))->assertForbidden();
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->get(route('admin.events.standings', $other))->assertForbidden();
        $response = $this->get(route('admin.events.standings', $event))->assertOk()->assertSee('Full event standings')->assertSee('Oak Primary School')->assertSee('Gender breakdown')->assertSee('View match results');
        $this->assertDatabaseCount('team_ties', 1);
        $this->assertDatabaseCount('team_fixture_results', 2);
        $this->assertSame('completed', $fixture['tie']->fresh()->status);
        if (getenv('EVENT_STANDINGS_SNAPSHOT')) {
            if (!is_dir(storage_path('app/testing'))) { mkdir(storage_path('app/testing'), 0777, true); }
            file_put_contents(storage_path('app/testing/event-standings.html'), $response->getContent());
        }
    }

    public function test_filters_and_foreign_team_ties_are_excluded(): void
    {
        $event = $this->event();
        $boys = $this->draw($event, 'u/10 Boys');
        $this->draw($event, 'u/12 Girls');
        $foreign = $this->draw($this->event(), 'Foreign category');
        TeamTie::create(['draw_id' => $boys['draw']->id, 'home_team_id' => $boys['teams'][0]->id, 'away_team_id' => $foreign['teams'][0]->id, 'round_nr' => 2, 'tie_nr' => 2, 'status' => 'draft']);
        $data = app(EventStandingsService::class)->forEvent($event, ['gender' => 'Boys / Men', 'age' => 'U10']);
        $this->assertSame(1, $data['stats']['draws']);
        $this->assertEquals(1, $data['stats']['ties']);
        $this->assertSame(1, $data['stats']['rubbers']);
        $this->assertCount(2, $data['overall']);
        $this->assertSame([3, 0], array_column($data['overall'], 'points'));
        $this->assertNotContains('Foreign category', $data['options']['category']);
        $this->assertSame([], app(EventStandingsService::class)->forEvent($event, ['category' => 'Foreign category'])['sections']);
    }

    public function test_mixed_scoring_snapshots_do_not_claim_overall_ranks(): void
    {
        $event = $this->event();
        $this->draw($event, 'u/10 Boys');
        $other = $this->draw($event, 'u/12 Girls');
        $rules = app(TeamEventRulesService::class)->defaults();
        $rules['tie_win'] = 7;
        $other['draw']->update(['team_scoring_rules' => $rules]);
        $data = app(EventStandingsService::class)->forEvent($event);
        $this->assertTrue($data['mixedRules']);
        $this->assertSame([null, null, null, null], array_column($data['overall'], 'rank'));
        $this->assertContains(10, array_column($data['overall'], 'points'));
    }

    public function test_foreign_draw_rubber_cannot_contaminate_an_event_tie(): void
    {
        $event = $this->event();
        $local = $this->draw($event, 'u/10 Boys');
        $foreign = $this->draw($this->event(), 'Foreign category');
        $foreign['rubber']->update(['team_tie_id' => $local['tie']->id, 'rubber_sequence' => 2]);
        $data = app(EventStandingsService::class)->forEvent($event);
        $this->assertSame([], $data['overall']);
        $this->assertSame(0, $data['stats']['rubbers']);
    }

    public function test_two_categories_combine_into_one_school_row(): void
    {
        $event = $this->event();
        $boys = $this->draw($event, 'u/10 Boys');
        $girls = $this->draw($event, 'u/12 Girls');
        foreach ([0, 1] as $side) { $girls['teams'][$side]->update(['region_id' => $boys['regions'][$side]->id]); }
        $data = app(EventStandingsService::class)->forEvent($event);
        $this->assertCount(2, $data['overall']);
        $this->assertSame([6, 0], array_column($data['overall'], 'points'));
        $this->assertSame([2, 2], array_column($data['overall'], 'played'));
        $this->assertSame([1, 2], array_column($data['overall'], 'rank'));
        $this->assertSame(4, $data['stats']['teams']);
        $this->assertSame(2, $data['stats']['rubbers']);
    }

    public function test_legacy_results_contribute_without_counting_partial_matches(): void
    {
        $event = $this->event();
        $data = $this->draw($event, 'u/10 Boys');
        $data['rubber']->delete();
        $data['tie']->delete();
        $data['draw']->update(['team_scoring_rules' => null]);
        $rubber = TeamFixture::create(['draw_id' => $data['draw']->id, 'region1' => $data['regions'][0]->id, 'region2' => $data['regions'][1]->id, 'fixture_type' => 1, 'round_nr' => 1, 'match_nr' => 2]);
        app(TeamFixtureScoreService::class)->save($rubber, ['set1_home' => 6, 'set1_away' => 2]);
        $this->assertSame(0, app(EventStandingsService::class)->forEvent($event)['stats']['rubbers']);
        app(TeamFixtureScoreService::class)->save($rubber->fresh(), ['set1_home' => 6, 'set1_away' => 2, 'set2_home' => 6, 'set2_away' => 3]);
        $stats = app(EventStandingsService::class)->forEvent($event);
        $this->assertSame(1, $stats['stats']['rubbers']);
        $this->assertEquals(0, $stats['stats']['ties']);
        $this->assertSame([3, 0], array_column($stats['overall'], 'points'));
        $this->assertSame([12, 5], array_column($stats['overall'], 'games_for'));
    }
}
