<?php

namespace Tests\Feature\Draw;

use App\Models\Draw;
use App\Models\DrawSetting;
use App\Models\Fixture;
use App\Models\Player;
use App\Models\Registration;
use App\Services\Draw\DrawFinalPlacementService;
use Tests\TestCase;

class CanonicalBracketPresentationTest extends TestCase
{
    public function test_playoff_svg_uses_shared_theme_without_duplicate_box_edges(): void
    {
        $draw = new Draw(['id' => 999]);
        $draw->setRelation('settings', new DrawSetting(['playoff_config' => []]));
        $draw->setRelation('drawFixtures', collect());
        $html = view('backend.draw.roundrobin.dynamic-bracket-svg', [
            'draw' => $draw,
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

    public function test_standard_playoffs_project_every_final_position_like_flexible_monrad(): void
    {
        $draw = new Draw(['id' => 1000]);
        $draw->setRelation('settings', new DrawSetting(['playoff_config' => [[
            'name' => 'Main Draw (1-4)', 'slug' => 'main', 'size' => 4,
            'positions' => [1, 2], 'enabled' => true,
        ]]]));

        $players = collect(['Champion', 'Runner Up', 'Third Place'])->map(function (string $name, int $index) {
            $player = new Player(['name' => $name, 'surname' => 'Player']);
            $registration = new Registration(['id' => 101 + $index]);
            $registration->setRelation('players', collect([$player]));
            return $registration;
        });

        $final = new Fixture([
            'id' => 201, 'stage' => 'MAIN', 'round' => 2, 'match_nr' => 1002,
            'position' => 1, 'winner_registration' => $players[0]->id,
            'registration1_id' => $players[0]->id, 'registration2_id' => $players[1]->id,
        ]);
        $final->setRelation('registration1', $players[0]);
        $final->setRelation('registration2', $players[1]);

        $third = new Fixture([
            'id' => 202, 'stage' => 'MAIN', 'round' => 2, 'match_nr' => 1003,
            'position' => 3, 'playoff_type' => '3rd/4th',
            'winner_registration' => $players[2]->id,
            'registration1_id' => $players[2]->id, 'registration2_id' => null,
        ]);
        $third->setRelation('registration1', $players[2]);
        $third->setRelation('registration2', null);
        $draw->setRelation('drawFixtures', collect([$final, $third]));

        $placements = app(DrawFinalPlacementService::class)->forDraw($draw);

        $this->assertSame([1, 2, 3, 4], $placements->pluck('position')->all());
        $this->assertSame(
            ['Champion Player', 'Runner Up Player', 'Third Place Player', 'Bye · no position awarded'],
            $placements->pluck('name')->all()
        );
        $this->assertSame(['resolved', 'resolved', 'resolved', 'bye'], $placements->pluck('status')->all());

        $html = view('draw.partials.final-positions', compact('draw', 'placements'))->render();
        $this->assertStringContainsString('Final positions', $html);
        $this->assertStringContainsString('Positions update automatically', $html);
    }

    public function test_legacy_eight_player_playoffs_project_all_finishing_positions(): void
    {
        $draw = new Draw(['id' => 1001]);
        $draw->setRelation('settings', new DrawSetting(['playoff_config' => []]));
        $registrations = collect(range(1, 8))->map(function (int $position) {
            $player = new Player(['name' => "Position {$position}", 'surname' => 'Player']);
            $registration = new Registration(['id' => 300 + $position]);
            $registration->setRelation('players', collect([$player]));
            return $registration;
        });

        $fixtures = collect([
            ['stage' => 'F', 'match_nr' => 7, 'start' => 0],
            ['stage' => '3/4', 'match_nr' => 12, 'start' => 2],
            ['stage' => 'C-F', 'match_nr' => 10, 'start' => 4],
            ['stage' => '7/8', 'match_nr' => 11, 'start' => 6],
        ])->map(function (array $definition) use ($registrations) {
            $first = $registrations[$definition['start']];
            $second = $registrations[$definition['start'] + 1];
            $fixture = new Fixture([
                'stage' => $definition['stage'],
                'match_nr' => $definition['match_nr'],
                'round' => 3,
                'bracket_id' => 1,
                'registration1_id' => $first->id,
                'registration2_id' => $second->id,
                'winner_registration' => $first->id,
            ]);
            $fixture->setRelation('registration1', $first);
            $fixture->setRelation('registration2', $second);
            return $fixture;
        });
        $draw->setRelation('drawFixtures', $fixtures);

        $placements = app(DrawFinalPlacementService::class)->forDraw($draw);

        $this->assertSame(range(1, 8), $placements->pluck('position')->all());
        $this->assertSame($registrations->pluck('id')->all(), $placements->pluck('registration_id')->all());
        $this->assertTrue($placements->every(fn (array $placement) => $placement['status'] === 'resolved'));
    }
}
