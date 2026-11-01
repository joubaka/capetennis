<?php

namespace Tests\Unit;

use App\Services\Performance\PlayerRatingReadBudget;
use PHPUnit\Framework\Attributes\{PreserveGlobalState, RunInSeparateProcess};
use PHPUnit\Framework\TestCase;

class PlayerRatingReadBudgetTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_low_process_budget_is_raised_before_reading_private_graphs(): void
    {
        $this->assertNotFalse(ini_set('memory_limit', '128M'));
        $this->assertTrue((new PlayerRatingReadBudget)->ensure());
        $this->assertGreaterThanOrEqual(PlayerRatingReadBudget::MIN_BYTES, ini_parse_quantity(ini_get('memory_limit')));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_larger_process_budget_is_preserved_exactly(): void
    {
        $this->assertNotFalse(ini_set('memory_limit', '768M'));
        $this->assertTrue((new PlayerRatingReadBudget)->ensure());
        $this->assertSame('768M', ini_get('memory_limit'));
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_unlimited_process_budget_is_preserved_exactly(): void
    {
        $this->assertNotFalse(ini_set('memory_limit', '-1'));
        $this->assertTrue((new PlayerRatingReadBudget)->ensure());
        $this->assertSame('-1', ini_get('memory_limit'));
    }
}
