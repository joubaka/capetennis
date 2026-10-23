<?php

namespace Tests\Feature;

use App\Models\{Category, CategoryEvent, Draw, Event, HistoricalIdentityProposal, NoProfileTeamPlayer, Player, Team, TeamFixture, TeamPlayer, TeamRegion, TeamTie, User};
use App\Services\Performance\{HistoricalIdentityReviewService, PlayerPerformanceHistoryService, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HistoricalIdentityReviewTest extends TestCase
{
    // Counterfactual previews use a real read-only transaction, so cannot nest in RefreshDatabase.
    use DatabaseTruncation;

    protected function afterTruncatingDatabase(): void
    {
        // Restore this migration-seeded singleton after truncation between scenarios.
        DB::table('player_ability_refresh_state')->insert(['id' => 1]);
    }

    protected function tearDown(): void
    {
        try { $this->truncateTablesForAllConnections(); }
        finally { parent::tearDown(); }
    }

    public static function tearDownAfterClass(): void
    {
        // Subsequent RefreshDatabase classes need the pristine migration-seeded baseline.
        \Illuminate\Foundation\Testing\RefreshDatabaseState::$migrated = false;
        parent::tearDownAfterClass();
    }

    private function recoveryManifest(array $s): array
    {
        $review = app(HistoricalIdentityReviewService::class);
        $identity = $review->validateIdentity($s['event'], $s['row']->id, 1, $s['home']->id, $s['first']->id);
        return ['event_id' => $s['event']->id, 'fixture_ids' => [$s['fixture']->id], 'expected_additional_matches' => 1,
            'reviewed_at' => '2026-03-03 10:00:00+02:00', 'documents' => [['reference' => 'Original fixture sheet page 2', 'sha256' => str_repeat('a', 64)]],
            'entries' => [[...$this->input($s), 'event_id' => $s['event']->id, 'fixture_id' => $s['fixture']->id,
                'target_fingerprint' => $review->targetFingerprint($identity['player']), 'cohort' => $identity['cohort']]]];
    }

    public function test_reviewed_recovery_is_exact_idempotent_and_does_not_mutate_sources_or_snapshots(): void
    {
        $s = $this->scenario(); $manifest = $this->recoveryManifest($s);
        $service = app(\App\Services\Performance\HistoricalIdentityRecoveryService::class);
        $before = $this->sources();
        $this->assertTrue($service->import($s['event'], auth()->user(), $manifest)['preview']);
        $this->assertDatabaseCount('historical_identity_recoveries', 0);
        $this->assertSame($before, $this->sources());
        $this->assertSame(1, $service->import($s['event'], auth()->user(), $manifest, true)['created']);
        $this->assertSame(0, $service->import($s['event'], auth()->user(), $manifest, true)['created']);
        $this->assertDatabaseCount('historical_identity_recoveries', 1);
        // Only the refresh work marker may change; canonical sources remain immutable.
        $after = $this->sources(); unset($before['player_ability_refresh_state'], $after['player_ability_refresh_state']);
        $this->assertSame($before, $after);
        $matches = iterator_to_array(app(PlayerPerformanceHistoryService::class)->teamMatches($s['event'], null));
        $this->assertNull($matches[0]['reason']);
        $this->assertSame($s['first']->id, $matches[0]['player1_id']);
        $this->assertNull($s['row']->fresh()->participant_snapshot);
        $this->assertFalse((bool) $s['event']->fresh()->results_published);
    }

    public function test_recovery_does_not_extend_to_other_matches_for_the_same_imported_identity(): void
    {
        $s = $this->scenario(); $manifest = $this->recoveryManifest($s);
        $other = $s['fixture']->replicate(); $other->match_nr = 2; $other->save();
        $other->fixturePlayers()->create(['slot_no' => 1, 'team1_no_profile_id' => $s['imported']->id, 'team2_id' => $s['second']->id]);
        foreach ([1, 2] as $set) { $other->teamResults()->create(['set_nr' => $set, 'team1_score' => 6, 'team2_score' => 1]); }
        app(\App\Services\Performance\HistoricalIdentityRecoveryService::class)->import($s['event'], auth()->user(), $manifest, true);
        $matches = collect(iterator_to_array(app(PlayerPerformanceHistoryService::class)->teamMatches($s['event'], null)))->keyBy('fixture_id');
        $this->assertNull($matches[$s['fixture']->id]['reason']);
        $this->assertNotNull($matches[$other->id]['reason']);
        $this->assertDatabaseCount('historical_identity_recoveries', 1);
    }

    public function test_saved_recovery_publication_context_uses_batched_reads_and_detects_roster_additions(): void
    {
        $s = $this->scenario(); $service = app(\App\Services\Performance\HistoricalIdentityRecoveryService::class);
        $service->import($s['event'], auth()->user(), $this->recoveryManifest($s), true);
        $record = (array) DB::table('historical_identity_recoveries')->first();
        // Repeated references must not trigger repeated validation queries on saved reads.
        $recoveries = array_fill(0, 160, $record);
        $manifest = ['historical_identity_recoveries' => $recoveries, 'historical_recovery_sources' => $service->context($recoveries)];
        $store = app(\App\Services\Performance\PlayerAbilitySnapshotStore::class);
        $this->assertFalse($store->published(['historical_identity_recoveries' => $recoveries]), 'Missing recovery context must fail closed.');
        DB::enableQueryLog(); DB::flushQueryLog();
        $this->assertTrue($store->published($manifest));
        $queries = count(DB::getQueryLog()); DB::disableQueryLog();
        $this->assertLessThanOrEqual(24, $queries, 'Saved recovery checks must use bounded batched reads.');
        NoProfileTeamPlayer::create(['team_id' => $s['home']->id, 'name' => 'Additional', 'surname' => 'Member', 'rank' => 2, 'pay_status' => 1]);
        $this->assertFalse($store->published($manifest), 'Added imported roster membership must withhold saved estimates.');
    }

    public function test_saved_recovery_with_empty_roster_withholds_when_the_first_conflicting_member_is_added(): void
    {
        $s = $this->scenario();
        TeamPlayer::where('team_id', $s['home']->id)->delete();
        $service = app(\App\Services\Performance\HistoricalIdentityRecoveryService::class);
        $service->import($s['event'], auth()->user(), $this->recoveryManifest($s), true);
        $recoveries = DB::table('historical_identity_recoveries')->get()->map(fn ($row) => (array) $row)->all();
        $manifest = ['historical_identity_recoveries' => $recoveries, 'historical_recovery_sources' => $service->context($recoveries)];
        $this->assertSame([], $manifest['historical_recovery_sources']['team_players']);
        $store = app(\App\Services\Performance\PlayerAbilitySnapshotStore::class);
        $this->assertTrue($store->published($manifest));
        TeamPlayer::create(['team_id' => $s['home']->id, 'rank' => 1, 'player_id' => $s['second']->id]);
        $this->assertFalse($store->published($manifest));
        $this->assertNull($service->resolve($s['event'], $s['row'], 1));
    }

    public function test_saved_recovery_compares_numeric_strings_exactly_while_tolerating_object_key_order(): void
    {
        $s = $this->scenario();
        // The testing base schema stores gender as TINYINT. Use a VARCHAR identity
        // field to exercise the same numeric-string coercion with a real stored edit.
        $s['first']->update(['name' => '1']); $s['imported']->update(['name' => '1']);
        $service = app(\App\Services\Performance\HistoricalIdentityRecoveryService::class);
        $service->import($s['event'], auth()->user(), $this->recoveryManifest($s), true);
        $recoveries = DB::table('historical_identity_recoveries')->get()->map(fn ($row) => (array) $row)->all();
        $context = $service->context($recoveries);
        // MySQL JSON may reorder object keys, including nested row fields.
        foreach ($context as &$rows) { foreach ($rows as &$row) { krsort($row); } unset($row); } unset($rows);
        krsort($context);
        $manifest = ['historical_identity_recoveries' => $recoveries, 'historical_recovery_sources' => $context];
        $store = app(\App\Services\Performance\PlayerAbilitySnapshotStore::class);
        $this->assertTrue($store->published($manifest));
        $this->assertNotNull($service->resolve($s['event'], $s['row'], 1));
        $before = app(PlayerSharedAbilityService::class)->fingerprint();
        // Simulate an external stored edit; Eloquent considers numeric strings equivalent.
        DB::table('players')->where('id', $s['first']->id)->update(['name' => '01']);
        $this->assertSame('01', DB::table('players')->where('id', $s['first']->id)->value('name'));
        $this->assertFalse($store->published($manifest));
        $this->assertNull($service->resolve($s['event'], $s['row'], 1));
        $this->assertNotSame($before, app(PlayerSharedAbilityService::class)->fingerprint());
    }

    public function test_recovery_rejects_unauthorized_actor_cross_event_and_changed_score_atomically(): void
    {
        $s = $this->scenario(); $manifest = $this->recoveryManifest($s);
        $service = app(\App\Services\Performance\HistoricalIdentityRecoveryService::class);
        try { $service->import($s['event'], User::factory()->create(), $manifest, true); $this->fail('Unauthorized actor accepted'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) { $this->assertSame(403, $error->getStatusCode()); }
        foreach ([['event_id' => Event::factory()->create()->id], ['fixture_id' => $s['fixture']->id + 1]] as $change) {
            $bad = $manifest; $bad['entries'][0] = [...$bad['entries'][0], ...$change];
            try { $service->import($s['event'], auth()->user(), $bad, true); $this->fail('Invalid scope accepted'); }
            catch (ValidationException) { $this->assertDatabaseCount('historical_identity_recoveries', 0); }
        }
        DB::table('team_fixture_results')->where('team_fixture_id', $s['fixture']->id)->update(['team2_score' => 2]);
        $this->expectException(ValidationException::class);
        $service->import($s['event'], auth()->user(), $manifest, true);
    }

    public function test_recovery_fails_closed_on_target_score_and_publication_changes_and_saved_snapshot_withholds(): void
    {
        $s = $this->scenario(); $service = app(\App\Services\Performance\HistoricalIdentityRecoveryService::class);
        $service->import($s['event'], auth()->user(), $this->recoveryManifest($s), true);
        $shared = app(PlayerSharedAbilityService::class);
        $snapshot = $shared->calculateSnapshot(CarbonImmutable::parse('2026-10-22'));
        $store = app(\App\Services\Performance\PlayerAbilitySnapshotStore::class);
        $manifest = $store->manifest($snapshot);
        $this->assertTrue($store->published($manifest));
        $old = $shared->fingerprint();
        $s['first']->update(['dateOfBirth' => '2014-04-03']);
        $this->assertFalse($store->published($manifest));
        $this->assertNotSame($old, $shared->fingerprint());
        $matches = iterator_to_array(app(PlayerPerformanceHistoryService::class)->teamMatches($s['event'], null));
        $this->assertNotNull($matches[0]['reason']);
        $s['first']->update(['dateOfBirth' => '2014-04-02']);
        DB::table('team_fixture_results')->where('team_fixture_id', $s['fixture']->id)->update(['team2_score' => 2]);
        $this->assertFalse($store->published($manifest));
        $s['event']->update(['published' => false]);
        $this->assertSame([], iterator_to_array(app(PlayerPerformanceHistoryService::class)->teamMatches($s['event'], null)));
    }

    public function test_review_table_indexes_have_mysql_compatible_names_and_expected_lookup_columns(): void
    {
        $indexes = \Illuminate\Support\Facades\Schema::getIndexes('historical_identity_proposals');
        foreach ($indexes as $index) {
            $this->assertLessThanOrEqual(64, strlen($index['name']));
        }
        $lookup = collect($indexes)->firstWhere('name', 'historical_identity_event_fixture_side_idx');
        $this->assertNotNull($lookup);
        $this->assertSame(['event_id', 'fixture_player_id', 'side'], $lookup['columns']);
    }

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
            $result[$table] = DB::table($table)->orderBy($table === 'player_ability_snapshots' ? 'snapshot_key' : 'id')->get()->toJson();
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

    public function test_conflicting_captured_history_cannot_be_overridden_by_a_proposal(): void
    {
        $s = $this->scenario(); $url = route('backend.player-performance.identity-proposals', $s['event']);
        foreach (['event_id' => 99999, 'source_team_id' => $s['away']->id, 'category_event_id' => 99999,
            'region_id' => 99999, 'imported_id' => 99999, 'rank' => 99, 'anchor_id' => 99999,
            'profile_id' => $s['first']->id, 'linked_profile_id' => $s['second']->id, 'anchor_type' => 'profile'] as $key => $value) {
            $s['row']->forceFill(['participant_snapshot' => [1 => [$key => $value]]])->save();
            $this->postJson($url, $this->input($s))->assertUnprocessable()->assertJsonValidationErrors('proposal');
        }
        $this->assertDatabaseCount('historical_identity_proposals', 0);
    }

    public function test_legacy_singles_reverse_labels_preserve_exact_age_and_gender_context(): void
    {
        $s = $this->scenario();
        // Legacy team draws may omit their category relation and encode it in the title.
        $s['draw']->update(['category_event_id' => null]);
        $service = app(HistoricalIdentityReviewService::class);
        foreach (['U/12 Girls Singles', 'U/12 Girls Singles reverse', 'U/12 Girls reverse Singles'] as $label) {
            $s['draw']->update(['drawName' => $label]);
            $proposal = $service->propose($s['event'], $this->input($s));
            $this->assertSame('u12 girls', $proposal->cohort);
        }
        foreach (['U/13 Girls Singles reverse', 'U/12 Boys Singles reverse', 'U/12 Girls Singles reverse other', 'U/12 Girls Doubles reverse'] as $label) {
            $s['draw']->update(['drawName' => $label]);
            $this->postJson(route('backend.player-performance.identity-proposals', $s['event']), $this->input($s))
                ->assertUnprocessable()->assertJsonValidationErrors('proposal');
        }
        $this->assertDatabaseCount('historical_identity_proposals', 3);
    }

    public function test_nonconverged_preview_is_withheld_and_preview_payload_cannot_be_saved(): void
    {
        $s = $this->scenario(); $service = app(HistoricalIdentityReviewService::class);
        $proposal = $service->propose($s['event'], $this->input($s));
        $this->partialMock(\App\Services\Performance\SharedAbilityModel::class)->shouldReceive('fit')->andReturn(['ratings' => [], 'components' => ['bad' => ['converged' => false]]]);
        $this->postJson(route('backend.player-performance.identity-preview', $s['event']), ['proposals' => [$proposal->id]])->assertUnprocessable()->assertJsonValidationErrors('proposal');
        $this->expectException(\LogicException::class);
        app(\App\Services\Performance\PlayerAbilitySnapshotStore::class)->replace(['preview_only' => true], CarbonImmutable::today(), '', []);
    }

    public function test_cutoff_preview_retains_full_cohorts_and_does_not_mix_disconnected_groups(): void
    {
        $s = $this->scenario();
        // Eleven other directly verified players give an existing 12-player comparison group.
        for ($rank = 2; $rank <= 12; $rank++) {
            $player = Player::factory()->female()->create(['dateOfBirth' => '2014-01-01']);
            TeamPlayer::create(['team_id' => $s['home']->id, 'player_id' => $player->id, 'rank' => $rank]);
            $fixture = TeamFixture::create(['draw_id' => $s['draw']->id, 'team_tie_id' => $s['fixture']->team_tie_id, 'fixture_type' => 1, 'numSets' => 3, 'match_status' => 1, 'round_nr' => 1, 'match_nr' => $rank]);
            $fixture->fixturePlayers()->create(['slot_no' => 1, 'team1_id' => $player->id, 'team2_id' => $s['second']->id]);
            foreach ([1, 2] as $set) { $fixture->teamResults()->create(['set_nr' => $set, 'team1_score' => 6, 'team2_score' => min(4, $rank % 5)]); }
        }
        $a = Player::factory()->female()->create(['dateOfBirth' => '2014-01-01']); $b = Player::factory()->female()->create(['dateOfBirth' => '2014-01-01']);
        TeamPlayer::create(['team_id' => $s['home']->id, 'player_id' => $a->id, 'rank' => 13]);
        TeamPlayer::create(['team_id' => $s['away']->id, 'player_id' => $b->id, 'rank' => 2]);
        $fixture = TeamFixture::create(['draw_id' => $s['draw']->id, 'team_tie_id' => $s['fixture']->team_tie_id, 'fixture_type' => 1, 'numSets' => 3, 'match_status' => 1, 'round_nr' => 1, 'match_nr' => 20]);
        $fixture->fixturePlayers()->create(['slot_no' => 1, 'team1_id' => $a->id, 'team2_id' => $b->id]);
        foreach ([1, 2] as $set) { $fixture->teamResults()->create(['set_nr' => $set, 'team1_score' => 6, 'team2_score' => 2]); }
        $service = app(HistoricalIdentityReviewService::class);
        $proposal = $service->propose($s['event'], $this->input($s));
        $preview = $service->preview($s['event'], [$proposal->id]);
        $data = $preview['cohorts']['u12 girls']; $rows = collect($data['rows'])->keyBy('id');
        $this->assertCount(15, $rows);
        $this->assertSame(2, $data['before_components']); $this->assertSame(2, $data['after_components']);
        $this->assertTrue($rows[$s['first']->id]['new_node']);
        $this->assertTrue($rows[$s['second']->id]['topology_changed']);
        $this->assertFalse($rows[$a->id]['topology_changed']);
        $this->assertSame($rows[$a->id]['before'], $rows[$a->id]['after']);
        $this->assertTrue($rows->contains(fn ($row) => $row['cutoff']));
        $this->assertTrue($rows->contains(fn ($row) => $row['top10_changed']));
    }
}
