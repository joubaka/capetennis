<?php

namespace Tests\Feature\TeamDraw;

use App\Models\{CategoryEvent, Draw, Event, EventType, Team, TeamFixture, TeamRegion, TeamTie, User};
use App\Services\{EventStandingsService, TeamEventRulesService, TeamFixtureScoreService};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventPointSharingTest extends TestCase
{
    use RefreshDatabase;

    private function setupEvent(): array
    {
        DB::table('eventtypes')->insertOrIgnore(['id' => 3, 'name' => 'Team', 'type' => EventType::TEAM]);
        $event = Event::factory()->create(['eventType' => 3, 'published' => true, 'standings_published' => true]);
        $regions = collect(['Invitational', 'Recipient A', 'Recipient B', 'Opponent'])->map(fn ($name) => TeamRegion::create(['region_name' => $name, 'short_name' => $name]));
        $event->regions()->attach($regions->pluck('id'));
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $category->category->update(['name' => 'U13']);
        $teams = collect();
        foreach (['Boys', 'Girls'] as $gender) {
            $source = Team::factory()->create(['name' => 'Invitational U13 '.$gender, 'category_event_id' => $category->id, 'region_id' => $regions[0]->id]);
            $opponent = Team::factory()->create(['category_event_id' => $category->id, 'region_id' => $regions[3]->id]);
            $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true, 'drawName' => 'U13 '.$gender,
                'category_event_id' => $category->id, 'team_scoring_rules' => app(TeamEventRulesService::class)->defaults()]);
            $tie = TeamTie::create(['draw_id' => $draw->id, 'home_team_id' => $source->id, 'away_team_id' => $opponent->id,
                'round_nr' => 1, 'tie_nr' => 1, 'published_at' => now(), 'status' => TeamTie::STATUS_PUBLISHED]);
            $rubber = TeamFixture::create(['draw_id' => $draw->id, 'team_tie_id' => $tie->id, 'fixture_type' => 1,
                'rubber_code' => 'singles', 'rubber_sequence' => 1, 'round_nr' => 1, 'match_nr' => 1]);
            app(TeamFixtureScoreService::class)->save($rubber, ['set1_home' => 6, 'set1_away' => 2, 'set2_home' => 6, 'set2_away' => 3]);
            $teams->push($source);
        }
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        $event->admins()->attach($admin);
        $this->actingAs($admin);
        return compact('event', 'regions', 'teams', 'admin');
    }

    private function payload($teams, $regions, string $mode = 'full', bool $enabled = true): array
    {
        return ['enabled' => $enabled, 'rules' => $teams->map(fn ($team) => ['team_id' => $team->id, 'enabled' => true,
            'mode' => $mode, 'region_ids' => [$regions[1]->id, $regions[2]->id]])->all()];
    }

    public function test_two_sources_share_full_points_without_changing_source_competition_or_counts_and_disable_restores(): void
    {
        ['event' => $event, 'regions' => $regions, 'teams' => $teams] = $this->setupEvent();
        $service = app(EventStandingsService::class);
        $before = $service->forEvent($event, publishedOnly: true);
        $url = route('admin.events.point-sharing.update', $event);
        $payload = $this->payload($teams, $regions);
        $this->putJson($url, $payload)->assertOk();
        $after = $service->forEvent($event->fresh(), publishedOnly: true);
        $own = collect($before['overall'])->firstWhere('name', 'Invitational');
        $this->assertGreaterThan(0, $own['points']);
        foreach (['Recipient A', 'Recipient B'] as $name) {
            $recipient = collect($after['overall'])->firstWhere('name', $name);
            $this->assertEquals($own['points'], $recipient['points']);
            $this->assertEquals($own['match_points'], $recipient['match_points']);
            $this->assertEquals($own['tie_points'], $recipient['tie_points']);
            $this->assertEquals($own['gender_points'], $recipient['gender_points']);
            foreach (['played', 'wins', 'rubber_wins', 'sets_for', 'games_for'] as $metric) $this->assertSame(0, $recipient[$metric]);
            $this->assertCount(2, $recipient['shared_contributions']);
        }
        $this->assertSame($before['ageStandings'], $after['ageStandings']);
        $this->assertSame($before['ageGroups'], $after['ageGroups']);
        $this->assertSame($before['stats'], $after['stats']);
        $this->assertSame($own, collect($after['overall'])->firstWhere('name', 'Invitational'));
        $this->putJson($url, $payload)->assertOk();
        $this->assertSame(1, DB::table('audit_events')->where('action', 'event.standings.point-sharing.changed')->count());
        $this->assertEquals($after['overall'], $service->forEvent($event->fresh(), publishedOnly: true)['overall']);
        $settingsPage = $this->get(route('admin.events.settings', $event))->assertOk()->assertSee('Invitational point sharing')->assertSee('Invitational U13 Boys')->assertSee('Invitational U13 Girls');
        $publicPage = $this->get(route('frontend.events.standings', $event))->assertOk()->assertSee('Overall regional standings')->assertSee('shared points from Invitational U13 Boys');
        // Optional synthetic HTML for responsive QA; all records come from this isolated test.
        if ($fixtureDirectory = getenv('CT_POINT_SHARING_FIXTURE_DIR')) {
            file_put_contents($fixtureDirectory.'/settings.html', $settingsPage->getContent());
            file_put_contents($fixtureDirectory.'/standings.html', $publicPage->getContent());
        }
        $preview = $this->get(route('admin.events.standings.preview', $event))->assertOk()->assertSee('Overall regional standings');
        $this->assertSame($after['overall'], $preview->viewData('overall'));
        $payload['enabled'] = false;
        $this->putJson($url, $payload)->assertOk();
        $this->assertSame($before['overall'], $service->forEvent($event->fresh(), publishedOnly: true)['overall']);
        $this->assertCount(2, $event->fresh()->team_result_settings['point_sharing']['rules']);
        $this->get(route('frontend.events.standings', $event))->assertOk()->assertDontSee('data-shared-overall-standings', false);
        $this->assertDatabaseCount('team_fixture_results', 4);
    }

    public function test_native_integer_and_accumulated_split_points_receive_the_same_rank(): void
    {
        ['event' => $event, 'regions' => $regions, 'teams' => $teams] = $this->setupEvent();
        // Two separately earning regions each have three native points. Each recipient
        // gets 1.5 + 1.5, which must rank equally with those native integer totals.
        $teams[1]->update(['region_id' => $regions[3]->id]);
        $this->putJson(route('admin.events.point-sharing.update', $event), $this->payload($teams, $regions, 'split'))->assertOk();
        $rows = app(EventStandingsService::class)->forEvent($event->fresh(), publishedOnly: true)['overall'];
        $this->assertCount(4, $rows);
        foreach ($rows as $row) {
            $this->assertEquals(3, $row['points']);
            $this->assertSame(1, $row['rank']);
        }
    }

    public function test_split_preserves_precision_publication_filters_and_does_not_cascade(): void
    {
        ['event' => $event, 'regions' => $regions, 'teams' => $teams] = $this->setupEvent();
        $url = route('admin.events.point-sharing.update', $event);
        $payload = $this->payload($teams, $regions, 'split');
        $payload['rules'][0]['region_ids'][] = $regions[3]->id;
        // A recipient's own team can share its earned zero points; it cannot pass on received points.
        $recipientTeam = Team::factory()->create(['category_event_id' => $teams[0]->category_event_id, 'region_id' => $regions[1]->id]);
        $payload['rules'][] = ['team_id' => $recipientTeam->id, 'enabled' => true, 'mode' => 'full', 'region_ids' => [$regions[3]->id]];
        $this->putJson($url, $payload)->assertOk();
        $data = app(EventStandingsService::class)->forEvent($event->fresh(), publishedOnly: true);
        $sourcePoints = $data['sections'][0]['rows'][0]['points'];
        $recipient = collect($data['overall'])->firstWhere('name', 'Recipient A');
        $this->assertEqualsWithDelta($sourcePoints / 3 + $sourcePoints / 2, $recipient['points'], 0.000000001);
        $this->assertEqualsWithDelta($sourcePoints / 3, collect($data['overall'])->firstWhere('name', 'Opponent')['shared_contributions'][0]['points'], 0.000000001);
        $this->assertCount(1, collect($data['overall'])->firstWhere('name', 'Opponent')['shared_contributions']);
        $this->assertSame([], app(EventStandingsService::class)->forEvent($event->fresh(), ['age' => 'U99'], true)['overall']);
        $event->draws()->update(['published' => false]);
        $this->assertSame([], app(EventStandingsService::class)->forEvent($event->fresh(), publishedOnly: true)['overall']);
        $event->forceFill(['standings_published' => false])->save();
        $this->get(route('frontend.events.standings', $event))->assertNotFound();
    }

    public function test_settings_reject_foreign_sources_recipients_self_duplicates_and_unauthorised_actors(): void
    {
        ['event' => $event, 'regions' => $regions, 'teams' => $teams, 'admin' => $admin] = $this->setupEvent();
        $url = route('admin.events.point-sharing.update', $event);
        $valid = $this->payload($teams, $regions);
        $foreignEvent = Event::factory()->create(['eventType' => 3]);
        $foreignCategory = CategoryEvent::factory()->create(['event_id' => $foreignEvent->id]);
        $foreignTeam = Team::factory()->create(['category_event_id' => $foreignCategory->id, 'region_id' => $regions[0]->id]);
        $foreignRegion = TeamRegion::create(['region_name' => 'Foreign', 'short_name' => 'Foreign']);
        foreach ([$foreignRegion->id, $regions[0]->id] as $badRecipient) {
            $payload = $valid; $payload['rules'][0]['region_ids'] = [$badRecipient];
            $this->putJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('rules.0.region_ids'.($badRecipient === $foreignRegion->id ? '.0' : ''));
        }
        $payload = $valid; $payload['rules'][0]['team_id'] = $foreignTeam->id;
        $this->putJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('rules.0.team_id');
        $payload = $valid; $payload['rules'][0]['region_ids'] = [$regions[1]->id, $regions[1]->id];
        $this->putJson($url, $payload)->assertUnprocessable()->assertJsonValidationErrors('rules.0.region_ids');
        $payload = $valid; $payload['rules'][0]['region_ids'] = [];
        $this->putJson($url, $payload)->assertUnprocessable();
        $payload = $valid; $payload['rules'][0]['mode'] = 'arbitrary';
        $this->putJson($url, $payload)->assertUnprocessable();
        $this->putJson(route('admin.events.point-sharing.update', $foreignEvent), $valid)->assertForbidden();
        $this->actingAs(User::factory()->create())->putJson($url, $valid)->assertForbidden();
        $this->assertNull($event->fresh()->team_result_settings);
        $this->assertSame(0, DB::table('audit_events')->where('action', 'event.standings.point-sharing.changed')->count());
        $this->actingAs($admin);
        $event->forceFill(['team_result_settings' => ['formats' => ['singles']]])->save();
        $this->putJson($url, $valid)->assertOk();
        $this->assertSame(['singles'], $event->fresh()->team_result_settings['formats']);
        $event->regions()->detach($regions[2]);
        $rows = app(EventStandingsService::class)->forEvent($event->fresh(), publishedOnly: true)['overall'];
        $this->assertNull(collect($rows)->firstWhere('name', 'Recipient A'));
    }
}
