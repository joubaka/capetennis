<?php

namespace Tests\Feature;

use App\Models\{Category, CategoryEvent, Draw, Event, EventType, NoProfileTeamPlayer, Player, Team, TeamFixture, TeamFixturePlayer, TeamFixtureResult, TeamPlayer, TeamRegion, TeamTie, Venue};
use App\Services\Performance\{AllAgeMatchFormatResolver, AllAgeRatingModel, PlayerAbilitySnapshotStore, PlayerAbilityValidationService, PlayerPerformanceHistoryService, PlayerRatingEvidenceBuilder, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AllAgeTeamEvidenceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $homeLabel = 'U13 Boys A', string $awayLabel = 'U18 Girls A', ?string $ball = 'yellow', ?Player $first = null, ?Player $second = null): array
    {
        $type = DB::table('eventtypes')->insertGetId(['name' => 'Synthetic team singles', 'type' => EventType::TEAM, 'code' => 'global-team-'.Event::count()]);
        $event = Event::factory()->create(['eventType' => $type, 'published' => true, 'results_published' => true, 'start_date' => '2026-10-01', 'end_date' => '2026-10-02']);
        $homeField = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => $homeLabel])->id]);
        $awayField = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => $awayLabel])->id]);
        $homeRegion = TeamRegion::create(['region_name' => 'Synthetic home region']);
        $awayRegion = TeamRegion::create(['region_name' => 'Synthetic away region']);
        $event->regions()->attach([$homeRegion->id, $awayRegion->id]);
        $home = Team::factory()->create(['category_event_id' => $homeField->id, 'year' => 2026, 'region_id' => $homeRegion->id]);
        $away = Team::factory()->create(['category_event_id' => $awayField->id, 'year' => 2026, 'region_id' => $awayRegion->id]);
        $first ??= Player::factory()->create(); $second ??= Player::factory()->create();
        TeamPlayer::create(['team_id' => $home->id, 'player_id' => $first->id, 'rank' => 1]);
        TeamPlayer::create(['team_id' => $away->id, 'player_id' => $second->id, 'rank' => 1]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => null, 'drawName' => 'Open Singles', 'published' => true]);
        $tie = TeamTie::factory()->published()->create(['draw_id' => $draw->id, 'home_team_id' => $home->id, 'away_team_id' => $away->id]);
        $venue = Venue::forceCreate(['name' => 'Synthetic verified court']);
        DB::table('event_venue_courts')->insert(['event_id' => $event->id, 'venue_id' => $venue->id, 'label' => '1', 'ball_type' => $ball, 'active' => true]);
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'team_tie_id' => $tie->id, 'fixture_type' => 1, 'rubber_code' => 'singles', 'numSets' => 1,
            'match_status' => 1, 'round_nr' => 1, 'match_nr' => 1, 'scheduled_at' => '2026-10-01 09:00:00', 'venue_id' => $venue->id, 'court_label' => '1']);
        $row = TeamFixturePlayer::create(['team_fixture_id' => $fixture->id, 'slot_no' => 1, 'team1_id' => $first->id, 'team2_id' => $second->id]);
        TeamFixtureResult::create(['team_fixture_id' => $fixture->id, 'set_nr' => 1, 'team1_score' => 6, 'team2_score' => 2]);
        return compact('event', 'homeField', 'awayField', 'home', 'away', 'first', 'second', 'draw', 'tie', 'fixture', 'row', 'venue');
    }

    private function globalRows(array $data): array
    {
        return iterator_to_array(app(PlayerPerformanceHistoryService::class)->allAgeTeamMatches($data['event'], CarbonImmutable::parse('2026-10-03')));
    }

    public function test_real_cross_age_and_cross_gender_result_connects_graph_without_changing_legacy_cohorts_or_sources(): void
    {
        $boys = $this->fixture('U13 Boys A', 'U13 Boys A');
        $girls = $this->fixture('U18 Girls A', 'U18 Girls A');
        $service = app(PlayerSharedAbilityService::class); $asOf = CarbonImmutable::parse('2026-10-03');
        $before = $service->calculateSnapshot($asOf);
        $this->assertCount(2, $before['all_age']['components']);
        $bridge = $this->fixture(first: $boys['first'], second: $girls['first']);
        $counts = [Player::count(), TeamFixture::count(), TeamFixtureResult::count(), DB::table('user_players')->count()];
        $rows = iterator_to_array(app(PlayerPerformanceHistoryService::class)->teamMatches($bridge['event'], null, $asOf, allAgeEvidence: true));
        $this->assertSame('Team singles age/gender context cannot be verified', $rows[0]['reason']);
        $this->assertNull($rows[0]['all_age_reason']);
        $this->assertTrue($rows[0]['all_age_requires_explicit_format']);
        $after = $service->calculateSnapshot($asOf);
        $this->assertCount(1, $after['all_age']['components']);
        $this->assertCount(4, $after['all_age']['ratings']);
        $this->assertSame(['u13 boys', 'u18 girls'], $after['all_age']['ratings'][$boys['first']->id]['component_age_groups']);
        $this->assertCount(2, $after['cohorts']);
        $this->assertContains($bridge['event']->id, $after['source_events']);
        $this->assertContains('team-match:'.$bridge['fixture']->id, $after['source_matches']);
        $this->assertSame($counts, [Player::count(), TeamFixture::count(), TeamFixtureResult::count(), DB::table('user_players')->count()]);
        $store = new PlayerAbilitySnapshotStore;
        $store->replace($after, $asOf, $service->fingerprint(), $store->manifest($after));
        $this->assertNull((new PlayerAbilitySnapshotStore)->current()['reason']);
        $sources = [];
        $historical = $service->historySnapshot($asOf, $sources);
        $evidence = new PlayerRatingEvidenceBuilder;
        $evidence->append($historical, $sources, 0);
        $bridgeRows = array_values(array_filter($evidence->all(), fn ($row) => $row['source_id'] === 'team-match:'.$bridge['fixture']->id));
        $this->assertCount(1, $bridgeRows);
        $this->assertSame(AllAgeRatingModel::COHORT, $bridgeRows[0]['cohort']);
        $this->assertSame($after['names'][$boys['first']->id], $bridgeRows[0]['names'][0]);
        $bridge['tie']->update(['published_at' => null]);
        $this->assertNotNull((new PlayerAbilitySnapshotStore)->current()['reason']);
    }

    public function test_cross_context_and_unclassified_matches_require_explicit_standard_conditions(): void
    {
        $service = app(PlayerSharedAbilityService::class); $date = CarbonImmutable::parse('2026-10-03');
        $unknown = $this->fixture(ball: null);
        $this->assertNull($this->globalRows($unknown)[0]['reason']);
        $this->assertSame([], $service->calculateSnapshot($date)['all_age']['ratings']);
        $unclassified = $this->fixture('Open home squad', 'Open away squad');
        $snapshot = $service->calculateSnapshot($date);
        $this->assertCount(2, $snapshot['all_age']['ratings']);
        $this->assertSame(['unclassified singles'], $snapshot['all_age']['ratings'][$unclassified['first']->id]['age_groups']);
        $blocked = $this->fixture('U13 Boys Green ball A', 'U18 Girls A');
        $snapshot = $service->calculateSnapshot($date);
        $this->assertArrayNotHasKey($blocked['first']->id, $snapshot['all_age']['ratings']);
        $this->assertSame('Team source declares a modified ball or court format', (new AllAgeMatchFormatResolver)->resolve($blocked['event']->id, $this->globalRows($blocked)[0])['format_reason']);
    }

    public function test_invalid_snapshot_error_is_preserved_when_legacy_demographic_error_overwrites_it(): void
    {
        $data = $this->fixture();
        $data['row']->forceFill(['participant_snapshot' => [1 => ['event_id' => 999999, 'source_team_id' => $data['home']->id,
            'category_event_id' => $data['homeField']->id, 'region_id' => $data['home']->region_id, 'profile_id' => $data['first']->id, 'imported_id' => null]]])->save();
        $legacy = iterator_to_array(app(PlayerPerformanceHistoryService::class)->teamMatches($data['event'], null, CarbonImmutable::parse('2026-10-03')));
        $this->assertSame('Team singles age/gender context cannot be verified', $legacy[0]['reason']);
        $this->assertSame('Team match participant history does not match this event/player/side', $this->globalRows($data)[0]['reason']);
        $this->assertSame([], app(PlayerSharedAbilityService::class)->calculateSnapshot(CarbonImmutable::parse('2026-10-03'))['all_age']['ratings']);
    }

    public function test_legacy_team_prefix_normalization_never_hides_modified_or_unknown_format_declarations(): void
    {
        $data = $this->fixture();
        $data['home']->update(['category_event_id' => null]);
        foreach (['60ft court U13 Boys', '36 foot court U13 Boys', '1/2 court U13 Boys', 'blue ball U13 Boys'] as $name) {
            $data['home']->update(['name' => $name]);
            $row = $this->globalRows($data)[0];
            $this->assertSame('Team source declares a modified ball or court format', (new AllAgeMatchFormatResolver)->resolve($data['event']->id, $row)['format_reason']);
        }
        $data['home']->regions()->update(['region_name' => 'Green Point Schools']);
        $data['home']->update(['name' => 'Green Point U13 Boys']);
        $row = $this->globalRows($data)[0];
        $this->assertNull($row['reason']);
        $this->assertNull((new AllAgeMatchFormatResolver)->resolve($data['event']->id, $row)['format_reason']);
    }

    public function test_competing_identity_identifiers_and_duplicate_roster_membership_are_rejected(): void
    {
        $data = $this->fixture();
        $imported = NoProfileTeamPlayer::create(['team_id' => $data['home']->id, 'rank' => 2, 'name' => 'Synthetic', 'surname' => 'Import', 'pay_status' => 0]);
        $data['row']->update(['team1_no_profile_id' => $imported->id]);
        $this->assertSame('Team match side has missing or competing identity identifiers', $this->globalRows($data)[0]['reason']);
        $data['row']->update(['team1_no_profile_id' => null]);
        TeamPlayer::create(['team_id' => $data['home']->id, 'player_id' => $data['first']->id, 'rank' => 2]);
        $this->assertSame('Shared team player roster membership is ambiguous', $this->globalRows($data)[0]['reason']);
    }

    public function test_foreign_source_and_foreign_draw_category_cannot_enter_global_evidence(): void
    {
        $data = $this->fixture();
        $foreign = CategoryEvent::factory()->create();
        $data['away']->update(['category_event_id' => $foreign->id]);
        $this->assertNotNull($this->globalRows($data)[0]['reason']);
        $data['away']->update(['category_event_id' => $data['awayField']->id]);
        $data['draw']->update(['category_event_id' => $foreign->id]);
        $this->assertSame('Team draw category belongs to another event', $this->globalRows($data)[0]['reason']);
    }

    public function test_doubles_partial_scores_unpublished_ties_and_future_dates_remain_excluded(): void
    {
        $data = $this->fixture();
        $data['fixture']->update(['fixture_type' => 2, 'rubber_code' => 'doubles']);
        $this->assertSame([], $this->globalRows($data));
        $data['fixture']->update(['fixture_type' => 1, 'rubber_code' => 'singles']);
        $data['fixture']->teamResults()->update(['team1_score' => 6, 'team2_score' => 5]);
        $this->assertNotNull($this->globalRows($data)[0]['reason']);
        $data['fixture']->teamResults()->update(['team1_score' => 6, 'team2_score' => 2]);
        $data['tie']->update(['published_at' => null]);
        $this->assertSame([], $this->globalRows($data));
        $data['tie']->update(['published_at' => now()]);
        $data['event']->update(['end_date' => '2026-10-12']);
        $data['fixture']->update(['scheduled_at' => '2026-10-10 09:00:00']);
        $this->assertSame('Team match schedule is later than the snapshot date', $this->globalRows($data)[0]['reason']);
    }

    public function test_all_age_holdout_uses_the_same_age_neutral_reader_and_standard_format_gate(): void
    {
        $boys = $this->fixture('U13 Boys A', 'U13 Boys A');
        $girls = $this->fixture('U18 Girls A', 'U18 Girls A');
        $this->fixture(first: $boys['first'], second: $girls['first']);
        $holdout = $this->fixture(first: $boys['first'], second: $girls['first']);
        $holdout['event']->update(['start_date' => '2026-10-10', 'end_date' => '2026-10-11']);
        $holdout['fixture']->update(['scheduled_at' => '2026-10-10 09:00:00']);
        $report = app(PlayerAbilityValidationService::class)->run($holdout['event']->fresh(), false, true);
        $this->assertSame(1, $report['eligible_matches']);
        $this->assertSame(1, $report['current']['evaluated_matches']);
        $this->assertSame(1, $report['coverage']);
        DB::table('event_venue_courts')->where('event_id', $holdout['event']->id)->update(['ball_type' => null]);
        $report = app(PlayerAbilityValidationService::class)->run($holdout['event']->fresh(), false, true);
        $this->assertSame(0, $report['eligible_matches']);
    }
}
