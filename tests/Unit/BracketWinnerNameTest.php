<?php

namespace Tests\Unit;

use App\Models\Fixture;
use App\Models\FixtureResult;
use App\Models\Player;
use App\Models\Registration;
use App\Services\Draw\Brackets;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class BracketWinnerNameTest extends TestCase
{
    public function test_unplayed_fixture_has_no_winner_name(): void
    {
        $fixture = (new Fixture)->setRelation('fixtureResults', new Collection);

        $this->assertSame('', Brackets::get_winner_name($fixture));
    }

    public function test_result_without_winning_registration_has_no_winner_name(): void
    {
        $result = (new FixtureResult)->setRelation('w_registration', null);
        $fixture = (new Fixture)->setRelation('fixtureResults', new Collection([$result]));

        $this->assertSame('', Brackets::get_winner_name($fixture));
    }

    public function test_winning_registration_without_players_has_no_winner_name(): void
    {
        $registration = (new Registration)->setRelation('players', new Collection);
        $result = (new FixtureResult)->setRelation('w_registration', $registration);
        $fixture = (new Fixture)->setRelation('fixtureResults', new Collection([$result]));

        $this->assertSame('', Brackets::get_winner_name($fixture));
    }

    public function test_winner_name_uses_first_available_collection_item_even_with_nonzero_keys(): void
    {
        $player = new Player;
        $player->name = 'Test';
        $player->surname = 'Winner';
        $registration = (new Registration)->setRelation('players', new Collection([7 => $player]));
        $result = (new FixtureResult)->setRelation('w_registration', $registration);
        $fixture = (new Fixture)->setRelation('fixtureResults', new Collection([4 => $result]));

        $this->assertSame('Test Winner', Brackets::get_winner_name($fixture));
    }
}
