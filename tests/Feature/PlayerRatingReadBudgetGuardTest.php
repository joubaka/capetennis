<?php

namespace Tests\Feature;

use App\Services\Performance\{PlayerAbilitySnapshotStore, PlayerRatingHistoryStore, PlayerRatingReadBudget};
use Illuminate\Support\Facades\{Bus, DB, File, Schema};
use Tests\TestCase;

class PlayerRatingReadBudgetGuardTest extends TestCase
{
    public function test_denied_ability_budget_withholds_data_before_fetching_any_private_payload(): void
    {
        Schema::shouldReceive('hasTable')->once()->with('player_ability_snapshots')->andReturn(true);
        $this->mock(PlayerRatingReadBudget::class)->shouldReceive('ensure')->once()->andReturn(false);
        DB::shouldReceive('table')->never();
        Bus::fake();
        $store = new PlayerAbilitySnapshotStore;
        $snapshot = $store->current();
        $this->assertSame([], $snapshot['cohorts']);
        $this->assertSame([], $snapshot['names']);
        $this->assertNull($snapshot['snapshot_version']);
        $this->assertTrue($snapshot['snapshot_stale']);
        $this->assertStringContainsString('temporarily unavailable', $snapshot['reason']);
        $this->assertSame($snapshot, $store->current());
        Bus::assertNothingDispatched();
    }

    public function test_missing_ability_schema_does_not_attempt_to_raise_budget_or_fetch_data(): void
    {
        Schema::shouldReceive('hasTable')->once()->with('player_ability_snapshots')->andReturn(false);
        $this->mock(PlayerRatingReadBudget::class)->shouldNotReceive('ensure');
        DB::shouldReceive('table')->never();
        $this->assertNull((new PlayerAbilitySnapshotStore)->current()['snapshot_version']);
    }

    public function test_denied_history_budget_withholds_data_before_reading_or_decoding_the_private_file(): void
    {
        $store = new PlayerRatingHistoryStore;
        File::shouldReceive('exists')->once()->with($store->path())->andReturn(true);
        File::shouldReceive('size')->once()->with($store->path())->andReturn(1024);
        File::shouldReceive('get')->never();
        $this->mock(PlayerRatingReadBudget::class)->shouldReceive('ensure')->once()->andReturn(false);
        Bus::fake();
        $this->assertNull($store->read());
        Bus::assertNothingDispatched();
    }

    public function test_missing_history_file_does_not_attempt_to_raise_budget(): void
    {
        $store = new PlayerRatingHistoryStore;
        File::shouldReceive('exists')->once()->with($store->path())->andReturn(false);
        File::shouldReceive('size')->never();
        File::shouldReceive('get')->never();
        $this->mock(PlayerRatingReadBudget::class)->shouldNotReceive('ensure');
        $this->assertNull($store->read());
    }

    public function test_history_size_limit_still_precedes_budget_increase_and_file_read(): void
    {
        $store = new PlayerRatingHistoryStore;
        File::shouldReceive('exists')->once()->with($store->path())->andReturn(true);
        File::shouldReceive('size')->once()->with($store->path())->andReturn(20 * 1024 * 1024 + 1);
        File::shouldReceive('get')->never();
        $this->mock(PlayerRatingReadBudget::class)->shouldNotReceive('ensure');
        $this->assertNull($store->read());
    }
}
