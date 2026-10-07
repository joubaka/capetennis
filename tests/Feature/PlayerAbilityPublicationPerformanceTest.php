<?php

namespace Tests\Feature;

use App\Models\{Draw, Event, Player};
use App\Services\Performance\PlayerAbilitySnapshotStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlayerAbilityPublicationPerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_large_disjoint_match_groups_remain_bounded_and_withhold_on_membership_or_identity_changes(): void
    {
        $event = Event::factory()->create(['published' => true]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'published' => true]);
        $player = Player::factory()->create();
        $fixtures = []; $participants = [];
        foreach (range(1, 2101) as $id) {
            $fixtures[] = ['id' => $id, 'draw_id' => $draw->id, 'match_nr' => $id];
            $participants[] = ['team_fixture_id' => $id, 'team1_id' => $player->id];
        }
        foreach (array_chunk($fixtures, 500) as $rows) DB::table('team_fixtures')->insert($rows);
        foreach (array_chunk($participants, 500) as $rows) DB::table('team_fixture_players')->insert($rows);
        $manifest = ['events' => [$event->only(['id', 'published'])],
            'draws' => [$draw->only(['id', 'event_id', 'published'])],
            'team_fixture_players' => DB::table('team_fixture_players')->get(['id', 'team_fixture_id', 'team1_id'])->map(fn ($row) => (array) $row)->all()];
        $store = new PlayerAbilitySnapshotStore;
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertTrue($store->published($manifest));
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        $this->assertLessThanOrEqual(6, $queryCount, 'Publication verification must remain batched as match groups grow.');

        $extraId = DB::table('team_fixture_players')->insertGetId(['team_fixture_id' => 2101, 'team1_id' => $player->id]);
        $this->assertFalse($store->published($manifest), 'An extra member in the last group must withhold the snapshot.');
        DB::table('team_fixture_players')->where('id', $extraId)->delete();
        DB::table('team_fixture_players')->where('team_fixture_id', 2101)->update(['team1_id' => Player::factory()->create()->id]);
        $this->assertFalse($store->published($manifest), 'Captured identity changes must still withhold the snapshot.');
        DB::table('team_fixture_players')->where('team_fixture_id', 2101)->update(['team1_id' => $player->id]);
        $this->assertTrue($store->published($manifest));
        DB::table('events')->where('id', $event->id)->update(['published' => false]);
        $this->assertFalse($store->published($manifest), 'Publication revocation must remain immediate.');
    }
}
