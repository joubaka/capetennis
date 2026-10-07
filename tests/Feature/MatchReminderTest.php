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

    public function test_all_linked_players_today_and_tomorrow_use_published_snapshot(): void
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
        $this->matchFor($player, '2026-10-09 00:00:00');
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
        $tie->update(['status' => \App\Models\TeamTie::STATUS_COMPLETED]);
        request()->attributes->remove('published_schedule_rows_'.$event->id);
        $this->assertSame([], app(MatchReminderService::class)->for($user)['players']);
        $tie->update(['status' => \App\Models\TeamTie::STATUS_DRAFT]);
        \App\Models\TeamPlayer::where('player_id', $player->id)->delete();
        request()->attributes->remove('published_schedule_rows_'.$event->id);
        $this->assertSame([], app(MatchReminderService::class)->for($user)['players']);
    }
}
