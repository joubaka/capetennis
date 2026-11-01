<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class CtrBadgePresentationTest extends TestCase
{
    public function test_badge_names_ctr_and_visibly_distinguishes_local_scores(): void
    {
        $shared = Blade::render('<x-ctr-badge :compact="true" :score="$score" />', ['score' => 72.4]);
        $this->assertStringContainsString('CTR 72.4', $shared);
        $this->assertStringContainsString('72.4/100', preg_replace('/\s+/', '', strip_tags($shared)));
        $this->assertStringNotContainsString('within-group only', $shared);
        $local = Blade::render('<x-ctr-badge :compact="true" :score="$score" :local="true" :title="$title" />', ['score' => 93.1, 'title' => '<script>unsafe</script>']);
        $this->assertStringContainsString('within-group only', $local);
        $this->assertStringContainsString('93.1/100', preg_replace('/\s+/', '', strip_tags($local)));
        $this->assertStringNotContainsString('<script>', $local);
        $this->assertStringContainsString('&lt;script&gt;', $local);
    }

    public function test_missing_or_nonfinite_scores_cannot_appear_as_numeric_ctr(): void
    {
        foreach ([null, 'not-a-score', INF, NAN] as $score) {
            $html = Blade::render('<x-ctr-badge :compact="true" :score="$score" />', compact('score'));
            $this->assertStringContainsString('unavailable', $html);
            $this->assertStringNotContainsString('/100', $html);
        }
    }
}
