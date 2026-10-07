<?php

namespace Tests\Feature;

use App\Models\CategoryEvent;
use App\Models\Draw;
use App\Models\Event;
use App\Models\Player;
use App\Models\Team;
use App\Models\TeamFixture;
use App\Models\TeamPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamWorkspaceResultsTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_singles_use_match_outcome_and_actual_roster_rank(): void
    {
        [$event, $category, $draw, $home, $away] = $this->scenario();
        // Historic set rows can have a last-set winner different from the match winner.
        $this->fixture($draw, $home, $away, [[6, 1], [6, 1], [1, 6]]);
        $this->fixture($draw, $home, $away, [[6, 1]], 1); // Incomplete best of three.
        $this->fixture($draw, $home, $away, [[6, 1], [6, 1]], 2); // Doubles.
        $foreignCategory = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $foreignDraw = Draw::factory()->create(['event_id' => $event->id,
            'category_event_id' => $foreignCategory->id, 'drawName' => $category->category->name]);
        $this->fixture($foreignDraw, $home, $away, [[1, 6], [1, 6]]);
        $otherEventDraw = Draw::factory()->create(['event_id' => Event::factory()->create()->id,
            'drawName' => $category->category->name]);
        $this->fixture($otherEventDraw, $home, $away, [[1, 6], [1, 6]]);
        $response = $this->postJson(route('get.event.category.data'), [
            'event_id' => $event->id, 'categoryEvent' => $category->id,
        ])->assertOk()->assertJsonCount(2, 'ranking');
        $ranking = collect($response->json('ranking'))->keyBy('name');
        $this->assertSame(3, $ranking[$home->name.' '.$home->surname]['rank']);
        $this->assertSame(70, $ranking[$home->name.' '.$home->surname]['points']);
        $this->assertSame(0, $ranking[$away->name.' '.$away->surname]['points']);
        $this->assertStringContainsString('Won', $response->json('html'));
        $this->assertStringContainsString('Lost', $response->json('html'));
    }

    public function test_unplayed_and_partial_singles_produce_empty_results(): void
    {
        [$event, $category, $draw, $home, $away] = $this->scenario();
        $this->fixture($draw, $home, $away, []);
        $this->fixture($draw, $home, $away, [[6, 2]]);
        $response = $this->postJson(route('get.event.category.data'), [
            'event_id' => $event->id, 'categoryEvent' => $category->id,
        ])->assertOk()->assertJsonCount(0, 'ranking');
        $this->assertStringContainsString('No results recorded', $response->json('html'));
    }

    public function test_result_requests_preserve_event_authorization_and_category_isolation(): void
    {
        [$event, $category] = $this->scenario();
        $foreignCategory = CategoryEvent::factory()->create();
        $this->postJson(route('get.event.category.data'), [
            'event_id' => $event->id, 'categoryEvent' => $foreignCategory->id,
        ])->assertNotFound();
        $this->actingAs(User::factory()->create())->postJson(route('get.event.category.data'), [
            'event_id' => $event->id, 'categoryEvent' => $category->id,
        ])->assertForbidden();
    }

    public function test_fixture_groups_merge_divisions_and_both_singles_formats_without_duplicate_players(): void
    {
        [$event, $category, $draw, $home, $away] = $this->groupedScenario();
        $this->fixture($draw, $home, $away, [[6, 1], [6, 1]]);
        $this->fixture($draw, $home, $away, [[6, 1], [6, 1]], 4);
        $this->fixture($draw, $home, $away, [[1, 6], [1, 6]], 2);
        $division = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $division->category->update(['name' => 'u/10 Boys-A division']);
        $divisionDraw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $division->id]);
        foreach ([[$home, 3], [$away, 4]] as [$player, $rank]) {
            $team = Team::factory()->create(['category_event_id' => $division->id, 'region_id' => $event->regions->first()->id]);
            TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => $rank]);
        }
        $this->fixture($divisionDraw, $home, $away, [[6, 1], [6, 1]]);
        $foreignDraw = Draw::factory()->create(['event_id' => Event::factory()->create()->id, 'drawName' => 'u/10 Boys']);
        $this->fixture($foreignDraw, $home, $away, [[1, 6], [1, 6]]);
        $setup = app(\App\Services\TeamResultRankingService::class)->setup($event);
        $this->assertCount(1, $setup['groups']);
        $response = $this->groupedRequest($event)->assertOk()->assertJsonCount(2, 'ranking');
        $winner = collect($response->json('ranking'))->firstWhere('id', $home->id);
        $this->assertSame(3, $winner['wins']);
        $this->assertSame(140, $winner['points']);
        $this->assertSame(1, $winner['starting_credit']);
        $this->assertSame(2, $winner['singles_wins']);
        $this->assertSame(1, $winner['reverse_singles_wins']);
        $this->assertSame(4, $winner['credited_wins']);
        $this->assertSame(6, $winner['sets_won']);
        $this->assertSame(0, $winner['sets_lost']);
        $this->assertSame(6, $winner['set_difference']);
        $this->assertCount(3, $winner['matches']);
        $this->putJson(route('backend.team-result-selection.store', $event), [
            'group_key' => '10-boys', 'region_ids' => $event->regions()->pluck('team_regions.id')->all(),
            'formats' => $setup['formats']->all(), 'selected_keys' => array_map('strval', [$home->id, $away->id]), 'reasons' => [], 'version' => 0,
        ])->assertOk()->assertJsonPath('draft.version', 1)->assertJsonPath('draft.snapshot.0.points', 140)->assertJsonPath('draft.snapshot.0.set_difference', 6)->assertJsonPath('draft.snapshot.0.starting_credit', 1)->assertJsonPath('draft.snapshot.0.singles_wins', 2);
        $this->getJson(route('backend.team-result-selection.show', $event).'?group_key=10-boys')->assertOk()->assertJsonCount(2, 'draft.selected_keys');
        if (getenv('CT_RESULTS_QA') === '1') {
            $panel = str_replace('class="tab-pane fade"', 'class="tab-pane fade show active"', view('backend.adminPage.admin_show._partials.result-ranks', compact('event'))->render());
            $json = json_encode($response->json(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            $directory = storage_path('app/team-results-qa');
            if (! is_dir($directory)) mkdir($directory, 0777, true);
            file_put_contents($directory.'/index.html', '<!doctype html><html><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/public/assets/vendor/css/rtl/core.css"><body><main class="team-admin-workspace p-3"><nav class="tabs-wrap"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-result-rank">Result Ranks</button></nav><div id="team-workspace-content" data-result-url="/mock-results">'.$panel.'</div></main><script src="/public/assets/vendor/libs/jquery/jquery.js"></script><script src="/public/assets/vendor/js/bootstrap.js"></script><script>const fixtureResponse='.$json.'; let storedDraft=null; jQuery.post=function(){const d=jQuery.Deferred();setTimeout(()=>d.resolve(fixtureResponse),80);const p=d.promise();p.abort=()=>d.reject({statusText:"abort"});return p;};jQuery.getJSON=function(){const d=jQuery.Deferred();setTimeout(()=>d.resolve({draft:storedDraft}),100);return d.promise();};jQuery.ajax=function(options){const d=jQuery.Deferred();const data=JSON.parse(options.data);setTimeout(()=>{storedDraft={...data,version:(storedDraft?.version||0)+1};d.resolve({draft:storedDraft});},150);return d.promise();};</script><script src="/public/js/team-workspace.js"></script></body></html>');
        }
        $this->groupedRequest($event, ['formats' => ['singles']])->assertOk()->assertJsonPath('ranking.0.wins', 2);
        $this->groupedRequest($event, ['regions' => []])->assertOk()->assertJsonCount(0, 'ranking');
        $this->groupedRequest($event, ['formats' => []])->assertOk()->assertJsonCount(0, 'ranking');
    }

    public function test_grouped_setup_rejects_foreign_regions_groups_and_doubles_and_requires_authorization(): void
    {
        [$event, , $draw, $home, $away] = $this->groupedScenario();
        $this->fixture($draw, $home, $away, [[6, 1], [6, 1]]);
        $region = \App\Models\TeamRegion::create(['region_name' => 'Other event', 'short_name' => 'OTHER']);
        $this->groupedRequest($event, ['regions' => [$region->id]])->assertUnprocessable();
        $this->groupedRequest($event, ['formats' => ['doubles']])->assertUnprocessable();
        $this->groupedRequest($event, ['result_group' => '11-girls'])->assertNotFound();
        $this->actingAs(User::factory()->create());
        $this->groupedRequest($event)->assertForbidden();
        $scorekeeper = User::factory()->create();
        \App\Models\EventConvenor::create(['user_id' => $scorekeeper->id, 'event_id' => $event->id, 'role' => 'score-keeper']);
        $this->actingAs($scorekeeper);
        $this->groupedRequest($event)->assertForbidden();
    }

    public function test_tenth_place_tie_is_flagged_only_when_it_crosses_selection_boundary(): void
    {
        [$event, $category, $draw, $home, $away] = $this->groupedScenario();
        $this->fixture($draw, $home, $away, [[6, 1], [6, 1]]);
        for ($index = 0; $index < 10; $index++) {
            $player = Player::factory()->create();
            $team = Team::factory()->create(['category_event_id' => $category->id, 'region_id' => $event->regions->first()->id]);
            TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 4]);
            $this->fixture($draw, $home, $player, [[6, 1], [6, 1]]);
        }
        $response = $this->groupedRequest($event)->assertOk();
        $ranking = collect($response->json('ranking'));
        $this->assertCount(12, $ranking);
        $this->assertCount(11, $ranking->where('cutoff_tie', true));
        $this->assertCount(1, $ranking->where('suggested', true));
        $this->assertStringContainsString('selection decision', $response->json('html'));
    }

    private function groupedScenario(): array
    {
        $scenario = $this->scenario();
        [$event, $category] = $scenario;
        $category->category->update(['name' => 'u/10 Boys']);
        $region = \App\Models\TeamRegion::create(['region_name' => 'Included region', 'short_name' => 'INC']);
        $event->regions()->attach($region);
        Team::where('category_event_id', $category->id)->update(['region_id' => $region->id]);
        return $scenario;
    }

    public function test_points_precede_set_difference_and_away_side_sets_are_counted(): void
    {
        [$event, , $draw, $home, $away] = $this->groupedScenario();
        TeamPlayer::where('player_id', $home->id)->update(['rank' => 4]);
        TeamPlayer::where('player_id', $away->id)->update(['rank' => 1]);
        $this->fixture($draw, $home, $away, [[6, 1], [6, 1]]);
        $response = $this->groupedRequest($event)->assertOk();
        $this->assertSame($away->id, $response->json('ranking.0.id'));
        $this->assertSame(100, $response->json('ranking.0.points'));
        $this->assertSame(-2, $response->json('ranking.0.set_difference'));
        $this->assertSame(2, $response->json('ranking.0.sets_lost'));
        $this->assertSame(35, $response->json('ranking.1.points'));
        $this->assertSame(2, $response->json('ranking.1.set_difference'));
    }

    public function test_one_historic_player_at_multiple_ranks_cannot_create_cross_band_team_evidence(): void
    {
        [$event, $category, $draw, $home, $away] = $this->groupedScenario();
        $homeTeam = TeamPlayer::where('player_id', $home->id)->first()->team;
        $awayTeam = TeamPlayer::where('player_id', $away->id)->first()->team;
        foreach (range(1, 4) as $rank) {
            for ($match = 0; $match < 2; $match++) {
                $this->fixture($draw, $home, $away, [[6, 1], [6, 1]]);
                $fixture = TeamFixture::where('draw_id', $draw->id)->latest('id')->first();
                $snapshot = [];
                foreach ([1 => [$home, $homeTeam, $rank], 2 => [$away, $awayTeam, 4]] as $side => [$player, $team, $sourceRank]) {
                    $snapshot[$side] = ['event_id' => $event->id, 'source_team_id' => $team->id, 'category_event_id' => $category->id, 'region_id' => $team->region_id, 'profile_id' => $player->id, 'imported_id' => null, 'rank' => $sourceRank];
                }
                $fixture->fixturePlayers->first()->forceFill(['participant_snapshot' => $snapshot])->save();
            }
        }
        $ranking = collect($this->groupedRequest($event)->assertOk()->json('ranking'));
        $this->assertCount(2, $ranking);
        $this->assertSame(8, $ranking->firstWhere('id', $home->id)['wins']);
        $this->assertTrue($ranking->every(fn ($row) => $row['cross_band_review'] === []));
    }

    public function test_set_difference_splits_tenth_place_without_changing_weighted_points(): void
    {
        [$event, $category, $draw, $home] = $this->groupedScenario();
        $ids = [];
        for ($index = 0; $index < 10; $index++) {
            $player = Player::factory()->create();
            $ids[] = $player->id;
            $team = Team::factory()->create(['category_event_id' => $category->id, 'region_id' => $event->regions->first()->id]);
            TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => 4]);
            $this->fixture($draw, $home, $player, $index === 9 ? [[6, 1], [6, 1]] : [[6, 1], [1, 6], [6, 1]]);
        }
        $ranking = collect($this->groupedRequest($event)->assertOk()->json('ranking'));
        $this->assertCount(11, $ranking);
        $this->assertCount(0, $ranking->where('cutoff_tie', true));
        $this->assertCount(10, $ranking->where('suggested', true));
        $this->assertSame($ids[9], $ranking->last()['id']);
        $this->assertSame(-2, $ranking->last()['set_difference']);
        $this->assertSame(2, $ranking[1]['position']);
        $this->assertSame(11, $ranking->last()['position']);
    }

    public function test_cross_band_review_requires_four_consecutive_winning_positions_on_one_source_team(): void
    {
        [$event, $category, $draw, $home, $away] = $this->groupedScenario();
        $member = TeamPlayer::where('player_id', $home->id)->first();
        $member->update(['rank' => 1]);
        $players = [$home];
        for ($rank = 2; $rank <= 5; $rank++) {
            $player = Player::factory()->create(); $players[] = $player;
            TeamPlayer::create(['team_id' => $member->team_id, 'player_id' => $player->id, 'rank' => $rank]);
        }
        foreach ($players as $player) {
            $this->fixture($draw, $player, $away, [[6, 1], [6, 1]]);
            $this->fixture($draw, $away, $player, [[1, 6], [1, 6]], 4);
            $this->fixture($draw, $away, $player, [[6, 1]], 1); // Partial excluded.
            $this->fixture($draw, $away, $player, [[6, 1], [6, 1]], 2); // Doubles excluded.
        }
        $ranking = collect($this->groupedRequest($event)->assertOk()->json('ranking'))->keyBy('id');
        foreach ($players as $player) {
            $this->assertCount(1, $ranking[$player->id]['cross_band_review']);
            $this->assertSame([1, 2, 3, 4, 5], $ranking[$player->id]['cross_band_review'][0]['ranks']);
            $this->assertSame(1, $ranking[$player->id]['singles_wins']);
            $this->assertSame(1, $ranking[$player->id]['reverse_singles_wins']);
            $this->assertSame(4, $ranking[$player->id]['sets_won']);
            $this->assertSame(0, $ranking[$player->id]['sets_lost']);
        }
        $filtered = collect($this->groupedRequest($event, ['formats' => ['singles']])->assertOk()->json('ranking'));
        $this->assertTrue($filtered->every(fn ($row) => $row['cross_band_review'] === [])); // Credits cannot replace second match.
        $duplicate = Player::factory()->create();
        $duplicateMember = TeamPlayer::create(['team_id' => $member->team_id, 'player_id' => $duplicate->id, 'rank' => 3]);
        for ($match = 0; $match < 2; $match++) $this->fixture($draw, $duplicate, $away, [[6, 1], [6, 1]]);
        $ambiguous = collect($this->groupedRequest($event)->assertOk()->json('ranking'));
        $this->assertTrue($ambiguous->every(fn ($row) => $row['cross_band_review'] === []));
        $duplicateMember->delete();
        $otherTeam = Team::factory()->create(['category_event_id' => $category->id, 'region_id' => $event->regions->first()->id]);
        TeamPlayer::where('player_id', $players[2]->id)->update(['team_id' => $otherTeam->id]);
        $separate = collect($this->groupedRequest($event)->assertOk()->json('ranking'));
        $this->assertTrue($separate->every(fn ($row) => $row['cross_band_review'] === []));
        TeamPlayer::where('player_id', $players[2]->id)->update(['team_id' => $member->team_id]);
        TeamPlayer::where('player_id', $players[2]->id)->update(['rank' => 7]);
        $gap = collect($this->groupedRequest($event)->assertOk()->json('ranking'));
        $this->assertTrue($gap->every(fn ($row) => $row['cross_band_review'] === []));
        TeamPlayer::where('player_id', $players[2]->id)->update(['rank' => 3]);
        foreach ([1, 4] as $format) $this->fixture($draw, $players[2], $away, [[1, 6], [1, 6]], $format);
        $nonWinning = collect($this->groupedRequest($event)->assertOk()->json('ranking'));
        $this->assertTrue($nonWinning->every(fn ($row) => $row['cross_band_review'] === []));
    }

    public function test_snapshot_retains_completed_player_and_roster_rank_after_substitution(): void
    {
        [$event, $category, $draw, $home, $away] = $this->groupedScenario();
        $this->fixture($draw, $home, $away, [[6, 1], [6, 1]]);
        $fixture = TeamFixture::where('draw_id', $draw->id)->first();
        $slot = $fixture->fixturePlayers->first();
        $snapshots = [];
        foreach ([1 => $home, 2 => $away] as $side => $player) {
            $member = TeamPlayer::where('player_id', $player->id)->first();
            $team = $member->team;
            $snapshots[$side] = ['event_id' => $event->id, 'source_team_id' => $team->id, 'category_event_id' => $category->id, 'region_id' => $team->region_id, 'profile_id' => $player->id, 'imported_id' => null, 'rank' => $member->rank, 'anchor_id' => $member->id];
        }
        $slot->forceFill(['participant_snapshot' => $snapshots])->save();
        TeamPlayer::where('player_id', $home->id)->delete();
        $this->groupedRequest($event)->assertOk()->assertJsonPath('ranking.0.id', $home->id)->assertJsonPath('ranking.0.rank', 3)->assertJsonPath('ranking.0.points', 70);
    }

    public function test_imported_singles_are_ranked_and_foreign_ties_cannot_supply_roster_membership(): void
    {
        [$event, $category, $draw, $home, $away] = $this->groupedScenario();
        $homeTeam = TeamPlayer::where('player_id', $home->id)->first()->team;
        $imported = \App\Models\NoProfileTeamPlayer::create(['team_id' => $homeTeam->id, 'name' => 'Imported', 'surname' => 'Candidate', 'rank' => 1, 'pay_status' => 1]);
        $this->fixture($draw, $home, $away, [[6, 1], [6, 1]]);
        $fixture = TeamFixture::where('draw_id', $draw->id)->first();
        $fixture->fixturePlayers->first()->update(['team1_id' => null, 'team1_no_profile_id' => $imported->id]);
        $this->groupedRequest($event)->assertOk()->assertJsonPath('ranking.0.name', 'Imported Candidate')->assertJsonPath('ranking.0.points', 200);
        $foreignDraw = Draw::factory()->create(['event_id' => Event::factory()->create()->id]);
        $tie = \App\Models\TeamTie::create(['draw_id' => $foreignDraw->id, 'round_nr' => 1, 'tie_nr' => 1, 'home_team_id' => $homeTeam->id, 'away_team_id' => TeamPlayer::where('player_id', $away->id)->first()->team_id]);
        $fixture->update(['team_tie_id' => $tie->id]);
        $this->groupedRequest($event)->assertOk()->assertJsonCount(0, 'ranking');
    }

    public function test_modern_mixed_source_fixture_resolves_source_team_instead_of_representative(): void
    {
        [$event, $category, $draw, $home, $away] = $this->groupedScenario();
        $homeTeam = TeamPlayer::where('player_id', $home->id)->first()->team;
        $awayTeam = TeamPlayer::where('player_id', $away->id)->first()->team;
        $girls = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $girls->category->update(['name' => 'u/10 Girls']);
        $representative = Team::factory()->create(['category_event_id' => $girls->id, 'region_id' => $homeTeam->region_id]);
        $draw->forceFill(['category_event_id' => null, 'drawName' => 'Combined team draw', 'team_draw_selection' => ['mixed_sides' => [$representative->id => ['boys' => $homeTeam->id, 'girls' => $representative->id]]]])->save();
        $tie = \App\Models\TeamTie::create(['draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => 1, 'home_team_id' => $representative->id, 'away_team_id' => $awayTeam->id]);
        $this->fixture($draw, $home, $away, [[6, 1], [6, 1]]);
        TeamFixture::where('draw_id', $draw->id)->update(['team_tie_id' => $tie->id, 'age' => 'u/10 Boys', 'gender_rule' => 'male']);
        $this->groupedRequest($event)->assertOk()->assertJsonCount(2, 'ranking')->assertJsonPath('ranking.0.id', $home->id)->assertJsonPath('ranking.0.points', 70);
    }

    private function groupedRequest(Event $event, array $overrides = [])
    {
        return $this->postJson(route('get.event.category.data'), array_replace([
            'event_id' => $event->id, 'result_group' => '10-boys',
            'regions' => $event->regions()->pluck('team_regions.id')->all(), 'formats' => app(\App\Services\TeamResultRankingService::class)->setup($event)['formats']->all(),
        ], $overrides));
    }

    private function scenario(): array
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $event = Event::factory()->create();
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id]);
        $home = Player::factory()->create();
        $away = Player::factory()->create();
        foreach ([[$home, 3], [$away, 4]] as [$player, $rank]) {
            $team = Team::factory()->create(['category_event_id' => $category->id]);
            TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => $rank, 'pay_status' => 1]);
        }
        return [$event, $category, $draw, $home, $away];
    }

    private function fixture(Draw $draw, Player $home, Player $away, array $sets, int $type = 1): void
    {
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'fixture_type' => $type,
            'numSets' => 3, 'round_nr' => 1, 'match_nr' => 1]);
        $fixture->fixturePlayers()->create(['slot_no' => 1, 'team1_id' => $home->id, 'team2_id' => $away->id]);
        foreach ($sets as $index => [$homeScore, $awayScore]) {
            $fixture->teamResults()->create(['set_nr' => $index + 1, 'team1_score' => $homeScore, 'team2_score' => $awayScore]);
        }
    }
}
