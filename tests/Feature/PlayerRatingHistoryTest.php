<?php

namespace Tests\Feature;

use App\Models\{Player, User};
use App\Services\Performance\{PlayerRatingAccess, PlayerRatingHistoryService, PlayerRatingHistoryStore, PlayerSharedAbilityService};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class PlayerRatingHistoryTest extends TestCase
{
    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_durable_store_roundtrip_retains_last_good_on_source_or_policy_mismatch(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-29', 'Africa/Johannesburg'));
        $path = storage_path('framework/testing/rating-history-'.bin2hex(random_bytes(8)).'.json');
        $store = new class($path) extends PlayerRatingHistoryStore {
            public function __construct(private string $testPath) {}
            public function path(): string { return $this->testPath; }
        };
        $months = [];
        for ($offset = 11; $offset >= 0; $offset--) {
            $date = $offset ? CarbonImmutable::today()->startOfMonth()->subMonths($offset)->endOfMonth() : CarbonImmutable::today();
            $months[] = ['date' => $date->toDateString(), 'cohorts' => []];
        }
        $saved = ['months' => $months, 'as_of' => '2026-10-29', 'built_at' => '2026-10-29T03:00:00+02:00',
            'source_fingerprint' => str_repeat('a', 64), 'policy_hash' => $store->policyHash(), 'version' => PlayerRatingHistoryStore::VERSION];
        try {
            $this->assertNull($store->read());
            $store->replace($saved, str_repeat('a', 64));
            $this->assertSame($saved, $store->read());
            foreach ([[$saved, str_repeat('b', 64)], [array_replace($saved, ['policy_hash' => 'old']), str_repeat('a', 64)]] as [$invalid, $fingerprint]) {
                try { $store->replace($invalid, $fingerprint); $this->fail('Invalid history replaced last good data'); }
                catch (\RuntimeException) { $this->assertSame($saved, $store->read()); }
            }
        } finally { \Illuminate\Support\Facades\File::delete($path); }
    }

    public function test_nightly_history_schedule_uses_three_am_sast_background_and_overlap_protection(): void
    {
        $events = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())
            ->filter(fn ($event) => str_contains($event->command ?? '', 'player-rating-history:refresh'));
        $this->assertCount(1, $events);
        $event = $events->first();
        $this->assertSame('0 3 * * *', $event->expression);
        $this->assertSame('Africa/Johannesburg', $event->timezone);
        $this->assertTrue($event->withoutOverlapping);
        $this->assertTrue($event->runInBackground);
    }

    public function test_background_calculates_twelve_month_cutoffs_and_page_only_reads_saved_results(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-29', 'Africa/Johannesburg'));
        Cache::store('array')->flush();
        $ability = Mockery::mock(PlayerSharedAbilityService::class);
        $ability->shouldReceive('fingerprint')->times(3)->andReturn(str_repeat('a', 64));
        $dates = [];
        $ability->shouldReceive('historySnapshot')->times(12)->andReturnUsing(function ($date, &$sources) use (&$dates) {
            $this->assertSame(count($dates), count($sources));
            $sources[] = 'Evidence reused';
            $dates[] = $date->toDateString();
            return ['reason' => null, 'cohorts' => ['u12 boys' => [
                'ratings' => [42 => ['score' => count($dates) === 1 ? null : 50 + count($dates), 'component' => 'a']],
                'components' => ['a' => ['members' => [42, 43], 'converged' => true]],
            ]]];
        });
        $ability->shouldNotReceive('calculateSnapshot');
        $player = new Player; $player->id = 42;
        $store = Mockery::mock(PlayerRatingHistoryStore::class);
        $store->shouldReceive('policyHash')->once()->andReturn('policy');
        $service = new PlayerRatingHistoryService($ability, $store);
        $saved = $service->calculate(CarbonImmutable::today('Africa/Johannesburg'));
        $store->shouldReceive('read')->once()->andReturn($saved);
        $history = $service->forPlayer($player);
        $this->assertCount(11, $history['series']['u12 boys']);
        $this->assertSame('2025-11-30', $dates[0]);
        $this->assertSame('2026-10-29', $dates[11]);
        $this->assertSame('2026-10-29', $history['series']['u12 boys'][10]['date']);
        $this->assertSame(62, $history['series']['u12 boys'][10]['score']);
    }

    public function test_source_change_withholds_entire_history(): void
    {
        Cache::store('array')->flush();
        $ability = Mockery::mock(PlayerSharedAbilityService::class);
        $ability->shouldReceive('fingerprint')->once()->andReturn('after');
        $ability->shouldNotReceive('historySnapshot', 'validationSnapshot', 'calculateSnapshot');
        $store = Mockery::mock(PlayerRatingHistoryStore::class);
        $store->shouldReceive('read')->once()->andReturn(['source_fingerprint' => 'before']);
        $history = (new PlayerRatingHistoryService($ability, $store))->forPlayer(new Player);
        $this->assertSame([], $history['series']);
        $this->assertStringContainsString('changed', $history['reason']);
    }

    public function test_missing_saved_history_never_calculates_or_requests_refresh(): void
    {
        $ability = Mockery::mock(PlayerSharedAbilityService::class);
        $ability->shouldNotReceive('fingerprint', 'historySnapshot', 'validationSnapshot', 'calculateSnapshot');
        $store = Mockery::mock(PlayerRatingHistoryStore::class);
        $store->shouldReceive('read')->once()->andReturn(null);
        $history = (new PlayerRatingHistoryService($ability, $store))->forPlayer(new Player);
        $this->assertSame([], $history['series']);
        $this->assertStringContainsString('03:00 SAST', $history['reason']);
    }

    public function test_failed_background_calculation_does_not_replace_saved_history(): void
    {
        $history = Mockery::mock(PlayerRatingHistoryService::class);
        $history->shouldReceive('calculate')->once()->andThrow(new \RuntimeException('Withheld test calculation'));
        $this->app->instance(PlayerRatingHistoryService::class, $history);
        $store = Mockery::mock(PlayerRatingHistoryStore::class);
        $store->shouldNotReceive('replace');
        $this->app->instance(PlayerRatingHistoryStore::class, $store);
        $consistent = Mockery::mock(\App\Services\Performance\PlayerAbilityConsistentRead::class);
        $consistent->shouldReceive('run')->once()->andReturnUsing(fn ($read) => $read());
        $this->app->instance(\App\Services\Performance\PlayerAbilityConsistentRead::class, $consistent);
        $this->artisan('player-rating-history:refresh')->expectsOutput('Rating history refresh failed. The last successful build was retained.')->assertFailed();
    }

    public function test_super_admin_can_view_unlinked_player_without_feature_or_membership_lookup(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-user')->once()->andReturn(true);
        $user->shouldNotReceive('players');
        $this->assertTrue((new PlayerRatingAccess)->allows($user, new Player));
    }

    public function test_enabling_feature_does_not_grant_access_without_premium_and_cannot_bypass_player_link(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        \Illuminate\Support\Facades\DB::purge('sqlite');
        \Illuminate\Support\Facades\Schema::create('site_settings', function ($table) {
            $table->id(); $table->string('key'); $table->string('value'); $table->string('group')->nullable(); $table->timestamps();
        });
        \App\Models\SiteSetting::set(PlayerRatingAccess::SETTING, '1');
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 7;
        $user->shouldReceive('hasRole')->with('super-user')->andReturn(false);
        $user->shouldReceive('checkPermissionTo')->andReturn(false);
        $player = new Player; $player->id = 42; $player->userId = 7;
        $this->assertFalse((new PlayerRatingAccess)->allows($user, $player));
        \Illuminate\Support\Facades\Gate::define('player-premium-rating', fn () => true);
        $this->assertTrue((new PlayerRatingAccess)->allows($user, $player));
        $player->userId = 8;
        $user->shouldReceive('players->whereKey->exists')->andReturn(false);
        $this->assertFalse((new PlayerRatingAccess)->allows($user, $player));
        \App\Models\SiteSetting::set(PlayerRatingAccess::SETTING, '0');
        $player->userId = 7;
        $this->assertFalse((new PlayerRatingAccess)->allows($user, $player));
    }

    public function test_rating_endpoint_authorizes_before_calculating_any_history(): void
    {
        $access = Mockery::mock(PlayerRatingAccess::class);
        $access->shouldReceive('allows')->once()->andReturn(false);
        $this->app->instance(PlayerRatingAccess::class, $access);
        $history = Mockery::mock(PlayerRatingHistoryService::class);
        $history->shouldNotReceive('forPlayer');
        $this->app->instance(PlayerRatingHistoryService::class, $history);
        $request = \Illuminate\Http\Request::create('/');
        $request->setUserResolver(fn () => new User);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Http\Controllers\Frontend\MyTennisController::class)->rating($request, new Player);
    }

    public function test_rating_endpoint_renders_graph_and_private_response_and_explains_pending_saved_state(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-01', 'Africa/Johannesburg'));
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-user')->andReturn(true);
        $user->shouldReceive('hasAnyRole')->andReturn(false);
        $this->actingAs($user);
        $request = \Illuminate\Http\Request::create('/');
        $request->setUserResolver(fn () => $user);
        $player = new Player; $player->id = 42; $player->name = 'Rating'; $player->surname = 'Preview';
        $access = Mockery::mock(PlayerRatingAccess::class);
        $access->shouldReceive('allows')->twice()->andReturn(true);
        $this->app->instance(PlayerRatingAccess::class, $access);
        $history = Mockery::mock(PlayerRatingHistoryService::class);
        $history->shouldReceive('forPlayer')->once()->andReturn(['reason' => null, 'as_of' => '2026-10-29', 'stale' => true, 'series' => ['u12 boys' => [
            ['date' => '2026-06-30', 'index' => 7, 'score' => 48, 'component' => 'a', 'group' => 'same'],
            ['date' => '2026-08-31', 'index' => 9, 'score' => 50, 'component' => 'a', 'group' => 'same'],
            ['date' => '2026-09-30', 'index' => 10, 'score' => 52, 'component' => 'a', 'group' => 'same'],
            ['date' => '2026-10-29', 'index' => 11, 'score' => 62, 'component' => 'b', 'group' => 'changed'],
        ]]]);
        $history->shouldReceive('forPlayer')->once()->andReturn(['reason' => 'Awaiting nightly update at 03:00 SAST', 'series' => []]);
        $this->app->instance(PlayerRatingHistoryService::class, $history);
        $controller = app(\App\Http\Controllers\Frontend\MyTennisController::class);
        $response = $controller->rating($request, $player);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('<svg', $response->getContent());
        $this->assertStringContainsString('+2.0', $response->getContent());
        $this->assertSame(1, substr_count($response->getContent(), 'stroke-width="3"'));
        $this->assertStringNotContainsString('+10.0', $response->getContent());
        $this->assertStringContainsString('Rating Preview', $response->getContent());
        $this->assertMatchesRegularExpression('/x="45" y="250"[^>]*>Nov<\/text>/', $response->getContent());
        $this->assertStringContainsString('Saved through 2026-10-29', $response->getContent());
        $pending = $controller->rating($request, $player);
        $this->assertSame(200, $pending->getStatusCode());
        $this->assertStringContainsString('Awaiting nightly update at 03:00 SAST', $pending->getContent());
    }
}
