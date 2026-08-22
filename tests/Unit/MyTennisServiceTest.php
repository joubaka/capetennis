<?php

namespace Tests\Unit;

use App\Models\Player;
use App\Models\User;
use App\Services\MyTennisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyTennisServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_switcher_combines_legacy_and_pivot_links_without_cross_family_leakage(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $pivotPlayer = Player::factory()->create();
        $legacyPlayer = Player::factory()->create(['userId' => $user->id]);
        $otherPlayer = Player::factory()->create(['userId' => $otherUser->id]);

        $user->players()->attach($pivotPlayer);
        $service = app(MyTennisService::class);

        $ids = $service->playersFor($user)->pluck('id')->all();

        $this->assertEqualsCanonicalizing([$pivotPlayer->id, $legacyPlayer->id], $ids);
        $this->assertNotContains($otherPlayer->id, $ids);
    }
}
