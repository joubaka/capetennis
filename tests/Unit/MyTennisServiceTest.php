<?php

namespace Tests\Unit;

use App\Models\Player;
use App\Models\Draw;
use App\Models\DrawSetting;
use App\Models\Event;
use App\Models\Fixture;
use App\Models\FixtureResult;
use App\Models\OrderOfPlay;
use App\Models\Registration;
use App\Models\User;
use App\Services\MyTennisService;
use App\Services\PublicDrawScheduleVisibility;
use App\Services\Scheduling\SchedulePublicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MyTennisServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_switcher_combines_legacy_and_pivot_links_without_cross_family_leakage(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $pivotPlayer = Player::factory()->create();
        $legacyPlayer = Player::factory()->create(['userId' => $user->id]);
        $otherPlayer = Player::factory()->create(['userId' => $otherUser->id]);

        $user->players()->attach($pivotPlayer);
        $service = app(MyTennisService::class);

        $ids = $service->playersFor($user)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$pivotPlayer->id, $legacyPlayer->id], $ids);
        $this->assertNotContains($otherPlayer->id, $ids);
    }

    public function test_public_first_match_mode_exposes_only_each_players_next_assigned_match(): void
    {
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();
        $registration = Registration::factory()->create();
        $opponentRegistration = Registration::factory()->create();
        $otherRegistration = Registration::factory()->create();
        $otherOpponentRegistration = Registration::factory()->create();
        $registration->players()->attach($player);
        $opponentRegistration->players()->attach($opponent);

        $event = Event::factory()->create(['end_date' => now()->addDays(5)->toDateString()]);
        $draw = Draw::factory()->create([
            'event_id' => $event->id,
            'published' => true,
            'oop_published' => true,
        ]);
        $settings = $draw->settings()->create([
            'schedule_visibility' => DrawSetting::SCHEDULE_VISIBILITY_CURRENT_ROUND,
        ]);
        $venueId = DB::table('venues')->insertGetId(['name' => 'Centre Court']);

        $first = Fixture::factory()->create([
            'draw_id' => $draw->id,
            'registration1_id' => $registration->id,
            'registration2_id' => $opponentRegistration->id,
            'scheduled' => 1,
        ]);
        $following = Fixture::factory()->create([
            'draw_id' => $draw->id,
            'registration1_id' => $registration->id,
            'registration2_id' => $opponentRegistration->id,
            'scheduled' => 1,
            'round' => 2,
        ]);
        $sameRound = Fixture::factory()->create([
            'draw_id' => $draw->id,
            'registration1_id' => $otherRegistration->id,
            'registration2_id' => $otherOpponentRegistration->id,
            'scheduled' => 1,
            'round' => 1,
        ]);
        OrderOfPlay::create([
            'fixture_id' => $first->id,
            'draw_id' => $draw->id,
            'venue_id' => $venueId,
            'time' => now()->addDay(),
            'court' => '1',
        ]);
        OrderOfPlay::create([
            'fixture_id' => $following->id,
            'draw_id' => $draw->id,
            'venue_id' => $venueId,
            'time' => now()->addDays(2),
            'court' => '2',
        ]);
        OrderOfPlay::create([
            'fixture_id' => $sameRound->id,
            'draw_id' => $draw->id,
            'venue_id' => $venueId,
            'time' => now()->addDay()->addHour(),
            'court' => '3',
        ]);

        app(SchedulePublicationService::class)->publish($event, ['draw_id' => $draw->id]);
        $service = app(MyTennisService::class);
        $publicVisibility = app(PublicDrawScheduleVisibility::class);

        $this->assertSame([$first->id], $service->nextScheduledMatchFor($player)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$first->id, $sameRound->id], $publicVisibility->visibleFixtureIds($draw)->all());
        $restrictedHub = $publicVisibility->restrictRoundRobinHub($draw, [
            'rrFixtures' => [[
                ['id' => $first->id, 'time' => '2026-09-06 08:00:00', 'venue_name' => 'Centre Court'],
                ['id' => $sameRound->id, 'time' => '2026-09-06 09:00:00', 'venue_name' => 'Centre Court'],
                ['id' => $following->id, 'time' => '2026-09-06 10:00:00', 'venue_name' => 'Centre Court'],
                ['id' => 999999, 'time' => null, 'venue_name' => null],
            ]],
            'oops' => collect([
                ['id' => $first->id, 'time' => '2026-09-06 08:00:00', 'venue_name' => 'Centre Court', 'court' => '1'],
                ['id' => $sameRound->id, 'time' => '2026-09-06 09:00:00', 'venue_name' => 'Centre Court', 'court' => '3'],
                ['id' => $following->id, 'time' => '2026-09-06 10:00:00', 'venue_name' => 'Centre Court', 'court' => '2'],
                ['id' => 999999, 'time' => null, 'venue_name' => null, 'court' => null],
            ]),
        ]);
        $this->assertSame($first->orderOfPlay->time, $restrictedHub['oops'][0]['time']);
        $this->assertSame($sameRound->orderOfPlay->time, $restrictedHub['oops'][1]['time']);
        $this->assertNull($restrictedHub['oops'][2]['time']);
        $this->assertTrue($restrictedHub['oops'][2]['schedule_hidden']);
        $this->assertNull($restrictedHub['oops'][2]['scheduled_date']);
        $this->assertNull($restrictedHub['oops'][2]['venue_name']);
        $this->assertNull($restrictedHub['oops'][2]['court']);
        $this->assertNull($restrictedHub['rrFixtures'][0][2]['time']);
        $this->assertTrue($restrictedHub['rrFixtures'][0][2]['schedule_hidden']);
        $this->assertNull($restrictedHub['rrFixtures'][0][2]['scheduled_date']);
        $this->assertNull($restrictedHub['rrFixtures'][0][2]['venue_name']);
        $this->assertTrue($restrictedHub['oops'][3]['schedule_hidden']);
        $this->assertTrue($restrictedHub['rrFixtures'][0][3]['schedule_hidden']);

        $settings->update(['schedule_visibility' => DrawSetting::SCHEDULE_VISIBILITY_FULL]);
        request()->attributes->remove('published_schedule_rows_'.$event->id);
        $this->assertSame([$first->id], $service->nextScheduledMatchFor($player)->pluck('id')->all());
        $this->assertNull($publicVisibility->visibleFixtureIds($draw->fresh()));

        $settings->update(['schedule_visibility' => DrawSetting::SCHEDULE_VISIBILITY_CURRENT_ROUND]);
        FixtureResult::factory()->create(['fixture_id' => $first->id]);
        request()->attributes->remove('published_schedule_rows_'.$event->id);
        $this->assertSame([$following->id], $service->nextScheduledMatchFor($player)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$sameRound->id, $following->id], $publicVisibility->visibleFixtureIds($draw->fresh())->all());
        FixtureResult::factory()->create(['fixture_id' => $sameRound->id]);
        request()->attributes->remove('published_schedule_rows_'.$event->id);
        $this->assertSame([$following->id], $publicVisibility->visibleFixtureIds($draw->fresh())->all());

        $draw->update(['oop_published' => false]);
        request()->attributes->remove('published_schedule_rows_'.$event->id);
        $this->assertTrue($service->nextScheduledMatchFor($player)->isEmpty(), 'An unpublished schedule must not appear on the player dashboard.');
        $this->assertTrue($publicVisibility->visibleFixtureIds($draw->fresh())->isEmpty(), 'An unpublished schedule must expose no public fixture times.');
    }

    public function test_round_robin_first_match_mode_gives_the_bye_player_a_time(): void
    {
        $event = Event::factory()->create(['end_date' => now()->addDays(5)->toDateString()]);
        $draw = Draw::factory()->create([
            'event_id' => $event->id,
            'published' => true,
            'oop_published' => true,
        ]);
        $draw->settings()->create([
            'workflow' => 'round_robin',
            'schedule_visibility' => DrawSetting::SCHEDULE_VISIBILITY_FIRST_MATCH,
        ]);
        $venueId = DB::table('venues')->insertGetId(['name' => 'Round Robin Courts']);
        $registrations = Registration::factory()->count(3)->create();

        $opening = Fixture::factory()->create([
            'draw_id' => $draw->id,
            'stage' => 'RR',
            'round' => 1,
            'registration1_id' => $registrations[0]->id,
            'registration2_id' => $registrations[1]->id,
        ]);
        $byePlayersFirstMatch = Fixture::factory()->create([
            'draw_id' => $draw->id,
            'stage' => 'RR',
            'round' => 2,
            'registration1_id' => $registrations[1]->id,
            'registration2_id' => $registrations[2]->id,
        ]);
        $laterMatch = Fixture::factory()->create([
            'draw_id' => $draw->id,
            'stage' => 'RR',
            'round' => 3,
            'registration1_id' => $registrations[0]->id,
            'registration2_id' => $registrations[2]->id,
        ]);

        foreach ([
            [$opening, now()->addDay(), '1'],
            [$byePlayersFirstMatch, now()->addDay()->addHour(), '2'],
            [$laterMatch, now()->addDay()->addHours(2), '3'],
        ] as [$fixture, $time, $court]) {
            OrderOfPlay::create([
                'fixture_id' => $fixture->id,
                'draw_id' => $draw->id,
                'venue_id' => $venueId,
                'time' => $time,
                'court' => $court,
            ]);
        }

        app(SchedulePublicationService::class)->publish($event, ['draw_id' => $draw->id]);
        $visibility = app(PublicDrawScheduleVisibility::class);

        $this->assertSame(
            [$opening->id, $byePlayersFirstMatch->id],
            $visibility->visibleFixtureIds($draw->fresh())->all(),
            'The player with the opening-round bye must receive their first playing time.',
        );

        FixtureResult::factory()->create(['fixture_id' => $opening->id]);
        request()->attributes->remove('published_schedule_rows_'.$event->id);

        $this->assertSame(
            [$byePlayersFirstMatch->id, $laterMatch->id],
            $visibility->visibleFixtureIds($draw->fresh())->all(),
        );
    }

    public function test_team_roster_member_sees_published_snapshot_before_lineup_publication(): void
    {
        [$player, $event, $draw, $fixture] = $this->scheduledTeamMatch();
        $service = app(MyTennisService::class);
        $this->assertTrue($service->nextScheduledMatchFor($player)->isEmpty());
        app(SchedulePublicationService::class)->publish($event, ['draw_id' => $draw->id]);
        $publishedTime = $fixture->scheduled_at->format('Y-m-d H:i:s');
        $fixture->update(['scheduled_at' => now()->addDays(4), 'court_label' => 'Private court']);

        $match = $service->nextScheduledMatchFor($player)->sole();
        $this->assertInstanceOf(\App\Models\TeamFixture::class, $match);
        $this->assertSame($publishedTime, $match->scheduled_at->format('Y-m-d H:i:s'));
        $this->assertSame('1', $match->court_label);
        $this->assertSame('Juventus', $match->tie_display['home']);
        $this->assertNull($match->teamTie->published_at);
        $this->assertDatabaseCount('team_fixture_players', 0);
        $this->assertTrue($service->nextScheduledMatchFor(Player::factory()->create())->isEmpty());

        $fixture->teamTie->homeTeam->category->update(['event_id' => Event::factory()->create()->id]);
        $this->assertTrue($service->nextScheduledMatchFor($player)->isEmpty(), 'A foreign event roster cannot qualify a player.');
    }

    public function test_next_match_selects_earliest_team_or_individual_and_respects_hidden_draw(): void
    {
        [$player, $event, $draw, $teamFixture] = $this->scheduledTeamMatch();
        app(SchedulePublicationService::class)->publish($event, ['draw_id' => $draw->id]);
        $registration = Registration::factory()->create();
        $registration->players()->attach($player);
        $individualDraw = Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        $individual = Fixture::factory()->create(['draw_id' => $individualDraw->id, 'registration1_id' => $registration->id]);
        OrderOfPlay::create(['fixture_id' => $individual->id, 'draw_id' => $individualDraw->id,
            'venue_id' => $teamFixture->venue_id, 'time' => now()->addDays(3), 'court' => '2']);
        app(SchedulePublicationService::class)->publish($event, ['draw_id' => $individualDraw->id]);
        $service = app(MyTennisService::class);
        $this->assertInstanceOf(\App\Models\TeamFixture::class, $service->nextScheduledMatchFor($player)->sole());
        $teamFixture->update(['match_status' => 1]);
        $this->assertSame($individual->id, $service->nextScheduledMatchFor($player)->sole()->id);
        $teamFixture->update(['match_status' => 0]);
        $draw->update(['published' => false]);
        request()->attributes->remove('published_schedule_rows_'.$event->id);
        $this->assertSame($individual->id, $service->nextScheduledMatchFor($player)->sole()->id);
        $individualDraw->update(['oop_published' => false]);
        request()->attributes->remove('published_schedule_rows_'.$event->id);
        $this->assertTrue($service->nextScheduledMatchFor($player)->isEmpty());
    }

    public function test_legacy_team_fixture_uses_assigned_player_and_published_time(): void
    {
        [$player, $event, $draw, $fixture] = $this->scheduledTeamMatch();
        $tie = $fixture->teamTie;
        $fixture->update(['team_tie_id' => null]);
        $tie->delete();
        \App\Models\TeamFixturePlayer::forceCreate(['team_fixture_id' => $fixture->id, 'team1_id' => $player->id, 'slot_no' => 1]);
        app(SchedulePublicationService::class)->publish($event, ['draw_id' => $draw->id]);
        $this->assertSame($fixture->id, app(MyTennisService::class)->nextScheduledMatchFor($player)->sole()->id);
    }

    public function test_team_match_excludes_private_or_finished_events_and_completed_ties(): void
    {
        [$player, $event, $draw, $fixture] = $this->scheduledTeamMatch();
        app(SchedulePublicationService::class)->publish($event, ['draw_id' => $draw->id]);
        $service = app(MyTennisService::class);
        $event->update(['published' => false]);
        request()->attributes->remove('published_schedule_rows_'.$event->id);
        $this->assertTrue($service->nextScheduledMatchFor($player)->isEmpty());
        $event->update(['published' => true, 'end_date' => '2026-10-06']);
        request()->attributes->remove('published_schedule_rows_'.$event->id);
        $this->assertTrue($service->nextScheduledMatchFor($player)->isEmpty());
        $event->update(['end_date' => '2026-10-14']);
        $fixture->teamTie->update(['status' => \App\Models\TeamTie::STATUS_COMPLETED]);
        $this->assertTrue($service->nextScheduledMatchFor($player)->isEmpty());
    }

    private function scheduledTeamMatch(): array
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-07 09:00:00'));
        $player = Player::factory()->create();
        $event = Event::factory()->create(['eventType' => 3, 'end_date' => '2026-10-14']);
        $category = \App\Models\CategoryEvent::factory()->create(['event_id' => $event->id]);
        $home = \App\Models\Team::factory()->create(['category_event_id' => $category->id, 'name' => 'Juventus']);
        $away = \App\Models\Team::factory()->create(['category_event_id' => $category->id, 'name' => 'Inter']);
        \App\Models\TeamPlayer::create(['team_id' => $home->id, 'player_id' => $player->id, 'rank' => 1]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        $tie = \App\Models\TeamTie::create(['draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => 1,
            'home_team_id' => $home->id, 'away_team_id' => $away->id, 'status' => 'draft']);
        $venueId = DB::table('venues')->insertGetId(['name' => 'Team courts']);
        $fixture = \App\Models\TeamFixture::create(['draw_id' => $draw->id, 'team_tie_id' => $tie->id,
            'round_nr' => 1, 'tie_nr' => 1, 'match_nr' => 1, 'fixture_type' => 1, 'match_status' => 0,
            'scheduled_at' => now()->addDays(2), 'venue_id' => $venueId, 'court_label' => '1']);
        return [$player, $event, $draw, $fixture];
    }

}
