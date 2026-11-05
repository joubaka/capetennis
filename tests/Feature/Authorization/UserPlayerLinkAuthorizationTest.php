<?php

namespace Tests\Feature\Authorization;

use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
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

    public function test_legacy_owner_can_restore_link_and_staff_link_requires_reason_and_audit(): void
    {
        Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $target = User::factory()->create();
        $player = Player::factory()->create([
            'userId' => $user->id,
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
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->assertDatabaseMissing('user_players', ['user_id' => $target->id, 'player_id' => $otherPlayer->id]);
        $this->actingAs($admin)
            ->postJson(route('backend.user.players.store', $target), ['player_id' => $otherPlayer->id, 'reason' => 'Guardian relationship reviewed'])
            ->assertOk();
        $this->assertDatabaseHas('audit_events', ['action' => 'player.account-linked', 'actor_id' => $admin->id, 'subject_id' => (string) $otherPlayer->id]);
    }

    public function test_unrelated_self_claim_is_denied_without_changing_legacy_or_existing_family_links(): void
    {
        $owner = User::factory()->create();
        $claimant = User::factory()->create();
        $player = Player::factory()->create([
            'userId' => $owner->id,
            'dateOfBirth' => '2012-05-17',
            'email' => 'family@example.test',
        ]);

        $this->actingAs($claimant)
            ->postJson(route('backend.user.players.store', $claimant), [
                'player_id' => $player->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('user_players', [
            'user_id' => $claimant->id,
            'player_id' => $player->id,
        ]);
        $this->assertSame($owner->id, $player->fresh()->userId);
    }

    public function test_super_admin_can_link_a_player_to_their_own_account_without_notifications(): void
    {
        Mail::fake();
        Notification::fake();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $admin = User::factory()->create();
        $admin->assignRole('super-user');
        $owner = User::factory()->create();
        $player = Player::factory()->create(['userId' => $owner->id]);
        $owner->players()->attach($player->id);

        $this->actingAs($admin)->postJson(route('backend.user.players.store', $admin), [
            'player_id' => $player->id,
            'reason' => 'Super admin reviewing the linked player profile in My Tennis',
        ])->assertOk();

        $this->assertDatabaseHas('user_players', ['user_id' => $admin->id, 'player_id' => $player->id]);
        $this->assertDatabaseHas('user_players', ['user_id' => $owner->id, 'player_id' => $player->id]);
        $this->assertDatabaseHas('players', ['id' => $player->id, 'userId' => $owner->id]);
        $this->assertDatabaseHas('audit_events', ['action' => 'player.account-linked', 'actor_id' => $admin->id, 'subject_id' => (string) $player->id]);
        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();
    }

    public function test_existing_pivot_link_is_idempotent_and_legacy_get_mutations_are_retired(): void
    {
        $user = User::factory()->create(); $player = Player::factory()->create();
        $user->players()->attach($player);
        $this->actingAs($user)->postJson(route('backend.user.players.store', $user), ['player_id' => $player->id])->assertOk();
        $this->actingAs($user)->getJson(route('player.add.profile', ['user_id' => $user->id, 'player_id' => $player->id]))->assertGone();
        $pivot = \Illuminate\Support\Facades\DB::table('user_players')->first();
        $this->actingAs($user)->getJson(route('player.remove.profile', $pivot->id))->assertGone();
        $this->assertDatabaseCount('user_players', 1);
    }

    public function test_ambiguous_legacy_post_cannot_bypass_canonical_link_authorization(): void
    {
        $user = User::factory()->create(); $other = User::factory()->create(); $player = Player::factory()->create();
        $this->actingAs($user)->postJson(route('backend.user.player.attach'), ['player_id' => $player->id])->assertForbidden();
        $this->actingAs($user)->postJson(route('backend.user.player.attach'), ['player_id' => $player->id, 'user_id' => $other->id])->assertForbidden();
        $this->assertDatabaseCount('user_players', 0);
    }

    public function test_staff_link_rolls_back_when_required_audit_cannot_be_written(): void
    {
        Role::findOrCreate('admin', 'web');
        $actor = User::factory()->create()->assignRole('admin'); $target = User::factory()->create(); $player = Player::factory()->create();
        $this->mock(\App\Support\Audit\AuditWriter::class)->shouldReceive('record')->once()->andReturn(null);
        try {
            app(\App\Services\PlayerRelationshipAccess::class)->link($actor, $target, $player, 'Guardian relationship reviewed');
            $this->fail('An unaudited relationship was retained');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('audit could not be saved', $error->getMessage());
        }
        $this->assertDatabaseMissing('user_players', ['user_id' => $target->id, 'player_id' => $player->id]);
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
