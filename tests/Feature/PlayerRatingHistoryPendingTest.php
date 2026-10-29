<?php

namespace Tests\Feature;

use App\Models\{Player, User};
use App\Services\Performance\{PlayerAbilitySnapshotStore, PlayerRatingHistoryService, PlayerRatingHistoryStore, PlayerSharedAbilityService};
use Mockery;
use Tests\TestCase;

class PlayerRatingHistoryPendingTest extends TestCase
{
    public function test_background_build_captures_context_for_every_month_not_only_latest_month(): void
    {
        $ability = Mockery::mock(PlayerSharedAbilityService::class);
        $ability->shouldReceive('fingerprint')->twice()->andReturn(str_repeat('a', 64));
        $month = 0;
        $ability->shouldReceive('historySnapshot')->times(12)->andReturnUsing(function () use (&$month) {
            $month++;
            return ['reason' => null, 'cohorts' => [], 'source_events' => [$month], 'source_matches' => ['match:'.$month]];
        });
        $snapshots = Mockery::mock(PlayerAbilitySnapshotStore::class);
        $manifest = ['events' => [['id' => 1, 'published' => true]]];
        $snapshots->shouldReceive('manifest')->once()->with(Mockery::on(fn ($context) =>
            $context['source_events'] === range(1, 12) && $context['source_matches'] === array_map(fn ($id) => 'match:'.$id, range(1, 12))))->andReturn($manifest);
        $this->app->instance(PlayerAbilitySnapshotStore::class, $snapshots);
        $store = Mockery::mock(PlayerRatingHistoryStore::class);
        $store->shouldReceive('policyHash')->once()->andReturn('policy');
        $saved = (new PlayerRatingHistoryService($ability, $store))->calculate(\Carbon\CarbonImmutable::today('Africa/Johannesburg'));
        $this->assertSame($manifest, $saved['publication_manifest']);
    }

    public function test_super_admin_keeps_legacy_calculation_visible_while_sources_change(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-user')->andReturn(true);
        $user->shouldReceive('hasAnyRole')->andReturn(false);
        $this->actingAs($user);
        $history = $this->history();
        $this->assertNull($history['reason']);
        $this->assertSame(61.5, $history['point']['score']);
        $this->assertTrue($history['stale']);
        $player = new Player;
        $player->setRawAttributes(['id' => 42, 'name' => 'Test', 'surname' => 'Player']);
        $html = view('frontend.my-tennis.rating', ['ratingPlayer' => $player, 'ratingHistory' => $history])->render();
        $this->assertStringContainsString('61.5', $html);
        $this->assertStringContainsString('<svg', $html);
        $this->assertStringContainsString('Showing the latest saved calculation', $html);
        $this->assertStringNotContainsString('source results or publication changed', $html);
    }

    public function test_legacy_history_without_captured_context_remains_withheld_for_other_viewers(): void
    {
        $this->assertNotNull($this->history()['reason']);
    }

    public function test_pending_history_uses_captured_publication_context_for_other_viewers(): void
    {
        $snapshotStore = Mockery::mock(PlayerAbilitySnapshotStore::class);
        $snapshotStore->shouldReceive('published')->with(['events' => []])->once()->andReturn(true);
        $this->app->instance(PlayerAbilitySnapshotStore::class, $snapshotStore);
        $history = $this->history(['publication_manifest' => ['events' => []]]);
        $this->assertNull($history['reason']);
        $this->assertSame(61.5, $history['point']['score']);
    }

    public function test_revoked_captured_context_withholds_history_even_for_super_admin(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-user')->andReturn(true);
        $this->actingAs($user);
        $snapshotStore = Mockery::mock(PlayerAbilitySnapshotStore::class);
        $snapshotStore->shouldReceive('published')->once()->andReturn(false);
        $this->app->instance(PlayerAbilitySnapshotStore::class, $snapshotStore);
        $history = $this->history(['publication_manifest' => ['events' => []]]);
        $this->assertSame([], $history['series']);
        $this->assertNotNull($history['reason']);
    }

    private function history(array $extra = []): array
    {
        $ability = Mockery::mock(PlayerSharedAbilityService::class);
        $ability->shouldReceive('fingerprint')->once()->andReturn('after');
        $ability->shouldNotReceive('historySnapshot', 'calculateSnapshot');
        $store = Mockery::mock(PlayerRatingHistoryStore::class);
        $months = [];
        for ($index = 0; $index < 12; $index++) {
            $months[] = ['date' => '2026-10-01', 'cohorts' => ['u13 boys' => [42 => ['score' => 61.5, 'group' => 'same', 'component' => 'a']]]];
        }
        $store->shouldReceive('read')->once()->andReturn($extra + ['source_fingerprint' => 'before', 'as_of' => '2026-10-01',
            'built_at' => '2026-10-01T03:00:00+02:00', 'months' => $months, 'evidence' => []]);
        $player = new Player;
        $player->id = 42;
        return (new PlayerRatingHistoryService($ability, $store))->forPlayer($player);
    }
}
