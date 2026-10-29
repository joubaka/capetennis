<?php

namespace Tests\Feature;

use App\Services\Performance\{PlayerAbilityConsistentRead, PlayerAbilityRefreshState, PlayerAbilitySnapshotStore, PlayerSharedAbilityService};
use Tests\TestCase;

class AllAgeRatingRefreshTest extends TestCase
{
    public function test_dry_run_rejects_invalid_shared_graph_and_applies_only_a_process_memory_floor(): void
    {
        config(['cache.stores.file' => ['driver' => 'array']]);
        $originalLimit = ini_get('memory_limit');
        ini_set('memory_limit', '128M');
        $this->mock(PlayerAbilityConsistentRead::class)->shouldReceive('run')->once()->andReturnUsing(fn ($read) => $read());
        $this->mock(PlayerAbilityRefreshState::class)->shouldReceive('generation')->once()->andReturn(null);
        $service = $this->mock(PlayerSharedAbilityService::class);
        $service->shouldReceive('fingerprint')->twice()->andReturn('same');
        $service->shouldReceive('calculateSnapshot')->once()->andReturnUsing(function () {
            $this->assertGreaterThanOrEqual(512 * 1024 * 1024, ini_parse_quantity(ini_get('memory_limit')));
            return ['cohorts' => [], 'all_age' => ['ratings' => [], 'components' => [], 'players' => [], 'reference_component' => 'missing'], 'reason' => null];
        });
        $store = $this->partialMock(PlayerAbilitySnapshotStore::class);
        $store->shouldReceive('manifest')->once()->andReturn([]);
        $store->shouldNotReceive('replace', 'published');
        try {
            $this->artisan('player-ability:refresh --dry-run')->assertFailed();
            $this->assertSame('128M', ini_get('memory_limit'));
        } finally {
            ini_set('memory_limit', $originalLimit);
        }
    }
}
