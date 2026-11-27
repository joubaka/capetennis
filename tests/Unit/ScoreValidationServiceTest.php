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
    public function test_start_at_two_with_deciding_tiebreak_requires_split_sets_and_terminal_points(): void
    {
        $validator=new ScoreValidationService();
        $fixture=$this->fixtureFor(TennisScoreFormat::BEST_OF_3_START_2_MATCH_TIEBREAK);
        $fixture->stage='RR';
        foreach ([[10,0],[10,8],[12,10],[20,18],[999,997]] as $points) {
            foreach ([$points,array_reverse($points)] as $third) $this->assertTrue($validator->validate($fixture,[[6,2],[3,6],$third])['valid']);
        }
        foreach ([[10,9],[11,8],[12,5],[20,10],[7,6],[1000,998],[9,7]] as $third)
            $this->assertFalse($validator->validate($fixture,[[6,2],[3,6],$third])['valid']);
        foreach ([[[6,2],[6,3],[10,8]],[[6,2],[3,6]],[[6,0],[6,2]],[[2,2],[6,3]]] as $sets)
            $this->assertFalse($validator->validate($fixture,$sets)['valid']);
        $this->assertTrue($validator->validate($fixture,[[6,2],[6,3]])['valid']);
        $this->assertTrue($validator->validateRetirement($fixture,[[6,2],[3,6],[0,0]])['valid']);
        $this->assertTrue($validator->validateRetirement($fixture,[[6,2],[3,6],[10,9]])['valid']);
        $this->assertFalse($validator->validateRetirement($fixture,[[6,2],[3,6],[11,8]])['valid']);
        $this->assertFalse($validator->validateRetirement($fixture,[[6,2],[3,6],[12,10]])['valid']);
        $full=$this->fixtureFor(TennisScoreFormat::BEST_OF_3_FULL_START_2);
        $this->assertFalse($validator->validate($full,[[6,2],[3,6],[12,10]])['valid']);
        $normal=$this->fixtureFor(TennisScoreFormat::BEST_OF_3_MATCH_TIEBREAK); $normal->stage='MAIN';
        $this->assertFalse($validator->validate($normal,[[6,0],[0,6],[11,8]])['valid']);
        $this->assertTrue($validator->validate($normal,[[6,0],[0,6],[12,10]])['valid']);
        $full->draw->settings->workflow='round_robin_playoffs';
        $full->draw->settings->playoff_score_format=TennisScoreFormat::BEST_OF_3_START_2_MATCH_TIEBREAK;
        foreach (['MAIN','PLATE','CONS','CHALLENGE'] as $stage) {
            $full->stage=$stage;
            $this->assertTrue($validator->validate($full,[[6,2],[3,6],[12,10]])['valid'],$stage);
            $this->assertTrue($validator->validateRetirement($full,[[6,2],[3,6],[0,0]])['valid'],$stage);
        }
        $full->stage='RR';$this->assertFalse($validator->validate($full,[[6,2],[3,6],[12,10]])['valid']);
    }

    public function test_retirement_keeps_zero_tied_and_partial_scores_but_rejects_impossible_or_finished_matches(): void
    {
        $validator = new ScoreValidationService();
        $fixture = $this->fixtureFor(TennisScoreFormat::BEST_OF_3_FULL);
        foreach ([[[0, 0]], [[3, 3]], [[6, 4]], [[6, 4], [3, 2]], [[6, 4], [4, 6], [6, 6]]] as $sets) {
            $this->assertTrue($validator->validateRetirement($fixture, $sets)['valid']);
        }
        foreach ([[], [[3, 2], [0, 0]], [[6, 4], [6, 4]], [[6, 4], [9, 1]], [[-1, 0]], [[1.5, 0]]] as $sets) {
            $this->assertFalse($validator->validateRetirement($fixture, $sets)['valid']);
        }
        $this->assertTrue($validator->validateRetirement($this->fixtureFor(TennisScoreFormat::MATCH_TIEBREAK_10), [[10, 10]])['valid']);
        $this->assertFalse($validator->validateRetirement($this->fixtureFor(TennisScoreFormat::MATCH_TIEBREAK_10), [[12, 8]])['valid']);
        $this->assertTrue($validator->validateRetirement($this->fixtureFor(TennisScoreFormat::SHORT_SET_4), [[3, 3]])['valid']);
        $this->assertFalse($validator->validateRetirement($this->fixtureFor(TennisScoreFormat::SHORT_SET_4), [[4, 3]])['valid']);
        $this->assertTrue($validator->validateRetirement($this->fixtureFor(TennisScoreFormat::TIMED), [[0, 0]])['valid']);
    }

    private function fixtureFor(string $format): Fixture
    {
        $draw = (new Draw())->setRelation('settings', new DrawSetting(['score_format' => $format]));

        return (new Fixture())->setRelation('draw', $draw);
    }

    public function test_start_at_two_all_requires_two_completed_sets_even_in_round_robin(): void
    {
        $validator = new ScoreValidationService();
        $fixture = $this->fixtureFor(TennisScoreFormat::BEST_OF_3_FULL_START_2);
        foreach (['RR', 'MAIN'] as $stage) {
            $fixture->stage = $stage;
            foreach ([[[6,2],[6,3]], [[2,6],[6,3],[7,6]], [[7,5],[7,6]]] as $sets) {
                $this->assertTrue($validator->validate($fixture,$sets)['valid']);
            }
            foreach ([[[3,2],[3,2]], [[6,2]], [[6,2],[2,6]], [[2,2]], [[6,0],[6,2]], [[6,2],[6,3],[7,5]], [[6,5],[6,2]]] as $sets) {
                $this->assertFalse($validator->validate($fixture,$sets)['valid'], json_encode($sets));
            }
        }
        foreach ([[[2,2]], [[3,2]], [[6,2],[2,3]], [[6,2],[3,6],[6,6]]] as $sets) {
            $this->assertTrue($validator->validateRetirement($fixture,$sets)['valid']);
        }
        foreach ([[[0,0]], [[3,1]], [[6,2],[6,3]], [[3,2],[2,2]], [[8,2]]] as $sets) {
            $this->assertFalse($validator->validateRetirement($fixture,$sets)['valid']);
        }
    }

    public function test_interpro_preset_requires_decisive_results_without_changing_ordinary_rr_draws(): void
    {
        $fixture=$this->fixtureFor(TennisScoreFormat::BEST_OF_3_FULL);
        $fixture->stage='RR';
        $validator=new ScoreValidationService();
        $this->assertTrue($validator->validate($fixture,[[3,3]])['valid']);
        $fixture->draw->settings->preset_key=\App\Services\Draw\InterproRoundRobinFormat::PRESET;
        $fixture->draw->settings->workflow='round_robin_playoffs';
        $this->assertFalse($validator->validate($fixture,[[3,3]])['valid']);
        $this->assertFalse($validator->validate($fixture,[[6,2],[2,6]])['valid']);
        $this->assertTrue($validator->validate($fixture,[[6,2],[2,6],[7,6]])['valid']);
    }

    public function test_stage_format_falls_back_for_legacy_and_selects_independent_playoff_rules(): void
    {
        $settings=new DrawSetting(['score_format'=>TennisScoreFormat::ONE_FULL_SET,'num_sets'=>1]);
        $this->assertSame(TennisScoreFormat::ONE_FULL_SET,$settings->scoreFormatForStage('MAIN'));
        $settings->playoff_score_format=TennisScoreFormat::BEST_OF_3_FULL_START_2;
        $this->assertSame(TennisScoreFormat::ONE_FULL_SET,$settings->scoreFormatForStage('MAIN'));
        $settings->workflow='round_robin_playoffs';
        $this->assertSame(TennisScoreFormat::ONE_FULL_SET,$settings->scoreFormatForStage('RR'));
        $this->assertSame(1,$settings->numSetsForStage('RR'));
        $this->assertSame(3,$settings->numSetsForStage('MAIN'));
        $settings->playoff_score_format='historical_unknown';
        $this->assertSame(1,$settings->numSetsForStage('MAIN'));
        $settings->playoff_score_format=TennisScoreFormat::BEST_OF_3_FULL_START_2;
        foreach (['monrad','feed_in_monrad','playoffs','custom_monrad'] as $workflow) {
            $settings->workflow=$workflow;
            $this->assertSame(TennisScoreFormat::ONE_FULL_SET,$settings->scoreFormatForStage('FM'));
            $this->assertSame(1,$settings->numSetsForStage('FM'));
        }
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
                    'full_start_2' => [6, 4],
                    'pro8' => [8, 6],
                    'match_tiebreak' => [10, 8],
                    'custom' => [1, 0],
                    'timed' => [3, 1],
                };
            }

            $this->assertTrue($validator->validate($this->fixtureFor($key), $sets)['valid'], $key);
        }
    }
    public function test_mixed_stage_validation_uses_the_fixture_stage(): void
    {
        $fixture=$this->fixtureFor(TennisScoreFormat::ONE_FULL_SET);
        $fixture->draw->setRelation('draw_types',null)->setRelation('event',null);
        $fixture->draw->settings->fill(['workflow'=>'round_robin_playoffs','playoff_score_format'=>TennisScoreFormat::TIMED,
            'match_duration_minutes'=>25,'changeover_minutes'=>3]);
        $fixture->registration1_id=10; $fixture->registration2_id=20;
        $validator=new ScoreValidationService();
        $fixture->stage='RR';
        $this->assertFalse($validator->validate($fixture,[[3,3]],10)['valid']);
        $fixture->stage='MAIN';
        $this->assertTrue($validator->validate($fixture,[[3,3]],10)['valid']);
        $this->assertFalse($validator->validate($fixture,[[3,3]])['valid']);
    }

    public function test_timed_matches_accept_one_total_and_only_tied_playoffs_require_a_current_deciding_winner(): void
    {
        $validator = new ScoreValidationService();
        $fixture = $this->fixtureFor(TennisScoreFormat::TIMED);
        $fixture->stage = 'RR';
        $fixture->registration1_id = 10;
        $fixture->registration2_id = 20;
        $this->assertTrue($validator->validate($fixture, [[3, 3]])['valid']);
        $this->assertTrue($validator->validate($fixture, [[0, 0]])['valid']);
        $this->assertTrue($validator->validate($fixture, [[8, 5]])['valid']);
        $this->assertFalse($validator->validate($fixture, [[3, 3]], 10)['valid']);
        $this->assertFalse($validator->validate($fixture, [[3, 3], [1, 0]])['valid']);
        $fixture->stage = 'MAIN';
        $this->assertFalse($validator->validate($fixture, [[3, 3]])['valid']);
        $this->assertFalse($validator->validate($fixture, [[3, 3]], 99)['valid']);
        $this->assertTrue($validator->validate($fixture, [[3, 3]], 10)['valid']);
        $this->assertTrue($validator->validate($fixture, [[3, 3]], 20)['valid']);
        $this->assertFalse($validator->validate($fixture, [[4, 3]], 20)['valid']);
        $this->assertFalse($validator->validate($fixture, [[-1, 3]], 20)['valid']);
        $fixture->registration2_id = null;
        $this->assertFalse($validator->validate($fixture, [[3, 3]], 10)['valid']);
    }

}
