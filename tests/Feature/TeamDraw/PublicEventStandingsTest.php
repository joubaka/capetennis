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
        $this->get(route('events.show', $event))->assertOk()->assertDontSee('data-event-standings-card', false);
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
        $this->get(route('events.show', $event))->assertOk()->assertSee('data-event-standings-card', false)
            ->assertSee('href="'.route('frontend.events.standings', $event).'"', false);
        $this->get(route('frontend.events.standings', $event))->assertOk();
        $this->get(route('frontend.team-draw.standings', $data['draw']))->assertOk();
        $this->assertSame($before, $event->fresh()->only(array_keys($before)));
        $this->assertSame($drawBefore, $data['draw']->fresh()->only(array_keys($drawBefore)));
        $this->assertFalse($foreign->fresh()->standings_published);
        $this->patchJson($url, ['standings_published' => false])->assertOk();
        $this->assertSame(2, DB::table('audit_events')->where('action', 'event.standings.publication.changed')->count());
        $this->get(route('events.show', $event))->assertOk()->assertDontSee('data-event-standings-card', false);
        $this->get(route('frontend.events.standings', $event))->assertNotFound();
        $this->get(route('frontend.team-draw.standings', $data['draw']))->assertNotFound();
        $this->actingAs(\App\Models\User::factory()->create())->patchJson($url, ['standings_published' => true])->assertForbidden();
        $convenor = \App\Models\User::factory()->create()->assignRole('convenor');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $convenor->id]);
        $this->actingAs($convenor)->patchJson($url, ['standings_published' => true])->assertForbidden();
        $this->assertFalse($event->fresh()->standings_published);
    }

    public function test_public_preview_requires_the_event_publisher_and_preserves_publication(): void
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $event = $this->event();
        $event->forceFill(['standings_published' => false])->save();
        $local = $this->draw($event, 'U10 Boys');
        $local['draw']->categoryEvent->category->update(['name' => 'U10']);
        $this->score($local['rubber']);
        $private = $this->draw($event, 'U11 Secret');
        $private['draw']->categoryEvent->category->update(['name' => 'U11']);
        $this->score($private['rubber']);
        $private['draw']->update(['published' => false]);
        $draft = $this->draw($event, 'U12 Private tie');
        $draft['draw']->categoryEvent->category->update(['name' => 'U12']);
        $this->score($draft['rubber']);
        $draft['tie']->update(['published_at' => null, 'status' => TeamTie::STATUS_DRAFT]);
        $url = route('admin.events.standings.preview', $event);
        $this->get($url)->assertRedirect();
        $ordinary = \App\Models\User::factory()->create();
        $this->actingAs($ordinary)->get($url)->assertForbidden();
        $foreignAdmin = \App\Models\User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $this->event()->id, 'user_id' => $foreignAdmin->id]);
        $this->actingAs($foreignAdmin)->get($url)->assertForbidden();
        $admin = \App\Models\User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $before = $event->fresh()->getAttributes();
        $auditCount = DB::table('audit_events')->where('category', 'publication')->count();
        $drawBefore = $private['draw']->fresh()->getAttributes();
        $tieBefore = $draft['tie']->fresh()->getAttributes();
        $response = $this->actingAs($admin)->get($url)->assertOk()->assertSee('Private preview')
            ->assertSee('Back to admin standings')->assertSee('data-points="3"', false)
            ->assertSee('action="'.$url.'"', false)->assertSee('href="'.$url.'"', false)
            ->assertDontSee('U11 Secret')->assertDontSee('U12 Private tie')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $groups = $response->viewData('ageGroups');
        $this->assertSame(['U10'], array_keys($groups));
        $this->assertSame([3, 0], array_column($groups['U10'], 'points'));
        $this->get($url.'?age=U99')->assertOk()->assertViewHas('ageGroups', []);
        $this->assertSame($groups, $this->get($url.'?age=U10')->assertOk()->viewData('ageGroups'));
        $this->get(route('frontend.events.standings', $event))->assertNotFound();
        $backend = $this->get(route('admin.events.standings', $event))->assertOk()
            ->assertSee('Preview public standings')->assertSee('target="_blank" rel="noopener"', false);
        $this->assertSame($before, $event->fresh()->getAttributes());
        $this->assertSame($drawBefore, $private['draw']->fresh()->getAttributes());
        $this->assertSame($tieBefore, $draft['tie']->fresh()->getAttributes());
        $this->assertSame($auditCount, DB::table('audit_events')->where('category', 'publication')->count());
        $event->forceFill(['standings_published' => true])->save();
        $public = $this->get(route('frontend.events.standings', $event))->assertOk()->assertDontSee('Private preview');
        $this->assertSame($groups, $public->viewData('ageGroups'));
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
        $response = $this->get(route('frontend.events.standings', $event))->assertOk()
            ->assertSee('data-auto-refresh="0"', false)->assertSee('Reload this page')->assertSee('data-points="3"', false)
            ->assertSee('<details class="points-details">', false)->assertDontSee('<details class="points-details" open', false)
            ->assertSee('12–5');
        $this->assertSame([12, 5], array_column($response->viewData('overall'), 'games_for'));
        $this->assertSame([2, 0], array_column($response->viewData('overall'), 'sets_for'));
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
        $response = $this->get($url)->assertOk()->assertSee('Team standings')
            ->assertSee('data-points="6"', false)->assertDontSee('Standings by draw')->assertSee('12–5')
            ->assertSee('<details class="points-details">', false);
        $groups = $response->viewData('ageGroups');
        $this->assertSame(['U9', 'U10'], array_keys($groups));
        $this->assertSame(['Audit Oak', 'Audit Pine'], array_column($groups['U9'], 'name'));
        $this->assertSame([6, 0], array_column($groups['U9'], 'points'));
        $this->assertSame(['boys' => 3, 'girls' => 3, 'mixed' => 0, 'other' => 0], $groups['U9'][0]['gender_points']);
        $response->assertSeeInOrder(['>Pos.<', '>Region / school<', '>Points<', '>Boys<', '>Girls<', '>Mixed<', '>Sets W–L<', '>Games W–L<'], false)
            ->assertDontSee('>Other<', false);
        $ranked = $response->viewData('ageStandings')['U9'];
        $this->assertSame(['Audit Oak', 'Audit Pine'], array_column($ranked, 'name'));
        $this->assertSame([1, 2], array_column($ranked, 'rank'));
        $this->assertSame([24, 10], array_column($ranked, 'games_for'));
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
        $this->assertSame(['boys' => 3, 'girls' => 0, 'mixed' => 0, 'other' => 0], $mixed->viewData('ageGroups')['U9'][0]['gender_points']);
        $this->assertSame(['boys' => 0, 'girls' => 5, 'mixed' => 0, 'other' => 0], $mixed->viewData('ageGroups')['U9'][1]['gender_points']);
        $mixedRanked = $mixed->viewData('ageStandings')['U9'];
        $this->assertSame(['Audit Oak', 'Audit Pine'], array_column($mixedRanked, 'name'));
        $this->assertSame([3, 5], array_column($mixedRanked, 'points'));
        $this->assertSame([null, null], array_column($mixedRanked, 'rank'));
        $girls['draw']->update(['team_scoring_rules' => app(TeamEventRulesService::class)->defaults()]);
        $this->score($girls['rubber'], away: true);
        $tied = $this->get($url)->assertOk();
        $this->assertFalse($tied->viewData('ageMixedRules')['U9']);
        $tiedRows = $tied->viewData('ageStandings')['U9'];
        $this->assertSame(['Audit Oak', 'Audit Pine'], array_column($tiedRows, 'name'));
        $this->assertSame([3, 3], array_column($tiedRows, 'points'));
        $this->assertSame([1, 1], array_column($tiedRows, 'rank'));
        $this->assertSame([17, 17], array_column($tiedRows, 'games_for'));
    }

    public function test_gender_points_use_rubber_gender_and_include_draw_bonuses_without_losing_points(): void
    {
        $event = $this->event();
        $data = $this->draw($event, 'U12 Boys');
        $data['draw']->categoryEvent->category->update(['name' => 'U12']);
        $rules = app(TeamEventRulesService::class)->defaults();
        $rules['tie_win'] = 4;
        $data['draw']->update(['team_scoring_rules' => $rules]);
        $this->score($data['rubber']);
        foreach (['doubles' => 'male', 'reverse_doubles' => 'female', 'mixed_doubles' => null, 'reverse_mixed_doubles' => null] as $code => $gender) {
            $rubber = $data['rubber']->replicate();
            $sequence = $data['tie']->rubbers()->count() + 1;
            $rubber->fill(['rubber_code' => $code, 'gender_rule' => $gender, 'fixture_type' => str_contains($code, 'mixed') ? 3 : 2,
                'rubber_sequence' => $sequence, 'match_nr' => $sequence])->save();
            app(TeamFixtureScoreService::class)->save($rubber, ['set1_home' => 6, 'set1_away' => 2]);
        }
        $url = route('frontend.events.standings', $event);
        $response = $this->get($url)->assertOk();
        $row = $response->viewData('ageGroups')['U12'][0];
        $this->assertSame(['boys' => 9, 'girls' => 2, 'mixed' => 4, 'other' => 0], $row['gender_points']);
        $this->assertSame(15, $row['points']);
        $this->assertSame($row['points'], array_sum($row['gender_points']));

        $data['draw']->update(['drawName' => 'U12 Open', 'gender' => null]);
        $response = $this->get($url)->assertOk()->assertSee('>Other<', false);
        $row = $response->viewData('ageGroups')['U12'][0];
        $this->assertSame(['boys' => 2, 'girls' => 2, 'mixed' => 4, 'other' => 7], $row['gender_points']);
        $this->assertSame($row['points'], array_sum($row['gender_points']));
        $data['draw']->categoryEvent->category->update(['name' => 'U12 Girls']);
        $row = $this->get($url)->assertOk()->viewData('ageGroups')['U12'][0];
        $this->assertSame(['boys' => 2, 'girls' => 9, 'mixed' => 4, 'other' => 0], $row['gender_points']);
        $data['draw']->update(['gender' => 'male']);
        $row = $this->get($url)->assertOk()->viewData('ageGroups')['U12'][0];
        $this->assertSame(['boys' => 9, 'girls' => 2, 'mixed' => 4, 'other' => 0], $row['gender_points']);
        $data['tie']->update(['published_at' => null, 'status' => TeamTie::STATUS_DRAFT]);
        $this->assertSame([], $this->get($url)->assertOk()->viewData('ageGroups')['U12'] ?? []);
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
            ->assertSee('data-live-results="event-standings"', false)->assertSee('js/live-results.js')->assertSee('data-points="3"', false)
            ->assertSee('<details class="points-details">', false)->assertDontSee('<details class="points-details" open', false)
            ->assertSee('12–5');
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
        $rubber->update(['fixture_type' => 3]);
        // Legacy mixed doubles takes its gender from its fixture type even in a boys draw.
        $groups = $this->get(route('frontend.events.standings', $event))->assertOk()->viewData('ageGroups');
        $row = array_values($groups)[0][0];
        $this->assertSame($row['points'], $row['gender_points']['mixed']);
        $this->assertSame(0, $row['gender_points']['boys']);
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
