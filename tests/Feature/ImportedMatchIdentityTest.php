<?php

namespace Tests\Feature;

use App\Models\{Category, CategoryEvent, Draw, Event, NoProfileTeamPlayer, Player, Team, TeamFixture, TeamPlayer, TeamTie};
use App\Services\Performance\{ImportedMatchIdentityResolver, PlayerAbilitySnapshotStore, PlayerPerformanceHistoryService, PlayerSharedAbilityService};
use App\Services\TeamParticipantHistoryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportedMatchIdentityTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $event = Event::factory()->create(['published' => true, 'start_date' => '2026-01-01', 'end_date' => '2026-01-02']);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => 'u10 Boys'])->id]);
        $home = Team::factory()->create(['category_event_id' => $category->id]);
        $away = Team::factory()->create(['category_event_id' => $category->id]);
        $first = Player::factory()->create(); $second = Player::factory()->create();
        TeamPlayer::create(['team_id' => $home->id, 'player_id' => $first->id, 'rank' => 1]);
        TeamPlayer::create(['team_id' => $away->id, 'player_id' => $second->id, 'rank' => 1]);
        $imported = NoProfileTeamPlayer::create(['team_id' => $home->id, 'name' => 'Imported', 'surname' => 'Player', 'rank' => 1, 'player_profile' => $first->id, 'pay_status' => 1]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'published' => true, 'drawName' => 'u10 Boys Singles']);
        $tie = TeamTie::factory()->published()->create(['draw_id' => $draw->id, 'home_team_id' => $home->id, 'away_team_id' => $away->id]);
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'team_tie_id' => $tie->id, 'fixture_type' => 1, 'numSets' => 3, 'match_status' => 1, 'round_nr' => 1, 'match_nr' => 1]);
        $row = $fixture->fixturePlayers()->create(['slot_no' => 1, 'team1_no_profile_id' => $imported->id, 'team2_id' => $second->id]);
        app(TeamParticipantHistoryService::class)->captureFixture($fixture, true);
        foreach ([1, 2] as $set) { $fixture->teamResults()->create(['set_nr' => $set, 'team1_score' => 6, 'team2_score' => 1]); }
        $row->refresh();
        return compact('event', 'home', 'away', 'first', 'second', 'imported', 'fixture', 'row');
    }

    public function test_captured_exact_link_resolves_played_evidence_without_rewriting_fixture_identity(): void
    {
        $s = $this->scenario();
        $this->assertSame($s['first']->id, $s['row']->participant_snapshot[1]['linked_profile_id']);
        $before = $s['row']->getRawOriginal();
        $matches = iterator_to_array(app(PlayerPerformanceHistoryService::class)->teamMatches($s['event'], null, CarbonImmutable::parse('2026-01-03')));
        $this->assertCount(1, $matches);
        $this->assertNull($matches[0]['reason']);
        $this->assertSame($s['first']->id, $matches[0]['player1_id']);
        $this->assertSame($before, $s['row']->fresh()->getRawOriginal());
        $personal = iterator_to_array(app(PlayerPerformanceHistoryService::class)->teamMatches($s['event'], $s['first'], CarbonImmutable::parse('2026-01-03')));
        $this->assertCount(1, $personal);
        $this->assertNull($personal[0]['reason']);
    }

    public function test_missing_attestation_is_never_backfilled_and_wrong_event_team_rank_or_relink_are_rejected(): void
    {
        $s = $this->scenario(); $resolver = app(ImportedMatchIdentityResolver::class);
        $original = $s['row']->participant_snapshot;
        unset($original[1]['linked_profile_id']);
        $s['row']->forceFill(['participant_snapshot' => null])->save();
        app(TeamParticipantHistoryService::class)->captureFixture($s['fixture']->fresh());
        $this->assertArrayNotHasKey('linked_profile_id', $s['row']->fresh()->participant_snapshot[1]);
        $this->assertNull($resolver->resolve($s['row']->fresh(), 1, $s['home'], $s['event']->id));
        $s['row']->forceFill(['participant_snapshot' => $original])->save();
        app(TeamParticipantHistoryService::class)->captureFixture($s['fixture']->fresh());
        $this->assertArrayNotHasKey('linked_profile_id', $s['row']->fresh()->participant_snapshot[1]);
        $this->assertNull($resolver->resolve($s['row']->fresh(), 1, $s['home'], $s['event']->id));
        $original[1]['linked_profile_id'] = $s['first']->id;
        $s['row']->forceFill(['participant_snapshot' => $original])->save();
        $this->assertNull($resolver->resolve($s['row'], 1, $s['home'], $s['event']->id + 1));
        $this->assertNull($resolver->resolve($s['row'], 1, $s['away'], $s['event']->id));
        foreach ([['rank' => 2], ['team_id' => $s['away']->id], ['player_profile' => $s['second']->id]] as $change) {
            $s['imported']->update($change);
            $this->assertNull($resolver->resolve($s['row'], 1, $s['home'], $s['event']->id));
            $s['imported']->update(['rank' => 1, 'team_id' => $s['home']->id, 'player_profile' => $s['first']->id]);
        }
        TeamPlayer::create(['team_id' => $s['home']->id, 'player_id' => Player::factory()->create()->id, 'rank' => 1]);
        $this->assertNull($resolver->resolve($s['row'], 1, $s['home'], $s['event']->id));
    }

    public function test_imported_relink_and_duplicate_roster_invalidate_snapshot_context_and_source_fingerprint(): void
    {
        $s = $this->scenario(); $store = app(PlayerAbilitySnapshotStore::class);
        $manifest = $store->manifest(['source_events' => [$s['event']->id], 'source_matches' => ['team-match:'.$s['fixture']->id]]);
        $fingerprint = app(PlayerSharedAbilityService::class)->fingerprint();
        $this->assertTrue($store->published($manifest));
        $s['imported']->update(['player_profile' => $s['second']->id]);
        $this->assertFalse($store->published($manifest));
        $this->assertNotSame($fingerprint, app(PlayerSharedAbilityService::class)->fingerprint());
        $marker = app(\App\Services\Performance\PlayerAbilityRefreshState::class);
        $generation = $marker->generation();
        $s['imported']->update(['player_profile' => $s['first']->id]);
        $this->assertGreaterThan($generation, $marker->generation());
        NoProfileTeamPlayer::create(['team_id' => $s['home']->id, 'name' => 'Duplicate', 'surname' => 'Row', 'rank' => 2, 'player_profile' => $s['first']->id, 'pay_status' => 1]);
        $this->assertNull(app(ImportedMatchIdentityResolver::class)->resolve($s['row'], 1, $s['home'], $s['event']->id));
        $this->assertFalse($store->published($manifest));
    }
}
