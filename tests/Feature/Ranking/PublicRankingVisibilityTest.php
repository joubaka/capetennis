<?php

namespace Tests\Feature\Ranking;

use App\Domain\Ranking\Enums\RankingStatus;
use App\Models\Category;
use App\Models\Player;
use App\Models\RankingList;
use App\Models\Series;
use App\Models\SeriesRanking;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PublicRankingVisibilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_public_leaderboard_only_shows_latest_published_run(): void
    {
        $series = Series::factory()->create(['leaderboard_published' => true]);
        $category = Category::factory()->create();
        $list = RankingList::factory()->create(['series_id' => $series->id, 'category_id' => $category->id]);
        $published = Player::factory()->create(['name' => 'Published Player']);
        $draft = Player::factory()->create(['name' => 'Draft Player']);
        $archived = Player::factory()->create(['name' => 'Archived Player']);

        $this->row($series, $list, $category, $published, RankingStatus::Published, 'run-live', now());
        $this->row($series, $list, $category, $draft, RankingStatus::Calculated, 'run-draft');
        $this->row($series, $list, $category, $archived, RankingStatus::Archived, 'run-old');

        $this->get(route('frontend.ranking.show', $series))
            ->assertOk()
            ->assertSee('Published Player')
            ->assertDontSee('Draft Player')
            ->assertDontSee('Archived Player');
    }

    public function test_direct_leaderboard_url_returns_404_when_series_is_not_published(): void
    {
        $series = Series::factory()->create(['leaderboard_published' => false]);

        $this->get(route('frontend.ranking.show', $series))->assertNotFound();
    }

    public function test_player_detail_does_not_expose_non_published_row(): void
    {
        $series = Series::factory()->create(['leaderboard_published' => true]);
        $category = Category::factory()->create();
        $list = RankingList::factory()->create(['series_id' => $series->id, 'category_id' => $category->id]);
        $player = Player::factory()->create(['name' => 'Calculated Only']);
        $this->row($series, $list, $category, $player, RankingStatus::Calculated, 'run-draft');

        $this->get(route('frontend.ranking.player-detail', [$series, $player]))->assertNotFound();
    }

    public function test_published_legacy_leaderboard_without_run_ids_remains_visible(): void
    {
        $series = Series::factory()->create(['leaderboard_published' => true]);
        $category = Category::factory()->create();
        $list = RankingList::factory()->create(['series_id' => $series->id, 'category_id' => $category->id]);
        $legacy = Player::factory()->create(['name' => 'Legacy Published Player']);
        $draft = Player::factory()->create(['name' => 'Canonical Draft Player']);

        $this->row($series, $list, $category, $legacy, RankingStatus::Calculated, null);
        $this->row($series, $list, $category, $draft, RankingStatus::Calculated, 'run-draft');

        $this->get(route('frontend.ranking.show', $series))
            ->assertOk()
            ->assertSee('Legacy Published Player')
            ->assertDontSee('Canonical Draft Player');

        $this->get(route('frontend.ranking.player-detail', [$series, $legacy]))->assertOk();
        $this->get(route('frontend.ranking.player-detail', [$series, $draft]))->assertNotFound();
    }

    private function row(
        Series $series,
        RankingList $list,
        Category $category,
        Player $player,
        RankingStatus $status,
        ?string $runId,
        $publishedAt = null,
    ): void {
        SeriesRanking::create([
            'series_id' => $series->id,
            'ranking_list_id' => $list->id,
            'category_id' => $category->id,
            'player_id' => $player->id,
            'rank_position' => 1,
            'total_points' => 1000,
            'status' => $status->value,
            'run_id' => $runId,
            'published_at' => $publishedAt,
            'meta_json' => [],
        ]);
    }
}
