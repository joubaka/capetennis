<?php

namespace Tests\Feature;

use App\Models\{Category, CategoryEvent, CategoryEventRegistration, CategoryResult, Draw, DrawSetting, Event, Fixture, FixtureResult, OrderOfPlay, Player, Registration, SiteSetting, User, Venue};
use App\Services\Performance\{BallRatingModel, PlayerEventRatingHistory, PlayerEventRatingHistoryLauncher, PlayerEventRatingHistoryRefresh, PlayerEventRatingHistoryStore, PlayerRatingAccess, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan, Cache, File, Gate};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerEventRatingHistoryTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;
    private PlayerEventRatingHistoryStore $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-01 12:00:00', 'Africa/Johannesburg'));
        config(['cache.stores.file' => ['driver' => 'array']]);
        $this->directory = storage_path('framework/testing/event-history-'.bin2hex(random_bytes(6)));
        $this->store = new class($this->directory) extends PlayerEventRatingHistoryStore {
            public function __construct(private string $directory) {}
            public function path(int $player): string { return $this->directory.'/player-'.$player.'.json'; }
        };
        $this->app->instance(PlayerEventRatingHistoryStore::class, $this->store);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    private function match(Player $player, Player $opponent, string $category, string $date): Event
    {
        $event = Event::factory()->create(['name' => 'Fictional junior event '.$date, 'start_date' => $date, 'end_date' => $date, 'published' => true]);
        $field = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => $category])->id]);
        $registrations = [];
        foreach ([$player, $opponent] as $participant) {
            $registration = Registration::factory()->create(); $registration->players()->attach($participant);
            CategoryEventRegistration::factory()->create(['category_event_id' => $field->id, 'registration_id' => $registration->id]);
            $registrations[] = $registration;
        }
        $draw = Draw::factory()->create(['event_id' => $event->id, 'category_event_id' => $field->id, 'drawName' => $category, 'published' => true]);
        DrawSetting::updateOrCreate(['draw_id' => $draw->id], ['workflow' => 'round_robin', 'num_sets' => 1, 'score_format' => 'best_of_1']);
        $fixture = Fixture::factory()->create(['draw_id' => $draw->id, 'registration1_id' => $registrations[0]->id,
            'registration2_id' => $registrations[1]->id, 'winner_registration' => $registrations[0]->id, 'match_status' => 0]);
        FixtureResult::create(['fixture_id' => $fixture->id, 'set_nr' => 1, 'registration1_score' => 6, 'registration2_score' => 2]);
        $venue = Venue::forceCreate(['name' => 'Fictional history court']);
        OrderOfPlay::create(['fixture_id' => $fixture->id, 'draw_id' => $draw->id, 'venue_id' => $venue->id, 'court' => 1, 'time' => $date.' 10:00:00']);
        return $event;
    }

    private function saved(int $player, string $fingerprint): array
    {
        $levels = [];
        foreach (BallRatingModel::LABELS as $stage => $label) $levels[$stage] = ['score' => $stage === 'red' ? 62.5 : null, 'local' => false,
            'confidence_label' => $stage === 'red' ? 'Low' : null, 'matches_in_event' => $stage === 'red' ? 1 : 0,
            'cape_events' => $stage === 'red' ? 1 : 0, 'reason' => 'Reconstructed using eligible results available by this date.'];
        return ['version' => PlayerEventRatingHistoryStore::VERSION, 'player_id' => $player, 'policy_hash' => $this->store->policyHash(),
            'source_fingerprint' => $fingerprint, 'as_of' => '2026-11-01', 'built_at' => '2026-11-01T12:00:00+02:00',
            'events' => [['event_id' => 12, 'event' => 'Fictional Red event', 'date' => '2023-05-01', 'entry_stages' => ['red'], 'finishes' => [], 'has_eligible_matches' => true, 'levels' => $levels]]];
    }

    public function test_reconstructs_older_events_with_three_independent_badges_and_no_future_leakage(): void
    {
        $players = Player::factory()->count(4)->create();
        $red = $this->match($players[0], $players[1], 'Red Boys u/9', '2023-04-01');
        $service = app(PlayerEventRatingHistory::class);
        $first = $service->build($players[0], CarbonImmutable::parse('2023-04-01'));
        $this->assertNotNull($first['events'][0]['levels']['red']['score']);
        $this->assertNull($first['events'][0]['levels']['orange']['score']);
        $this->match($players[0], $players[2], 'Orange Ball', '2024-06-08');
        $this->match($players[0], $players[3], 'U10 Boys', '2026-06-20');
        $saved = $service->build($players[0], CarbonImmutable::today());
        $this->assertCount(3, $saved['events']);
        $this->assertSame($red->id, $saved['events'][0]['event_id']);
        $this->assertSame($first['events'][0]['levels']['red']['score'], $saved['events'][0]['levels']['red']['score']);
        $this->assertNull($saved['events'][0]['levels']['orange']['score']);
        $this->assertNull($saved['events'][0]['levels']['standard']['score']);
        $this->assertSame(1, $saved['events'][0]['levels']['red']['matches_in_event']);
        $this->assertSame(1, $saved['events'][1]['levels']['orange']['matches_in_event']);
        $this->assertSame(1, $saved['events'][2]['levels']['standard']['matches_in_event']);
        $this->assertSame(1, $saved['events'][2]['levels']['red']['cape_events']);
        $this->assertNull($saved['events'][2]['levels']['red']['score'], 'A 2023 rating is not copied into a 2026 match window.');
        $ability = app(PlayerSharedAbilityService::class);
        $this->store->replace($players[0]->id, $saved, $ability->fingerprint());
        $this->assertSame('2023-04-01', $this->store->read($players[0]->id)['events'][0]['date']);
        $this->assertArrayNotHasKey('score', $saved['events'][0]);
        $this->assertArrayNotHasKey('opponent', $saved['events'][0]);
    }

    public function test_registration_only_events_have_no_invented_rating_and_private_or_future_events_are_excluded(): void
    {
        $player = Player::factory()->create();
        foreach ([['2023-05-01', true], ['2024-01-01', false], ['2027-01-01', true]] as [$date, $published]) {
            $event = Event::factory()->create(['name' => 'Fictional recorded entry', 'start_date' => $date, 'end_date' => $date, 'published' => $published]);
            $field = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => 'Red Boys u/9'])->id]);
            $registration = Registration::factory()->create(); $registration->players()->attach($player);
            CategoryEventRegistration::factory()->create(['category_event_id' => $field->id, 'registration_id' => $registration->id]);
        }
        $saved = app(PlayerEventRatingHistory::class)->build($player, CarbonImmutable::today());
        $this->assertCount(1, $saved['events']);
        $this->assertSame(['red'], $saved['events'][0]['entry_stages']);
        $this->assertFalse($saved['events'][0]['has_eligible_matches']);
        foreach ($saved['events'][0]['levels'] as $level) {
            $this->assertNull($level['score']); $this->assertSame(0, $level['matches_in_event']); $this->assertSame(0, $level['cape_events']);
        }
    }

    public function test_saved_reads_never_recalculate_and_source_changes_withhold_history(): void
    {
        $player = Player::factory()->create(); $fingerprint = str_repeat('a', 64); $saved = $this->saved($player->id, $fingerprint);
        $this->store->replace($player->id, $saved, $fingerprint);
        $ability = \Mockery::mock(PlayerSharedAbilityService::class);
        $ability->shouldReceive('fingerprint')->twice()->andReturn($fingerprint, str_repeat('b', 64));
        $ability->shouldNotReceive('historySnapshot')->shouldNotReceive('calculateSnapshot');
        $service = new PlayerEventRatingHistory($ability, $this->store);
        $this->assertCount(1, $service->forPlayer($player)['events']);
        $this->assertSame([], $service->forPlayer($player)['events']);
        $invalid = $saved; $invalid['events'][0]['levels']['orange']['score'] = 200;
        try { $this->store->replace($player->id, $invalid, $fingerprint); $this->fail('Invalid score replaced saved history.'); }
        catch (\RuntimeException) { $this->assertSame($saved, $this->store->read($player->id)); }
        $this->assertNull($this->store->read($player->id + 1));
    }

    public function test_published_finishing_position_is_shown_as_metadata_without_inventing_a_played_rating(): void
    {
        $player = Player::factory()->create();
        $event = Event::factory()->create(['name' => 'Fictional recorded Red event', 'start_date' => '2023-05-01', 'end_date' => '2023-05-01', 'published' => true, 'results_published' => false]);
        $field = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => 'Red Boys u/9'])->id]);
        $registration = Registration::factory()->create(); $registration->players()->attach($player);
        CategoryEventRegistration::factory()->create(['category_event_id' => $field->id, 'registration_id' => $registration->id]);
        CategoryResult::create(['event_id' => $event->id, 'category_id' => $field->category_id, 'registration_id' => $registration->id, 'position' => 2]);
        $service = app(PlayerEventRatingHistory::class);
        $this->assertSame([], $service->build($player, CarbonImmutable::today())['events'][0]['finishes']);
        $event->update(['results_published' => true]);
        $wrongCategory = Category::factory()->create(['name' => 'Unrelated category']);
        CategoryResult::create(['event_id' => $event->id, 'category_id' => $wrongCategory->id, 'registration_id' => $registration->id, 'position' => 1]);
        $row = $service->build($player, CarbonImmutable::today())['events'][0];
        $this->assertSame([['category' => 'Red Boys u/9', 'position' => 2]], $row['finishes']);
        $this->assertSame(['red'], $row['entry_stages']);
        $this->assertFalse($row['has_eligible_matches']);
        $this->assertNull($row['levels']['red']['score']);
        $this->assertSame(0, $row['levels']['red']['cape_events']);
    }

    public function test_complete_finish_only_history_is_labelled_low_confidence_and_store_rejects_false_confidence(): void
    {
        $players = Player::factory()->count(2)->create();
        $event = Event::factory()->create(['name' => 'Fictional Orange final', 'start_date' => '2026-10-01', 'end_date' => '2026-10-01', 'published' => true, 'results_published' => true]);
        $field = CategoryEvent::factory()->create(['event_id' => $event->id, 'category_id' => Category::factory()->create(['name' => 'Orange ball Boys A'])->id]);
        foreach ($players as $index => $player) {
            $registration = Registration::factory()->create(); $registration->players()->attach($player);
            CategoryEventRegistration::factory()->create(['category_event_id' => $field->id, 'registration_id' => $registration->id]);
            CategoryResult::create(['event_id' => $event->id, 'category_id' => $field->category_id, 'registration_id' => $registration->id, 'position' => $index + 1]);
        }
        $service = app(PlayerEventRatingHistory::class); $saved = $service->build($players[0], CarbonImmutable::today());
        $level = $saved['events'][0]['levels']['orange'];
        $this->assertNotNull($level['score']); $this->assertSame('finish_only', $level['evidence_basis']);
        $this->assertSame('Low', $level['confidence_label']); $this->assertTrue($level['local']);
        $this->assertSame(0, $level['matches_in_event']); $this->assertSame(0, $level['cape_events']);
        $this->store->replace($players[0]->id, $saved, $saved['source_fingerprint']);
        $timeline = $service->forPlayer($players[0]);
        $html = view('frontend.my-tennis.event-rating-history', ['ratingPlayer' => $players[0], 'timeline' => $timeline,
            'refreshStatus' => ['state' => 'idle', 'message' => 'Ready']])->render();
        $this->assertStringContainsString('Low confidence · final placings only', $html);
        $invalid = $saved; $invalid['events'][0]['levels']['orange']['confidence_label'] = 'High';
        try { $this->store->replace($players[0]->id, $invalid, $saved['source_fingerprint']); $this->fail('A finish-only estimate claimed strong confidence.'); }
        catch (\RuntimeException) { $this->assertSame($saved, $this->store->read($players[0]->id)); }
    }

    public function test_existing_player_rating_access_guards_both_history_and_background_requests(): void
    {
        $parent = User::factory()->create(); $other = User::factory()->create(); $player = Player::factory()->create(['userId' => $parent->id]);
        $url = route('my.tennis.event-history', $player); $refresh = route('my.tennis.event-history.refresh', $player);
        $this->actingAs($other)->get($url)->assertForbidden();
        $this->post($refresh)->assertForbidden();
        $this->actingAs($parent)->get($url)->assertForbidden();
        SiteSetting::set(PlayerRatingAccess::SETTING, '1');
        Gate::define('player-premium-rating', fn (User $actor, Player $target) => $actor->id === $parent->id && $target->id === $player->id);
        $this->mock(PlayerEventRatingHistory::class)->shouldReceive('forPlayer')->once()->withArgs(fn ($p, $page) => $p->id === $player->id && $page === 1)
            ->andReturn(['events' => $this->saved($player->id, str_repeat('a', 64))['events'], 'reason' => null, 'total' => 1, 'page' => 1, 'pages' => 1, 'as_of' => '2026-11-01']);
        $this->get($url)->assertOk()->assertSee('Reconstructed as of each event')->assertSee('Fictional Red event')->assertSee('2023-05-01')->assertSee('62.5')->assertSee('Not rated at this date');
        $this->mock(PlayerEventRatingHistoryRefresh::class)->shouldReceive('request')->once()->with($player->id)->andReturn(['state' => 'pending', 'message' => 'Background request accepted.']);
        $this->post($refresh)->assertRedirect($url);
        $this->actingAs($other)->get($url)->assertForbidden();
    }

    public function test_background_requests_are_coalesced_without_calculating_on_the_request(): void
    {
        $launcher = \Mockery::mock(PlayerEventRatingHistoryLauncher::class);
        $launcher->shouldReceive('launch')->once()->with(42, \Mockery::type('string'));
        $refresh = new PlayerEventRatingHistoryRefresh($launcher);
        $this->assertSame('pending', $refresh->request(42)['state']);
        $this->assertSame('pending', $refresh->request(42)['state']);
        $this->assertStringContainsString('requested', $refresh->status(42)['message']);
    }

    public function test_requested_runner_requires_a_live_matching_one_use_lease(): void
    {
        $refresh = app(PlayerEventRatingHistoryRefresh::class);
        $lease = Cache::store('file')->lock($refresh->lease(42), 120); $this->assertTrue($lease->get());
        $kernel = $this->app->make(\Illuminate\Contracts\Console\Kernel::class);
        Artisan::shouldReceive('call')->once()->with('player-event-ratings:refresh', ['player' => '42'])->andReturn(0);
        try {
            putenv('CT_EVENT_HISTORY_OWNER='.$lease->owner());
            $this->assertSame(0, $kernel->call('player-event-ratings:requested-refresh', ['player' => '42']));
            putenv('CT_EVENT_HISTORY_OWNER='.$lease->owner());
            $this->assertSame(1, $kernel->call('player-event-ratings:requested-refresh', ['player' => '42']));
        } finally { putenv('CT_EVENT_HISTORY_OWNER'); $lease->release(); }
    }
}
