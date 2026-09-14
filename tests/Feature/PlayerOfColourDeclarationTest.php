<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlayerOfColourDeclarationTest extends TestCase
{
    use RefreshDatabase;

    public function test_linked_user_can_declare_player_of_colour_while_confirming_details(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['is_player_of_colour' => null]);
        $user->players()->attach($player->id);

        $this->actingAs($user)
            ->postJson(route('register.update.player.details'), $this->detailsPayload($player, '1'))
            ->assertOk();

        $player->refresh();

        $this->assertTrue($player->is_player_of_colour);
        $this->assertNotNull($player->player_of_colour_declared_at);
        $this->assertSame($user->id, $player->player_of_colour_declared_by_user_id);
    }

    public function test_unlinked_user_cannot_change_player_of_colour_declaration(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['is_player_of_colour' => null]);

        $this->actingAs($user)
            ->postJson(route('register.update.player.details'), $this->detailsPayload($player, '1'))
            ->assertOk();

        $this->assertNull($player->refresh()->is_player_of_colour);
    }

    public function test_admin_can_manage_declaration_for_any_player(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $admin = User::factory()->create()->assignRole('admin');
        $player = Player::factory()->create(['is_player_of_colour' => null]);

        $this->actingAs($admin)
            ->postJson(route('register.update.player.details'), $this->detailsPayload($player, '0'))
            ->assertOk();

        $this->assertFalse($player->refresh()->is_player_of_colour);
        $this->assertSame($admin->id, $player->player_of_colour_declared_by_user_id);
    }

    public function test_declaration_is_hidden_from_normal_player_json(): void
    {
        $player = Player::factory()->create(['is_player_of_colour' => true]);

        $this->assertArrayNotHasKey('is_player_of_colour', $player->toArray());
        $this->assertArrayNotHasKey('player_of_colour_declared_at', $player->toArray());
        $this->assertArrayNotHasKey('player_of_colour_declared_by_user_id', $player->toArray());
    }

    private function detailsPayload(Player $player, string $declaration): array
    {
        return [
            'player_id' => $player->id,
            'name' => $player->name,
            'surname' => $player->surname,
            'email' => $player->email,
            'cellNr' => $player->cellNr,
            'dateOfBirth' => $player->dateOfBirth,
            'gender' => $player->gender === 1 ? 'Male' : 'Female',
            'is_player_of_colour' => $declaration,
        ];
    }
}
