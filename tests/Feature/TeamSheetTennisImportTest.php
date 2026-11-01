<?php

namespace Tests\Feature;

use App\Models\{Player, User};
use App\Services\Performance\{AllAgeRatingModel, PlayerAbilitySnapshotStore, PlayerRatingHistoryService, PlayerRatingHistoryStore, PlayerSharedAbilityService, TeamSheetTennisEvidence, TeamSheetTennisImportService};
use Carbon\{Carbon, CarbonImmutable};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, File, Http};
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamSheetTennisImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-01 12:00:00'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-01 12:00:00'));
        config(['teamsheet.url' => 'https://teamsheet.example', 'teamsheet.token' => 'synthetic-read-only-token']);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function row(string $id = 'ts-challenge-1', array $changes = []): array
    {
        $type = str_contains($id, 'tournament') ? 'tournament' : (str_contains($id, 'fixture') ? 'league' : 'ladder');
        $row = array_replace_recursive(['source' => 'teamsheet', 'source_match_id' => $id,
            'competition' => ['type' => $type, 'id' => 7], 'discipline' => 'singles',
            'played_at' => '2026-10-01T09:00:00+02:00', 'date_precision' => 'actual_time', 'status' => 'verified',
            'side1' => ['teamsheet_player_id' => 101], 'side2' => ['teamsheet_player_id' => 102], 'winner_player_id' => 101,
            'sets' => [['side1_games' => 6, 'side2_games' => 2]]], $changes);
        unset($row['source_version']);
        return $row + ['source_version' => $this->hash($row)];
    }

    private function hash(array $value): string { return hash('sha256', json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)); }

    private function snapshot(array $rows, array $meta = []): array
    {
        usort($rows, fn ($a, $b) => strcmp($a['source_match_id'], $b['source_match_id']));
        return ['data' => $rows, 'meta' => array_replace(['snapshot' => true, 'snapshot_hash' => $this->hash($rows), 'total' => count($rows), 'current_page' => 1, 'last_page' => 1], $meta)];
    }

    private function sync(array $rows): void
    {
        $this->fakeResponse($this->snapshot($rows));
        $this->assertSame(count($rows), app(TeamSheetTennisImportService::class)->sync());
    }

    private function fakeResponse(array|string $payload, int $status = 200): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake(['teamsheet.example/*' => Http::response($payload, $status)]);
    }

    private function admin(): User
    {
        Role::findOrCreate('super-user', 'web');
        $user = User::factory()->create(); $user->assignRole('super-user'); return $user;
    }

    private function connect(?User $actor = null): array
    {
        $players = Player::factory()->count(2)->create(); $actor ??= $this->admin();
        foreach ([101, 102] as $i => $source) app(TeamSheetTennisImportService::class)->link($actor, $source, $players[$i]->id, 'Reviewed authoritative school identity evidence.');
        return $players->all();
    }

    public function test_restricted_sync_authenticates_is_idempotent_and_retracts_missing_rows(): void
    {
        $rows = [$this->row(), $this->row('ts-fixture-rubber-1'), $this->row('ts-tournament-rubber-1')];
        $this->sync($rows); $this->sync($rows);
        $this->assertDatabaseCount('teamsheet_tennis_matches', 3);
        $this->assertDatabaseCount('teamsheet_tennis_competitions', 3);
        Http::assertSent(fn ($request) => $request->url() === 'https://teamsheet.example/api/v1/integrations/cape-tennis/results?page=1&per_page=100'
            && $request->hasHeader('Authorization', 'Bearer synthetic-read-only-token'));
        $this->sync([]);
        $this->assertSame(3, DB::table('teamsheet_tennis_matches')->where('retracted', true)->count());
    }

    public function test_failed_partial_changing_or_tampered_snapshot_does_not_replace_prior_results(): void
    {
        $this->sync([$this->row()]);
        foreach ([
            $this->snapshot([], ['total' => 2]),
            $this->snapshot([$this->row()], ['snapshot_hash' => str_repeat('a', 64)]),
            $this->snapshot([$this->row(), $this->row()]),
            $this->snapshot([$this->row('ts-fixture-rubber-1', ['discipline' => 'doubles'])]),
            $this->snapshot([$this->row('ts-fixture-rubber-1', ['source' => 'cape_tennis'])]),
            $this->snapshot([$this->row('ts-fixture-rubber-1', ['winner_player_id' => 102])]),
        ] as $payload) {
            $this->fakeResponse($payload);
            try { app(TeamSheetTennisImportService::class)->sync(); $this->fail('Invalid snapshot accepted.'); }
            catch (ValidationException) { $this->assertDatabaseCount('teamsheet_tennis_matches', 1); $this->assertDatabaseHas('teamsheet_tennis_matches', ['source_match_id' => 'ts-challenge-1', 'retracted' => false]); }
        }
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fakeSequence()->push($this->snapshot([$this->row()], ['total' => 2, 'last_page' => 2]))
            ->push($this->snapshot([$this->row('ts-challenge-2')], ['total' => 2, 'current_page' => 2, 'last_page' => 2]));
        try { app(TeamSheetTennisImportService::class)->sync(); $this->fail('Changing pages accepted.'); }
        catch (ValidationException) { $this->assertDatabaseCount('teamsheet_tennis_matches', 1); }
    }

    public function test_http_error_redirect_and_missing_token_retain_previous_data_without_leaking_response(): void
    {
        $this->sync([$this->row()]);
        foreach ([401, 302, 500] as $status) {
            $this->fakeResponse('synthetic provider body must stay private', $status);
            try { app(TeamSheetTennisImportService::class)->sync(); $this->fail('HTTP failure accepted.'); }
            catch (ValidationException $error) { $this->assertStringNotContainsString('synthetic provider body', $error->getMessage()); }
        }
        config(['teamsheet.token' => null]);
        $this->assertNull(app(TeamSheetTennisImportService::class)->origin());
        $this->assertFalse(app(TeamSheetTennisImportService::class)->fresh());
        $this->assertDatabaseCount('teamsheet_tennis_matches', 1);
    }

    public function test_only_reviewed_player_links_and_actual_dates_contribute_with_namespaced_provenance(): void
    {
        $this->sync([$this->row('ts-fixture-rubber-1', ['date_precision' => 'scheduled_date'])]);
        $this->assertSame([], app(TeamSheetTennisEvidence::class)->matches(CarbonImmutable::today()));
        $admin = $this->admin(); $players = $this->connect($admin);
        $this->assertSame([], app(TeamSheetTennisEvidence::class)->matches(CarbonImmutable::today()));
        $row = DB::table('teamsheet_tennis_matches')->first();
        app(TeamSheetTennisImportService::class)->reviewDate($admin, $row->id, $row->source_version, '2026-10-02', 'Signed scorecard confirms actual match date.');
        $snapshot = app(PlayerSharedAbilityService::class)->calculateSnapshot(CarbonImmutable::today());
        $this->assertSame(['teamsheet:ts-fixture-rubber-1'], $snapshot['source_matches']);
        $this->assertSame([], $snapshot['source_events']);
        $this->assertCount(2, $snapshot['all_age']['ratings']);
        $this->assertSame(1, $snapshot['all_age']['players'][$players[0]->id]['played']);
        $this->assertSame(0, $snapshot['all_age']['ratings'][$players[0]->id]['proxy_dated_matches']);
        $this->assertLessThan(0, $snapshot['all_age']['history_evidence']['matches'][0]['event_id']);
        $this->assertSame([], $snapshot['cohorts']);
        $this->sync([$this->row('ts-fixture-rubber-1', ['date_precision' => 'scheduled_date', 'sets' => [['side1_games' => 6, 'side2_games' => 3]]])]);
        $this->assertSame([], app(TeamSheetTennisEvidence::class)->matches(CarbonImmutable::today()), 'Date review must not transfer to a changed result revision.');
    }

    public function test_stale_review_and_unauthorised_identity_changes_are_rejected(): void
    {
        $this->sync([$this->row()]); $admin = $this->admin(); $players = $this->connect($admin); $row = DB::table('teamsheet_tennis_matches')->first();
        try { app(TeamSheetTennisImportService::class)->reviewDate($admin, $row->id, str_repeat('b', 64), '2026-10-01', 'Evidence refers to a superseded source revision.'); $this->fail('Stale review accepted.'); }
        catch (ValidationException) { $this->assertNull(DB::table('teamsheet_tennis_matches')->value('reviewed_version')); }
        try { app(TeamSheetTennisImportService::class)->link($admin, 101, $players[1]->id, 'Duplicate identity should not be connected.'); $this->fail('Duplicate link accepted.'); }
        catch (ValidationException) { $this->assertDatabaseCount('teamsheet_tennis_player_links', 2); }
        $ordinary = User::factory()->create(); $this->actingAs($ordinary);
        $this->get(route('backend.integrations.teamsheet.tennis.index'))->assertForbidden();
        $this->post(route('backend.integrations.teamsheet.tennis.sync'))->assertForbidden();
        $this->post(route('backend.integrations.teamsheet.tennis.link'), ['teamsheet_player_id' => 999, 'player_id' => $players[0]->id, 'reason' => 'An ordinary user cannot connect identities.'])->assertForbidden();
        $this->delete(route('backend.integrations.teamsheet.tennis.unlink', 1), ['reason' => 'An ordinary user cannot remove identities.'])->assertForbidden();
        $this->post(route('backend.integrations.teamsheet.tennis.date', $row->id), ['source_version' => $row->source_version, 'played_on' => '2026-10-01', 'reason' => 'An ordinary user cannot review actual dates.'])->assertForbidden();
    }

    public function test_saved_estimates_and_history_fail_closed_after_unlink_retraction_or_stale_feed(): void
    {
        $this->sync([$this->row()]); $admin = $this->admin(); $this->connect($admin);
        $ability = app(PlayerSharedAbilityService::class); $snapshot = $ability->calculateSnapshot(CarbonImmutable::today());
        $store = new PlayerAbilitySnapshotStore; $manifest = $store->manifest($snapshot);
        $store->replace($snapshot, CarbonImmutable::today(), $ability->fingerprint(), $manifest);
        $this->assertNull((new PlayerAbilitySnapshotStore)->current()['reason']);
        $before = app(TeamSheetTennisEvidence::class)->fingerprint(); $this->sync([$this->row()]);
        $this->assertSame($before, app(TeamSheetTennisEvidence::class)->fingerprint(), 'No-op sync must extend freshness without invalidating saved estimates.');
        $this->assertTrue($store->published($manifest));
        DB::table('teamsheet_tennis_feeds')->update(['last_success_at' => now()->subHours(25)]);
        $this->assertFalse($store->published($manifest));
        $this->assertNotNull((new PlayerAbilitySnapshotStore)->current()['reason']);
        $this->sync([$this->row()]);
        app(TeamSheetTennisImportService::class)->unlink($admin, DB::table('teamsheet_tennis_player_links')->value('id'), 'Identity evidence was corrected during source review.');
        $this->assertFalse($store->published($manifest));
        $this->sync([]);
        $this->assertSame([], app(TeamSheetTennisEvidence::class)->matches(CarbonImmutable::today()));
    }

    public function test_duplicate_cape_and_cross_source_rows_are_held_and_competition_budget_is_not_per_match(): void
    {
        $this->sync([$this->row(), $this->row('ts-fixture-rubber-1'), $this->row('ts-challenge-2', ['played_at' => '2026-10-02T09:00:00+02:00'])]);
        $players = $this->connect(); $evidence = app(TeamSheetTennisEvidence::class);
        $rows = $evidence->matches(CarbonImmutable::today()); $this->assertCount(2, $rows);
        $this->assertSame($rows[0]['event_id'], $rows[1]['event_id']);
        $key = $evidence->duplicateKey($players[0]->id, $players[1]->id, '2026-10-01', '6-2');
        $this->assertCount(1, $evidence->matches(CarbonImmutable::today(), [$key => true]));
        $this->assertSame($key, $evidence->duplicateKey($players[1]->id, $players[0]->id, '2026-10-01', '2-6'));
    }

    public function test_background_history_preserves_external_sources_and_restricts_raw_details_to_super_users(): void
    {
        $this->sync([$this->row(), $this->row('ts-challenge-2', ['played_at' => '2026-09-15T09:00:00+02:00'])]);
        $admin = $this->admin(); $players = $this->connect($admin);
        $path = storage_path('framework/testing/teamsheet-history-'.bin2hex(random_bytes(5)).'.json');
        $store = new class($path) extends PlayerRatingHistoryStore { public function __construct(private string $testPath) {} public function path(): string { return $this->testPath; } };
        try {
            $ability = app(PlayerSharedAbilityService::class); $history = new PlayerRatingHistoryService($ability, $store);
            $saved = $history->calculate(CarbonImmutable::today()); $store->replace($saved, $ability->fingerprint());
            $registry = collect($store->read()['evidence']);
            $this->assertCount(4, $registry);
            $this->assertCount(2, $registry->where('cohort', AllAgeRatingModel::COHORT));
            $this->assertCount(2, $registry->where('cohort', \App\Services\Performance\BallRatingModel::COHORTS['standard']));
            $this->assertCount(4, $registry->unique(fn ($row) => $row['cohort'].'|'.$row['source_id']));
            $this->actingAs(User::factory()->create());
            $result = $history->forPlayer($players[0], ['scope' => 'all']);
            $this->assertNotEmpty($result['points']); $this->assertSame([], $result['evidence']['rows']);
            $this->assertSame(2, $result['evidence']['restricted']); $this->assertSame(2, $result['evidence']['matches']);
            $this->actingAs($admin);
            $result = $history->forPlayer($players[0], ['scope' => 'all']); $this->assertCount(2, $result['evidence']['rows']);
            $this->assertStringStartsWith('teamsheet:', $result['evidence']['rows'][0]['source_id']);
            $this->assertLessThan(0, $result['evidence']['rows'][0]['event_id']);
            DB::table('teamsheet_tennis_feeds')->update(['last_success_at' => now()->subHours(25)]);
            $this->assertSame([], $history->forPlayer($players[0])['series']);
        } finally { File::delete($path); }
    }

    public function test_super_user_management_page_renders_fictional_source_status_and_can_capture_for_qa(): void
    {
        $this->sync([$this->row(), $this->row('ts-tournament-rubber-2', ['date_precision' => 'recorded_time', 'side2' => ['teamsheet_player_id' => 103]])]);
        $admin = $this->admin(); $this->connect($admin); $this->actingAs($admin);
        $response = $this->get(route('backend.integrations.teamsheet.tennis.index'))->assertOk()
            ->assertSee('verified-restricted-v1')->assertSee('Both player identities need reviewed Cape Tennis links')
            ->assertDontSee('synthetic-read-only-token');
        if (getenv('CT_TEAMSHEET_QA_HTML') === '1') {
            File::ensureDirectoryExists(storage_path('framework/testing'));
            File::put(storage_path('framework/testing/teamsheet-tennis-admin.html'), $response->getContent());
        }
    }

    public function test_cumulative_retained_history_limit_rejects_growth_atomically(): void
    {
        $this->sync([$this->row()]);
        $template = (array) DB::table('teamsheet_tennis_matches')->first(); unset($template['id']);
        for ($offset = 2; $offset <= TeamSheetTennisImportService::CAP; $offset += 200) {
            $rows = [];
            for ($id = $offset; $id < min($offset + 200, TeamSheetTennisImportService::CAP + 1); $id++) $rows[] = array_replace($template, ['source_match_id' => 'ts-challenge-'.$id, 'retracted' => true]);
            DB::table('teamsheet_tennis_matches')->insert($rows);
        }
        $this->fakeResponse($this->snapshot([$this->row('ts-challenge-10001')]));
        try { app(TeamSheetTennisImportService::class)->sync(); $this->fail('Unbounded retained history accepted.'); }
        catch (ValidationException) {
            $this->assertDatabaseCount('teamsheet_tennis_matches', TeamSheetTennisImportService::CAP);
            $this->assertDatabaseHas('teamsheet_tennis_matches', ['source_match_id' => 'ts-challenge-1', 'retracted' => false]);
            $this->assertDatabaseMissing('teamsheet_tennis_matches', ['source_match_id' => 'ts-challenge-10001']);
        }
    }

    public function test_complete_pagination_verifies_all_contents_and_racing_apply_cannot_overwrite_newer_state(): void
    {
        $rows = [];
        for ($id = 1; $id <= 101; $id++) $rows[] = $this->row('ts-challenge-'.$id);
        usort($rows, fn ($a, $b) => strcmp($a['source_match_id'], $b['source_match_id']));
        $meta = ['snapshot_hash' => $this->hash($rows), 'total' => 101, 'last_page' => 2];
        Http::fakeSequence()->push($this->snapshot(array_slice($rows, 0, 100), $meta))
            ->push($this->snapshot(array_slice($rows, 100), $meta + ['current_page' => 2]));
        $this->assertSame(101, app(TeamSheetTennisImportService::class)->sync());
        $this->assertDatabaseCount('teamsheet_tennis_matches', 101);
        $prior = DB::table('teamsheet_tennis_feeds')->first();
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake(function () {
            DB::table('teamsheet_tennis_feeds')->update(['version' => DB::raw('version + 1')]);
            return Http::response($this->snapshot([]));
        });
        try { app(TeamSheetTennisImportService::class)->sync(); $this->fail('An earlier captured generation overwrote a newer sync.'); }
        catch (ValidationException) {
            $this->assertDatabaseCount('teamsheet_tennis_matches', 101);
            $this->assertSame(0, DB::table('teamsheet_tennis_matches')->where('retracted', true)->count());
            $this->assertSame($prior->snapshot_hash, DB::table('teamsheet_tennis_feeds')->value('snapshot_hash'));
        }
    }

    public function test_a_match_already_in_cape_tennis_is_counted_once_and_reported_as_external_duplicate(): void
    {
        $this->sync([$this->row()]); $players = $this->connect();
        $event = \App\Models\Event::factory()->create(['start_date' => '2026-10-01', 'end_date' => '2026-11-02', 'published' => true]);
        $category = \App\Models\Category::factory()->create(['name' => 'U13 Boys']);
        $field = \App\Models\CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => $category->id]);
        $registrations = [];
        foreach ($players as $player) {
            $registration = \App\Models\Registration::factory()->create(); $registration->players()->attach($player);
            \App\Models\CategoryEventRegistration::factory()->create(['category_event_id' => $field->id, 'registration_id' => $registration->id]);
            $registrations[] = $registration;
        }
        $draw = \App\Models\Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $field->id, 'drawName' => 'U13 Boys', 'published' => true]);
        \App\Models\DrawSetting::create(['draw_id' => $draw->id, 'workflow' => 'round_robin', 'score_format' => 'one_full_set', 'num_sets' => 1]);
        $fixture = \App\Models\Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => $registrations[0]->id, 'registration2_id' => $registrations[1]->id, 'match_status' => 1, 'winner_registration' => $registrations[0]->id]);
        \App\Models\FixtureResult::create(['fixture_id' => $fixture->id, 'set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2]);
        $venue = \App\Models\Venue::forceCreate(['name' => 'Synthetic source duplicate court']);
        \App\Models\OrderOfPlay::create(['draw_id' => $draw->id, 'fixture_id' => $fixture->id, 'venue_id' => $venue->id, 'court' => '1', 'time' => '2026-10-01 09:00:00']);
        $snapshot = app(PlayerSharedAbilityService::class)->calculateSnapshot(CarbonImmutable::today());
        $this->assertSame(['match:'.$fixture->id], $snapshot['source_matches']);
        $this->assertSame(1, $snapshot['all_age']['players'][$players[0]->id]['played']);
        $this->assertSame('Possible duplicate across result sources; source correction required', $snapshot['teamsheet_held']['teamsheet:ts-challenge-1']);
    }
}
