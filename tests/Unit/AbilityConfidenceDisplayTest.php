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
    public function test_high_is_restricted_by_structure_and_reference_without_changing_the_index(): void
    {
        $clean = ['confidence_index' => 92, 'played' => 15, 'component_inferred' => 0, 'bridge_count' => 0, 'baseline_status' => 'Direct main-trial baseline'];
        $this->assertSame('High', AbilityConfidenceDisplay::normalize($clean)['confidence_band']);
        foreach ([['component_inferred' => 1], ['bridge_count' => 1], ['baseline_status' => 'Linked to main-trial baseline'], ['baseline_status' => 'No connected main-trial baseline'], ['played' => 0]] as $weak) {
            $display = AbilityConfidenceDisplay::normalize(array_replace($clean, $weak));
            $this->assertSame(isset($weak['played']) ? 'Low' : 'Medium', $display['confidence_band']);
            $this->assertSame(92, $display['confidence_index']);
            $this->assertStringContainsString('Caution:', $display['confidence_explanation']);
        }
        $this->assertSame('Medium', AbilityConfidenceDisplay::normalize(['confidence_index' => 92])['confidence_band']);
        $this->assertSame('Low', AbilityConfidenceDisplay::normalize(array_replace($clean, ['confidence_index' => 12]))['confidence_band']);
    }}
