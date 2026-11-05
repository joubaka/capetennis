<?php

namespace Tests\Feature;

use App\Models\{Player, User};
use App\Services\Performance\TeamSheetProfileLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\{ArraySessionHandler, Store};
use Illuminate\Support\Facades\{Blade, DB, Http};
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamSheetProfileLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['teamsheet.url' => 'https://teamsheet.example', 'teamsheet.token' => 'synthetic-server-only-token']);
        Http::preventStrayRequests();
    }

    private function actor(): array
    {
        $user = User::factory()->create(); $player = Player::factory()->create(['userId' => null]);
        $user->players()->attach($player->id);
        return [$user, $player];
    }

    private function request(User $user, string $session = 'a'): Request
    {
        $request = Request::create('/my-tennis/teamsheet/callback');
        $request->setUserResolver(fn () => $user);
        $store = new Store('profile-link-test', new ArraySessionHandler(120)); $store->setId(str_repeat($session, 40)); $store->start();
        $request->setLaravelSession($store);
        return $request;
    }

    private function start(User $user, Player $player): array
    {
        $url = app(TeamSheetProfileLinkService::class)->start($this->request($user), $player);
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $data = json_decode(base64_decode(strtr($query['request'], '-_', '+/')), true);
        return [$data, $query, $url];
    }

    private function claim(string $state, int $id = 101): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory); Http::preventStrayRequests();
        Http::fake(['teamsheet.example/*' => Http::response(['data' => ['source' => 'teamsheet', 'state' => $state, 'teamsheet_player_id' => $id]])]);
    }

    public function test_owned_player_starts_signed_short_lived_request_without_exposing_bearer(): void
    {
        [$user, $player] = $this->actor(); [$data, $query, $url] = $this->start($user, $player);
        $this->assertSame($player->id, $data['cape_player_id']);
        $this->assertSame(600, $data['expires_at'] - $data['issued_at']);
        $this->assertSame(hash_hmac('sha256', TeamSheetProfileLinkService::REQUEST_CONTEXT.$query['request'], 'synthetic-server-only-token'), $query['signature']);
        $this->assertStringNotContainsString('synthetic-server-only-token', $url);
        $this->assertDatabaseCount('teamsheet_profile_link_requests', 1);
        $this->assertNotSame($data['state'], DB::table('teamsheet_profile_link_requests')->value('state_hash'));
        Http::assertNothingSent();
    }

    public function test_super_user_preview_and_other_parents_cannot_start_for_an_unowned_player(): void
    {
        [$owner, $player] = $this->actor(); Role::findOrCreate('super-user', 'web');
        $other = User::factory()->create()->assignRole('super-user');
        $this->actingAs($other)->post(route('my.tennis.teamsheet.start', $player))->assertForbidden();
        $this->assertDatabaseCount('teamsheet_profile_link_requests', 0);
        $this->actingAs($owner)->post(route('my.tennis.teamsheet.start', $player))->assertRedirect();
    }

    public function test_exchange_binds_only_the_owned_player_and_is_one_use_without_creating_ownership(): void
    {
        [$user, $player] = $this->actor(); [$data] = $this->start($user, $player); $this->claim($data['state']);
        $this->assertSame($player->id, app(TeamSheetProfileLinkService::class)->complete($this->request($user), $data['state'], str_repeat('c', 64)));
        $this->assertDatabaseHas('teamsheet_tennis_player_links', ['player_id' => $player->id, 'teamsheet_player_id' => 101, 'link_method' => 'self_service', 'linked_by' => $user->id]);
        $this->assertDatabaseCount('user_players', 1);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer synthetic-server-only-token') && $request['state'] === $data['state']);
        try { app(TeamSheetProfileLinkService::class)->complete($this->request($user), $data['state'], str_repeat('c', 64)); $this->fail('Replayed callback accepted'); }
        catch (ValidationException) { $this->assertDatabaseCount('teamsheet_tennis_player_links', 1); }
        Http::assertSentCount(1);
    }

    public function test_wrong_actor_session_state_and_expired_attempts_cannot_exchange(): void
    {
        [$user, $player] = $this->actor(); [$data] = $this->start($user, $player);
        $other = User::factory()->create();
        foreach ([[$this->request($other), $data['state']], [$this->request($user, 'b'), $data['state']], [$this->request($user), str_repeat('d', 64)]] as [$request, $state]) {
            try { app(TeamSheetProfileLinkService::class)->complete($request, $state, str_repeat('c', 64)); $this->fail('Invalid callback accepted'); }
            catch (ValidationException) { $this->assertDatabaseCount('teamsheet_tennis_player_links', 0); }
        }
        DB::table('teamsheet_profile_link_requests')->update(['expires_at' => now()->subSecond()]);
        try { app(TeamSheetProfileLinkService::class)->complete($this->request($user), $data['state'], str_repeat('c', 64)); $this->fail('Expired callback accepted'); }
        catch (ValidationException) { Http::assertNothingSent(); }
    }

    public function test_revoked_ownership_and_superseded_attempt_fail_before_remote_exchange(): void
    {
        [$user, $player] = $this->actor(); [$old] = $this->start($user, $player); [$new] = $this->start($user, $player);
        try { app(TeamSheetProfileLinkService::class)->complete($this->request($user), $old['state'], str_repeat('c', 64)); $this->fail('Superseded callback accepted'); }
        catch (ValidationException) { $this->assertDatabaseCount('teamsheet_tennis_player_links', 0); }
        $user->players()->detach($player->id);
        try { app(TeamSheetProfileLinkService::class)->complete($this->request($user), $new['state'], str_repeat('c', 64)); $this->fail('Revoked ownership accepted'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) { $this->assertSame(403, $error->getStatusCode()); }
        Http::assertNothingSent();
    }

    public function test_mapping_conflicts_preserve_existing_admin_and_other_player_connections(): void
    {
        [$user, $player] = $this->actor(); [$data] = $this->start($user, $player); $other = Player::factory()->create();
        DB::table('teamsheet_tennis_player_links')->insert(['player_id' => $other->id, 'teamsheet_player_id' => 101, 'reviewed_by' => $user->id, 'reason' => 'Existing reviewed administrator mapping.', 'created_at' => now(), 'updated_at' => now()]);
        $this->claim($data['state']);
        try { app(TeamSheetProfileLinkService::class)->complete($this->request($user), $data['state'], str_repeat('c', 64)); $this->fail('Conflicting identity overwritten'); }
        catch (ValidationException) { $this->assertDatabaseHas('teamsheet_tennis_player_links', ['player_id' => $other->id, 'teamsheet_player_id' => 101, 'link_method' => 'admin_review']); }
        $this->assertDatabaseMissing('teamsheet_tennis_player_links', ['player_id' => $player->id]);
        $this->assertNull(DB::table('teamsheet_profile_link_requests')->where('state_hash', hash('sha256', $data['state']))->value('consumed_at'));
    }

    public function test_only_original_self_linking_actor_with_current_ownership_can_unlink(): void
    {
        [$user, $player] = $this->actor(); [$data] = $this->start($user, $player); $this->claim($data['state']);
        $links = app(TeamSheetProfileLinkService::class); $links->complete($this->request($user), $data['state'], str_repeat('c', 64));
        $otherParent = User::factory()->create(); $otherParent->players()->attach($player->id);
        try { $links->unlink($otherParent, $player); $this->fail('Another actor removed the mapping'); }
        catch (ValidationException) { $this->assertDatabaseCount('teamsheet_tennis_player_links', 1); }
        $links->unlink($user, $player); $this->assertDatabaseCount('teamsheet_tennis_player_links', 0);
        DB::table('teamsheet_tennis_player_links')->insert(['player_id' => $player->id, 'teamsheet_player_id' => 101, 'reviewed_by' => $user->id, 'reason' => 'Existing administrator mapping.', 'created_at' => now(), 'updated_at' => now()]);
        try { $links->unlink($user, $player); $this->fail('Self service removed an admin mapping'); }
        catch (ValidationException) { $this->assertDatabaseCount('teamsheet_tennis_player_links', 1); }
    }

    public function test_legacy_owner_can_link_and_dashboard_partial_has_no_private_token(): void
    {
        $user = User::factory()->create(); $player = Player::factory()->create(['userId' => $user->id]);
        $this->actingAs($user);
        $html = Blade::render('@include("frontend.my-tennis.teamsheet-connection")', ['selectedPlayer' => $player, 'errors' => new \Illuminate\Support\ViewErrorBag]);
        $this->assertStringContainsString('Link TeamSheet', $html); $this->assertStringNotContainsString('synthetic-server-only-token', $html);
        $this->assertStringContainsString('href="https://www.teamsheet.co.za/"', $html);
        [$data] = $this->start($user, $player); $this->claim($data['state']);
        $this->assertSame($player->id, app(TeamSheetProfileLinkService::class)->complete($this->request($user), $data['state'], str_repeat('c', 64)));
        $this->assertDatabaseCount('user_players', 0);
    }

    public function test_profile_card_links_to_teamsheet_when_profile_linking_is_unavailable(): void
    {
        [$user, $player] = $this->actor();
        config(['teamsheet.token' => null]);
        $this->actingAs($user);

        $html = Blade::render('@include("frontend.my-tennis.teamsheet-connection")', [
            'selectedPlayer' => $player, 'errors' => new \Illuminate\Support\ViewErrorBag,
        ]);

        $this->assertStringContainsString('Once your school signs up for TeamSheet, you can link your player profile', $html);
        $this->assertStringContainsString('tennis ladder, school tournament and league match results', $html);
        $this->assertStringContainsString('href="https://www.teamsheet.co.za/"', $html);
        $this->assertStringNotContainsString(route('my.tennis.teamsheet.start', $player), $html);
    }

    public function test_invalid_claim_or_redirect_does_not_follow_or_create_mapping_and_error_is_private(): void
    {
        [$user, $player] = $this->actor(); [$data] = $this->start($user, $player);
        Http::fake(['teamsheet.example/*' => Http::response('', 302, ['Location' => 'https://other.example/'])]);
        try { app(TeamSheetProfileLinkService::class)->complete($this->request($user), $data['state'], str_repeat('c', 64)); $this->fail('Redirect followed'); }
        catch (ValidationException) { $this->assertDatabaseCount('teamsheet_tennis_player_links', 0); }
        Http::assertSentCount(1);
        $response = $this->actingAs($user)->get(route('my.tennis.teamsheet.callback', ['state' => 'bad', 'code' => 'bad']))->assertRedirect(route('my.tennis'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control')); $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $this->assertStringNotContainsString('synthetic-server-only-token', $response->getContent());
    }
}
