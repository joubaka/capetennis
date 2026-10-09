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
        $response->assertSee('data-standings-details', false)->assertSee('beforeprint', false);
        if (getenv('CT_BATCHES121314_QA')) { file_put_contents(storage_path('app/batches121314-qa/standings.html'), $response->getContent()); }
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
        $this->assertSame([3, 0], array_column($stats['ageStandings']['U10'], 'points'));
        $this->assertSame([1, 2], array_column($stats['ageStandings']['U10'], 'rank'));
        $this->assertSame([0, 0], array_column($stats['ageStandings']['U10'], 'played'));
    }

    public function test_age_only_results_combine_genders_and_every_rubber_type_without_other_ages_or_events(): void
    {
        $event = $this->event();
        $boys = $this->draw($event, 'u/10 Boys');
        $girls = $this->draw($event, 'Under10 Girls');
        $mixed = $this->draw($event, 'Under-10 Mixed');
        $older = $this->draw($event, 'U12 Boys');
        foreach ([$girls, $mixed, $older] as $data) {
            foreach ([0, 1] as $side) { $data['teams'][$side]->update(['region_id' => $boys['regions'][$side]->id]); }
        }
        $girls['rubber']->update(['rubber_code' => 'reverse_singles', 'fixture_type' => 4]);
        $mixed['rubber']->update(['rubber_code' => 'mixed_doubles', 'fixture_type' => 3]);
        app(TeamFixtureScoreService::class)->save($mixed['rubber']->fresh(), ['set1_home' => 2, 'set1_away' => 6]);
        foreach (['doubles', 'reverse_doubles', 'reverse_mixed_doubles'] as $index => $code) {
            $rubber = TeamFixture::create(['draw_id' => $boys['draw']->id, 'team_tie_id' => $boys['tie']->id,
                'fixture_type' => $code === 'reverse_mixed_doubles' ? 3 : 2, 'rubber_code' => $code,
                'rubber_sequence' => $index + 2, 'round_nr' => 1, 'match_nr' => $index + 2]);
            app(TeamFixtureScoreService::class)->save($rubber, ['set1_home' => 6, 'set1_away' => 2]);
        }
        $unfinished = $this->draw($event, 'U10 Girls unfinished');
        app(TeamFixtureScoreService::class)->delete($unfinished['rubber']);
        foreach ([0, 1] as $side) { $unfinished['teams'][$side]->update(['region_id' => $boys['regions'][$side]->id]); }
        $this->draw($this->event(), 'U10 Foreign');

        $data = app(EventStandingsService::class)->forEvent($event, ['age' => 'U10']);
        $this->assertCount(4, $data['sections']);
        $this->assertSame(['U10'], array_keys($data['ageStandings']));
        $rows = $data['ageStandings']['U10'];
        $this->assertSame(['Oak Primary School', 'Pine Primary School'], array_column($rows, 'name'));
        $this->assertSame([12, 2], array_column($rows, 'points'));
        $this->assertSame([5, 1], array_column($rows, 'rubber_wins'));
        $this->assertSame([3, 3], array_column($rows, 'played'));
        $this->assertSame([1, 2], array_column($rows, 'rank'));
        $this->assertSame($data['overall'][0]['games_for'], $rows[0]['games_for']);
        $this->assertSame(6, $data['stats']['rubbers']);

        $admin = User::factory()->create();
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        if (getenv('AGE_STANDINGS_SNAPSHOT')) {
            file_put_contents(storage_path('framework/testing/age-standings.html'), $this->actingAs($admin)->get(route('admin.events.standings', $event))->assertOk()->getContent());
        }
        $this->actingAs($admin)->get(route('admin.events.standings', [$event, 'gender' => 'Girls / Women', 'category' => 'Under10 Girls']))
            ->assertOk()->assertSee('Combined results by age group')->assertSee('Under 10')
            ->assertSee('href="'.route('admin.events.standings', [$event, 'age' => 'U10']).'"', false);
        $this->get(route('admin.events.standings', [$event, 'age' => 'U10']))->assertOk()
            ->assertSee('Under 10 combined results')->assertSee('data-age-standings="U10"', false)
            ->assertDontSee('data-age-standings="U12"', false)->assertViewHas('ageStandings', $data['ageStandings']);
    }

    public function test_age_ranks_use_saved_tiebreakers_share_exact_ties_and_suppress_incompatible_rules(): void
    {
        $event = $this->event();
        $boys = $this->draw($event, 'U10 Boys');
        $girls = $this->draw($event, 'U10 Girls');
        foreach ([0, 1] as $side) { $girls['teams'][$side]->update(['region_id' => $boys['regions'][$side]->id]); }
        app(TeamFixtureScoreService::class)->save($girls['rubber']->fresh(), ['set1_home' => 2, 'set1_away' => 6, 'set2_home' => 3, 'set2_away' => 6]);
        $rows = app(EventStandingsService::class)->forEvent($event)['ageStandings']['U10'];
        $this->assertSame([3, 3], array_column($rows, 'points'));
        $this->assertSame([1, 1], array_column($rows, 'rank'));

        $rules = app(TeamEventRulesService::class)->defaults();
        $rules['standings_order'] = ['points', 'game_difference'];
        foreach ([$boys, $girls] as $data) { $data['draw']->update(['team_scoring_rules' => $rules]); }
        app(TeamFixtureScoreService::class)->save($girls['rubber']->fresh(), ['set1_home' => 0, 'set1_away' => 6, 'set2_home' => 0, 'set2_away' => 6]);
        $data = app(EventStandingsService::class)->forEvent($event);
        $this->assertSame(['Pine Primary School', 'Oak Primary School'], array_column($data['ageStandings']['U10'], 'name'));
        $this->assertSame([1, 2], array_column($data['ageStandings']['U10'], 'rank'));
        $this->draw($event, 'U12 Mixed');
        $data = app(EventStandingsService::class)->forEvent($event);
        $this->assertTrue($data['mixedRules']);
        $this->assertFalse($data['ageMixedRules']['U10']);
        $this->assertSame([1, 2], array_column($data['ageStandings']['U10'], 'rank'));

        $rules['tie_win'] = 7;
        $girls['draw']->update(['team_scoring_rules' => $rules]);
        $data = app(EventStandingsService::class)->forEvent($event);
        $this->assertTrue($data['ageMixedRules']['U10']);
        $this->assertSame([null, null], array_column($data['ageStandings']['U10'], 'rank'));
        $this->assertSame(['Oak Primary School', 'Pine Primary School'], array_column($data['ageStandings']['U10'], 'name'));
    }
}
