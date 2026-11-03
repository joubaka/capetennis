<?php

namespace Tests\Feature;

use App\Models\{Category, CategoryEvent, CategoryEventRegistration, Draw, DrawSetting, Event, Fixture, FixtureResult, OrderOfPlay, Player, Registration, User, Venue};
use App\Services\Performance\{AllAgeRatingModel, BallRatingModel, PlayerAbilitySnapshotStore, PlayerPathwayService, PlayerRatingBadgeService, PlayerRatingHistoryService, PlayerRatingHistoryStore, PlayerSharedAbilityService, TeamSheetTennisImportService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, File, Http};
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BallRatingPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function finishField(string $name = 'Fictional final', string $category = 'U10 Boys A'): array
    {
        $event = Event::factory()->create(['name' => $name, 'start_date' => '2026-10-20', 'end_date' => '2026-10-20', 'published' => true, 'results_published' => true]);
        $field = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => $category])->id]);
        $players = Player::factory()->count(3)->create();
        foreach ($players as $index => $player) {
            $registration = Registration::factory()->create(); $registration->players()->attach($player);
            CategoryEventRegistration::factory()->create(['category_event_id' => $field->id, 'registration_id' => $registration->id]);
            \App\Models\CategoryResult::create(['event_id' => $event->id, 'category_id' => $field->category_id, 'registration_id' => $registration->id, 'position' => $index + 1]);
        }
        return [$event, $field, $players];
    }

    public function test_valid_finishes_have_ball_badges_and_history_without_played_matches(): void
    {
        [$event, $field, $players] = $this->finishField('Fictional Orange festival', 'Orange ball Boys A');
        $ability = app(PlayerSharedAbilityService::class); $sources = [];
        $snapshot = $ability->historySnapshot(CarbonImmutable::today(), $sources);
        $this->assertCount(3, $snapshot['ball_ratings']['orange']['ratings']);
        $this->assertSame([], $snapshot['all_age']['ratings']);
        $store = app(PlayerAbilitySnapshotStore::class);
        $store->replace($snapshot, CarbonImmutable::today(), $ability->fingerprint(), $store->manifest($snapshot));
        $badge = app(PlayerRatingBadgeService::class)->forPlayer($players[0]->id, $field);
        $this->assertSame('finish_only', $badge['evidence_basis']); $this->assertSame(0, $badge['played']);
        $this->assertStringContainsString('final placings', $badge['title']);
        $path = storage_path('framework/testing/finish-history-'.bin2hex(random_bytes(5)).'.json');
        $historyStore = new class($path) extends PlayerRatingHistoryStore { public function __construct(private string $testPath) {} public function path(): string { return $this->testPath; } };
        try {
            $history = new PlayerRatingHistoryService($ability, $historyStore);
            $saved = $history->calculate(CarbonImmutable::today()); $historyStore->replace($saved, $ability->fingerprint());
            $result = $history->forPlayer($players[0], ['cohort' => BallRatingModel::COHORTS['orange'], 'scope' => 'all']);
            $this->assertNotEmpty($result['points']); $this->assertSame(0, $result['evidence']['matches']);
            $this->assertSame(1, $result['evidence']['finishes']);
            $this->assertSame('finish', $result['evidence']['rows'][0]['kind']);
            $this->assertSame($badge['score'], $result['point']['score']);
            $this->assertSame('finish_only', $result['point']['evidence_basis']);
            $this->assertSame(1, $result['point']['finish_events']); $this->assertSame(1, $result['point']['inferred']);
        } finally { File::delete($path); }
    }

    public function test_finishing_context_rejects_changed_deleted_or_new_results_and_unpublication(): void
    {
        [$event] = $this->finishField(); $ability = app(PlayerSharedAbilityService::class); $store = app(PlayerAbilitySnapshotStore::class);
        $snapshot = $ability->calculateSnapshot(CarbonImmutable::today()); $manifest = $store->manifest($snapshot);
        $this->assertTrue($store->published($manifest));
        $row = \App\Models\CategoryResult::where('event_id', $event->id)->first();
        $row->update(['position' => 8]); $this->assertFalse($store->published($manifest));
        $row->update(['position' => 1]); $this->assertTrue($store->published($manifest));
        $copy = $row->replicate(); $copy->save(); $this->assertFalse($store->published($manifest)); $copy->delete();
        $event->update(['results_published' => false]); $this->assertFalse($store->published($manifest));
        $event->update(['results_published' => true]);
        $event->update(['eventType' => 3]); $this->assertFalse($store->published($manifest));
        $row->delete(); $this->assertFalse($store->published($manifest));
    }

    public function test_invalid_membership_or_ambiguous_or_unpublished_finishes_cannot_create_ratings(): void
    {
        [$event, $field] = $this->finishField();
        $membership = $field->categoryEventRegistrations()->first(); $membership->forceDelete();
        $this->assertSame([], app(PlayerSharedAbilityService::class)->calculateSnapshot(CarbonImmutable::today())['ball_ratings']['standard']['ratings']);
        [$second] = $this->finishField(); $second->update(['results_published' => false]);
        $this->assertSame([], app(PlayerSharedAbilityService::class)->calculateSnapshot(CarbonImmutable::today())['ball_ratings']['standard']['ratings']);
    }

    public function test_unpublished_draw_ball_allocations_and_added_draws_invalidate_finish_context(): void
    {
        [$event, $field] = $this->finishField();
        $ability = app(PlayerSharedAbilityService::class); $store = app(PlayerAbilitySnapshotStore::class);
        $snapshot = $ability->calculateSnapshot(CarbonImmutable::today()); $manifest = $store->manifest($snapshot);
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $field->id, 'published' => false]);
        $this->assertFalse($store->published($manifest));
        foreach (['1' => 'orange', '2' => 'yellow'] as $label => $ball) DB::table('event_venue_courts')->insert(['event_id' => $event->id, 'venue_id' => 1, 'label' => $label, 'ball_type' => $ball, 'active' => true]);
        $allocation = DB::table('draw_venue_court_allocations')->insertGetId(['draw_id' => $draw->id, 'venue_id' => 1, 'court_label' => '1']);
        $snapshot = $ability->calculateSnapshot(CarbonImmutable::today());
        $this->assertCount(3, $snapshot['ball_ratings']['orange']['ratings']);
        $manifest = $store->manifest($snapshot); $this->assertTrue($store->published($manifest));
        DB::table('draw_venue_court_allocations')->where('id', $allocation)->update(['court_label' => '2']);
        $this->assertFalse($store->published($manifest));
        $this->assertCount(3, $ability->calculateSnapshot(CarbonImmutable::today())['ball_ratings']['standard']['ratings']);
    }

    public function test_foreign_event_draw_cannot_change_a_finishing_fields_ball_level(): void
    {
        [$event, $field] = $this->finishField();
        $foreign = Event::factory()->create();
        $draw = Draw::factory()->create(['event_id' => $foreign->id, 'category_event_id' => $field->id, 'published' => false]);
        DB::table('event_venue_courts')->insert(['event_id' => $foreign->id, 'venue_id' => 1, 'label' => '1', 'ball_type' => 'orange', 'active' => true]);
        DB::table('draw_venue_court_allocations')->insert(['draw_id' => $draw->id, 'venue_id' => 1, 'court_label' => '1']);
        $this->assertSame('standard', app(\App\Services\Performance\BallRatingContext::class)->stage($field));
        $snapshot = app(PlayerSharedAbilityService::class)->calculateSnapshot(CarbonImmutable::today());
        $this->assertCount(3, $snapshot['ball_ratings']['standard']['ratings']);
        $this->assertSame([], $snapshot['ball_ratings']['orange']['ratings']);
    }

    public function test_explicit_category_ball_level_precedes_wilson_u9b_alias_for_finishes(): void
    {
        [$event, $field] = $this->finishField('Wilson Series', 'Red Ball U9B Boys A');
        $context = app(\App\Services\Performance\BallRatingContext::class);
        $this->assertSame('red', $context->stage($field));
        $field->category->update(['name' => 'U9B Boys A']); $this->assertSame('orange', $context->stage($field));
        $field->category->update(['name' => 'Green Ball U9B Boys A']); $this->assertSame('standard', $context->stage($field));
        $field->category->update(['name' => 'Red and Orange U9B Boys A']); $this->assertNull($context->stage($field));
    }

    public function test_partial_active_fields_are_withheld_and_withdrawn_or_pending_entries_are_not_missing_finishers(): void
    {
        [$event, $field, $players] = $this->finishField();
        $last = \App\Models\CategoryResult::where('event_id', $event->id)->where('position', 3)->first();
        $last->delete(); $ability = app(PlayerSharedAbilityService::class);
        $this->assertSame([], $ability->calculateSnapshot(CarbonImmutable::today())['ball_ratings']['standard']['ratings']);
        $membership = $field->categoryEventRegistrations()->where('registration_id', $last->registration_id)->first();
        $membership->update(['status' => 'pending_checkout']);
        $snapshot = $ability->calculateSnapshot(CarbonImmutable::today()); $this->assertCount(2, $snapshot['ball_ratings']['standard']['ratings']);
        $store = app(PlayerAbilitySnapshotStore::class); $pendingManifest = $store->manifest($snapshot);
        $membership->update(['status' => 'withdrawn']);
        $this->assertFalse($store->published($pendingManifest));
        $this->assertCount(2, $ability->calculateSnapshot(CarbonImmutable::today())['ball_ratings']['standard']['ratings']);
        $membership->update(['status' => 'active', 'withdrawn_at' => now()]);
        $this->assertCount(2, $ability->calculateSnapshot(CarbonImmutable::today())['ball_ratings']['standard']['ratings']);
        $withdrawnManifest = $store->manifest($ability->calculateSnapshot(CarbonImmutable::today()));
        $membership->update(['withdrawn_at' => null]); $this->assertFalse($store->published($withdrawnManifest));
        $membership->delete();
        $snapshot = $ability->calculateSnapshot(CarbonImmutable::today()); $this->assertCount(2, $snapshot['ball_ratings']['standard']['ratings']);
        $store = app(PlayerAbilitySnapshotStore::class); $manifest = $store->manifest($snapshot); $fingerprint = $ability->fingerprint();
        $membership->restore();
        $this->assertFalse($store->published($manifest)); $this->assertNotSame($fingerprint, $ability->fingerprint());
        $membership->delete(); $restoredResult = $last->replicate(); $restoredResult->save();
        $snapshot = $ability->calculateSnapshot(CarbonImmutable::today());
        $this->assertCount(3, $snapshot['ball_ratings']['standard']['ratings'], 'A ranked historical membership remains valid after withdrawal.');
    }

    public function test_reviewed_orange_event_categories_align_finishes_matches_and_display_context(): void
    {
        $players = Player::factory()->count(4)->create();
        $fixtures = [];
        foreach ([['Overberg Tennis Trials - Leg 2 2026', 'u/10 Girls - B division'],
            ['West Coast Tennis Trials 2026', 'U10 Boys B'],
            ['Cavaliers Junior Ceres Tournament 2026', 'u/9 Girls B - Division']] as $index => [$event, $category]) {
            [$finishEvent, $field] = $this->finishField($event, $category);
            $this->assertSame('orange', app(\App\Services\Performance\BallRatingContext::class)->stage($field));
            $fixture = $this->match($event, $players[0], $players[$index + 1], '2026-10-20');
            $fixture->draw->categoryEvent->category->update(['name' => $category]);
            $fixtures[] = $fixture;
        }
        $ability = app(PlayerSharedAbilityService::class);
        $snapshot = $ability->calculateSnapshot(CarbonImmutable::today());
        $this->assertSame([], $snapshot['ball_ratings']['standard']['ratings']);
        $this->assertCount(13, $snapshot['ball_ratings']['orange']['ratings']);
        $this->assertSame(3, $snapshot['ball_ratings']['orange']['ratings'][$players[0]->id]['played']);
        foreach ($fixtures as $fixture) $this->assertSame('orange', app(\App\Services\Performance\BallRatingContext::class)->stage($fixture->draw));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-01 12:00:00', 'Africa/Johannesburg'));
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
    }

    private function match(string $name, Player $first, Player $second, string $date, bool $firstWins = true): Fixture
    {
        $event = Event::factory()->create(['name' => $name, 'start_date' => $date, 'end_date' => $date, 'published' => true]);
        $field = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => 'U10 Boys A'])->id]);
        $registrations = [];
        foreach ([$first, $second] as $player) {
            $registration = Registration::factory()->create(); $registration->players()->attach($player);
            CategoryEventRegistration::factory()->create(['category_event_id' => $field->id, 'registration_id' => $registration->id]);
            $registrations[] = $registration;
        }
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $field->id, 'drawName' => 'U10 Boys A', 'published' => true]);
        DrawSetting::updateOrCreate(['draw_id' => $draw->id], ['workflow' => 'round_robin', 'num_sets' => 1, 'score_format' => 'best_of_1']);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => $registrations[0]->id,
            'registration2_id' => $registrations[1]->id, 'winner_registration' => $registrations[$firstWins ? 0 : 1]->id, 'match_status' => 0]);
        FixtureResult::create(['fixture_id' => $fixture->id, 'set_nr' => 1, 'registration1_score' => $firstWins ? 6 : 2, 'registration2_score' => $firstWins ? 2 : 6]);
        $venue = Venue::forceCreate(['name' => 'Fictional ball-rating court']);
        OrderOfPlay::create(['fixture_id' => $fixture->id, 'draw_id' => $draw->id, 'venue_id' => $venue->id, 'court' => 1, 'time' => $date.' 10:00:00']);
        return $fixture;
    }

    public function test_independent_saved_graphs_and_context_badges_never_borrow_another_level(): void
    {
        $players = Player::factory()->count(3)->create();
        $red = $this->match('Wilson Red event', $players[0], $players[1], '2026-09-10');
        $service = app(PlayerSharedAbilityService::class);
        $before = $service->calculateSnapshot(CarbonImmutable::today());
        $orange = $this->match('Orange ball event', $players[0], $players[1], '2026-10-10', false);
        $normal = $this->match('Fictional open event', $players[0], $players[2], '2026-10-20', false);
        $snapshot = $service->calculateSnapshot(CarbonImmutable::today());
        $this->assertSame($before['ball_ratings']['red'], $snapshot['ball_ratings']['red']);
        foreach (['red', 'orange', 'standard'] as $stage) $this->assertSame(1, $snapshot['ball_ratings'][$stage]['ratings'][$players[0]->id]['played']);
        $store = app(PlayerAbilitySnapshotStore::class);
        $store->replace($snapshot, CarbonImmutable::today(), $service->fingerprint(), $store->manifest($snapshot));
        $this->assertSame('red', app(PlayerRatingBadgeService::class)->forPlayer($players[0]->id, $red->draw)['ball_type']);
        $this->assertSame('orange', app(PlayerRatingBadgeService::class)->forPlayer($players[0]->id, $orange->draw)['ball_type']);
        $this->assertSame('standard', app(PlayerRatingBadgeService::class)->forPlayer($players[0]->id, $normal->draw)['ball_type']);
        $this->assertNull(app(PlayerRatingBadgeService::class)->forPlayer($players[2]->id, $red->draw));
        $this->assertArrayNotHasKey($players[2]->id, $service->ballBadgeSnapshot('red'));
        $this->assertGreaterThan($snapshot['ball_ratings']['orange']['ratings'][$players[0]->id]['score'], $snapshot['ball_ratings']['red']['ratings'][$players[0]->id]['score']);
        $missing = $snapshot; unset($missing['ball_ratings']);
        try { $store->replace($missing, CarbonImmutable::today(), $service->fingerprint(), $store->manifest($missing)); $this->fail('A combined score replaced independent ball graphs.'); }
        catch (\UnexpectedValueException) { $this->assertSame($snapshot['ball_ratings']['red']['ratings'][$players[0]->id]['score'], $store->current()['ball_ratings']['red']['ratings'][$players[0]->id]['score']); }
    }

    public function test_three_monthly_curves_have_own_evidence_and_no_private_source_disclosure(): void
    {
        $players = Player::factory()->count(4)->create();
        $red = $this->match('Fictional festival', $players[0], $players[1], '2026-09-10');
        $red->draw->categoryEvent->category->update(['name' => 'Red ball']);
        $this->match('Wilson Orange event', $players[0], $players[2], '2026-10-10', false);
        $this->match('Unstated ball event', $players[0], $players[3], '2026-10-20');
        $path = storage_path('framework/testing/ball-history-'.bin2hex(random_bytes(5)).'.json');
        $store = new class($path) extends PlayerRatingHistoryStore { public function __construct(private string $testPath) {} public function path(): string { return $this->testPath; } };
        try {
            $ability = app(PlayerSharedAbilityService::class); $history = new PlayerRatingHistoryService($ability, $store);
            $saved = $history->calculate(CarbonImmutable::today()); $store->replace($saved, $ability->fingerprint());
            foreach (BallRatingModel::COHORTS as $stage => $cohort) {
                $result = $history->forPlayer($players[0], ['cohort' => $cohort, 'scope' => 'all']);
                // A historical filter cannot replace the player's current saved level.
                $this->assertSame('standard', $result['ball_stage']); $this->assertSame(1, $result['evidence']['matches']);
                $this->assertSame('standard', $result['evidence']['rows'][0]['ball_type']);
                $this->assertNotEmpty($result['ball_series'][$cohort]);
                $this->assertNotNull($result['pathway_metrics']['stage_ratings'][$stage]['score']);
                $ownEvidence = array_values(array_filter($saved['evidence'], fn ($row) => $row['cohort'] === $cohort));
                $this->assertCount(1, $ownEvidence);
                $this->assertSame($stage, $ownEvidence[0]['ball_type']);
            }
            $this->assertSame('standard', $history->forPlayer($players[0])['ball_stage']);
            $registry = $saved['evidence'];
            $this->assertCount(3, array_filter($registry, fn ($row) => $row['cohort'] === AllAgeRatingModel::COHORT));
        } finally { File::delete($path); }
    }

    public function test_overall_rating_cannot_complete_a_stage_without_its_own_rating(): void
    {
        $player = Player::factory()->create();
        DB::table('player_pathway_profiles')->insert(['player_id' => $player->id, 'stage' => 'red', 'recorded_by_user_id' => auth()->id(), 'created_at' => now(), 'updated_at' => now()]);
        $metrics = ['player_id' => $player->id,
            'available' => true, 'score' => 99, 'comparable' => true, 'stale' => false, 'stage_events' => ['red' => 3, 'orange' => 3, 'standard' => 3],
            'stage_ratings' => []];
        $service = app(PlayerPathwayService::class);
        $summary = $service->summary($player, ['pathway_metrics' => $metrics]);
        $this->assertNull($summary['score']); $this->assertFalse($summary['ready']);
        $this->assertFalse($summary['stageProgress']['red']['met']); $this->assertNull($summary['stageProgress']['red']['score']);

        $metrics['stage_ratings']['orange'] = ['score' => 50, 'comparable' => true];
        $summary = $service->summary($player, ['pathway_metrics' => $metrics]);
        $this->assertSame('orange', $summary['current_stage']); $this->assertSame(50.0, $summary['score']);
        $this->assertFalse($summary['ready']); $this->assertFalse($summary['stageProgress']['orange']['met']);
        $this->assertFalse($summary['stageProgress']['red']['met']); $this->assertNull($summary['stageProgress']['red']['score']);

        $metrics['stage_ratings']['orange']['score'] = 99;
        $summary = $service->summary($player, ['pathway_metrics' => $metrics]);
        $this->assertSame(99.0, $summary['score']); $this->assertTrue($summary['ready']);
        $this->assertTrue($summary['stageProgress']['orange']['met']);
        $this->assertFalse($summary['stageProgress']['red']['met']);
    }

    public function test_invalid_import_ball_metadata_cannot_partially_change_staged_results(): void
    {
        config(['teamsheet.url' => 'https://teamsheet.example', 'teamsheet.token' => 'synthetic-read-only-token']);
        $row = ['source' => 'teamsheet', 'source_match_id' => 'ts-challenge-1', 'competition' => ['type' => 'ladder', 'id' => 7],
            'discipline' => 'singles', 'played_at' => '2026-10-01T09:00:00+02:00', 'date_precision' => 'actual_time', 'status' => 'verified',
            'side1' => ['teamsheet_player_id' => 101], 'side2' => ['teamsheet_player_id' => 102], 'winner_player_id' => 101,
            'sets' => [['side1_games' => 6, 'side2_games' => 2]], 'ball_type' => 'untrusted-red'];
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
        $row['source_version'] = hash('sha256', json_encode($row, $flags));
        Http::preventStrayRequests(); Http::fake(['teamsheet.example/*' => Http::response(['data' => [$row], 'meta' => ['snapshot' => true,
            'snapshot_hash' => hash('sha256', json_encode([$row], $flags)), 'current_page' => 1, 'last_page' => 1, 'total' => 1]])]);
        try { app(TeamSheetTennisImportService::class)->sync(); $this->fail('Invalid ball metadata accepted.'); }
        catch (ValidationException) { $this->assertDatabaseCount('teamsheet_tennis_matches', 0); $this->assertNull(DB::table('teamsheet_tennis_feeds')->value('last_success_at')); }
    }

    public function test_imported_explicit_ball_levels_are_separate_and_private_history_stays_redacted(): void
    {
        config(['teamsheet.url' => 'https://teamsheet.example', 'teamsheet.token' => 'synthetic-read-only-token']);
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
        $rows = [];
        foreach (['red', 'orange', 'green', null] as $index => $ball) {
            $row = ['source' => 'teamsheet', 'source_match_id' => 'ts-challenge-'.($index + 1), 'competition' => ['type' => 'ladder', 'id' => 7],
                'discipline' => 'singles', 'played_at' => '2026-10-'.(10 + $index).'T09:00:00+02:00', 'date_precision' => 'actual_time', 'status' => 'verified',
                'side1' => ['teamsheet_player_id' => 101], 'side2' => ['teamsheet_player_id' => 102], 'winner_player_id' => 101,
                'sets' => [['side1_games' => 6, 'side2_games' => 2]]];
            if ($ball !== null) $row['ball_type'] = $ball;
            $row['source_version'] = hash('sha256', json_encode($row, $flags)); $rows[] = $row;
        }
        Http::preventStrayRequests(); Http::fake(['teamsheet.example/*' => Http::response(['data' => $rows, 'meta' => ['snapshot' => true,
            'snapshot_hash' => hash('sha256', json_encode($rows, $flags)), 'current_page' => 1, 'last_page' => 1, 'total' => count($rows)]])]);
        $import = app(TeamSheetTennisImportService::class); $import->sync();
        $players = Player::factory()->count(2)->create();
        foreach ([101, 102] as $index => $id) $import->link(auth()->user(), $id, $players[$index]->id, 'Verified fictional source identity evidence.');
        $ability = app(PlayerSharedAbilityService::class); $snapshot = $ability->calculateSnapshot(CarbonImmutable::today());
        $this->assertSame(1, $snapshot['ball_ratings']['red']['ratings'][$players[0]->id]['played']);
        $this->assertSame(1, $snapshot['ball_ratings']['orange']['ratings'][$players[0]->id]['played']);
        $this->assertSame(2, $snapshot['ball_ratings']['standard']['ratings'][$players[0]->id]['played']);
        $path = storage_path('framework/testing/private-ball-history-'.bin2hex(random_bytes(5)).'.json');
        $store = new class($path) extends PlayerRatingHistoryStore { public function __construct(private string $testPath) {} public function path(): string { return $this->testPath; } };
        try {
            $history = new PlayerRatingHistoryService($ability, $store); $saved = $history->calculate(CarbonImmutable::today());
            $store->replace($saved, $ability->fingerprint()); $this->actingAs(User::factory()->create());
            foreach (['red' => 1, 'orange' => 1, 'standard' => 2] as $ball => $count) {
                $result = $history->forPlayer($players[0], ['cohort' => BallRatingModel::COHORTS[$ball], 'scope' => 'all']);
                $this->assertSame('standard', $result['ball_stage']);
                $this->assertNotEmpty($result['points']); $this->assertSame([], $result['evidence']['rows']);
                $this->assertSame(2, $result['evidence']['restricted']); $this->assertSame(2, $result['evidence']['matches']);
                $ownEvidence = array_values(array_filter($saved['evidence'], fn ($row) => $row['cohort'] === BallRatingModel::COHORTS[$ball]));
                $this->assertCount($count, $ownEvidence);
                foreach ($ownEvidence as $row) $this->assertSame($ball, $row['ball_type']);
                $this->assertSame(0, $result['pathway_metrics']['stage_events'][$ball], 'Private external competitions do not become published Cape events.');
            }
        } finally { File::delete($path); }
    }

    public function test_structured_red_categories_keep_rating_badge_history_and_stage_count_in_the_same_level(): void
    {
        $players = Player::factory()->count(4)->create();
        $fixtures = [];
        foreach (['Red Boys u/9', 'JTA u/9 Red Boys + Girls', 'Red ball'] as $index => $label) {
            $fixture = $this->match('Fictional junior festival', $players[0], $players[$index + 1], ['2026-09-10', '2026-10-10', '2026-10-20'][$index]);
            $fixture->draw->categoryEvent->category->update(['name' => $label]); $fixtures[] = $fixture;
        }
        $ability = app(PlayerSharedAbilityService::class); $sources = [];
        $snapshot = $ability->historySnapshot(CarbonImmutable::today(), $sources);
        $this->assertSame(3, $snapshot['ball_ratings']['red']['ratings'][$players[0]->id]['played']);
        $this->assertSame([], $snapshot['ball_ratings']['standard']['ratings']);
        $snapshotStore = app(PlayerAbilitySnapshotStore::class);
        $snapshotStore->replace($snapshot, CarbonImmutable::today(), $ability->fingerprint(), $snapshotStore->manifest($snapshot));
        foreach ($fixtures as $fixture) $this->assertSame('red', app(PlayerRatingBadgeService::class)->forPlayer($players[0]->id, $fixture->draw)['ball_type']);
        $builder = new \App\Services\Performance\PlayerRatingEvidenceBuilder; $builder->append($snapshot, $sources, 11);
        $rows = array_values(array_filter($builder->all(), fn ($row) => $row['cohort'] === AllAgeRatingModel::COHORT));
        $this->assertCount(3, $rows);
        foreach ($rows as $row) $this->assertSame('red', $row['rating_ball_type']);
        $saved = ['as_of' => CarbonImmutable::today()->toDateString(), 'publication_manifest' => $snapshotStore->manifest($snapshot), 'evidence' => $rows];
        $metrics = app(\App\Services\Performance\PlayerPathwayEvidence::class)->fromHistory($saved, $players[0], [['index' => 11,
            'date' => CarbonImmutable::today()->toDateString(), 'score' => 60, 'connection_status' => 'recent']], false);
        $this->assertSame(['red' => 3, 'orange' => 0, 'standard' => 0], $metrics['stage_events']);
    }
}
