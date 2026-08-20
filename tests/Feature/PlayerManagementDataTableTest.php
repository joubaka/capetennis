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
}
