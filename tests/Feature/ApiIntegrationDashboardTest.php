<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiIntegrationDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_super_users_can_view_api_connections(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('superadmin.api-integrations.index'))
            ->assertForbidden();

        Role::findOrCreate('super-user', 'web');
        $user->assignRole('super-user');

        $this->actingAs($user)
            ->get(route('superadmin.api-integrations.index'))
            ->assertOk()
            ->assertSee('API Connections');
    }

    public function test_dashboard_shows_only_integration_tokens_and_explains_connection_state(): void
    {
        Role::findOrCreate('super-user', 'web');
        $superUser = User::factory()->create();
        $superUser->assignRole('super-user');

        $superUser->createToken('Bayhart Tennis Academy', ['jta-results:read'], now()->addDays(30));
        $superUser->createToken('Ordinary personal token', ['read'], now()->addDays(30));

        $this->actingAs($superUser)
            ->get(route('superadmin.api-integrations.index'))
            ->assertOk()
            ->assertSee('Bayhart Tennis Academy')
            ->assertSee('Awaiting first connection')
            ->assertDontSee('Ordinary personal token');

        PersonalAccessToken::where('name', 'Bayhart Tennis Academy')->update(['last_used_at' => now()]);

        $this->actingAs($superUser)
            ->get(route('superadmin.api-integrations.index'))
            ->assertOk()
            ->assertSee('Trying to connect');
    }

    public function test_successful_api_traffic_marks_the_connection_active_without_exposing_the_key(): void
    {
        config()->set('integrations.jta.require_https', false);
        Role::findOrCreate('super-user', 'web');
        $superUser = User::factory()->create();
        $superUser->assignRole('super-user');
        $issued = $superUser->createToken('Bayhart Tennis Academy', ['jta-results:read'], now()->addDays(30));

        $this->withToken($issued->plainTextToken)
            ->getJson('/api/v1/integrations/jta/health')
            ->assertOk();

        $this->actingAs($superUser)
            ->get(route('superadmin.api-integrations.index'))
            ->assertOk()
            ->assertSee('Bayhart Tennis Academy')
            ->assertSee('Active')
            ->assertSee('Successfully connected to Cape Tennis')
            ->assertDontSee($issued->plainTextToken);
    }
}
