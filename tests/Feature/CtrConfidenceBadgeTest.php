<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class CtrConfidenceBadgeTest extends TestCase
{
    public function test_percentages_and_colours_use_recorded_evidence_confidence(): void
    {
        foreach ([[0, 'Low', '#8c2820'], [39, 'Low', '#8c2820'], [40, 'Medium', '#735000'], [69, 'Medium', '#735000'], [70, 'High', '#1d6240'], [100, 'High', '#1d6240']] as [$index, $label, $colour]) {
            $rating = ['confidence_index' => $index, 'confidence_label' => $label];
            $html = Blade::render('<x-ctr-confidence-badge :rating="$rating" />', compact('rating'));
            $this->assertStringContainsString($index.'% evidence confidence', $html);
            $this->assertStringContainsString($colour, $html);
            $this->assertStringContainsString('not rating accuracy or chance of winning', $html);
        }
    }

    public function test_missing_values_are_not_fabricated_and_policy_cautions_keep_their_colour(): void
    {
        foreach ([null, INF, NAN, -1, 101, 'bad'] as $index) {
            $rating = ['confidence_index' => $index, 'confidence_label' => 'Low'];
            $html = Blade::render('<x-ctr-confidence-badge :rating="$rating" />', compact('rating'));
            $this->assertStringContainsString('Low evidence confidence', $html);
            $this->assertDoesNotMatchRegularExpression('/\d+% evidence confidence/', $html);
        }
        $rating = ['confidence_index' => 75, 'confidence_label' => 'Medium', 'confidence_explanation' => '<script>unsafe</script>'];
        $html = Blade::render('<x-ctr-confidence-badge :rating="$rating" />', compact('rating'));
        $this->assertStringContainsString('75% evidence confidence', $html);
        $this->assertStringContainsString('#735000', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }
}
