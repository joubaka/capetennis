<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerShowRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_resource_show_redirects_to_the_existing_profile_page(): void
    {
        $user = User::factory()->create();
        $player = Player::factory()->create();

        $this->actingAs($user)
            ->get(route('player.show', $player))
            ->assertRedirect(route('backend.player.profile', $player->id));
    }

    public function test_player_resource_show_returns_not_found_for_a_missing_player(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('player.show', 999999))
            ->assertNotFound();
    }
}
