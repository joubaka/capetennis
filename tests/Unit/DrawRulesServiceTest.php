<?php

namespace Tests\Unit;

use App\Domain\Draws\Services\DrawRulesService;
use App\Domain\Draws\Services\TennisScoreFormat;
use App\Models\Draw;
use App\Models\DrawSetting;
use App\Models\FlexibleMonradDraw;
use PHPUnit\Framework\TestCase;

class DrawRulesServiceTest extends TestCase
{
    private function draw(array $settings): Draw
    {
        $draw = new Draw;
        $draw->setRelation('settings', new DrawSetting($settings));
        $draw->setRelation('flexibleMonrad', null);
        return $draw;
    }

    public function test_round_robin_only_hides_playoffs_and_disabled_notes_without_hiding_generated_rules(): void
    {
        $draw = $this->draw(['workflow' => 'round_robin', 'score_format' => TennisScoreFormat::ONE_FULL_SET,
            'notes' => ['general' => 'Bring water.', 'round_robin' => 'Hidden note', 'bracket_main' => 'Old bracket note'],
            'notes_print' => ['round_robin' => false]]);
        $rules = (new DrawRulesService)->printable($draw);
        $this->assertSame(['General rules', 'Configured match format', 'Round Robin scoring rules'], array_keys($rules));
        $this->assertStringContainsString('tiebreak at 6-all', $rules['Configured match format']);
        $this->assertStringContainsString('percentage of sets won', $rules['Round Robin scoring rules']);
        $this->assertStringNotContainsString('Hidden note', implode('', $rules));
    }

    public function test_brackets_follow_saved_config_and_format_changes_without_stale_default_notes(): void
    {
        $draw = $this->draw(['workflow' => 'round_robin_playoffs', 'score_format' => TennisScoreFormat::SHORT_SET_4,
            'notes' => ['round_robin' => "Round Robin Match Format\n\n".TennisScoreFormat::rules(TennisScoreFormat::ONE_FULL_SET), 'general' => 'Custom rules remain.'],
            'playoff_config' => [
                ['slug' => 'main', 'name' => 'Championship', 'enabled' => true, 'positions' => [1, 2], 'size' => 4, 'group_order' => ['B', 'A']],
                ['slug' => 'plate', 'name' => 'Plate', 'enabled' => false],
            ]]);
        $service = new DrawRulesService;
        $rules = $service->printable($draw);
        $this->assertArrayNotHasKey('Plate rules', $rules);
        $this->assertStringContainsString('1, 2', $rules['Championship rules']);
        $this->assertStringContainsString('B, A', $rules['Championship rules']);
        $this->assertStringNotContainsString('6-all', implode('', $rules));
        $this->assertSame('Custom rules remain.', $rules['General rules']);
        $draw->settings->score_format = TennisScoreFormat::MATCH_TIEBREAK_10;
        $this->assertStringContainsString('won by 2 points', $service->printable($draw)['Configured match format']);
    }

    public function test_legacy_warning_stays_out_of_print_and_custom_notes_are_preserved(): void
    {
        $warning = 'Legacy scoring is active. Select an enforceable Match Scoring Format in Settings before publishing these rules.';
        $draw = $this->draw(['workflow' => 'round_robin', 'notes' => ['round_robin' => "Round Robin Match Format\n\n".$warning, 'general' => "Custom note\n".$warning]]);
        $sections = (new DrawRulesService)->sections($draw);
        $this->assertSame('', $sections[1]['notes']);
        $this->assertSame("Custom note\n".$warning, $sections[0]['notes']);
    }

    public function test_flexible_monrad_has_placement_rules_and_no_round_robin_or_default_plate(): void
    {
        $draw = $this->draw(['workflow' => 'custom_monrad']);
        $draw->setRelation('flexibleMonrad', new FlexibleMonradDraw(['draft' => ['mode' => 'monrad']]));
        $rules = (new DrawRulesService)->printable($draw);
        $this->assertSame(['Placement draw rules'], array_keys($rules));
        $this->assertStringContainsString('Winners and losers', $rules['Placement draw rules']);
        $draw->flexibleMonrad->draft = ['mode' => 'playoffs'];
        $this->assertArrayHasKey('Playoff rules', (new DrawRulesService)->printable($draw));
    }

    public function test_duplicate_bracket_names_do_not_overwrite_printed_rules(): void
    {
        $draw = $this->draw(['workflow' => 'round_robin_playoffs', 'playoff_config' => [
            ['slug' => 'main', 'name' => 'Finals', 'enabled' => true, 'positions' => [1], 'size' => 4],
            ['slug' => 'plate', 'name' => 'Finals', 'enabled' => true, 'positions' => [2], 'size' => 4],
        ]]);
        $rules = (new DrawRulesService)->printable($draw);
        $this->assertStringContainsString('positions: 1.', $rules['Finals rules']);
        $this->assertStringContainsString('positions: 2.', $rules['Finals rules (bracket_plate)']);
    }
}
