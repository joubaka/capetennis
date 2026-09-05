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

        $service = app(MyTennisService::class);
        $publicVisibility = app(PublicDrawScheduleVisibility::class);

        $this->assertSame([$first->id], $service->nextScheduledMatchFor($player)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$first->id, $sameRound->id], $publicVisibility->visibleFixtureIds($draw)->all());
        $restrictedHub = $publicVisibility->restrictRoundRobinHub($draw, [
            'rrFixtures' => [[
                ['id' => $first->id, 'time' => 'first', 'venue_name' => 'Centre Court'],
                ['id' => $sameRound->id, 'time' => 'same round', 'venue_name' => 'Centre Court'],
                ['id' => $following->id, 'time' => 'following', 'venue_name' => 'Centre Court'],
            ]],
            'oops' => collect([
                ['id' => $first->id, 'time' => 'first', 'venue_name' => 'Centre Court', 'court' => '1'],
                ['id' => $sameRound->id, 'time' => 'same round', 'venue_name' => 'Centre Court', 'court' => '3'],
                ['id' => $following->id, 'time' => 'following', 'venue_name' => 'Centre Court', 'court' => '2'],
            ]),
        ]);
        $this->assertSame('first', $restrictedHub['oops'][0]['time']);
        $this->assertSame('same round', $restrictedHub['oops'][1]['time']);
        $this->assertNull($restrictedHub['oops'][2]['time']);
        $this->assertTrue($restrictedHub['oops'][2]['schedule_hidden']);
        $this->assertNull($restrictedHub['rrFixtures'][0][2]['time']);
        $this->assertTrue($restrictedHub['rrFixtures'][0][2]['schedule_hidden']);

        $settings->update(['schedule_visibility' => DrawSetting::SCHEDULE_VISIBILITY_FULL]);
        $this->assertSame([$first->id], $service->nextScheduledMatchFor($player)->pluck('id')->all());
        $this->assertNull($publicVisibility->visibleFixtureIds($draw->fresh()));

        $settings->update(['schedule_visibility' => DrawSetting::SCHEDULE_VISIBILITY_CURRENT_ROUND]);
        FixtureResult::factory()->create(['fixture_id' => $first->id]);
        $this->assertSame([$following->id], $service->nextScheduledMatchFor($player)->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$sameRound->id, $following->id], $publicVisibility->visibleFixtureIds($draw->fresh())->all());
        FixtureResult::factory()->create(['fixture_id' => $sameRound->id]);
        $this->assertSame([$following->id], $publicVisibility->visibleFixtureIds($draw->fresh())->all());

        $draw->update(['oop_published' => false]);
        $this->assertTrue($service->nextScheduledMatchFor($player)->isEmpty(), 'An unpublished schedule must not appear on the player dashboard.');
        $this->assertTrue($publicVisibility->visibleFixtureIds($draw->fresh())->isEmpty(), 'An unpublished schedule must expose no public fixture times.');
    }
}
