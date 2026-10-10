<?php

namespace Tests\Feature;

use App\Jobs\RefreshPlayerRatings;
use App\Models\User;
use App\Services\Performance\{PlayerAbilityRefreshState, PlayerRatingRefreshRequest};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan, Cache};
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerRatingRefreshRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_and_polling_deny_guests_and_normal_admins(): void
    {
        $this->partialMock(PlayerAbilityRefreshState::class)->shouldNotReceive('status');
        $this->postJson(route('backend.player-performance.ratings.refresh'))->assertUnauthorized();
        $this->getJson(route('backend.player-performance.ratings.status'))->assertUnauthorized();
        Role::findOrCreate('admin', 'web');
        $this->actingAs(User::factory()->create()->assignRole('admin'));
        $this->postJson(route('backend.player-performance.ratings.refresh'))->assertForbidden();
        $this->getJson(route('backend.player-performance.ratings.status'))->assertForbidden();
    }

    public function test_failed_snapshot_requests_refresh_without_calculating_in_the_request(): void
    {
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $status = ['version' => 'old', 'pending' => true, 'failed' => true, 'last_updated' => null];
        $this->partialMock(PlayerAbilityRefreshState::class)->shouldReceive('status')->twice()->andReturn($status);
        $refresh = $this->mock(PlayerRatingRefreshRequest::class);
        $refresh->shouldReceive('request')->once();
        $refresh->shouldReceive('running')->twice()->andReturn(true);
        $this->postJson(route('backend.player-performance.ratings.refresh'))->assertOk()
            ->assertJsonPath('refreshing', true)->assertHeader('Cache-Control', 'no-store, private');
        $this->getJson(route('backend.player-performance.ratings.status'))->assertOk()->assertJsonPath('status.failed', true);
    }

    public function test_current_snapshot_does_not_request_another_calculation(): void
    {
        Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $this->partialMock(PlayerAbilityRefreshState::class)->shouldReceive('status')->once()
            ->andReturn(['version' => 'current', 'pending' => false, 'failed' => false]);
        $refresh = $this->mock(PlayerRatingRefreshRequest::class);
        $refresh->shouldNotReceive('request');
        $refresh->shouldReceive('running')->once()->andReturn(false);
        $this->postJson(route('backend.player-performance.ratings.refresh'))->assertOk()->assertJsonPath('refreshing', false);
    }

    public function test_concurrent_requests_are_deduplicated_and_job_releases_its_owned_lock(): void
    {
        $lock = Cache::store('file')->lock('player-ability:requested-refresh', 7200);
        $this->assertTrue($lock->get());
        try {
            $refresh = new PlayerRatingRefreshRequest;
            $refresh->request();
            $this->assertTrue($refresh->running());
            // Deliberately no --pending: a user retry bypasses the scheduler's
            // five-minute failure cooldown, while retaining the builder lock.
            Artisan::shouldReceive('call')->once()->with('player-ability:refresh')->andReturn(1);
            (new RefreshPlayerRatings($lock->owner()))->handle();
            $this->assertFalse($refresh->running());
        } finally { $lock->release(); }
    }

    public function test_scheduler_build_is_reported_as_running_and_user_requests_do_not_duplicate_it(): void
    {
        $lock = Cache::store('file')->lock('player-ability:refresh', 60);
        $this->assertTrue($lock->get());
        try {
            $refresh = new PlayerRatingRefreshRequest;
            $refresh->request();
            $this->assertTrue($refresh->running());
            $request = Cache::store('file')->lock('player-ability:requested-refresh', 60);
            $this->assertTrue($request->get());
            $request->release();
        } finally { $lock->release(); }
    }

    public function test_unexpected_job_failure_releases_execution_and_launch_leases(): void
    {
        $lock = Cache::store('file')->lock('player-ability:requested-refresh', 60);
        $this->assertTrue($lock->get());
        Artisan::shouldReceive('call')->once()->andThrow(new \RuntimeException('Test failure'));
        try {
            (new RefreshPlayerRatings($lock->owner()))->handle();
            $this->fail('Expected the command error to propagate.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Test failure', $error->getMessage());
            $this->assertFalse((new PlayerRatingRefreshRequest)->running());
        } finally { $lock->release(); }
    }
}
