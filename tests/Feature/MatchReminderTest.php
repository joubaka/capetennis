<?php

namespace Tests\Feature;

use App\Models\{CategoryEvent, CategoryEventRegistration, Draw, DrawSetting, Event, Fixture, FixtureResult, OrderOfPlay, Player, Registration, User};
use App\Services\MatchReminderService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MatchReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-07 23:30:00', 'Africa/Johannesburg'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function matchFor(Player $player, string $time, array $options = []): Fixture
    {
        $event = $options['event'] ?? Event::factory()->create(['end_date' => '2026-10-10']);
        $category = $options['category'] ?? CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = $options['draw'] ?? Draw::factory()->create(['event_id' => $event->id,
            'category_event_id' => $category->id, 'published' => true, 'oop_published' => true]);
        $registration = $options['registration'] ?? Registration::factory()->create();
        $registration->players()->syncWithoutDetaching($player->id);
        CategoryEventRegistration::factory()->create(['registration_id' => $registration->id,
            'category_event_id' => $category->id, 'status' => $options['status'] ?? 'active',
            'withdrawn_at' => $options['withdrawn_at'] ?? null]);
        $opponent = Registration::factory()->create();
        $opponent->players()->attach(Player::factory()->create(['name' => 'Opponent', 'surname' => 'Player']));
        CategoryEventRegistration::factory()->create(['registration_id' => $opponent->id, 'category_event_id' => $category->id]);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => $registration->id,
            'registration2_id' => $opponent->id, 'match_status' => $options['match_status'] ?? 0]);
        $venueId = DB::table('venues')->insertGetId(['name' => 'Published Club']);
        DB::table('published_schedule_assignments')->insert(['event_id' => $event->id, 'draw_id' => $draw->id,
            'fixture_kind' => 'individual', 'fixture_id' => $fixture->id, 'scheduled_at' => $time,
            'venue_id' => $venueId, 'court' => '4', 'duration' => 75, 'published_at' => now()]);
        return $fixture;
    }

    public function test_endpoint_requires_authentication_and_has_no_account_selector(): void
    {
        $this->getJson(route('my.tennis.match-reminder'))->assertUnauthorized();
        $other = User::factory()->create();
        $player = Player::factory()->create(['userId' => $other->id]);
        $this->matchFor($player, '2026-10-08 08:00:00');
        $this->actingAs(User::factory()->create())->getJson(route('my.tennis.match-reminder', ['user_id' => $other->id, 'player_id' => $player->id]))
            ->assertOk()->assertJsonPath('players', [])->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    public function test_all_linked_players_in_the_seven_day_window_use_published_snapshot(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['name' => 'First', 'surname' => 'Player', 'userId' => $user->id]);
        $user->players()->attach($player);
        $second = Player::factory()->create(['name' => 'Second', 'surname' => 'Player']);
        $user->players()->attach($second);
        $today = $this->matchFor($player, '2026-10-07 08:00:00');
        $this->matchFor($player, '2026-10-08 23:59:59');
        $this->matchFor($second, '2026-10-08 10:00:00');
        $this->matchFor($player, '2026-10-06 23:59:59');
        $this->matchFor($player, '2026-10-14 00:00:00');
        $this->matchFor(Player::factory()->create(), '2026-10-08 08:00:00');
        OrderOfPlay::create(['fixture_id' => $today->id, 'draw_id' => $today->draw_id,
            'time' => '2026-10-09 15:00:00', 'venue_id' => DB::table('venues')->insertGetId(['name' => 'Private changed venue']), 'court' => '99']);

        $data = app(MatchReminderService::class)->for($user);
        $this->assertSame('2026-10-07', $data['day']);
        $this->assertCount(2, $data['players']);
        $matches = collect($data['players'])->flatMap(fn ($p) => $p['matches']);
        $this->assertCount(3, $matches);
        $this->assertSame(['Today', 'Tomorrow', 'Tomorrow'], $matches->pluck('day')->all());
        $this->assertSame('Published Club', $matches[0]['venue']);
        $this->assertSame('4', $matches[0]['court']);
        $this->assertSame('08:00', $matches[0]['time']);
        $this->assertSame(route('frontend.showDraw', $today->draw_id), $matches[0]['url']);
        $this->assertStringContainsString('Opponent Player', implode(' ', $matches[0]['participants']));
    }

    public function test_hidden_completed_started_and_withdrawn_matches_are_excluded(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id]);
        $this->matchFor($player, '2026-10-08 08:00:00', ['event' => Event::factory()->unpublished()->create()]);
        $hiddenDraw = $this->matchFor($player, '2026-10-08 08:00:00');
        $hiddenDraw->draw->update(['published' => false]);
        $hiddenSchedule = $this->matchFor($player, '2026-10-08 08:00:00');
        $hiddenSchedule->draw->update(['oop_published' => false]);
        $completed = $this->matchFor($player, '2026-10-08 08:00:00');
        FixtureResult::factory()->create(['fixture_id' => $completed->id]);
        $this->matchFor($player, '2026-10-08 08:00:00', ['match_status' => 1]);
        $this->matchFor($player, '2026-10-08 08:00:00', ['status' => 'withdrawn']);
        $this->matchFor($player, '2026-10-08 08:00:00', ['withdrawn_at' => now()]);
        $withdrawnOpponent = $this->matchFor($player, '2026-10-08 08:00:00');
        $withdrawnOpponent->registration2->categoryEventRegistrations()->update(['status' => 'withdrawn']);
        $unassignedOpponent = $this->matchFor($player, '2026-10-08 08:00:00');
        $unassignedOpponent->update(['registration2_id' => null]);
        $this->assertSame([], app(MatchReminderService::class)->for($user)['players']);
        $this->assertTrue(app(\App\Services\MyTennisService::class)->upcomingScheduledMatchesFor($player)->isEmpty());
    }

    public function test_friday_matches_are_reminded_on_wednesday_and_seven_day_boundary_is_excluded(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id]);
        $friday = $this->matchFor($player, '2026-10-09 09:15:00');
        $this->matchFor($player, '2026-10-13 23:59:59');
        $this->matchFor($player, '2026-10-14 00:00:00');
        $matches = app(MatchReminderService::class)->for($user)['players'][0]['matches'];
        $this->assertCount(2, $matches);
        $this->assertSame('individual:'.$friday->id, $matches[0]['key']);
        $this->assertSame('Friday', $matches[0]['day']);
        $this->assertSame('09:15', $matches[0]['time']);
    }

    public function test_profile_lists_all_upcoming_matches_in_order_with_pagination_and_preserves_player_scope(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id]);
        $foreignPlayer = Player::factory()->create();
        $this->matchFor($foreignPlayer, '2026-10-08 06:00:00');
        $ids = [];
        for ($index = 0; $index < 27; $index++) {
            $ids[] = $this->matchFor($player, CarbonImmutable::parse('2026-10-08 08:00:00')->addMinutes($index)->toDateTimeString())->id;
        }
        $service = app(\App\Services\MyTennisService::class);
        $this->assertSame($ids, $service->upcomingScheduledMatchesFor($player)->pluck('id')->all());
        $this->assertSame($ids[0], $service->nextScheduledMatchFor($player)->sole()->id);
        $this->actingAs($user)->get(route('my.tennis', ['player' => $player->id]))
            ->assertOk()->assertSee('Upcoming scheduled matches')
            ->assertViewHas('upcomingMatches', fn ($matches) => $matches->pluck('id')->all() === array_slice($ids, 0, 25))
            ->assertViewHas('upcomingMatchPage', fn ($page) => $page->total() === 27 && str_contains($page->nextPageUrl(), 'player='.$player->id));
        $this->get(route('my.tennis', ['player' => $player->id, 'matches_page' => 2]))
            ->assertOk()->assertViewHas('upcomingMatches', fn ($matches) => $matches->pluck('id')->all() === array_slice($ids, 25));
        $this->get(route('my.tennis', ['player' => $foreignPlayer->id]))
            ->assertOk()->assertViewHas('selectedPlayer', null)
            ->assertViewHas('upcomingMatches', fn ($matches) => $matches->isEmpty());
    }

    public function test_real_login_exposes_linked_player_reminder_and_resets_its_scope_on_next_login(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id]);
        $this->matchFor($player, '2026-10-09 09:15:00');
        $credentials = ['email' => $user->email, 'password' => 'password'];

        $this->post('/login', $credentials)->assertRedirect();
        $firstLogin = session('match_reminder_login');
        $this->assertTrue(\Illuminate\Support\Str::isUuid($firstLogin));
        $this->get(route('my.tennis'))->assertOk()
            ->assertSee('id="match-reminder"', false)
            ->assertSee('data-login="'.$firstLogin.'"', false)
            ->assertSee('data-auto-open="1"', false);
        $this->assertFalse(session()->has('match_reminder_pending'));
        $this->get(route('my.tennis'))->assertOk()->assertSee('data-auto-open="0"', false);
        $this->getJson(route('my.tennis.match-reminder'))->assertOk()
            ->assertJsonCount(1, 'players')->assertJsonCount(1, 'players.0.matches');

        $this->post('/logout')->assertRedirect();
        $this->post('/login', $credentials)->assertRedirect();
        $this->assertNotSame($firstLogin, session('match_reminder_login'));
        $this->get(route('my.tennis'))->assertOk()->assertSee('data-auto-open="1"', false);
    }

    public function test_existing_authenticated_session_has_no_automatic_reminder_offer(): void
    {
        $this->actingAs(User::factory()->create())->get(route('my.tennis'))->assertOk()
            ->assertSee('data-auto-open="0"', false);
    }

    public function test_successful_login_rotates_only_the_reminder_dismissal_scope(): void
    {
        $user = User::factory()->create();
        $this->withSession(['match_reminder_login' => 'old-login']);
        request()->setLaravelSession(app('session.store'));
        app(\App\Listeners\LogSuccessfulLogin::class)->handle(new \Illuminate\Auth\Events\Login('web', $user, false));
        $first = session('match_reminder_login');
        $this->assertTrue(\Illuminate\Support\Str::isUuid($first));
        $this->assertTrue(session('match_reminder_pending'));
        app(\App\Listeners\LogSuccessfulLogin::class)->handle(new \Illuminate\Auth\Events\Login('web', $user, false));
        $this->assertNotSame($first, session('match_reminder_login'));
    }

    public function test_first_match_only_and_category_isolation_are_preserved(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id]);
        $event = Event::factory()->create();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id,
            'published' => true, 'oop_published' => true]);
        $draw->settings()->create(['schedule_visibility' => DrawSetting::SCHEDULE_VISIBILITY_FIRST_MATCH]);
        $registration = Registration::factory()->create();
        $options = compact('event', 'category', 'draw', 'registration');
        $first = $this->matchFor($player, '2026-10-07 08:00:00', $options);
        $this->matchFor($player, '2026-10-08 08:00:00', $options);
        $mismatch = $this->matchFor($player, '2026-10-08 10:00:00');
        $mismatch->draw->update(['category_event_id' => $category->id]);
        $data = app(MatchReminderService::class)->for($user);
        $this->assertCount(1, $data['players'][0]['matches']);
        $this->assertSame('individual:'.$first->id, $data['players'][0]['matches'][0]['key']);
    }

    public function test_team_roster_reminder_is_deduplicated_and_is_not_an_assignment_claim(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id]);
        $event = Event::factory()->create();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id,
            'published' => true, 'oop_published' => true]);
        $home = \App\Models\Team::factory()->create(['category_event_id' => $category->id]);
        $away = \App\Models\Team::factory()->create(['category_event_id' => $category->id]);
        \App\Models\TeamPlayer::create(['team_id' => $home->id, 'player_id' => $player->id, 'rank' => 1]);
        $tie = \App\Models\TeamTie::factory()->create(['draw_id' => $draw->id,
            'home_team_id' => $home->id, 'away_team_id' => $away->id]);
        foreach (['2026-10-08 11:00:00', '2026-10-08 09:00:00'] as $index => $time) {
            $fixture = \App\Models\TeamFixture::create(['draw_id' => $draw->id, 'team_tie_id' => $tie->id,
                'fixture_type' => 1, 'match_nr' => $index + 1, 'match_status' => 0]);
            DB::table('published_schedule_assignments')->insert(['event_id' => $event->id, 'draw_id' => $draw->id,
                'fixture_kind' => 'team', 'fixture_id' => $fixture->id, 'scheduled_at' => $time,
                'venue_id' => DB::table('venues')->insertGetId(['name' => 'Team Club']), 'court' => '1', 'duration' => 120,
                'published_at' => now()]);
        }
        $data = app(MatchReminderService::class)->for($user);
        $this->assertCount(1, $data['players'][0]['matches']);
        $match = $data['players'][0]['matches'][0];
        $this->assertSame('Your team plays', $match['label']);
        $this->assertSame('09:00', $match['time']);
        $this->assertSame(route('frontend.fixtures.show', $draw->id), $match['url']);
        $profileMatches = app(\App\Services\MyTennisService::class)->upcomingScheduledMatchesFor($player);
        $this->assertCount(1, $profileMatches);
        $this->assertSame('2026-10-08 09:00:00', $profileMatches->sole()->scheduled_at->format('Y-m-d H:i:s'));
        // Assignments outside the visible reminder window still make this an assigned tie.
        $assignedSibling = \App\Models\TeamFixture::create(['draw_id' => $draw->id, 'team_tie_id' => $tie->id,
            'fixture_type' => 1, 'match_nr' => 3, 'match_status' => 1, 'scheduled_at' => '2026-10-15 09:00:00']);
        $assignment = \App\Models\TeamFixturePlayer::forceCreate(['team_fixture_id' => $assignedSibling->id,
            'team1_id' => $player->id, 'slot_no' => 1]);
        $this->assertSame([], app(MatchReminderService::class)->for($user)['players']);
        $assignment->delete();
        $assignedSibling->delete();
        $tie->update(['status' => \App\Models\TeamTie::STATUS_COMPLETED]);
        request()->attributes->remove('published_schedule_rows_'.$event->id);
        $this->assertSame([], app(MatchReminderService::class)->for($user)['players']);
        $tie->update(['status' => \App\Models\TeamTie::STATUS_DRAFT]);
        \App\Models\TeamPlayer::where('player_id', $player->id)->delete();
        request()->attributes->remove('published_schedule_rows_'.$event->id);
        $this->assertSame([], app(MatchReminderService::class)->for($user)['players']);
    }

    public function test_team_reminders_use_each_linked_players_assigned_rubbers_and_published_venue(): void
    {
        $user = User::factory()->create();
        $first = Player::factory()->create(['userId' => $user->id]);
        $second = Player::factory()->create();
        $reserve = Player::factory()->create();
        $user->players()->attach([$second->id, $reserve->id]);
        $event = Event::factory()->create();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id,
            'published' => true, 'oop_published' => true]);
        $home = \App\Models\Team::factory()->create(['category_event_id' => $category->id]);
        $away = \App\Models\Team::factory()->create(['category_event_id' => $category->id]);
        foreach ([$first, $second, $reserve] as $index => $player) {
            \App\Models\TeamPlayer::create(['team_id' => $home->id, 'player_id' => $player->id, 'rank' => $index + 1]);
        }
        $tie = \App\Models\TeamTie::factory()->create(['draw_id' => $draw->id,
            'home_team_id' => $home->id, 'away_team_id' => $away->id]);
        $fixtures = [];
        foreach ([[$second, '09:00', 'Other Club', '1'], [$first, '09:15', 'Player School', '3'],
            [$first, '14:45', 'Player School', '5']] as $index => [$player, $time, $venue, $court]) {
            $fixture = \App\Models\TeamFixture::create(['draw_id' => $draw->id, 'team_tie_id' => $tie->id,
                'fixture_type' => 1, 'match_nr' => $index + 1, 'match_status' => 0]);
            \App\Models\TeamFixturePlayer::forceCreate(['team_fixture_id' => $fixture->id,
                'team1_id' => $player->id, 'slot_no' => 1]);
            DB::table('published_schedule_assignments')->insert(['event_id' => $event->id, 'draw_id' => $draw->id,
                'fixture_kind' => 'team', 'fixture_id' => $fixture->id, 'scheduled_at' => '2026-10-09 '.$time.':00',
                'venue_id' => DB::table('venues')->insertGetId(['name' => $venue]), 'court' => $court,
                'duration' => 75, 'published_at' => now()]);
            $fixture->forceFill(['scheduled_at' => '2026-10-09 18:00:00',
                'venue_id' => DB::table('venues')->insertGetId(['name' => 'Private working venue']), 'court_label' => '99'])->save();
            $fixtures[] = $fixture;
        }
        $groups = collect(app(MatchReminderService::class)->for($user)['players'])->keyBy('name');
        $this->assertCount(2, $groups);
        $this->assertFalse($groups->has($reserve->full_name));
        $matches = $groups[$first->full_name]['matches'];
        $this->assertSame(['team:'.$fixtures[1]->id, 'team:'.$fixtures[2]->id], array_column($matches, 'key'));
        $this->assertSame(['09:15', '14:45'], array_column($matches, 'time'));
        $this->assertSame(['Player School', 'Player School'], array_column($matches, 'venue'));
        $this->assertSame(['3', '5'], array_column($matches, 'court'));
        $this->assertSame(['Your match', 'Your match'], array_column($matches, 'label'));
        $this->assertSame([$first->full_name, ''], $matches[0]['participants']);
        $this->assertSame('Other Club', $groups[$second->full_name]['matches'][0]['venue']);
        $this->assertCount(1, $groups[$second->full_name]['matches']);
        \App\Models\TeamPlayer::where('player_id', $first->id)->delete();
        $remaining = app(MatchReminderService::class)->for($user)['players'];
        $this->assertCount(1, $remaining);
        $this->assertSame($second->full_name, $remaining[0]['name']);
    }
}
