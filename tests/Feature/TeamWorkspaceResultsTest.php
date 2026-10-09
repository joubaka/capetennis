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

    public function test_result_cards_show_global_playing_cohort_rating_without_changing_order_or_persisting_it(): void
    {
        [$event, , $draw, $home, $away] = $this->groupedScenario();
        $otherRegion = \App\Models\TeamRegion::create(['region_name' => 'Other rating region', 'short_name' => 'OTHER']);
        $event->regions()->attach($otherRegion);
        TeamPlayer::where('player_id', $away->id)->first()->team->update(['region_id' => $otherRegion->id]);
        $this->fixture($draw, $home, $away, [[6, 1], [6, 1]]);
        $this->mock(\App\Services\Performance\PlayerRatingLeaderboardService::class)
            ->shouldReceive('build')->twice()->with(null, 'u10 boys')->andReturn([
                'rows' => collect([
                    ['identity' => 'p:'.$home->id, 'cohort' => 'u10 boys', 'position' => 7, 'rating' => ['score' => 62.4, 'component' => 'connected']],
                    ['identity' => 'p:'.$away->id, 'cohort' => 'u10 boys', 'position' => 1, 'rating' => ['score' => 88.8, 'component' => 'connected']],
                ]), 'snapshot' => ['snapshot_as_of' => '2026-10-09', 'snapshot_stale' => true], 'limitReason' => null,
            ]);
        $response = $this->groupedRequest($event)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonPath('ranking.0.id', $home->id)->assertJsonPath('ranking.0.cape_tennis_rating.score', 62.4)
            ->assertJsonPath('ranking.0.cape_tennis_rating.position', 7)->assertJsonPath('ranking.0.cape_tennis_rating.cohort', 'u10 boys');
        $this->assertStringContainsString('Rating rank 7', $response->json('html'));
        $this->assertStringContainsString('Saved rating is stale', $response->json('html'));
        $this->assertArrayNotHasKey('rating_player_id', $response->json('ranking.0'));
        $this->groupedRequest($event, ['regions' => [TeamPlayer::where('player_id', $home->id)->first()->team->region_id]])
            ->assertOk()->assertJsonPath('ranking.0.cape_tennis_rating.position', 7);
        $this->putJson(route('backend.team-result-selection.store', $event), [
            'group_key' => '10-boys', 'region_ids' => $event->regions()->pluck('team_regions.id')->all(), 'formats' => ['singles'],
            'selected_keys' => array_map('strval', [$home->id, $away->id]), 'reasons' => [], 'version' => 0,
        ])->assertOk();
        $draft = \App\Models\TeamResultSelectionDraft::firstOrFail();
        foreach ($draft->snapshot as $row) {
            $this->assertArrayNotHasKey('cape_tennis_rating', $row);
            $this->assertArrayNotHasKey('rating_player_id', $row);
        }
        $this->assertStringNotContainsString('cape_tennis_rating', \Illuminate\Support\Facades\DB::table('team_result_selection_revisions')->value('evidence'));
        Role::findOrCreate('admin', 'web');
        $manager = User::factory()->create()->assignRole('admin');
        $manager->adminEvents()->attach($event);
        $this->actingAs($manager);
        $privateFree = $this->groupedRequest($event)->assertOk();
        $this->assertArrayNotHasKey('cape_tennis_rating', $privateFree->json('ranking.0'));
        $this->assertArrayNotHasKey('rating_player_id', $privateFree->json('ranking.0'));
        $this->assertStringNotContainsString('Cape Tennis rating', $privateFree->json('html'));
        $this->getJson(route('backend.team-result-selection.show', $event).'?group_key=10-boys')->assertOk()
            ->assertJsonPath('draft.snapshot.0.id', $home->id)->assertDontSee('cape_tennis_rating');
    }

    public function test_band_first_keeps_zero_win_player_above_winning_lower_band(): void
    {
        [$event, , $draw, $home, $away] = $this->groupedScenario();
        TeamPlayer::where('player_id', $home->id)->update(['rank' => 1]);
        TeamPlayer::where('player_id', $away->id)->update(['rank' => 3]);
        $this->fixture($draw, $home, $away, [[1, 6], [1, 6]]);
        $this->groupedRequest($event)->assertOk()->assertJsonPath('ranking.0.id', $home->id)
            ->assertJsonPath('ranking.0.points', 0)->assertJsonPath('ranking.0.band', '1–2')
            ->assertJsonPath('ranking.0.lost_all_counted_band_matches', false)
            ->assertJsonPath('ranking.1.id', $away->id)->assertJsonPath('ranking.1.points', 35);
    }

    public function test_adjacent_direct_comparisons_and_own_band_loss_flags_use_only_counted_formats_and_regions(): void
    {
        [$event, $category, $draw, $home, $away] = $this->groupedScenario();
        TeamPlayer::where('player_id', $away->id)->update(['rank' => 1]);
        $opponentRegion = \App\Models\TeamRegion::create(['region_name' => 'Own band opponents']);
        $event->regions()->attach($opponentRegion);
        $third = Player::factory()->create(['name' => 'OwnBandOpponent']);
        $team = Team::factory()->create(['category_event_id' => $category->id, 'region_id' => $opponentRegion->id]);
        TeamPlayer::create(['team_id' => $team->id, 'player_id' => $third->id, 'rank' => 4]);
        $this->fixture($draw, $home, $away, [[6, 1], [6, 1]], 4);
        $this->fixture($draw, $home, $third, [[1, 6], [1, 6]]);
        $counts = [TeamFixture::count(), \App\Models\TeamFixtureResult::count(), TeamPlayer::count()];
        $ranking = collect($this->groupedRequest($event)->assertOk()->json('ranking'))->keyBy('id');
        $row = $ranking[$home->id];
        $this->assertSame(['wins' => 0, 'losses' => 1], $row['own_band_record']);
        $this->assertTrue($row['lost_all_counted_band_matches']); // A cross-band win does not erase an own-band losing record.
        $this->assertSame(1, $row['wins']);
        $comparison = collect($row['adjacent_band_comparisons'])->firstWhere('direction', 'higher');
        $this->assertSame(1, $comparison['direct_wins']);
        $this->assertSame(0, $comparison['direct_losses']);
        $this->assertSame(2, $comparison['sets_won']);
        $this->assertSame($away->id, $comparison['opponents'][0]['id']);
        $this->assertSame([$away->id], array_column($comparison['candidate_records'], 'id'));
        $singles = $this->groupedRequest($event, ['formats' => ['singles']])->assertOk();
        $singlesRow = collect($singles->json('ranking'))->firstWhere('id', $home->id);
        $this->assertSame(0, $singlesRow['adjacent_band_comparisons'][0]['direct_matches']);
        $this->assertTrue($singlesRow['lost_all_counted_band_matches']);
        $this->assertStringContainsString('No direct meetings', $singles->json('html'));
        $excluded = collect($this->groupedRequest($event, ['excluded_result_region_ids' => [$opponentRegion->id]])->assertOk()->json('ranking'))->firstWhere('id', $home->id);
        $this->assertSame(['wins' => 0, 'losses' => 0], $excluded['own_band_record']);
        $this->assertFalse($excluded['lost_all_counted_band_matches']);
        $this->assertSame(1, $excluded['adjacent_band_comparisons'][0]['direct_wins']);
        $this->assertSame($counts, [TeamFixture::count(), \App\Models\TeamFixtureResult::count(), TeamPlayer::count()]);
    }

    public function test_roster_evidence_spanning_bands_withholds_definitive_lost_all_flag(): void
    {
        [$event, , $draw, $home, $away] = $this->groupedScenario();
        $this->fixture($draw, $home, $away, [[1, 6], [1, 6]]);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $category->category->update(['name' => 'u/10 Boys']);
        $otherDraw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id]);
        foreach ([[$home, 1], [$away, 2]] as [$player, $rank]) {
            $team = Team::factory()->create(['category_event_id' => $category->id, 'region_id' => $event->regions->first()->id]);
            TeamPlayer::create(['team_id' => $team->id, 'player_id' => $player->id, 'rank' => $rank]);
        }
        $this->fixture($otherDraw, $home, $away, [[1, 6], [1, 6]]);
        $row = collect($this->groupedRequest($event)->assertOk()->json('ranking'))->firstWhere('id', $home->id);
        $this->assertTrue($row['band_attribution_uncertain']);
        $this->assertSame(['wins' => 0, 'losses' => 1], $row['own_band_record']);
        $this->assertFalse($row['lost_all_counted_band_matches']);
    }

    public function test_result_region_exclusion_is_separate_from_candidate_regions_and_persists_in_draft_evidence(): void
    {
        [$event, $category, $draw, $home, $away] = $this->groupedScenario();
        $homeRegion = $event->regions->first();
        $excluded = \App\Models\TeamRegion::create(['region_name' => 'Excluded opponents']);
        $retained = \App\Models\TeamRegion::create(['region_name' => 'Retained opponents']);
        $event->regions()->attach([$excluded->id, $retained->id]);
        $homeTeam = TeamPlayer::where('player_id', $home->id)->first()->team;
        $awayTeam = TeamPlayer::where('player_id', $away->id)->first()->team;
        $awayTeam->update(['region_id' => $excluded->id]);
        $third = Player::factory()->create();
        $thirdTeam = Team::factory()->create(['category_event_id' => $category->id, 'region_id' => $retained->id]);
        TeamPlayer::create(['team_id' => $thirdTeam->id, 'player_id' => $third->id, 'rank' => 4]);
        $this->fixture($draw, $home, $away, [[6, 1], [6, 1]]);
        $tie = \App\Models\TeamTie::create(['draw_id' => $draw->id, 'round_nr' => 1, 'tie_nr' => 1, 'home_team_id' => $homeTeam->id, 'away_team_id' => $awayTeam->id]);
        TeamFixture::where('draw_id', $draw->id)->update(['team_tie_id' => $tie->id, 'region1' => null, 'region2' => null]);
        $this->fixture($draw, $home, $third, [[1, 6], [1, 6]]);
        $this->fixture($draw, $away, $third, [[6, 1], [6, 1]]);
        $counts = [TeamFixture::count(), \App\Models\TeamFixtureResult::count(), TeamPlayer::count()];
        $candidateSetup = ['regions' => [$homeRegion->id]];
        $this->groupedRequest($event, $candidateSetup)->assertOk()->assertJsonCount(1, 'ranking')
            ->assertJsonPath('ranking.0.wins', 1)->assertJsonPath('ranking.0.losses', 1)->assertJsonCount(2, 'ranking.0.matches');
        $this->groupedRequest($event, $candidateSetup + ['excluded_result_region_ids' => [$excluded->id]])->assertOk()
            ->assertJsonCount(1, 'ranking')->assertJsonPath('ranking.0.id', $home->id)->assertJsonPath('ranking.0.points', 0)
            ->assertJsonPath('ranking.0.wins', 0)->assertJsonPath('ranking.0.losses', 1)->assertJsonPath('ranking.0.set_difference', -2)->assertJsonCount(1, 'ranking.0.matches');
        $this->groupedRequest($event, $candidateSetup + ['excluded_result_region_ids' => [$retained->id]])->assertOk()
            ->assertJsonPath('ranking.0.wins', 1)->assertJsonPath('ranking.0.losses', 0);
        $this->groupedRequest($event, ['regions' => [$homeRegion->id, $excluded->id]])->assertOk()
            ->assertJsonPath('ranking.0.id', $home->id)->assertJsonPath('ranking.0.head_to_head.applied', true)
            ->assertJsonPath('ranking.0.head_to_head.wins', 1)->assertJsonPath('ranking.1.id', $away->id);
        $withoutDirect = $this->groupedRequest($event, ['regions' => [$homeRegion->id, $excluded->id], 'excluded_result_region_ids' => [$excluded->id]])->assertOk()
            ->assertJsonCount(1, 'ranking')->assertJsonPath('ranking.0.head_to_head.applied', false);
        $this->assertNotContains($away->id, array_column($withoutDirect->json('ranking.0.matches'), 'opponent_id'));
        $this->groupedRequest($event, ['excluded_result_region_ids' => [$homeRegion->id, $excluded->id, $retained->id]])->assertOk()->assertJsonCount(0, 'ranking');
        $input = ['group_key' => '10-boys', 'region_ids' => [$homeRegion->id], 'excluded_result_region_ids' => [$excluded->id],
            'formats' => ['singles'], 'selected_keys' => [(string) $home->id], 'reasons' => [], 'version' => 0];
        $this->putJson(route('backend.team-result-selection.store', $event), $input)->assertOk()
            ->assertJsonPath('draft.excluded_result_region_ids.0', $excluded->id)->assertJsonPath('draft.snapshot.0.points', 0);
        $this->getJson(route('backend.team-result-selection.show', $event).'?group_key=10-boys')->assertOk()->assertJsonPath('draft.excluded_result_region_ids.0', $excluded->id);
        $evidence = json_decode(\Illuminate\Support\Facades\DB::table('team_result_selection_revisions')->first()->evidence, true);
        $this->assertSame([$excluded->id], $evidence['excluded_result_region_ids']);
        $foreign = \App\Models\TeamRegion::create(['region_name' => 'Foreign region']);
        $this->putJson(route('backend.team-result-selection.store', $event), array_replace($input, ['version' => 1,
            'excluded_result_region_ids' => [$homeRegion->id, $excluded->id, $retained->id]]))->assertUnprocessable();
        $this->groupedRequest($event, ['excluded_result_region_ids' => [$foreign->id]])->assertUnprocessable();
        $this->putJson(route('backend.team-result-selection.store', $event), array_replace($input, ['version' => 1, 'excluded_result_region_ids' => [$foreign->id]]))->assertUnprocessable();
        $this->assertDatabaseCount('team_result_selection_revisions', 1);
        $this->assertSame($counts, [TeamFixture::count(), \App\Models\TeamFixtureResult::count(), TeamPlayer::count()]);
    }

    public function test_head_to_head_uses_identity_and_balanced_direct_results_and_preserves_split_or_cyclic_ties(): void
    {
        $service = app(\App\Services\TeamResultRankingService::class);
        $a = ['id' => 1, 'name' => 'Zulu', 'points' => 100, 'set_difference' => 0, 'source_team_ids' => [10], 'ranks' => [1], 'rank' => 1,
            'matches' => [['opponent_id' => 2, 'opponent' => 'Wrong name', 'won' => true]]];
        $b = array_replace($a, ['id' => 2, 'name' => 'Aaron', 'source_team_ids' => [20], 'matches' => [['opponent_id' => 1, 'won' => false]]]);
        $ranking = $service->orderRanking(collect([$b, $a]));
        $this->assertSame([1, 2], $ranking->pluck('id')->all());
        $this->assertSame([1, 2], $ranking->pluck('position')->all());
        $this->assertSame(1, $ranking[0]['head_to_head']['wins']);
        $this->assertTrue($ranking[0]['head_to_head']['applied']);
        $a['matches'][] = ['opponent_id' => 2, 'won' => false];
        $b['matches'][] = ['opponent_id' => 1, 'won' => true];
        $split = $service->orderRanking(collect([$a, $b]));
        $this->assertSame([1, 1], $split->pluck('position')->all());
        $c = array_replace($a, ['id' => 'imported:30:3', 'name' => 'Middle', 'source_team_ids' => [30], 'matches' => [
            ['opponent_id' => 1, 'won' => true], ['opponent_id' => 2, 'won' => false]]]);
        $a['matches'] = [['opponent_id' => 2, 'won' => true], ['opponent_id' => $c['id'], 'won' => false]];
        $b['matches'] = [['opponent_id' => 1, 'won' => false], ['opponent_id' => $c['id'], 'won' => true]];
        $cycle = $service->orderRanking(collect([$c, $b, $a]));
        $this->assertSame([1, 1, 1], $cycle->pluck('position')->all());
        $this->assertTrue($cycle->every(fn ($row) => $row['head_to_head']['applied']));
        $this->assertSame($cycle->all(), $service->orderRanking(collect([$a, $c, $b]))->all());
        $a['matches'][1]['won'] = true;
        $c['matches'][0]['won'] = false;
        $miniLeague = $service->orderRanking(collect([$c, $b, $a]));
        $this->assertSame([1, 2, $c['id']], $miniLeague->pluck('id')->all());
        $this->assertSame([1, 2, 3], $miniLeague->pluck('position')->all());
        $c['matches'] = [];
        $incomplete = $service->orderRanking(collect([$a, $b, $c]));
        $this->assertSame([1, 1, 1], $incomplete->pluck('position')->all());
        $this->assertFalse($incomplete[0]['head_to_head']['applied']);
    }

    public function test_same_team_final_tie_uses_higher_roster_position_after_points_and_sets(): void
    {
        [$event, , $draw, $home, $away] = $this->groupedScenario();
        $home->update(['name' => 'Zulu']);
        $away->update(['name' => 'Aaron']);
        $member = TeamPlayer::where('player_id', $home->id)->first();
        $member->update(['rank' => 1]);
        TeamPlayer::where('player_id', $away->id)->update(['team_id' => $member->team_id, 'rank' => 2]);
        $this->fixture($draw, $home, $away, [[6, 1], [6, 1]]);
        $this->fixture($draw, $home, $away, [[1, 6], [1, 6]]);
        $this->groupedRequest($event)->assertOk()->assertJsonPath('ranking.0.id', $home->id)
            ->assertJsonPath('ranking.0.position', 1)->assertJsonPath('ranking.1.position', 2)
            ->assertJsonPath('ranking.0.points', 100)->assertJsonPath('ranking.1.points', 100)
            ->assertJsonPath('ranking.0.set_difference', 0)->assertJsonPath('ranking.1.set_difference', 0);
    }

    public function test_same_team_cutoff_resolves_but_mixed_or_ambiguous_team_ties_stay_shared_and_deterministic(): void
    {
        $service = app(\App\Services\TeamResultRankingService::class);
        $leaders = collect(range(1, 9))->map(fn ($id) => ['id' => $id, 'name' => 'Leader'.$id, 'points' => 1000 - $id,
            'set_difference' => 0, 'source_team_ids' => [30], 'ranks' => [1], 'rank' => 1]);
        $higher = ['id' => 10, 'name' => 'Zulu', 'points' => 100, 'set_difference' => 0, 'source_team_ids' => [10], 'ranks' => [1], 'rank' => 1];
        $lower = array_replace($higher, ['id' => 11, 'name' => 'Aaron', 'ranks' => [2], 'rank' => 2]);
        $resolved = $service->orderRanking($leaders->concat([$lower, $higher]));
        $this->assertSame([10, 11], $resolved->slice(9)->pluck('id')->all());
        $this->assertSame([10, 11], $resolved->slice(9)->pluck('position')->all());
        $this->assertCount(0, $resolved->where('cutoff_tie', true));
        $this->assertTrue($resolved[9]['suggested']);
        $this->assertFalse($resolved[10]['suggested']);
        foreach ([array_replace($higher, ['id' => 12, 'name' => 'Middle', 'source_team_ids' => [20]]),
            array_replace($higher, ['id' => 12, 'name' => 'Ambiguous', 'source_team_ids' => [10, 20]]),
            array_replace($higher, ['id' => 12, 'name' => 'Multiple ranks', 'ranks' => [1, 2], 'rank' => 2])] as $foreign) {
            $rows = $leaders->concat([$lower, $foreign, $higher]);
            $ranking = $service->orderRanking($rows);
            $this->assertSame($ranking->all(), $service->orderRanking($rows->reverse())->all());
            $this->assertSame([10, 10, 10], $ranking->slice(9)->pluck('position')->all());
            $this->assertCount(3, $ranking->where('cutoff_tie', true));
            $this->assertCount(9, $ranking->where('suggested', true));
        }
        $equalRank = $service->orderRanking($leaders->concat([$higher, array_replace($higher, ['id' => 11])]));
        $this->assertCount(2, $equalRank->where('cutoff_tie', true));
    }

    public function test_positions_in_each_band_have_equal_weight_and_zero_wins_earn_zero_points(): void
    {
        $service = app(\App\Services\TeamResultRankingService::class);
        foreach ([1 => 100, 3 => 35, 5 => 12, 7 => 2] as $rank => $weight) {
            $this->assertSame(6 * $weight, $service->points($rank, 6));
            $this->assertSame($service->points($rank, 6), $service->points($rank + 1, 6));
            $this->assertSame(0, $service->points($rank, 0));
            $this->assertSame(0, $service->points($rank + 1, 0));
        }
    }

    public function test_rank_two_with_six_wins_outranks_rank_one_with_four_wins(): void
    {
        [$event, $category, $draw, $home, $away] = $this->groupedScenario();
        TeamPlayer::where('player_id', $home->id)->update(['rank' => 1]);
        TeamPlayer::where('player_id', $away->id)->update(['rank' => 2]);
        foreach (range(1, 4) as $match) $this->fixture($draw, $home, $away, [[6, 1], [6, 1]]);
        foreach (range(1, 6) as $match) $this->fixture($draw, $home, $away, [[1, 6], [1, 6]]);
        $this->groupedRequest($event)->assertOk()->assertJsonPath('ranking.0.id', $away->id)
            ->assertJsonPath('ranking.0.points', 600)->assertJsonPath('ranking.0.wins', 6)
            ->assertJsonPath('ranking.1.id', $home->id)->assertJsonPath('ranking.1.points', 400)
            ->assertJsonPath('ranking.0.starting_credit', 0)->assertJsonPath('ranking.0.points_per_win', 100);
    }

    public function test_old_draft_evidence_is_preserved_while_current_results_and_new_revision_use_band_weights(): void
    {
        [$event, , $draw, $home, $away] = $this->groupedScenario();
        $this->fixture($draw, $home, $away, [[6, 1], [6, 1]]);
        $input = ['group_key' => '10-boys', 'region_ids' => $event->regions()->pluck('team_regions.id')->all(),
            'formats' => ['singles'], 'selected_keys' => array_map('strval', [$home->id, $away->id]), 'reasons' => [], 'version' => 0];
        $draft = \App\Models\TeamResultSelectionDraft::create(array_merge($input, ['event_id' => $event->id, 'version' => 1,
            'snapshot' => [['id' => $home->id, 'points' => 70, 'starting_credit' => 1, 'credited_wins' => 2]], 'updated_by' => auth()->id()]));
        $oldEvidence = json_encode($draft->only(['region_ids', 'formats', 'selected_keys', 'reasons', 'snapshot']), JSON_THROW_ON_ERROR);
        \Illuminate\Support\Facades\DB::table('team_result_selection_revisions')->insert([
            'draft_id' => $draft->id, 'version' => 1, 'evidence' => $oldEvidence, 'created_by' => auth()->id(), 'created_at' => now(),
        ]);
        $storedOldEvidence = \Illuminate\Support\Facades\DB::table('team_result_selection_revisions')->where('draft_id', $draft->id)->where('version', 1)->value('evidence');
        $this->getJson(route('backend.team-result-selection.show', $event).'?group_key=10-boys')->assertOk()->assertJsonPath('draft.snapshot.0.points', 70)->assertJsonPath('draft.excluded_result_region_ids', []);
        $current = $this->groupedRequest($event)->assertOk()->assertJsonPath('ranking.0.points', 35);
        $this->assertStringNotContainsString('Starting credit', $current->json('html'));
        $this->assertStringContainsString('Points per win', $current->json('html'));
        $this->putJson(route('backend.team-result-selection.store', $event), array_replace($input, ['version' => 1]))
            ->assertOk()->assertJsonPath('draft.version', 2)->assertJsonPath('draft.snapshot.0.points', 35)->assertJsonPath('draft.snapshot.0.starting_credit', 0);
        $this->assertSame($storedOldEvidence, \Illuminate\Support\Facades\DB::table('team_result_selection_revisions')->where('draft_id', $draft->id)->where('version', 1)->value('evidence'));
        $this->assertDatabaseCount('team_result_selection_revisions', 2);
    }

    public function test_historical_workspace_and_results_keep_rosters_and_exclude_shared_region_teams(): void
    {
        [$event, , $draw, $home, $away] = $this->groupedScenario();
        $event->update(['eventType' => 3]);
        $region = $event->regions->first();
        $teams = Team::where('region_id', $region->id)->get();
        foreach ($teams as $team) $team->update(['category_event_id' => null, 'name' => 'Included u/10 Boys '.$team->id]);
        $draw->update(['category_event_id' => null, 'drawName' => 'U/10 Boys Singles reverse']);
        $this->fixture($draw, $home, $away, [[6, 1], [6, 1]]);
        TeamFixture::where('draw_id', $draw->id)->update(['fixture_type' => null]);
        $this->fixture($draw, $home, $away, [[6, 1]]);
        TeamFixture::where('draw_id', $draw->id)->update(['fixture_type' => null, 'numSets' => null]);
        $shared = \App\Models\TeamRegion::create(['region_name' => 'Shared historical region']);
        $event->regions()->attach($shared);
        Event::factory()->create()->regions()->attach($shared);
        $foreign = Team::factory()->create(['region_id' => $shared->id, 'name' => 'Foreign u/10 Boys']);
        TeamPlayer::create(['team_id' => $foreign->id, 'player_id' => $home->id, 'rank' => 1]);
        $counts = [TeamFixture::count(), \App\Models\TeamFixtureResult::count(), TeamPlayer::count()];

        $response = $this->get('/backend/event/'.$event->id.'/teams')->assertOk();
        $this->assertEqualsCanonicalizing($teams->modelKeys(), $response->viewData('event')->regions->flatMap->teams->pluck('id')->all());
        $this->get('/backend/event/'.$event->id.'/teams?roster_region='.$region->id)->assertOk()->assertSee($home->name);
        $this->groupedRequest($event)->assertOk()->assertJsonCount(2, 'ranking')->assertJsonPath('ranking.0.reverse_singles_wins', 1);
        $this->assertSame($counts, [TeamFixture::count(), \App\Models\TeamFixtureResult::count(), TeamPlayer::count()]);
        $homeTeam = $teams->first(fn ($team) => $team->team_players->contains('player_id', $home->id));
        $imported = \App\Models\NoProfileTeamPlayer::create(['team_id' => $homeTeam->id, 'name' => 'Historical', 'surname' => 'Imported', 'rank' => 3, 'pay_status' => 1]);
        $fixture = TeamFixture::where('draw_id', $draw->id)->orderBy('id')->first();
        $fixture->fixturePlayers->first()->update(['team1_id' => null, 'team1_no_profile_id' => $imported->id]);
        $this->groupedRequest($event)->assertOk()->assertJsonCount(2, 'ranking')->assertJsonPath('ranking.0.name', 'Historical Imported');
        $draw->update(['drawName' => 'U/10 Boys Singles Doubles']);
        $this->assertCount(0, app(\App\Services\TeamResultRankingService::class)->setup($event)['groups']);
    }

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
        $this->assertSame(35, $ranking[$home->name.' '.$home->surname]['points']);
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
        $this->assertSame(105, $winner['points']);
        $this->assertSame(0, $winner['starting_credit']);
        $this->assertSame(2, $winner['singles_wins']);
        $this->assertSame(1, $winner['reverse_singles_wins']);
        $this->assertSame(3, $winner['credited_wins']);
        $this->assertSame(6, $winner['sets_won']);
        $this->assertSame(0, $winner['sets_lost']);
        $this->assertSame(6, $winner['set_difference']);
        $this->assertCount(3, $winner['matches']);
        $this->putJson(route('backend.team-result-selection.store', $event), [
            'group_key' => '10-boys', 'region_ids' => $event->regions()->pluck('team_regions.id')->all(),
            'formats' => $setup['formats']->all(), 'selected_keys' => array_map('strval', [$home->id, $away->id]), 'reasons' => [], 'version' => 0,
        ])->assertOk()->assertJsonPath('draft.version', 1)->assertJsonPath('draft.snapshot.0.points', 105)->assertJsonPath('draft.snapshot.0.set_difference', 6)->assertJsonPath('draft.snapshot.0.starting_credit', 0)->assertJsonPath('draft.snapshot.0.singles_wins', 2);
        $this->getJson(route('backend.team-result-selection.show', $event).'?group_key=10-boys')->assertOk()->assertJsonCount(2, 'draft.selected_keys');
        if (getenv('CT_RESULTS_QA') === '1') {
            $panel = str_replace('class="tab-pane fade result-workspace"', 'class="tab-pane fade show active result-workspace"', view('backend.adminPage.admin_show._partials.result-ranks', compact('event'))->render());
            $preview = $response->json();
            $previewRows = collect($preview['ranking']);
            foreach (['Liam van der Merwe', 'Oliver Jacobs', 'Noah Petersen', 'Ethan du Plessis', 'Daniel Williams', 'Joshua Adams'] as $index => $name) {
                $row = $preview['ranking'][0]; $row['id'] = 'preview-'.$index; $row['name'] = $name;
                $row['position'] = $index + 3; $row['region'] = 'CT'; $row['teams'] = ['Cape Town A'];
                $previewRows->push($row);
            }
            $reviewRow = $previewRows->get(2);
            $reviewRow['cross_band_review'] = [['team' => 'Cape Town A', 'ranks' => [1, 2, 3, 4], 'records' => [1 => ['wins' => 2, 'losses' => 0], 2 => ['wins' => 2, 'losses' => 0], 3 => ['wins' => 2, 'losses' => 0], 4 => ['wins' => 2, 'losses' => 0]]]];
            $previewRows->put(2, $reviewRow);
            $preview['html'] = view('backend.adminPage.admin_show._table.result-selection', ['ranking' => $previewRows])->render();
            $extraGroups = '';
            foreach (['10-girls' => 'u/10 Girls', '11-boys' => 'u/11 Boys', '11-girls' => 'u/11 Girls', '12-boys' => 'u/12 Boys', '12-girls' => 'u/12 Girls', '13-boys' => 'u/13 Boys', '13-girls' => 'u/13 Girls'] as $key => $label) {
                $extraGroups .= '<label class="result-choice result-group-choice"><input class="form-check-input category-radio" type="radio" name="category-radio" value="'.$key.'" data-name="'.$label.'" data-event_id="'.$event->id.'"><span>'.$label.'</span></label>';
            }
            $panel = preg_replace('/(<\/div>\s*<\/aside>)/', $extraGroups.'$1', $panel, 1);
            $panel = str_replace('Included region', 'Cape Town', $panel);
            $extraRegions = '';
            foreach (['Cape Winelands', 'Eden District', 'West Coast', 'Overberg', 'Nelson Mandela Bay'] as $index => $regionName) $extraRegions .= '<label class="result-choice"><input class="form-check-input" type="checkbox" data-result-region value="'.(1000 + $index).'" checked><span>'.$regionName.'</span></label>';
            $panel = preg_replace('/<\/div><\/fieldset>/', $extraRegions.'</div></fieldset>', $panel, 1);
            $json = json_encode($preview, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
            $directory = storage_path('app/team-results-qa');
            if (! is_dir($directory)) mkdir($directory, 0777, true);
            file_put_contents($directory.'/index.html', '<!doctype html><html><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/public/assets/vendor/css/rtl/core.css"><link rel="stylesheet" href="/public/css/backend-workspace.css"><link rel="stylesheet" href="/public/css/team-workspace.css"><body class="ct-backend"><main class="team-admin-workspace p-3"><nav class="tabs-wrap"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-result-rank">Result Ranks</button></nav><div id="team-workspace-content" data-result-url="/mock-results">'.$panel.'</div></main><script src="/public/assets/vendor/libs/jquery/jquery.js"></script><script src="/public/assets/vendor/js/bootstrap.js"></script><script>const fixtureResponse='.$json.'; let storedDraft=null; jQuery.post=function(){const d=jQuery.Deferred();setTimeout(()=>d.resolve(fixtureResponse),80);const p=d.promise();p.abort=()=>d.reject({statusText:"abort"});return p;};jQuery.getJSON=function(){const d=jQuery.Deferred();setTimeout(()=>d.resolve({draft:storedDraft}),100);return d.promise();};jQuery.ajax=function(options){const d=jQuery.Deferred();const data=JSON.parse(options.data);setTimeout(()=>{storedDraft={...data,version:(storedDraft?.version||0)+1};d.resolve({draft:storedDraft});},150);return d.promise();};</script><script src="/public/js/team-workspace.js"></script></body></html>');
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
        $this->fixture($draw, $home, $away, [[1, 6], [6, 1], [1, 6]]);
        $response = $this->groupedRequest($event)->assertOk();
        $this->assertSame($away->id, $response->json('ranking.0.id'));
        $this->assertSame(100, $response->json('ranking.0.points'));
        $this->assertSame(-1, $response->json('ranking.0.set_difference'));
        $this->assertSame(3, $response->json('ranking.0.sets_lost'));
        $this->assertSame(35, $response->json('ranking.1.points'));
        $this->assertSame(1, $response->json('ranking.1.set_difference'));
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
        $this->groupedRequest($event)->assertOk()->assertJsonPath('ranking.0.id', $home->id)->assertJsonPath('ranking.0.rank', 3)->assertJsonPath('ranking.0.points', 35);
    }

    public function test_imported_singles_are_ranked_and_foreign_ties_cannot_supply_roster_membership(): void
    {
        [$event, $category, $draw, $home, $away] = $this->groupedScenario();
        $homeTeam = TeamPlayer::where('player_id', $home->id)->first()->team;
        $imported = \App\Models\NoProfileTeamPlayer::create(['team_id' => $homeTeam->id, 'name' => 'Imported', 'surname' => 'Candidate', 'rank' => 1, 'pay_status' => 1]);
        $this->fixture($draw, $home, $away, [[6, 1], [6, 1]]);
        $fixture = TeamFixture::where('draw_id', $draw->id)->first();
        $fixture->fixturePlayers->first()->update(['team1_id' => null, 'team1_no_profile_id' => $imported->id]);
        $this->groupedRequest($event)->assertOk()->assertJsonPath('ranking.0.name', 'Imported Candidate')->assertJsonPath('ranking.0.points', 100);
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
        $this->groupedRequest($event)->assertOk()->assertJsonCount(2, 'ranking')->assertJsonPath('ranking.0.id', $home->id)->assertJsonPath('ranking.0.points', 35);
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
