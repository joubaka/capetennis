<?php

namespace Tests\Feature\Ranking;

use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\RankingList;
use App\Models\Series;
use App\Models\SeriesRanking;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RankingManagementAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $this->admin = User::factory()->create()->assignRole('admin');
    }

    public function test_ordinary_authenticated_user_cannot_rebuild_rankings(): void
    {
        $series = Series::factory()->create();

        $this->actingAs(User::factory()->create())
            ->postJson("/backend/ranking/series/{$series->id}/rebuild")
            ->assertForbidden();
    }

    public function test_legacy_ranking_mutations_are_retired(): void
    {
        $this->artisan('ranking:rebuild-legacy', ['series_id' => 999])
            ->expectsOutputToContain('legacy rebuild is disabled')
            ->assertFailed();

        $this->actingAs($this->admin)
            ->postJson('/backend/ranking-scores/999/school', ['group' => 'primary'])
            ->assertStatus(410);
    }

    public function test_category_event_from_another_series_cannot_be_attached(): void
    {
        [$list] = $this->rankingListFixture();
        $otherSeries = Series::factory()->create();
        $otherEvent = Event::factory()->create(['series_id' => $otherSeries->id]);
        $foreignCategoryEvent = CategoryEvent::factory()->create([
            'event_id' => $otherEvent->id,
            'category_id' => $list->category_id,
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('ranking.lists.add-category', $list), [
                'category_event_id' => $foreignCategoryEvent->id,
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('ranking_list_category_events', [
            'ranking_list_id' => $list->id,
            'category_event_id' => $foreignCategoryEvent->id,
        ]);
    }

    public function test_category_event_from_wrong_category_cannot_be_attached(): void
    {
        [$list, $series] = $this->rankingListFixture();
        $event = Event::factory()->create(['series_id' => $series->id]);
        $wrongCategory = Category::factory()->create();
        $categoryEvent = CategoryEvent::factory()->create([
            'event_id' => $event->id,
            'category_id' => $wrongCategory->id,
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('ranking.lists.add-category', $list), [
                'category_event_id' => $categoryEvent->id,
            ])
            ->assertStatus(422);
    }

    public function test_list_order_requires_exact_linked_set_and_persists_sort_order(): void
    {
        [$list, $series] = $this->rankingListFixture();
        $event = Event::factory()->create(['series_id' => $series->id]);
        $first = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => $list->category_id]);
        $second = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => $list->category_id]);

        $list->rank_cats()->createMany([
            ['category_event_id' => $first->id],
            ['category_event_id' => $second->id],
        ]);

        $this->actingAs($this->admin)
            ->postJson(route('ranking.lists.order', $list), ['order' => [$first->id]])
            ->assertStatus(422);

        $this->actingAs($this->admin)
            ->postJson(route('ranking.lists.order', $list), ['order' => [$second->id, $first->id]])
            ->assertOk();

        $this->assertDatabaseHas('ranking_list_category_events', [
            'ranking_list_id' => $list->id,
            'category_event_id' => $second->id,
            'sort_order' => 1,
        ]);
        $this->assertDatabaseHas('ranking_list_category_events', [
            'ranking_list_id' => $list->id,
            'category_event_id' => $first->id,
            'sort_order' => 2,
        ]);
    }

    public function test_legacy_public_results_route_is_not_registered(): void
    {
        $series = Series::factory()->create();

        $this->get("/ranking/{$series->id}/results")->assertNotFound();
    }

    public function test_legacy_authenticated_ranking_pages_redirect_to_canonical_list(): void
    {
        [, $series] = $this->rankingListFixture();

        $this->actingAs($this->admin)
            ->get(route('backend.ranking.show', $series))
            ->assertRedirect(route('ranking.series.list', $series));

        $this->actingAs($this->admin)
            ->get(route('backend.ranking.results', $series))
            ->assertRedirect(route('ranking.series.list', $series));

        $this->actingAs($this->admin)
            ->get(route('series.rankings', $series))
            ->assertRedirect(route('ranking.series.list', $series));
    }

    public function test_admin_list_exposes_only_the_action_for_the_active_lifecycle_state(): void
    {
        [$list, $series] = $this->rankingListFixture();
        $player = \App\Models\Player::factory()->create();
        SeriesRanking::create([
            'series_id' => $series->id,
            'ranking_list_id' => $list->id,
            'category_id' => $list->category_id,
            'player_id' => $player->id,
            'rank_position' => 1,
            'total_points' => 1000,
            'status' => 'calculated',
            'run_id' => 'run-calculated',
            'meta_json' => [],
        ]);

        $this->actingAs($this->admin)
            ->get(route('ranking.series.list', $series))
            ->assertOk()
            ->assertSee('Mark Reviewed')
            ->assertDontSee('>Publish<', false)
            ->assertDontSee('Roll Back');
    }

    private function rankingListFixture(): array
    {
        $series = Series::factory()->create();
        $event = Event::factory()->create(['series_id' => $series->id]);
        \Illuminate\Support\Facades\DB::table('event_admins')->insert([
            'event_id' => $event->id,
            'user_id' => $this->admin->id,
        ]);
        $category = Category::factory()->create();
        $list = RankingList::factory()->create([
            'series_id' => $series->id,
            'category_id' => $category->id,
        ]);

        return [$list, $series];
    }
}
