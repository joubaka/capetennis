<?php

namespace Tests\Feature;

use App\Models\{Player, User};
use App\Services\Performance\{PlayerAbilitySnapshotStore, PlayerMatchHistoricalContext, PlayerMatchProgress, PlayerMatchProgressStore, PlayerRatingAccess, PlayerSharedAbilityService, TeamSheetTennisEvidence};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{DB, File};
use Tests\TestCase;

class PlayerMatchHistoricalContextTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;
    private string $date = '2026-11-20';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(\Carbon\CarbonImmutable::parse($this->date.' 10:00', 'Africa/Johannesburg'));
        $this->directory = storage_path('framework/testing/graph-context-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($this->directory);
        $this->mock(TeamSheetTennisEvidence::class)->shouldReceive('fingerprint')->andReturn('external-unchanged');
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldReceive('policyHash')->andReturn(str_repeat('c', 64));
        $this->mock(\App\Services\Performance\PlayerMatchProgressCalculator::class)->shouldNotReceive('latest', 'pair');
        $this->mock(\App\Services\Performance\PlayerMatchInfluenceCalculator::class)->shouldNotReceive('calculate');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function fixtures(): array
    {
        $event = \App\Models\Event::factory()->create(['start_date' => '2026-11-01', 'end_date' => '2026-11-02', 'published' => true]);
        $category = \App\Models\Category::create(['name' => 'Green U11 Boys']);
        $field = \App\Models\CategoryEvent::create(['event_id' => $event->id, 'category_id' => $category->id]);
        $draw = \App\Models\Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $field->id, 'published' => true]);
        $player = Player::factory()->create();
        $registration = \App\Models\Registration::factory()->create(); $registration->players()->attach($player->id);
        $fixture = DB::table('fixtures')->insertGetId(['draw_id' => $draw->id, 'registration1_id' => $registration->id]);
        return compact('event', 'category', 'field', 'draw', 'player', 'registration', 'fixture');
    }

    private function store(): PlayerMatchProgressStore
    {
        return new class($this->directory) extends PlayerMatchProgressStore {
            public function __construct(private string $directory) {}
            public function path(int $player): string { return $this->directory.'/player-'.$player.'.json'; }
        };
    }

    private function artifact(PlayerMatchProgressStore $store, int $player, int $version = 6): array
    {
        $saved = ['version' => $version, 'basis' => 'scheduled_match_order', 'player_id' => $player,
            'policy_hash' => $store->policyHash($version), 'source_fingerprint' => str_repeat('a', 64),
            'snapshot_version' => str_repeat('b', 64), 'as_of' => $this->date, 'built_at' => now()->toIso8601String(),
            'current_score' => 65.8, 'own_catalog' => [], 'rows' => [[
                'source_id' => 'match:fixture', 'date' => $this->date, 'time' => $this->date.' 09:00:00',
                'opponent' => 'Fictional Saved Opponent', 'score' => '6-3', 'won' => true,
                'before_score' => 64.6, 'after_score' => 65.8, 'delta' => 1.2, 'reason' => null,
                'explanation' => ['network_before' => 0, 'network_after' => 1, 'own_before' => 0, 'own_after' => 1, 'opponents_before' => 0, 'opponents_after' => 1],
                'evidence_refs' => [['source_id' => 'match:fixture', 'opponent_id' => 999999, 'before' => false, 'after' => true, 'opponent_before' => null, 'opponent_after' => null]],
                'omitted_matches' => 0,
            ]]];
        if ($version === 6) $saved['historical_context'] = app(PlayerMatchHistoricalContext::class)->capture($this->date);
        return $saved;
    }

    private function reader(PlayerMatchProgressStore $store, Player $player): PlayerMatchProgress
    {
        $this->app->instance(PlayerMatchProgressStore::class, $store);
        app(PlayerAbilitySnapshotStore::class)->shouldReceive('current')->andReturn(['reason' => null, 'snapshot_as_of' => $this->date,
            'snapshot_version' => str_repeat('b', 64), 'all_age' => ['ratings' => [$player->id => ['score' => 65.8]]]]);
        $this->mock(PlayerSharedAbilityService::class)->shouldReceive('fingerprint')->andReturn(str_repeat('e', 64));
        $this->mock(PlayerRatingAccess::class)->shouldReceive('allows')->andReturnTrue();
        app(PlayerRatingAccess::class)->shouldReceive('allowsMarketing')->andReturnFalse();
        $this->mock(\App\Services\Performance\PlayerRatingTopTen::class)->shouldReceive('forMatchPlayers')->andReturn([]);
        return $this->app->build(PlayerMatchProgress::class);
    }

    public function test_future_event_signup_and_unreferenced_player_do_not_discard_current_v6_graph_but_historical_identity_does(): void
    {
        $f = $this->fixtures(); $store = $this->store();
        $saved = $this->artifact($store, $f['player']->id); $store->replace($f['player']->id, $saved, str_repeat('a', 64));
        $before = File::get($store->path($f['player']->id));
        $future = \App\Models\Event::factory()->create(['start_date' => '2027-01-01', 'end_date' => '2027-01-02', 'eventType' => $f['event']->eventType]);
        $field = \App\Models\CategoryEvent::create(['event_id' => $future->id, 'category_id' => $f['category']->id]);
        $newPlayer = Player::factory()->create();
        $registration = \App\Models\Registration::factory()->create(); $registration->players()->attach($newPlayer->id);
        DB::table('category_event_registrations')->insert(['category_event_id' => $field->id, 'registration_id' => $registration->id, 'status' => 'active']);
        $service = $this->reader($store, $f['player']); $user = User::factory()->create();
        $this->assertNull($service->forPlayer($user, $f['player'])['reason']);
        $this->assertSame($saved['historical_context'], app(PlayerMatchHistoricalContext::class)->capture($this->date));
        $f['player']->update(['name' => 'Corrected identity']);
        $this->assertNotNull($service->forPlayer($user, $f['player'])['reason']);
        $this->assertSame($before, File::get($store->path($f['player']->id)));
    }

    public function test_raw_historical_scores_new_backdated_roots_publication_ball_and_chronology_all_change_the_context(): void
    {
        $f = $this->fixtures(); $context = app(PlayerMatchHistoricalContext::class);
        $changes = [
            fn () => DB::table('fixture_results')->insert(['fixture_id' => $f['fixture'], 'set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 3]),
            fn () => DB::table('fixture_results')->where('fixture_id', $f['fixture'])->update(['registration1_score' => 7]),
            fn () => $f['event']->update(['published' => false]),
            fn () => $f['category']->update(['name' => 'Orange U11 Boys']),
            fn () => DB::table('order_of_plays')->insert(['draw_id' => $f['draw']->id, 'fixture_id' => $f['fixture'], 'venue_id' => 1, 'time' => '2026-11-01 09:00:00']),
            fn () => DB::table('order_of_plays')->where('fixture_id', $f['fixture'])->update(['time' => '2026-11-01 10:00:00']),
            fn () => \App\Models\Event::factory()->create(['start_date' => '2026-10-01', 'end_date' => '2026-10-02', 'eventType' => $f['event']->eventType]),
        ];
        foreach ($changes as $change) {
            $before = $context->capture($this->date); $change();
            $this->assertNotSame($before, $context->capture($this->date));
        }
        // A different network match matters even though it is not a displayed own match.
        $networkFixture = DB::table('team_fixtures')->insertGetId(['draw_id' => $f['draw']->id, 'match_nr' => 1]);
        $before = $context->capture($this->date);
        DB::table('team_fixture_results')->insert(['team_fixture_id' => $networkFixture, 'team1_score' => 6, 'team2_score' => 2, 'set_nr' => 1]);
        $this->assertNotSame($before, $context->capture($this->date));
        $before = $context->capture($this->date);
        DB::table('team_fixture_results')->where('team_fixture_id', $networkFixture)->update(['team2_score' => 4]);
        $this->assertNotSame($before, $context->capture($this->date));
    }

    public function test_legacy_v5_is_readable_but_keeps_strict_global_guard_and_is_never_upgraded_on_read(): void
    {
        $f = $this->fixtures(); $store = $this->store(); $saved = $this->artifact($store, $f['player']->id, 5);
        $store->replace($f['player']->id, $saved, str_repeat('a', 64)); $before = File::get($store->path($f['player']->id));
        $this->assertSame(5, $store->read($f['player']->id)['version']);
        $this->assertNotNull($this->reader($store, $f['player'])->forPlayer(User::factory()->create(), $f['player'])['reason']);
        $this->assertSame($before, File::get($store->path($f['player']->id)));
        $this->assertArrayNotHasKey('historical_context', $store->read($f['player']->id));
    }

    public function test_actual_v6_file_and_reader_keep_profile_and_rating_available_then_both_withhold_corrected_history(): void
    {
        $this->withoutMiddleware([\App\Http\Middleware\EnsureAgreementAccepted::class, \App\Http\Middleware\EnsurePlayerProfileUpdated::class]);
        $f = $this->fixtures(); $user = User::factory()->create(); $f['player']->update(['userId' => $user->id]);
        $this->actingAs($user);
        $store = $this->store(); $saved = $this->artifact($store, $f['player']->id);
        $store->replace($f['player']->id, $saved, str_repeat('a', 64)); $file = File::get($store->path($f['player']->id));
        $future = \App\Models\Event::factory()->create(['start_date' => '2027-01-01', 'end_date' => '2027-01-02']);
        $futureField = \App\Models\CategoryEvent::create(['event_id' => $future->id, 'category_id' => $f['category']->id]);
        $futurePlayer = Player::factory()->create(); $futureRegistration = \App\Models\Registration::factory()->create();
        $futureRegistration->players()->attach($futurePlayer->id);
        DB::table('category_event_registrations')->insert(['category_event_id' => $futureField->id, 'registration_id' => $futureRegistration->id, 'status' => 'active']);
        $this->app->instance(PlayerMatchProgress::class, $this->reader($store, $f['player']));
        app(\App\Services\Performance\PlayerRatingTopTen::class)->shouldReceive('build')->andReturn(['reason' => 'Comparison fixture.']);
        $this->mock(\App\Services\Performance\PlayerRatingHistoryService::class)->shouldReceive('forPlayer')->andReturn(['reason' => 'Monthly history pending.']);
        app(\App\Services\Performance\PlayerRatingHistoryService::class)->shouldReceive('recordSource')->andReturn(['reason' => 'Record pending.']);
        $profile = route('my.tennis', ['player' => $f['player']->id, 'tab' => 'profile']);
        $rating = route('my.tennis.rating', $f['player']);
        $before = $this->get($profile)->assertOk()->assertSee('data-profile-match-pair', false)->viewData('profileMatchProgress');
        $this->assertSame($before, $this->get($rating)->assertOk()->viewData('matchProgress'));
        DB::table('fixture_results')->insert(['fixture_id' => $f['fixture'], 'set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 3]);
        $pending = $this->get($profile)->assertOk()->assertSee('data-profile-match-pending', false)->viewData('profileMatchProgress');
        $this->assertNotNull($pending['reason']);
        $this->assertSame($pending, $this->get($rating, ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->viewData('matchProgress'));
        $this->assertSame($file, File::get($store->path($f['player']->id)));
    }

    public function test_context_bounds_and_missing_or_malformed_v6_manifest_fail_closed(): void
    {
        $f = $this->fixtures();
        $bounded = new class extends PlayerMatchHistoricalContext { protected const MAX_ROWS = 0; };
        try { $bounded->capture($this->date); $this->fail('Expected row bound.'); } catch (\OverflowException) { $this->assertTrue(true); }
        $bytes = new class extends PlayerMatchHistoricalContext { protected const MAX_BYTES = 1; };
        try { $bytes->capture($this->date); $this->fail('Expected byte bound.'); } catch (\OverflowException) { $this->assertTrue(true); }
        $store = $this->store(); $saved = $this->artifact($store, $f['player']->id);
        unset($saved['historical_context']); File::put($store->path($f['player']->id), json_encode($saved));
        $this->assertNull($store->read($f['player']->id));
        $saved['historical_context'] = ['version' => 1, 'as_of' => $this->date, 'fingerprint' => 'forged'];
        File::put($store->path($f['player']->id), json_encode($saved)); $this->assertNull($store->read($f['player']->id));
    }

    public function test_empty_legacy_roster_candidates_membership_changes_and_future_event_backdating_are_detected(): void
    {
        $f = $this->fixtures(); $context = app(PlayerMatchHistoricalContext::class);
        $region = DB::table('team_regions')->insertGetId(['region_name' => 'Fictional region']);
        DB::table('event_regions')->insert(['event_id' => $f['event']->id, 'region_id' => $region]);
        $before = $context->capture($this->date);
        $team = \App\Models\Team::factory()->create(['year' => 2026, 'region_id' => $region, 'category_event_id' => null]);
        $this->assertNotSame($before, $context->capture($this->date));
        $before = $context->capture($this->date);
        DB::table('team_players')->insert(['team_id' => $team->id, 'player_id' => $f['player']->id, 'rank' => 1]);
        $this->assertNotSame($before, $context->capture($this->date));
        $before = $context->capture($this->date);
        $other = Player::factory()->create(); $f['registration']->players()->attach($other->id);
        $this->assertNotSame($before, $context->capture($this->date));
        $future = \App\Models\Event::factory()->create(['start_date' => '2027-01-01', 'end_date' => '2027-01-02']);
        $before = $context->capture($this->date);
        $future->update(['start_date' => '2026-10-01', 'end_date' => '2026-10-02']);
        $this->assertNotSame($before, $context->capture($this->date));
    }
}
