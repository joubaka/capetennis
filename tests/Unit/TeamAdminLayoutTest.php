<?php

namespace Tests\Unit;

use Tests\TestCase;

class TeamAdminLayoutTest extends TestCase
{
    public function test_player_region_content_uses_the_scoped_flush_layout(): void
    {
        $players = file_get_contents(
            resource_path('views/backend/adminPage/admin_show/tabs/players.blade.php')
        );

        $this->assertStringContainsString('tab-content region-tab-content', $players);
        $this->assertStringContainsString('player-global-actions', $players);
        $this->assertStringContainsString('region-email-actions', $players);
        $this->assertStringContainsString('team-player-table', $players);
        $this->assertStringContainsString('<colgroup>', $players);
    }

    public function test_team_admin_spacing_and_mobile_rules_are_scoped(): void
    {
        $layout = file_get_contents(
            resource_path('views/backend/adminPage/admin_show/team_show.blade.php')
        );

        $this->assertStringContainsString('class="team-admin-workspace" data-backend-wide', $layout);
        $this->assertStringContainsString('.team-admin-workspace .region-tab-content', $layout);
        $this->assertStringContainsString('.team-admin-workspace .team-player-table', $layout);
        $this->assertStringNotContainsString("\n    .tab-content { padding: .5rem !important; }", $layout);
        $this->assertSame(0, preg_match('/class="col-xl-12"/', $layout));
    }
}
