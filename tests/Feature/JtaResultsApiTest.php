<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\CategoryEvent;
use App\Models\Draw;
use App\Models\Event;
use App\Models\Fixture;
use App\Models\FixtureResult;
use App\Models\OrderOfPlay;
use App\Models\Player;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JtaResultsApiTest extends TestCase
{
    use RefreshDatabase;

    private User $tokenOwner;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tokenOwner = User::factory()->create();
        $this->token = $this->tokenOwner
            ->createToken('jta-test', ['jta-results:read'], now()->addHour())
            ->plainTextToken;
    }

    public function test_authentication_and_dedicated_ability_are_required(): void
    {
        $this->getJson('/api/v1/integrations/jta/health')->assertUnauthorized();
        $this->assertDatabaseHas('audit_events', [
            'action' => 'integration.jta.access',
            'status_code' => 401,
        ]);

        $wrongToken = User::factory()->create()->createToken('wrong', ['read'])->plainTextToken;
        $this->withToken($wrongToken)
            ->getJson('/api/v1/integrations/jta/health')
            ->assertForbidden();

        app('auth')->forgetGuards();
        $this->withToken($this->token)
            ->getJson('/api/v1/integrations/jta/health')
            ->assertOk()
            ->assertJsonPath('api_version', 'v1')
            ->assertJsonPath('status', 'ok')
            ->assertJsonMissing(['data']);
    }

    public function test_exact_identity_resolution_is_private_and_never_creates_players(): void
    {
        $player = Player::factory()->create([
            'name' => 'John',
            'surname' => 'Smith',
            'dateOfBirth' => '2012-04-15',
            'email' => 'private@example.test',
            'cellNr' => '0820000000',
        ]);
        $count = Player::count();

        $response = $this->withToken($this->token)->postJson('/api/v1/integrations/jta/players/resolve', [
            'first_name' => '  JOHN ',
            'last_name' => 'Smith',
            'date_of_birth' => '2012-04-15',
        ]);

        $response->assertOk()->assertExactJson(['data' => [
            'cape_tennis_player_id' => $player->id,
            'display_name' => 'John Smith',
            'identity_match' => 'exact',
        ]]);
        $this->assertSame($count, Player::count());
        $this->assertStringNotContainsString('private@example.test', $response->getContent());
        $this->assertStringNotContainsString('2012-04-15', $response->getContent());
        $this->assertStringNotContainsString('0820000000', $response->getContent());
    }

    public function test_lookup_returns_only_bounded_same_name_evidence_without_birth_dates(): void
    {
        $exact = Player::factory()->create([
            'name' => 'Jovan',
            'surname' => 'Joubert',
            'dateOfBirth' => '2012-04-15',
            'email' => 'exact-private@example.test',
            'cellNr' => '0820000001',
        ]);
        $possible = Player::factory()->create([
            'name' => '  JOVAN ',
            'surname' => 'Joubert',
            'dateOfBirth' => '2013-06-20',
            'email' => 'possible-private@example.test',
            'cellNr' => '0820000002',
        ]);
        $legacy = Player::factory()->create([
            'name' => 'Jovan',
            'surname' => 'Joubert',
            'dateOfBirth' => null,
        ]);
        Player::factory()->create([
            'name' => 'Jovan',
            'surname' => 'Different',
            'dateOfBirth' => '2012-04-15',
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v1/integrations/jta/players/lookup', [
            'first_name' => 'jovan',
            'last_name' => 'joubert',
            'date_of_birth' => '2012-04-15',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.exact_match.cape_tennis_player_id', $exact->id)
            ->assertJsonCount(3, 'data.possible_matches')
            ->assertJsonFragment([
                'cape_tennis_player_id' => $exact->id,
                'date_of_birth_status' => 'match',
                'match_type' => 'exact',
            ])
            ->assertJsonFragment([
                'cape_tennis_player_id' => $possible->id,
                'date_of_birth_status' => 'different',
                'match_type' => 'name_only',
            ])
            ->assertJsonFragment([
                'cape_tennis_player_id' => $legacy->id,
                'date_of_birth_status' => 'missing',
                'match_type' => 'name_only',
            ])
            ->assertJsonMissingPath('data.possible_matches.0.date_of_birth')
            ->assertJsonMissing(['email' => 'exact-private@example.test'])
            ->assertJsonMissing(['cellNr' => '0820000002']);

        $this->withToken($this->token)->postJson('/api/v1/integrations/jta/players/lookup', [
            'first_name' => 'Jovan',
            'last_name' => 'Joubert',
            'date_of_birth' => '2014-01-01',
        ])->assertOk()
            ->assertJsonPath('data.exact_match', null)
            ->assertJsonCount(3, 'data.possible_matches');

        $this->withToken($this->token)->postJson('/api/v1/integrations/jta/players/lookup', [
            'first_name' => 'Jovan',
            'last_name' => 'Joubert',
        ])->assertOk()
            ->assertJsonPath('data.exact_match', null)
            ->assertJsonCount(3, 'data.possible_matches');
    }

    public function test_bulk_lookup_is_bounded_correlated_and_does_not_disclose_birth_dates(): void
    {
        $exact = Player::factory()->create([
            'name' => 'Jovan',
            'surname' => 'Joubert',
            'dateOfBirth' => '2012-04-15',
            'email' => 'private@example.test',
        ]);
        Player::factory()->create([
            'name' => 'Jovan',
            'surname' => 'Joubert',
            'dateOfBirth' => '2013-06-20',
        ]);

        $response = $this->withToken($this->token)->postJson('/api/v1/integrations/jta/players/bulk-lookup', [
            'request_id' => 'scan-42-batch-1',
            'players' => [
                ['client_reference' => 42, 'first_name' => 'Jovan', 'last_name' => 'Joubert', 'date_of_birth' => '2012-04-15'],
                ['client_reference' => 43, 'first_name' => 'Missing', 'last_name' => 'Player', 'date_of_birth' => null],
            ],
        ])->assertOk()
            ->assertJsonPath('data.request_id', 'scan-42-batch-1')
            ->assertJsonPath('data.results.0.client_reference', 42)
            ->assertJsonPath('data.results.0.status', 'exact')
            ->assertJsonPath('data.results.0.exact_match.cape_tennis_player_id', $exact->id)
            ->assertJsonPath('data.results.0.possible_matches.1.date_of_birth_status', 'different')
            ->assertJsonPath('data.results.1.status', 'missing_date_of_birth')
            ->assertJsonPath('meta.count', 2)
            ->assertJsonPath('meta.max_batch_size', 50);

        $this->assertStringNotContainsString('2012-04-15', $response->getContent());
        $this->assertStringNotContainsString('2013-06-20', $response->getContent());
        $this->assertStringNotContainsString('private@example.test', $response->getContent());
    }

    public function test_bulk_lookup_rejects_oversized_or_duplicate_batches(): void
    {
        $player = ['client_reference' => 1, 'first_name' => 'Test', 'last_name' => 'Player', 'date_of_birth' => null];

        $this->withToken($this->token)->postJson('/api/v1/integrations/jta/players/bulk-lookup', [
            'request_id' => 'too-large',
            'players' => array_fill(0, 51, $player),
        ])->assertUnprocessable();

        $this->withToken($this->token)->postJson('/api/v1/integrations/jta/players/bulk-lookup', [
            'request_id' => 'duplicate',
            'players' => [$player, $player],
        ])->assertUnprocessable();
    }

    public function test_unknown_and_duplicate_identities_return_safe_errors(): void
    {
        $payload = ['first_name' => 'Alex', 'last_name' => 'Duplicate', 'date_of_birth' => '2011-01-02'];

        $this->withToken($this->token)
            ->postJson('/api/v1/integrations/jta/players/resolve', $payload)
            ->assertNotFound();

        Player::factory()->count(2)->create([
            'name' => 'Alex', 'surname' => 'Duplicate', 'dateOfBirth' => '2011-01-02',
        ]);

        $this->withToken($this->token)
            ->postJson('/api/v1/integrations/jta/players/resolve', $payload)
            ->assertStatus(409)
            ->assertJsonMissing(['data']);
    }

    public function test_side_one_side_two_singles_and_registration_doubles_serialize_without_private_data(): void
    {
        $requested = Player::factory()->create(['name' => 'Linked', 'surname' => 'Player']);
        $opponent = Player::factory()->create(['name' => 'Safe', 'surname' => 'Opponent', 'email' => 'hidden@example.test']);

        $sideOne = $this->makeFixture([$requested], [$opponent], ['match_nr' => 1]);
        $sideTwo = $this->makeFixture([$opponent], [$requested], ['match_nr' => 2]);
        $partner = Player::factory()->create(['name' => 'Double', 'surname' => 'Partner']);
        $other1 = Player::factory()->create();
        $other2 = Player::factory()->create();
        $doubles = $this->makeFixture([$requested, $partner], [$other1, $other2], ['match_nr' => 3]);
        OrderOfPlay::create([
            'fixture_id' => $sideOne->id,
            'draw_id' => $sideOne->draw_id,
            'venue_id' => 1,
            'time' => '2026-08-10 09:30:00',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/v1/integrations/jta/players/{$requested->id}/results")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.source_match_id', 'ct-fixture-'.$sideOne->id)
            ->assertJsonPath('data.0.match.date_precision', 'scheduled_time')
            ->assertJsonPath('data.0.match.match_type', 'singles')
            ->assertJsonPath('data.1.side2.players.0.cape_tennis_player_id', $requested->id)
            ->assertJsonPath('data.2.source_fixture_id', $doubles->id)
            ->assertJsonPath('data.2.match.match_type', 'doubles')
            ->assertJsonCount(2, 'data.2.side1.players');

        $this->assertStringNotContainsString('hidden@example.test', $response->getContent());
        $this->assertSame($sideTwo->id, $response->json('data.1.source_fixture_id'));
    }

    public function test_export_rules_include_normal_and_legacy_scored_matches_and_exclude_byes_unscored_partial_and_team_rows(): void
    {
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();
        $normal = $this->makeFixture([$player], [$opponent], ['match_status' => 1, 'match_nr' => 1]);
        $legacy = $this->makeFixture([$player], [$opponent], ['match_status' => 3, 'match_nr' => 2]);

        $this->makeFixture([$player], [$opponent], ['match_status' => 2, 'match_nr' => 3]);
        $this->makeFixture([$player], [$opponent], ['match_status' => 1, 'match_nr' => 4], false);

        $bye = $this->makeFixture([$player], [$opponent], ['match_status' => 3, 'match_nr' => 5]);
        $bye->update(['registration2_id' => 0, 'winner_registration' => $bye->registration1_id]);

        $teamA = Player::factory()->count(3)->create()->all();
        $teamB = Player::factory()->count(3)->create()->all();
        $teamA[0] = $player;
        $this->makeFixture($teamA, $teamB, ['match_status' => 1, 'match_nr' => 6]);

        $ids = $this->withToken($this->token)
            ->getJson("/api/v1/integrations/jta/players/{$player->id}/results")
            ->assertOk()
            ->json('data.*.source_fixture_id');

        $this->assertSame([$normal->id, $legacy->id], $ids);
    }

    public function test_unpublished_draws_and_unrelated_players_do_not_leak(): void
    {
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();
        $unrelated = Player::factory()->create();
        $this->makeFixture([$unrelated], [$opponent]);
        $hidden = $this->makeFixture([$player], [$opponent]);
        $hidden->draw->update(['published' => false]);

        $this->withToken($this->token)
            ->getJson("/api/v1/integrations/jta/players/{$player->id}/results")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_score_corrections_keep_source_id_change_version_and_are_found_by_updated_since(): void
    {
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();
        $fixture = $this->makeFixture([$player], [$opponent]);
        $url = "/api/v1/integrations/jta/players/{$player->id}/results";
        $before = $this->withToken($this->token)->getJson($url)->assertOk()->json('data.0');

        DB::table('fixtures')->where('id', $fixture->id)->update(['updated_at' => now()->subHours(2)]);
        $result = FixtureResult::where('fixture_id', $fixture->id)->firstOrFail();
        $result->update(['registration1_score' => 7, 'updated_at' => now()]);
        $since = urlencode(now()->subMinute()->toIso8601String());
        $after = $this->withToken($this->token)->getJson($url.'?updated_since='.$since)->assertOk()->json('data.0');

        $this->assertSame($before['source_match_id'], $after['source_match_id']);
        $this->assertNotSame($before['source_version'], $after['source_version']);
        $this->assertSame(7, $after['sets'][0]['side1_games']);

        $event = Event::findOrFail($fixture->draw->event_id);
        $event->update(['name' => 'Corrected Event Name']);
        $eventChanged = $this->withToken($this->token)->getJson($url)->assertOk()->json('data.0');
        $this->assertNotSame($after['source_version'], $eventChanged['source_version']);

        $opponent->update(['surname' => 'Corrected Opponent']);
        $participantChanged = $this->withToken($this->token)->getJson($url)->assertOk()->json('data.0');
        $this->assertNotSame($eventChanged['source_version'], $participantChanged['source_version']);

        $fixture->update(['winner_registration' => $fixture->registration2_id]);
        $winnerChanged = $this->withToken($this->token)->getJson($url)->assertOk()->json('data.0');
        $this->assertNotSame($participantChanged['source_version'], $winnerChanged['source_version']);
    }

    public function test_page_and_cursor_pagination_are_deterministic_without_duplicates(): void
    {
        $player = Player::factory()->create();
        $opponent = Player::factory()->create();
        foreach (range(1, 3) as $matchNumber) {
            $this->makeFixture([$player], [$opponent], ['match_nr' => $matchNumber]);
        }

        $base = "/api/v1/integrations/jta/players/{$player->id}/results?per_page=2";
        $page1 = $this->withToken($this->token)->getJson($base)->assertOk();
        $page2 = $this->withToken($this->token)->getJson($base.'&page=2')->assertOk();
        $ids = array_merge($page1->json('data.*.source_fixture_id'), $page2->json('data.*.source_fixture_id'));
        $this->assertCount(3, array_unique($ids));
        $this->assertSame($ids, collect($ids)->sort()->values()->all());

        $cursor1 = $this->withToken($this->token)->getJson($base.'&cursor=')->assertOk();
        $cursorUrl = $cursor1->json('links.next');
        $this->assertNotNull($cursorUrl);
        $cursor2 = $this->withToken($this->token)->getJson($cursorUrl)->assertOk();
        $cursorIds = array_merge($cursor1->json('data.*.source_fixture_id'), $cursor2->json('data.*.source_fixture_id'));
        $this->assertCount(3, array_unique($cursorIds));
    }

    public function test_named_rate_limit_and_safe_integration_audit_logging_work(): void
    {
        config()->set('integrations.jta.rate_limit_per_minute', 1);
        RateLimiter::clear('token:'.$this->tokenOwner->currentAccessToken()?->getKey());
        $player = Player::factory()->create();

        $this->withToken($this->token)
            ->getJson("/api/v1/integrations/jta/players/{$player->id}/results")
            ->assertOk();
        $this->withToken($this->token)
            ->getJson("/api/v1/integrations/jta/players/{$player->id}/results")
            ->assertStatus(429);

        $audit = AuditEvent::where('action', 'integration.jta.access')
            ->where('subject_id', (string) $player->id)
            ->latest('id')
            ->firstOrFail();
        $this->assertSame('api.v1.integrations.jta.players.results', $audit->metadata['endpoint']);
        $this->assertSame($player->id, $audit->metadata['cape_tennis_player_id']);
        $this->assertArrayNotHasKey('date_of_birth', $audit->metadata);
    }

    public function test_token_command_requires_super_user_and_rotates_an_expiring_single_ability_token(): void
    {
        $this->artisan('jta:issue-results-token', ['--user' => $this->tokenOwner->email])
            ->assertFailed();

        Role::findOrCreate('super-user', 'web');
        $this->tokenOwner->assignRole('super-user');

        $this->artisan('jta:issue-results-token', [
            '--user' => $this->tokenOwner->email,
            '--name' => 'jta-production',
            '--expires-days' => 30,
        ])->assertSuccessful();

        $issued = $this->tokenOwner->tokens()->where('name', 'jta-production')->firstOrFail();
        $this->assertSame(['jta-results:read'], $issued->abilities);
        $this->assertNotNull($issued->expires_at);

        $this->artisan('jta:issue-results-token', [
            '--user' => $this->tokenOwner->email,
            '--name' => 'jta-production',
            '--expires-days' => 30,
        ])->assertSuccessful();
        $this->assertSame(1, $this->tokenOwner->tokens()->where('name', 'jta-production')->count());
    }

    private function makeFixture(array $side1Players, array $side2Players, array $fixtureAttributes = [], bool $withScore = true): Fixture
    {
        $event = Event::factory()->create(['published' => true]);
        $categoryEvent = CategoryEvent::factory()->create(['event_id' => $event->id]);
        $draw = Draw::factory()->create([
            'event_id' => $event->id,
            'category_event_id' => $categoryEvent->id,
            'published' => true,
        ]);
        $registration1 = Registration::factory()->create();
        $registration2 = Registration::factory()->create();
        $registration1->players()->attach(collect($side1Players)->pluck('id'));
        $registration2->players()->attach(collect($side2Players)->pluck('id'));

        $fixture = Fixture::factory()->create(array_merge([
            'draw_id' => $draw->id,
            'registration1_id' => $registration1->id,
            'registration2_id' => $registration2->id,
            'winner_registration' => $registration1->id,
            'match_status' => 1,
        ], $fixtureAttributes));

        if ($withScore) {
            FixtureResult::factory()->create([
                'fixture_id' => $fixture->id,
                'winner_registration' => $registration1->id,
                'loser_registration' => $registration2->id,
            ]);
        }

        return $fixture->load('draw');
    }
}
