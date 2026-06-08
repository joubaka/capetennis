<?php

declare(strict_types=1);

namespace Tests\Feature\Doubles;

use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RegistrationDisplayNameTest
 *
 * Covers Registration::displayName() and Registration::displayShortName()
 * for all expected cases: no players, one player, two players, ordering.
 */
class RegistrationDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeRegistration(): Registration
    {
        return Registration::factory()->create();
    }

    private function makePlayer(string $name, string $surname): Player
    {
        return Player::factory()->create([
            'name'    => $name,
            'surname' => $surname,
        ]);
    }

    private function attachPlayer(Registration $reg, Player $player): void
    {
        PlayerRegistration::create([
            'registration_id' => $reg->id,
            'player_id'       => $player->id,
        ]);
    }

    // -----------------------------------------------------------------------
    // displayName() tests
    // -----------------------------------------------------------------------

    /** @test */
    public function display_name_returns_unassigned_when_no_players(): void
    {
        $reg = $this->makeRegistration();

        $this->assertSame('Unassigned', $reg->displayName());
    }

    /** @test */
    public function display_name_returns_full_name_for_single_player(): void
    {
        $reg    = $this->makeRegistration();
        $player = $this->makePlayer('John', 'Smith');
        $this->attachPlayer($reg, $player);

        $this->assertSame('John Smith', $reg->displayName());
    }

    /** @test */
    public function display_name_returns_slash_separated_names_for_two_players(): void
    {
        $reg     = $this->makeRegistration();
        $player1 = $this->makePlayer('John', 'Smith');
        $player2 = $this->makePlayer('Peter', 'Jones');
        $this->attachPlayer($reg, $player1);
        $this->attachPlayer($reg, $player2);

        // Names are sorted by surname: Jones first, then Smith
        $this->assertSame('Peter Jones / John Smith', $reg->displayName());
    }

    /** @test */
    public function display_name_orders_by_surname_alphabetically(): void
    {
        $reg = $this->makeRegistration();
        // Add in reverse alpha order intentionally
        $this->attachPlayer($reg, $this->makePlayer('Zara', 'Williams'));
        $this->attachPlayer($reg, $this->makePlayer('Alice', 'Adams'));

        // Adams before Williams
        $this->assertSame('Alice Adams / Zara Williams', $reg->displayName());
    }

    /** @test */
    public function display_name_attribute_matches_display_name_method(): void
    {
        $reg    = $this->makeRegistration();
        $player = $this->makePlayer('Jane', 'Doe');
        $this->attachPlayer($reg, $player);

        $this->assertSame($reg->displayName(), $reg->display_name);
    }

    // -----------------------------------------------------------------------
    // displayShortName() tests
    // -----------------------------------------------------------------------

    /** @test */
    public function display_short_name_returns_unassigned_when_no_players(): void
    {
        $reg = $this->makeRegistration();

        $this->assertSame('Unassigned', $reg->displayShortName());
    }

    /** @test */
    public function display_short_name_returns_surname_only_for_single_player(): void
    {
        $reg    = $this->makeRegistration();
        $player = $this->makePlayer('John', 'Smith');
        $this->attachPlayer($reg, $player);

        $this->assertSame('Smith', $reg->displayShortName());
    }

    /** @test */
    public function display_short_name_returns_slash_separated_surnames_for_two_players(): void
    {
        $reg     = $this->makeRegistration();
        $player1 = $this->makePlayer('John', 'Smith');
        $player2 = $this->makePlayer('Peter', 'Jones');
        $this->attachPlayer($reg, $player1);
        $this->attachPlayer($reg, $player2);

        // Sorted by surname: Jones / Smith
        $this->assertSame('Jones / Smith', $reg->displayShortName());
    }

    /** @test */
    public function display_short_name_orders_by_surname_alphabetically(): void
    {
        $reg = $this->makeRegistration();
        $this->attachPlayer($reg, $this->makePlayer('Zara', 'Williams'));
        $this->attachPlayer($reg, $this->makePlayer('Alice', 'Adams'));

        $this->assertSame('Adams / Williams', $reg->displayShortName());
    }
}
