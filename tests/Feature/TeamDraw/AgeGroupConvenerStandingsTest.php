<?php

namespace Tests\Feature\TeamDraw;

use App\Models\{CategoryEvent, Draw, Event, EventType, Team, TeamFixture, TeamRegion, TeamTie, User};
use App\Services\{AgeGroupConvenerStandingsService, TeamEventRulesService, TeamFixtureScoreService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AgeGroupConvenerStandingsTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        DB::table('eventtypes')->insertOrIgnore(['id' => 3, 'name' => 'Team', 'type' => EventType::TEAM]);
        return Event::factory()->create(['eventType' => 3, 'published' => true, 'standings_published' => false]);
    }

    private function draw(Event $event, string $age = 'U10', string $gender = 'Boys'): array
    {
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $category->category->update(['name' => $age]);
        $regions = collect(['Oak', 'Pine'])->map(fn ($name) => TeamRegion::create(['region_name' => $name, 'short_name' => $name]));
        $event->regions()->attach($regions->pluck('id'));
        $teams = $regions->map(fn ($region) => Team::factory()->create(['category_event_id' => $category->id, 'region_id' => $region->id]));
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true, 'drawName' => "$age $gender",
            'category_event_id' => $category->id, 'team_scoring_rules' => app(TeamEventRulesService::class)->defaults()]);
        $tie = TeamTie::create(['draw_id' => $draw->id, 'home_team_id' => $teams[0]->id, 'away_team_id' => $teams[1]->id,
            'round_nr' => 1, 'tie_nr' => 1, 'published_at' => now(), 'status' => TeamTie::STATUS_PUBLISHED]);
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'team_tie_id' => $tie->id, 'fixture_type' => 1,
            'rubber_code' => 'singles', 'rubber_sequence' => 1, 'round_nr' => 1, 'match_nr' => 1]);
        return compact('draw', 'tie', 'fixture');
    }

    private function score(TeamFixture $fixture): void
    {
        app(TeamFixtureScoreService::class)->save($fixture->fresh(), ['set1_home' => 6, 'set1_away' => 2, 'set2_home' => 6, 'set2_away' => 3]);
    }

    private function assign(Event $event, User $actor, string $age = 'U10'): void
    {
        DB::table('event_age_group_conveners')->insert(['event_id' => $event->id, 'age_group' => $age, 'user_id' => $actor->id]);
    }

    public function test_only_current_assigned_account_can_read_its_age_and_event(): void
    {
        $event = $this->event();
        $data = $this->draw($event);
        $actor = User::factory()->create();
        $this->assign($event, $actor);
        $url = route('frontend.events.convener-standings', [$event, 'U10']);
        $this->get($url)->assertRedirect();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs($actor)->get(route('frontend.events.convener-standings', [$event, 'U12']))->assertForbidden();
        $this->get(route('frontend.events.convener-standings', [$this->event(), 'U10']))->assertForbidden();
        $this->score($data['fixture']);
        $response = $this->get($url.'?age=U12&gender=Girls')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertSame(['rows', 'mixedRules'], array_keys($response->viewData('standings')));
        $response->assertSee('Oak')->assertSee('Pine');
        $this->get(route('frontend.events.standings', $event))->assertNotFound();
        $data['draw']->update(['published' => false]);
        $this->get(route('events.show', $event))->assertOk()->assertSee('U10 convener standings');
        $this->get(route('admin.events.settings', $event))->assertForbidden();
        DB::table('event_age_group_conveners')->where('event_id', $event->id)->delete();
        $this->get($url)->assertForbidden();
        $this->get(route('events.show', $event))->assertOk()->assertDontSee('U10 convener standings');
    }

    public function test_all_gender_matches_and_hidden_draws_must_complete_and_reopening_hides_data(): void
    {
        $event = $this->event();
        $actor = User::factory()->create();
        $this->assign($event, $actor);
        $boys = $this->draw($event);
        $girls = $this->draw($event, gender: 'Girls');
        $girls['draw']->update(['published' => false]);
        $mixed = $this->draw($event, gender: 'Mixed');
        $this->draw($event, 'U12'); // Other ages do not delay this group's completion.
        $url = route('frontend.events.convener-standings', [$event, 'U10']);
        $this->actingAs($actor)->get($url)->assertOk()->assertViewHas('standings', null)->assertDontSee('Oak');
        $this->score($boys['fixture']);
        $this->score($girls['fixture']);
        $this->get($url)->assertViewHas('standings', null);
        $this->score($mixed['fixture']);
        $this->get($url)->assertOk()->assertSee('Oak')->assertDontSee('U12');
        $girls['fixture']->teamResults()->delete();
        $this->get($url)->assertOk()->assertViewHas('standings', null)->assertDontSee('Oak');
        $this->score($girls['fixture']);
        $boys['tie']->update(['format_snapshot' => ['rubbers' => [['sequence' => 1], ['sequence' => 2]]]]);
        $this->get($url)->assertViewHas('standings', null);
    }

    public function test_individual_age_matches_and_cross_event_tie_cannot_certify_completion(): void
    {
        $event = $this->event();
        $data = $this->draw($event);
        $this->score($data['fixture']);
        $originalAway = $data['tie']->away_team_id;
        $foreign = $this->draw($this->event());
        $data['tie']->update(['away_team_id' => $foreign['tie']->away_team_id]);
        $service = app(AgeGroupConvenerStandingsService::class);
        $this->assertFalse($service->complete($event, 'U10'));
        $data['tie']->update(['away_team_id' => $originalAway]);
        $fixture = \App\Models\Fixture::factory()->create(['draw_id' => $data['draw']->id, 'registration1_id' => 11, 'registration2_id' => 12]);
        $this->assertFalse($service->complete($event, 'U10'));
        foreach ([1, 2] as $set) {
            DB::table('fixture_results')->insert(['fixture_id' => $fixture->id, 'set_nr' => $set, 'registration1_score' => 6, 'registration2_score' => 2]);
        }
        $this->assertTrue($service->complete($event, 'U10'));
        $fixture->fixtureResults()->delete();
        $this->assertFalse($service->complete($event, 'U10'));
    }

    public function test_empty_age_group_is_not_complete(): void
    {
        $event = $this->event();
        $this->assertFalse(app(AgeGroupConvenerStandingsService::class)->complete($event, 'U10'));
        $data = $this->draw($event);
        $data['fixture']->delete();
        $this->assertFalse(app(AgeGroupConvenerStandingsService::class)->complete($event, 'U10'));
    }

    public function test_settings_validate_event_ages_save_replace_remove_and_preserve_partial_updates(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $event = $this->event();
        $this->draw($event);
        $admin = User::factory()->create()->assignRole('admin');
        $actor = User::factory()->create();
        $replacement = User::factory()->create();
        $event->admins()->attach($admin);
        $url = route('admin.events.settings.update', $event);
        $this->actingAs($actor)->patchJson($url, ['age_group_conveners' => ['U10' => $actor->id]])->assertForbidden();
        $this->actingAs($admin)->patchJson($url, ['age_group_conveners' => ['U99' => $actor->id]])->assertUnprocessable();
        $this->patchJson($url, ['age_group_conveners' => ['U10' => 99999999]])->assertUnprocessable();
        $this->patchJson($url, ['age_group_conveners' => ['U10' => $actor->id]])->assertOk();
        $this->assertDatabaseHas('event_age_group_conveners', ['event_id' => $event->id, 'age_group' => 'U10', 'user_id' => $actor->id]);
        $this->patchJson($url, ['name' => 'Renamed'])->assertOk();
        $this->assertDatabaseCount('event_age_group_conveners', 1);
        $this->get(route('admin.events.settings', $event))->assertOk()->assertSee('U10 main convener')->assertSee($actor->email);
        $this->patchJson($url, ['age_group_conveners' => ['U10' => $replacement->id]])->assertOk();
        $this->assertDatabaseCount('event_age_group_conveners', 1);
        $this->assertFalse($actor->fresh()->hasAnyRole(['admin', 'convenor', 'score-keeper']));
        $this->assertDatabaseMissing('event_convenors', ['event_id' => $event->id, 'user_id' => $actor->id]);
        $this->patchJson($url, ['age_group_conveners' => ['U10' => null]])->assertOk();
        $this->assertDatabaseCount('event_age_group_conveners', 0);
    }
}
