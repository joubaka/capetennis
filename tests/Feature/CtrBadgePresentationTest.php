<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Mockery;
use Tests\TestCase;

class CtrBadgePresentationTest extends TestCase
{
    public function test_ajax_configuration_uses_the_same_stamp_component_for_both_variants(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('hasRole')->with('super-user')->andReturnTrue();
        $user->shouldReceive('hasAnyRole')->andReturnFalse();
        auth()->setUser($user);

        $html = view('draw.partials.player-rating-assets')->render();
        $this->assertSame(1, preg_match('/stampTemplates:\s*(\{.*\})\s*\};/s', $html, $matches));
        $templates = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['unknown', 'red', 'orange', 'green', 'yellow', 'standard'], array_keys($templates));
        foreach ($templates as $variants) {
            $this->assertSame(['shared', 'local'], array_keys($variants));
            foreach ($variants as $template) {
                $this->assertStringContainsString('data-ctr-badge', $template);
                $this->assertStringContainsString('stroke-dasharray=', $template);
                $this->assertStringContainsString('__CTR_SCORE__', $template);
                $this->assertStringContainsString('__CTR_TITLE__', $template);
                $this->assertStringNotContainsString('98.7', $template);
            }
        }
        $this->assertStringContainsString('within-group only', $templates['unknown']['local']);
        $this->assertStringNotContainsString('within-group only', $templates['unknown']['shared']);
        $this->assertStringContainsString('Red ball', $templates['red']['shared']);
        $this->assertStringContainsString('Orange ball', $templates['orange']['local']);
    }

    public function test_explicit_ball_stage_controls_colour_independently_of_local_status(): void
    {
        foreach (['red' => '#c91822', 'orange' => '#b94a00', 'green' => '#095c60', 'yellow' => '#095c60', 'standard' => '#095c60'] as $stage => $ink) {
            foreach ([false, true] as $local) {
                $html = Blade::render('<x-ctr-badge :stage="$stage" :local="$local" :score="62.3" />', compact('stage', 'local'));
                $this->assertStringContainsString('color:'.$ink.'!important', $html);
                $this->assertStringContainsString('data-ctr-stage-known="true"', $html);
                $this->assertStringContainsString(in_array($stage, ['red', 'orange'], true) ? ucfirst($stage).' ball' : 'Green/Yellow ball', $html);
                $this->assertStringContainsString('62.3/100', preg_replace('/\s+/', '', strip_tags($html)));
                if ($local) {
                    $this->assertStringContainsString('within-group only', $html);
                }
            }
        }
        foreach ([null, 'unknown', 'u10', 'javascript:alert(1)', ['red']] as $stage) {
            $html = Blade::render('<x-ctr-badge :stage="$stage" :score="92.1" />', compact('stage'));
            $this->assertStringContainsString('color:#095c60!important', $html);
            $this->assertStringContainsString('data-ctr-stage="standard"', $html);
            $this->assertStringContainsString('data-ctr-stage-known="false"', $html);
            $this->assertStringContainsString('Ball stage not recorded', $html);
            $this->assertStringNotContainsString('Standard ball', $html);
        }
    }

    public function test_default_and_dense_variants_use_the_same_stamp_with_dynamic_scores(): void
    {
        foreach (['standard', 'small', 'inline'] as $size) {
            $shared = Blade::render('<x-ctr-badge :size="$size" :score="62.3" data-example="rating" />', compact('size'));
            $this->assertStringContainsString('data-ctr-badge', $shared);
            $this->assertStringContainsString('stroke-dasharray=', $shared);
            $this->assertStringContainsString('62.3/100', preg_replace('/\s+/', '', strip_tags($shared)));
            $this->assertStringContainsString('data-example="rating"', $shared);
            $this->assertStringNotContainsString('ctr-rating-badge', $shared);

            $local = Blade::render('<x-ctr-badge :size="$size" :score="93.1" :local="true" />', compact('size'));
            $this->assertStringContainsString('within-group only', $local);
            $this->assertStringContainsString('#754b0c', $local);
            $unrated = Blade::render('<x-ctr-badge :size="$size" />', compact('size'));
            $this->assertStringContainsString('unavailable', $unrated);
            $this->assertStringNotContainsString('/100', $unrated);
        }
    }

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
