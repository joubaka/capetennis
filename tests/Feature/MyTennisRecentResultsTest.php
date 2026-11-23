<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Services\PlayerCompetitionTimelineService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
use Tests\TestCase;

class MyTennisRecentResultsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        $this->travelTo(\Carbon\CarbonImmutable::parse('2026-11-23 10:00:00', 'Africa/Johannesburg'));
        Schema::create('events', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->boolean('published'); $t->boolean('results_published'); $t->date('end_date');
        });
        Schema::create('categories', function (Blueprint $t) { $t->id(); $t->string('name'); });
        Schema::create('category_events', function (Blueprint $t) { $t->id(); $t->integer('event_id'); $t->integer('category_id'); });
        Schema::create('positions', function (Blueprint $t) { $t->id(); $t->integer('player_id'); $t->integer('category_event_id'); $t->integer('position'); });
        Schema::create('draws', function (Blueprint $t) {
            $t->id(); $t->integer('event_id'); $t->integer('category_event_id')->nullable(); $t->string('drawName'); $t->boolean('published');
        });
        Schema::create('fixtures', function (Blueprint $t) {
            $t->id(); $t->integer('draw_id'); $t->integer('registration1_id'); $t->integer('registration2_id');
        });
        Schema::create('fixture_results', function (Blueprint $t) { $t->id(); $t->integer('fixture_id'); });
        Schema::create('player_registrations', function (Blueprint $t) { $t->integer('player_id'); $t->integer('registration_id'); });
        Schema::create('category_event_registrations', function (Blueprint $t) {
            $t->id(); $t->integer('registration_id'); $t->integer('category_event_id'); $t->softDeletes();
        });
        Schema::create('team_fixtures', function (Blueprint $t) { $t->id(); $t->integer('draw_id'); $t->integer('team_tie_id')->nullable(); });
        Schema::create('team_ties', function (Blueprint $t) { $t->id(); $t->integer('draw_id'); $t->string('status'); $t->timestamp('published_at')->nullable(); });
        Schema::create('team_fixture_players', function (Blueprint $t) {
            $t->id(); $t->integer('team_fixture_id'); $t->integer('team1_id')->nullable(); $t->integer('team2_id')->nullable(); $t->integer('slot_no')->default(1);
        });
        Schema::create('team_fixture_results', function (Blueprint $t) { $t->id(); $t->integer('team_fixture_id'); $t->integer('set_nr')->default(1); });
        DB::table('categories')->insert(['id' => 1, 'name' => 'Green ball']);
    }

    private function makeResult(int $id, array $event = [], array $draw = [], int $playerId = 7, bool $scored = true): void
    {
        DB::table('events')->insert(array_replace(['id' => $id, 'name' => 'Event '.$id, 'published' => true,
            'results_published' => false, 'end_date' => '2026-11-20'], $event));
        DB::table('category_events')->insert(['id' => $id, 'event_id' => $id, 'category_id' => 1]);
        DB::table('draws')->insert(array_replace(['id' => $id, 'event_id' => $id, 'category_event_id' => $id,
            'drawName' => 'Draw '.$id, 'published' => true], $draw));
        DB::table('fixtures')->insert(['id' => $id, 'draw_id' => $id, 'registration1_id' => $id * 10, 'registration2_id' => $id * 10 + 1]);
        DB::table('player_registrations')->insert(['player_id' => $playerId, 'registration_id' => $id * 10]);
        DB::table('category_event_registrations')->insert(['id' => $id, 'registration_id' => $id * 10, 'category_event_id' => $id]);
        if ($scored) DB::table('fixture_results')->insert(['fixture_id' => $id]);
    }

    private function rows(int $limit = 20)
    {
        $player = new Player;
        $player->id = 7;
        return app(PlayerCompetitionTimelineService::class)->recentResultsFor($player, $limit);
    }

    public function test_past_public_draw_scores_are_included_without_publishing_finishing_results_or_inventing_a_place(): void
    {
        $this->makeResult(1);
        // An unpublished manual position must not leak through the draw fallback.
        DB::table('positions')->insert(['player_id' => 7, 'category_event_id' => 1, 'position' => 2]);
        $rows = $this->rows();
        $this->assertCount(1, $rows);
        $this->assertSame('Event 1', $rows[0]['event']);
        $this->assertSame('Green ball', $rows[0]['category']);
        $this->assertSame(1, $rows[0]['draw_id']);
        $this->assertNull($rows[0]['position']);
        $html = view('frontend.my-tennis.recent-results', ['recentResults' => $rows])->render();
        $this->assertStringContainsString('Draw results', $html);
        $this->assertStringContainsString(route('events.show', 1).'#event-draws-match-times', $html);
        $this->assertStringNotContainsString('Place 2', $html);
    }

    public function test_hidden_future_unscored_foreign_and_deleted_participation_are_excluded(): void
    {
        $this->makeResult(1, ['published' => false]);
        $this->makeResult(2, draw: ['published' => false]);
        $this->makeResult(3, ['end_date' => '2026-11-24']);
        $this->makeResult(4, scored: false);
        $this->makeResult(5, playerId: 8);
        $this->makeResult(6);
        DB::table('category_event_registrations')->where('id', 6)->update(['category_event_id' => 5]);
        $this->makeResult(7);
        DB::table('category_event_registrations')->where('id', 7)->update(['deleted_at' => now()]);
        $this->makeResult(8);
        DB::table('draws')->where('id', 8)->update(['category_event_id' => 5]);
        $this->assertCount(0, $this->rows());
    }

    public function test_published_places_win_over_draw_fallback_and_results_are_bounded_and_recent_first(): void
    {
        $this->makeResult(1, ['results_published' => true, 'end_date' => '2026-11-01']);
        DB::table('positions')->insert(['player_id' => 7, 'category_event_id' => 1, 'position' => 3]);
        $this->makeResult(2, ['end_date' => '2026-11-22']);
        $this->makeResult(3, ['end_date' => '2026-10-01']);
        $rows = $this->rows(2);
        $this->assertSame([2, 1], $rows->pluck('event_id')->all());
        $this->assertSame(3, $rows[1]['position']);
        $this->assertNull($rows[1]['draw_id']);
    }

    public function test_team_results_require_public_tie_and_validated_public_player_lineup(): void
    {
        $this->makeResult(1, scored: false);
        $this->makeResult(2, scored: false);
        DB::table('team_ties')->insert([
            ['id' => 1, 'draw_id' => 1, 'status' => 'published', 'published_at' => now()],
            ['id' => 2, 'draw_id' => 2, 'status' => 'draft', 'published_at' => null],
        ]);
        foreach ([1, 2] as $id) {
            DB::table('team_fixtures')->insert(['id' => $id, 'draw_id' => $id, 'team_tie_id' => $id]);
            DB::table('team_fixture_players')->insert(['team_fixture_id' => $id, 'team1_id' => 7]);
            DB::table('team_fixture_results')->insert(['team_fixture_id' => $id]);
        }
        $presenter = $this->mock(\App\Services\TeamFixtureLineupPresenter::class);
        $presenter->shouldReceive('eligiblePublicLineups')->twice()->withArgs(fn ($fixtures) => $fixtures->pluck('id')->all() === [1])
            ->andReturnUsing(fn ($fixtures) => $fixtures);
        $valid = true;
        $presenter->shouldReceive('prepare')->twice()->withArgs(function ($fixtures, $publicDraw, $selectedPlayerId, $includeProfileIds) use (&$valid) {
            $this->assertTrue($publicDraw); $this->assertSame(7, $selectedPlayerId); $this->assertTrue($includeProfileIds);
            foreach ($fixtures as $fixture) $fixture->setAttribute('lineup_display', ['home' => ['players' => [['player_id' => $valid ? 7 : null]]]]);
            return true;
        });
        $rows = $this->rows();
        $this->assertSame([1], $rows->pluck('draw_id')->all());
        $this->assertNull($rows[0]['position']);
        $valid = false;
        $this->assertCount(0, $this->rows());
    }

    public function test_multiple_draws_in_one_category_do_not_crowd_out_other_events(): void
    {
        $this->makeResult(1, ['end_date' => '2026-11-22']);
        $this->makeResult(2, ['end_date' => '2026-11-20']);
        foreach ([3, 4, 5] as $id) {
            DB::table('draws')->insert(['id' => $id, 'event_id' => 1, 'category_event_id' => 1, 'drawName' => 'Extra draw', 'published' => true]);
            DB::table('fixtures')->insert(['id' => $id, 'draw_id' => $id, 'registration1_id' => 10, 'registration2_id' => 11]);
            DB::table('fixture_results')->insert(['fixture_id' => $id]);
        }
        $this->assertSame([1, 2], $this->rows(2)->pluck('event_id')->all());
    }

    public function test_recent_event_date_takes_precedence_over_later_manual_position_saves(): void
    {
        $this->makeResult(1, ['results_published' => true, 'end_date' => '2026-11-22']);
        $this->makeResult(2, ['results_published' => true, 'end_date' => '2026-01-01']);
        DB::table('positions')->insert([
            ['id' => 1, 'player_id' => 7, 'category_event_id' => 1, 'position' => 1],
            ['id' => 2, 'player_id' => 7, 'category_event_id' => 2, 'position' => 2],
        ]);
        $row = $this->rows(1)->sole();
        $this->assertSame(1, $row['event_id']);
        $this->assertSame(1, $row['position']);
        $html = view('frontend.my-tennis.recent-results', ['recentResults' => collect([$row])])->render();
        $this->assertStringContainsString('Place 1', $html);
        $this->assertStringNotContainsString('Draw results', $html);
    }
}

