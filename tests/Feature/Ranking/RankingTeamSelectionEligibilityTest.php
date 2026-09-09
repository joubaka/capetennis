<?php

namespace Tests\Feature\Ranking;

use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\MastersInvitation;
use App\Models\MastersRankingCategoryLink;
use App\Models\Player;
use App\Models\RankingList;
use App\Models\Series;
use App\Models\SeriesRanking;
use App\Models\User;
use App\Services\Masters\MastersInvitationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RankingTeamSelectionEligibilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_masters_generation_skips_one_event_player_and_fills_the_team_from_eligible_rankings(): void
    {
        $series = Series::factory()->create(['minimum_events_for_team_selection' => 2]);
        $category = Category::factory()->create();
        $rankingList = RankingList::factory()->create([
            'series_id' => $series->id,
            'category_id' => $category->id,
        ]);
        $event = Event::factory()->create(['series_id' => $series->id]);
        $categoryEvent = CategoryEvent::factory()->create([
            'event_id' => $event->id,
            'category_id' => $category->id,
        ]);
        MastersRankingCategoryLink::create([
            'event_id' => $event->id,
            'ranking_list_id' => $rankingList->id,
            'category_event_id' => $categoryEvent->id,
            'enabled' => true,
            'top_x' => 1,
            'category_name' => $category->name,
        ]);

        $oneEventPlayer = Player::factory()->create();
        $eligiblePlayer = Player::factory()->create();
        foreach ([
            [$oneEventPlayer, 1, 1200, 1],
            [$eligiblePlayer, 2, 1100, 2],
        ] as [$player, $rank, $points, $eventsPlayed]) {
            SeriesRanking::create([
                'series_id' => $series->id,
                'ranking_list_id' => $rankingList->id,
                'category_id' => $category->id,
                'player_id' => $player->id,
                'rank_position' => $rank,
                'total_points' => $points,
                'run_id' => 'published-team-eligibility-run',
                'status' => 'published',
                'meta_json' => ['events_played' => $eventsPlayed],
            ]);
        }

        $batch = app(MastersInvitationService::class)->generateBatch(
            $event,
            $series->id,
            'published-team-eligibility-run',
            [['ranking_list_id' => $rankingList->id, 'category_event_id' => $categoryEvent->id]],
            1,
            User::factory()->create(),
        );

        $this->assertSame(1, $batch->invitations()->count());
        $invitation = $batch->invitations()->firstOrFail();
        $this->assertSame($eligiblePlayer->id, $invitation->player_id);
        $this->assertSame(MastersInvitation::INVITED, $invitation->status);
        $this->assertSame(1, $invitation->queue_position);
        $this->assertSame(2, $invitation->snapshot_json['events_played']);
        $this->assertDatabaseMissing('masters_invitations', [
            'batch_id' => $batch->id,
            'player_id' => $oneEventPlayer->id,
        ]);
    }
}
