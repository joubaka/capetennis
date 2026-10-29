<?php

namespace Tests\Feature;

use App\Http\Controllers\Backend\PlayerMembershipPreviewController;
use App\Models\User;
use App\Services\Performance\{PlayerAbilitySnapshotStore, PlayerMembershipPreviewService};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Bus, DB, Mail, Queue};
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PlayerMembershipAlertsPreviewTest extends TestCase
{
    public function test_alerts_denies_nonlocal_guests_and_authenticated_non_super_users_before_any_data_access(): void
    {
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldNotReceive('current', 'replace');
        $this->mock(PlayerMembershipPreviewService::class)->shouldNotReceive('build');
        $controller = new PlayerMembershipPreviewController;
        $this->app['env'] = 'production';
        try { $controller->alerts(Request::create('/alerts')); $this->fail(); } catch (HttpException $e) { $this->assertSame(404, $e->getStatusCode()); }
        $this->app['env'] = 'local';
        $ordinary = Mockery::mock(User::class)->makePartial(); $ordinary->shouldReceive('hasRole')->with('super-user')->andReturnFalse();
        foreach ([null, $ordinary] as $actor) {
            $request = Request::create('/alerts'); $request->setUserResolver(fn () => $actor);
            try { $controller->alerts($request); $this->fail(); } catch (HttpException $e) { $this->assertSame(403, $e->getStatusCode()); }
        }
    }

    public function test_authorized_alerts_is_a_private_fictional_view_without_rating_reads_writes_jobs_or_mail(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']); DB::purge('sqlite');
        Bus::fake(); Queue::fake(); Mail::fake();
        $this->mock(PlayerAbilitySnapshotStore::class)->shouldNotReceive('current', 'replace');
        $this->mock(PlayerMembershipPreviewService::class)->shouldNotReceive('build');
        $this->app['env'] = 'local';
        $user = Mockery::mock(User::class)->makePartial(); $user->shouldReceive('hasRole')->with('super-user')->andReturnTrue();
        $factory = Mockery::mock(\Illuminate\Contracts\Routing\ResponseFactory::class);
        $factory->shouldReceive('view')->once()->withArgs(function ($name, $data) {
            $this->assertSame('backend.player-performance.membership-preview.alerts', $name);
            $this->assertArrayNotHasKey('rows', $data); $this->assertArrayNotHasKey('names', $data);
            $this->assertNull($data['as_of']); return true;
        })->andReturn(new \Illuminate\Http\Response('fictional preview'));
        $this->app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class, $factory);
        $request = Request::create('/alerts'); $request->setUserResolver(fn () => $user);
        DB::statement('PRAGMA query_only = ON');
        try {
            $response = (new PlayerMembershipPreviewController)->alerts($request);
            $html = view('backend.player-performance.membership-preview.alerts-simulator')->render();
            $this->assertSame('no-store, private', $response->headers->get('Cache-Control'));
            $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
            $this->assertStringContainsString('Fictional simulation', $html);
            $this->assertStringContainsString('all delivery off', $html);
            $this->assertStringContainsString('Demo Coastal Cup', $html);
            $this->assertStringContainsString('Demo Alex', $html);
            $this->assertStringContainsString('Daily digest', $html);
            $this->assertStringContainsString('Unsubscribe all examples', $html);
            $this->assertStringContainsString('Essential participation, cancellation and required tournament information would remain free', $html);
            Bus::assertNothingDispatched(); Queue::assertNothingPushed(); Mail::assertNothingSent(); Mail::assertNothingQueued();
        } finally { DB::statement('PRAGMA query_only = OFF'); }
    }
}
