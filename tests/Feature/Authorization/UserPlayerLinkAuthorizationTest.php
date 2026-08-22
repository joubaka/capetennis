<?php

namespace Tests\Feature\Authorization;

use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserPlayerLinkAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_cannot_modify_another_users_player_links(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $player = Player::factory()->create();

        $this->actingAs($actor)
            ->postJson(route('backend.user.players.store', $target), ['player_id' => $player->id])
            ->assertForbidden();
    }

    public function test_user_can_manage_own_link_and_admin_can_manage_another_user(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $target = User::factory()->create();
        $player = Player::factory()->create([
            'email' => $user->email,
            'dateOfBirth' => '2012-05-17',
        ]);

        $this->actingAs($user)
            ->postJson(route('backend.user.players.store', $user), [
                'player_id' => $player->id,
                'date_of_birth' => '2012-05-17',
                'contact' => $user->email,
            ])
            ->assertOk();

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $otherPlayer = Player::factory()->create();

        $this->actingAs($admin)
            ->postJson(route('backend.user.players.store', $target), ['player_id' => $otherPlayer->id])
            ->assertOk();
    }

    public function test_user_cannot_claim_a_player_already_linked_to_another_family(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);

        $this->actingAs($claimant)
            ->postJson(route('backend.user.players.store', $claimant), ['player_id' => $player->id])
            ->assertForbidden();
    }

    public function test_user_can_unlink_a_legacy_direct_player_link_without_deleting_the_player(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create(['userId' => $user->id]);

        $this->actingAs($user)
            ->deleteJson(route('backend.user.players.destroy', [$user, $player]))
            ->assertOk();

        $this->assertDatabaseHas('players', ['id' => $player->id, 'userId' => 0]);
    }

    public function test_user_can_bulk_unlink_mixed_player_links_atomically(): void
    {
        $user = User::factory()->create();
        $legacy = Player::factory()->create(['userId' => $user->id]);
        $pivot = Player::factory()->create();
        $user->players()->attach($pivot->id);

        $this->actingAs($user)
            ->deleteJson(route('backend.user.players.bulk-destroy', $user), [
                'player_ids' => [$legacy->id, $pivot->id],
            ])
            ->assertOk()
            ->assertJsonPath('removed', 2);

        $this->assertDatabaseHas('players', ['id' => $legacy->id, 'userId' => 0]);
        $this->assertDatabaseMissing('user_players', ['user_id' => $user->id, 'player_id' => $pivot->id]);
    }
}
