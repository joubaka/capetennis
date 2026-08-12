<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SuperAdminPlayerDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private User $superUser;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'super-user', 'guard_name' => 'web']);
        $this->superUser = User::factory()->create();
        $this->superUser->assignRole('super-user');
    }

    public function test_only_super_users_can_access_duplicate_review(): void
    {
        $this->get(route('superadmin.player-duplicates.index'))->assertRedirect();
        $this->actingAs(User::factory()->create())
            ->get(route('superadmin.player-duplicates.index'))
            ->assertForbidden();
        $this->actingAs($this->superUser)
            ->get(route('superadmin.player-duplicates.index'))
            ->assertOk();
    }

    public function test_scan_shows_matching_names_and_linked_emails(): void
    {
        $owner = User::factory()->create(['email' => 'parent@example.test']);
        $first = Player::factory()->create(['name' => '  Jamie', 'surname' => 'Smith ', 'email' => 'player@example.test']);
        $second = Player::factory()->create(['name' => 'jamie', 'surname' => 'SMITH']);
        DB::table('user_players')->insert(['user_id' => $owner->id, 'player_id' => $first->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->superUser)
            ->get(route('superadmin.player-duplicates.index'))
            ->assertOk()
            ->assertSee('Jamie Smith')
            ->assertSee('parent@example.test')
            ->assertSee('player@example.test')
            ->assertSee("#{$second->id}");
    }

    public function test_approved_merge_transfers_owner_and_deletes_only_empty_profile(): void
    {
        $keeperOwner = User::factory()->create();
        $emptyOwner = User::factory()->create();
        $keep = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith', 'userId' => $keeperOwner->id]);
        $remove = Player::factory()->create(['name' => 'jamie', 'surname' => ' smith ', 'userId' => $emptyOwner->id]);

        $this->actingAs($this->superUser)->post(route('superadmin.player-duplicates.merge'), [
            'keep_player_id' => $keep->id,
            'remove_player_id' => $remove->id,
            'confirmation' => 'MERGE',
        ])->assertRedirect(route('superadmin.player-duplicates.index'));

        $this->assertDatabaseMissing('players', ['id' => $remove->id]);
        $this->assertDatabaseHas('players', ['id' => $keep->id]);
        $this->assertDatabaseHas('user_players', ['user_id' => $emptyOwner->id, 'player_id' => $keep->id]);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'player-profile-merge', 'subject_id' => $keep->id]);
    }

    public function test_merge_is_rejected_when_profile_selected_for_removal_is_in_use(): void
    {
        $keep = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith']);
        $remove = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith']);
        $registrationId = DB::table('registrations')->insertGetId(['created_at' => now(), 'updated_at' => now()]);
        DB::table('player_registrations')->insert(['registration_id' => $registrationId, 'player_id' => $remove->id, 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($this->superUser)->post(route('superadmin.player-duplicates.merge'), [
            'keep_player_id' => $keep->id,
            'remove_player_id' => $remove->id,
            'confirmation' => 'MERGE',
        ])->assertSessionHasErrors('remove_player_id');

        $this->assertDatabaseHas('players', ['id' => $remove->id]);
        $this->assertDatabaseHas('player_registrations', ['player_id' => $remove->id]);
    }

    public function test_merge_rejects_different_names_and_requires_exact_confirmation(): void
    {
        $keep = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Smith']);
        $remove = Player::factory()->create(['name' => 'Jamie', 'surname' => 'Jones']);

        $this->actingAs($this->superUser)->post(route('superadmin.player-duplicates.merge'), [
            'keep_player_id' => $keep->id,
            'remove_player_id' => $remove->id,
            'confirmation' => 'yes',
        ])->assertSessionHasErrors('confirmation');

        $this->actingAs($this->superUser)->post(route('superadmin.player-duplicates.merge'), [
            'keep_player_id' => $keep->id,
            'remove_player_id' => $remove->id,
            'confirmation' => 'MERGE',
        ])->assertSessionHasErrors('remove_player_id');

        $this->assertDatabaseHas('players', ['id' => $remove->id]);
    }
}
