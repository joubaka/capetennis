<?php

namespace Tests\Feature;

use App\Models\{Category, CategoryEvent, Draw, Event, HistoricalIdentityProposal, NoProfileTeamPlayer, Player, Team, TeamFixture, TeamPlayer, TeamRegion, TeamTie, User};
use App\Services\Performance\{HistoricalIdentityReviewService, PlayerAbilityRefreshState, PlayerPerformanceHistoryService, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HistoricalIdentityReviewTest extends TestCase
{
    use RefreshDatabase;

    public function scenario(): array
    {
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $event = Event::factory()->create(['published' => true, 'results_published' => false, 'start_date' => '2026-03-01', 'end_date' => '2026-03-02']);
        $category = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => 'U12 Girls'])->id]);
        $region1 = TeamRegion::create(['region_name' => 'Region A', 'short_name' => 'A']); $region2 = TeamRegion::create(['region_name' => 'Region B', 'short_name' => 'B']);
        $event->regions()->attach([$region1->id, $region2->id]);
        $home = Team::factory()->create(['category_event_id' => $category->id, 'region_id' => $region1->id, 'year' => 2026]);
        $away = Team::factory()->create(['category_event_id' => $category->id, 'region_id' => $region2->id, 'year' => 2026]);
        $first = Player::factory()->female()->create(['dateOfBirth' => '2014-04-02']);
        $second = Player::factory()->female()->create(['dateOfBirth' => '2014-02-01']);
        TeamPlayer::create(['team_id' => $home->id, 'player_id' => 0, 'rank' => 1]);
        TeamPlayer::create(['team_id' => $away->id, 'player_id' => $second->id, 'rank' => 1]);
        $imported = NoProfileTeamPlayer::create(['team_id' => $home->id, 'name' => $first->name, 'surname' => $first->surname, 'rank' => 1, 'player_profile' => null, 'pay_status' => 1]);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $category->id, 'published' => true, 'drawName' => 'U12 Girls Singles']);
        $tie = TeamTie::factory()->published()->create(['draw_id' => $draw->id, 'home_team_id' => $home->id, 'away_team_id' => $away->id]);
        $fixture = TeamFixture::create(['draw_id' => $draw->id, 'team_tie_id' => $tie->id, 'fixture_type' => 1, 'numSets' => 3, 'match_status' => 1, 'round_nr' => 1, 'match_nr' => 1]);
        $row = $fixture->fixturePlayers()->create(['slot_no' => 1, 'team1_no_profile_id' => $imported->id, 'team2_id' => $second->id]);
        foreach ([1, 2] as $set) { $fixture->teamResults()->create(['set_nr' => $set, 'team1_score' => 6, 'team2_score' => 1]); }
        return compact('event', 'home', 'away', 'first', 'second', 'imported', 'fixture', 'row', 'draw', 'category');
    }

    private function input(array $s): array
    {
        $item = app(HistoricalIdentityReviewService::class)->listings($s['event'])['items'][0];
        return ['fixture_player_id' => $s['row']->id, 'side' => 1, 'source_team_id' => $s['home']->id, 'suggested_player_id' => $s['first']->id,
            'source_fingerprint' => $item['fingerprint'], 'evidence' => 'Original signed team sheet and scorecard independently confirm the participant, region and fixture side.',
            'reference' => 'Original tournament team sheet, page 2 and signed scorecard match 1', 'reason' => 'A later evidence-backed identity resolution is proposed for review only.'];
    }

    private function sources(): array
    {
        $result = [];
        foreach (['events', 'players', 'teams', 'team_players', 'no_profile_team_players', 'team_fixture_players', 'team_fixture_results', 'team_fixtures', 'player_ability_snapshots', 'player_ability_refresh_state'] as $table) {
            $result[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }
        return $result;
    }

    public function test_form_fingerprint_saves_proposal_and_preview_uses_canonical_reader_without_source_writes(): void
    {
        $s = $this->scenario(); $service = app(HistoricalIdentityReviewService::class);
        $before = $this->sources();
        $response = $this->post(route('backend.player-performance.identity-proposals', $s['event']), $this->input($s));
        $response->assertSessionHasNoErrors()->assertRedirect();
        $proposal = HistoricalIdentityProposal::firstOrFail();
        $this->assertSame((int) auth()->id(), (int) $proposal->created_by);
        $ordinary = iterator_to_array(app(PlayerPerformanceHistoryService::class)->teamMatches($s['event'], null, CarbonImmutable::parse('2026-10-18')));
        $this->assertNotNull($ordinary[0]['reason']);
        $preview = $service->preview($s['event'], [$proposal->id]);
        $this->assertTrue($preview['preview_only']);
        $this->assertSame(['team-match:'.$s['fixture']->id], $preview['added_matches']);
        $this->assertNull($preview['source_checks'][0]['reason']);
        $this->assertCount(2, $preview['cohorts']['u12 girls']['rows']);
        $this->assertSame($before, $this->sources());
        $normal = app(PlayerSharedAbilityService::class)->calculateSnapshot(CarbonImmutable::parse('2026-10-18'));
        $this->assertNotContains('team-match:'.$s['fixture']->id, $normal['source_matches']);
    }

    public function test_preview_http_renders_private_counterfactual_and_escaped_evidence(): void
    {
        $s = $this->scenario(); $input = $this->input($s); $input['evidence'] .= ' <script>alert(1)</script>';
        $proposal = app(HistoricalIdentityReviewService::class)->propose($s['event'], $input);
        $this->get(route('backend.player-performance.identity-review', $s['event']))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
        $this->post(route('backend.player-performance.identity-preview', $s['event']), ['proposals' => [$proposal->id]])->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertSee('Hypothetical review only');
    }

    public function test_guests_and_other_roles_cannot_read_save_or_preview(): void
    {
        $s = $this->scenario(); auth()->logout();
        $base = route('backend.player-performance.identity-review', $s['event']);
        $this->getJson($base)->assertUnauthorized();
        $this->postJson(route('backend.player-performance.identity-proposals', $s['event']), [])->assertUnauthorized();
        $this->postJson(route('backend.player-performance.identity-preview', $s['event']), [])->assertUnauthorized();
        Role::findOrCreate('admin', 'web'); $this->actingAs(User::factory()->create()->assignRole('admin'));
        $this->getJson($base)->assertForbidden();
        $this->postJson(route('backend.player-performance.identity-proposals', $s['event']), [])->assertForbidden();
        $this->postJson(route('backend.player-performance.identity-preview', $s['event']), [])->assertForbidden();
    }

    public function test_over_age_gender_conflict_and_cross_event_side_are_rejected(): void
    {
        $s = $this->scenario(); $input = $this->input($s); $url = route('backend.player-performance.identity-proposals', $s['event']);
        $s['first']->update(['dateOfBirth' => '2004-01-01']);
        $this->postJson($url, $input)->assertUnprocessable()->assertJsonValidationErrors('proposal');
        $s['first']->update(['dateOfBirth' => '2014-01-01', 'gender' => 1]);
        $this->postJson($url, $input)->assertUnprocessable()->assertJsonValidationErrors('proposal');
        $s['first']->update(['gender' => 2]);
        $other = Event::factory()->create();
        $this->postJson(route('backend.player-performance.identity-proposals', $other), $input)->assertUnprocessable();
        $this->postJson($url, [...$input, 'side' => 2])->assertUnprocessable();
        $this->postJson($url, [...$input, 'source_team_id' => $s['away']->id])->assertUnprocessable();
        $this->assertDatabaseCount('historical_identity_proposals', 0);
    }

    public function test_changed_source_and_profile_invalidate_proposal_without_mutating_records(): void
    {
        $s = $this->scenario(); $service = app(HistoricalIdentityReviewService::class);
        $proposal = $service->propose($s['event'], $this->input($s));
        $s['first']->update(['surname' => 'Changed']);
        $this->postJson(route('backend.player-performance.identity-preview', $s['event']), ['proposals' => [$proposal->id]])->assertUnprocessable()->assertJsonValidationErrors('proposal');
        $proposal = $service->propose($s['event'], $this->input($s));
        DB::table('team_fixture_results')->where('team_fixture_id', $s['fixture']->id)->update(['team2_score' => 2]);
        $this->postJson(route('backend.player-performance.identity-preview', $s['event']), ['proposals' => [$proposal->id]])->assertUnprocessable()->assertJsonValidationErrors('proposal');
    }

    public function test_conflicting_links_duplicate_ranks_and_duplicate_revisions_are_rejected(): void
    {
        $s = $this->scenario(); $service = app(HistoricalIdentityReviewService::class); $input = $this->input($s);
        $s['imported']->update(['player_profile' => $s['second']->id]);
        $this->postJson(route('backend.player-performance.identity-proposals', $s['event']), $input)->assertUnprocessable();
        $s['imported']->update(['player_profile' => null]);
        $proposal1 = $service->propose($s['event'], $this->input($s));
        $proposal2 = $service->propose($s['event'], $this->input($s));
        $this->postJson(route('backend.player-performance.identity-preview', $s['event']), ['proposals' => [$proposal1->id, $proposal2->id]])->assertUnprocessable();
        NoProfileTeamPlayer::create(['team_id' => $s['home']->id, 'name' => 'Duplicate', 'surname' => 'Slot', 'rank' => 2, 'player_profile' => $s['first']->id, 'pay_status' => 1]);
        $this->postJson(route('backend.player-performance.identity-proposals', $s['event']), $this->input($s))->assertUnprocessable();
    }

    public function test_foreign_proposal_is_rejected_and_existing_evidence_is_append_only(): void
    {
        $s = $this->scenario(); $proposal = app(HistoricalIdentityReviewService::class)->propose($s['event'], $this->input($s));
        $other = Event::factory()->create();
        $this->postJson(route('backend.player-performance.identity-preview', $other), ['proposals' => [$proposal->id]])->assertUnprocessable();
        $this->expectException(\LogicException::class);
        $proposal->update(['evidence' => 'Replacement']);
    }

    public function test_unpublished_sources_remain_excluded_in_preview(): void
    {
        $s = $this->scenario(); $s['event']->update(['published' => false]);
        $service = app(HistoricalIdentityReviewService::class); $proposal = $service->propose($s['event'], $this->input($s));
        $preview = $service->preview($s['event'], [$proposal->id]);
        $this->assertSame([], $preview['added_matches']);
        $this->assertFalse((bool) $s['event']->fresh()->published);
        $this->assertFalse((bool) $s['event']->fresh()->results_published);
    }
}
