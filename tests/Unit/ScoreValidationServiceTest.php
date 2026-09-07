<?php

namespace Tests\Unit;

use App\Domain\Draws\Services\ScoreValidationService;
use App\Domain\Draws\Services\TennisScoreFormat;
use App\Models\Draw;
use App\Models\DrawSetting;
use App\Models\Fixture;
use PHPUnit\Framework\TestCase;

class ScoreValidationServiceTest extends TestCase
{
    private function fixtureFor(string $format): Fixture
    {
        $draw = (new Draw())->setRelation('settings', new DrawSetting(['score_format' => $format]));

        return (new Fixture())->setRelation('draw', $draw);
    }

    public function test_third_set_accepts_an_extended_match_tiebreak_score(): void
    {
        $result = (new ScoreValidationService())->validate(
            new Fixture(),
            [[6, 4], [4, 6], [23, 21]],
        );

        $this->assertTrue($result['valid']);
    }

    public function test_first_and_second_sets_retain_the_existing_score_limit(): void
    {
        $result = (new ScoreValidationService())->validate(
            new Fixture(),
            [[23, 21], [6, 4]],
        );

        $this->assertFalse($result['valid']);
    }

    public function test_a_one_set_draw_rejects_an_accidental_second_set(): void
    {
        $draw = (new Draw())->setRelation('settings', new DrawSetting(['num_sets' => 1]));
        $fixture = (new Fixture())->setRelation('draw', $draw);
        $validator = new ScoreValidationService();

        $this->assertTrue($validator->validate($fixture, [[3, 1]])['valid']);
        $this->assertSame(
            'This draw allows 1 set per match.',
            $validator->validate($fixture, [[3, 1], [3, 0]])['message'],
        );
    }

    public function test_each_selected_first_to_target_accepts_its_completed_short_set(): void
    {
        $validator = new ScoreValidationService();
        $formats = [
            TennisScoreFormat::ONE_SET_TO_3 => 3,
            TennisScoreFormat::SHORT_SET_4 => 4,
            TennisScoreFormat::ONE_SET_TO_5 => 5,
            TennisScoreFormat::ONE_SET_TO_6 => 6,
        ];

        foreach ($formats as $format => $target) {
            $fixture = $this->fixtureFor($format);
            $this->assertTrue($validator->validate($fixture, [[$target, $target - 1]])['valid'], $format);
            $this->assertFalse($validator->validate($fixture, [[$target + 1, $target - 1]])['valid'], $format);
        }
    }

    public function test_best_of_three_short_formats_require_two_sets_at_the_selected_target(): void
    {
        $validator = new ScoreValidationService();
        $formats = [
            TennisScoreFormat::BEST_OF_3_TO_3 => 3,
            TennisScoreFormat::BEST_OF_3_TO_4 => 4,
            TennisScoreFormat::BEST_OF_3_TO_5 => 5,
            TennisScoreFormat::BEST_OF_3_TO_6 => 6,
        ];

        foreach ($formats as $format => $target) {
            $fixture = $this->fixtureFor($format);
            $this->assertFalse($validator->validate($fixture, [[$target, 1]])['valid'], $format);
            $this->assertTrue($validator->validate($fixture, [[$target, 1], [2, $target], [$target, 0]])['valid'], $format);
        }
    }

    public function test_best_of_five_short_formats_require_three_sets_at_the_selected_target(): void
    {
        $validator = new ScoreValidationService();
        $formats = [
            TennisScoreFormat::BEST_OF_5_TO_3 => 3,
            TennisScoreFormat::BEST_OF_5_TO_4 => 4,
            TennisScoreFormat::BEST_OF_5_TO_5 => 5,
            TennisScoreFormat::BEST_OF_5_TO_6 => 6,
        ];

        foreach ($formats as $format => $target) {
            $fixture = $this->fixtureFor($format);
            $sets = [[$target, 1], [2, $target], [$target, 0], [1, $target], [$target, 2]];
            $this->assertTrue($validator->validate($fixture, $sets)['valid'], $format);
            $this->assertFalse($validator->validate($fixture, array_slice($sets, 0, 2))['valid'], $format);
        }
    }

    public function test_standard_pro_set_match_tiebreak_and_custom_presets_are_enforced(): void
    {
        $validator = new ScoreValidationService();

        $this->assertTrue($validator->validate($this->fixtureFor(TennisScoreFormat::ONE_FULL_SET), [[7, 6]])['valid']);
        $this->assertFalse($validator->validate($this->fixtureFor(TennisScoreFormat::ONE_FULL_SET), [[6, 5]])['valid']);
        $this->assertTrue($validator->validate($this->fixtureFor(TennisScoreFormat::PRO_SET_8), [[9, 8]])['valid']);
        $this->assertTrue($validator->validate($this->fixtureFor(TennisScoreFormat::MATCH_TIEBREAK_10), [[23, 21]])['valid']);
        $this->assertFalse($validator->validate($this->fixtureFor(TennisScoreFormat::BEST_OF_3_FULL), [[6, 4]])['valid']);
        $this->assertTrue($validator->validate($this->fixtureFor(TennisScoreFormat::BEST_OF_3_MATCH_TIEBREAK), [[6, 4], [4, 6], [10, 8]])['valid']);
        $this->assertTrue($validator->validate($this->fixtureFor(TennisScoreFormat::CUSTOM_3), [[3, 1], [1, 3], [5, 4]])['valid']);
    }

    public function test_every_catalog_format_accepts_a_valid_completed_result(): void
    {
        $validator = new ScoreValidationService();

        foreach (TennisScoreFormat::catalog() as $key => $format) {
            $sets = [];
            for ($index = 0; $index < $format['wins_needed']; $index++) {
                $sets[] = match ($format['set_types'][$index]) {
                    'target3' => [3, 1],
                    'target4' => [4, 1],
                    'target5' => [5, 1],
                    'target6' => [6, 1],
                    'full' => [6, 4],
                    'pro8' => [8, 6],
                    'match_tiebreak' => [10, 8],
                    'custom' => [1, 0],
                };
            }

            $this->assertTrue($validator->validate($this->fixtureFor($key), $sets)['valid'], $key);
        }
    }
}
