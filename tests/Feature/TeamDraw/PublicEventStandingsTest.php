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

        return Event::factory()->create(['eventType' => 3, 'published' => true, 'standings_published' => true]);
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

    public function test_standings_publication_is_explicit_event_scoped_idempotent_and_separate(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'convenor', 'guard_name' => 'web']);
        $event = $this->event();
        $event->forceFill(['standings_published' => false])->save();
        $data = $this->draw($event);
        $admin = \App\Models\User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $foreign = $this->event();
        $foreign->forceFill(['standings_published' => false])->save();
        $url = route('admin.events.standings.publication', $event);
        $before = $event->fresh()->only(['published', 'results_published', 'result_notifications_enabled', 'result_auto_refresh_enabled']);
        $drawBefore = $data['draw']->fresh()->only(['published', 'oop_published', 'results_published']);
        $this->get(route('events.show', $event))->assertOk()->assertDontSee('Team standings and match totals');
        $this->get(route('frontend.events.standings', $event))->assertNotFound();
        $this->get(route('frontend.team-draw.standings', $data['draw']))->assertNotFound();
        $this->actingAs($admin)->get(route('frontend.events.standings', $event))->assertNotFound();
        $this->get(route('admin.events.standings', $event))->assertOk()->assertSee('Publish standings');
        $this->patchJson($url, [])->assertUnprocessable()->assertJsonValidationErrors('standings_published');
        $this->patchJson($url, ['standings_published' => 'yes'])->assertUnprocessable();
        $this->patchJson(route('admin.events.standings.publication', $foreign), ['standings_published' => true])->assertForbidden();
        $this->patchJson($url, ['standings_published' => true, 'published' => false])->assertOk()->assertJsonPath('standings_published', true);
        $auditCount = DB::table('audit_events')->where('action', 'event.standings.publication.changed')->count();
        $this->assertSame(1, $auditCount);
        $this->patchJson($url, ['standings_published' => true])->assertOk();
        $this->assertSame($auditCount, DB::table('audit_events')->where('action', 'event.standings.publication.changed')->count());
        $this->get(route('events.show', $event))->assertOk()->assertSee('Team standings and match totals');
        $this->get(route('frontend.events.standings', $event))->assertOk();
        $this->get(route('frontend.team-draw.standings', $data['draw']))->assertOk();
        $this->assertSame($before, $event->fresh()->only(array_keys($before)));
        $this->assertSame($drawBefore, $data['draw']->fresh()->only(array_keys($drawBefore)));
        $this->assertFalse($foreign->fresh()->standings_published);
        $this->patchJson($url, ['standings_published' => false])->assertOk();
        $this->assertSame(2, DB::table('audit_events')->where('action', 'event.standings.publication.changed')->count());
        $this->get(route('events.show', $event))->assertOk()->assertDontSee('Team standings and match totals');
        $this->get(route('frontend.events.standings', $event))->assertNotFound();
        $this->get(route('frontend.team-draw.standings', $data['draw']))->assertNotFound();
        $this->actingAs(\App\Models\User::factory()->create())->patchJson($url, ['standings_published' => true])->assertForbidden();
        $convenor = \App\Models\User::factory()->create()->assignRole('convenor');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $convenor->id]);
        $this->actingAs($convenor)->patchJson($url, ['standings_published' => true])->assertForbidden();
        $this->assertFalse($event->fresh()->standings_published);
    }

    public function test_standings_default_private_and_generic_fill_cannot_publish(): void
    {
        $event = Event::factory()->create(['eventType' => 3]);
        $this->assertFalse($event->fresh()->standings_published);
        $event->fill(['standings_published' => true])->save();
        $this->assertFalse($event->fresh()->standings_published);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(EventStandingsService::class)->forEvent($event, publishedOnly: true);
    }

    public function test_disabling_auto_refresh_keeps_current_scores_visible(): void
    {
        $event = $this->event();
        $data = $this->draw($event);
        $event->update(['result_auto_refresh_enabled' => false]);
        $this->score($data['rubber']);
        $this->get(route('frontend.events.standings', $event))->assertOk()
            ->assertSee('data-auto-refresh="0"', false)->assertSee('Reload this page')->assertSee('data-points="3"', false)->assertDontSee('12–5');
        $this->get(route('frontend.team-draw.standings', $data['draw']))->assertOk()
            ->assertSee('data-auto-refresh="0"', false)->assertSee('12–5');
        $event->update(['result_auto_refresh_enabled' => true]);
        $this->get(route('frontend.events.standings', $event))->assertOk()->assertSee('data-auto-refresh="1"', false);
    }

    public function test_public_age_points_combine_regions_across_genders_keep_ages_separate_and_ignore_hidden_filters(): void
    {
        $event = $this->event();
        $boys = $this->draw($event, 'U9 Boys');
        $girls = $this->draw($event, 'U9 Girls');
        $older = $this->draw($event, 'U10 Boys');
        foreach ([[$boys, 'U9'], [$girls, 'U9'], [$older, 'U10']] as [$data, $age]) {
            $data['draw']->categoryEvent->category->update(['name' => $age]);
            foreach ($data['teams'] as $index => $team) $team->update(['region_id' => $boys['regions'][$index]->id]);
        }
        $this->score($boys['rubber']);
        $this->score($girls['rubber']);
        $this->score($older['rubber'], away: true);
        $private = $this->draw($event, 'U9 Private');
        $this->score($private['rubber']);
        $private['draw']->update(['published' => false]);
        $foreign = $this->draw($this->event(), 'U9 Foreign');
        $this->score($foreign['rubber']);
        $url = route('frontend.events.standings', $event);
        $response = $this->get($url)->assertOk()->assertSee('Running points totals by age group')
            ->assertSee('data-points="6"', false)->assertDontSee('Standings by draw')->assertDontSee('12–5');
        $groups = $response->viewData('ageGroups');
        $this->assertSame(['U9', 'U10'], array_keys($groups));
        $this->assertSame(['Audit Oak', 'Audit Pine'], array_column($groups['U9'], 'name'));
        $this->assertSame([6, 0], array_column($groups['U9'], 'points'));
        $this->assertSame(['Audit Pine', 'Audit Oak'], array_column($groups['U10'], 'name'));
        $this->assertSame([3, 0], array_column($groups['U10'], 'points'));
        $this->assertSame(['U9'], array_keys($this->get($url.'?age=U9')->assertOk()->viewData('ageGroups')));
        $this->assertSame($groups, $this->get($url.'?gender=Girls%20%2F%20Women&category=Unknown')->assertOk()->viewData('ageGroups'));
        $rules = app(TeamEventRulesService::class)->defaults();
        $rules['rubbers']['singles']['straight_win'] = 5;
        $girls['draw']->update(['team_scoring_rules' => $rules]);
        $this->score($girls['rubber'], away: true);
        $mixed = $this->get($url)->assertOk()->assertSee('Points use each draw’s scoring rules.');
        $this->assertTrue($mixed->viewData('ageMixedRules')['U9']);
        $this->assertSame(['Audit Oak', 'Audit Pine'], array_column($mixed->viewData('ageGroups')['U9'], 'name'));
        $this->assertSame([3, 5], array_column($mixed->viewData('ageGroups')['U9'], 'points'));
    }

    public function test_public_age_points_distinguishes_zero_points_from_no_participants(): void
    {
        $event = $this->event();
        $url = route('frontend.events.standings', $event);
        $this->get($url)->assertOk()->assertSee('No published points or participants for this age group yet.');
        $this->draw($event);
        $this->get($url)->assertOk()->assertSee('No completed match points yet.')->assertSee('data-points="0"', false);
    }

    public function test_parent_public_page_tracks_each_match_and_complete_tie_then_correction_and_deletion(): void
    {
        $event = $this->event();
        $data = $this->draw($event);
        $second = $data['rubber']->replicate();
        $second->fill(['rubber_sequence' => 2, 'match_nr' => 2])->save();
        $this->score($data['rubber']);
        $response = $this->get(route('frontend.events.standings', $event))->assertOk()
            ->assertSee('data-live-results="event-standings"', false)->assertSee('js/live-results.js')->assertSee('data-points="3"', false)->assertDontSee('12–5');
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
        $age = app(EventStandingsService::class)->forEvent($event, publishedOnly: true)['sections'][0]['age'];
        $this->get(route('frontend.events.standings', [$event, 'age' => 'U99']))->assertOk()->assertViewHas('overall', []);
        $this->get(route('frontend.events.standings', [$event, 'age' => $age]))->assertOk()->assertSee('data-points="3"', false);
        $event->update(['published' => false]);
        $this->get(route('frontend.events.standings', $event))->assertNotFound()->assertDontSee('12–5');
        $this->get(route('frontend.team-draw.standings', $data['draw']))->assertNotFound();
    }
}
