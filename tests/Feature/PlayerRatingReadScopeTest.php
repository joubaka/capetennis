<?php

namespace Tests\Feature;

use App\Services\Performance\{PlayerAbilitySnapshotStore, PlayerMatchHistoricalContext, PlayerRatingHistoryStore, PlayerRatingReadScope, PlayerSharedAbilityService, TeamSheetTennisEvidence};
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\{DB, File, Route as Routes, Schema};
use Tests\TestCase;

class PlayerRatingReadScopeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'database.connections.sqlite.url' => null]);
        DB::purge('sqlite');
    }

    private function request(string $method = 'GET', bool $routed = true): Request
    {
        $request = Request::create('/my-tennis', $method);
        if ($routed) $request->setRouteResolver(fn () => new Route($method, 'my-tennis', fn () => null));
        $this->app->instance('request', $request);
        return $request;
    }

    private function counter(): PlayerSharedAbilityService
    {
        return new class extends PlayerSharedAbilityService {
            public int $reads = 0;
            protected function exactFingerprint(): string { return hash('sha256', (string) ++$this->reads); }
        };
    }

    public function test_exact_source_scan_is_reused_only_during_one_get_response(): void
    {
        $request = $this->request();
        $scope = app(PlayerRatingReadScope::class);
        $ability = $this->counter();
        $scope->run($request, function () use ($scope, $request, $ability) {
            $first = $ability->fingerprint();
            $this->assertSame($first, $ability->fingerprint());
            $scope->run($request, fn () => $this->assertSame($first, $ability->fingerprint()));
            $this->assertSame(1, $ability->reads);
        });
        $this->assertSame(1, $ability->reads);
        $after = $ability->fingerprint();
        $this->assertSame(2, $ability->reads, 'Outside the response scope exact reads remain fresh.');
        $scope->run($this->request(), function () use ($ability, $after) {
            $this->assertNotSame($after, $ability->fingerprint());
            $this->assertSame(3, $ability->reads, 'A new HTTP request always scans again.');
        });
    }

    public function test_post_and_unrouted_command_contexts_never_reuse_fingerprint_checks(): void
    {
        foreach ([$this->request('POST'), $this->request('GET', false)] as $request) {
            $this->app->instance('request', $request);
            $ability = $this->counter();
            app(PlayerRatingReadScope::class)->run($request, function () use ($ability) {
                $this->assertNotSame($ability->fingerprint(), $ability->fingerprint());
                $this->assertSame(2, $ability->reads);
            });
        }
    }

    public function test_exception_clears_scope_and_does_not_reuse_a_failed_or_previous_read(): void
    {
        $request = $this->request();
        $scope = app(PlayerRatingReadScope::class);
        $ability = $this->counter();
        try {
            $scope->run($request, function () use ($ability) { $ability->fingerprint(); throw new \RuntimeException('Expected test failure'); });
        } catch (\RuntimeException) {}
        $ability->fingerprint();
        $this->assertSame(2, $ability->reads);
        $scope->run($request, function () use ($scope) {
            $attempts = 0;
            try { $scope->remember('failing', function () use (&$attempts) { $attempts++; throw new \RuntimeException; }); }
            catch (\RuntimeException) {}
            $this->assertSame('fresh', $scope->remember('failing', function () use (&$attempts) { $attempts++; return 'fresh'; }));
            $this->assertSame(2, $attempts);
        });
    }

    public function test_validated_history_is_decoded_once_per_get_but_rechecked_next_request(): void
    {
        $request = $this->request();
        $store = new class extends PlayerRatingHistoryStore {
            public function path(): string { return 'fictional-private-history.json'; }
            public function policyHash(): string { return str_repeat('a', 64); }
        };
        $today = CarbonImmutable::today('Africa/Johannesburg');
        $months = [];
        for ($index = 0; $index < 12; $index++) {
            $date = $index === 11 ? $today : $today->startOfMonth()->subMonths(11 - $index)->endOfMonth();
            $months[] = ['date' => $date->toDateString(), 'cohorts' => []];
        }
        $saved = ['version' => PlayerRatingHistoryStore::VERSION, 'policy_hash' => str_repeat('a', 64), 'source_fingerprint' => str_repeat('b', 64),
            'as_of' => $today->toDateString(), 'built_at' => 'First private build', 'months' => $months, 'evidence' => []];
        File::shouldReceive('exists')->with($store->path())->twice()->andReturnTrue();
        File::shouldReceive('size')->with($store->path())->twice()->andReturn(1000);
        File::shouldReceive('get')->with($store->path())->twice()->andReturn(json_encode($saved), json_encode(array_replace($saved, ['built_at' => 'New private build'])));
        app(PlayerRatingReadScope::class)->run($request, function () use ($store) {
            $this->assertSame('First private build', $store->read()['built_at']);
            $this->assertSame('First private build', $store->read()['built_at']);
        });
        app(PlayerRatingReadScope::class)->run($this->request(), fn () => $this->assertSame('New private build', $store->read()['built_at']));
    }

    public function test_unavailable_history_is_reused_as_unavailable_within_one_response_only(): void
    {
        $request = $this->request();
        $store = new class extends PlayerRatingHistoryStore { public function path(): string { return 'missing-private-history.json'; } };
        File::shouldReceive('exists')->with($store->path())->twice()->andReturnFalse();
        File::shouldNotReceive('get');
        app(PlayerRatingReadScope::class)->run($request, function () use ($store) {
            $this->assertNull($store->read()); $this->assertNull($store->read());
        });
        app(PlayerRatingReadScope::class)->run($this->request(), fn () => $this->assertNull($store->read()));
    }

    public function test_my_tennis_controller_middleware_rechecks_changed_source_on_the_next_http_get(): void
    {
        // Exercise the real My Tennis constructor/middleware without a real player,
        // database result set or any rating builder. This route exists only in this test.
        Routes::middleware('web')->get('/_test/my-tennis-read-probe', [MyTennisReadScopeProbeController::class, 'probe']);
        $ability = new class extends PlayerSharedAbilityService {
            public int $reads = 0;
            public string $publication = 'published-source-one';
            protected function exactFingerprint(): string { $this->reads++; return hash('sha256', $this->publication); }
        };
        $this->app->instance(PlayerSharedAbilityService::class, $ability);
        $store = new class extends PlayerRatingHistoryStore { public function path(): string { return 'request-probe-missing.json'; } };
        $this->app->instance(PlayerRatingHistoryStore::class, $store);
        File::shouldReceive('exists')->with($store->path())->twice()->andReturnFalse();
        $actor = new \App\Models\User; $actor->id = 42;
        $this->actingAs($actor);
        $first = $this->getJson('/_test/my-tennis-read-probe')->assertOk()->json();
        $this->assertSame($first['first'], $first['second']);
        $this->assertSame(1, $ability->reads);
        $ability->publication = 'source-publication-changed';
        $next = $this->getJson('/_test/my-tennis-read-probe')->assertOk()->json();
        $this->assertNotSame($first['first'], $next['first']);
        $this->assertSame(2, $ability->reads);
    }

    public function test_publication_validation_runs_once_for_an_exact_manifest_and_rechecks_revocation_next_get(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id(); $table->string('name'); $table->boolean('published');
        });
        DB::table('events')->insert(['id' => 1, 'name' => 'Published example', 'published' => true]);
        $manifest = ['events' => [['id' => 1, 'name' => 'Published example', 'published' => 1]]];
        $changed = ['events' => [['id' => 1, 'name' => 'A different saved title', 'published' => 1]]];
        $scope = app(PlayerRatingReadScope::class);
        $store = app(PlayerAbilitySnapshotStore::class);
        DB::enableQueryLog();
        $scope->run($this->request(), function () use ($store, $manifest, $changed) {
            foreach (range(1, 3) as $_) $this->assertTrue($store->published($manifest));
            $this->assertFalse($store->published($changed), 'A different manifest never reuses a positive validation.');
        });
        $this->assertCount(2, DB::getQueryLog(), 'Three identical checks use one database query; a distinct manifest uses its own.');
        DB::disableQueryLog();
        DB::table('events')->where('id', 1)->update(['published' => false]);
        $scope->run($this->request(), fn () => $this->assertFalse($store->published($manifest), 'The next GET observes publication revocation.'));
    }

    public function test_offline_and_post_publication_checks_never_reuse_previous_results(): void
    {
        Schema::create('events', function (Blueprint $table) { $table->id(); $table->boolean('published'); });
        $store = app(PlayerAbilitySnapshotStore::class);
        $manifest = ['events' => [['id' => 1, 'published' => 1]]];
        foreach ([$this->request('POST'), $this->request('GET', false)] as $request) {
            $this->app->instance('request', $request);
            DB::table('events')->updateOrInsert(['id' => 1], ['published' => true]);
            app(PlayerRatingReadScope::class)->run($request, function () use ($store, $manifest) {
                $this->assertTrue($store->published($manifest));
                DB::table('events')->where('id', 1)->update(['published' => false]);
                $this->assertFalse($store->published($manifest));
            });
        }
    }

    public function test_external_fingerprint_and_historical_context_are_reused_only_for_the_exact_get_input(): void
    {
        $external = new class extends TeamSheetTennisEvidence {
            public int $reads = 0;
            protected function exactFingerprint(): string { return hash('sha256', (string) ++$this->reads); }
        };
        $history = new class extends PlayerMatchHistoricalContext {
            public int $reads = 0;
            protected function captureFresh(string $asOf): array {
                return ['version' => self::VERSION, 'as_of' => $asOf, 'fingerprint' => hash('sha256', (string) ++$this->reads)];
            }
        };
        $scope = app(PlayerRatingReadScope::class);
        $scope->run($this->request(), function () use ($external, $history) {
            $this->assertSame($external->fingerprint(), $external->fingerprint());
            $first = $history->capture('2026-11-19');
            $this->assertSame($first, $history->capture('2026-11-19'));
            $this->assertNotSame($first, $history->capture('2026-11-20'), 'Different historical cutoffs require distinct reads.');
            $this->assertSame(1, $external->reads);
            $this->assertSame(2, $history->reads);
        });
        $scope->run($this->request(), function () use ($external, $history) {
            $external->fingerprint(); $history->capture('2026-11-19');
            $this->assertSame(2, $external->reads);
            $this->assertSame(3, $history->reads);
        });
        foreach ([$this->request('POST'), $this->request('GET', false)] as $request) {
            $this->app->instance('request', $request);
            $scope->run($request, function () use ($external, $history) {
                $this->assertNotSame($external->fingerprint(), $external->fingerprint());
                $this->assertNotSame($history->capture('2026-11-19'), $history->capture('2026-11-19'));
            });
        }
    }
}

class MyTennisReadScopeProbeController extends \App\Http\Controllers\Frontend\MyTennisController
{
    public function probe()
    {
        $ability = app(PlayerSharedAbilityService::class);
        $history = app(PlayerRatingHistoryStore::class);
        $history->read(); $history->read();
        return response()->json(['first' => $ability->fingerprint(), 'second' => $ability->fingerprint()]);
    }
}
