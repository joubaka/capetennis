<?php

namespace Tests\Feature;

use App\Models\Player;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerManagementDataTableTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_data_is_paginated_and_reports_record_counts(): void
    {
        $user = User::factory()->create();

        Player::factory()->count(30)->create();

        $response = $this->actingAs($user)->getJson(route('player.data', [
            'draw' => 4,
            'start' => 10,
            'length' => 10,
            'order' => [['column' => 0, 'dir' => 'desc']],
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('draw', 4)
            ->assertJsonPath('recordsTotal', 30)
            ->assertJsonPath('recordsFiltered', 30)
            ->assertJsonCount(10, 'data');
    }

    public function test_player_data_searches_server_side_and_returns_renderable_status_fields(): void
    {
        $user = User::factory()->create();
        Player::factory()->create([
            'name' => 'UniqueSearchName',
            'surname' => 'Needle',
            'gender' => 1,
            'profile_updated_at' => now(),
        ]);
        Player::factory()->create(['name' => 'SomeoneElse']);

        $response = $this->actingAs($user)->getJson(route('player.data', [
            'draw' => 2,
            'start' => 0,
            'length' => 25,
            'search' => ['value' => 'UniqueSearchName'],
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('recordsFiltered', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'UniqueSearchName')
            ->assertJsonPath('data.0.gender', 1)
            ->assertJsonPath('data.0.profile_status.status', 'current')
            ->assertJsonPath('data.0.needs_update', false)
            ->assertJsonPath('data.0.is_complete', true);
    }

    public function test_player_data_route_is_registered_before_the_resource_show_route(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/backend/player/data?draw=1&start=0&length=25')
            ->assertOk()
            ->assertJsonStructure([
                'draw',
                'recordsTotal',
                'recordsFiltered',
                'data',
            ]);
    }
    public function test_player_data_never_exposes_private_badges_or_calculates_ratings_for_other_roles(): void
    {
        $this->partialMock(\App\Services\Performance\PlayerSharedAbilityService::class)->shouldNotReceive('badgeSnapshot');
        Player::factory()->create();
        $this->getJson(route('player.data'))->assertUnauthorized();
        foreach ([null, 'admin', 'convenor'] as $role) {
            $user = User::factory()->create();
            if ($role) { \Spatie\Permission\Models\Role::findOrCreate($role, 'web'); $user->assignRole($role); }
            $response = $this->actingAs($user)->getJson(route('player.data'))->assertOk();
            $this->assertArrayNotHasKey('ability_badge_html', $response->json('data.0'));
            $this->assertStringNotContainsString('player-rating-badge', $response->getContent());
            $this->assertStringNotContainsString('confidence_index', $response->getContent());
        }
    }

    public function test_super_admin_player_data_renders_escaped_rating_and_confidence_on_only_the_bounded_page(): void
    {
        \Spatie\Permission\Models\Role::findOrCreate('super-user', 'web');
        $this->actingAs(User::factory()->create()->assignRole('super-user'));
        $players = Player::factory()->count(115)->create();
        $snapshot = [];
        foreach ($players as $player) {
            $snapshot[$player->id] = [['score' => 54.8, 'cohort' => 'u10 boys "<script>bad()</script>', 'component' => 'group',
                'last_played' => '2026-09-01', 'confidence_index' => 0, 'confidence_band' => 'Very low', 'confidence_as_of' => '2026-10-06', 'last_eligible_activity' => '2026-09-01']];
        }
        $this->partialMock(\App\Services\Performance\PlayerSharedAbilityService::class)->shouldReceive('badgeSnapshot')->once()->andReturn($snapshot);
        $response = $this->getJson(route('player.data', ['start' => 10, 'length' => 10000, 'order' => [['column' => 0, 'dir' => 'asc']]]))
            ->assertOk()->assertJsonCount(100, 'data')->assertJsonPath('recordsTotal', 115);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertSame($players->slice(10, 100)->pluck('id')->values()->all(), array_column($response->json('data'), 'id'));
        foreach ($response->json('data') as $row) {
            $html = $row['ability_badge_html'];
            $this->assertStringContainsString('54.8 | Low', $html);
            $this->assertStringNotContainsString('Very low', $html);
            $this->assertStringNotContainsString('C0', $html);
            $this->assertStringContainsString('&quot;&lt;script&gt;', $html);
            $this->assertStringNotContainsString('<script>', $html);
            $this->assertStringNotContainsString('data-rating-player', $html);
        }
        $view = file_get_contents(resource_path('views/backend/player/index.blade.php'));
        $this->assertStringContainsString('row.ability_badge_html', $view);
        $this->assertStringNotContainsString('CTPlayerRatings?.marker', $view);
    }

}
