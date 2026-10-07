<?php

namespace Tests\Feature;

use App\Models\CategoryEventRegistration;
use App\Models\Draw;
use App\Models\Event;
use App\Models\TeamFixture;
use App\Models\TeamPaymentOrder;
use App\Models\User;
use App\Models\Venue;
use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\Team;
use App\Models\TeamPlayer;
use App\Models\TeamRegion;
use App\Models\Player;
use App\Services\Performance\PendingBankRefundCount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventPagePerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_timing_projection_matches_full_visibility_order_and_keeps_participant_cache_separate(): void
    {
        $event = Event::factory()->create(['eventType' => 3]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true, 'oop_published' => true]);
        \App\Models\DrawSetting::create(['draw_id' => $draw->id, 'schedule_visibility' => 'first_match']);
        $venue = Venue::forceCreate(['name' => 'Snapshot timing courts']);
        $alice = Player::factory()->create(['name' => 'Alice', 'surname' => 'Timing']);
        $bob = Player::factory()->create(['name' => 'Bob', 'surname' => 'Timing']);
        $firstRegistration = \App\Models\Registration::factory()->create();
        $secondRegistration = \App\Models\Registration::factory()->create();
        $firstRegistration->players()->attach($alice);
        $secondRegistration->players()->attach($bob);
        $first = \App\Models\Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => $firstRegistration->id, 'registration2_id' => $secondRegistration->id]);
        $later = \App\Models\Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => $firstRegistration->id, 'registration2_id' => $secondRegistration->id]);
        $this->insertSnapshot($event, $draw->id, $first->id, $venue->id, 'individual', '2026-10-10 08:00:00');
        $this->insertSnapshot($event, $draw->id, $later->id, $venue->id, 'individual', '2026-10-10 09:00:00');
        $teamFixture = TeamFixture::create(['draw_id' => $draw->id, 'match_nr' => 1, 'fixture_type' => 1]);
        \App\Models\TeamFixturePlayer::create(['team_fixture_id' => $teamFixture->id, 'team1_id' => $alice->id, 'team2_id' => $bob->id]);
        $this->insertSnapshot($event, $draw->id, $teamFixture->id, $venue->id, 'team', '2026-10-10 08:30:00');
        $foreignDraw = Draw::factory()->create(['published' => true, 'oop_published' => true]);
        $foreign = TeamFixture::create(['draw_id' => $foreignDraw->id, 'match_nr' => 1]);
        $this->insertSnapshot($event, $foreignDraw->id, $foreign->id, $venue->id, 'team', '2026-10-10 07:00:00');
        $this->insertSnapshot($event, $draw->id, 999999, $venue->id, 'team', '2026-10-10 06:00:00');
        $service = app(\App\Services\Scheduling\SchedulePublicationService::class);
        $timing = $service->publishedRows($event, includeParticipants: false);
        $this->assertSame(['individual:'.$first->id, 'team:'.$teamFixture->id], $timing->pluck('fixture_key')->all());
        $this->assertArrayNotHasKey('participants', $timing->first());
        $full = $service->publishedRows($event);
        $this->assertSame($timing->all(), $full->map(fn ($row) => \Illuminate\Support\Arr::except($row, ['participants']))->all());
        $this->assertSame(['Alice Timing', 'Bob Timing'], $full->first()['participants']);
        $this->assertSame(['Alice Timing', 'Bob Timing'], $full->last()['participants']);
        $this->assertSame($timing->all(), $service->publishedRows($event, includeParticipants: false)->all());

        $service->hide($event, ['draw_id' => $draw->id]);
        $this->assertCount(0, $service->publishedRows($event));
        $this->assertCount(0, $service->publishedRows($event, includeParticipants: false));
    }

    public function test_imported_roster_profiles_and_payer_controls_remain_available(): void
    {
        $event = Event::factory()->create(['eventType' => 3]);
        $region = TeamRegion::create(['region_name' => 'Imported region', 'clothing_order' => false]);
        $event->regions()->attach($region->id);
        $team = Team::factory()->create(['region_id' => $region->id, 'noProfile' => true]);
        $player = Player::factory()->create(['name' => 'Linked', 'surname' => 'Roster']);
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1, 'pay_status' => 1]);
        \App\Models\NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => 'Old', 'surname' => 'Import', 'rank' => 1, 'player_profile' => $player->id, 'pay_status' => 1]);
        $unlinked = \App\Models\NoProfileTeamPlayer::create(['team_id' => $team->id, 'name' => 'Unlinked', 'surname' => 'Roster', 'rank' => 2, 'pay_status' => 0]);
        $payer = User::factory()->create();
        TeamPaymentOrder::create(['user_id' => $payer->id, 'event_id' => $event->id, 'team_id' => $team->id, 'player_id' => $player->id, 'pay_status' => true]);
        $withdrawUrl = route('team.player.withdraw', [$team->id, $player->id, $event->id]);
        $linkUrl = route('player.create', ['type' => 'noProfile', 'noProfile' => $unlinked->id, 'team' => $team->id, 'event' => $event->id]);

        $response = $this->actingAs($payer)->get('/events/'.$event->id)->assertOk()->assertSee('Linked Roster')->assertSee('Unlinked Roster')->assertSee($linkUrl)->assertSee($withdrawUrl, false);
        $this->assertSame($team->id, $response->viewData('regions')->first()->teams->first()->team_players_no_profile->first()->team->id);
        $this->actingAs(User::factory()->create())->get('/events/'.$event->id)->assertOk()->assertSee($linkUrl)->assertDontSee($withdrawUrl, false);
    }

    public function test_type_13_individual_event_keeps_public_snapshot_venue_time_and_order_and_hides_drafts(): void
    {
        $event = Event::factory()->create(['eventType' => 13]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true, 'oop_published' => true]);
        $draft = Draw::factory()->create(['event_id' => $event->id, 'published' => false, 'oop_published' => true]);
        $publicVenue = Venue::forceCreate(['name' => 'Public individual venue']);
        $privateVenue = Venue::forceCreate(['name' => 'Private working individual venue']);
        $first = \App\Models\Fixture::factory()->create(['draw_id' => $draw->id, 'match_nr' => 2]);
        $second = \App\Models\Fixture::factory()->create(['draw_id' => $draw->id, 'match_nr' => 1]);
        $draftFixture = \App\Models\Fixture::factory()->create(['draw_id' => $draft->id]);
        foreach ([$first, $second, $draftFixture] as $fixture) {
            \App\Models\OrderOfPlay::create(['fixture_id' => $fixture->id, 'draw_id' => $fixture->draw_id, 'venue_id' => $privateVenue->id, 'time' => '2026-10-11 12:00:00', 'court' => '9']);
        }
        foreach ([[$first, '08:00:00'], [$second, '09:00:00'], [$draftFixture, '07:00:00']] as [$fixture, $time]) {
            $this->insertSnapshot($event, $fixture->draw_id, $fixture->id, $publicVenue->id, 'individual', '2026-10-10 '.$time);
        }
        $response = $this->get('/events/'.$event->id)->assertOk();
        $groups = $response->viewData('fixturesPerVenueGrouped');
        $this->assertSame(['Public individual venue'], $groups->keys()->all());
        $this->assertSame([$first->id, $second->id], $groups->first()->pluck('id')->all());
        $this->assertSame('2026-10-10 08:00:00', $groups->first()->first()->orderOfPlay->time);
        $this->assertSame([$draw->id], $response->viewData('eventDraws')->pluck('id')->all());
        $this->assertSame('2026-10-10 08:00:00', $response->viewData('eventDraws')->first()->order_of_play->first()->time);
        $this->assertTrue($response->viewData('eventDraws')->first()->scheduleIsPublished());
        $response->assertDontSee('Private working individual venue');
    }

    public function test_twenty_published_draws_do_not_add_per_draw_schedule_queries(): void
    {
        $event = Event::factory()->create(['eventType' => 3]);
        $venue = Venue::forceCreate(['name' => 'Public large schedule venue']);
        foreach (range(1, 20) as $index) {
            $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true, 'oop_published' => true]);
            $fixture = TeamFixture::create(['draw_id' => $draw->id, 'match_nr' => 1]);
            $this->insertSnapshot($event, $draw->id, $fixture->id, $venue->id, 'team', '2026-10-10 08:00:00');
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->get('/events/'.$event->id)->assertOk();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertSame(20, $response->viewData('drawPublicationSummary')['schedule_published']);
        $this->assertCount(20, $response->viewData('eventDraws'));
        $this->assertLessThan(100, $queryCount);
    }

    private function insertSnapshot(Event $event, int $drawId, int $fixtureId, int $venueId, string $kind, string $time): void
    {
        DB::table('published_schedule_assignments')->insert([
            'event_id' => $event->id, 'draw_id' => $drawId, 'fixture_kind' => $kind,
            'fixture_id' => $fixtureId, 'venue_id' => $venueId, 'scheduled_at' => $time,
            'court' => '1', 'duration' => 120, 'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_draw_view_permissions_are_event_scoped_and_scorers_cannot_preview_drafts(): void
    {
        $admin = User::factory()->create();
        $scorer = User::factory()->create();
        $event = Event::factory()->create(['eventType' => 3]);
        \App\Models\EventAdmin::create(['event_id' => $event->id, 'user_id' => $admin->id]);
        \App\Models\EventConvenor::create(['event_id' => $event->id, 'user_id' => $scorer->id, 'role' => 'score-keeper']);
        $draws = Draw::factory()->count(2)->create(['event_id' => $event->id, 'published' => false]);
        $foreignDraw = Draw::factory()->create(['published' => false]);
        $permissions = app(\App\Services\EventPageDataService::class)->drawViewPermissions($admin, $draws->concat([$foreignDraw]));
        foreach ($draws as $draw) $this->assertTrue($permissions->get($draw->id));
        $this->assertFalse($permissions->get($foreignDraw->id));
        $this->actingAs($scorer)->get('/events/'.$event->id)->assertOk()->assertViewHas('canPreviewUnpublishedDraws', false)->assertViewHas('eventDraws', fn ($draws) => $draws->isEmpty());
        $response = $this->actingAs($admin)->get('/events/'.$event->id)->assertOk()->assertViewHas('canPreviewUnpublishedDraws', true);
        $this->assertEqualsCanonicalizing($draws->pluck('id')->all(), $response->viewData('eventDraws')->pluck('id')->all());
    }

    public function test_rosters_keep_current_and_legacy_teams_but_exclude_foreign_event_and_private_names(): void
    {
        $event = Event::factory()->create(['eventType' => 3]);
        $region = TeamRegion::create(['region_name' => 'Event region', 'clothing_order' => false]);
        $event->regions()->attach($region->id);
        $currentCategory = CategoryEvent::create(['event_id' => $event->id, 'category_id' => Category::factory()->create()->id]);
        $foreignCategory = CategoryEvent::create(['event_id' => Event::factory()->create()->id, 'category_id' => Category::factory()->create()->id]);
        $current = Team::factory()->create(['name' => 'Current roster', 'region_id' => $region->id, 'category_event_id' => $currentCategory->id]);
        $legacy = Team::factory()->create(['name' => 'Supported legacy roster', 'region_id' => $region->id]);
        $foreign = Team::factory()->create(['name' => 'Foreign roster', 'region_id' => $region->id, 'category_event_id' => $foreignCategory->id]);
        $private = Team::factory()->create(['name' => 'Unpublished roster', 'region_id' => $region->id, 'category_event_id' => $currentCategory->id, 'published' => false]);
        foreach ([$current, $legacy, $foreign, $private] as $team) {
            $player = Player::factory()->create(['name' => 'Player'.$team->id, 'surname' => 'Roster']);
            TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 1]);
        }

        $response = $this->get('/events/'.$event->id)->assertOk();
        $response->assertSee('Current roster')->assertSee('Supported legacy roster')->assertDontSee('Foreign roster');
        $response->assertSee('Player'.$current->id)->assertSee('Player'.$legacy->id)->assertDontSee('Player'.$private->id);
        $this->assertEqualsCanonicalizing([$current->id, $legacy->id, $private->id], $response->viewData('regions')->first()->teams->pluck('id')->all());
    }

    public function test_registration_remains_sponsored_but_withdrawal_requires_the_current_event_payer(): void
    {
        $event = Event::factory()->create(['eventType' => 3]);
        $region = TeamRegion::create(['region_name' => 'Payer region', 'clothing_order' => false]);
        $event->regions()->attach($region->id);
        $team = Team::factory()->create(['region_id' => $region->id]);
        $paidPlayer = Player::factory()->create();
        $unpaidPlayer = Player::factory()->create();
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $paidPlayer->id, 'rank' => 1, 'pay_status' => 1]);
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $unpaidPlayer->id, 'rank' => 2, 'pay_status' => 0]);
        $payer = User::factory()->create();
        $other = User::factory()->create();
        TeamPaymentOrder::create(['user_id' => $payer->id, 'event_id' => $event->id, 'team_id' => $team->id, 'player_id' => $paidPlayer->id, 'pay_status' => true]);
        TeamPaymentOrder::create(['user_id' => $other->id, 'event_id' => Event::factory()->create()->id, 'team_id' => $team->id, 'player_id' => $paidPlayer->id, 'pay_status' => true]);
        $registerUrl = route('team.payment.payfast', [$team->id, $unpaidPlayer->id, $event->id]);
        $withdrawUrl = route('team.player.withdraw', [$team->id, $paidPlayer->id, $event->id]);

        $this->actingAs($other)->get('/events/'.$event->id)->assertOk()->assertSee($registerUrl, false)->assertDontSee($withdrawUrl, false);
        $this->actingAs($payer)->get('/events/'.$event->id)->assertOk()->assertSee($registerUrl, false)->assertSee($withdrawUrl, false);
    }

    public function test_initial_page_query_count_does_not_grow_with_roster_and_unscheduled_fixture_count(): void
    {
        $event = Event::factory()->create(['eventType' => 3]);
        $region = TeamRegion::create(['region_name' => 'Large roster region', 'clothing_order' => false]);
        $event->regions()->attach($region->id);
        $team = Team::factory()->create(['region_id' => $region->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true, 'oop_published' => false]);
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => Player::factory()->create()->id, 'rank' => 1]);
        TeamFixture::create(['draw_id' => $draw->id, 'match_nr' => 1]);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->get('/events/'.$event->id)->assertOk();
        $smallCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        foreach (range(2, 60) as $rank) {
            TeamPlayer::create(['team_id' => $team->id, 'player_id' => Player::factory()->create()->id, 'rank' => $rank]);
            TeamFixture::create(['draw_id' => $draw->id, 'match_nr' => $rank]);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->get('/events/'.$event->id)->assertOk();
        $largeCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertCount(60, $response->viewData('regions')->first()->teams->first()->teamPlayers);
        $this->assertLessThanOrEqual($smallCount + 3, $largeCount, 'Roster and fixture growth must not cause per-row queries.');
        $this->assertLessThan(100, $largeCount);
    }

    public function test_refund_badge_counts_once_across_views_and_refreshes_for_a_new_scope(): void
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        CategoryEventRegistration::factory()->withdrawn()->create([
            'refund_method' => 'bank', 'refund_status' => 'pending',
        ]);
        CategoryEventRegistration::factory()->withdrawn()->create([
            'refund_method' => 'wallet', 'refund_status' => 'pending',
        ]);
        CategoryEventRegistration::factory()->create([
            'status' => 'active', 'refund_method' => 'bank', 'refund_status' => 'pending',
        ]);
        CategoryEventRegistration::factory()->withdrawn()->create([
            'refund_method' => 'bank', 'refund_status' => 'refunded',
        ]);
        TeamPaymentOrder::create(['refund_method' => 'bank', 'refund_status' => 'pending']);
        TeamPaymentOrder::create(['refund_method' => 'wallet', 'refund_status' => 'pending']);
        TeamPaymentOrder::create(['refund_method' => 'bank', 'refund_status' => 'refunded']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        foreach (range(1, 10) as $index) {
            $view = View::make('frontend.event.partials.event-about');
            View::callComposer($view);
            $this->assertSame(2, $view->getData()['pendingBankRefundCount']);
        }
        $queries = collect(DB::getQueryLog())->filter(fn ($query) =>
            str_contains($query['query'], 'count(*)') && str_contains($query['query'], 'refund_status'));
        $this->assertCount(2, $queries);
        DB::disableQueryLog();

        CategoryEventRegistration::factory()->withdrawn()->create([
            'refund_method' => 'bank', 'refund_status' => 'pending',
        ]);
        $this->app->forgetScopedInstances();
        $this->assertSame(3, app(PendingBankRefundCount::class)->count());
    }

    public function test_refund_badge_does_not_query_or_share_counts_with_ordinary_users(): void
    {
        $this->actingAs(User::factory()->create());
        DB::enableQueryLog();
        DB::flushQueryLog();
        $view = View::make('frontend.event.partials.event-about');
        View::callComposer($view);
        $this->assertArrayNotHasKey('pendingBankRefundCount', $view->getData());
        $this->assertEmpty(collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'refund_status')));
        DB::disableQueryLog();
    }

    public function test_team_venue_grouping_does_not_query_individual_schedule_or_reveal_private_venue(): void
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $event = Event::factory()->create(['eventType' => 3]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true, 'oop_published' => false]);
        $venue = Venue::forceCreate(['name' => 'Private working venue']);
        foreach (range(1, 5) as $index) {
            TeamFixture::create(['draw_id' => $draw->id, 'match_nr' => $index,
                'venue_id' => $venue->id, 'scheduled_at' => '2026-10-10 08:00:00']);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->get('/events/'.$event->id)->assertOk();
        $groups = $response->viewData('fixturesPerVenueGrouped');
        $this->assertSame(['Unassigned'], $groups->keys()->all());
        $this->assertCount(5, $groups->get('Unassigned'));
        $lazySchedules = collect(DB::getQueryLog())->filter(fn ($query) =>
            str_contains($query['query'], '`order_of_plays`.`fixture_id` = ?'));
        $this->assertCount(0, $lazySchedules);
        DB::disableQueryLog();
    }

    public function test_team_venue_grouping_uses_published_snapshot_and_excludes_foreign_event(): void
    {
        $event = Event::factory()->create(['eventType' => 3]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true, 'oop_published' => true]);
        $publicVenue = Venue::forceCreate(['name' => 'Public snapshot venue']);
        $privateVenue = Venue::forceCreate(['name' => 'Private revised venue']);
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'match_nr' => 1,
            'venue_id' => $privateVenue->id, 'scheduled_at' => '2026-10-11 10:00:00']);
        DB::table('published_schedule_assignments')->insert([
            'event_id' => $event->id, 'draw_id' => $draw->id, 'fixture_kind' => 'team',
            'fixture_id' => $fixture->id, 'venue_id' => $publicVenue->id,
            'scheduled_at' => '2026-10-10 08:00:00', 'court' => '1', 'duration' => 120,
            'published_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        $foreignDraw = Draw::factory()->create(['published' => true]);
        TeamFixture::create(['draw_id' => $foreignDraw->id, 'match_nr' => 1, 'venue_id' => $privateVenue->id]);

        $response = $this->get('/events/'.$event->id)->assertOk();
        $groups = $response->viewData('fixturesPerVenueGrouped');
        $this->assertSame(['Public snapshot venue'], $groups->keys()->all());
        $this->assertSame([$fixture->id], $groups->first()->pluck('id')->all());
        $this->assertSame('2026-10-10 08:00:00', $groups->first()->first()->scheduled_at->format('Y-m-d H:i:s'));
    }
}
