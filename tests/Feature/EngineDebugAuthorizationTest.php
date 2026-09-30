<?php

namespace Tests\Feature;

use App\Models\EngineComparisonLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EngineDebugAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
    }

    public function test_only_a_super_user_can_view_or_clear_engine_debug_logs(): void
    {
        $regular = User::factory()->create();
        $superUser = User::factory()->create()->assignRole('super-user');
        $this->createComparisonLog();

        $this->get(route('engine.debug'))->assertRedirect(route('login'));
        $this->actingAs($regular)->get(route('engine.debug'))->assertForbidden();
        $this->actingAs($regular)->delete(route('engine.debug.clear'))->assertForbidden();
        $this->assertDatabaseCount('engine_comparison_logs', 1);

        $this->actingAs($superUser)->get(route('engine.debug'))->assertOk();
        $this->actingAs($superUser)->delete(route('engine.debug.clear'))
            ->assertRedirect(route('engine.debug'));
        $this->assertDatabaseCount('engine_comparison_logs', 0);
    }

    public function test_engine_debug_endpoints_are_not_found_in_production_and_clear_does_not_mutate(): void
    {
        $superUser = User::factory()->create()->assignRole('super-user');
        $this->createComparisonLog();
        $originalEnvironment = app()->environment();

        app()->detectEnvironment(fn (): string => 'production');

        try {
            $this->actingAs($superUser)->get(route('engine.debug'))->assertNotFound();
            $this->actingAs($superUser)
                ->withSession(['_token' => 'engine-debug-production-test'])
                ->delete(route('engine.debug.clear'), ['_token' => 'engine-debug-production-test'])
                ->assertNotFound();
            $this->assertDatabaseCount('engine_comparison_logs', 1);
        } finally {
            app()->detectEnvironment(fn (): string => $originalEnvironment);
        }
    }

    private function createComparisonLog(): void
    {
        EngineComparisonLog::create([
            'operation' => 'test',
            'mismatch_type' => 'test',
            'engine_mode' => 'hybrid',
            'legacy_result' => [],
            'canonical_result' => [],
            'was_fallback' => false,
        ]);
    }
}
