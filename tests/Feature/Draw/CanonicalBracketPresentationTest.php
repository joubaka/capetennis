<?php

namespace Tests\Feature\Draw;

use Tests\TestCase;

class CanonicalBracketPresentationTest extends TestCase
{
    public function test_playoff_svg_uses_shared_theme_without_duplicate_box_edges(): void
    {
        $html = view('backend.draw.roundrobin.dynamic-bracket-svg', [
            'emptyBracket' => true,
            'svgData' => [
                'totalWidth' => 500, 'totalHeight' => 300,
                'brackets' => [[
                    'name' => 'Main', 'numRounds' => 1,
                    'rounds' => [1 => [[
                        'fx' => null, 'x' => 60, 'y' => 70, 'width' => 190, 'height' => 60,
                        'seedLabel1' => 'A1', 'seedLabel2' => 'B2',
                    ]]],
                    'positionPlayoffs' => [[
                        'fx' => null, 'x' => 60, 'y' => 200, 'width' => 190, 'height' => 60,
                        'label' => '3rd place', 'isFinal' => true,
                        'feederLabel1' => 'L1', 'feederLabel2' => 'L2',
                    ]],
                ]],
            ],
        ])->render();

        $this->assertStringContainsString('class="ct-bracket-svg"', $html);
        $this->assertStringContainsString(file_get_contents(public_path('css/tennis-bracket.css')), $html);
        $this->assertStringContainsString('class="match-row-bg" fill="#ffffff"', $html);
        $this->assertStringNotContainsString('stroke="#e2e8f0"', $html);
        $this->assertStringNotContainsString('stroke-width="0.8"', $html);
        $this->assertStringNotContainsString('stroke-width: 2px', $html);
        $this->assertStringContainsString('A1', $html);
        $this->assertStringContainsString('B2', $html);
        $this->assertStringContainsString('L1', $html);
        $this->assertSame(2, substr_count($html, 'class="match-row-bg"'));
        // A public/anonymous viewer must not gain score controls from presentation.
        $this->assertStringNotContainsString('class="match-hit bracket-score-btn"', $html);
        $this->assertStringContainsString('.ct-bracket-svg .player-identity-bg', $html);
        $this->assertStringContainsString('.ct-bracket-svg .automatic-note', $html);
    }

    public function test_shared_assets_are_emitted_once_and_cache_busted(): void
    {
        $html = \Illuminate\Support\Facades\Blade::render(
            "@include('draw.partials.bracket-assets') @include('draw.partials.bracket-assets')"
        );
        $this->assertSame(1, substr_count($html, 'css/tennis-bracket.css?v='));
        $this->assertSame(1, substr_count($html, 'js/tennis-bracket.js?v='));
    }

    public function test_all_active_playoff_renderers_share_monrad_player_identity_styles(): void
    {
        $css = file_get_contents(public_path('css/tennis-bracket.css'));
        $interproMatch = file_get_contents(resource_path('views/svg/match.blade.php'));
        $legacyPlate = file_get_contents(resource_path('views/backend/draw/roundrobin/plate-bracket.blade.php'));

        $this->assertStringContainsString('.player-identity-bg', $css);
        $this->assertStringContainsString('.player-identity-text', $css);
        $this->assertStringContainsString('.bracket-winner', $css);
        $this->assertStringContainsString("draw.partials.svg-player-identity", $interproMatch);
        $this->assertStringContainsString("draw.partials.svg-player-identity", $legacyPlate);
        $this->assertStringContainsString('BYE · ADVANCES', $interproMatch);
    }
}
