<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryEvent;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamPlayer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventConfirmedEntryCountTest extends TestCase
{
    use DatabaseTransactions;

    public function test_team_event_counts_paid_players_not_team_or_roster_rows_and_stays_event_scoped(): void
    {
        $teamTypeId = DB::table('eventtypes')->insertGetId([
            'name' => 'Team event count test',
            'type' => EventType::TEAM,
            'code' => 'team-entry-count-test',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $event = Event::factory()->create(['eventType' => $teamTypeId, 'entryFee' => 490]);
        $otherEvent = Event::factory()->create(['eventType' => $teamTypeId, 'entryFee' => 490]);

        foreach (range(1, 15) as $teamNumber) {
            $categoryEvent = CategoryEvent::create([
                'event_id' => $event->id,
                'category_id' => Category::factory()->create()->id,
                'entry_fee' => 0,
                'ordering' => $teamNumber,
            ]);
            $team = Team::factory()->create(['category_event_id' => $categoryEvent->id]);
            $player = Player::factory()->create();
            TeamPlayer::create([
                'team_id' => $team->id,
                'player_id' => $player->id,
                'rank' => 1,
                'pay_status' => $teamNumber === 1 ? 1 : 0,
            ]);
        }

        $otherCategory = CategoryEvent::create([
            'event_id' => $otherEvent->id,
            'category_id' => Category::factory()->create()->id,
            'entry_fee' => 0,
            'ordering' => 1,
        ]);
        $otherTeam = Team::factory()->create(['category_event_id' => $otherCategory->id]);
        TeamPlayer::create([
            'team_id' => $otherTeam->id,
            'player_id' => Player::factory()->create()->id,
            'rank' => 1,
            'pay_status' => 1,
        ]);

        $this->assertSame(1, $event->confirmedEntryCount());

        TeamPlayer::withoutGlobalScopes()
            ->whereHas('team.category', fn ($query) => $query->where('event_id', $event->id))
            ->update(['pay_status' => 0]);

        $this->assertSame(0, $event->confirmedEntryCount());
    }
}
