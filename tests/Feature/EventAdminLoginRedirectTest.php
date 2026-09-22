<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventAdmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
