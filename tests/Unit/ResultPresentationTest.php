<?php

namespace Tests\Unit;

use App\Models\{Draw, Fixture, FixtureResult, TeamFixture, TeamFixtureResult, TeamTie};
use App\Support\ResultPresentation;
use Tests\TestCase;

class ResultPresentationTest extends TestCase
{
    private function individual(array $scores, ?int $stored = null): Fixture
    {
        $fixture = new Fixture(['registration1_id' => 11, 'registration2_id' => 22, 'winner_registration' => $stored]);
        $draw = new Draw;
        $draw->setRelation('settings', null);
        $fixture->setRelation('draw', $draw);
        $fixture->setRelation('fixtureResults', collect($scores)->map(fn ($score, $index) => new FixtureResult([
            'set_nr' => $index + 1, 'registration1_score' => $score[0], 'registration2_score' => $score[1],
        ])));
        return $fixture;
    }

    private function rubber(array $scores): TeamFixture
    {
        $fixture = new TeamFixture(['fixture_type' => 1, 'numSets' => 3]);
        $fixture->setRelation('draw', new Draw(['team_scoring_rules' => null]));
        $fixture->setRelation('teamResults', collect($scores)->map(fn ($score, $index) => new TeamFixtureResult([
            'set_nr' => $index + 1, 'team1_score' => $score[0], 'team2_score' => $score[1],
        ])));
        return $fixture;
    }

    public function test_exact_participants_unknown_winners_and_byes(): void
    {
        $this->assertSame(['winner-home', 'loser-home'], ResultPresentation::registrationClasses('11', 11, 22));
        $this->assertSame(['loser-home', 'winner-home'], ResultPresentation::registrationClasses(22, 11, 22));
        $this->assertSame(['', ''], ResultPresentation::registrationClasses(99, 11, 22));
        $this->assertSame(['', ''], ResultPresentation::registrationClasses(null, null, null));
        $this->assertSame(['winner-home', ''], ResultPresentation::registrationClasses(11, 11, null));
    }

    public function test_completed_match_uses_aggregate_outcome_over_stale_last_set_pointer(): void
    {
        $this->assertSame(['winner-home', 'loser-home'], ResultPresentation::classes($this->individual([[6, 2], [6, 3], [2, 6]], 22)));
        $this->assertSame(['loser-home', 'winner-home'], ResultPresentation::classes($this->individual([[2, 6], [3, 6]])));
        $this->assertSame(['', ''], ResultPresentation::classes($this->individual([[6, 2]], 11)));
        $this->assertSame(['', ''], ResultPresentation::classes($this->individual([[6, 2], [2, 6]])));
    }

    public function test_team_partial_scores_and_drawn_ties_remain_neutral(): void
    {
        $home = $this->rubber([[6, 2], [6, 3]]);
        $away = $this->rubber([[2, 6], [3, 6]]);
        $partial = $this->rubber([[6, 2]]);
        $this->assertSame(['winner-home', 'loser-home'], ResultPresentation::classes($home));
        $this->assertSame(['', ''], ResultPresentation::classes($partial));
        $this->assertSame(['', ''], ResultPresentation::tieClasses(collect([$home, $away])));
        $this->assertSame(['', ''], ResultPresentation::tieClasses(collect([$home, $partial])));
        $this->assertSame(['winner-home', 'loser-home'], ResultPresentation::tieClasses(collect([$home, $home, $away])));
    }

    public function test_result_label_has_visible_outcome_and_neutral_has_no_label(): void
    {
        $this->assertStringContainsString('Won', view('components.result-label', ['outcome' => 'winner-home'])->render());
        $this->assertStringContainsString('Lost', view('components.result-label', ['outcome' => 'loser-home'])->render());
        $this->assertStringNotContainsString('result-label', view('components.result-label', ['outcome' => ''])->render());
    }

    public function test_modern_tie_requires_displayed_required_rubbers_and_matching_draw(): void
    {
        $tie = new TeamTie(['draw_id' => 7, 'format_snapshot' => ['rubbers' => [
            ['sequence' => 1], ['sequence' => 2],
        ]]]);
        $home = $this->rubber([[6, 2], [6, 3]]);
        $second = $this->rubber([[6, 1], [6, 2]]);
        foreach ([$home, $second] as $index => $fixture) {
            $fixture->draw_id = 7;
            $fixture->team_tie_id = 8;
            $fixture->rubber_sequence = $index + 1;
            $fixture->setRelation('teamTie', $tie);
        }
        $this->assertSame(['', ''], ResultPresentation::tieClasses(collect([$home])));
        $this->assertSame(['winner-home', 'loser-home'], ResultPresentation::tieClasses(collect([$home, $second])));
        $tie->draw_id = 99;
        $this->assertSame(['', ''], ResultPresentation::tieClasses(collect([$home, $second])));
    }
}
