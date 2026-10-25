<?php

namespace Tests\Feature\TeamDraw;

use App\Models\{Draw, Event, TeamTie};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicStandingsPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_view_preserves_canonical_positions_and_exposes_match_statistics(): void
    {
        $html = $this->renderStandings();

        $this->assertStringContainsString('<tr data-position="1"', $html);
        $this->assertStringContainsString('data-match-points>3', $html);
        $this->assertStringContainsString('data-tie-points>1', $html);
        $this->assertStringContainsString('Total points', $html);
        $this->assertStringContainsString('2 won · 1 lost · 3 matches', $html);
        $this->assertStringContainsString('data-team-round-robin-matrix', $html);
        $this->assertStringContainsString('Read each result from the row team', $html);
        $this->assertStringContainsString('<strong>2–1</strong>', $html);
        $this->assertStringContainsString('<strong>1–2</strong>', $html);
        $this->assertStringContainsString('Round 2', $html);
        $this->assertStringContainsString('position:sticky', $html);
        $this->assertStringNotContainsString('data-gender-points', $html);
        $this->assertStringNotContainsString('class="team-rr-cell" href=', $html);
        $this->assertStringContainsString('Points breakdown and match statistics', $html);
        $this->assertLessThan(strpos($html, 'Alpha School'), strpos($html, 'Zulu School'));
        $this->assertStringContainsString('may change while play continues', $html);

        if (getenv('PUBLIC_STANDINGS_SNAPSHOT')) {
            $path = storage_path('framework/testing/public-standings.html');
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }
            file_put_contents($path, $html);
        }
    }

    public function test_incompatible_scoring_and_unplayed_rows_do_not_show_positions(): void
    {
        $html = $this->renderStandings(mixed: true);
        $this->assertStringNotContainsString('<tr data-position="1"', $html);
        $this->assertStringContainsString('listed alphabetically', $html);

        $html = $this->renderStandings(unplayed: true);
        $this->assertStringNotContainsString('<tr data-position="1"', $html);
        $this->assertStringContainsString('No completed match points yet.', $html);
    }

    private function renderStandings(bool $mixed = false, bool $unplayed = false): string
    {
        $event = new Event(['name' => 'Schools tournament']);
        $event->id = 123;
        $row = ['rank' => $mixed ? null : 1, 'points' => 4, 'match_points' => 3, 'tie_points' => 1,
            'rubber_wins' => $unplayed ? 0 : 2, 'rubber_losses' => $unplayed ? 0 : 1,
            'sets_for' => 4, 'sets_against' => 2, 'games_for' => 30, 'games_against' => 20,
            'gender_points' => ['boys' => 2, 'girls' => 1, 'mixed' => 1, 'other' => 0]];
        $rows = [['name' => 'Zulu School'] + $row, ['name' => 'Alpha School'] + $row];
        $draw = new Draw(['drawName' => 'U10 round robin']);
        $draw->id = 456;
        $tie = new TeamTie(['round_nr' => 2]);
        $tie->id = 789;
        $matrixRows = [];
        foreach ($rows as $index => $standing) {
            $matrixRows[] = $standing + ['team_id' => $index + 1, 'played' => 1, 'wins' => $index === 0 ? 1 : 0, 'draws' => 0, 'losses' => $index === 0 ? 0 : 1];
        }
        $cell = ['tie' => $tie, 'status' => 'Complete', 'canScore' => true];

        return view('frontend.fixtures.event-standings', [
            'event' => $event, 'options' => ['age' => ['U10']], 'filters' => [],
            'ageStandings' => ['U10' => $rows],
            'ageGroups' => ['U10' => collect(array_reverse($rows))->map(fn ($row) => $row + ['color' => '#173f7a'])->all()],
            'ageMixedRules' => ['U10' => $mixed],
            'publicMatrices' => ['U10' => [['draw' => $draw, 'rows' => $matrixRows, 'cells' => [
                1 => [2 => [$cell + ['for' => 2, 'against' => 1]]],
                2 => [1 => [$cell + ['for' => 1, 'against' => 2]]],
            ]]]],
        ])->render();
    }
}

