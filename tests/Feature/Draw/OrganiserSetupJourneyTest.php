<?php

namespace Tests\Feature\Draw;

use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, Event, EventType, Fixture, OrderOfPlay, Player, Registration, User, Venue};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrganiserSetupJourneyTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(bool $trials = false): array
    {
        $type = DB::table('eventtypes')->insertGetId(['name' => $trials ? 'Interpro Trials' : 'Interpro team event',
            'type' => $trials ? EventType::INDIVIDUAL : EventType::TEAM,
            'code' => $trials ? EventType::INTERPROVINCIAL_TRIALS_CODE : null]);
        $event = Event::factory()->create(['eventType' => $type]);
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        DB::table('event_admins')->insert(['event_id' => $event->id, 'user_id' => $admin->id]);
        $this->actingAs($admin);

        return [$event, $admin];
    }

    public function test_new_organiser_can_follow_participants_draw_schedule_and_publication_in_order(): void
    {
        [$event] = $this->scenario();
        $response = $this->get(route('headOffice.show', $event))->assertOk()
            ->assertSee('No draws saved yet.')
            ->assertSee('Team tie for teams playing singles and doubles')
            ->assertSee('saving setup does not make them public')
            ->assertSee(e(route('admin.events.teams', $event)), false)
            ->assertSee(e(route('backend.event-venue-schedule.index', $event)), false)
            ->assertSee(e(route('backend.event-venue-schedule.calendar', ['event' => $event, 'date' => 'all'])), false);
        $this->assertSame(1, preg_match('/<section[^>]*data-organiser-setup[^>]*>([\s\S]*?)<\/section>/', $response->getContent(), $setup));
        $this->assertMatchesRegularExpression('/Set up your tournament[\s\S]*Check participants[\s\S]*Create or continue a draw[\s\S]*Set venues and schedule[\s\S]*Review and publish/', $setup[1]);
        $this->assertDatabaseCount('draws', 0);
        $this->assertDatabaseCount('team_fixtures', 0);
    }

    public function test_saved_draw_has_visible_resume_action_and_does_not_change_on_return(): void
    {
        [$event] = $this->scenario();
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => false, 'oop_published' => false]);
        $foreign = Draw::factory()->create(['drawName' => 'Other event private draw']);
        $before = $draw->refresh()->getAttributes();

        $response = $this->get(route('headOffice.show', $event))->assertOk()
            ->assertSee($draw->drawName)
            ->assertSee(e(route('draw.guide.show', [$event, $draw])), false)
            ->assertDontSee('No draws saved yet.')
            ->assertDontSee($foreign->drawName);
        $html = $response->getContent();
        $resume = strpos($html, 'href="'.route('draw.guide.show', [$event, $draw]).'"');
        $this->assertNotFalse($resume);
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $resumeSelector = 'a[@data-draw-guide-open and @href="'.route('draw.guide.show', [$event, $draw]).'"]';
        $visibleResumeCount = 0;
        foreach ($xpath->query('//'.$resumeSelector) as $resumeNode) {
            $hidden = false;
            foreach ($xpath->query('ancestor::details[not(@open)]', $resumeNode) as $closedDetails) {
                $insideSummary = false;
                foreach ($xpath->query('ancestor::summary/parent::details', $resumeNode) as $summaryDetails) {
                    if ($closedDetails->isSameNode($summaryDetails)) $insideSummary = true;
                }
                if (!$insideSummary) $hidden = true;
            }
            if (!$hidden) $visibleResumeCount++;
        }
        $this->assertGreaterThan(0, $visibleResumeCount,
            'At least one resume action must be outside collapsed details or inside their always-visible summary.');
        $this->assertSame($before, $draw->fresh()->getAttributes());
        $this->assertDatabaseCount('draws', 2);
        $this->assertDatabaseCount('team_fixtures', 0);
    }

    public function test_an_unrelated_administrator_cannot_open_event_setup(): void
    {
        [$event] = $this->scenario();
        $otherAdmin = User::factory()->create()->assignRole('admin');
        $this->actingAs($otherAdmin)->get(route('headOffice.show', $event))->assertForbidden();
    }

    public function test_interpro_trials_use_player_guidance_and_offer_scheduling_before_any_draw_exists(): void
    {
        [$event] = $this->scenario(true);
        $this->get(route('headOffice.show', $event))->assertOk()
            ->assertViewIs('backend.headOffice.individual-event-show')
            ->assertSee('These are individual player trials.')
            ->assertSee('Choose Create draw beside a category below')
            ->assertSee('Plan schedule across all draws')
            ->assertSee(e(route('backend.event-venue-schedule.index', $event).'#venue-management'), false)
            ->assertSee('Review saved order of play')
            ->assertSee('create a draw from a category below')
            ->assertDontSee('Team tie for teams playing singles and doubles');
        $this->assertDatabaseCount('draws', 0);
        $this->assertDatabaseCount('fixtures', 0);
        $this->assertDatabaseCount('team_fixtures', 0);
    }

    public function test_saved_trials_category_draw_can_resume_without_changing_saved_setup(): void
    {
        [$event] = $this->scenario(true);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id,
            'published' => false, 'oop_published' => false, 'locked' => false]);
        $draw->settings()->create(['workflow' => 'round_robin']);
        $before = $draw->refresh()->getAttributes();
        $guideUrl = route('draw.guide.show', [$event, $draw]);

        $this->get(route('headOffice.show', $event))->assertOk()
            ->assertSee('Continue setup')
            ->assertSee(e($guideUrl), false);
        $this->get($guideUrl)->assertOk()->assertSee('Step 2 of 5')
            ->assertSee('Place your players');
        $this->assertSame($before, $draw->fresh()->getAttributes());
        $this->assertDatabaseCount('draws', 1);
        $this->assertDatabaseCount('fixtures', 0);
        $this->assertDatabaseCount('team_fixtures', 0);
    }

    public function test_team_family_keeps_team_workspace_when_legacy_type_id_is_five(): void
    {
        [$event] = $this->scenario();
        DB::table('eventtypes')->updateOrInsert(['id' => 5], ['name' => 'Interpro team competition', 'type' => EventType::TEAM, 'code' => null]);
        $event->update(['eventType' => 5]);
        $this->get(route('headOffice.show', $event))->assertOk()
            ->assertViewIs('backend.headOffice.team-event-show')
            ->assertSee('Set up your tournament')
            ->assertSee('Team tie for teams playing singles and doubles');
    }

    public function test_small_fresh_trials_draw_suggests_one_group_and_preserves_saved_counts(): void
    {
        [$event, $admin] = $this->scenario(true);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id]);
        $draw->settings()->create(['workflow' => 'round_robin']);
        foreach (range(1, 4) as $number) {
            $registration = Registration::factory()->create();
            $registration->players()->attach(Player::factory()->create());
            CategoryEventRegistration::factory()->create(['category_event_id' => $category->id,
                'registration_id' => $registration->id, 'user_id' => $admin->id, 'payment_status_id' => 1, 'status' => 'active']);
        }
        $url = route('backend.draw.roundrobin.show', $draw);
        $this->get($url)->assertOk()->assertSee('value="1" selected', false);
        $this->assertDatabaseCount('draw_groups', 0);
        $draw->settings()->update(['boxes' => 3]);
        $this->get($url)->assertOk()->assertSee('value="3" selected', false);
        $draw->settings()->update(['boxes' => null]);
        $draw->groups()->createMany([['name' => 'A'], ['name' => 'B']]);
        $this->get($url)->assertOk()->assertSee('value="2" selected', false);
        $this->assertDatabaseCount('fixtures', 0);
    }

    public function test_generated_trials_guide_exposes_schedule_and_publication_without_expanding_details(): void
    {
        [$event] = $this->scenario(true);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => CategoryEvent::factory()->create(['event_id' => $event->id])->id]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'winner_registration' => null]);
        $url = route('draw.guide.show', [$event, $draw]);
        $this->get($url)->assertOk()->assertSee('Step 4 of 5 · Schedule')
            ->assertSee('Schedule matches')->assertDontSee('Later: scheduling and publication')
            ->assertSee(e(route('backend.draw.roundrobin.show', $draw).'?review=publication#matrix'), false);
        $this->get(route('backend.draw.roundrobin.show', [$draw, 'review' => 'publication']))->assertOk()
            ->assertSee('data-individual-draw-tools  open', false)
            ->assertSee('id="rr-publish-draw"', false)->assertSee('id="rr-publish-schedule"', false)
            ->assertSee('Draw and schedule publish separately.');
        $venue = Venue::forceCreate(['name' => 'Synthetic Trials courts']);
        OrderOfPlay::create(['fixture_id' => $fixture->id, 'draw_id' => $draw->id, 'time' => '2026-10-30 09:00:00', 'court' => '1', 'venue_id' => $venue->id]);
        $this->get($url)->assertOk()->assertSee('Review saved schedule');
        $this->assertFalse((bool) $draw->fresh()->published);
        $this->assertFalse((bool) $draw->fresh()->oop_published);
    }

    public function test_trials_scheduling_uses_match_terms_and_keeps_team_controls_collapsed(): void
    {
        [$event] = $this->scenario(true);
        $draw = Draw::factory()->create(['event_id' => $event->id]);
        $this->get(route('backend.event-venue-schedule.index', ['event' => $event, 'draw_ids' => [$draw->id]]))->assertOk()
            ->assertSee('Matches / draw')->assertSee('Full day across venues')->assertSee('Schedule setup')
            ->assertDontSee('Step 5 of 6')->assertSee('data-team-scheduling-options >', false)
            ->assertSee('id="round-progression"', false)->assertSee('id="tie-allocation"', false);
        $this->get(route('backend.event-venue-schedule.calendar', ['event' => $event, 'draw_id' => $draw->id, 'date' => 'all']))->assertOk()
            ->assertSee('Replan selected rounds - full day')->assertDontSee('Replan selected rounds - whole ties');
    }
}
