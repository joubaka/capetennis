<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerIdentityDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_rejects_values_longer_than_the_player_columns_without_writes(): void
    {
        $user = User::factory()->create();
        $payload = [
            'player_name' => 'Jamie',
            'player_surname' => 'Smith',
            'dob' => '2012-04-03',
            'gender' => 1,
            'cell_nr' => '0820000000',
        ];

        foreach (['player_name', 'player_surname', 'cell_nr'] as $field) {
            $this->actingAs($user)->postJson(route('player.store'), [
                ...$payload,
                $field => str_repeat('a', 256),
            ])->assertUnprocessable()->assertJsonValidationErrors($field);

            $this->assertDatabaseCount('players', 0);
            $this->assertDatabaseCount('user_players', 0);
        }
    }

    public function test_create_accepts_column_length_boundaries_and_normalizes_a_valid_date(): void
    {
        $user = User::factory()->create();
        $name = str_repeat('a', 255);
        $surname = str_repeat('b', 255);
        $cell = str_repeat('1', 255);

        $this->actingAs($user)->postJson(route('player.store'), [
            'player_name' => $name,
            'player_surname' => $surname,
            'dob' => 'April 3, 2012',
            'gender' => 1,
            'cell_nr' => $cell,
        ])->assertCreated()->assertJsonPath('created', true);

        $this->assertDatabaseCount('players', 1);
        $this->assertDatabaseHas('players', [
            'name' => $name,
            'surname' => $surname,
            'dateOfBirth' => '2012-04-03',
            'cellNr' => $cell,
            'userId' => $user->id,
        ]);
    }

    public function test_create_normalizes_date_before_reusing_an_existing_identity(): void
    {
        $existing = Player::factory()->create([
            'name' => 'Amy Lee',
            'surname' => 'Van Wyk',
            'dateOfBirth' => '2012-04-03',
            'cellNr' => '0820000000',
        ]);

        $this->actingAs(User::factory()->create())->postJson(route('player.store'), [
            'player_name' => ' amy   lee ',
            'player_surname' => 'VAN WYK',
            'dob' => 'April 3, 2012',
            'gender' => 2,
            'cell_nr' => '0830000000',
        ])->assertOk()->assertJsonPath('id', $existing->id)->assertJsonPath('created', false);

        $this->assertDatabaseCount('players', 1);
        $this->assertDatabaseCount('user_players', 0);
        $this->assertDatabaseHas('players', [
            'id' => $existing->id,
            'cellNr' => '0820000000',
        ]);
    }

    public function test_self_service_reuses_same_normalized_name_and_birth_date_across_accounts(): void
    {
        $existing = Player::factory()->create([
            'name' => 'Amy Lee',
            'surname' => 'Van Wyk',
            'dateOfBirth' => '2012-04-03',
            'gender' => 1,
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('player.profile.store'), [
            'name' => '  amy   lee ',
            'surname' => ' VAN WYK ',
            'dateOfBirth' => '2012-04-03',
            'gender' => 2,
        ])->assertRedirect(route('backend.dashboard'));

        $this->assertDatabaseCount('players', 1);
        $this->assertDatabaseHas('user_players', [
            'user_id' => $user->id,
            'player_id' => $existing->id,
        ]);
    }

    public function test_same_name_with_different_birth_date_creates_a_distinct_player(): void
    {
        Player::factory()->create([
            'name' => 'Jamie',
            'surname' => 'Smith',
            'dateOfBirth' => '2010-01-01',
        ]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('player.profile.store'), [
            'name' => 'Jamie',
            'surname' => 'Smith',
            'dateOfBirth' => '2011-01-01',
            'gender' => 1,
        ])->assertRedirect(route('backend.dashboard'));

        $this->assertDatabaseCount('players', 2);
    }

    public function test_profile_update_cannot_change_identity_to_an_existing_player(): void
    {
        $user = User::factory()->create();
        $existing = Player::factory()->create([
            'name' => 'Jamie',
            'surname' => 'Smith',
            'dateOfBirth' => '2010-01-01',
        ]);
        $player = Player::factory()->create([
            'name' => 'Different',
            'surname' => 'Player',
            'dateOfBirth' => '2011-01-01',
        ]);
        $user->players()->attach($player->id);

        $this->actingAs($user)->put(route('player.profile.update', $player), [
            'name' => ' jamie ',
            'surname' => 'SMITH',
            'dateOfBirth' => '2010-01-01',
            'gender' => 'Male',
            'cellNr' => '0820000000',
        ])->assertSessionHasErrors('dateOfBirth');

        $this->assertDatabaseHas('players', [
            'id' => $player->id,
            'name' => 'Different',
        ]);
        $this->assertDatabaseHas('players', ['id' => $existing->id]);
    }
}
