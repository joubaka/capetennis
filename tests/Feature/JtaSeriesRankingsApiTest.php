<?php

namespace Tests\Feature;

use App\Domain\Ranking\Enums\RankingStatus;
use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\Player;
use App\Models\RankingList;
use App\Models\Series;
use App\Models\SeriesRanking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JtaSeriesRankingsApiTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->token = User::factory()->create()
            ->createToken('jta-series-ranking-test', ['jta-results:read'], now()->addHour())
            ->plainTextToken;
    }

    public function test_series_rankings_endpoint_requires_the_dedicated_token_ability(): void
    {
        $player = Player::factory()->create();
        $url = "/api/v1/integrations/jta/players/{$player->id}/series-rankings";

        $this->getJson($url)->assertUnauthorized();

        $wrongToken = User::factory()->create()->createToken('wrong-ranking', ['read'])->plainTextToken;
        $this->withToken($wrongToken)->getJson($url)->assertForbidden();

        app('auth')->forgetGuards();
        $this->withToken($this->token)->getJson($url)->assertOk();
    }

    public function test_only_official_published_ranking_is_exported_with_public_event_legs(): void
    {
        $player = Player::factory()->create([
            'name' => 'Ranked',
            'surname' => 'Player',
            'email' => 'private-ranking@example.test',
            'cellNr' => '0825551111',
            'dateOfBirth' => '2011-03-12',
        ]);
        [$series, $category, $list] = $this->rankingContext(true);
        $countedEvent = Event::factory()->create(['series_id' => $series->id]);
        $droppedEvent = Event::factory()->create(['series_id' => $series->id]);
        $countedCategoryEvent = CategoryEvent::factory()->create([
            'event_id' => $countedEvent->id,
            'category_id' => $category->id,
        ]);
        $droppedCategoryEvent = CategoryEvent::factory()->create([
            'event_id' => $droppedEvent->id,
            'category_id' => $category->id,
        ]);
        $ranking = $this->ranking($series, $category, $list, $player, [
            'rank_position' => 2,
            'total_points' => 175,
            'meta_json' => [
                'counting_legs' => [[
                    'category_event_id' => $countedCategoryEvent->id,
                    'position' => 1,
                    'points' => 100,
                ]],
                'dropped_legs' => [[
                    'category_event_id' => $droppedCategoryEvent->id,
                    'position' => 4,
                    'points' => 25,
                    'synthetic' => true,
                ]],
            ],
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v1/integrations/jta/players/{$player->id}/series-rankings")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.result_type', 'series_ranking')
            ->assertJsonPath('data.0.source_result_id', "ct-series-ranking-{$series->id}-{$category->id}-{$player->id}")
            ->assertJsonPath('data.0.series.id', $series->id)
            ->assertJsonPath('data.0.ranking_list_id', $list->id)
            ->assertJsonPath('data.0.category.id', $category->id)
            ->assertJsonPath('data.0.player.cape_tennis_player_id', $player->id)
            ->assertJsonPath('data.0.rank_position', 2)
            ->assertJsonPath('data.0.total_points', 175)
            ->assertJsonPath('data.0.event_legs.0.event_id', $countedEvent->id)
            ->assertJsonPath('data.0.event_legs.0.status', 'counted')
            ->assertJsonPath('data.0.event_legs.1.event_id', $droppedEvent->id)
            ->assertJsonPath('data.0.event_legs.1.status', 'dropped')
            ->assertJsonPath('data.0.event_legs.1.synthetic', true);

        $this->assertSame($ranking->published_at->toIso8601String(), $response->json('data.0.published_at'));
        $this->assertStringNotContainsString('private-ranking@example.test', $response->getContent());
        $this->assertStringNotContainsString('0825551111', $response->getContent());
        $this->assertStringNotContainsString('2011-03-12', $response->getContent());
        $response->assertJsonMissingPath('data.0.run_id')->assertJsonMissingPath('data.0.meta_json');
    }

    public function test_draft_archived_legacy_hidden_series_and_other_player_rankings_do_not_leak(): void
    {
        $player = Player::factory()->create();
        $other = Player::factory()->create();

        foreach ([RankingStatus::Calculated, RankingStatus::Reviewed, RankingStatus::Archived] as $status) {
            [$series, $category, $list] = $this->rankingContext(true);
            $this->ranking($series, $category, $list, $player, ['status' => $status->value]);
        }

        [$legacySeries, $legacyCategory, $legacyList] = $this->rankingContext(true);
        $this->ranking($legacySeries, $legacyCategory, $legacyList, $player, ['run_id' => null]);

        [$hiddenSeries, $hiddenCategory, $hiddenList] = $this->rankingContext(false);
        $this->ranking($hiddenSeries, $hiddenCategory, $hiddenList, $player);

        [$otherSeries, $otherCategory, $otherList] = $this->rankingContext(true);
        $this->ranking($otherSeries, $otherCategory, $otherList, $other);

        $this->withToken($this->token)
            ->getJson("/api/v1/integrations/jta/players/{$player->id}/series-rankings")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_published_correction_keeps_source_id_changes_version_and_supports_incremental_sync(): void
    {
        $player = Player::factory()->create();
        [$series, $category, $list] = $this->rankingContext(true);
        $ranking = $this->ranking($series, $category, $list, $player);
        $url = "/api/v1/integrations/jta/players/{$player->id}/series-rankings";
        $before = $this->withToken($this->token)->getJson($url)->assertOk()->json('data.0');

        $ranking->update(['rank_position' => 1, 'total_points' => 250, 'published_at' => now()]);
        $since = urlencode(now()->subMinute()->toIso8601String());
        $after = $this->withToken($this->token)
            ->getJson($url.'?updated_since='.$since)
            ->assertOk()
            ->json('data.0');

        $this->assertSame($before['source_result_id'], $after['source_result_id']);
        $this->assertNotSame($before['source_version'], $after['source_version']);
        $this->assertSame(1, $after['rank_position']);
        $this->assertSame(250, $after['total_points']);
    }

    public function test_page_and_cursor_pagination_return_each_official_ranking_once(): void
    {
        $player = Player::factory()->create();

        foreach (range(1, 3) as $rank) {
            [$series, $category, $list] = $this->rankingContext(true);
            $this->ranking($series, $category, $list, $player, ['rank_position' => $rank]);
        }

        $base = "/api/v1/integrations/jta/players/{$player->id}/series-rankings?per_page=2";
        $page1 = $this->withToken($this->token)->getJson($base)->assertOk();
        $page2 = $this->withToken($this->token)->getJson($base.'&page=2')->assertOk();
        $ids = array_merge($page1->json('data.*.source_result_id'), $page2->json('data.*.source_result_id'));
        $this->assertCount(3, array_unique($ids));

        $cursor1 = $this->withToken($this->token)->getJson($base.'&cursor=')->assertOk();
        $cursor2 = $this->withToken($this->token)->getJson($cursor1->json('links.next'))->assertOk();
        $cursorIds = array_merge($cursor1->json('data.*.source_result_id'), $cursor2->json('data.*.source_result_id'));
        $this->assertCount(3, array_unique($cursorIds));
    }

    private function rankingContext(bool $published): array
    {
        $series = Series::factory()->create(['leaderboard_published' => $published]);
        $category = Category::factory()->create();
        $list = RankingList::factory()->create([
            'series_id' => $series->id,
            'category_id' => $category->id,
        ]);

        return [$series, $category, $list];
    }

    private function ranking(
        Series $series,
        Category $category,
        RankingList $list,
        Player $player,
        array $overrides = [],
    ): SeriesRanking {
        return SeriesRanking::create(array_merge([
            'series_id' => $series->id,
            'ranking_list_id' => $list->id,
            'category_id' => $category->id,
            'player_id' => $player->id,
            'rank_position' => 3,
            'total_points' => 150,
            'meta_json' => [],
            'status' => RankingStatus::Published->value,
            'run_id' => 'published-run-'.$series->id,
            'published_at' => now()->subHour(),
        ], $overrides));
    }
}
