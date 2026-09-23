<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EventAdminLoginRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_admin_login_falls_back_to_work_hub(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();
        EventAdmin::create(['user_id' => $user->id, 'event_id' => $event->id]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('backend.dashboard'));
    }

    public function test_ordinary_login_retains_home_fallback_and_intended_url_wins(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/');

        auth()->logout();

        $this->withSession(['url.intended' => '/events'])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ])->assertRedirect('/events');
    }

    public function test_super_user_login_falls_back_to_work_hub_without_event_assignment(): void
    {
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $user = User::factory()->create()->assignRole('super-user');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('backend.dashboard'));
    }

    public function test_unsafe_intended_destinations_fall_back_but_safe_local_intended_path_works(): void
    {
        foreach (['https://evil.example/path', '//evil.example/path', '/%2F%2Fevil.example', '/%5Cevil', "/events\r\nX-Test: bad"] as $unsafe) {
            $user = User::factory()->create();
            $this->withSession(['url.intended' => $unsafe])
                ->post('/login', ['email' => $user->email, 'password' => 'password'])
                ->assertRedirect('/');
            auth()->logout();
        }

        $user = User::factory()->create();
        $this->withSession(['url.intended' => '/events?mine=1'])
            ->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/events?mine=1');
    }

    public function test_authenticated_login_page_uses_the_shared_safe_destination_rules(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/login?redirect=%2Fevents')->assertRedirect('/events');
        $this->actingAs($user)->get('/login?redirect=https%3A%2F%2Fevil.example')->assertRedirect('/');
    }

    public function test_safe_explicit_destination_survives_two_factor_session_regeneration(): void
    {
        $user = $this->twoFactorUser('recovery-code');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'redirect' => '/events?after=2fa',
        ])->assertRedirect(route('two-factor.login'));

        $this->post(route('two-factor.login'), [
            'recovery_code' => 'recovery-code',
        ])->assertRedirect('/events?after=2fa');

        $this->assertAuthenticatedAs($user);
    }

    public function test_two_factor_login_rejects_unsafe_intended_and_explicit_destinations(): void
    {
        $user = $this->twoFactorUser('first-recovery');

        $this->withSession(['url.intended' => 'https://evil.example/intended'])
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
                'redirect' => '//evil.example/explicit',
            ])->assertRedirect(route('two-factor.login'));

        $this->post(route('two-factor.login'), [
            'recovery_code' => 'first-recovery',
        ])->assertRedirect('/');
    }

    public function test_json_two_factor_response_clears_stored_destinations(): void
    {
        $user = $this->twoFactorUser('json-recovery');

        $this->withSession(['url.intended' => '/events'])
            ->postJson('/login', [
                'email' => $user->email,
                'password' => 'password',
                'redirect' => '/events?json=1',
            ])
            ->assertOk()
            ->assertJsonPath('two_factor', true);

        $this->postJson(route('two-factor.login'), [
            'recovery_code' => 'json-recovery',
        ])->assertNoContent();

        $this->assertAuthenticatedAs($user);
        $this->assertFalse(session()->has('auth.post_login_redirect'));
        $this->assertFalse(session()->has('url.intended'));
    }

    public function test_legacy_state_changing_get_routes_are_never_login_destinations(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $roleUser = User::factory()->create()->assignRole('admin');
        $rolePath = '/backend/user/removeRole/'.$roleUser->id.'?role=admin';

        $this->post('/login', [
            'email' => $roleUser->email,
            'password' => 'password',
            'redirect' => $rolePath,
        ])->assertRedirect('/');
        $this->assertTrue($roleUser->fresh()->hasRole('admin'));

        auth()->logout();

        $profileUser = User::factory()->create();
        $player = Player::factory()->create();
        $profileUser->players()->attach($player->id);
        $pivotId = $profileUser->players()->firstOrFail()->pivot->id;
        $removeProfilePath = '/backend/player/removeProfileFromUser/'.$pivotId;

        $this->withSession(['url.intended' => $removeProfilePath])
            ->post('/login', [
                'email' => $profileUser->email,
                'password' => 'password',
            ])->assertRedirect('/');

        $this->assertDatabaseHas('user_players', ['id' => $pivotId]);
    }

    public function test_case_variant_login_attempts_share_one_throttle_bucket(): void
    {
        $user = User::factory()->create(['email' => 'mixed.case@example.test']);
        $key = 'mixed.case@example.test|127.0.0.1';
        RateLimiter::clear($key);

        foreach (range(1, 5) as $attempt) {
            $email = $attempt % 2 === 0 ? 'MIXED.CASE@EXAMPLE.TEST' : 'Mixed.Case@Example.Test';
            $this->post('/login', ['email' => $email, 'password' => 'wrong-password']);
        }

        $this->post('/login', [
            'email' => 'mixed.case@example.test',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_explicit_redirect_accepts_local_paths_and_rejects_protocol_relative_paths(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'redirect' => '/events?from=login',
        ])->assertRedirect('/events?from=login');

        auth()->logout();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'redirect' => '//evil.example/path',
        ])->assertRedirect('/');

        auth()->logout();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'redirect' => '/%2F%2Fevil.example/path',
        ])->assertRedirect('/');

        auth()->logout();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'redirect' => '/\\evil.example/path',
        ])->assertRedirect('/');

        auth()->logout();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
            'redirect' => '/%5C%5Cevil.example/path',
        ])->assertRedirect('/');
    }

    private function twoFactorUser(string $recoveryCode): User
    {
        $user = User::factory()->create();
        $secret = app(TwoFactorAuthenticationProvider::class)->generateSecretKey();
        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode([$recoveryCode])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }
}
