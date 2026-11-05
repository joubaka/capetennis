<?php

namespace Tests\Feature;

use App\Models\{Draw, Event, Fixture, OrderOfPlay, Player, Registration, User, Venue};
use App\Services\MyTennisService;
use App\Services\Performance\{PlayerRatingTopTen, PlayerUpcomingMatchInsights};
use Tests\TestCase;

class PlayerUpcomingMatchInsightsTest extends TestCase
{
    public function test_empty_schedule_does_not_load_the_saved_ratings_directory(): void
    {
        $user = new User;
        $player = new Player;
        $this->mock(MyTennisService::class)->shouldReceive('upcomingScheduledMatchesFor')->once()->with($player)->andReturn(collect());
        $this->mock(PlayerRatingTopTen::class)->shouldNotReceive('forMatchPlayers');

        $this->assertSame(['rows' => [], 'ratings' => [], 'total' => 0, 'page' => 1, 'pages' => 1],
            app(PlayerUpcomingMatchInsights::class)->forPlayer($user, $player));
    }

    public function test_published_fixture_projection_is_paginated_and_ratings_only_use_page_identities(): void
    {
        $user = new User;
        $player = new Player; $player->setRawAttributes(['id' => 42, 'name' => 'Own', 'surname' => 'Player']);
        $fixtures = collect();
        for ($i = 1; $i <= 21; $i++) {
            $opponent = new Player; $opponent->setRawAttributes(['id' => 100 + $i, 'name' => 'Opponent', 'surname' => (string) $i]);
            $own = new Registration; $own->setRelation('players', collect([$player]));
            $other = new Registration; $other->setRelation('players', collect([$opponent]));
            $draw = new Draw(['drawName' => 'Singles']); $draw->setRelation('event', new Event(['name' => 'Published Open']));
            $fixture = new Fixture; $fixture->setRawAttributes(['id' => $i, 'draw_id' => 5, 'scheduled_at' => '2026-11-05 09:00:00']);
            $venue = new Venue; $venue->setRawAttributes(['name' => 'Published venue']);
            $fixture->setRelation('registration1', $own)->setRelation('registration2', $other)->setRelation('draw', $draw)
                ->setRelation('venue', $venue)->setRelation('orderOfPlay', new OrderOfPlay(['court' => '3']));
            $fixtures->push($fixture);
        }
        $this->mock(MyTennisService::class)->shouldReceive('upcomingScheduledMatchesFor')->once()->with($player)->andReturn($fixtures);
        $this->mock(PlayerRatingTopTen::class)->shouldReceive('forMatchPlayers')->once()->with($user, $player, [121, 42])->andReturn([]);
        $result = app(PlayerUpcomingMatchInsights::class)->forPlayer($user, $player, 999);
        $this->assertSame(21, $result['total']);
        $this->assertSame(2, $result['page']);
        $this->assertCount(1, $result['rows']);
        $this->assertSame([['id' => 121, 'name' => 'Opponent 21']], $result['rows'][0]['opponents']);
        $this->assertSame('3', $result['rows'][0]['court']);
        $this->assertSame(route('public.roundrobin.show', 5), $result['rows'][0]['url']);
        $this->assertSame([], $result['ratings']);
    }
}
