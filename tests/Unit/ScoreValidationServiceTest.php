<?php

namespace Tests\Unit;

use App\Domain\Draws\Services\ScoreValidationService;
use App\Models\Draw;
use App\Models\DrawSetting;
use App\Models\Fixture;
use PHPUnit\Framework\TestCase;

class ScoreValidationServiceTest extends TestCase
{
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
}
