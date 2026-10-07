<?php
namespace Tests\Unit;

use App\Services\Performance\AbilityConfidenceDisplay;
use PHPUnit\Framework\TestCase;

class AbilityConfidenceDisplayTest extends TestCase
{
    public function test_saved_indices_map_at_display_boundary_without_recalculating(): void
    {
        foreach ([0 => 'Low', 19 => 'Low', 39 => 'Low', 40 => 'Medium', 69 => 'Medium', 70 => 'High', 100 => 'High'] as $index => $label) {
            $this->assertSame($label, AbilityConfidenceDisplay::label($index));
        }
    }
}
