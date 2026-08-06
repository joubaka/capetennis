<?php

namespace Tests\Feature\Authorization;

use Tests\TestCase;

class CriticalMutationRouteAuthorizationTest extends TestCase
{
    public function test_guests_cannot_create_or_modify_events(): void
    {
        $this->post('/events')->assertRedirect('/login');
        $this->patch('/events/1')->assertRedirect('/login');
        $this->delete('/events/1')->assertRedirect('/login');
    }
    public function test_guests_cannot_modify_players_or_ranking_scores(): void
    {
        $this->patch('/backend/player/1')->assertRedirect('/login');
        $this->post('/backend/ranking-scores/1/school')->assertRedirect('/login');
    }
    public function test_guests_cannot_modify_team_fixtures_or_schedules(): void
    {
        $this->post('/backend/team-fixtures')->assertRedirect('/login');
        $this->post('/backend/team-fixtures/1/insert-score')->assertRedirect('/login');
        $this->post('/fixtures/1/save-score')->assertRedirect('/login');
        $this->post('/backend/team-schedule/all-auto/1')->assertRedirect('/login');
        $this->post('/schedule/create')->assertRedirect('/login');
    }
}
