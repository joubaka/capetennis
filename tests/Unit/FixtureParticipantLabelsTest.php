<?php

namespace Tests\Unit;

use App\Models\{Draw, Fixture, FlexibleMonradDraw, Player, Registration};
use App\Services\Scoring\FixtureParticipantLabels;
use PHPUnit\Framework\TestCase;

class FixtureParticipantLabelsTest extends TestCase
{
    private function fixture(array $sources): Fixture
    {
        $draw = (new Draw)->forceFill(['id' => 10]);
        $draw->setRelation('flexibleMonrad', (new FlexibleMonradDraw)->forceFill([
            'draw_id' => 10, 'graph' => ['nodes' => ['target' => ['sources' => $sources]]],
            'fixture_map' => ['opening_a' => 101, 'opening_b' => 102, 'target' => 103],
        ]));
        $fixture = (new Fixture)->forceFill(['id' => 103, 'draw_id' => 10]);
        $fixture->setRelation('draw', $draw)->setRelation('registration1', null)->setRelation('registration2', null);

        return $fixture;
    }

    private function sources(): \Illuminate\Support\Collection
    {
        return collect([
            (new Fixture)->forceFill(['id' => 101, 'draw_id' => 10, 'match_nr' => 4]),
            (new Fixture)->forceFill(['id' => 102, 'draw_id' => 10, 'match_nr' => 9]),
        ]);
    }

    public function test_winner_and_loser_sources_use_actual_match_numbers_without_resolving_players(): void
    {
        $fixture = $this->fixture([['type' => 'winner', 'match' => 'opening_a'], ['type' => 'loser', 'match' => 'opening_b']]);
        $before = $fixture->getAttributes();
        $this->assertSame(['Winner of Match 4', 'Loser of Match 9'],
            (new FixtureParticipantLabels)->forFixture($fixture, $this->sources()));
        $this->assertSame($before, $fixture->getAttributes());
    }

    public function test_resolved_name_takes_precedence_and_position_sources_remain_explicit(): void
    {
        $fixture = $this->fixture([['type' => 'loser', 'match' => 'opening_a'], ['type' => 'winner', 'match' => 'opening_b', 'base_position' => 3]]);
        $registration = new Registration;
        $registration->setRelation('players', collect([(new Player)->forceFill(['name' => 'Alex', 'surname' => 'Example'])]));
        $fixture->setRelation('registration1', $registration);
        $this->assertSame(['Alex Example', 'Base position 3'],
            (new FixtureParticipantLabels)->forFixture($fixture, $this->sources()));
    }

    public function test_foreign_draw_mapping_cannot_supply_a_match_label(): void
    {
        $fixture = $this->fixture([['type' => 'winner', 'match' => 'opening_a'], ['type' => 'loser', 'match' => 'opening_b']]);
        $foreign = $this->sources()->each(fn ($source) => $source->draw_id = 99);
        $this->assertSame(['To be decided', 'To be decided'],
            (new FixtureParticipantLabels)->forFixture($fixture, $foreign));
        $fixture->draw->flexibleMonrad->draw_id = 99;
        $this->assertSame(['To be decided', 'To be decided'],
            (new FixtureParticipantLabels)->forFixture($fixture, $this->sources()));
    }

    public function test_original_source_and_bye_labels_survive_display_node_collapsing(): void
    {
        $fixture = $this->fixture([['type' => 'loser', 'original_match' => 'opening_a', 'match' => 'opening_b'], ['type' => 'bye']]);
        $this->assertSame(['Loser of Match 4', 'Bye'],
            (new FixtureParticipantLabels)->forFixture($fixture, $this->sources()));
    }
}
